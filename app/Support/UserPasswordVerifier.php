<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserPasswordVerifier
{
    public function verifyAndUpgrade(User $user, string $password): bool
    {
        $storedPassword = $user->getAuthPassword();
        $passwordInfo = password_get_info($storedPassword);

        $passwordMatches = match ($passwordInfo['algoName']) {
            'bcrypt' => Hash::check($password, $storedPassword),
            'unknown' => hash_equals($storedPassword, $password),
            default => password_verify($password, $storedPassword),
        };

        if ($passwordMatches && $passwordInfo['algoName'] !== 'bcrypt') {
            $user->password_hash = Hash::make($password);
            $user->save();
        }

        return $passwordMatches;
    }
}
