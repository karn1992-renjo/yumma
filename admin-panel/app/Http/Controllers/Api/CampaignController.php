<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CampaignService;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function __construct(private readonly CampaignService $campaigns)
    {
    }

    public function index(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->campaigns->list($request),
        ]);
    }

    public function show($id)
    {
        $campaign = $this->campaigns->findActive((int) $id);

        return response()->json([
            'success' => true,
            'data' => $this->campaigns->payload($campaign),
        ]);
    }

    public function trackClick(Request $request, $id)
    {
        $this->campaigns->findActive((int) $id)->recordClick();

        return response()->json([
            'success' => true,
            'message' => 'Click tracked',
        ]);
    }

    public function trackImpression(Request $request, $id)
    {
        $this->campaigns->findActive((int) $id)->recordImpression();

        return response()->json([
            'success' => true,
            'message' => 'Impression tracked',
        ]);
    }
}
