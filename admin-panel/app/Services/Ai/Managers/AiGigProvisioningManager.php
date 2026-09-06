<?php

namespace App\Services\Ai\Managers;

use App\Models\AppSetting;
use App\Models\DriverGig;
use App\Services\Ai\AiDecisionLogger;
use App\Services\Ai\AiSettingsService;
use App\Services\GigDemandForecastService;
use App\Services\GigOperationsBroadcastService;
use App\Services\GigProvisioningService;
use Carbon\Carbon;

/**
 * Runs hourly (routes/console.php). When the demand forecast projects more
 * driver capacity for an upcoming area/hour than the gig slots currently
 * published cover (App\Services\GigDemandForecastService::upcomingShortages),
 * this publishes real gig slots to close the gap -- so "AI gig creation"
 * actually produces bookable slots instead of only ever landing a proposal
 * in the approval queue.
 *
 * Opt-in: Settings > AI > "Auto-create gig slots from forecast"
 * (ai_gig_autoprovision_enabled). Bounded per run by
 * ai_gig_autoprovision_max_slots_per_run and only looks
 * ai_gig_autoprovision_horizon_hours ahead. Each created slot is logged as an
 * AiDecision (decision_type gig_autoprovision) for the activity feed.
 */
class AiGigProvisioningManager
{
    public function __construct(
        private readonly AiSettingsService $settings,
        private readonly GigDemandForecastService $forecast,
        private readonly GigProvisioningService $provisioning,
        private readonly AiDecisionLogger $logger,
    ) {
    }

    public function run(): array
    {
        if (! $this->settings->bool('ai_enabled')
            || $this->settings->bool('ai_kill_switch')
            || $this->settings->get('ai_autonomy_mode', 'monitor') === 'off'
            || ! $this->settings->bool('ai_gig_autoprovision_enabled')) {
            return ['created' => 0, 'skipped' => 'disabled'];
        }

        $horizon = (int) $this->settings->get('ai_gig_autoprovision_horizon_hours', 24);
        $maxSlots = max(1, (int) $this->settings->get('ai_gig_autoprovision_max_slots_per_run', 3));
        $minOrders = max(1, (int) $this->settings->get('ai_gig_autoprovision_min_forecast_orders', 5));

        $shortages = $this->forecast->upcomingShortages($horizon, $minOrders);
        if ($shortages === []) {
            return ['created' => 0, 'shortages' => 0];
        }

        $created = [];
        foreach ($shortages as $shortage) {
            if (count($created) >= $maxSlots) {
                break;
            }

            $slot = $this->provisionSlot($shortage);
            if ($slot) {
                $created[] = $slot;
            }
        }

        if ($created !== []) {
            app(GigOperationsBroadcastService::class)->broadcast();
        }

        return ['created' => count($created), 'shortages' => count($shortages), 'slots' => $created];
    }

    private function provisionSlot(array $shortage): ?array
    {
        $start = Carbon::parse($shortage['date'])->setTime($shortage['hour'], 0);
        $end = $start->copy()->addHour();
        $capacity = min(10, max(1, (int) $shortage['gap']));
        $areaLabel = $shortage['area_name'] ?? ('area #' . $shortage['area_id']);
        $template = $this->payTemplate((int) $shortage['area_id']);

        // A slot already covers this hour but is too small -- raise its
        // capacity instead of trying to create an overlapping one (which
        // GigProvisioningService rejects).
        $existing = $this->coveringSlot((int) $shortage['area_id'], $start);
        if ($existing) {
            $newCapacity = min(50, (int) $existing->capacity + $capacity);
            $modify = $this->provisioning->modifyGig($existing, ['capacity' => $newCapacity]);
            if (! ($modify['success'] ?? false)) {
                return null;
            }

            $this->logDecision($shortage, $existing->fresh(), $capacity, $areaLabel, 'expanded', $newCapacity);

            return [
                'gig_id' => $existing->id,
                'area_id' => (int) $shortage['area_id'],
                'date' => $shortage['date'],
                'hour' => (int) $shortage['hour'],
                'capacity' => $newCapacity,
                'action' => 'expanded',
            ];
        }

        $result = $this->provisioning->createGig([
            'area_id' => $shortage['area_id'],
            'date' => $shortage['date'],
            'start_time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'capacity' => $capacity,
            'title' => $this->slotTitle((int) $shortage['hour']),
            'description' => sprintf(
                'Auto-created by AI: forecast %d orders vs %d seat(s) published for %s at %02d:00.',
                $shortage['forecasted_orders'],
                $shortage['existing_capacity'],
                $areaLabel,
                $shortage['hour']
            ),
            'base_pay' => $template['base_pay'],
            'order_incentive' => $template['order_incentive'],
            'login_incentive' => $template['login_incentive'],
            'min_orders_required' => $template['min_orders_required'],
            'min_login_minutes' => $template['min_login_minutes'],
            'max_cancellations_allowed' => $template['max_cancellations_allowed'],
            'forecasted_orders' => $shortage['forecasted_orders'],
            'recommended_capacity' => $shortage['recommended_capacity'],
            'demand_score' => $shortage['demand_score'],
            'surge_multiplier' => max(1, (float) $shortage['surge_multiplier']),
            'auto_pricing_enabled' => true,
        ]);

        if (! ($result['success'] ?? false)) {
            return null;
        }

        $gig = $result['gig'] ?? DriverGig::find($result['gig_id'] ?? null);

        $this->logDecision($shortage, $gig, $capacity, $areaLabel, 'created', $capacity);

        return [
            'gig_id' => $gig?->id,
            'area_id' => (int) $shortage['area_id'],
            'date' => $shortage['date'],
            'hour' => (int) $shortage['hour'],
            'capacity' => $capacity,
            'action' => 'created',
        ];
    }

