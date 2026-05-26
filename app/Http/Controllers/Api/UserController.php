<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\User;



class UserController extends Controller
{
    public function index(Request $request)
    {
        $authUser =
            JWTAuth::parseToken()
            ->authenticate();

        $users = User::query()

            ->when(
                $request->search,
                function ($query) use ($request) {

                    $query->where(
                        'name',
                        'like',
                        "%{$request->search}%"
                    );
                }
            )

            ->where(
                'id',
                '!=',
                $authUser->id
            )

            ->paginate(20);

        return response()->json([
            'status' => true,
            'data' => $users
        ]);
    }
}
