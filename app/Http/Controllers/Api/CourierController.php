<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class CourierController extends Controller
{
    public function index()
    {
        // Ambil semua kurir. photo_url otomatis digenerate oleh Model
        $couriers = User::where('role', 'courier')->get();
        return response()->json(['data' => $couriers]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string|max:20', // TERIMA PHONE
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048', // TERIMA FOTO
        ]);

        $path = null;
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('couriers', 'public');
        }

        $courier = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone, // SIMPAN PHONE
            'role' => 'courier',
            'photo' => $path,
        ]);

        return response()->json(['message' => 'Kurir berhasil ditambahkan', 'data' => $courier], 201);
    }

    public function update(Request $request, $id)
    {
        $courier = User::where('id', $id)->where('role', 'courier')->first();
        if (!$courier) return response()->json(['message' => 'Kurir tidak ditemukan'], 404);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $courier->name = $request->name;
        $courier->email = $request->email;
        $courier->phone = $request->phone;
        
        if ($request->filled('password')) {
            $courier->password = Hash::make($request->password);
        }

        if ($request->hasFile('photo')) {
            if ($courier->photo) {
                Storage::disk('public')->delete($courier->photo);
            }
            $courier->photo = $request->file('photo')->store('couriers', 'public');
        }

        $courier->save();
        return response()->json(['message' => 'Data kurir berhasil diperbarui', 'data' => $courier]);
    }

    public function destroy($id)
    {
        $courier = User::where('id', $id)->where('role', 'courier')->first();
        if (!$courier) return response()->json(['message' => 'Kurir tidak ditemukan'], 404);

        if ($courier->photo) {
            Storage::disk('public')->delete($courier->photo);
        }
        $courier->delete();
        
        return response()->json(['message' => 'Kurir berhasil dihapus']);
    }
}