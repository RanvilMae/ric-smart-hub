<?php

namespace App\Http\Middleware;

use App\Models\Member;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'role' => $user?->role?->value,   // 'super_admin', 'regional_admin', 'member' or null
            ],
            'nav' => fn () => [
                // Pending registrations for the sidebar badge (admins only)
                'pendingMembers' => $user && ($user->isSuperAdmin() || $user->isRegionalAdmin())
                    ? Member::where('status', 'pending')
                        ->when($user->isRegionalAdmin(), fn ($q) => $q->where('region_id', $user->region_id))
                        ->count()
                    : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentTeam' => fn () => $user?->currentTeam ? $user->toUserTeam($user->currentTeam) : null,
            'teams' => fn () => $user?->toUserTeams(includeCurrent: true) ?? [],
        ];
    }
}