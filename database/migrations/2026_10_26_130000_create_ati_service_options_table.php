<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The choices of the follow-up questions under an ATI service:
        //   ati_trainings    → Training:    "Which ATI trainings have you availed?"
        //   ati_scholarships → Scholarship: "Which ATI scholarship have you availed?"
        //   lsa_levels       → LSA:         "Which level are you in?"
        //   lsa_types        → LSA:         "Which type of LSA do you belong to?"
        Schema::create('ati_service_options', function (Blueprint $table) {
            $table->id();
            $table->string('list', 30)->index();
            $table->string('name');
            $table->boolean('is_active')->default(true);   // shown on the registration form
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['list', 'name']);
        });

        // Start with the choices from the Google Form (config/ric.php)
        foreach (['ati_trainings', 'ati_scholarships', 'lsa_levels', 'lsa_types'] as $list) {
            foreach (array_values(config("ric.$list", [])) as $i => $name) {
                DB::table('ati_service_options')->insert([
                    'list' => $list,
                    'name' => $name,
                    'is_active' => true,
                    'sort_order' => $i + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ati_service_options');
    }
};