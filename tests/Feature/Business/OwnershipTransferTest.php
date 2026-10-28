<?php

use App\Models\OwnershipTransfer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    [$this->business, $this->owner] = createOwner();
    [, $this->manager] = createManager($this->business);
    [, $this->staff] = createStaff($this->business);
});

test('owner can initiate ownership transfer to a member (spec 5.2)', function (): void {
    $response = $this->actingAs($this->owner, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/ownership-transfer", [
            'successor_email' => $this->manager->email,
        ]);

    $response->assertStatus(202)
        ->assertJsonStructure([
            'data' => [
                'id',
                'business_id',
                'current_owner' => ['id', 'email'],
                'successor' => ['id', 'email'],
                'status',
                'token',
                'expires_at',
            ],
        ])
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.current_owner.id', $this->owner->id)
        ->assertJsonPath('data.successor.id', $this->manager->id);

    $token = $response->json('data.token');
    expect($token)->not->toBeNull();

    $this->assertDatabaseHas('ownership_transfers', [
        'business_id' => $this->business->id,
        'current_owner_id' => $this->owner->id,
        'successor_id' => $this->manager->id,
        'token_hash' => hash('sha256', $token),
        'status' => 'pending',
    ]);
});

test('non-owner cannot initiate ownership transfer', function (): void {
    $this->actingAs($this->manager, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/ownership-transfer", [
            'successor_email' => $this->staff->email,
        ])
        ->assertForbidden();

    $this->actingAs($this->staff, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/ownership-transfer", [
            'successor_email' => $this->manager->email,
        ])
        ->assertForbidden();
});

test('transferring to non-member or self returns 422', function (): void {
    $outsider = User::factory()->create();

    // Outsider
    $outsiderResponse = $this->actingAs($this->owner, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/ownership-transfer", [
            'successor_email' => $outsider->email,
        ]);

    $outsiderResponse->assertStatus(422)
        ->assertJsonValidationErrors(['successor_email'])
        ->assertJsonPath('errors.successor_email.0', 'Successor must be an active member of this business.');

    // Self
    $selfResponse = $this->actingAs($this->owner, 'sanctum')
        ->postJson("/api/businesses/{$this->business->id}/ownership-transfer", [
            'successor_email' => $this->owner->email,
        ]);

    $selfResponse->assertStatus(422)
        ->assertJsonValidationErrors(['successor_email'])
        ->assertJsonPath('errors.successor_email.0', 'Cannot transfer ownership to yourself.');
});

test('successor can accept ownership transfer atomically promoting successor and demoting owner', function (): void {
    $plainToken = Str::random(40);
    $transfer = OwnershipTransfer::factory()->create([
        'business_id' => $this->business->id,
        'current_owner_id' => $this->owner->id,
        'successor_id' => $this->manager->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => 'pending',
        'expires_at' => now()->addHours(48),
    ]);

    $response = $this->actingAs($this->manager, 'sanctum')
        ->postJson("/api/ownership-transfers/{$plainToken}/accept");

    $response->assertNoContent();

    // Verify successor is now owner
    expect($this->manager->roleIn($this->business))->toBe('owner');

    // Verify previous owner is now manager
    expect($this->owner->roleIn($this->business))->toBe('manager');

    // Verify transfer is accepted
    $transfer->refresh();
    expect($transfer->status)->toBe('accepted');
    expect($transfer->accepted_at)->not->toBeNull();
});

test('non-successor cannot accept ownership transfer', function (): void {
    $plainToken = Str::random(40);
    OwnershipTransfer::factory()->create([
        'business_id' => $this->business->id,
        'current_owner_id' => $this->owner->id,
        'successor_id' => $this->manager->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => 'pending',
        'expires_at' => now()->addHours(48),
    ]);

    // Staff tries to accept
    $this->actingAs($this->staff, 'sanctum')
        ->postJson("/api/ownership-transfers/{$plainToken}/accept")
        ->assertForbidden();
});

test('accepting expired or invalid transfer token returns 422', function (): void {
    $plainToken = Str::random(40);
    OwnershipTransfer::factory()->create([
        'business_id' => $this->business->id,
        'current_owner_id' => $this->owner->id,
        'successor_id' => $this->manager->id,
        'token_hash' => hash('sha256', $plainToken),
        'status' => 'pending',
        'expires_at' => Carbon::now()->subHour(),
    ]);

    $this->actingAs($this->manager, 'sanctum')
        ->postJson("/api/ownership-transfers/{$plainToken}/accept")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['token'])
        ->assertJsonPath('errors.token.0', 'Invalid or expired ownership transfer.');
});
