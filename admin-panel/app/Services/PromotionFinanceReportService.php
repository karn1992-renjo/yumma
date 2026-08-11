<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PromotionFinanceReportService
{
    public function campaignRows(array $filters = []): Collection
    {
        return DB::table('promotion_settlement_ledgers')
            ->leftJoin('promotions', 'promotion_settlement_ledgers.promotion_id', '=', 'promotions.id')
            ->leftJoin('restaurants', 'promotion_settlement_ledgers.restaurant_id', '=', 'restaurants.id')
            ->when($filters['promotion_id'] ?? null, fn ($query, $id) => $query->where('promotion_settlement_ledgers.promotion_id', $id))
            ->when($filters['restaurant_id'] ?? null, fn ($query, $id) => $query->where('promotion_settlement_ledgers.restaurant_id', $id))
            ->when($filters['partner_name'] ?? null, fn ($query, $partner) => $query->where('promotion_settlement_ledgers.partner_name', 'like', '%' . $partner . '%'))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('promotion_settlement_ledgers.created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('promotion_settlement_ledgers.created_at', '<=', $date))
            ->groupBy(
                'promotion_settlement_ledgers.promotion_id',
                'promotion_settlement_ledgers.restaurant_id',
                'promotion_settlement_ledgers.funding_type',
                'promotion_settlement_ledgers.partner_name',
                'promotions.title',
                'promotions.total_budget',
                'promotions.budget_used',
                'restaurants.name'
            )
            ->selectRaw('promotion_settlement_ledgers.promotion_id')
            ->selectRaw('promotions.title as promotion_title')
            ->selectRaw('promotion_settlement_ledgers.restaurant_id')
            ->selectRaw('restaurants.name as restaurant_name')
            ->selectRaw('promotion_settlement_ledgers.funding_type')
            ->selectRaw('promotion_settlement_ledgers.partner_name')
            ->selectRaw('COUNT(DISTINCT promotion_settlement_ledgers.order_id) as orders')
            ->selectRaw('COUNT(*) as redemptions')
            ->selectRaw('SUM(promotion_settlement_ledgers.discount_amount) as discount_given')
            ->selectRaw('SUM(promotion_settlement_ledgers.gross_liability_amount) as budget_used')
            ->selectRaw('SUM(promotion_settlement_ledgers.platform_liability_amount) as platform_burn')
            ->selectRaw('SUM(promotion_settlement_ledgers.restaurant_liability_amount) as restaurant_burn')
            ->selectRaw('SUM(promotion_settlement_ledgers.partner_liability_amount) as partner_burn')
            ->selectRaw('promotions.total_budget as total_budget')
            ->selectRaw('promotions.budget_used as promotion_budget_used')
            ->orderByDesc('budget_used')
            ->get()
            ->map(function ($row) {
                $totalBudget = $row->total_budget !== null ? (float) $row->total_budget : null;
                $promotionBudgetUsed = (float) ($row->promotion_budget_used ?? 0);
                $row->remaining_budget = $totalBudget !== null ? max(0, round($totalBudget - $promotionBudgetUsed, 2)) : null;
                return $row;
            });
    }

    public function bankPartnerRows(array $filters = []): Collection
    {
        return DB::table('promotion_settlement_ledgers')
            ->leftJoin('promotions', 'promotion_settlement_ledgers.promotion_id', '=', 'promotions.id')
            ->leftJoin('restaurants', 'promotion_settlement_ledgers.restaurant_id', '=', 'restaurants.id')
            ->leftJoin('orders', 'promotion_settlement_ledgers.order_id', '=', 'orders.id')
            ->where(function ($query) {
                $query->where('promotion_settlement_ledgers.funding_type', 'bank_partner')
                    ->orWhere('promotion_settlement_ledgers.partner_liability_amount', '>', 0);
            })
            ->when($filters['promotion_id'] ?? null, fn ($query, $id) => $query->where('promotion_settlement_ledgers.promotion_id', $id))
            ->when($filters['restaurant_id'] ?? null, fn ($query, $id) => $query->where('promotion_settlement_ledgers.restaurant_id', $id))
            ->when($filters['partner_name'] ?? null, fn ($query, $partner) => $query->where('promotion_settlement_ledgers.partner_name', 'like', '%' . $partner . '%'))
            ->when($filters['settlement_status'] ?? null, fn ($query, $status) => $query->where('promotion_settlement_ledgers.settlement_status', $status))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('promotion_settlement_ledgers.created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('promotion_settlement_ledgers.created_at', '<=', $date))
            ->latest('promotion_settlement_ledgers.created_at')
            ->get([
                'promotion_settlement_ledgers.id',
                'promotion_settlement_ledgers.order_id',
                'orders.order_number',
                'promotion_settlement_ledgers.promotion_id',
                'promotions.title as promotion_title',
                'promotion_settlement_ledgers.restaurant_id',
                'restaurants.name as restaurant_name',
                'promotion_settlement_ledgers.coupon_code',
                'promotion_settlement_ledgers.partner_name',
                'promotion_settlement_ledgers.discount_amount',
                'promotion_settlement_ledgers.partner_liability_amount',
                'promotion_settlement_ledgers.settlement_status',
                'promotion_settlement_ledgers.created_at',
            ]);
    }

    public function streamCsv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}