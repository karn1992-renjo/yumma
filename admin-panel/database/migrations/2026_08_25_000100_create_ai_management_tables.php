<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_settings')) {
            Schema::create('ai_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->longText('value')->nullable();
                $table->string('type')->default('string');
                $table->boolean('is_secret')->default(false);
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_agents')) {
            Schema::create('ai_agents', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->string('default_model')->nullable();
                $table->string('max_auto_risk')->default('low');
                $table->json('config')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_agent_tools')) {
            Schema::create('ai_agent_tools', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ai_agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
                $table->string('tool_key');
                $table->string('tool_type')->default('read');
                $table->string('risk_level')->default('low');
                $table->boolean('requires_approval')->default(false);
                $table->boolean('is_enabled')->default(true);
                $table->json('schema')->nullable();
                $table->timestamps();
                $table->unique(['ai_agent_id', 'tool_key']);
            });
        }

        if (! Schema::hasTable('ai_decisions')) {
            Schema::create('ai_decisions', function (Blueprint $table) {
                $table->id();
                $table->string('agent_key')->index();
                $table->string('decision_type')->index();
                $table->string('trigger')->nullable()->index();
                $table->string('provider')->nullable();
                $table->string('model')->nullable();
                $table->string('risk_level')->default('low')->index();
                $table->decimal('confidence', 5, 4)->nullable();
                $table->json('input_snapshot')->nullable();
                $table->text('reason_summary')->nullable();
                $table->json('proposed_action')->nullable();
                $table->json('expected_result')->nullable();
                $table->decimal('expected_financial_impact', 14, 2)->nullable();
                $table->string('policy_status')->default('pending')->index();
                $table->boolean('requires_approval')->default(false);
                $table->boolean('auto_executed')->default(false);
                $table->string('execution_status')->default('pending')->index();
                $table->json('execution_result')->nullable();
                $table->json('actual_result')->nullable();
                $table->decimal('roi', 12, 4)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_actions')) {
            Schema::create('ai_actions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ai_decision_id')->nullable()->constrained('ai_decisions')->nullOnDelete();
                $table->string('action_key')->index();
                $table->string('risk_level')->default('low');
                $table->string('status')->default('pending')->index();
                $table->string('idempotency_key')->nullable()->unique();
                $table->json('parameters')->nullable();
                $table->json('policy_result')->nullable();
                $table->json('result')->nullable();
                $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('executed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_approvals')) {
            Schema::create('ai_approvals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ai_decision_id')->constrained('ai_decisions')->cascadeOnDelete();
                $table->foreignId('ai_action_id')->nullable()->constrained('ai_actions')->nullOnDelete();
                $table->string('status')->default('pending')->index();
                $table->json('requested_payload')->nullable();
                $table->json('modified_payload')->nullable();
                $table->text('admin_note')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_usage_logs')) {
            Schema::create('ai_usage_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ai_decision_id')->nullable()->constrained('ai_decisions')->nullOnDelete();
                $table->string('agent_key')->nullable()->index();
                $table->string('provider')->index();
                $table->string('model')->index();
                $table->unsignedInteger('input_tokens')->default(0);
                $table->unsignedInteger('output_tokens')->default(0);
                $table->decimal('estimated_cost', 12, 6)->default(0);
                $table->unsignedInteger('latency_ms')->nullable();
                $table->string('status')->default('success')->index();
                $table->text('error')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_alerts')) {
            Schema::create('ai_alerts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ai_decision_id')->nullable()->constrained('ai_decisions')->nullOnDelete();
                $table->string('agent_key')->nullable()->index();
                $table->string('severity')->default('info')->index();
                $table->string('title');
                $table->text('message')->nullable();
                $table->string('status')->default('open')->index();
                $table->json('context')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_forecasts')) {
            Schema::create('ai_forecasts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('area_id')->nullable()->constrained('delivery_areas')->nullOnDelete();
                $table->string('forecast_type')->default('demand')->index();
                $table->timestamp('window_start')->nullable()->index();
                $table->timestamp('window_end')->nullable()->index();
                $table->unsignedInteger('window_minutes')->default(60);
                $table->unsignedInteger('expected_orders')->default(0);
                $table->unsignedInteger('lower_bound')->default(0);
                $table->unsignedInteger('upper_bound')->default(0);
                $table->decimal('confidence', 5, 4)->default(0);
                $table->unsignedInteger('required_drivers')->default(0);
                $table->unsignedInteger('available_drivers')->default(0);
                $table->integer('shortage')->default(0);
                $table->json('signals')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_forecast_results')) {
            Schema::create('ai_forecast_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ai_forecast_id')->constrained('ai_forecasts')->cascadeOnDelete();
                $table->unsignedInteger('actual_orders')->nullable();
                $table->unsignedInteger('actual_available_drivers')->nullable();
                $table->decimal('accuracy', 7, 4)->nullable();
                $table->json('outcome')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('driver_performance_scores')) {
            Schema::create('driver_performance_scores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
                $table->date('score_date')->index();
                $table->decimal('score', 6, 2)->default(0);
                $table->string('classification')->default('review')->index();
                $table->json('factors')->nullable();
                $table->json('raw_metrics')->nullable();
                $table->timestamps();
                $table->unique(['driver_id', 'score_date']);
            });
        }

        foreach ([
            'ai_gig_metrics',
            'ai_incentive_metrics',
            'ai_promotion_metrics',
        ] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                Schema::create($tableName, function (Blueprint $table) use ($tableName) {
                    $table->id();
                    $table->foreignId('ai_decision_id')->nullable()->constrained('ai_decisions')->nullOnDelete();
                    $table->unsignedBigInteger('subject_id')->nullable()->index();
                    $table->string('subject_type')->nullable();
                    $table->decimal('budget', 14, 2)->default(0);
                    $table->decimal('actual_spend', 14, 2)->default(0);
                    $table->decimal('incremental_contribution', 14, 2)->default(0);
                    $table->decimal('roi', 12, 4)->nullable();
                    $table->json('metrics')->nullable();
                    $table->string('status')->default('pending')->index();
                    $table->timestamps();
                    $table->index(['subject_type', 'subject_id'], $tableName . '_subject_idx');
                });
            }
        }

        if (! Schema::hasTable('financial_exceptions')) {
            Schema::create('financial_exceptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ai_decision_id')->nullable()->constrained('ai_decisions')->nullOnDelete();
                $table->string('exception_type')->index();
                $table->string('severity')->default('medium')->index();
                $table->string('status')->default('open')->index();
                $table->decimal('expected_amount', 14, 2)->nullable();
                $table->decimal('actual_amount', 14, 2)->nullable();
                $table->decimal('variance_amount', 14, 2)->nullable();
                $table->json('context')->nullable();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('reconciliation_exceptions')) {
            Schema::create('reconciliation_exceptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ai_decision_id')->nullable()->constrained('ai_decisions')->nullOnDelete();
                $table->morphs('reconcilable', 'reconcilable_index');
                $table->string('exception_type')->index();
                $table->string('severity')->default('medium')->index();
                $table->string('status')->default('open')->index();
                $table->decimal('expected_amount', 14, 2)->nullable();
                $table->decimal('actual_amount', 14, 2)->nullable();
                $table->decimal('variance_amount', 14, 2)->nullable();
                $table->json('context')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        $this->seedDefaults();
    }

    public function down(): void
    {
        foreach ([
            'reconciliation_exceptions',
            'financial_exceptions',
            'ai_promotion_metrics',
            'ai_incentive_metrics',
            'ai_gig_metrics',
            'driver_performance_scores',
            'ai_forecast_results',
            'ai_forecasts',
            'ai_alerts',
            'ai_usage_logs',
            'ai_approvals',
            'ai_actions',
            'ai_decisions',
            'ai_agent_tools',
            'ai_agents',
            'ai_settings',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function seedDefaults(): void
    {
        $settings = [
            ['ai_enabled', '0', 'boolean', false, 'Enable AI management features.'],
            ['ai_provider', 'gemini', 'string', false, 'Primary AI provider.'],
            ['ai_fallback_provider', 'openai', 'string', false, 'Fallback AI provider.'],
            ['ai_autonomy_mode', 'monitor', 'string', false, 'off, monitor, assist, auto, autonomous.'],
            ['ai_simulation_mode', '1', 'boolean', false, 'Record actions without executing them.'],
            ['ai_kill_switch', '0', 'boolean', false, 'Emergency stop for all AI execution.'],
            ['ai_low_risk_auto_enabled', '0', 'boolean', false, 'Allow low-risk auto actions when mode is auto and simulation is off.'],
            ['ai_daily_budget_usd', '5', 'number', false, 'Daily AI API budget in USD.'],
            ['ai_max_action_amount', '1000', 'number', false, 'Maximum amount an AI action can request before blocking.'],
            ['ai_min_margin_percent', '8', 'number', false, 'Minimum margin percent for AI recommendations.'],
            ['openai_model', 'gpt-4o-mini', 'string', false, 'Default OpenAI model.'],
            ['gemini_model', 'gemini-1.5-flash', 'string', false, 'Default Gemini model.'],
            ['ai_daily_budget', '0', 'decimal', false, 'Daily AI API budget.'],
            ['ai_monthly_budget', '0', 'decimal', false, 'Monthly AI API budget.'],
            ['ai_max_model_cost_per_decision', '0', 'decimal', false, 'Maximum estimated model cost per decision.'],
            ['ai_minimum_admin_margin', '10', 'decimal', false, 'Minimum admin contribution margin percent.'],
            ['ai_target_admin_margin', '12', 'decimal', false, 'Target admin contribution margin percent.'],
            ['ai_maximum_gig_guarantee', '300', 'decimal', false, 'Maximum automatic gig guarantee.'],
            ['ai_maximum_per_order_incentive', '50', 'decimal', false, 'Maximum automatic per-order incentive.'],
            ['ai_maximum_daily_incentive_budget', '5000', 'decimal', false, 'Daily automatic incentive budget.'],
            ['ai_maximum_promotion_budget', '10000', 'decimal', false, 'Daily automatic promotion budget.'],
            ['ai_maximum_automatic_financial_action', '1000', 'decimal', false, 'Maximum low-risk financial action.'],
            ['ai_maximum_cod_outstanding', '0', 'decimal', false, 'COD exception threshold.'],
            ['openai_api_key', '', 'string', true, 'Encrypted OpenAI API key.'],
            ['gemini_api_key', '', 'string', true, 'Encrypted Gemini API key.'],
        ];

        foreach ($settings as [$key, $value, $type, $secret, $description]) {
            DB::table('ai_settings')->updateOrInsert(
                ['key' => $key],
                [
                    'value' => $value,
                    'type' => $type,
                    'is_secret' => $secret,
                    'description' => $description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $agents = [
            ['operations', 'Operations Agent', 'Order health, SLA, delays, cancellations and bottlenecks.'],
            ['fleet', 'Fleet Agent', 'Driver supply, demand forecasts, gigs, incentives and positioning.'],
            ['finance', 'Finance Agent', 'Income, cost, margin, budgets and profitability.'],
            ['accounting', 'Accounting Agent', 'Settlements, COD, reconciliation and financial exceptions.'],
            ['promotion', 'Promotion Agent', 'Customer offers, incentives and ROI.'],
            ['risk', 'Risk Agent', 'Policy, anomaly and approval risk classification.'],
        ];

        foreach ($agents as [$key, $name, $description]) {
            DB::table('ai_agents')->updateOrInsert(
                ['key' => $key],
                [
                    'name' => $name,
                    'description' => $description,
                    'is_enabled' => true,
                    'max_auto_risk' => $key === 'risk' ? 'medium' : 'low',
                    'config' => json_encode([]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $permissions = [
            'ai.view',
            'ai.configure',
            'ai.autonomy.change',
            'ai.decisions.view',
            'ai.actions.execute',
            'ai.approvals.manage',
            'ai.finance.view',
            'ai.accounting.view',
            'ai.cost.view',
            'ai.kill_switch',
        ];

        if (class_exists(Permission::class) && Schema::hasTable('permissions')) {
            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, 'web');
            }

            foreach (['super_admin', 'admin'] as $roleName) {
                $role = Role::findOrCreate($roleName, 'web');
                $role->givePermissionTo($permissions);
            }
        }
    }
};
