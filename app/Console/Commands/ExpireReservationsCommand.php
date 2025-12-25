<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

class ExpireReservationsCommand extends Command
{
    protected $signature = 'reservations:expire';

    protected $description = 'Expire old seat and table reservations and release them back to available';

    public function __construct(
        private ReservationService $reservationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->reservationService->expireReservations();

        if ($count > 0) {
            $this->info("Expired {$count} reservations and released items back to available.");
        } else {
            $this->info('No expired reservations found.');
        }

        return Command::SUCCESS;
    }
}