    /**
     * An available/booked gig slot that already covers this area at this hour.
     */
    private function coveringSlot(int $areaId, Carbon $slotStart): ?DriverGig
    {
        $hour = (int) $slotStart->format('G');

        return DriverGig::where('area_id', $areaId)
            ->whereDate('date', $slotStart->toDateString())
            ->whereIn('status', ['available', 'booked'])
            ->get()
            ->first(function (DriverGig $gig) use ($hour) {
                if (! $gig->start_time || ! $gig->end_time) {
                    return false;
                }
                $startHour = (int) $gig->start_time->format('G');
                $endHour = (int) $gig->end_time->format('G');
                if ($endHour <= $startHour) {
                    $endHour = 24;
                }

                return $hour >= $startHour && $hour < $endHour;
            });
    }

    private function logDecision(array $shortage, ?DriverGig $gig, int $addedSeats, string $areaLabel, string $action, int $finalCapacity): void
    {
        $verb = $action === 'expanded' ? 'Expanded a gig slot to' : 'Published a';
        $this->logger->decision([
            'agent_key' => 'gig_provisioning',
            'decision_type' => 'gig_autoprovision',
            'trigger' => 'scheduled',
            'risk_level' => 'medium',
            'reason_summary' => sprintf(
                '%s %d seat(s) in %s for %s %02d:00 -- forecast %d orders with only %d seat(s) live (recommended %d).',
                $verb,
                $finalCapacity,
                $areaLabel,
                $shortage['date'],
                $shortage['hour'],
                $shortage['forecasted_orders'],
                $shortage['existing_capacity'],
                $shortage['recommended_capacity']
            ),
            'input_snapshot' => $shortage,
            'proposed_action' => [
                'tool' => $action === 'expanded' ? 'modify_gig' : 'create_gig',
                'summary' => sprintf(
                    '%s %s in %s to %d seats (+%d)',
                    $action === 'expanded' ? 'Expand' : 'Create',
                    $this->slotTitle((int) $shortage['hour']),
                    $areaLabel,
                    $finalCapacity,
                    $addedSeats
                ),
                'parameters' => [
                    'gig_id' => $gig?->id,
                    'area_id' => (int) $shortage['area_id'],
                    'date' => $shortage['date'],
                    'hour' => (int) $shortage['hour'],
                    'capacity' => $finalCapacity,
                ],
            ],
            'policy_status' => 'allowed',
            'execution_status' => 'executed',
            'auto_executed' => true,
            'requires_approval' => false,
        ]);
    }

    /**
     * Clone pay/thresholds from the most recent real gig in this area (or
     * anywhere), so auto-created slots match whatever the admin already
     * configures. AppSetting fallbacks only apply on a brand-new install.
     */
    private function payTemplate(int $areaId): array
    {
        $recent = DriverGig::where('area_id', $areaId)->latest('id')->first()
            ?: DriverGig::latest('id')->first();

        return [
            'base_pay' => (float) ($recent->base_pay ?? AppSetting::getValue('gig_default_base_pay', 0)),
            'order_incentive' => (float) ($recent->order_incentive ?? AppSetting::getValue('gig_default_order_incentive', 15)),
            'login_incentive' => (float) ($recent->login_incentive ?? AppSetting::getValue('gig_default_login_incentive', 0)),
            'min_orders_required' => (int) ($recent->min_orders_required ?? 1),
            'min_login_minutes' => (int) ($recent->min_login_minutes ?? 30),
            'max_cancellations_allowed' => (int) ($recent->max_cancellations_allowed ?? 1),
        ];
    }

    private function slotTitle(int $hour): string
    {
        return match (true) {
            $hour >= 5 && $hour < 11 => 'Breakfast Slot',
            $hour >= 11 && $hour < 15 => 'Lunch Rush Slot',
            $hour >= 15 && $hour < 18 => 'Afternoon Slot',
            $hour >= 18 && $hour < 22 => 'Dinner Peak Slot',
            default => 'Late Night Slot',
        };
    }
}
