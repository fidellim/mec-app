<?php

namespace App\Auth\Passwords;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class AtomicDatabaseTokenRepository extends DatabaseTokenRepository
{
    /**
     * Create or replace a password reset token in one atomic query.
     */
    public function create(CanResetPasswordContract $user): string
    {
        $email = $user->getEmailForPasswordReset();
        $token = $this->createNewToken();

        $this->getTable()->upsert(
            [$this->getPayload($email, $token)],
            ['email'],
            ['token', 'created_at'],
        );

        return $token;
    }
}
