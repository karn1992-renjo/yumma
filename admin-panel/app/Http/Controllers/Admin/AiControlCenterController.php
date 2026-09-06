<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiAction;
use App\Models\AiAlert;
use App\Models\AiApproval;
use App\Models\AiDecision;
use App\Models\AiSetting;
use App\Models\AiUsageLog;
use App\Services\Ai\AiApprovalService;
use App\Services\Ai\AiCostAwareRouter;
use App\Services\Ai\AiOrchestrator;
use App\Services\Ai\AiSettingsService;
use App\Services\Ai\AiToolRegistry;
use Illuminate\Http\Request;

class AiControlCenterController extends Controller
{
    public function index(AiSettingsService $settings, AiCostAwareRouter $router, AiToolRegistry $tools)
    {
        $this->guard('ai.view');

        return view('admin.ai.index', [
            'settings' => $settings->health(),
            'routerHealth' => $router->health(),
            'pendingApprovals' => AiApproval::where('status', 'pending')->latest()->limit(8)->get(),
            'criticalAlerts' => AiAlert::where('status', 'open')->latest()->limit(8)->get(),
            'todayDecisions' => AiDecision::whereDate('created_at', today())->count(),
            'todayCost' => (float) AiUsageLog::whereDate('created_at', today())->sum('estimated_cost'),
            'recentActions' => AiAction::latest()->limit(10)->get(),
            'business' => $tools->execute('get_business_summary')['data'] ?? [],
            'finance' => $tools->execute('get_finance_summary')['data'] ?? [],
            'accounting' => $tools->execute('get_accounting_summary')['data'] ?? [],
            'promotion' => $tools->execute('get_promotion_performance')['data'] ?? [],
            'anomalies' => $tools->execute('detect_financial_anomalies')['data'] ?? [],
            'decisionTrend' => $this->decisionTrend(),
            'riskDistribution' => AiDecision::query()
                ->selectRaw('risk_level, COUNT(*) as total')
                ->groupBy('risk_level')
                ->pluck('total', 'risk_level'),
            'executionDistribution' => AiDecision::query()
                ->selectRaw('execution_status, COUNT(*) as total')
                ->groupBy('execution_status')
                ->pluck('total', 'execution_status'),
        ]);
    }

    /**
     * Daily decision counts for the last 14 days, zero-filled for days with
     * no activity so the dashboard trend chart doesn't show gaps.
     */
    private function decisionTrend(): array
    {
        $rows = AiDecision::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->groupBy('day')
            ->pluck('total', 'day');

        $trend = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $trend[$date] = (int) ($rows[$date] ?? 0);
        }

