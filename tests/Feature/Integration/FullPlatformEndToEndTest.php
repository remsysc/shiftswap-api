<?php

use App\Models\Business;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('full platform end-to-end user lifecycle across all sprints integrates seamlessly', function (): void {
    // -------------------------------------------------------------
    // Step 1: Owner Registration & Location Creation
    // -------------------------------------------------------------
    $registerResp = $this->postJson('/api/auth/register', [
        'name' => 'Alice Founder',
        'email' => 'alice@bakery.com',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
        'business_name' => 'Artisan Bakery',
    ]);

    $registerResp->assertStatus(201);
    $aliceToken = $registerResp->json('token');
    $aliceId = $registerResp->json('data.id');
    $business = Business::where('name', 'Artisan Bakery')->firstOrFail();

    expect($business->users()->where('users.id', $aliceId)->first()?->pivot->role)->toBe('owner');

    // Create Main Bakery location
    $locResp = $this->withHeader('Authorization', "Bearer {$aliceToken}")
        ->postJson("/api/businesses/{$business->id}/locations", [
            'name' => 'Main Kitchen',
            'address' => '42 Flour St',
        ]);
    $locResp->assertStatus(201);
    $kitchenLocId = $locResp->json('data.id');

    // -------------------------------------------------------------
    // Step 2: Staff Invitations (Manager Bob, Staff Carol & Dave)
    // -------------------------------------------------------------
    // Invite Bob
    $bobInvite = $this->withHeader('Authorization', "Bearer {$aliceToken}")
        ->postJson("/api/businesses/{$business->id}/staff", ['email' => 'bob@bakery.com']);
    $bobInvite->assertStatus(202);
    $bobToken = $bobInvite->json('data.token');

    // Bob accepts and registers
    $bobAccept = $this->postJson("/api/invitations/{$bobToken}/accept", [
        'name' => 'Bob Manager',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
    ]);
    $bobAccept->assertStatus(201);
    $bobBearer = $bobAccept->json('token');
    $bobId = $bobAccept->json('data.id');

    // Alice promotes Bob to manager
    $this->withHeader('Authorization', "Bearer {$aliceToken}")
        ->patchJson("/api/businesses/{$business->id}/staff/{$bobId}", ['role' => 'manager'])
        ->assertOk()
        ->assertJsonPath('data.role', 'manager');

    // Bob (manager) invites Carol
    $carolInvite = $this->withHeader('Authorization', "Bearer {$bobBearer}")
        ->postJson("/api/businesses/{$business->id}/staff", ['email' => 'carol@bakery.com']);
    $carolInvite->assertStatus(202);
    $carolToken = $carolInvite->json('data.token');

    $carolAccept = $this->postJson("/api/invitations/{$carolToken}/accept", [
        'name' => 'Carol Baker',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
    ]);
    $carolAccept->assertStatus(201);
    $carolBearer = $carolAccept->json('token');
    $carolId = $carolAccept->json('data.id');

    // Bob invites Dave
    $daveInvite = $this->withHeader('Authorization', "Bearer {$bobBearer}")
        ->postJson("/api/businesses/{$business->id}/staff", ['email' => 'dave@bakery.com']);
    $daveInvite->assertStatus(202);
    $daveToken = $daveInvite->json('data.token');

    $daveAccept = $this->postJson("/api/invitations/{$daveToken}/accept", [
        'name' => 'Dave Pastry',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
    ]);
    $daveAccept->assertStatus(201);
    $daveBearer = $daveAccept->json('token');
    $daveId = $daveAccept->json('data.id');

    // -------------------------------------------------------------
    // Step 3: Shift Creation & Time-Off Request Workflow
    // -------------------------------------------------------------
    // Bob creates Shift A (in 2 days: 06:00 - 14:00)
    $shiftA = $this->withHeader('Authorization', "Bearer {$bobBearer}")
        ->postJson("/api/businesses/{$business->id}/shifts", [
            'location_id' => $kitchenLocId,
            'role_label' => 'Bread Baker',
            'start_at' => Carbon::now()->addDays(2)->setTime(6, 0)->toIso8601String(),
            'end_at' => Carbon::now()->addDays(2)->setTime(14, 0)->toIso8601String(),
        ]);
    $shiftA->assertStatus(201);
    $shiftAId = $shiftA->json('data.id');

    // Bob creates Shift B (in 3 days: 06:00 - 14:00)
    $shiftB = $this->withHeader('Authorization', "Bearer {$bobBearer}")
        ->postJson("/api/businesses/{$business->id}/shifts", [
            'location_id' => $kitchenLocId,
            'role_label' => 'Pastry Chef',
            'start_at' => Carbon::now()->addDays(3)->setTime(6, 0)->toIso8601String(),
            'end_at' => Carbon::now()->addDays(3)->setTime(14, 0)->toIso8601String(),
        ]);
    $shiftB->assertStatus(201);
    $shiftBId = $shiftB->json('data.id');

    // Assign Carol to Shift A
    $this->withHeader('Authorization', "Bearer {$bobBearer}")
        ->postJson("/api/businesses/{$business->id}/shifts/{$shiftAId}/staff", ['user_id' => $carolId])
        ->assertStatus(201);

    // Carol requests Time-Off for in 4 days (start: day 4, end: day 5)
    app('auth')->forgetGuards();
    $timeOffResp = $this->withHeader('Authorization', "Bearer {$carolBearer}")
        ->postJson("/api/businesses/{$business->id}/time-off-requests", [
            'start_date' => Carbon::now()->addDays(4)->toDateString(),
            'end_date' => Carbon::now()->addDays(5)->toDateString(),
            'reason' => 'Family event',
        ]);
    $timeOffResp->assertStatus(201)
        ->assertJsonPath('data.status', 'pending');
    $timeOffId = $timeOffResp->json('data.id');

    // Bob inspects the time-off request (verifies conflicts array is empty)
    app('auth')->forgetGuards();
    $this->withHeader('Authorization', "Bearer {$bobBearer}")
        ->getJson("/api/businesses/{$business->id}/time-off-requests/{$timeOffId}")
        ->assertOk()
        ->assertJsonPath('conflicts', []);

    // Bob approves Carol's time-off
    $this->withHeader('Authorization', "Bearer {$bobBearer}")
        ->postJson("/api/businesses/{$business->id}/time-off-requests/{$timeOffId}/approve", [
            'manager_notes' => 'Approved! Enjoy your family event.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    // Create Shift C during Carol's approved time-off (Day 4: 08:00 - 16:00)
    $shiftC = $this->withHeader('Authorization', "Bearer {$bobBearer}")
        ->postJson("/api/businesses/{$business->id}/shifts", [
            'location_id' => $kitchenLocId,
            'role_label' => 'Oven Operator',
            'start_at' => Carbon::now()->addDays(4)->setTime(8, 0)->toIso8601String(),
            'end_at' => Carbon::now()->addDays(4)->setTime(16, 0)->toIso8601String(),
        ]);
    $shiftC->assertStatus(201);
    $shiftCId = $shiftC->json('data.id');

    // Attempting to assign Carol to Shift C must fail because of approved time-off
    $conflictAssign = $this->withHeader('Authorization', "Bearer {$bobBearer}")
        ->postJson("/api/businesses/{$business->id}/shifts/{$shiftCId}/staff", ['user_id' => $carolId]);
    $conflictAssign->assertStatus(422)
        ->assertJsonValidationErrors(['user_id']);

    // -------------------------------------------------------------
    // Step 4: Shift Swap Marketplace & Single-Requester Lock
    // -------------------------------------------------------------
    // Carol offers Shift A for swap
    app('auth')->forgetGuards();
    $offerResp = $this->withHeader('Authorization', "Bearer {$carolBearer}")
        ->postJson("/api/businesses/{$business->id}/shift-swaps", [
            'shift_id' => $shiftAId,
        ]);
    $offerResp->assertStatus(201)
        ->assertJsonPath('data.status', 'offered');
    $swapId = $offerResp->json('data.id');

    // Dave requests the swap -> lock acquired (status becomes pending_approval)
    app('auth')->forgetGuards();
    $daveRequest = $this->withHeader('Authorization', "Bearer {$daveBearer}")
        ->postJson("/api/businesses/{$business->id}/shift-swaps/{$swapId}/request");
    $daveRequest->assertOk()
        ->assertJsonPath('data.status', 'pending_approval')
        ->assertJsonPath('data.requested_by.id', $daveId);

    // Another attempt to take the same swap receives 422 (single-requester lock)
    app('auth')->forgetGuards();
    $bobAttempt = $this->withHeader('Authorization', "Bearer {$bobBearer}")
        ->postJson("/api/businesses/{$business->id}/shift-swaps/{$swapId}/request");
    $bobAttempt->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    // Manager Bob approves the swap
    app('auth')->forgetGuards();
    $approveSwap = $this->withHeader('Authorization', "Bearer {$bobBearer}")
        ->postJson("/api/businesses/{$business->id}/shift-swaps/{$swapId}/approve");
    $approveSwap->assertOk()
        ->assertJsonPath('data.status', 'approved');

    // Verify shift assignment transferred: Carol is removed, Dave is assigned
    $shiftCheck = Shift::with('assignedStaff')->findOrFail($shiftAId);
    expect($shiftCheck->assignedStaff->pluck('user_id')->all())->toBe([$daveId]);

    // -------------------------------------------------------------
    // Step 5: Notifications In-App Feed
    // -------------------------------------------------------------
    app('auth')->forgetGuards();
    $daveNotifs = $this->withHeader('Authorization', "Bearer {$daveBearer}")
        ->getJson("/api/businesses/{$business->id}/notifications");
    $daveNotifs->assertOk()
        ->assertJsonCount(2, 'data');

    // Dave marks all notifications as read
    $markAllResp = $this->withHeader('Authorization', "Bearer {$daveBearer}")
        ->postJson("/api/businesses/{$business->id}/notifications/read-all");
    $markAllResp->assertNoContent();

    // -------------------------------------------------------------
    // Step 6: Dashboards
    // -------------------------------------------------------------
    // Bob (manager) views 7-day dashboard
    app('auth')->forgetGuards();
    $bobDashboard = $this->withHeader('Authorization', "Bearer {$bobBearer}")
        ->getJson("/api/businesses/{$business->id}/dashboard");
    $bobDashboard->assertOk()
        ->assertJsonStructure([
            'data' => [
                'upcoming_shifts',
                'pending_swaps_count',
                'pending_time_off_count',
            ],
        ]);

    // Carol views staff dashboard
    app('auth')->forgetGuards();
    $carolDashboard = $this->withHeader('Authorization', "Bearer {$carolBearer}")
        ->getJson("/api/businesses/{$business->id}/dashboard");
    $carolDashboard->assertOk()
        ->assertJsonStructure([
            'data' => [
                'upcoming_shifts',
                'pending_time_off_requests',
                'active_swaps',
            ],
        ]);

    // -------------------------------------------------------------
    // Step 7: Ownership Transfer (Alice -> Bob)
    // -------------------------------------------------------------
    // Alice initiates transfer to Bob
    app('auth')->forgetGuards();
    $transferInit = $this->withHeader('Authorization', "Bearer {$aliceToken}")
        ->postJson("/api/businesses/{$business->id}/ownership-transfer", [
            'successor_email' => 'bob@bakery.com',
        ]);
    $transferInit->assertStatus(202);
    $transferToken = $transferInit->json('data.token');
    expect($transferToken)->not->toBeNull();

    // Bob accepts the transfer
    app('auth')->forgetGuards();
    $transferAccept = $this->withHeader('Authorization', "Bearer {$bobBearer}")
        ->postJson("/api/ownership-transfers/{$transferToken}/accept");
    $transferAccept->assertNoContent();

    // Assert atomic role change: Bob is now owner, Alice is demoted to manager
    $business->refresh();
    expect($business->users()->where('users.id', $bobId)->first()?->pivot->role)->toBe('owner');
    expect($business->users()->where('users.id', $aliceId)->first()?->pivot->role)->toBe('manager');

    // -------------------------------------------------------------
    // Step 8: Multi-Tenant Isolation & Logout
    // -------------------------------------------------------------
    // Create separate tenant (Omega Roastery)
    app('auth')->forgetGuards();
    $omegaReg = $this->postJson('/api/auth/register', [
        'name' => 'Oliver Omega',
        'email' => 'oliver@omega.com',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
        'business_name' => 'Omega Roastery',
    ]);
    $omegaToken = $omegaReg->json('token');

    // Oliver cannot access Bakery's dashboard, notifications, swaps, or time-off
    $this->withHeader('Authorization', "Bearer {$omegaToken}")
        ->getJson("/api/businesses/{$business->id}/dashboard")
        ->assertForbidden();

    $this->withHeader('Authorization', "Bearer {$omegaToken}")
        ->getJson("/api/businesses/{$business->id}/shift-swaps")
        ->assertForbidden();

    $this->withHeader('Authorization', "Bearer {$omegaToken}")
        ->getJson("/api/businesses/{$business->id}/time-off-requests")
        ->assertForbidden();

    $this->withHeader('Authorization', "Bearer {$omegaToken}")
        ->getJson("/api/businesses/{$business->id}/notifications")
        ->assertForbidden();

    // Bob logs out successfully
    app('auth')->forgetGuards();
    $this->withHeader('Authorization', "Bearer {$bobBearer}")
        ->postJson('/api/auth/logout')
        ->assertNoContent();

    app('auth')->forgetGuards();
    $this->withHeader('Authorization', "Bearer {$bobBearer}")
        ->getJson('/api/auth/user')
        ->assertUnauthorized();
});
