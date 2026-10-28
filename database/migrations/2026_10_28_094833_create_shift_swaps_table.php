<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shift_swaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
            $table->foreignId('shift_staff_id')->constrained('shift_staff')->cascadeOnDelete();
            $table->foreignId('offered_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['offered', 'pending_approval', 'approved', 'cancelled'])->default('offered');
            $table->string('cancellation_reason', 100)->nullable();
            $table->text('notes')->nullable();
            $table->text('manager_notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['shift_staff_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_swaps');
    }
};
