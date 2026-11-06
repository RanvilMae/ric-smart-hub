<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RIC organizations / clubs, managed by the regional admin of each region
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained();
            $table->string('name');
            $table->string('address');
            $table->text('description')->nullable();
            $table->json('specializations')->nullable();    // free-form list, e.g. ["Food processing", "Vegetables"]
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['region_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};