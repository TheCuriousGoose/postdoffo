<?php

use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamInvitationController;
use App\Http\Controllers\TeamMemberController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('teams', [TeamController::class, 'index'])->name('teams.index');
    Route::post('teams', [TeamController::class, 'store'])->name('teams.store');
    Route::get('teams/{team}', [TeamController::class, 'show'])->name('teams.show');
    Route::patch('teams/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::delete('teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');

    Route::get('team-invitations/{token}', [TeamInvitationController::class, 'accept'])->name('team-invitations.accept');

    Route::prefix('api')->name('api.')->group(function () {
        Route::get('teams/{team}/members', [TeamMemberController::class, 'index'])->name('team-members.index');
        Route::post('teams/{team}/members', [TeamMemberController::class, 'store'])->name('team-members.store');
        Route::patch('teams/{team}/members/{member}', [TeamMemberController::class, 'updateRole'])->name('team-members.update-role');
        Route::delete('teams/{team}/members/{member}', [TeamMemberController::class, 'destroy'])->name('team-members.destroy');
        Route::delete('teams/{team}/invitations/{invitation}', [TeamMemberController::class, 'destroyInvitation'])->name('team-invitations.destroy');
    });
});
