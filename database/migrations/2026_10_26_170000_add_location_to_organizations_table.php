<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Address from the PSGC dropdowns (region comes from the regional admin).
        // "address" now holds only the street / sitio / purok, so it becomes optional.
        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignId('province_id')->nullable()->after('region_id')->constrained();
            $table->foreignId('city_id')->nullable()->after('province_id')->constrained();
            $table->foreignId('barangay_id')->nullable()->after('city_id')->constrained();
            $table->string('address')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('barangay_id');
            $table->dropConstrainedForeignId('city_id');
            $table->dropConstrainedForeignId('province_id');
        });
    }
};