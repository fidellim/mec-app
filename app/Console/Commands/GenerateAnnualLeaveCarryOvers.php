<?php

namespace App\Console\Commands;

use App\Services\AnnualLeaveCarryOverService;
use Illuminate\Console\Command;

class GenerateAnnualLeaveCarryOvers extends Command
{
    protected $signature = 'leave:generate-annual-carryovers {--from-year= : Calendar year to calculate from}';

    protected $description = 'Generate pending annual leave carry-over suggestions from unused prior-year L100 balances.';

    public function handle(AnnualLeaveCarryOverService $carryOvers): int
    {
        $fromYear = (int) ($this->option('from-year') ?: now()->subYear()->year);

        if ($fromYear < 2000 || $fromYear > 2100) {
            $this->error('The from-year option must be between 2000 and 2100.');

            return self::FAILURE;
        }

        $generated = $carryOvers->generatePendingForYear($fromYear);

        $this->info("Generated or refreshed {$generated->count()} pending annual leave carry-over suggestion(s) from {$fromYear} to ".($fromYear + 1).'.');

        return self::SUCCESS;
    }
}
