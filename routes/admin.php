<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\TenantsController;
use Illuminate\Support\Facades\Route;

/*
| Platform admin API — mounted at /api/admin (routes/api.php) and reachable
| only on admin.<central domain> (IdentifyTenant).
*/

Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:admin')->group(function () {
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::get('/me', [AdminAuthController::class, 'me']);
    Route::put('/password', [AdminAuthController::class, 'updatePassword']);

    Route::get('/meta', [TenantsController::class, 'meta']);
    Route::get('/tenants', [TenantsController::class, 'index']);
    Route::post('/tenants', [TenantsController::class, 'store']);
    Route::get('/tenants/{tenant}', [TenantsController::class, 'show']);
    Route::patch('/tenants/{tenant}', [TenantsController::class, 'update']);
    Route::delete('/tenants/{tenant}', [TenantsController::class, 'destroy']);
    Route::post('/tenants/{tenant}/reset-password', [TenantsController::class, 'resetPassword']);
    Route::post('/tenants/{tenant}/certificate', [TenantsController::class, 'issueCertificate']);
    Route::post('/tenants/{tenant}/migrate', [TenantsController::class, 'migrate']);
});
