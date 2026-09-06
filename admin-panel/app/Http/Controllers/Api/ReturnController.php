<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    public function requestReturn(Request $request, $orderId)
    {
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        $order = Order::where('customer_id', auth()->id())->findOrFail($orderId);

        if (! $order->isReturnable()) {
            return response()->json([
                'success' => false,
                'message' => 'This order is not eligible for a return request.'
            ], 400);
        }

        $order->requestReturn($request->reason);

        return response()->json([
            'success' => true,
            'message' => 'Return request submitted successfully'
        ]);
    }
    
    public function getReturnStatus($orderId)
    {
        $order = Order::where('customer_id', auth()->id())
            ->whereNotNull('return_status')
            ->findOrFail($orderId);
            
        return response()->json([
            'success' => true,
            'data' => [
                'status' => $order->return_status,
                'reason' => $order->return_reason,
                'amount' => $order->return_amount,
                'processed_at' => $order->return_processed_at
            ]
        ]);
    }
}