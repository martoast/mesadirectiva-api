<?php

namespace App\Providers;

use App\Models\Event;
use App\Models\Group;
use App\Policies\EventPolicy;
use App\Policies\GroupPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url') . '/password-reset/' . $token . '?email=' . urlencode($notifiable->getEmailForPasswordReset());
        });

        // Register policies
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(Group::class, GroupPolicy::class);

        // Gate for user management (super_admin only)
        Gate::define('manage-users', function ($user) {
            return $user->isSuperAdmin();
        });
    }
}
