<?php

use App\Http\Controllers\Central\SignupController;
use Illuminate\Support\Facades\Route;

/*
| Public-site API — mounted at /api/public (routes/api.php) and reachable only
| on the bare central domain (IdentifyTenant).
*/

Route::get('/slug-check', [SignupController::class, 'checkSlug'])->middleware('throttle:slug-check');
Route::post('/register', [SignupController::class, 'register'])->middleware('throttle:signup');
