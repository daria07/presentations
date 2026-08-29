<?php

namespace App\Policies;

use App\Models\Presentation;
use App\Models\User;

class PresentationPolicy
{
    public function view(User $user, Presentation $presentation): bool
    {
        return $presentation->user_id === $user->id;
    }

    public function update(User $user, Presentation $presentation): bool
    {
        return $presentation->user_id === $user->id;
    }

    public function delete(User $user, Presentation $presentation): bool
    {
        return $presentation->user_id === $user->id;
    }
}
