<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\RefundService;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    protected $refundService;
    
    public function __construct(RefundService $refundService)
    {
        $this->refundService = $refundService;
    }
    
    public function requestReturn(Request $request, $orderId)
    {
        $request->validate([
            'reason' => 'required|string|max:500'
        ]);
        
        $order = Order::where('customer_id', auth()->id())
            ->where('status', 'delivered')
            ->whereNull('return_status')
            ->findOrFail($orderId);
            
        // Check if within return window (e.g., 7 days)
        if ($order->delivered_at->diffInDays(now()) > 7) {
            return response()->json([
                'success' => false,
                'message' => 'Return window has expired'
            ], 400);
        }
        
        $order->requestReturn($request->reason);
        
        // Notify admin about return request
        // dispatch(new \App\Jobs\NotifyAdminReturnRequest($order));
        
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