        return $trend;
    }

    public function settings(AiSettingsService $settings, AiToolRegistry $tools)
    {
        $this->guard('ai.configure');

        return view('admin.ai.settings', [
            'settings' => $settings->all(),
            'health' => $settings->health(),
            'tools' => $tools->definitions(),
            'connectionTest' => session('connectionTest'),
        ]);
    }

    public function updateSettings(Request $request, AiSettingsService $settings)
    {
        $this->guard('ai.configure');

        $validated = $request->validate([
            'ai_enabled' => ['required', 'in:0,1'],
            'ai_provider' => ['required', 'in:gemini,openai'],
            'ai_fallback_provider' => ['required', 'in:gemini,openai'],
            'ai_autonomy_mode' => ['required', 'in:monitor,assist,auto,autonomous'],
            'ai_simulation_mode' => ['required', 'in:0,1'],
            'ai_low_risk_auto_enabled' => ['required', 'in:0,1'],
            'ai_auto_execute' => ['required', 'in:0,1'],
            'ai_notifications_require_approval' => ['required', 'in:0,1'],
            'ai_daily_budget_usd' => ['required', 'numeric', 'min:0', 'max:100000'],
            'ai_max_action_amount' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'ai_min_margin_percent' => ['required', 'numeric', 'min:-100', 'max:100'],
            'ai_max_surge_fee_amount' => ['required', 'numeric', 'min:0', 'max:1000'],
            'ai_gig_autoprovision_enabled' => ['required', 'in:0,1'],
            'ai_gig_autoprovision_horizon_hours' => ['required', 'integer', 'min:1', 'max:72'],
            'ai_gig_autoprovision_max_slots_per_run' => ['required', 'integer', 'min:1', 'max:20'],
            'ai_gig_autoprovision_min_forecast_orders' => ['required', 'integer', 'min:1', 'max:500'],
            'openai_model' => ['required', 'string', 'max:120'],
            'gemini_model' => ['required', 'string', 'max:120'],
            'openai_api_key' => ['nullable', 'string', 'max:2000'],
            'gemini_api_key' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['ai_autonomy_mode'] === 'autonomous') {
            return back()->withErrors(['ai_autonomy_mode' => 'Autonomous mode must remain disabled until owner manual approval.']);
        }

        $settings->update($validated);

        return redirect()->route('admin.ai.settings')->with('success', 'AI settings updated.');
    }

    public function testConnection(AiCostAwareRouter $router)
    {
        $this->guard('ai.configure');

        $results = $router->testAll();
        $failed = collect($results)->where('success', false)->count();

        return redirect()->route('admin.ai.settings')
            ->with('connectionTest', $results)
            ->with($failed > 0 ? 'error' : 'success', $failed > 0
                ? $failed.' of '.count($results).' provider(s) failed the connection test — see details below.'
                : 'All configured AI providers connected successfully.');
    }

    public function decisions(Request $request)
    {
        $this->guard('ai.decisions.view');

        $query = AiDecision::query();

        if ($request->filled('agent_key')) {
            $query->where('agent_key', $request->string('agent_key'));
        }
        if ($request->filled('risk_level')) {
            $query->where('risk_level', $request->string('risk_level'));
        }
        if ($request->filled('execution_status')) {
            $query->where('execution_status', $request->string('execution_status'));
        }

        return view('admin.ai.decisions.index', [
            'decisions' => $query->latest()->paginate(25)->withQueryString(),
            'agentOptions' => AiDecision::query()->distinct()->orderBy('agent_key')->pluck('agent_key'),
            'stats' => [
                'total' => AiDecision::count(),
                'today' => AiDecision::whereDate('created_at', today())->count(),
                'pending_approval' => AiDecision::where('execution_status', 'pending')->orWhere('policy_status', 'pending')->count(),
                'executed' => AiDecision::where('execution_status', 'executed')->count(),
            ],
            'filters' => $request->only(['agent_key', 'risk_level', 'execution_status']),
        ]);
    }

    public function showDecision(AiDecision $decision)
    {
        $this->guard('ai.decisions.view');

        return view('admin.ai.decisions.show', [
            'decision' => $decision->load(['actions', 'approvals']),
        ]);
    }

    public function approvals(Request $request)
    {
        $this->guard('ai.approvals.manage');

        $status = $request->string('status', 'pending')->toString();
        $allowedStatuses = ['pending', 'approved', 'rejected'];
        $status = in_array($status, $allowedStatuses, true) ? $status : 'pending';

        return view('admin.ai.approvals.index', [
            'approvals' => AiApproval::with(['decision', 'action', 'reviewer'])
                ->where('approver_type', 'admin')
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'status' => $status,
            'allowedStatuses' => $allowedStatuses,
            'stats' => [
                'pending' => AiApproval::where('approver_type', 'admin')->where('status', 'pending')->count(),
                'approved_today' => AiApproval::where('approver_type', 'admin')->where('status', 'approved')->whereDate('reviewed_at', today())->count(),
                'rejected_today' => AiApproval::where('approver_type', 'admin')->where('status', 'rejected')->whereDate('reviewed_at', today())->count(),
                'total' => AiApproval::where('approver_type', 'admin')->count(),
            ],
        ]);
    }

    public function approve(Request $request, AiApproval $approval, AiApprovalService $approvals)
    {
        $this->guard('ai.approvals.manage');

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $approvals->approve($approval, $request->user(), $validated['admin_note'] ?? null);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result + ['approval_id' => $approval->id]);
        }

        return back()->with('success', 'AI approval processed.');
    }

    public function reject(Request $request, AiApproval $approval, AiApprovalService $approvals)
    {
        $this->guard('ai.approvals.manage');

        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $approvals->reject($approval, $request->user(), $validated['admin_note'] ?? null);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($result + ['approval_id' => $approval->id]);
        }

        return back()->with('success', 'AI approval rejected.');
    }

    public function chat(AiSettingsService $settings)
    {
        $this->guard('ai.view');

        return view('admin.ai.chat', [
            'health' => $settings->health(),
            'recentDecisions' => AiDecision::latest()->limit(8)->get(),
        ]);
    }

    public function chatAsk(Request $request, AiOrchestrator $orchestrator)
    {
        $this->guard('ai.view');

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $result = $orchestrator->chat($validated['message'], $request->user());
        $decision = $result['decision'];
        $approval = $decision?->approvals->first();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => (bool) ($result['success'] ?? false),
                'answer' => $result['answer'],
                'decision_id' => $decision?->id,
                'decision_url' => $decision ? route('admin.ai.decisions.show', $decision) : null,
                'risk_level' => $decision?->risk_level,
                'requires_approval' => (bool) $decision?->requires_approval,
                'proposed_action' => $decision?->proposed_action,
                'approval_id' => $approval?->status === 'pending' ? $approval->id : null,
            ]);
        }

        return view('admin.ai.chat', [
            'question' => $validated['message'],
            'answer' => $result['answer'],
            'decision' => $decision,
            'approval' => $approval?->status === 'pending' ? $approval : null,
        ]);
    }

    /**
     * Reports content was moved onto the main dashboard (index()). This
     * route is kept only so old bookmarks/links to /admin/ai/reports still
     * land somewhere sensible.
     */
    public function reports()
    {
        return redirect()->route('admin.ai.index');
    }

    public function run(Request $request, AiOrchestrator $orchestrator)
    {
        $this->guard('ai.actions.execute');

        $decision = $orchestrator->run($request->input('agent', 'operations'), 'manual', $request->user());

        return redirect()->route('admin.ai.decisions.show', $decision)->with('success', 'AI management cycle completed.');
    }

    public function killSwitch(Request $request)
    {
        $this->guard('ai.kill_switch');

        AiSetting::setValue('ai_kill_switch', true, 'boolean');

        return redirect()->route('admin.ai.index')->with('success', 'AI kill switch activated. Automatic execution is disabled; analysis, monitoring, and chat continue.');
    }

    public function resumeAi(Request $request)
    {
        $this->guard('ai.kill_switch');

        AiSetting::setValue('ai_kill_switch', false, 'boolean');

        return redirect()->route('admin.ai.index')->with('success', 'AI kill switch cleared. Automatic execution is governed by the autonomy mode again.');
    }

    private function guard(string $permission): void
    {
        $user = auth()->user();

        abort_unless($user && ($user->hasRole('super_admin') || $user->can($permission)), 403);
    }
}
