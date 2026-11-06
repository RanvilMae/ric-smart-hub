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
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();             // II, IVB, CAR
            $table->string('psgc_code', 10)->unique();        // 0200000000
            $table->string('name')->unique();                 // "Region II"
            $table->string('label');                          // "Cagayan Valley"
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('provinces', function (Blueprint $table) {
            $table->id();
            $table->string('psgc_code', 10)->unique();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_independent_city')->default(false);
            $table->timestamps();
        });

        // Cities AND municipalities
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('psgc_code', 10)->unique();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 30); // Municipality, City, Highly Urbanized City...
            $table->timestamps();
        });

        Schema::create('barangays', function (Blueprint $table) {
            $table->id();
            $table->string('psgc_code', 10)->unique();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barangays');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('provinces');
        Schema::dropIfExists('regions');
    }
};
