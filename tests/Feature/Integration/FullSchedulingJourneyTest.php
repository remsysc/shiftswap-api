<?php

use App\Models\Business;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('complete end-to-end multi-tenant scheduling journey integrates properly', function (): void {
    // -------------------------------------------------------------
    // Step 1: Business Registration (Owner Onboarding)
    // -------------------------------------------------------------
    $registerResponse = $this->postJson('/api/auth/register', [
        'name' => 'Alice Owner',
        'email' => 'alice@acme.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'business_name' => 'Acme Coffee Co',
    ]);

    $registerResponse->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email'],
            'token',
        ]);

    $ownerToken = $registerResponse->json('token');
    $ownerId = $registerResponse->json('data.id');
    $business = Business::where('name', 'Acme Coffee Co')->firstOrFail();

    expect($business->users()->where('users.id', $ownerId)->first()?->pivot->role)->toBe('owner');

    // -------------------------------------------------------------
    // Step 2: Location Management
    // -------------------------------------------------------------
    $loc1Response = $this->withHeader('Authorization', "Bearer {$ownerToken}")
        ->postJson("/api/businesses/{$business->id}/locations", [
            'name' => 'Downtown Roastery',
            'address' => '100 Main St',
        ]);

    $loc1Response->assertStatus(201);
    $roasteryId = $loc1Response->json('data.id');

    $loc2Response = $this->withHeader('Authorization', "Bearer {$ownerToken}")
        ->postJson("/api/businesses/{$business->id}/locations", [
            'name' => 'Airport Kiosk',
            'address' => 'Terminal 2',
        ]);

    $loc2Response->assertStatus(201);
    $kioskId = $loc2Response->json('data.id');

    // Verify locations listing
    $locationsList = $this->withHeader('Authorization', "Bearer {$ownerToken}")
        ->getJson("/api/businesses/{$business->id}/locations");

    $locationsList->assertOk()
        ->assertJsonCount(2, 'data');

    // -------------------------------------------------------------
    // Step 3: Staff Invitation & Promotion to Manager
    // -------------------------------------------------------------
    // Owner invites Bob
    $inviteBobResponse = $this->withHeader('Authorization', "Bearer {$ownerToken}")
        ->postJson("/api/businesses/{$business->id}/staff", [
            'email' => 'bob@acme.com',
        ]);

    $inviteBobResponse->assertStatus(202);
    $bobToken = $inviteBobResponse->json('data.token');
    expect($bobToken)->not->toBeNull();

    // Bob accepts invitation
    $bobAcceptResponse = $this->postJson("/api/invitations/{$bobToken}/accept", [
        'name' => 'Bob Manager',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $bobAcceptResponse->assertStatus(201);
    $bobBearerToken = $bobAcceptResponse->json('token');
    $bobId = $bobAcceptResponse->json('data.id');

    // Owner promotes Bob to manager
    $promoteResponse = $this->withHeader('Authorization', "Bearer {$ownerToken}")
        ->patchJson("/api/businesses/{$business->id}/staff/{$bobId}", [
            'role' => 'manager',
        ]);

    $promoteResponse->assertOk()
        ->assertJsonPath('data.role', 'manager');

    // -------------------------------------------------------------
    // Step 4: Manager Invites Staff Member
    // -------------------------------------------------------------
    // Bob (manager) invites Carol
    $inviteCarolResponse = $this->withHeader('Authorization', "Bearer {$bobBearerToken}")
        ->postJson("/api/businesses/{$business->id}/staff", [
            'email' => 'carol@acme.com',
        ]);

    $inviteCarolResponse->assertStatus(202);
    $carolInviteToken = $inviteCarolResponse->json('data.token');

    // Carol accepts invitation
    $carolAcceptResponse = $this->postJson("/api/invitations/{$carolInviteToken}/accept", [
        'name' => 'Carol Staff',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $carolAcceptResponse->assertStatus(201);
    $carolBearerToken = $carolAcceptResponse->json('token');
    $carolId = $carolAcceptResponse->json('data.id');

    // Verify staff list has 3 members
    $staffListResponse = $this->withHeader('Authorization', "Bearer {$bobBearerToken}")
        ->getJson("/api/businesses/{$business->id}/staff");

    $staffListResponse->assertOk()
        ->assertJsonCount(3, 'data');

    // -------------------------------------------------------------
    // Step 5: Shift Creation & Conflict Detection
    // -------------------------------------------------------------
    // Bob creates Shift 1: tomorrow 08:00 - 16:00 at Roastery
    $shift1Response = $this->withHeader('Authorization', "Bearer {$bobBearerToken}")
        ->postJson("/api/businesses/{$business->id}/shifts", [
            'location_id' => $roasteryId,
            'role_label' => 'Barista',
            'start_at' => Carbon::parse('2026-11-15 08:00:00')->toIso8601String(),
            'end_at' => Carbon::parse('2026-11-15 16:00:00')->toIso8601String(),
        ]);

    $shift1Response->assertStatus(201);
    $shift1Id = $shift1Response->json('data.id');

    // Bob creates Shift 2: tomorrow 16:00 - 00:00 (Boundary equality with Shift 1)
    $shift2Response = $this->withHeader('Authorization', "Bearer {$bobBearerToken}")
        ->postJson("/api/businesses/{$business->id}/shifts", [
            'location_id' => $roasteryId,
            'role_label' => 'Closer',
            'start_at' => Carbon::parse('2026-11-15 16:00:00')->toIso8601String(),
            'end_at' => Carbon::parse('2026-11-16 00:00:00')->toIso8601String(),
        ]);

    $shift2Response->assertStatus(201);
    $shift2Id = $shift2Response->json('data.id');

    // Bob creates Shift 3: tomorrow 12:00 - 20:00 (Overlaps Shift 1 and Shift 2)
    $shift3Response = $this->withHeader('Authorization', "Bearer {$bobBearerToken}")
        ->postJson("/api/businesses/{$business->id}/shifts", [
            'location_id' => $kioskId,
            'role_label' => 'Floater',
            'start_at' => Carbon::parse('2026-11-15 12:00:00')->toIso8601String(),
            'end_at' => Carbon::parse('2026-11-15 20:00:00')->toIso8601String(),
        ]);

    $shift3Response->assertStatus(201);
    $shift3Id = $shift3Response->json('data.id');

    // Assign Carol to Shift 1 (08:00 - 16:00)
    $assign1 = $this->withHeader('Authorization', "Bearer {$bobBearerToken}")
        ->postJson("/api/businesses/{$business->id}/shifts/{$shift1Id}/staff", [
            'user_id' => $carolId,
        ]);

    $assign1->assertStatus(201)
        ->assertJsonPath('data.status', 'assigned');

    // Try assigning Carol to Shift 3 (12:00 - 20:00) -> Conflicts with Shift 1!
    $conflictAssign = $this->withHeader('Authorization', "Bearer {$bobBearerToken}")
        ->postJson("/api/businesses/{$business->id}/shifts/{$shift3Id}/staff", [
            'user_id' => $carolId,
        ]);

    $conflictAssign->assertStatus(422)
        ->assertJsonValidationErrors(['user_id'])
        ->assertJsonPath('errors.user_id.0', 'This staff member has a conflicting shift or approved time-off.');

    // Assign Carol to Shift 2 (16:00 - 00:00) -> Boundary equality, succeeds!
    $assign2 = $this->withHeader('Authorization', "Bearer {$bobBearerToken}")
        ->postJson("/api/businesses/{$business->id}/shifts/{$shift2Id}/staff", [
            'user_id' => $carolId,
        ]);

    $assign2->assertStatus(201)
        ->assertJsonPath('data.status', 'assigned');

    // Carol views shifts for tomorrow
    $carolShiftList = $this->withHeader('Authorization', "Bearer {$carolBearerToken}")
        ->getJson("/api/businesses/{$business->id}/shifts?start_date=2026-11-15&end_date=2026-11-15");

    $carolShiftList->assertOk()
        ->assertJsonCount(3, 'data');

    // -------------------------------------------------------------
    // Step 6: Authorization Matrix & Cross-Tenant Boundaries
    // -------------------------------------------------------------
    app('auth')->forgetGuards();

    // Staff (Carol) cannot invite staff
    $this->withHeader('Authorization', "Bearer {$carolBearerToken}")
        ->postJson("/api/businesses/{$business->id}/staff", ['email' => 'hacker@acme.com'])
        ->assertForbidden();

    // Staff (Carol) cannot create shifts
    $this->withHeader('Authorization', "Bearer {$carolBearerToken}")
        ->postJson("/api/businesses/{$business->id}/shifts", [
            'start_at' => Carbon::parse('2026-11-15 08:00:00')->toIso8601String(),
            'end_at' => Carbon::parse('2026-11-15 16:00:00')->toIso8601String(),
        ])
        ->assertForbidden();

    // Separate tenant (Zeta Corp)
    app('auth')->forgetGuards();

    $zetaRegister = $this->postJson('/api/auth/register', [
        'name' => 'Zach Zeta',
        'email' => 'zach@zeta.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'business_name' => 'Zeta Corp',
    ]);
    $zetaToken = $zetaRegister->json('token');

    // Zeta cannot access Acme shifts
    $this->withHeader('Authorization', "Bearer {$zetaToken}")
        ->getJson("/api/businesses/{$business->id}/shifts?start_date=2026-11-15&end_date=2026-11-15")
        ->assertForbidden();

    // Zeta cannot access Acme staff
    $this->withHeader('Authorization', "Bearer {$zetaToken}")
        ->getJson("/api/businesses/{$business->id}/staff")
        ->assertForbidden();

    // Zeta cannot access Acme locations
    $this->withHeader('Authorization', "Bearer {$zetaToken}")
        ->getJson("/api/businesses/{$business->id}/locations")
        ->assertForbidden();

    // -------------------------------------------------------------
    // Step 7: Assignment Removal & Soft Delete Behavior
    // -------------------------------------------------------------
    app('auth')->forgetGuards();

    // Bob removes Carol from Shift 2
    $removeCarol = $this->withHeader('Authorization', "Bearer {$bobBearerToken}")
        ->deleteJson("/api/businesses/{$business->id}/shifts/{$shift2Id}/staff/{$carolId}");

    $removeCarol->assertNoContent();

    // Bob deletes Shift 1 (soft delete)
    $deleteShift1 = $this->withHeader('Authorization', "Bearer {$bobBearerToken}")
        ->deleteJson("/api/businesses/{$business->id}/shifts/{$shift1Id}");

    $deleteShift1->assertNoContent();
    $this->assertSoftDeleted('shifts', ['id' => $shift1Id]);

    // Now Carol has NO active conflicting shift from 08:00 - 16:00.
    // Assigning Carol to Shift 3 (12:00 - 20:00) now succeeds!
    $assignAfterDelete = $this->withHeader('Authorization', "Bearer {$bobBearerToken}")
        ->postJson("/api/businesses/{$business->id}/shifts/{$shift3Id}/staff", [
            'user_id' => $carolId,
        ]);

    $assignAfterDelete->assertStatus(201)
        ->assertJsonPath('data.status', 'assigned');

    // -------------------------------------------------------------
    // Step 8: Logout & Token Revocation
    // -------------------------------------------------------------
    $logoutResponse = $this->withHeader('Authorization', "Bearer {$bobBearerToken}")
        ->postJson('/api/auth/logout');

    $logoutResponse->assertNoContent();

    app('auth')->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$bobBearerToken}")
        ->getJson('/api/auth/user')
        ->assertUnauthorized();
});
