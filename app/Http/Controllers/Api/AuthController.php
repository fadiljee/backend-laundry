<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    /**
     * LOGIN
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Email atau password salah.'], 401);
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'photo_url' => $user->photo_url, // Kirim URL foto saat login
            ]
        ]);
    }

    /**
     * MANAJEMEN KURIR: GET ALL COURIERS
     */
    public function getCouriers()
    {
        // Hanya ambil user yang rolenya courier
        $couriers = User::where('role', 'courier')->get();
        
        return response()->json([
            'data' => $couriers->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'photo_url' => $user->photo_url, // Menggunakan accessor dari model
                ];
            })
        ]);
    }

    /**
     * MANAJEMEN KURIR: ADD NEW COURIER
     */
    public function storeCourier(Request $request)
{
    $request->validate([
        'name' => 'required|string',
        'email' => 'required|email|unique:users',
        'password' => 'required|min:8',
        'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    $path = null;
    if ($request->hasFile('photo')) {
        // Simpan ke storage/app/public/couriers
        $path = $request->file('photo')->store('couriers', 'public');
    }

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => bcrypt($request->password),
        'role' => 'courier',
        'photo' => $path,
    ]);

    return response()->json(['message' => 'Kurir berhasil dibuat', 'data' => $user], 201);
}

// FUNGSI UPDATE (EDIT KURIR)
public function updateCourier(Request $request, $id)
{
    $user = User::findOrFail($id);

    // Ingat: Flutter kirim _method: PUT lewat POST multipart
    $user->name = $request->name;
    $user->email = $request->email;

    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email,' . $id,
        'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    ]);

    $user->name = $request->name;
    $user->email = $request->email;

    if ($request->filled('password')) {
        $user->password = bcrypt($request->password);
    }

    if ($request->hasFile('photo')) {
        // Hapus foto lama jika ada agar storage tidak penuh
        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }
        $user->photo = $request->file('photo')->store('couriers', 'public');
    }

    $user->save();
    return response()->json(['message' => 'Data kurir diperbarui', 'data' => $user]);
}

    /**
     * MANAJEMEN KURIR: DELETE COURIER
     */
    public function destroyCourier($id)
    {
        $user = User::findOrFail($id);
        
        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }
        
        $user->delete();

        return response()->json(['message' => 'Kurir berhasil dihapus']);
    }

    /**
     * LOGOUT
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Berhasil logout']);
    }
}