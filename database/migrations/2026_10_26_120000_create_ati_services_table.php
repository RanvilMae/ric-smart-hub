<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ati_services', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description')->nullable();
            // Services that open extra questions on the form (training / scholarship / lsa).
            // Their names are locked because the form looks for these exact names.
            $table->string('follow_up', 20)->nullable();
            $table->boolean('is_active')->default(true);   // shown on the registration form
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Start with the services from the Google Form
        $rows = [
            ['name' => 'Training', 'description' => 'Trainings conducted by ATI', 'follow_up' => 'training'],
            ['name' => 'Scholarship', 'description' => 'EdGE, EAsY Agri and other ATI scholarships', 'follow_up' => 'scholarship'],
            ['name' => 'LSA', 'description' => 'Learning Site for Agriculture', 'follow_up' => 'lsa'],
            ['name' => 'Internship', 'description' => null, 'follow_up' => null],
        ];

        foreach ($rows as $i => $row) {
            DB::table('ati_services')->insert($row + [
                'is_active' => true,
                'sort_order' => $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ati_services');
    }
};
