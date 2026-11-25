<?php

namespace App\Console\Commands;

use App\Services\MembershipBilling;
use Illuminate\Console\Command;

class ExpireMemberships extends Command
{
    protected $signature = 'memberships:expire';

    protected $description =
        'Expire completed membership periods and deactivate lapsed memberships';

    public function handle(MembershipBilling $billing): int
    {
        $counts = $billing->expireMemberships();

        $this->info(
            "Expired {$counts['transactions']} transaction(s); "
            ."deactivated {$counts['members']} member(s)."
        );

        return self::SUCCESS;
    }
}