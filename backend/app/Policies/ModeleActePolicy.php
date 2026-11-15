<?php

namespace App\Policies;

use App\Models\ModeleActe;
use App\Models\User;

class ModeleActePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->estSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->estSuperAdmin();
    }

    public function update(User $user, ModeleActe $modele): bool
    {
        return $user->estSuperAdmin();
    }

    public function delete(User $user, ModeleActe $modele): bool
    {
        return $user->estSuperAdmin();
    }
}
