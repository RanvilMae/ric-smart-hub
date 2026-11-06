<?php

namespace App\Providers;

use App\Models\AtiService;
use App\Models\AtiServiceOption;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Replaces choice lists in config/ric.php with the lists the super admin
 * manages in the database. The registration form, My account and the
 * validation rules all read config('ric'), so they pick this up automatically.
 */
class RicOptionsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            config([
                'ric.ati_services' => AtiService::query()
                    ->where('is_active', true)
                    ->ordered()
                    ->pluck('name')
                    ->all(),
            ]);
        } catch (Throwable) {
            // Table not migrated yet: keep the defaults from config/ric.php
        }

        try {
            $options = AtiServiceOption::query()
                ->where('is_active', true)
                ->ordered()
                ->get(['list', 'name'])
                ->groupBy('list');

            foreach (array_keys(AtiServiceOption::LISTS) as $list) {
                config(["ric.$list" => ($options[$list] ?? collect())->pluck('name')->all()]);
            }
        } catch (Throwable) {
            // Table not migrated yet: keep the defaults from config/ric.php
        }
    }
}