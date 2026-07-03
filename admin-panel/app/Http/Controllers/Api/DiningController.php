<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\DiningBooking;
use App\Models\Restaurant;
use App\Models\CelebrationType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class DiningController extends Controller
{
    public function getCelebrationTypes()
    {
        $types = CelebrationType::where('is_active', true)
            ->orderBy('display_order')
            ->get();
            
        return response()->json(['success' => true, 'data' => $types]);
    }
    
    public function getRestaurantsForDining(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'radius' => 'nullable|integer|min:1|max:50',
        ]);
        
        $radius = $request->radius ?? 10;
        
        $distanceSql = "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))";

        $restaurants = Restaurant::where(function ($query) {
                $query->where('restaurant_type', 'dining')
                      ->orWhere('restaurant_type', 'both');
            })
            ->where('is_open', true)
            ->select('*')
            ->selectRaw("{$distanceSql} AS distance", [$request->lat, $request->lng, $request->lat])
            ->whereRaw("{$distanceSql} <= ?", [$request->lat, $request->lng, $request->lat, $radius])
            ->orderBy('distance')
            ->limit(50)
            ->get();
            
        return response()->json(['success' => true, 'data' => $restaurants]);
    }
    
    public function bookTable(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'restaurant_id' => 'required|exists:restaurants,id',
            'booking_date' => 'required|date|after_or_equal:today',
            'booking_time' => 'required|date_format:H:i',
            'number_of_guests' => 'required|integer|min:1|max:50',
            'celebration_type' => 'nullable|string',
            'special_requests' => 'nullable|string|max:500',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        
        $restaurant = Restaurant::find($request->restaurant_id);
        
        // Check if restaurant accepts dining bookings
        if (!$restaurant->acceptsService('dining')) {
            return response()->json(['success' => false, 'message' => 'Restaurant does not accept dining bookings'], 400);
        }
        
        // Check for existing booking at same time
        $existingBooking = DiningBooking::where('restaurant_id', $request->restaurant_id)
            ->where('booking_date', $request->booking_date)
            ->where('booking_time', $request->booking_time)
            ->whereIn('status', ['pending', 'confirmed'])
            ->exists();
            
        if ($existingBooking) {
            return response()->json(['success' => false, 'message' => 'Time slot not available'], 400);
        }
        
        $booking = DiningBooking::create([
            'restaurant_id' => $request->restaurant_id,
            'user_id' => auth()->id(),
            'booking_date' => $request->booking_date,
            'booking_time' => $request->booking_time,
            'number_of_guests' => $request->number_of_guests,
            'celebration_type' => $request->celebration_type,
            'special_requests' => $request->special_requests,
            'status' => 'pending',
            'booking_charge' => $restaurant->dining_charge ?? 0,
            'payment_status' => ($restaurant->dining_charge ?? 0) > 0 ? 'pending' : 'success',
        ]);
        
        return response()->json([
            'success' => true,
            'message' => ($restaurant->dining_charge ?? 0) > 0
                ? 'Booking created. Complete payment to confirm your table request.'
                : 'Booking request sent. Waiting for restaurant confirmation.',
            'data' => $booking
        ], 201);
    }

    public function createPayment(Request $request, $id)
    {
        $request->validate([
            'payment_method' => 'nullable|in:razorpay,stripe,cashfree,card,upi',
        ]);

        $booking = DiningBooking::where('user_id', auth()->id())->findOrFail($id);

        if ((float) $booking->booking_charge <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'This booking does not require online payment.'
            ], 400);
        }

        if ($booking->payment_status === 'success') {
            return response()->json([
                'success' => false,
                'message' => 'Payment has already been completed for this booking.'
            ], 400);
        }

        $paymentMethod = in_array($request->payment_method, ['card', 'upi'], true)
            ? AppSetting::getValue('payment_gateway_provider', 'razorpay')
            : ($request->payment_method ?: AppSetting::getValue('payment_gateway_provider', 'razorpay'));

        if ($paymentMethod === 'razorpay') {
            $key = AppSetting::getValue('razorpay_key', config('services.razorpay.key'));
            $secret = AppSetting::getValue('razorpay_secret', config('services.razorpay.secret'));

            if (!$key || !$secret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Razorpay is not configured.'
                ], 503);
            }

            $orderData = [
                'receipt' => 'dining_' . $booking->booking_number,
                'amount' => (int) round($booking->booking_charge * 100),
                'currency' => strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR'),
                'payment_capture' => 1,
            ];

            $razorpayOrder = Http::withBasicAuth($key, $secret)
                ->acceptJson()
                ->asJson()
                ->post('https://api.razorpay.com/v1/orders', $orderData);

            if (!$razorpayOrder->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to create Razorpay order.'
                ], 502);
            }

            $razorpayOrderData = $razorpayOrder->json();
            $booking->update([
                'payment_method' => 'razorpay',
                'gateway_order_id' => $razorpayOrderData['id'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_method' => 'razorpay',
                    'order_id' => $razorpayOrderData['id'],
                    'amount' => $razorpayOrderData['amount'],
                    'currency' => $razorpayOrderData['currency'],
                    'key' => $key,
                ]
            ]);
        }

        if ($paymentMethod === 'stripe') {
            $stripeSecret = AppSetting::getValue('stripe_secret', config('services.stripe.secret'));
            $stripeKey = AppSetting::getValue('stripe_key', config('services.stripe.key'));

            if (!$stripeSecret || !$stripeKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe is not configured.'
                ], 503);
            }

            Stripe::setApiKey($stripeSecret);
            $currency = strtolower(AppSetting::getValue('currency_code', 'INR') ?: 'INR');
            $paymentIntent = PaymentIntent::create([
                'amount' => (int) round($booking->booking_charge * 100),
                'currency' => $currency,
                'metadata' => [
                    'booking_id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                ],
            ]);

            $booking->update(['payment_method' => 'stripe']);

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_method' => 'stripe',
                    'client_secret' => $paymentIntent->client_secret,
                    'publishable_key' => $stripeKey,
                ]
            ]);
        }

        if ($paymentMethod === 'cashfree') {
            $clientId = AppSetting::getValue('cashfree_client_id', config('services.cashfree.client_id'));
            $clientSecret = AppSetting::getValue('cashfree_client_secret', config('services.cashfree.client_secret'));
            $apiVersion = config('services.cashfree.api_version', '2022-09-01');

            if (!$clientId || !$clientSecret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cashfree is not configured.'
                ], 503);
            }

            $cashfreeOrder = Http::withHeaders([
                'x-api-version' => $apiVersion,
                'x-client-id' => $clientId,
                'x-client-secret' => $clientSecret,
            ])->post($this->cashfreeBaseUrl() . '/pg/orders', [
                'order_id' => 'DINE_' . $booking->id . '_' . time(),
                'order_amount' => round($booking->booking_charge, 2),
                'order_currency' => strtoupper(AppSetting::getValue('currency_code', 'INR') ?: 'INR'),
                'customer_details' => [
                    'customer_id' => 'CUST_' . $booking->user_id,
                    'customer_email' => $booking->user?->email ?? '',
                    'customer_phone' => $booking->user?->phone ?? '',
                ],
            ]);

            if ($cashfreeOrder->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to create Cashfree booking payment: ' . $cashfreeOrder->body(),
                ], 502);
            }

            $gatewayData = $cashfreeOrder->json();
            $booking->update([
                'payment_method' => 'cashfree',
                'gateway_order_id' => $gatewayData['order_id'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_method' => 'cashfree',
                    'order_id' => $gatewayData['order_id'] ?? null,
                    'payment_session_id' => $gatewayData['payment_session_id'] ?? null,
                    'order_token' => $gatewayData['order_token'] ?? null,
                    'environment' => AppSetting::getValue('cashfree_mode', 'live') === 'test' ? 'sandbox' : 'production',
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => ucfirst($paymentMethod) . ' payments are not available for dining bookings.'
        ], 400);
    }

    public function verifyPayment(Request $request, $id)
    {
        $request->validate([
            'payment_method' => 'required|in:razorpay,stripe,cashfree,card,upi',
            'payment_id' => 'required|string',
            'razorpay_order_id' => 'required_if:payment_method,razorpay|string',
            'razorpay_signature' => 'required_if:payment_method,razorpay|string',
            'stripe_payment_intent_id' => 'required_if:payment_method,stripe|string',
        ]);

        $booking = DiningBooking::where('user_id', auth()->id())->findOrFail($id);
        $paymentMethod = in_array($request->payment_method, ['card', 'upi'], true)
            ? AppSetting::getValue('payment_gateway_provider', 'razorpay')
            : $request->payment_method;

        if ($paymentMethod === 'razorpay') {
            $secret = AppSetting::getValue('razorpay_secret', config('services.razorpay.secret'));
            if (!$secret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Razorpay is not configured.'
                ], 503);
            }

            $payload = $request->razorpay_order_id . '|' . $request->payment_id;
            $expectedSignature = hash_hmac('sha256', $payload, $secret);
            if (!hash_equals($expectedSignature, $request->razorpay_signature)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment signature verification failed.'
                ], 422);
            }
        }

        if ($paymentMethod === 'stripe') {
            $stripeSecret = AppSetting::getValue('stripe_secret', config('services.stripe.secret'));
            if (!$stripeSecret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stripe is not configured.'
                ], 503);
            }

            Stripe::setApiKey($stripeSecret);

            try {
                $paymentIntent = PaymentIntent::retrieve($request->stripe_payment_intent_id);
                if (!in_array($paymentIntent->status, ['succeeded', 'processing'], true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment was not successful. Status: ' . $paymentIntent->status
                    ], 422);
                }

                if ($paymentIntent->amount !== (int) round($booking->booking_charge * 100)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment amount does not match booking charge.'
                    ], 422);
                }
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to verify Stripe payment: ' . $e->getMessage()
                ], 422);
            }
        }

        if ($paymentMethod === 'cashfree') {
            $clientId = AppSetting::getValue('cashfree_client_id', config('services.cashfree.client_id'));
            $clientSecret = AppSetting::getValue('cashfree_client_secret', config('services.cashfree.client_secret'));
            $apiVersion = config('services.cashfree.api_version', '2022-09-01');

            if (!$clientId || !$clientSecret) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cashfree is not configured.'
                ], 503);
            }

            $response = Http::withHeaders([
                'x-api-version' => $apiVersion,
                'x-client-id' => $clientId,
                'x-client-secret' => $clientSecret,
            ])->get($this->cashfreeBaseUrl() . '/pg/orders/' . $request->payment_id . '/payments');

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unable to verify Cashfree payment.'
                ], 422);
            }

            $paymentData = $response->json();
            if (!is_array($paymentData) || empty($paymentData[0])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No payment found for this booking order.'
                ], 422);
            }

            $successfulPayment = collect($paymentData)->first(
                fn ($payment) => ($payment['payment_status'] ?? null) === 'SUCCESS'
            );

            if (!$successfulPayment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cashfree payment was not successful.'
                ], 422);
            }

            $request->merge([
                'payment_id' => $successfulPayment['cf_payment_id'] ?? $request->payment_id,
            ]);
        }

        $booking->update([
            'payment_status' => 'success',
            'payment_method' => $paymentMethod,
            'payment_id' => $request->payment_id,
            'online_payment_verified_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dining booking payment verified successfully',
            'data' => $booking->fresh(),
        ]);
    }
    
    public function getMyBookings(Request $request)
    {
        $bookings = DiningBooking::where('user_id', auth()->id())
            ->with('restaurant')
            ->orderBy('booking_date', 'desc')
            ->paginate(20);
            
        return response()->json(['success' => true, 'data' => $bookings]);
    }
    
    public function cancelBooking($id, Request $request)
    {
        $booking = DiningBooking::where('user_id', auth()->id())
            ->whereIn('status', ['pending', 'confirmed'])
            ->findOrFail($id);
            
        $booking->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $request->cancellation_reason ?? 'Cancelled by customer'
        ]);

        // TODO: Process refund if any booking charge was paid
        
        return response()->json(['success' => true, 'message' => 'Booking cancelled successfully']);
    }

    public function getBookingDetails($id)
    {
        $booking = DiningBooking::where('user_id', auth()->id())
            ->with('restaurant')
            ->findOrFail($id);
            
        return response()->json(['success' => true, 'data' => $booking]);
    }

    public function submitReview($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'required|numeric|min:1|max:5',
            'feedback' => 'required|string|max:500',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        
        $booking = DiningBooking::where('user_id', auth()->id())
            ->where('status', 'completed')
            ->findOrFail($id);
            
        $booking->update([
            'rating' => $request->rating,
            'feedback' => $request->feedback,
        ]);
        
        return response()->json(['success' => true, 'message' => 'Review submitted successfully']);
    }

    private function cashfreeBaseUrl(): string
    {
        return AppSetting::getValue('cashfree_mode', 'live') === 'test'
            ? 'https://sandbox.cashfree.com'
            : 'https://api.cashfree.com';
    }
}
