<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\RegistrationStatusController;

Route::inertia('/', 'Welcome')->name('home');
// Members waiting for approval land here
Route::middleware(['auth', 'verified'])
    ->get('registration-status', RegistrationStatusController::class)
    ->name('registration.status');

// Regional admins (own region) and the super admin (all regions)
Route::middleware(['auth', 'verified', 'role:super_admin,regional_admin'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('members', [MemberController::class, 'index'])->name('members.index');
        Route::get('members/{member}', [MemberController::class, 'show'])->name('members.show');
        Route::post('members/{member}/approve', [MemberController::class, 'approve'])->name('members.approve');
        Route::post('members/{member}/reject', [MemberController::class, 'reject'])->name('members.reject');
    });
    
Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

Route::prefix('locations')->name('locations.')->group(function () {
    Route::get('regions/{region}/provinces', [LocationController::class, 'provinces'])->name('provinces');
    Route::get('provinces/{province}/cities', [LocationController::class, 'cities'])->name('cities');
    Route::get('cities/{city}/barangays', [LocationController::class, 'barangays'])->name('barangays');
});

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', 'approved', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

require __DIR__.'/settings.php';

// Super Admin only
Route::middleware(['auth', 'verified', 'role:super_admin'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('/', \App\Http\Controllers\Admin\OverviewController::class)->name('overview');
        Route::get('admins', [\App\Http\Controllers\Admin\RegionalAdminController::class, 'index'])->name('admins.index');
        Route::patch('admins/{admin}', [\App\Http\Controllers\Admin\RegionalAdminController::class, 'update'])->name('admins.update');
    });

// Approved members: My Account (details, photo, digital ID)
Route::middleware(['auth', 'verified', 'approved', 'role:member,regional_admin'])->group(function () {
    Route::get('my-account', [\App\Http\Controllers\MemberAccountController::class, 'show'])->name('account.show');
    Route::post('my-account/photo', [\App\Http\Controllers\MemberAccountController::class, 'updatePhoto'])->name('account.photo');
    Route::get('my-account/register', [\App\Http\Controllers\MemberAccountController::class, 'createRegistration'])->name('account.register');
    Route::post('my-account/register', [\App\Http\Controllers\MemberAccountController::class, 'storeRegistration'])->name('account.register.store');
});

// Admins (super admin and regional admins): My account
Route::middleware(['auth', 'verified', 'role:super_admin,regional_admin'])->group(function () {
    Route::get('admin/my-account', [\App\Http\Controllers\Admin\AccountController::class, 'show'])->name('admin.account.show');
    Route::put('admin/my-account', [\App\Http\Controllers\Admin\AccountController::class, 'update'])->name('admin.account.update');
    Route::post('admin/my-account/photo', [\App\Http\Controllers\Admin\AccountController::class, 'updatePhoto'])->name('admin.account.photo');
});

// Super Admin: regional admins are chosen from approved members
Route::middleware(['auth', 'verified', 'role:super_admin'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::post('admins/promote/{member}', [\App\Http\Controllers\Admin\RegionalAdminController::class, 'promote'])->name('admins.promote');
        Route::delete('admins/{admin}', [\App\Http\Controllers\Admin\RegionalAdminController::class, 'demote'])->name('admins.demote');
    });

// Regional admin: dashboard for their own region
Route::middleware(['auth', 'verified', 'role:regional_admin'])->group(function () {
    Route::get('admin/region', \App\Http\Controllers\Admin\RegionOverviewController::class)->name('admin.region');
});


// Super Admin: Data management → ATI services
Route::middleware(['auth', 'verified', 'role:super_admin'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('ati-services', [\App\Http\Controllers\Admin\AtiServiceController::class, 'index'])->name('ati-services.index');
        Route::post('ati-services', [\App\Http\Controllers\Admin\AtiServiceController::class, 'store'])->name('ati-services.store');
        Route::put('ati-services/{atiService}', [\App\Http\Controllers\Admin\AtiServiceController::class, 'update'])->name('ati-services.update');
        Route::patch('ati-services/{atiService}/move', [\App\Http\Controllers\Admin\AtiServiceController::class, 'move'])->name('ati-services.move');
        Route::delete('ati-services/{atiService}', [\App\Http\Controllers\Admin\AtiServiceController::class, 'destroy'])->name('ati-services.destroy');
        Route::post('ati-service-options', [\App\Http\Controllers\Admin\AtiServiceOptionController::class, 'store'])->name('ati-service-options.store');
        Route::put('ati-service-options/{option}', [\App\Http\Controllers\Admin\AtiServiceOptionController::class, 'update'])->name('ati-service-options.update');
        Route::delete('ati-service-options/{option}', [\App\Http\Controllers\Admin\AtiServiceOptionController::class, 'destroy'])->name('ati-service-options.destroy');
    });

    
// Members: upload proof of membership payment
Route::middleware(['auth', 'verified', 'approved', 'role:member,regional_admin'])->group(function () {
    Route::post('my-account/payment', [\App\Http\Controllers\MemberPaymentController::class, 'store'])->name('account.payment');
});

// Open an uploaded receipt (the member, their regional admin, or the super admin)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('payments/{payment}/proof', [\App\Http\Controllers\MemberPaymentController::class, 'proof'])->name('payments.proof');
});


// Admins: confirm membership payments (regional admins: own region; super admin: all)
Route::middleware(['auth', 'verified', 'role:super_admin,regional_admin'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('payments', [\App\Http\Controllers\Admin\PaymentController::class, 'index'])->name('payments.index');
        Route::post('payments/{payment}/confirm', [\App\Http\Controllers\Admin\PaymentController::class, 'confirm'])->name('payments.confirm');
        Route::post('payments/{payment}/reject', [\App\Http\Controllers\Admin\PaymentController::class, 'reject'])->name('payments.reject');
    });



// Regional admin: RIC organizations / clubs of their region
Route::middleware(['auth', 'verified', 'role:regional_admin'])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('organizations', [\App\Http\Controllers\Admin\OrganizationController::class, 'index'])->name('organizations.index');
        Route::post('organizations', [\App\Http\Controllers\Admin\OrganizationController::class, 'store'])->name('organizations.store');
        Route::put('organizations/{organization}', [\App\Http\Controllers\Admin\OrganizationController::class, 'update'])->name('organizations.update');
        Route::delete('organizations/{organization}', [\App\Http\Controllers\Admin\OrganizationController::class, 'destroy'])->name('organizations.destroy');
    });


// Registration form: RIC organizations of a region / city (public, like the address dropdowns)
Route::get('locations/organizations', \App\Http\Controllers\OrganizationLookupController::class)->name('locations.organizations');