<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::inertia('/login', 'Auth/Login');
Route::inertia('/organization', 'Organizations/Show');
