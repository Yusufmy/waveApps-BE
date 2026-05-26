<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    ///FUNGSI ME
    public function me()
    {
        return response()->json([
            'status' => true,
            'user' => JWTAuth::parseToken()->authenticate(),
        ]);
    }

    ///FUNGSI REGISTER
    public function register(Request $request)
    {
        try {

            $request->validate([
                'name' => 'required|string|min:3|max:100',
                'email' => 'required|email:rfc,dns|max:255|unique:users,email',
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'max:32',
                    'regex:/[A-Z]/',
                    'regex:/[a-z]/',
                    'regex:/[0-9]/',
                ],
                'confirm_password' => 'required|same:password',
            ], [
                'name.required' => 'Nama wajib diisi',
                'name.min' => 'Nama minimal 3 karakter',
                'name.max' => 'Nama maksimal 100 karakter',

                'email.required' => 'Email wajib diisi',
                'email.email' => 'Format email tidak valid',
                'email.max' => 'Email terlalu panjang',
                'email.unique' => 'Email sudah terdaftar',

                'password.required' => 'Password wajib diisi',
                'password.min' => 'Password minimal 8 karakter',
                'password.max' => 'Password maksimal 32 karakter',
                'password.regex' => 'Password harus mengandung huruf besar, huruf kecil, dan angka',

                'confirm_password.required' => 'Konfirmasi password wajib diisi',
                'confirm_password.same' => 'Konfirmasi password tidak cocok',
            ]);

            $auth = app('firebase.auth');

            $firebaseUser = $auth->createUser([
                'email' => $request->email,
                'password' => $request->password,
                'displayName' => trim($request->name),
            ]);

            $user = User::create([
                'firebase_uid' => $firebaseUser->uid,
                'name' => trim($request->name),
                'email' => strtolower(trim($request->email)),
                'password' => Hash::make($request->password),
            ]);

            $user->update([
                'is_online' => true,
                'last_seen' => now(),
            ]);

            $token = JWTAuth::fromUser($user);

            return response()->json([
                'status' => true,
                'message' => 'Registrasi berhasil',
                'token_type' => 'Bearer',
                'access_token' => $token,
                'user' => $user,
            ]);
        } catch (ValidationException $e) {

            return response()->json([
                'status' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    ///FUNGSI LOGIN
    public function login(Request $request)
    {
        try {

            $request->validate([
                'email' => 'required|email:rfc,dns|max:255',
                'password' => 'required|string|min:8|max:32',
            ], [
                'email.required' => 'Email wajib diisi',
                'email.email' => 'Format email tidak valid',
                'email.max' => 'Email terlalu panjang',

                'password.required' => 'Password wajib diisi',
                'password.min' => 'Password minimal 8 karakter',
                'password.max' => 'Password maksimal 32 karakter',
            ]);

            $auth = app('firebase.auth');

            // Login ke Firebase
            $signInResult = $auth->signInWithEmailAndPassword(
                strtolower(trim($request->email)),
                $request->password
            );

            $firebaseUid = $signInResult->firebaseUserId();

            // Ambil data user Firebase
            $firebaseUser = $auth->getUser($firebaseUid);

            // Cek email verification



            // Cari user di database Laravel
            $user = User::where(
                'firebase_uid',
                $firebaseUid
            )->first();

            if (!$user) {

                return response()->json([
                    'status' => false,
                    'message' => 'Data user tidak ditemukan'
                ], 404);
            }

            // Generate JWT
            $token = JWTAuth::fromUser($user);

            return response()->json([
                'status' => true,
                'message' => 'Login berhasil',
                'token_type' => 'Bearer',
                'access_token' => $token,
                'user' => $user,
            ]);
        } catch (ValidationException $e) {

            return response()->json([
                'status' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {

            // Untuk production jangan tampilkan detail error asli
            return response()->json([
                'status' => false,
                'message' => 'Email atau password salah'
            ], 401);
        }
    }

    ///FUNGSI LOGOUT
    public function logout()
    {
        $user = JWTAuth::parseToken()->authenticate();

        $user->update([
            'is_online' => false,
            'last_seen' => now(),
        ]);

        JWTAuth::invalidate(
            JWTAuth::getToken()
        );

        return response()->json([
            'status' => true,
            'message' => 'Logout berhasil'
        ]);
    }
}
