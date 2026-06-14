<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('auth:sanctum')->group(function () {
    Route::inertia('/organization', 'Organizations/Show')->name('organization');
});

require __DIR__.'/auth.php';
