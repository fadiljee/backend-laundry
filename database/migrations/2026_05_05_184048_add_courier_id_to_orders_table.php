<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            // Menambahkan kolom courier_id, boleh kosong (nullable) kalau belum ada kurir
            $table->unsignedBigInteger('courier_id')->nullable()->after('status');
            
            // Opsional: Menjadikannya Foreign Key ke tabel users
            // $table->foreign('courier_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            // $table->dropForeign(['courier_id']); // Buka komen ini jika pakai foreign key
            $table->dropColumn('courier_id');
        });
    }
};