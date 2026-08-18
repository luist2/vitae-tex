<?php

namespace App\Policies;

use App\Models\Cv;
use App\Models\User;

class CvPolicy
{
    /**
     * Determine whether the user can view any CVs.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the CV.
     */
    public function view(User $user, Cv $cv): bool
    {
        return $this->owns($user, $cv);
    }

    /**
     * Determine whether the user can create CVs.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the CV.
     */
    public function update(User $user, Cv $cv): bool
    {
        return $this->owns($user, $cv);
    }

    /**
     * Determine whether the user can delete the CV.
     */
    public function delete(User $user, Cv $cv): bool
    {
        return $this->owns($user, $cv);
    }

    /**
     * Determine whether the user can duplicate the CV.
     */
    public function duplicate(User $user, Cv $cv): bool
    {
        return $this->owns($user, $cv);
    }

    /**
     * Determine whether the user can download documents for the CV.
     */
    public function download(User $user, Cv $cv): bool
    {
        return $this->owns($user, $cv);
    }

    /**
     * Determine whether the user can generate documents for the CV.
     */
    public function generate(User $user, Cv $cv): bool
    {
        return $this->owns($user, $cv);
    }

    private function owns(User $user, Cv $cv): bool
    {
        return $user->getKey() === $cv->user_id;
    }
}
