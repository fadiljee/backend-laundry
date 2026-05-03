<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

   protected $fillable = [
        'order_code',
        'customer_name',
        'wa_number',
        'address',
        'weight',
        'service',
        'status',
        'total_price',
        'customer_lat', // <-- Tambahkan ini
        'customer_lng', // <-- Tambahkan ini
        'courier_lat',
        'courier_lng',
        'image_path',
    ];

    // 1. Beri tahu Laravel untuk menyertakan atribut 'image_url' saat diubah ke JSON
    protected $appends = ['image_url'];

    // 2. Buat fungsi pembuat URL otomatis
    public function getImageUrlAttribute()
    {
        if ($this->image_path) {
            return asset('storage/' . $this->image_path);
        }
        return null;
    }

    // Relasi ke history (One to Many)
    public function logs()
    {
        return $this->hasMany(TrackingLog::class)->orderBy('created_at', 'desc');
    }
}