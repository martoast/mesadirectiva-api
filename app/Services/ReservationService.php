<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Seat;
use App\Models\SeatReservation;
use App\Models\Table;
use App\Models\TableReservation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReservationService
{
    /**
     * Reserve tables and/or seats for checkout
     *
     * @param Event $event
     * @param array $tableIds
     * @param array $seatIds
     * @return array{token: string, expires_at: \Carbon\Carbon, tables: Collection, seats: Collection}
     * @throws \Exception
     */
    public function reserve(Event $event, array $tableIds = [], array $seatIds = []): array
    {
        return DB::transaction(function () use ($event, $tableIds, $seatIds) {
            $token = Str::uuid()->toString();
            $expiresAt = now()->addMinutes($event->reservation_minutes ?? 15);

            $tables = collect();
            $seats = collect();

            // Reserve tables
            if (!empty($tableIds)) {
                $tables = Table::where('event_id', $event->id)
                    ->whereIn('id', $tableIds)
                    ->lockForUpdate()
                    ->get();

                foreach ($tables as $table) {
                    if (!$table->isAvailable()) {
                        throw new \Exception("Table '{$table->name}' is not available");
                    }

                    if (!$table->sell_as_whole) {
                        throw new \Exception("Table '{$table->name}' must be purchased by individual seats");
                    }

                    TableReservation::create([
                        'table_id' => $table->id,
                        'session_token' => $token,
                        'expires_at' => $expiresAt,
                    ]);

                    $table->markAsReserved();
                }
            }

            // Reserve seats
            if (!empty($seatIds)) {
                $seats = Seat::whereIn('id', $seatIds)
                    ->whereHas('table', function ($query) use ($event) {
                        $query->where('event_id', $event->id)
                            ->where('sell_as_whole', false);
                    })
                    ->lockForUpdate()
                    ->get();

                if ($seats->count() !== count($seatIds)) {
                    throw new \Exception('One or more seats are not available for individual purchase');
                }

                foreach ($seats as $seat) {
                    if (!$seat->isAvailable()) {
                        throw new \Exception("Seat '{$seat->label}' is not available");
                    }

                    SeatReservation::create([
                        'seat_id' => $seat->id,
                        'session_token' => $token,
                        'expires_at' => $expiresAt,
                    ]);

                    $seat->markAsReserved();
                }
            }

            return [
                'token' => $token,
                'expires_at' => $expiresAt,
                'tables' => $tables,
                'seats' => $seats,
            ];
        });
    }

    /**
     * Release a reservation by token
     *
     * @param string $token
     * @return void
     */
    public function release(string $token): void
    {
        DB::transaction(function () use ($token) {
            // Release table reservations
            $tableReservations = TableReservation::where('session_token', $token)->get();
            foreach ($tableReservations as $reservation) {
                $reservation->table->markAsAvailable();
                $reservation->delete();
            }

            // Release seat reservations
            $seatReservations = SeatReservation::where('session_token', $token)->get();
            foreach ($seatReservations as $reservation) {
                $reservation->seat->markAsAvailable();
                $reservation->delete();
            }
        });
    }

    /**
     * Expire all outdated reservations
     *
     * @return int Number of expired reservations
     */
    public function expireReservations(): int
    {
        return DB::transaction(function () {
            $count = 0;

            // Expire table reservations
            $expiredTableReservations = TableReservation::expired()->get();
            foreach ($expiredTableReservations as $reservation) {
                $reservation->table->markAsAvailable();
                $reservation->delete();
                $count++;
            }

            // Expire seat reservations
            $expiredSeatReservations = SeatReservation::expired()->get();
            foreach ($expiredSeatReservations as $reservation) {
                $reservation->seat->markAsAvailable();
                $reservation->delete();
                $count++;
            }

            return $count;
        });
    }

    /**
     * Mark reservation as completed (after payment)
     *
     * @param string $token
     * @param int $orderId
     * @return void
     */
    public function completeReservation(string $token, int $orderId): void
    {
        DB::transaction(function () use ($token, $orderId) {
            // Complete table reservations
            $tableReservations = TableReservation::where('session_token', $token)->get();
            foreach ($tableReservations as $reservation) {
                $reservation->table->markAsSold();
                $reservation->update(['order_id' => $orderId]);
            }

            // Complete seat reservations
            $seatReservations = SeatReservation::where('session_token', $token)->get();
            foreach ($seatReservations as $reservation) {
                $reservation->seat->markAsSold();
                $reservation->update(['order_id' => $orderId]);
            }
        });
    }

    /**
     * Validate that a reservation token is valid for the given tables/seats
     *
     * @param string $token
     * @param array $tableIds
     * @param array $seatIds
     * @return bool
     */
    public function validateReservation(string $token, array $tableIds = [], array $seatIds = []): bool
    {
        // Check table reservations
        if (!empty($tableIds)) {
            $reservedTableIds = TableReservation::where('session_token', $token)
                ->valid()
                ->pluck('table_id')
                ->toArray();

            if (array_diff($tableIds, $reservedTableIds)) {
                return false;
            }
        }

        // Check seat reservations
        if (!empty($seatIds)) {
            $reservedSeatIds = SeatReservation::where('session_token', $token)
                ->valid()
                ->pluck('seat_id')
                ->toArray();

            if (array_diff($seatIds, $reservedSeatIds)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all items for a reservation token
     *
     * @param string $token
     * @return array{tables: Collection, seats: Collection}
     */
    public function getReservationItems(string $token): array
    {
        $tableIds = TableReservation::where('session_token', $token)
            ->valid()
            ->pluck('table_id');

        $seatIds = SeatReservation::where('session_token', $token)
            ->valid()
            ->pluck('seat_id');

        return [
            'tables' => Table::whereIn('id', $tableIds)->get(),
            'seats' => Seat::whereIn('id', $seatIds)->get(),
        ];
    }
}
