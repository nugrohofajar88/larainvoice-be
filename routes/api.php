<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchSettingController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\PlateVariantController;
use App\Http\Controllers\Api\PlateTypeController;
use App\Http\Controllers\Api\SizeController;
use App\Http\Controllers\Api\MachineTypeController;
use App\Http\Controllers\Api\CuttingPriceController;
use App\Http\Controllers\Api\MachineController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SalesController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReportSalesRecapController;
use App\Http\Controllers\Api\ReportSalesKpiController;
use App\Http\Controllers\Api\ComponentCategoryController;
use App\Http\Controllers\Api\ComponentController;
use App\Http\Controllers\Api\CostTypeController;
use App\Http\Controllers\Api\MachineOrderController;
use App\Http\Controllers\Api\MobileDeviceTokenController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\UserNotificationController;
use Illuminate\Http\Request;

Route::get('/test', function () {
    return response()->json([
        'message' => 'API OK'
    ]);
});

Route::post('/login', [AuthController::class, 'login']);

// ===============================
// PROTECTED AREA
// ===============================
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
    Route::get('/notifications', [UserNotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [UserNotificationController::class, 'unreadCount']);
    Route::patch('/notifications/{id}/read', [UserNotificationController::class, 'markAsRead']);
    Route::patch('/notifications/read-all', [UserNotificationController::class, 'markAllAsRead']);
    Route::post('/device-tokens', [MobileDeviceTokenController::class, 'store']);
    Route::delete('/device-tokens', [MobileDeviceTokenController::class, 'destroy']);

    // Dashboard
    Route::get('/dashboard/summary', [DashboardController::class, 'summary'])
        ->middleware('permission:dashboard,read');
    Route::get('/reports/sales-kpi', [ReportSalesKpiController::class, 'index'])
        ->middleware('permission:report-sales-kpi,read');
    Route::get('/reports/sales-recap/plate', [ReportSalesRecapController::class, 'plate'])
        ->middleware('permission:report-plate-sales-recap,read');
    Route::get('/reports/sales-recap/cutting', [ReportSalesRecapController::class, 'cutting'])
        ->middleware('permission:report-cutting-sales-recap,read');

    // Branch Settings
    Route::get('/branch-settings/{branch_id}', [BranchSettingController::class, 'show'])
        ->middleware('permission:branch,read');
    Route::put('/branch-settings/{branch_id}', [BranchSettingController::class, 'update'])
        ->middleware('permission:branch,update');

    // Customers
    Route::get('/customers', [CustomerController::class, 'index'])
        ->middleware('permission:customer,read');
    Route::get('/customers/{id}', [CustomerController::class, 'show'])
        ->middleware('permission:customer,read');
    Route::post('/customers', [CustomerController::class, 'store'])
        ->middleware('permission:customer,create');
    Route::put('/customers/{id}', [CustomerController::class, 'update'])
        ->middleware('permission:customer,update');
    Route::delete('/customers/{id}', [CustomerController::class, 'destroy'])
        ->middleware('permission:customer,delete');

    // Plate variants
    Route::get('/plate-variants/multi', [PlateVariantController::class, 'getMulti'])
        ->middleware('permission:plate,read');
    Route::put('/plate-variants/batch', [PlateVariantController::class, 'batchUpdate'])
        ->middleware('permission:plate,update');
        
    Route::get('/plate-variants', [PlateVariantController::class, 'index'])
        ->middleware('permission:plate,read');
    Route::get('/plate-variants/{id}', [PlateVariantController::class, 'show'])
        ->middleware('permission:plate,read');
    Route::post('/plate-variants', [PlateVariantController::class, 'store'])
        ->middleware('permission:plate,create');
    Route::put('/plate-variants/{id}', [PlateVariantController::class, 'update'])
        ->middleware('permission:plate,update');
    Route::delete('/plate-variants/{id}', [PlateVariantController::class, 'destroy'])
        ->middleware('permission:plate,delete');

    // Plate types (master)
    Route::get('/plate-types', [PlateTypeController::class, 'index'])
        ->middleware('permission:plate,read');
    Route::get('/plate-types/{id}', [PlateTypeController::class, 'show'])
        ->middleware('permission:plate,read');
    Route::post('/plate-types', [PlateTypeController::class, 'store'])
        ->middleware('permission:plate,create');
    Route::put('/plate-types/{id}', [PlateTypeController::class, 'update'])
        ->middleware('permission:plate,update');
    Route::delete('/plate-types/{id}', [PlateTypeController::class, 'destroy'])
        ->middleware('permission:plate,delete');

    // Sizes (master)
    Route::get('/sizes', [SizeController::class, 'index'])
        ->middleware('permission:plate-size,read');
    Route::get('/sizes/{id}', [SizeController::class, 'show'])
        ->middleware('permission:plate-size,read');
    Route::post('/sizes', [SizeController::class, 'store'])
        ->middleware('permission:plate-size,create');
    Route::put('/sizes/{id}', [SizeController::class, 'update'])
        ->middleware('permission:plate-size,update');
    Route::delete('/sizes/{id}', [SizeController::class, 'destroy'])
        ->middleware('permission:plate-size,delete');

    // Machine types (master)
    Route::get('/machine-types', [MachineTypeController::class, 'index'])
        ->middleware('permission:machine-type,read');
    Route::get('/machine-types/{id}', [MachineTypeController::class, 'show'])
        ->middleware('permission:machine-type,read');
    Route::post('/machine-types', [MachineTypeController::class, 'store'])
        ->middleware('permission:machine-type,create');
    Route::put('/machine-types/{id}', [MachineTypeController::class, 'update'])
        ->middleware('permission:machine-type,update');
    Route::delete('/machine-types/{id}', [MachineTypeController::class, 'destroy'])
        ->middleware('permission:machine-type,delete');

    // Cutting prices
    Route::get('/cutting-prices/multi', [CuttingPriceController::class, 'getMulti'])
        ->middleware('permission:cutting-price,read');
    Route::put('/cutting-prices/batch', [CuttingPriceController::class, 'batchUpdate'])
        ->middleware('permission:cutting-price,update');
    Route::get('/cutting-prices', [CuttingPriceController::class, 'index'])
        ->middleware('permission:cutting-price,read');
    Route::get('/cutting-prices/{id}', [CuttingPriceController::class, 'show'])
        ->middleware('permission:cutting-price,read');
    Route::post('/cutting-prices', [CuttingPriceController::class, 'store'])
        ->middleware('permission:cutting-price,create');
    Route::put('/cutting-prices/{id}', [CuttingPriceController::class, 'update'])
        ->middleware('permission:cutting-price,update');
    Route::delete('/cutting-prices/{id}', [CuttingPriceController::class, 'destroy'])
        ->middleware('permission:cutting-price,delete');

    // Machines
    Route::get('/machines', [MachineController::class, 'index'])
        ->middleware('permission:machine,read');
    Route::get('/machines/{id}', [MachineController::class, 'show'])
        ->middleware('permission:machine,read');
    Route::get('/machines/{id}/files/{fileId}/download', [MachineController::class, 'downloadFile'])
        ->middleware('permission:machine,read');
    Route::delete('/machines/{id}/files/{fileId}', [MachineController::class, 'destroyFile'])
        ->middleware('permission:machine,update');
    Route::post('/machines', [MachineController::class, 'store'])
        ->middleware('permission:machine,create');
    Route::put('/machines/{id}', [MachineController::class, 'update'])
        ->middleware('permission:machine,update');
    Route::delete('/machines/{id}', [MachineController::class, 'destroy'])
        ->middleware('permission:machine,delete');

    // Branches
    Route::get('/branches', [\App\Http\Controllers\Api\BranchController::class, 'index'])
        ->middleware('permission:branch,read');
    Route::get('/branches/{id}', [\App\Http\Controllers\Api\BranchController::class, 'show'])
        ->middleware('permission:branch,read');
    Route::post('/branches', [\App\Http\Controllers\Api\BranchController::class, 'store'])
        ->middleware('permission:branch,create');
    Route::put('/branches/{id}', [\App\Http\Controllers\Api\BranchController::class, 'update'])
        ->middleware('permission:branch,update');
    Route::delete('/branches/{id}', [\App\Http\Controllers\Api\BranchController::class, 'destroy'])
        ->middleware('permission:branch,delete');

    // Branch bank accounts
    Route::get('/branch-bank-accounts', [\App\Http\Controllers\Api\BranchBankAccountController::class, 'index'])
        ->middleware('permission:branch,read');
    Route::get('/branch-bank-accounts/{id}', [\App\Http\Controllers\Api\BranchBankAccountController::class, 'show'])
        ->middleware('permission:branch,read');
    Route::post('/branch-bank-accounts', [\App\Http\Controllers\Api\BranchBankAccountController::class, 'store'])
        ->middleware('permission:branch,create');
    Route::put('/branch-bank-accounts/{id}', [\App\Http\Controllers\Api\BranchBankAccountController::class, 'update'])
        ->middleware('permission:branch,update');
    Route::delete('/branch-bank-accounts/{id}', [\App\Http\Controllers\Api\BranchBankAccountController::class, 'destroy'])
        ->middleware('permission:branch,delete');

    // Branch invoice counters
    Route::get('/branch-invoice-counters', [\App\Http\Controllers\Api\BranchInvoiceCounterController::class, 'index'])
        ->middleware('permission:branch,read');
    Route::get('/branch-invoice-counters/{id}', [\App\Http\Controllers\Api\BranchInvoiceCounterController::class, 'show'])
        ->middleware('permission:branch,read');
    Route::post('/branch-invoice-counters', [\App\Http\Controllers\Api\BranchInvoiceCounterController::class, 'store'])
        ->middleware('permission:branch,create');
    Route::put('/branch-invoice-counters/{id}', [\App\Http\Controllers\Api\BranchInvoiceCounterController::class, 'update'])
        ->middleware('permission:branch,update');
    Route::delete('/branch-invoice-counters/{id}', [\App\Http\Controllers\Api\BranchInvoiceCounterController::class, 'destroy'])
        ->middleware('permission:branch,delete');

    // Invoices
    Route::get('/invoices/master-data', [InvoiceController::class, 'masterData'])
        ->middleware('permission:invoice,create');
    Route::get('/invoices', [InvoiceController::class, 'index'])
        ->middleware('permission:invoice,read');
    Route::get('/invoices/{id}', [InvoiceController::class, 'show'])
        ->middleware('permission:invoice,read');
    Route::post('/invoices', [InvoiceController::class, 'store'])
        ->middleware('permission:invoice,create');
    Route::put('/invoices/{id}', [InvoiceController::class, 'update'])
        ->middleware('permission:invoice,update');
    Route::patch('/invoices/{id}/production-status', [InvoiceController::class, 'updateProductionStatus'])
        ->middleware('permission:production,update');
    Route::post('/invoices/{id}/item-files', [InvoiceController::class, 'uploadItemFiles'])
        ->middleware('permission:production,update');
    Route::get('/invoices/{invoiceId}/item-files/{fileId}/download', [InvoiceController::class, 'downloadItemFile'])
        ->middleware('permission:production,read');
    Route::delete('/invoices/{id}', [InvoiceController::class, 'destroy'])
        ->middleware('permission:invoice,delete');

    // Payments
    Route::get('/payments', [PaymentController::class, 'index'])
        ->middleware('permission:payment,read');
    Route::post('/payments', [PaymentController::class, 'store'])
        ->middleware('permission:payment,create');
    Route::get('/payments/{paymentId}/files/{fileId}/download', [PaymentController::class, 'downloadFile'])
        ->middleware('permission:payment,read');

    // Menus
    Route::get('/menus', [MenuController::class, 'index'])
        ->middleware('permission:role,read');
    Route::get('/menus/template', [MenuController::class, 'template'])
        ->middleware('permission:role,read');
    Route::get('/menus/flat', [MenuController::class, 'flat'])
        ->middleware('permission:role,read');
    Route::get('/menus/{id}', [MenuController::class, 'show'])
        ->middleware('permission:role,read');
    Route::post('/menus', [MenuController::class, 'store'])
        ->middleware('permission:role,create');
    Route::put('/menus/{id}', [MenuController::class, 'update'])
        ->middleware('permission:role,update');
    Route::delete('/menus/{id}', [MenuController::class, 'destroy'])
        ->middleware('permission:role,delete');

    // Roles
    Route::get('/roles/list', [RoleController::class, 'list'])
        ->middleware('permission:role,read');
    Route::get('/roles/permissions/template', [RoleController::class, 'getPermissionsTemplate'])
        ->middleware('permission:role,read');
    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware('permission:role,read');
    Route::get('/roles/{id}', [RoleController::class, 'show'])
        ->middleware('permission:role,read');
    Route::post('/roles', [RoleController::class, 'store'])
        ->middleware('permission:role,create');
    Route::put('/roles/{id}', [RoleController::class, 'update'])
        ->middleware('permission:role,update');
    Route::delete('/roles/{id}', [RoleController::class, 'destroy'])
        ->middleware('permission:role,delete');

    // Users
    Route::get('/users', [UserController::class, 'index'])
        ->middleware('permission:user,read');
    Route::get('/users/{id}', [UserController::class, 'show'])
        ->middleware('permission:user,read');
    Route::post('/users', [UserController::class, 'store'])
        ->middleware('permission:user,create');
    Route::put('/users/{id}', [UserController::class, 'update'])
        ->middleware('permission:user,update');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])
        ->middleware('permission:user,delete');

    // Sales
    Route::get('/sales', [SalesController::class, 'index'])
        ->middleware('permission:sales,read');
    Route::get('/sales/{id}', [SalesController::class, 'show'])
        ->middleware('permission:sales,read');
    Route::post('/sales', [SalesController::class, 'store'])
        ->middleware('permission:sales,create');
    Route::put('/sales/{id}', [SalesController::class, 'update'])
        ->middleware('permission:sales,update');
    Route::delete('/sales/{id}', [SalesController::class, 'destroy'])
        ->middleware('permission:sales,delete');

    // Suppliers
    Route::get('/suppliers', [SupplierController::class, 'index'])
        ->middleware('permission:supplier,read');
    Route::get('/suppliers/{id}', [SupplierController::class, 'show'])
        ->middleware('permission:supplier,read');
    Route::post('/suppliers', [SupplierController::class, 'store'])
        ->middleware('permission:supplier,create');
    Route::put('/suppliers/{id}', [SupplierController::class, 'update'])
        ->middleware('permission:supplier,update');
    Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy'])
        ->middleware('permission:supplier,delete');

    // Component categories
    Route::get('/component-categories', [ComponentCategoryController::class, 'index'])
        ->middleware('permission:component-category,read');
    Route::get('/component-categories/{id}', [ComponentCategoryController::class, 'show'])
        ->middleware('permission:component-category,read');
    Route::post('/component-categories', [ComponentCategoryController::class, 'store'])
        ->middleware('permission:component-category,create');
    Route::put('/component-categories/{id}', [ComponentCategoryController::class, 'update'])
        ->middleware('permission:component-category,update');
    Route::delete('/component-categories/{id}', [ComponentCategoryController::class, 'destroy'])
        ->middleware('permission:component-category,delete');

    // Cost types
    Route::get('/cost-types', [CostTypeController::class, 'index'])
        ->middleware('permission:cost-type,read');
    Route::get('/cost-types/{id}', [CostTypeController::class, 'show'])
        ->middleware('permission:cost-type,read');
    Route::post('/cost-types', [CostTypeController::class, 'store'])
        ->middleware('permission:cost-type,create');
    Route::put('/cost-types/{id}', [CostTypeController::class, 'update'])
        ->middleware('permission:cost-type,update');
    Route::delete('/cost-types/{id}', [CostTypeController::class, 'destroy'])
        ->middleware('permission:cost-type,delete');

    // Components
    Route::get('/components', [ComponentController::class, 'index'])
        ->middleware('permission:component,read');
    Route::get('/components/{id}', [ComponentController::class, 'show'])
        ->middleware('permission:component,read');
    Route::post('/components', [ComponentController::class, 'store'])
        ->middleware('permission:component,create');
    Route::put('/components/{id}', [ComponentController::class, 'update'])
        ->middleware('permission:component,update');
    Route::delete('/components/{id}', [ComponentController::class, 'destroy'])
        ->middleware('permission:component,delete');

    // Machine orders
    Route::get('/machine-orders', [MachineOrderController::class, 'index'])
        ->middleware('permission:machine-order,read');
    Route::get('/machine-orders/{id}', [MachineOrderController::class, 'show'])
        ->middleware('permission:machine-order,read');
    Route::post('/machine-orders', [MachineOrderController::class, 'store'])
        ->middleware('permission:machine-order,create');
    Route::put('/machine-orders/{id}', [MachineOrderController::class, 'update'])
        ->middleware('permission:machine-order,update');
    Route::delete('/machine-orders/{id}', [MachineOrderController::class, 'destroy'])
        ->middleware('permission:machine-order,delete');

});
