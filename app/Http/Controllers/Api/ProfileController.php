<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;


class ProfileController extends Controller
{
    ///GET PROFILE
    public function show()
    {
        $user = JWTAuth::parseToken()->authenticate();

        return response()->json([
            'status' => true,
            'user' => $user,
        ]);
    }

    public function update(Request $request)
    {
        try {

            $user = JWTAuth::parseToken()->authenticate();

            $request->validate([
                'name' => 'sometimes|string|min:3|max:100',
                'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
            ], [
                'email.email' => 'Format email tidak valid',
                'email.unique' => 'Email sudah digunakan',
                'name.min' => 'Nama minimal 3 karakter',
            ]);

            $data = [];

            if ($request->filled('name')) {
                $data['name'] = trim($request->name);
            }

            if ($request->filled('email')) {
                $data['email'] = strtolower(trim($request->email));
            }

            $user->update($data);

            return response()->json([
                'status' => true,
                'message' => 'Profile berhasil diperbarui',
                'user' => $user->fresh(),
            ]);
        } catch (ValidationException $e) {

            return response()->json([
                'status' => false,
                'message' => collect(
                    $e->errors()
                )->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {

            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan',
            ], 500);
        }
    }

    public function uploadPhoto(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $path = $request
            ->file('photo')
            ->store('profile', 'public');

        $user->update([
            'photo' => $path
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Foto profile berhasil diupload',
            'photo' => asset('storage/' . $path),
        ]);
    }
}
