<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WhatsAppCatalogSync;
use Illuminate\Console\Command;

class WhatsAppCatalogSyncCommand extends Command
{
    protected $signature = 'whatsapp:catalog-sync {--force : Resend every item, not just the changed ones}';

    protected $description = 'Sync restaurant menus to the WhatsApp (Meta) product catalog.';

    public function handle(WhatsAppCatalogSync $sync): int
    {
        if (! $sync->ready()) {
            $this->info('WhatsApp catalog is not enabled.');

            return self::SUCCESS;
        }
        $r = $sync->run((bool) $this->option('force'));
        $this->info("Catalog sync: {$r['created']} added, {$r['updated']} updated, {$r['removed']} removed, {$r['failed']} failed ({$r['total']} sellable)." . ($r['error'] ? " Error: {$r['error']}" : ''));

        return $r['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
