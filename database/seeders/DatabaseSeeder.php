<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Location;
use App\Models\Shift;
use App\Models\ShiftStaff;
use App\Models\ShiftSwap;
use App\Models\TimeOffRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with a realistic local development dataset.
     */
    public function run(): void
    {
        $password = Hash::make('password123');

        // Create Business
        $business = Business::create([
            'name' => 'Artisan Bakery Co.',
            'slug' => 'artisan-bakery-co',
            'timezone' => 'Asia/Manila',
        ]);

        // Create Users
        $owner = User::create([
            'name' => 'Alice Founder',
            'email' => 'alice@bakery.com',
            'password' => $password,
        ]);
        $owner->businesses()->attach($business->id, ['role' => 'owner']);

        $manager = User::create([
            'name' => 'Bob Manager',
            'email' => 'bob@bakery.com',
            'password' => $password,
        ]);
        $manager->businesses()->attach($business->id, ['role' => 'manager']);

        $staff1 = User::create([
            'name' => 'Carol Baker',
            'email' => 'carol@bakery.com',
            'password' => $password,
        ]);
        $staff1->businesses()->attach($business->id, ['role' => 'staff']);

        $staff2 = User::create([
            'name' => 'Dave Pastry',
            'email' => 'dave@bakery.com',
            'password' => $password,
        ]);
        $staff2->businesses()->attach($business->id, ['role' => 'staff']);

        // Create Locations
        $loc1 = Location::create([
            'business_id' => $business->id,
            'name' => 'Downtown Roastery',
            'address' => '100 Main St, Suite 4',
        ]);

        $loc2 = Location::create([
            'business_id' => $business->id,
            'name' => 'Airport Kiosk',
            'address' => 'Terminal 2 Concourse B',
        ]);

        // Create Shifts for current week
        $today = Carbon::now();

        $shift1 = Shift::create([
            'business_id' => $business->id,
            'location_id' => $loc1->id,
            'role_label' => 'Morning Barista',
            'start_at' => $today->copy()->setTime(8, 0)->toIso8601String(),
            'end_at' => $today->copy()->setTime(16, 0)->toIso8601String(),
            'created_by' => $manager->id,
        ]);

        $shift2 = Shift::create([
            'business_id' => $business->id,
            'location_id' => $loc1->id,
            'role_label' => 'Evening Closer',
            'start_at' => $today->copy()->setTime(16, 0)->toIso8601String(),
            'end_at' => $today->copy()->addDay()->setTime(0, 0)->toIso8601String(),
            'created_by' => $manager->id,
        ]);

        $shift3 = Shift::create([
            'business_id' => $business->id,
            'location_id' => $loc2->id,
            'role_label' => 'Pastry Prep',
            'start_at' => $today->copy()->addDay()->setTime(6, 0)->toIso8601String(),
            'end_at' => $today->copy()->addDay()->setTime(14, 0)->toIso8601String(),
            'created_by' => $manager->id,
        ]);

        $shift4 = Shift::create([
            'business_id' => $business->id,
            'location_id' => $loc1->id,
            'role_label' => 'Weekend Barista',
            'start_at' => $today->copy()->addDays(2)->setTime(9, 0)->toIso8601String(),
            'end_at' => $today->copy()->addDays(2)->setTime(17, 0)->toIso8601String(),
            'created_by' => $manager->id,
        ]);

        // Assign Staff
        ShiftStaff::create([
            'shift_id' => $shift1->id,
            'user_id' => $staff1->id,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        ShiftStaff::create([
            'shift_id' => $shift2->id,
            'user_id' => $staff2->id,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        $assignment3 = ShiftStaff::create([
            'shift_id' => $shift3->id,
            'user_id' => $staff1->id,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        // Create an open shift swap offer
        ShiftSwap::create([
            'business_id' => $business->id,
            'shift_id' => $shift3->id,
            'shift_staff_id' => $assignment3->id,
            'offered_by' => $staff1->id,
            'status' => 'offered',
            'notes' => 'Looking to trade for an afternoon shift on Friday.',
        ]);

        // Create a time-off request
        TimeOffRequest::create([
            'business_id' => $business->id,
            'user_id' => $staff2->id,
            'start_date' => $today->copy()->addDays(5)->toDateString(),
            'end_date' => $today->copy()->addDays(7)->toDateString(),
            'reason' => 'Family vacation',
            'status' => 'pending',
        ]);
    }
}
