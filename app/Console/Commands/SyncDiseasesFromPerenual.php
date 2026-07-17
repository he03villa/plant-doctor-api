<?php

namespace App\Console\Commands;

use App\Services\PerenualService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sync:diseases-from-perenual')]
#[Description('Sync diseases from Perenual API to local database')]
class SyncDiseasesFromPerenual extends Command
{
    public function __construct(
        private PerenualService $perenualService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!$this->perenualService->isConfigured()) {
            $this->error('Perenual API key not configured. Set PERENUAL_API_KEY in .env');
            return self::FAILURE;
        }

        $this->info('Syncing diseases from Perenual API...');

        $result = $this->perenualService->syncDiseases();

        $this->info("Sync completed:");
        $this->info("  Total synced: {$result['synced']}");
        $this->info("  Created: {$result['created']}");
        $this->info("  Updated: {$result['updated']}");

        return self::SUCCESS;
    }
}
