<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Proof of membership payment uploaded by a member.
        // The digital ID unlocks once the regional admin confirms it.
        Schema::create('member_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('method', 50);                       // GCash, bank transfer, cash...
            $table->string('reference_no', 100)->nullable();
            $table->decimal('amount', 10, 2);
            $table->date('paid_on');
            $table->string('notes')->nullable();
            $table->string('proof_path');                       // private storage, not public
            $table->string('proof_name')->nullable();           // original file name
            $table->string('status', 20)->default('pending')->index(); // pending, confirmed, rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_payments');
    }
};