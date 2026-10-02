<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request)
    {
        $user = DB::transaction(function () use ($request) {
            // write user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
            ]);
            // write business
            $business = Business::create([
                'name' => $request->business_name,
                'slug' => Str::slug($request->business_name),
                'timezone' => 'Asia/Manila',
            ]);

            // attach user -> business
            $business->users()->attach($user->id, [
                'role' => 'owner',
            ]);

            return $user;
        });

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json(
            [
                'data' => new UserResource($user),
                'token' => $token,
            ],
            201,
        );
    }
}
