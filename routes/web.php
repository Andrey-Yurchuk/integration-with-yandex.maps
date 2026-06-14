<?php

use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/organization', [OrganizationController::class, 'show'])->name('organization');
    Route::post('/organization', [OrganizationController::class, 'store'])->name('organization.store');
    Route::get('/organization/reviews', [ReviewController::class, 'index'])->name('organization.reviews');
    Route::get('/organization/sync-status', [OrganizationController::class, 'syncStatus'])->name('organization.sync-status');
});

require __DIR__.'/auth.php';
