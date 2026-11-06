<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RIC member ID, e.g. RIC-II-2026-000-001
        Schema::table('members', function (Blueprint $table) {
            $table->string('member_code', 30)->nullable()->unique()->after('id');
        });

        // Last number used per region per year (keeps IDs unique even if two people register at once)
        Schema::create('member_code_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->unique(['region_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_code_sequences');

        Schema::table('members', function (Blueprint $table) {
            $table->dropUnique(['member_code']);
            $table->dropColumn('member_code');
        });
    }
};
