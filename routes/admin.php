<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->name('users.update-role');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::delete('workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');
});
