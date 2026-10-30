<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInvitationRequest;
use App\Http\Requests\InviteStaffRequest;
use App\Http\Resources\InvitationResource;
use App\Http\Resources\UserResource;
use App\Models\Business;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    /**
     * List all invitations for a business.
     */
    public function index(Request $request, Business $business): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Invitation::class, $business]);

        return InvitationResource::collection($business->invitations()->latest()->get());
    }

    /**
     * Dispatch an invitation to a prospective staff member.
     */
    public function store(InviteStaffRequest $request, Business $business): JsonResponse
    {
        Gate::authorize('create', [Invitation::class, $business]);

        $email = (string) $request->input('email');
        $plainToken = Str::random(40);
        $tokenHash = hash('sha256', $plainToken);

        /** @var Invitation|null $invitation */
        $invitation = $business->invitations()->where('email', $email)->first();

        if ($invitation !== null) {
            $invitation->update([
                'token_hash' => $tokenHash,
                'status' => 'pending',
                'expires_at' => now()->addHours(48),
                'invited_by' => $request->user()?->id,
            ]);
        } else {
            /** @var Invitation $invitation */
            $invitation = $business->invitations()->create([
                'email' => $email,
                'token_hash' => $tokenHash,
                'status' => 'pending',
                'expires_at' => now()->addHours(48),
                'invited_by' => $request->user()?->id,
            ]);
        }

        $invitation->plain_token = $plainToken;

        return (new InvitationResource($invitation))
            ->response()
            ->setStatusCode(202);
    }

    /**
     * Accept an invitation and register the new staff user.
     */
    public function accept(AcceptInvitationRequest $request, string $token): JsonResponse
    {
        $throttleKey = 'invitation-accept:'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            abort(429, 'Too many attempts.');
        }

        $tokenHash = hash('sha256', $token);

        /** @var Invitation|null $invitation */
        $invitation = Invitation::where('token_hash', $tokenHash)->first();

        if ($invitation === null || ! $invitation->isPending()) {
            if ($invitation !== null && $invitation->isExpired() && $invitation->status === 'pending') {
                $invitation->update(['status' => 'expired']);
            }

            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'token' => ['Invalid or expired invitation.'],
            ]);
        }

        $user = DB::transaction(function () use ($request, $invitation): User {
            /** @var Invitation $lockedInvitation */
            $lockedInvitation = Invitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();

            if (! $lockedInvitation->isPending()) {
                throw ValidationException::withMessages([
                    'token' => ['Invalid or expired invitation.'],
                ]);
            }

            /** @var User $newUser */
            $newUser = User::create([
                'name' => (string) $request->input('name'),
                'email' => $lockedInvitation->email,
                'password' => Hash::make((string) $request->input('password')),
            ]);

            $lockedInvitation->business->users()->attach($newUser->id, [
                'role' => 'staff',
            ]);

            $lockedInvitation->update([
                'status' => 'accepted',
            ]);

            return $newUser;
        });

        RateLimiter::clear($throttleKey);

        $bearerToken = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'data' => new UserResource($user),
            'token' => $bearerToken,
        ], 201);
    }
}
