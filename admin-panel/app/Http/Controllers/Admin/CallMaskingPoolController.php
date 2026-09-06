<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CallMaskingPool;
use Illuminate\Http\Request;

class CallMaskingPoolController extends Controller
{
    public function index(Request $request)
    {
        $pool = CallMaskingPool::with('currentOrder:id,order_number')
            ->latest()
            ->paginate(25);

        $stats = [
            'total' => CallMaskingPool::count(),
            'available' => CallMaskingPool::where('status', 'available')->count(),
            'in_use' => CallMaskingPool::where('status', 'in_use')->count(),
        ];

        return view('admin.call-masking.pool', compact('pool', 'stats'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'exophone' => ['required', 'string', 'max:32', 'unique:call_masking_pool,exophone'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        CallMaskingPool::create([
            'exophone' => $validated['exophone'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'available',
        ]);

        return back()->with('success', 'Exophone added to the pool.');
    }

    public function update(Request $request, CallMaskingPool $pool)
    {
        $validated = $request->validate([
            'exophone' => ['required', 'string', 'max:32', 'unique:call_masking_pool,exophone,' . $pool->id],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $pool->update($validated);

        return back()->with('success', 'Exophone updated.');
    }

    public function disable(CallMaskingPool $pool)
    {
        $pool->update(['status' => 'disabled', 'current_order_id' => null, 'released_at' => now()]);

        return back()->with('success', 'Exophone disabled.');
    }

    public function enable(CallMaskingPool $pool)
    {
        if ($pool->status === 'disabled') {
            $pool->update(['status' => 'available']);
        }

        return back()->with('success', 'Exophone re-enabled.');
    }

    public function destroy(CallMaskingPool $pool)
    {
        if ($pool->status === 'in_use') {
            return back()->withErrors(['exophone' => 'Cannot delete a number that is currently assigned to an order.']);
        }

        $pool->delete();

        return back()->with('success', 'Exophone removed from the pool.');
    }
}
