<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
//
//Route::prefix('paddlepaymentgateway')->group(function() {
//    Route::get('/', 'PaddlePaymentGatewayController@index');
//});



/* frontend routes */
Route::prefix('paddlepaymentgateway')->group(function() {
    Route::post("landlord-price-plan-paddle",[\Modules\PaddlePaymentGateway\Http\Controllers\PaddlePaymentGatewayController::class,"landlordPricePlanIpn"])
        ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
        ->name("paddlepaymentgateway.landlord.price.plan.ipn");

});


/* tenant payment ipn route*/
Route::middleware([
    'web',
    \App\Http\Middleware\Tenant\InitializeTenancyByDomainCustomisedMiddleware::class,
    \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class
])->prefix('paddlepaymentgateway')->group(function () {
    Route::post("tenant-price-plan-paddle",[\Modules\PaddlePaymentGateway\Http\Controllers\PaddlePaymentGatewayController::class,"TenantSiteswayIpn"])
        ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
        ->name("paddle.tenant.price.plan.ipn");

});

/* admin panel routes landlord */
Route::group(['middleware' => ['auth:admin','adminglobalVariable', 'set_lang'],'prefix' => 'admin-home'],function () {
    Route::prefix('paddlepaymentgateway')->group(function() {
        Route::get('/settings', [\Modules\PaddlePaymentGateway\Http\Controllers\PaddlePaymentGatewayAdminPanelController::class,"settings"])->name("paddle.landlord.admin.settings");
        Route::post('/settings', [\Modules\PaddlePaymentGateway\Http\Controllers\PaddlePaymentGatewayAdminPanelController::class,"settingsUpdate"]);
    });
});


Route::group(['middleware' => [
    'web',
    \App\Http\Middleware\Tenant\InitializeTenancyByDomainCustomisedMiddleware::class,
    \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
    'auth:admin',
    'tenant_admin_glvar',
    'package_expire',
    'tenantAdminPanelMailVerify',
    'tenant_status',
    'set_lang'
],'prefix' => 'admin-home'],function () {
    Route::prefix('paddlepaymentgateway/tenant')->group(function() {
        Route::get('/settings', [\Modules\PaddlePaymentGateway\Http\Controllers\PaddlePaymentGatewayAdminPanelController::class,"settings"])->name("paddle.tenant.admin.settings");
        Route::post('/settings', [\Modules\PaddlePaymentGateway\Http\Controllers\PaddlePaymentGatewayAdminPanelController::class,"settingsUpdate"]);
    });
});

