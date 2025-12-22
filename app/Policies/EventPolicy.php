<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Determine if user can view any events
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine if user can view the event
     */
    public function view(User $user, Event $event): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (!$event->category_id) {
            return false;
        }

        return $user->hasAccessToCategory($event->category_id);
    }

    /**
     * Determine if user can create events
     */
    public function create(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->categories()
            ->wherePivotIn('permission', ['edit', 'manage'])
            ->exists();
    }

    /**
     * Determine if user can update the event
     */
    public function update(User $user, Event $event): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (!$event->category_id) {
            return false;
        }

        return $user->canEditCategory($event->category_id);
    }

    /**
     * Determine if user can delete the event
     */
    public function delete(User $user, Event $event): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (!$event->category_id) {
            return false;
        }

        return $user->canManageCategory($event->category_id);
    }
}
