<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('home_sections')) {
            return;
        }

        DB::table('home_sections')
            ->where('section_type', 'recommended_for_you')
            ->orderBy('id')
            ->get(['id', 'configuration'])
            ->each(function ($section): void {
                $configuration = json_decode((string) $section->configuration, true);
                $configuration = is_array($configuration) ? $configuration : [];

                // Eight was the old generic form default, but the runtime
                // ignored it and forced 12. Preserve the visible card count.
                if (! isset($configuration['limit']) || (int) $configuration['limit'] === 8) {
                    $configuration['limit'] = 12;
                    DB::table('home_sections')->where('id', $section->id)->update([
                        'configuration' => json_encode($configuration),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Do not overwrite card counts subsequently chosen by administrators.
    }
};
