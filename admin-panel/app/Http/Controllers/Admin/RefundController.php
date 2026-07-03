<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\RefundService;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function index(Request $request)
    {
        $refunds = Order::with(['customer', 'restaurant'])
            ->whereNotNull('refund_status')
            ->when($request->status, fn ($query, $status) => $query->where('refund_status', $status))
            ->latest()
            ->paginate(20);

        return view('admin.refunds.index', compact('refunds'));
    }

    public function store(Request $request, RefundService $refundService)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'refund_amount' => 'nullable|numeric|min:0.01',
            'refund_reason' => 'required|string|max:500',
        ]);

        $order = Order::findOrFail($validated['order_id']);
        $result = $refundService->processRefund(
            $order,
            $validated['refund_reason'],
            $validated['refund_amount'] ?? null
        );

        return redirect()->route('admin.refunds.index')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
