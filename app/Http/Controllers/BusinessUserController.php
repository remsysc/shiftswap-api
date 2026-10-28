<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\BusinessUserResource;
use App\Models\Business;
use App\Models\BusinessUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class BusinessUserController extends Controller
{
    public function index(Business $business): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [BusinessUser::class, $business]);

        $users = $business->users()->get();

        return BusinessUserResource::collection($users);
    }

    public function update(UpdateRoleRequest $request, Business $business, User $user): JsonResponse
    {
        Gate::authorize('update', [BusinessUser::class, $business]);

        // Target user must belong to this business
        if (! $user->belongsToBusiness($business)) {
            abort(404, 'User does not belong to this business.');
        }

        $business->users()->updateExistingPivot($user->id, [
            'role' => $request->role,
        ]);

        $updatedUser = $business->users()->where('users.id', $user->id)->first();

        return response()->json([
            'data' => new BusinessUserResource($updatedUser),
        ]);
    }
}
