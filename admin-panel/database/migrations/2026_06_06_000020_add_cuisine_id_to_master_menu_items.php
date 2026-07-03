<?php

use App\Models\Cuisine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_menu_items', function (Blueprint $table) {
            if (!Schema::hasColumn('master_menu_items', 'cuisine_id')) {
                $table->foreignId('cuisine_id')
                    ->nullable()
                    ->after('subcategory_name')
                    ->constrained('cuisines')
                    ->nullOnDelete();
            }
        });

        $cuisines = Cuisine::query()->get(['id', 'name', 'slug']);

        DB::table('master_menu_items')
            ->orderBy('id')
            ->select(['id', 'name', 'category_name', 'subcategory_name'])
            ->chunkById(100, function ($items) use ($cuisines) {
                foreach ($items as $item) {
                    $cuisineId = $this->matchCuisineId($cuisines, [
                        $item->subcategory_name,
                        $item->category_name,
                        $item->name,
                    ]);

                    if ($cuisineId) {
                        DB::table('master_menu_items')
                            ->where('id', $item->id)
                            ->update(['cuisine_id' => $cuisineId]);
                    }
                }
            });

        DB::table('menu_items')
            ->join('master_menu_items', 'menu_items.master_menu_item_id', '=', 'master_menu_items.id')
            ->whereNull('menu_items.cuisine_id')
            ->whereNotNull('master_menu_items.cuisine_id')
            ->update(['menu_items.cuisine_id' => DB::raw('master_menu_items.cuisine_id')]);
    }

    public function down(): void
    {
        Schema::table('master_menu_items', function (Blueprint $table) {
            if (Schema::hasColumn('master_menu_items', 'cuisine_id')) {
                $table->dropConstrainedForeignId('cuisine_id');
            }
        });
    }

    private function matchCuisineId($cuisines, array $terms): ?int
    {
        $normalizedTerms = collect($terms)
            ->filter(fn ($term) => filled($term))
            ->map(fn ($term) => Str::slug((string) $term))
            ->filter()
            ->values();

        foreach ($normalizedTerms as $term) {
            $match = $cuisines->first(function ($cuisine) use ($term) {
                $name = Str::slug((string) $cuisine->name);
                $slug = Str::slug((string) ($cuisine->slug ?: $cuisine->name));

                return $term === $name ||
                    $term === $slug ||
                    str_contains($term, $name) ||
                    str_contains($name, $term) ||
                    str_contains($term, $slug) ||
                    str_contains($slug, $term);
            });

            if ($match) {
                return (int) $match->id;
            }
        }

        return null;
    }
};
