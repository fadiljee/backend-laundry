<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CourierController extends Controller
{
    // 1. Ambil semua data kurir
    public function index()
    {
        $couriers = User::where('role', 'courier')->get(['id', 'name', 'email', 'created_at']);
        return response()->json(['data' => $couriers]);
    }

    // 2. Tambah kurir baru
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $courier = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'courier', // Paksa role menjadi courier
        ]);

        return response()->json(['message' => 'Kurir berhasil ditambahkan', 'data' => $courier], 201);
    }

    // 3. Hapus kurir
    public function destroy($id)
    {
        $courier = User::where('id', $id)->where('role', 'courier')->first();
        if (!$courier) {
            return response()->json(['message' => 'Kurir tidak ditemukan'], 404);
        }

        $courier->delete();
        return response()->json(['message' => 'Kurir berhasil dihapus']);
    }

    // 4. Edit data kurir
    public function update(Request $request, $id)
    {
        $courier = User::where('id', $id)->where('role', 'courier')->first();
        
        if (!$courier) {
            return response()->json(['message' => 'Kurir tidak ditemukan'], 404);
        }

        // Validasi: Email harus unik, tapi abaikan email milik kurir itu sendiri
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:6', // Password boleh kosong jika tidak ingin diubah
        ]);

        $courier->name = $request->name;
        $courier->email = $request->email;
        
        // Hanya update password jika form password diisi
        if ($request->filled('password')) {
            $courier->password = Hash::make($request->password);
        }

        $courier->save();

        return response()->json([
            'message' => 'Data kurir berhasil diperbarui',
            'data' => $courier
        ]);
    }
}