<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Category $category): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasAccessToCategory($category->id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Category $category): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->isSuperAdmin();
    }
}
