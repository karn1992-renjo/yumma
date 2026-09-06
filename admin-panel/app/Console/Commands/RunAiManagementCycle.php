<?php

namespace App\Console\Commands;

use App\Services\Ai\AiOrchestrator;
use App\Services\Ai\AiSettingsService;
use Illuminate\Console\Command;

class RunAiManagementCycle extends Command
{
    protected $signature = 'ai:management-cycle {agent=operations} {--trigger=scheduled}';

    protected $description = 'Run the controlled AI management cycle in monitor/simulation-safe mode.';

    public function handle(AiOrchestrator $orchestrator, AiSettingsService $settings): int
    {
        if (! $settings->bool('ai_enabled')) {
            $this->info('AI feature is disabled (Settings > Branding). Skipping management cycle.');

            return self::SUCCESS;
        }

        $decision = $orchestrator->run(
            (string) $this->argument('agent'),
            (string) $this->option('trigger')
        );

        $this->info("AI decision #{$decision->id}: {$decision->execution_status}");

        return self::SUCCESS;
    }
}
