<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My account" for super admin and regional admins.
 */
class AccountController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user()->load('region:id,name,label');

        return Inertia::render('admin/Account', [
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'contact_number' => $user->contact_number,
                'position' => $user->position,
                'office' => $user->office,
                'role' => $user->role->label(),
                'region' => $user->region ? "{$user->region->name} – {$user->region->label}" : 'All regions (national)',
                'photo_url' => $user->photo_path ? Storage::disk('public')->url($user->photo_path) : null,
                'last_updated' => $user->updated_at?->diffForHumans(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'regex:/^09\d{9}$/'],
            'position' => ['nullable', 'string', 'max:255'],
            'office' => ['nullable', 'string', 'max:255'],
        ], [
            'contact_number.regex' => 'Enter an 11-digit mobile number starting with 09, e.g. 09171234567.',
        ]);

        $request->user()->forceFill($data)->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Account details updated.']);

        return back();
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'photo.max' => 'The photo must not be larger than 2 MB.',
        ]);

        $user = $request->user();
        $old = $user->photo_path;

        $user->forceFill(['photo_path' => $request->file('photo')->store('admin-photos', 'public')])->save();

        if ($old) {
            Storage::disk('public')->delete($old);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Photo updated.']);

        return back();
    }
}
