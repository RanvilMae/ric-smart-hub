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
        Schema::table('users', function (Blueprint $table) {
            // super_admin | regional_admin | member
            $table->string('role', 20)->default('member')->after('password')->index();
            // The region a regional admin manages (null for super admin and members)
            $table->foreignId('region_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('region_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('region_id');
            $table->dropColumn(['role', 'is_active']);
        });
    }
    
};
