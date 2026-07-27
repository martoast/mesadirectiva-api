<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;

class MigrateEventsStripeAccount extends Command
{
    protected $signature = 'stripe:migrate-events
                            {account : Target Stripe account (cafeteria|rifa|eventos)}
                            {--year= : Only events starting in this year (default: current year)}
                            {--all : Migrate every event regardless of year}
                            {--dry-run : List affected events without changing anything}';

    protected $description = 'Point events at a different Stripe account (clears their account-scoped Stripe product/price ids)';

    public function handle(): int
    {
        $account = $this->argument('account');

        if (!config("services.stripe.accounts.{$account}")) {
            $this->error("Unknown Stripe account [{$account}]. Valid: " . implode(', ', array_keys(config('services.stripe.accounts'))));
            return Command::FAILURE;
        }

        $query = Event::query()->where('stripe_account', '!=', $account);

        if (!$this->option('all')) {
            $year = (int) ($this->option('year') ?: now()->year);
            $query->whereYear('starts_at', $year);
        }

        $events = $query->get(['id', 'slug', 'name', 'starts_at', 'stripe_account']);

        if ($events->isEmpty()) {
            $this->info('No events to migrate.');
            return Command::SUCCESS;
        }

        $this->table(
            ['ID', 'Slug', 'Starts', 'Current account'],
            $events->map(fn ($e) => [$e->id, $e->slug, $e->starts_at?->toDateString(), $e->stripe_account])
        );

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$events->count()} event(s) would move to [{$account}].");
            return Command::SUCCESS;
        }

        if (!$this->confirm("Move {$events->count()} event(s) to the [{$account}] Stripe account?")) {
            return Command::SUCCESS;
        }

        Event::whereIn('id', $events->pluck('id'))->update([
            'stripe_account' => $account,
            // Product/price ids belong to the old account; publish() recreates them.
            'stripe_product_id' => null,
            'stripe_price_id' => null,
        ]);

        $this->info("Done. {$events->count()} event(s) now collect on [{$account}].");
        $this->warn('Reminder: completed orders keep their original stripe_account for refund tracing.');

        return Command::SUCCESS;
    }
}
