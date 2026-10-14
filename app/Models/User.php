<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    public $timestamps = false; // Karena tidak ada kolom updated_at

    protected $fillable = [
        'email',
        'phone_number',
        'password_hash',
        'role_id',
        'is_active',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    // Beritahu Laravel untuk membaca password dari kolom password_hash
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }
}
