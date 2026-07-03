<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Restaurant;
use App\Models\PartnerApplication;
use App\Models\DeliveryArea;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PartnerController extends Controller
{
    /**
     * Show partner registration page
     */
    public function showRegistrationForm()
    {
        $deliveryAreas = DeliveryArea::where('is_active', true)->orderBy('name')->get();
        return view('partner.register', compact('deliveryAreas'));
    }

    /**
     * Submit partner registration - ROLE BASED DATA COLLECTION
     */
    public function submitRegistration(Request $request)
    {
        // Normalize partner type
        $partnerType = $request->input('partner_type');
        if (is_array($partnerType)) {
            $partnerType = head($partnerType);
            $request->merge(['partner_type' => $partnerType]);
        }

        try {
            // Role-based validation
            if ($partnerType === 'restaurant') {
                $validated = $request->validate([
                    'partner_type' => 'required|in:restaurant',
                    // Restaurant Basic Info
                    'business_name' => 'required|string|max:255',
                    'business_email' => 'required|email|unique:restaurants,email|unique:users,email',
                    'business_phone' => 'required|string|max:20',
                    'city' => 'required|string',
                    'address' => 'required|string',
                    'pincode' => 'nullable|string|max:10',
                    'cuisine' => 'nullable|string',
                    'is_pure_veg' => 'nullable|boolean',
                    // Contact Person Details
                    'contact_name' => 'required|string|max:255',
                    'contact_designation' => 'required|string|max:100',
                    'contact_email' => 'required|email',
                    'contact_phone' => 'required|string|max:20',
                    // Documents
                    'gst_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
                    'fssai_license' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
                    // Bank Details
                    'bank_holder_name' => 'nullable|string',
                    'bank_account_number' => 'nullable|string',
                    'bank_ifsc' => 'nullable|string',
                    'bank_name' => 'nullable|string',
                    'bank_details' => 'nullable|string',
                    'terms' => 'accepted',
                ]);
            } else {
                $validated = $request->validate([
                    'partner_type' => 'required|in:driver',
                    // Personal Info
                    'full_name' => 'required|string|max:255',
                    'email' => 'required|email|unique:users,email',
                    'phone' => 'required|string|max:20|unique:users,phone',
                    'city' => 'required|string',
                    'address' => 'required|string',
                    'area_id' => 'required|exists:delivery_areas,id',
                    'latitude' => 'nullable|numeric',
                    'longitude' => 'nullable|numeric',
                    // Vehicle & License Details
                    'vehicle_type' => 'required|in:bike,scooter,car',
                    'vehicle_number' => 'required|string|max:20',
                    'license_number' => 'required|string|max:50',
                    'license_expiry' => 'nullable|date',
                    // Documents
                    'license_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
                    'aadhar_card' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
                    // Bank Details
                    'bank_holder_name' => 'nullable|string',
                    'bank_account_number' => 'nullable|string',
                    'bank_ifsc' => 'nullable|string',
                    'bank_name' => 'nullable|string',
                    'upi_id' => 'nullable|string',
                    'bank_details' => 'nullable|string',
                    'terms' => 'accepted',
                ]);
            }

            DB::beginTransaction();
            // Upload documents
            $gstPath = null;
            $fssaiPath = null;
            $licensePath = null;
            $aadharPath = null;

            if ($request->hasFile('gst_certificate')) {
                $gstPath = $request->file('gst_certificate')->store('partner_documents/gst', 'public');
            }
            if ($request->hasFile('fssai_license')) {
                $fssaiPath = $request->file('fssai_license')->store('partner_documents/fssai', 'public');
            }
            if ($request->hasFile('license_document')) {
                $licensePath = $request->file('license_document')->store('partner_documents/licenses', 'public');
            }
            if ($request->hasFile('aadhar_card')) {
                $aadharPath = $request->file('aadhar_card')->store('partner_documents/aadhar', 'public');
            }

            // Prepare bank details JSON
            $bankDetails = [];
            if ($request->bank_holder_name) $bankDetails['holder_name'] = $request->bank_holder_name;
            if ($request->bank_account_number) $bankDetails['account_number'] = $request->bank_account_number;
            if ($request->bank_ifsc) $bankDetails['ifsc'] = $request->bank_ifsc;
            if ($request->bank_name) $bankDetails['bank_name'] = $request->bank_name;
            if ($request->upi_id) $bankDetails['upi_id'] = $request->upi_id;
            if ($request->bank_details) $bankDetails['additional_info'] = $request->bank_details;

            // Create application
            $generatedPassword = Str::password(24);
            $applicationData = [
                'application_number' => 'APP' . strtoupper(uniqid()),
                'partner_type' => $request->partner_type,
                'status' => 'pending',
                'city' => $request->city,
                'address' => $request->address,
                'bank_details' => !empty($bankDetails) ? json_encode($bankDetails) : null,
                'password' => Hash::make((string) $request->input('password', $generatedPassword)),
            ];

            if ($request->partner_type === 'restaurant') {
                $applicationData['business_name'] = $request->business_name;
                $applicationData['business_email'] = $request->business_email;
                $applicationData['business_phone'] = $request->business_phone;
                $applicationData['pincode'] = $request->pincode;
                $applicationData['cuisine'] = $request->cuisine;
                $applicationData['is_pure_veg'] = $request->boolean('is_pure_veg');
                $applicationData['contact_name'] = $request->contact_name;
                $applicationData['contact_designation'] = $request->contact_designation;
                $applicationData['contact_email'] = $request->contact_email;
                $applicationData['contact_phone'] = $request->contact_phone;
                $applicationData['gst_certificate'] = $gstPath;
                $applicationData['fssai_license'] = $fssaiPath;
            } else {
                $applicationData['full_name'] = $request->full_name;
                $applicationData['email'] = $request->email;
                $applicationData['phone'] = $request->phone;
                $applicationData['area_id'] = $request->area_id;
                $applicationData['latitude'] = $request->latitude;
                $applicationData['longitude'] = $request->longitude;
                $applicationData['vehicle_type'] = $request->vehicle_type;
                $applicationData['vehicle_number'] = $request->vehicle_number;
                $applicationData['license_number'] = $request->license_number;
                $applicationData['license_expiry'] = $request->license_expiry;
                $applicationData['license_document'] = $licensePath;
                $applicationData['aadhar_card'] = $aadharPath;
            }

            $application = PartnerApplication::create($applicationData);

            DB::commit();

            // Send email notifications
            $this->sendApplicationEmails($application);

            return response()->json([
                'success' => true,
                'message' => 'Your application has been submitted successfully! We will review and contact you soon.',
                'application_id' => $application->id,
                'application_number' => $application->application_number
            ]);

        } catch (ValidationException $e) {
            DB::rollback();
            \Log::warning('Partner registration validation failed', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed. Please fix the errors and try again.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Partner registration failed: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send email notifications
     */
    private function sendApplicationEmails($application)
    {
        try {
            $adminEmail = config('mail.admin_email', 'admin@foodflow.com');
            
            // Admin notification
            Mail::send('emails.partner-application', ['application' => $application], function ($mail) use ($adminEmail, $application) {
                $mail->to($adminEmail)
                    ->subject('New Partner Application - ' . $application->application_number);
            });

            // Applicant confirmation
            $applicantEmail = $application->partner_type === 'restaurant' ? $application->contact_email : $application->email;
            Mail::send('emails.partner-confirmation', ['application' => $application], function ($mail) use ($applicantEmail, $application) {
                $mail->to($applicantEmail)
                    ->subject('Application Received - FoodFlow Partner Program');
            });
        } catch (\Exception $e) {
            \Log::error('Failed to send application email: ' . $e->getMessage());
        }
    }

    /**
     * Get application status
     */
    public function getApplicationStatus($applicationNumber)
    {
        $application = PartnerApplication::where('application_number', $applicationNumber)->firstOrFail();
        
        return response()->json([
            'success' => true,
            'status' => $application->status,
            'message' => $this->getStatusMessage($application->status),
            'application' => $application
        ]);
    }

    private function getStatusMessage($status)
    {
        switch ($status) {
            case 'pending':
                return 'Your application is under review. We will get back to you within 2-3 business days.';
            case 'approved':
                return 'Congratulations! Your application has been approved. You can now login to your dashboard.';
            case 'rejected':
                return 'We regret to inform you that your application has been declined. Please contact support for more details.';
            default:
                return 'Status unknown';
        }
    }
}
