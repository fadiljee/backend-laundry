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
        'courier_lat',
        'courier_lng',
        'image_path',
    ];

    // Relasi ke history (One to Many)
    public function logs()
    {
        return $this->hasMany(TrackingLog::class)->orderBy('created_at', 'desc');
    }
}