<?php

namespace App\Console\Commands;

use App\Services\ZohoService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:check-expiring-contracts')]
#[Description('Command description')]
class CheckExpiringContracts extends Command
{
    /**
     * Execute the console command.
     */
    protected $signature = 'contracts:check-expiring {--days=30 : Expiry window in days}';
    protected $description = 'Automatically scan expiring contracts and create follow-up renewal tasks in Zoho CRM';

    public function handle(ZohoService $zohoService)
    {
        $days = (int) $this->option('days');
        $this->info("Scanning Zoho CRM for contracts expiring in next {$days} days...");

        $result = $zohoService->autoCreateExpiringFollowups($days);

        $created = $result['created'] ?? 0;
        $this->info("Done! Total renewal tasks created: {$created}");

        return Command::SUCCESS;
    }
}
