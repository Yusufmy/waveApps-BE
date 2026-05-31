<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /// GET PROFILE
    public function show()
    {
        $user = JWTAuth::parseToken()->authenticate();

        return response()->json([
            'status' => true,
            'user' => $user,
        ]);
    }

    /// UPDATE PROFILE
    public function update(Request $request)
    {
        try {

            $user = JWTAuth::parseToken()->authenticate();

            $request->validate([
                'name' => 'sometimes|string|min:3|max:100',
                'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
                'username' => 'sometimes|string|min:3|max:30|unique:users,username,' . $user->id,
                'bio' => 'sometimes|string|max:500',
            ], [
                'email.email' => 'Format email tidak valid',
                'email.unique' => 'Email sudah digunakan',

                'name.min' => 'Nama minimal 3 karakter',

                'username.min' => 'Username minimal 3 karakter',
                'username.unique' => 'Username sudah digunakan',

                'bio.max' => 'Bio maksimal 500 karakter',
            ]);

            $data = [];

            $auth = app('firebase.auth');

            /**
             * UPDATE NAME
             */
            if ($request->filled('name')) {

                $newName = trim($request->name);

                try {

                    if (!empty($user->firebase_uid)) {
                        $auth->updateUser(
                            $user->firebase_uid,
                            [
                                'displayName' => $newName,
                            ]
                        );
                    }

                    $data['name'] = $newName;
                } catch (\Throwable $e) {

                    return response()->json([
                        'status' => false,
                        'message' => 'Gagal memperbarui nama Firebase',
                        'error' => $e->getMessage(),
                    ], 422);
                }
            }

            /**
             * UPDATE EMAIL
             */
            if (
                $request->filled('email') &&
                strtolower(trim($request->email)) !== strtolower($user->email)
            ) {

                $newEmail = strtolower(
                    trim($request->email)
                );

                try {

                    if (!empty($user->firebase_uid)) {
                        $auth->updateUser(
                            $user->firebase_uid,
                            [
                                'email' => $newEmail,
                            ]
                        );
                    }

                    $data['email'] = $newEmail;
                } catch (\Throwable $e) {

                    return response()->json([
                        'status' => false,
                        'message' => 'Gagal memperbarui email Firebase',
                        'error' => $e->getMessage(),
                    ], 422);
                }
            }

            /**
             * UPDATE USERNAME
             */
            if ($request->filled('username')) {
                $data['username'] = strtolower(
                    trim($request->username)
                );
            }

            /**
             * UPDATE BIO
             */
            if ($request->has('bio')) {
                $data['bio'] = trim($request->bio);
            }

            /**
             * UPDATE MYSQL
             */
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /// UPLOAD PHOTO
    public function uploadPhoto(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();

        $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        /**
         * Hapus foto lama
         */
        if (
            $user->photo &&
            Storage::disk('public')->exists($user->photo)
        ) {
            Storage::disk('public')->delete(
                $user->photo
            );
        }

        /**
         * Upload foto baru
         */
        $path = $request
            ->file('photo')
            ->store(
                'profile',
                'public'
            );

        /**
         * Update database
         */
        $user->update([
            'photo' => $path
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Foto profile berhasil diperbarui',
            'photo' => asset('storage/' . $path),
            'user' => $user->fresh(),
        ]);
    }
}
