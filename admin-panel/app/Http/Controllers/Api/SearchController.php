<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdvancedSearchService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private readonly AdvancedSearchService $search)
    {
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'query' => ['nullable', 'string', 'max:255'],
            'q' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'device_type' => ['nullable', 'string', 'max:40'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->search->search($validated, $request->user()?->id),
        ]);
    }

    public function suggestions(Request $request)
    {
        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'max:255'],
            'query' => ['nullable', 'string', 'max:255'],
            'q' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $keyword = $validated['keyword'] ?? $validated['query'] ?? $validated['q'] ?? '';

        return response()->json([
            'success' => true,
            'suggestions' => $this->search->suggestions($keyword, (int) ($validated['limit'] ?? 8)),
        ]);
    }

    public function trending()
    {
        return response()->json([
            'success' => true,
            'data' => $this->search->search(['keyword' => ''], null)['trending'],
        ]);
    }

    public function history(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->search->history($request->user()?->id),
        ]);
    }

    public function clearHistory(Request $request, ?int $id = null)
    {
        $this->search->clearHistory($request->user()?->id, $id);

        return response()->json([
            'success' => true,
            'message' => 'Search history cleared.',
        ]);
    }

    public function trackClick(Request $request)
    {
        $validated = $request->validate([
            'keyword' => ['required', 'string', 'max:255'],
            'result_type' => ['required', 'string', 'max:40'],
            'result_id' => ['required', 'integer'],
        ]);

        $this->search->logClick(
            $request->user()?->id,
            $validated['keyword'],
            $validated['result_type'],
            (int) $validated['result_id']
        );

        return response()->json(['success' => true]);
    }
}
