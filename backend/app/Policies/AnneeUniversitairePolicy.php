<?php

namespace App\Policies;

use App\Models\AnneeUniversitaire;
use App\Models\User;

class AnneeUniversitairePolicy
{
    public function create(User $user): bool
    {
        return $user->estSuperAdmin();
    }

    public function update(User $user, AnneeUniversitaire $anneeUniversitaire): bool
    {
        return $user->estSuperAdmin();
    }
}
