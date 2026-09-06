<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\RefundService;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    public function index(Request $request)
    {
        $returns = Order::with(['customer', 'restaurant'])
            ->whereNotNull('return_status')
            ->when($request->status, fn ($query, $status) => $query->where('return_status', $status))
            ->latest()
            ->paginate(20);

        return view('admin.returns.index', compact('returns'));
    }

    public function approve(Request $request, Order $order, RefundService $refundService)
    {
        if ($order->return_status !== 'requested') {
            return redirect()->route('admin.returns.index')->with('error', 'Only requested returns can be approved.');
        }

        $validated = $request->validate([
            'return_amount' => ['required', 'numeric', 'min:0.01', 'max:' . (float) $order->total],
        ]);

        $result = $refundService->processRefund(
            $order,
            $order->return_reason ?: 'Return approved',
            (string) $validated['return_amount']
        );

        if (! $result['success']) {
            return redirect()->route('admin.returns.index')->with('error', $result['message']);
        }

        $order->processReturn((float) $validated['return_amount']);

        return redirect()->route('admin.returns.index')->with('success', 'Return approved and refund processed.');
    }

    public function reject(Request $request, Order $order)
    {
        if ($order->return_status !== 'requested') {
            return redirect()->route('admin.returns.index')->with('error', 'Only requested returns can be rejected.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $order->rejectReturn($validated['reason']);

        return redirect()->route('admin.returns.index')->with('success', 'Return request rejected.');
    }
}
