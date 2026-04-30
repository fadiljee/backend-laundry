<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // Pakai cara ini, Bosku. Jangan pakai #[Fillable] di atas class
   protected $fillable = [
    'name', 'email', 'password', 'role', 'photo', // Pastikan 'photo' ada di sini
];

// Menambahkan field virtual 'photo_url' ke JSON response
protected $appends = ['photo_url'];

public function getPhotoUrlAttribute()
{
    // asset() akan menghasilkan http://domain.com/storage/path_foto
    return $this->photo ? asset('storage/couriers/' . $this->photo) : null;
}
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $attributes = [
        'role' => 'courier',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    
    // ... method isAdmin dan isCourier tetap sama ...
}