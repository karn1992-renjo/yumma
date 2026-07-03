<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->query('type');
        $types = $type
            ? collect(explode(',', (string) $type))->map(fn ($item) => trim($item))->filter()->values()->all()
            : ['banner', 'popup'];

        $campaigns = Campaign::active()
            ->when(! empty($types), fn ($query) => $query->whereIn('type', $types))
            ->latest('id')
            ->limit((int) min(max((int) $request->query('limit', 10), 1), 50))
            ->get()
            ->filter(fn (Campaign $campaign) => $this->matchesAudience($campaign, $request))
            ->map(fn (Campaign $campaign) => $this->campaignPayload($campaign))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $campaigns,
        ]);
    }

    public function show($id)
    {
        $campaign = Campaign::active()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->campaignPayload($campaign),
        ]);
    }

    public function trackClick(Request $request, $id)
    {
        Campaign::query()->whereKey($id)->firstOrFail()->recordClick();

        return response()->json([
            'success' => true,
            'message' => 'Click tracked',
        ]);
    }

    public function trackImpression(Request $request, $id)
    {
        Campaign::query()->whereKey($id)->firstOrFail()->recordImpression();

        return response()->json([
            'success' => true,
            'message' => 'Impression tracked',
        ]);
    }

    protected function campaignPayload(Campaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'title' => $campaign->name,
            'name' => $campaign->name,
            'type' => $campaign->type,
            'target_audience' => $campaign->target_audience,
            'target_location' => $campaign->target_location,
            'discount_details' => $campaign->discount_details ?? [],
            'image' => $this->resolveImageUrl($campaign->image_url),
            'image_url' => $this->resolveImageUrl($campaign->image_url),
            'banner_image' => $this->resolveImageUrl($campaign->image_url),
            'link' => $campaign->link_url,
            'link_url' => $campaign->link_url,
            'start_date' => optional($campaign->start_date)->toDateString(),
            'end_date' => optional($campaign->end_date)->toDateString(),
            'impressions' => (int) $campaign->impressions,
            'clicks' => (int) $campaign->clicks,
        ];
    }

    protected function matchesAudience(Campaign $campaign, Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return $campaign->target_audience === 'all';
        }

        $hasOrders = Order::query()
            ->where('customer_id', $user->id)
            ->exists();

        if ($campaign->target_audience === 'new_customer' && $hasOrders) {
            return false;
        }

        if ($campaign->target_audience === 'returning_customer' && ! $hasOrders) {
            return false;
        }

        if (filled($campaign->target_location)) {
            $location = strtolower(trim((string) $campaign->target_location));
            $address = strtolower(trim((string) ($user->address ?? '')));
            if ($address === '' || ! str_contains($address, $location)) {
                return false;
            }
        }

        return true;
    }

    protected function resolveImageUrl(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return \App\Services\MediaStorage::url($path);
    }
}
