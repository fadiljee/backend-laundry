<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
            */
            public function up(): void
        {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_code')->unique();
                $table->string('customer_name');
                $table->string('wa_number');
                $table->text('address');
                $table->decimal('weight', 8, 2);
                $table->string('service');
                $table->string('status')->default('Menunggu Pembayaran');
                $table->double('courier_lat')->nullable();
                $table->double('courier_lng')->nullable();
                $table->text('image_path')->nullable(); // Simpan path foto timbangan
                $table->timestamps();
            });
        }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
