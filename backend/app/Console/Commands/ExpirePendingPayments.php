<?php

namespace App\Console\Commands;

use App\Services\PaymentExpirationService;
use Illuminate\Console\Command;

class ExpirePendingPayments extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'payments:expire
                            {--minutes=30 : Payment expiration timeout in minutes}';

    /**
     * The console command description.
     */
    protected $description = 'Expire pending payments and release reserved inventory';

    /**
     * Execute the console command.
     */
    public function handle(
        PaymentExpirationService $expirationService
    ): int {
        $minutes = (int) $this->option('minutes');

        if ($minutes <= 0) {
            $this->error(
                'The expiration timeout must be greater than zero.'
            );

            return self::FAILURE;
        }

        $this->info(
            "Checking for pending payments older than {$minutes} minutes..."
        );

        $expiredCount = $expirationService->expirePendingPayments(
            $minutes
        );

        if ($expiredCount === 0) {
            $this->info(
                'No expired pending payments found.'
            );

            return self::SUCCESS;
        }

        $this->info(
            "{$expiredCount} pending payment(s) expired successfully."
        );

        return self::SUCCESS;
    }
}