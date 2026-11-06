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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending')->index(); // pending, approved, rejected

            // Personal information
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('suffix', 10)->nullable();
            $table->string('sex', 10);
            $table->string('civil_status', 20);
            $table->date('birth_date')->nullable();
            $table->string('education_attainment');
            $table->string('degree')->nullable();

            // Address (PSGC)
            $table->foreignId('region_id')->constrained();
            $table->foreignId('province_id')->nullable()->constrained();
            $table->foreignId('city_id')->nullable()->constrained();
            $table->foreignId('barangay_id')->nullable()->constrained();
            $table->string('district', 20)->nullable();
            $table->string('municipality_class', 30)->nullable();
            $table->string('zip_code', 10)->nullable();

            // Membership
            $table->string('member_type');
            $table->string('occupation')->nullable();
            $table->string('contact_number', 20)->nullable();
            $table->string('alternate_email')->nullable();   // "Email Address 2"
            $table->string('organization_address')->nullable();
            $table->string('ric_organization')->nullable();

            // Specialization and ATI services
            $table->string('specialization', 30)->nullable();
            $table->string('hvcdp_commodity', 40)->nullable();
            $table->json('ati_services')->nullable();
            $table->string('lsa_level', 20)->nullable();
            $table->string('lsa_type', 30)->nullable();
            $table->string('ati_scholarship')->nullable();
            $table->json('ati_trainings')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['region_id', 'status']);
            $table->index(['last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
