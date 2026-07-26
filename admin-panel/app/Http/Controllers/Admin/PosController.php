<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(Request $request): View
    {
        $storeId = $request->integer('store_id') ?: null;

        $orders = Order::query()
            ->with(['restaurant', 'customer'])
            ->where(function ($query) {
                $query->where('order_processing_type', 'pos');

                if (Schema::hasColumn('orders', 'payment_source')) {
                    $query->orWhere('payment_source', 'pos');
                }
            })
            ->when($storeId, fn ($query) => $query->where('restaurant_id', $storeId));

        $summaryBase = clone $orders;
        $todayBase = (clone $orders)->whereDate('created_at', today());

        $summary = [
            'total_orders' => (clone $summaryBase)->count(),
            'total_revenue' => (float) (clone $summaryBase)->sum('total'),
            'today_orders' => (clone $todayBase)->count(),
            'today_revenue' => (float) (clone $todayBase)->sum('total'),
            'cash_revenue' => (float) (clone $summaryBase)->whereIn('payment_method', ['cash', 'cod'])->sum('total'),
            'online_revenue' => (float) (clone $summaryBase)->whereNotIn('payment_method', ['cash', 'cod'])->sum('total'),
        ];

        $topStores = Restaurant::query()
            ->withCount(['orders as pos_orders_count' => fn ($query) => $query
                ->where(function ($orders) {
                    $orders->where('order_processing_type', 'pos');

                    if (Schema::hasColumn('orders', 'payment_source')) {
                        $orders->orWhere('payment_source', 'pos');
                    }
                })])
            ->withSum(['orders as pos_revenue' => fn ($query) => $query
                ->where(function ($orders) {
                    $orders->where('order_processing_type', 'pos');

                    if (Schema::hasColumn('orders', 'payment_source')) {
                        $orders->orWhere('payment_source', 'pos');
                    }
                })], 'total')
            ->orderByDesc('pos_revenue')
            ->limit(8)
            ->get();

        $recentOrders = (clone $orders)->latest()->limit(25)->get();
        $stores = Restaurant::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.pos.index', compact(
            'storeId',
            'summary',
            'topStores',
            'recentOrders',
            'stores'
        ));
    }
}
