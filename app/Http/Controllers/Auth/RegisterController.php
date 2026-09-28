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
    public function store(RegisterRequest $request)
    {
        $user = DB::transaction(function () use ($request) {
            // 1. Create the tenant business
            $business = Business::create([
                'name' => $request->business_name,
                'slug' => Str::slug($request->business_name).'-'.Str::random(6),
                'timezone' => 'Asia/Manila',
            ]);

            // 2. Create the user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password, // automatically hashed by User model casts
            ]);

            // 3. Link them together as owner
            $business->users()->attach($user, ['role' => 'owner']);

            return $user;
        });

        // 4. Issue Sanctum token
        $token = $user->createToken('auth_token')->plainTextToken;

        // 5. Return JSON resource with 201 Created
        return (new UserResource($user))
            ->additional(['token' => $token])
            ->response()
            ->setStatusCode(201);
    }
}
