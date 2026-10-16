<?php

use App\Http\Controllers\Api\AccessLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CurrenciesController;
use App\Http\Controllers\Api\CustomersController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentPdfController;
use App\Http\Controllers\Api\ExpenseCategoriesController;
use App\Http\Controllers\Api\ExpensesController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\MaterialCategoriesController;
use App\Http\Controllers\Api\MaterialsController;
use App\Http\Controllers\Api\PaymentsController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PurchasesController;
use App\Http\Controllers\Api\RepairsController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\RolesController;
use App\Http\Controllers\Api\SalesController;
use App\Http\Controllers\Api\ServicesController;
use App\Http\Controllers\Api\ServiceUnitsController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StatementsController;
use App\Http\Controllers\Api\SuppliersController;
use App\Http\Controllers\Api\TechniciansController;
use App\Http\Controllers\Api\UsersController;
use Illuminate\Support\Facades\Route;

// Platform admin API, answered only on admin.<central domain> (IdentifyTenant).
Route::prefix('admin')->group(base_path('routes/admin.php'));

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    // The signed-in user's own account. No permission: everyone may edit their
    // card and password, and see which browsers hold a session for them.
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::put('/password', [ProfileController::class, 'updatePassword']);
        Route::get('/sessions', [ProfileController::class, 'sessions']);
        Route::delete('/sessions', [ProfileController::class, 'destroyOtherSessions']);
    });

    Route::get('/features', [SettingsController::class, 'features']);
    // features.manage alone reaches only the feature switches (filtered in the controller).
    Route::middleware('permission:settings.manage,features.manage')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::put('/settings', [SettingsController::class, 'update']);
    });

    // Active currencies and their rates, for every form that takes money.
    Route::get('/currencies/options', [CurrenciesController::class, 'options']);
    Route::middleware('permission:currencies.manage')->group(function () {
        Route::get('/currencies', [CurrenciesController::class, 'index']);
        Route::post('/currencies', [CurrenciesController::class, 'store']);
        Route::put('/currencies/{currency}', [CurrenciesController::class, 'update']);
        Route::delete('/currencies/{currency}', [CurrenciesController::class, 'destroy']);
        Route::get('/currencies/{currency}/rates', [CurrenciesController::class, 'rates']);
    });

    Route::middleware('permission:users.manage')->group(function () {
        Route::get('/users', [UsersController::class, 'index']);
        Route::post('/users', [UsersController::class, 'store']);
        Route::get('/users/{user}', [UsersController::class, 'show']);
        Route::put('/users/{user}', [UsersController::class, 'update']);
        Route::delete('/users/{user}', [UsersController::class, 'destroy']);
    });

    Route::middleware('permission:users.manage')->group(function () {
        Route::get('/roles/options', [RolesController::class, 'options']);
    });

    // Roles and Permissions management. Master always passes; a delegated roles.manage
    // holder is limited by AccessControlService to roles within their own permissions.
    Route::middleware('permission:roles.manage')->group(function () {
        Route::get('/roles', [RolesController::class, 'index']);
        Route::post('/roles', [RolesController::class, 'store']);
        Route::get('/roles/{role}', [RolesController::class, 'show']);
        Route::put('/roles/{role}', [RolesController::class, 'update']);
        Route::delete('/roles/{role}', [RolesController::class, 'destroy']);
        Route::post('/roles/{role}/users', [RolesController::class, 'assignUsers']);
        Route::get('/permissions', [RolesController::class, 'permissionsIndex']);
    });

    // History of role/permission/user changes; each holder sees the part they manage.
    Route::get('/access-log', [AccessLogController::class, 'index'])
        ->middleware('permission:roles.manage,users.manage');

    Route::apiResource('/customers', CustomersController::class)
        ->parameters(['customers' => 'customer']);
    Route::apiResource('/suppliers', SuppliersController::class)
        ->parameters(['suppliers' => 'supplier']);

    Route::middleware('feature:technicians')->group(function () {
        Route::get('/technicians/options', [TechniciansController::class, 'options']);
        Route::apiResource('/technicians', TechniciansController::class)
            ->parameters(['technicians' => 'technician']);
    });

    Route::middleware('permission:statements.view')->group(function () {
        Route::get('/customers/{customer}/statement', [StatementsController::class, 'customer']);
        Route::get('/suppliers/{supplier}/statement', [StatementsController::class, 'supplier']);
        Route::get('/technicians/{technician}/statement', [StatementsController::class, 'technician']);
    });

    // Paper sizes and the shop's defaults, for labelling the print buttons.
    Route::get('/print/config', [DocumentPdfController::class, 'config']);

    // Printable documents. Each is authorised by the same policy as the record
    // it renders, so PDF access can never be wider than on-screen access.
    Route::prefix('pdf')->middleware('throttle:documents')->group(function () {
        Route::get('/sales/{sale}', [DocumentPdfController::class, 'salesInvoice']);
        Route::get('/purchases/{purchase}', [DocumentPdfController::class, 'purchaseInvoice']);
        Route::get('/payments/{payment}', [DocumentPdfController::class, 'paymentReceipt']);
        Route::get('/repairs/{repair}', [DocumentPdfController::class, 'repairReceipt']);
        Route::get('/customers/{customer}/statement', [DocumentPdfController::class, 'customerStatement']);
        Route::get('/suppliers/{supplier}/statement', [DocumentPdfController::class, 'supplierStatement']);
        Route::get('/sample', [DocumentPdfController::class, 'sample']);
    });

    Route::middleware('feature:inventory')->prefix('inventory')->group(function () {
        Route::middleware('permission:inventory.view')->group(function () {
            Route::get('/', [InventoryController::class, 'index']);
            Route::get('/adjustments', [InventoryController::class, 'adjustments']);
            Route::get('/materials/{material}', [InventoryController::class, 'show']);
        });

        Route::middleware('permission:inventory.adjust')->group(function () {
            Route::post('/adjustments', [InventoryController::class, 'store']);
            Route::post('/adjustments/{adjustment}/cancel', [InventoryController::class, 'cancel']);
        });
    });

    Route::middleware('permission:dashboard.view')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
    });

    Route::middleware(['permission:reports.view', 'feature:reports'])->prefix('reports')->group(function () {
        Route::get('/low-stock', [ReportsController::class, 'lowStock']);
        Route::get('/sales-summary', [ReportsController::class, 'salesSummary']);
        Route::get('/purchases-summary', [ReportsController::class, 'purchasesSummary']);
        Route::get('/profit-summary', [ReportsController::class, 'profitSummary']);
        Route::get('/receivables', [ReportsController::class, 'receivables']);
        Route::get('/payables', [ReportsController::class, 'payables']);
        Route::get('/stock-movements', [ReportsController::class, 'stockMovements']);
        // Technician reports: their own permission on top of reports.view, and only
        // while the technicians module is switched on.
        Route::get('/technician-summary', [ReportsController::class, 'technicianSummary'])
            ->middleware(['permission:reports.technicians', 'feature:technicians']);
        Route::get('/technician-repairs', [ReportsController::class, 'technicianRepairs'])
            ->middleware(['permission:reports.technician_repairs', 'feature:technicians']);
    });

    Route::middleware('feature:services')->group(function () {
        Route::get('/service-units/options', [ServiceUnitsController::class, 'options']);
        Route::apiResource('/service-units', ServiceUnitsController::class)
            ->parameters(['service_unit' => 'service_unit']);
        Route::apiResource('/services', ServicesController::class)
            ->parameters(['service' => 'service']);
        Route::post('/services/{service}/restore', [ServicesController::class, 'restore'])->withTrashed();
        Route::delete('/services/{service}/force', [ServicesController::class, 'forceDelete'])->withTrashed();
    });

    Route::middleware('feature:repairs')->group(function () {
        Route::get('/repairs/customer-options', [RepairsController::class, 'customerOptions']);
        Route::get('/repairs/brand-options', [RepairsController::class, 'brandOptions']);
        Route::get('/repairs/customer-account', [RepairsController::class, 'customerAccount']);
        Route::get('/repairs/{repair}/settlement', [RepairsController::class, 'settlement']);
        Route::post('/repairs/{repair}/deposit', [RepairsController::class, 'deposit']);
        Route::post('/repairs/{repair}/settle', [RepairsController::class, 'settle']);
        Route::apiResource('/repairs', RepairsController::class)
            ->parameters(['repair' => 'repair']);
    });

    Route::get('/material-categories/options', [MaterialCategoriesController::class, 'options']);
    Route::apiResource('/material-categories', MaterialCategoriesController::class)
        ->parameters(['material_category' => 'material_category']);
    Route::get('/materials/lookup', [MaterialsController::class, 'lookup']);
    Route::apiResource('/materials', MaterialsController::class)
        ->parameters(['material' => 'material']);
    Route::post('/materials/{material}/restore', [MaterialsController::class, 'restore'])->withTrashed();
    Route::delete('/materials/{material}/force', [MaterialsController::class, 'forceDelete'])->withTrashed();

    Route::middleware('feature:purchases')->group(function () {
        Route::get('/purchases/supplier-options', [PurchasesController::class, 'supplierOptions']);
        Route::get('/purchases/price-suggestions', [PurchasesController::class, 'priceSuggestions']);
        Route::apiResource('/purchases', PurchasesController::class)
            ->parameters(['purchase' => 'purchase']);
        Route::post('/purchases/{purchase}/post', [PurchasesController::class, 'post']);
        Route::post('/purchases/{purchase}/cancel', [PurchasesController::class, 'cancel']);
    });

    Route::middleware('feature:sales')->group(function () {
        Route::get('/sales/customer-options', [SalesController::class, 'customerOptions']);
        Route::apiResource('/sales', SalesController::class)
            ->parameters(['sale' => 'sale']);
        Route::post('/sales/{sale}/post', [SalesController::class, 'post']);
        Route::post('/sales/{sale}/cancel', [SalesController::class, 'cancel']);
    });

    // مصاريف الصندوق: لا مسودة لها ولا تخصيص، فليس لها إلا التسجيل والتصحيح
    // والإلغاء المُعلَّل.
    Route::middleware('feature:expenses')->group(function () {
        Route::get('/expense-categories', [ExpenseCategoriesController::class, 'index']);
        Route::post('/expense-categories', [ExpenseCategoriesController::class, 'store']);
        Route::put('/expense-categories/{expense_category}', [ExpenseCategoriesController::class, 'update']);
        Route::delete('/expense-categories/{expense_category}', [ExpenseCategoriesController::class, 'destroy']);

        Route::apiResource('/expenses', ExpensesController::class)
            ->parameters(['expenses' => 'expense'])
            ->except(['destroy']);
        Route::post('/expenses/{expense}/cancel', [ExpensesController::class, 'cancel']);
    });

    Route::get('/payments/open-invoices', [PaymentsController::class, 'openInvoices']);
    Route::apiResource('/payments', PaymentsController::class)
        ->parameters(['payment' => 'payment']);
    Route::post('/payments/{payment}/post', [PaymentsController::class, 'post']);
    Route::post('/payments/{payment}/cancel', [PaymentsController::class, 'cancel']);
});
