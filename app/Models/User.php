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
    'name', 'email', 'password', 'role', 'photo', 'phone', // Pastikan 'photo' ada di sini
];

// Menambahkan field virtual 'photo_url' ke JSON response
protected $appends = ['photo_url'];

/**
 * Otomatis mengubah path foto menjadi URL lengkap saat dipanggil oleh API
 */
/**
 * Aksesor untuk foto_url
 */
public function getPhotoUrlAttribute()
{
    // Kita ambil langsung dari kolom 'photo' yang ada di database
    $photo = $this->attributes['photo'] ?? null;

    if ($photo) {
        if (str_starts_with($photo, 'http')) {
            return $photo;
        }
        // Pastikan APP_URL di .env sudah pakai IP laptop kamu!
        return asset('storage/' . $photo);
    }
    
    return null;
}
    // ... method isAdmin dan isCourier tetap sama ...
}