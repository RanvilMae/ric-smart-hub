<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

// Give an RIC member ID to every member who doesn't have one yet (oldest first)
Artisan::command('ric:assign-member-codes', function () {
    $count = 0;

    App\Models\Member::withTrashed()
        ->whereNull('member_code')
        ->orderBy('id')
        ->chunkById(500, function ($members) use (&$count) {
            foreach ($members as $member) {
                $member->member_code = App\Support\MemberCode::next((int) $member->region_id, (int) $member->created_at->year);
                $member->saveQuietly();
                $count++;
            }
        });

    $this->info("Assigned {$count} member IDs.");
})->purpose('Give every member without an ID an RIC member ID');
