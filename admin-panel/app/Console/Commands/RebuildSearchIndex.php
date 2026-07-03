<?php

namespace App\Console\Commands;

use App\Services\AdvancedSearchService;
use Illuminate\Console\Command;

class RebuildSearchIndex extends Command
{
    protected $signature = 'search:rebuild-index';

    protected $description = 'Rebuild the FoodFlow advanced search index.';

    public function handle(AdvancedSearchService $search): int
    {
        $this->info('Rebuilding search index...');
        $count = $search->rebuildIndex();
        $this->info("Search index rebuilt with {$count} records.");

        return self::SUCCESS;
    }
}
