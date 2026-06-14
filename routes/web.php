<?php

use App\Http\Controllers\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/organization', [OrganizationController::class, 'show'])->name('organization');
    Route::post('/organization', [OrganizationController::class, 'store'])->name('organization.store');
});

require __DIR__.'/auth.php';
