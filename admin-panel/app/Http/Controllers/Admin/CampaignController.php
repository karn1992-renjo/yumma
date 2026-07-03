<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\FirebaseHelper;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class CampaignController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::orderBy('created_at', 'desc')->paginate(20);
        return view('admin.campaigns.index', compact('campaigns'));
    }
    
    public function create()
    {
        return view('admin.campaigns.create');
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:banner,popup,email,push',
            'target_audience' => 'required|in:all,new_customer,returning_customer',
            'target_location' => 'nullable|string',
            'discount_details' => 'nullable|array',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'link_url' => 'nullable|string|max:2048',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'nullable|boolean',
        ]);
        
        $data = $request->except('image');
        $data['is_active'] = $request->boolean('is_active');
        
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('campaigns', 'public');
            $data['image_url'] = $path;
        }
        
        $campaign = Campaign::create($data);
        $this->dispatchCampaignIfNeeded($campaign);
        
        return redirect()->route('admin.campaigns.index')->with('success', 'Campaign created successfully!');
    }
    
    public function edit($id)
    {
        $campaign = Campaign::findOrFail($id);
        return view('admin.campaigns.edit', compact('campaign'));
    }
    
    public function update(Request $request, $id)
    {
        $campaign = Campaign::findOrFail($id);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:banner,popup,email,push',
            'target_audience' => 'required|in:all,new_customer,returning_customer',
            'target_location' => 'nullable|string',
            'discount_details' => 'nullable|array',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'link_url' => 'nullable|string|max:2048',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'nullable|boolean',
        ]);
        
        $data = $request->except('image');
        $wasActive = $campaign->is_active;
        $data['is_active'] = $request->boolean('is_active');
        
        if ($request->hasFile('image')) {
            if ($campaign->image_url) {
                Storage::disk('public')->delete($campaign->image_url);
            }
            $path = $request->file('image')->store('campaigns', 'public');
            $data['image_url'] = $path;
        }
        
        $campaign->update($data);
        $campaign->refresh();
        if (! $wasActive && $campaign->is_active) {
            $this->dispatchCampaignIfNeeded($campaign);
        }
        
        return redirect()->route('admin.campaigns.index')->with('success', 'Campaign updated successfully!');
    }
    
    public function destroy($id)
    {
        $campaign = Campaign::findOrFail($id);
        
        if ($campaign->image_url) {
            Storage::disk('public')->delete($campaign->image_url);
        }
        
        $campaign->delete();
        
        return redirect()->route('admin.campaigns.index')->with('success', 'Campaign deleted successfully!');
    }

    protected function dispatchCampaignIfNeeded(Campaign $campaign): void
    {
        try {
            if (! $campaign->is_active || ! in_array($campaign->type, ['email', 'push'], true)) {
                return;
            }

            if ($campaign->start_date?->isFuture() || $campaign->end_date?->isPast()) {
                return;
            }

            if ($campaign->type === 'push') {
                $this->sendPushCampaign($campaign);
                return;
            }

            $this->sendEmailCampaign($campaign);
        } catch (\Throwable $e) {
            Log::warning('Campaign delivery failed: ' . $e->getMessage(), [
                'campaign_id' => $campaign->id,
                'campaign_type' => $campaign->type,
            ]);
            return;
        }
    }

    protected function sendPushCampaign(Campaign $campaign): void
    {
        $firebase = new FirebaseHelper();
        if (! $firebase->isConfigured()) {
            return;
        }

        $users = $this->campaignAudienceQuery($campaign)
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->get(['id', 'fcm_token']);

        $tokens = $users->pluck('fcm_token')->filter()->unique()->values()->all();
        if (empty($tokens)) {
            return;
        }

        $firebase->sendToDevices(
            $tokens,
            $campaign->name,
            $this->campaignMessage($campaign),
            array_filter([
                'type' => 'campaign',
                'campaign_id' => (string) $campaign->id,
                'campaign_type' => $campaign->type,
                'deep_link' => $campaign->link_url,
            ], fn ($value) => filled($value))
        );
    }

    protected function sendEmailCampaign(Campaign $campaign): void
    {
        $this->campaignAudienceQuery($campaign)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->select(['id', 'email', 'name'])
            ->chunkById(100, function ($users) use ($campaign) {
                foreach ($users as $user) {
                    Mail::raw($this->campaignEmailBody($campaign, $user), function ($message) use ($campaign, $user) {
                        $message->to($user->email, $user->name)
                            ->subject($campaign->name);
                    });
                }
            });
    }

    protected function campaignAudienceQuery(Campaign $campaign)
    {
        $query = User::query()
            ->role('customer')
            ->where('is_active', true);

        $orderedCustomerIds = Order::query()
            ->whereNotNull('customer_id')
            ->select('customer_id');

        if ($campaign->target_audience === 'new_customer') {
            $query->whereNotIn('id', $orderedCustomerIds);
        }

        if ($campaign->target_audience === 'returning_customer') {
            $query->whereIn('id', $orderedCustomerIds);
        }

        if (filled($campaign->target_location)) {
            $location = trim((string) $campaign->target_location);
            $query->where(function ($builder) use ($location) {
                $builder->where('address', 'like', "%{$location}%");
            });
        }

        return $query;
    }

    protected function campaignMessage(Campaign $campaign): string
    {
        $discount = $campaign->discount_details ?? [];
        $type = $discount['type'] ?? null;
        $value = $discount['value'] ?? null;
        $minOrder = $discount['min_order'] ?? null;

        if ($type && $value) {
            $label = $type === 'percentage' ? "{$value}% off" : "Flat {$value} off";
            return trim($label . ($minOrder ? " on orders above {$minOrder}" : '') . '. Order now.');
        }

        return 'A new offer is live. Open the app to explore it.';
    }

    protected function campaignEmailBody(Campaign $campaign, User $user): string
    {
        $lines = [
            'Hi ' . ($user->name ?: 'there') . ',',
            '',
            $this->campaignMessage($campaign),
        ];

        if (filled($campaign->link_url)) {
            $lines[] = '';
            $lines[] = 'Open offer: ' . $campaign->link_url;
        }

        $lines[] = '';
        $lines[] = 'Thank you.';

        return implode("\n", $lines);
    }
}
