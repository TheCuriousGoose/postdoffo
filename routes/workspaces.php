<?php

use App\Http\Controllers\CollectionController;
use App\Http\Controllers\EnvironmentController;
use App\Http\Controllers\EnvironmentVariableController;
use App\Http\Controllers\RequestController;
use App\Http\Controllers\RequestCookieController;
use App\Http\Controllers\RequestFileController;
use App\Http\Controllers\RequestHistoryController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceInvitationController;
use App\Http\Controllers\WorkspaceMemberController;
use App\Http\Controllers\WorkspaceVariableController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::get('workspaces/{workspace}', [WorkspaceController::class, 'show'])->name('workspaces.show');
    Route::patch('workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::delete('workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');
    Route::patch('workspaces/{workspace}/team', [WorkspaceController::class, 'updateTeam'])->name('workspaces.update-team');

    Route::get('invitations/{token}', [WorkspaceInvitationController::class, 'accept'])->name('invitations.accept');

    Route::prefix('api')->name('api.')->group(function () {
        Route::post('workspaces/{workspace}/collections/import', [CollectionController::class, 'import'])->name('collections.import');
        Route::post('workspaces/{workspace}/collections', [CollectionController::class, 'store'])->name('collections.store');
        Route::patch('workspaces/{workspace}/collections/reorder', [CollectionController::class, 'reorder'])->name('collections.reorder');
        Route::patch('collections/{collection}', [CollectionController::class, 'update'])->name('collections.update');
        Route::get('collections/{collection}/download', [CollectionController::class, 'download'])->name('collections.download');
        Route::delete('collections/{collection}', [CollectionController::class, 'destroy'])->name('collections.destroy');

        Route::post('collections/{collection}/requests', [RequestController::class, 'store'])->name('requests.store');
        Route::post('collections/{collection}/requests/from-curl', [RequestController::class, 'fromCurl'])->name('requests.from-curl');
        Route::get('requests/{apiRequest}/curl', [RequestController::class, 'curl'])->name('requests.curl');
        Route::patch('collections/{collection}/requests/reorder', [RequestController::class, 'reorder'])->name('requests.reorder');
        Route::get('requests/{apiRequest}', [RequestController::class, 'show'])->name('requests.show');
        Route::patch('requests/{apiRequest}', [RequestController::class, 'update'])->name('requests.update');
        Route::delete('requests/{apiRequest}', [RequestController::class, 'destroy'])->name('requests.destroy');
        // Throttled together: these four are the ones that make this server fetch
        // something on a user's behalf, so they share one per-user budget.
        Route::middleware('throttle:execute-request')->group(function () {
            Route::post('requests/{apiRequest}/execute', [RequestController::class, 'execute'])->name('requests.execute');
            Route::post('requests/{apiRequest}/prepare', [RequestController::class, 'prepare'])->name('requests.prepare');
            Route::post('requests/{apiRequest}/send', [RequestController::class, 'send'])->name('requests.send');
            Route::post('requests/{apiRequest}/record', [RequestController::class, 'record'])->name('requests.record');
        });

        Route::post('requests/{apiRequest}/files', [RequestFileController::class, 'store'])->name('request-files.store');
        Route::get('request-files/{requestFile}', [RequestFileController::class, 'show'])->name('request-files.show');
        Route::delete('request-files/{requestFile}', [RequestFileController::class, 'destroy'])->name('request-files.destroy');

        Route::post('workspaces/{workspace}/environments', [EnvironmentController::class, 'store'])->name('environments.store');
        Route::patch('environments/{environment}', [EnvironmentController::class, 'update'])->name('environments.update');
        Route::post('environments/{environment}/activate', [EnvironmentController::class, 'activate'])->name('environments.activate');
        Route::delete('environments/{environment}', [EnvironmentController::class, 'destroy'])->name('environments.destroy');

        Route::post('environments/{environment}/variables', [EnvironmentVariableController::class, 'store'])->name('environment-variables.store');
        Route::patch('environment-variables/{environmentVariable}', [EnvironmentVariableController::class, 'update'])->name('environment-variables.update');
        Route::delete('environment-variables/{environmentVariable}', [EnvironmentVariableController::class, 'destroy'])->name('environment-variables.destroy');

        Route::post('workspaces/{workspace}/variables', [WorkspaceVariableController::class, 'store'])->name('workspace-variables.store');
        Route::patch('workspace-variables/{workspaceVariable}', [WorkspaceVariableController::class, 'update'])->name('workspace-variables.update');
        Route::delete('workspace-variables/{workspaceVariable}', [WorkspaceVariableController::class, 'destroy'])->name('workspace-variables.destroy');

        Route::get('workspaces/{workspace}/cookies', [RequestCookieController::class, 'index'])->name('cookies.index');
        Route::delete('workspaces/{workspace}/cookies', [RequestCookieController::class, 'clear'])->name('cookies.clear');
        Route::delete('cookies/{requestCookie}', [RequestCookieController::class, 'destroy'])->name('cookies.destroy');

        Route::get('workspaces/{workspace}/history', [RequestHistoryController::class, 'index'])->name('history.index');
        Route::get('history/{requestHistory}', [RequestHistoryController::class, 'show'])->name('history.show');
        Route::delete('history/{requestHistory}', [RequestHistoryController::class, 'destroy'])->name('history.destroy');

        Route::get('workspaces/{workspace}/members', [WorkspaceMemberController::class, 'index'])->name('members.index');
        Route::post('workspaces/{workspace}/members', [WorkspaceMemberController::class, 'store'])->name('members.store');
        Route::patch('workspaces/{workspace}/members/{member}', [WorkspaceMemberController::class, 'updateRole'])->name('members.update-role');
        Route::delete('workspaces/{workspace}/members/{member}', [WorkspaceMemberController::class, 'destroy'])->name('members.destroy');
        Route::delete('workspaces/{workspace}/invitations/{invitation}', [WorkspaceMemberController::class, 'destroyInvitation'])->name('invitations.destroy');
    });
});
