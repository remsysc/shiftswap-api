<?php

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    [$this->business, $this->owner] = createOwner();
    [, $this->manager] = createManager($this->business);
    [, $this->staff] = createStaff($this->business);
});

test('owner can invite staff member', function (): void {
    $response = $this->actingAs($this->owner, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/staff", [
            'email' => 'newstaff@example.com',
            'role' => 'staff',
        ]);

    $response->assertStatus(202)
        ->assertJsonStructure([
            'data' => ['id', 'email', 'status', 'token', 'created_at'],
        ])
        ->assertJsonPath('data.email', 'newstaff@example.com')
        ->assertJsonPath('data.status', 'pending');

    $this->assertDatabaseHas('invitations', [
        'business_id' => $this->business->id,
        'email' => 'newstaff@example.com',
        'status' => 'pending',
    ]);
});

test('manager can invite staff member', function (): void {
    $response = $this->actingAs($this->manager, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/staff", [
            'email' => 'anotherstaff@example.com',
        ]);

    $response->assertStatus(202)
        ->assertJsonPath('data.email', 'anotherstaff@example.com')
        ->assertJsonPath('data.status', 'pending');
});

test('staff member cannot invite staff', function (): void {
    $response = $this->actingAs($this->staff, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/staff", [
            'email' => 'forbidden@example.com',
        ]);

    $response->assertForbidden();
});

test('cross-tenant user cannot invite staff', function (): void {
    [$otherBusiness, $otherOwner] = createOwner();

    $response = $this->actingAs($otherOwner, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/staff", [
            'email' => 'crosstenant@example.com',
        ]);

    $response->assertForbidden();
});

test('inviting existing registered user returns 422 with specific error', function (): void {
    $existingUser = User::factory()->create(['email' => 'alreadyregistered@example.com']);

    $response = $this->actingAs($this->owner, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/staff", [
            'email' => $existingUser->email,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email'])
        ->assertJsonPath('errors.email.0', 'This user already belongs to a business.');
});

test('resending invitation invalidates old token and replaces with new 48-hour token', function (): void {
    $oldToken = 'initial-token-123456789012345678901234567890';
    $invitation = Invitation::factory()->create([
        'business_id' => $this->business->id,
        'email' => 'pendinguser@example.com',
        'token_hash' => hash('sha256', $oldToken),
        'status' => 'pending',
        'expires_at' => now()->addHour(),
        'invited_by' => $this->owner->id,
    ]);

    $response = $this->actingAs($this->owner, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/staff", [
            'email' => 'pendinguser@example.com',
        ]);

    $response->assertStatus(202);
    $newToken = $response->json('data.token');
    expect($newToken)->not->toBeNull();
    expect($newToken)->not->toBe($oldToken);

    $invitation->refresh();
    expect($invitation->token_hash)->toBe(hash('sha256', $newToken));
    expect($invitation->expires_at->greaterThan(now()->addHours(47)))->toBeTrue();
});

test('owner and manager can view invitations list', function (): void {
    Invitation::factory()->count(2)->create([
        'business_id' => $this->business->id,
        'invited_by' => $this->owner->id,
    ]);

    $response = $this->actingAs($this->owner, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/invitations");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'email', 'status', 'created_at'],
            ],
        ]);

    $managerResponse = $this->actingAs($this->manager, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/invitations");

    $managerResponse->assertOk();

    $staffResponse = $this->actingAs($this->staff, 'sanctum')
        ->getJson("/api/businesses/{$this->business->id}/invitations");

    $staffResponse->assertForbidden();
});

test('invited user can accept invitation and is registered as staff', function (): void {
    $plainToken = Str::random(40);
    $invitation = Invitation::factory()->create([
        'business_id' => $this->business->id,
        'email' => 'brandnewstaff@example.com',
        'token_hash' => hash('sha256', $plainToken),
        'status' => 'pending',
        'expires_at' => now()->addHours(48),
        'invited_by' => $this->owner->id,
    ]);

    $response = $this->postJson("/api/invitations/{$plainToken}/accept", [
        'name' => 'Alice Staff',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'created_at'],
            'token',
        ])
        ->assertJsonPath('data.email', 'brandnewstaff@example.com');

    $this->assertDatabaseHas('users', [
        'email' => 'brandnewstaff@example.com',
        'name' => 'Alice Staff',
    ]);

    $user = User::where('email', 'brandnewstaff@example.com')->firstOrFail();

    $this->assertDatabaseHas('business_user', [
        'business_id' => $this->business->id,
        'user_id' => $user->id,
        'role' => 'staff',
    ]);

    $invitation->refresh();
    expect($invitation->status)->toBe('accepted');
});

test('accepting expired invitation returns 422 with specific error', function (): void {
    $plainToken = Str::random(40);
    $invitation = Invitation::factory()->create([
        'business_id' => $this->business->id,
        'email' => 'expireduser@example.com',
        'token_hash' => hash('sha256', $plainToken),
        'status' => 'pending',
        'expires_at' => Carbon::now()->subHour(),
        'invited_by' => $this->owner->id,
    ]);

    $response = $this->postJson("/api/invitations/{$plainToken}/accept", [
        'name' => 'Late User',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['token'])
        ->assertJsonPath('errors.token.0', 'Invalid or expired invitation.');

    $invitation->refresh();
    expect($invitation->status)->toBe('expired');
});

test('accepting already accepted invitation returns 422', function (): void {
    $plainToken = Str::random(40);
    Invitation::factory()->create([
        'business_id' => $this->business->id,
        'email' => 'alreadyused@example.com',
        'token_hash' => hash('sha256', $plainToken),
        'status' => 'accepted',
        'expires_at' => now()->addHours(24),
        'invited_by' => $this->owner->id,
    ]);

    $response = $this->postJson("/api/invitations/{$plainToken}/accept", [
        'name' => 'Double Claimer',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['token'])
        ->assertJsonPath('errors.token.0', 'Invalid or expired invitation.');
});

test('accepting non-existent invitation token returns 422', function (): void {
    $response = $this->postJson('/api/invitations/non-existent-token/accept', [
        'name' => 'Ghost',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['token'])
        ->assertJsonPath('errors.token.0', 'Invalid or expired invitation.');
});
