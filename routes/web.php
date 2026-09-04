<?php

use App\Http\Controllers\CoordinatorController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DriverEscortController;
use App\Http\Controllers\ExpeditionController;
use App\Http\Controllers\ExpeditionPlanningController;
use App\Http\Controllers\ExpeditionRequestController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\VendorCompanyController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login', 301);
Route::get('/sitemap.xml', function () {
    $loginUrl = rtrim(config('app.url'), '/').'/login';
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL
        .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL
        .'  <url><loc>'.e($loginUrl).'</loc><changefreq>monthly</changefreq><priority>1.0</priority></url>'.PHP_EOL
        .'</urlset>';

    return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');
Route::middleware('auth')->group(function () {
    Route::get('/push/config', [PushSubscriptionController::class, 'config'])->name('push.config');
    Route::post('/push/subscriptions', [PushSubscriptionController::class, 'store'])->name('push.subscriptions.store');
    Route::delete('/push/subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push.subscriptions.destroy');
    Route::post('/push/test', [PushSubscriptionController::class, 'test'])->name('push.test');
    Route::get('/dashboard', fn () => redirect(request()->user()->homePath()))->name('dashboard');
    Route::middleware(['role:expedition', 'permission:expedition.report'])->prefix('expedition')->group(function () {
        Route::get('/', [ExpeditionController::class, 'index'])->name('expedition.index');
        Route::post('/reports', [ExpeditionController::class, 'store'])->name('expedition.store');
        Route::get('/reverse-geocode', [ExpeditionController::class, 'reverseGeocode'])->name('expedition.reverse-geocode');
        Route::get('/reports/{report}/photos/{kind}', [ExpeditionController::class, 'photo'])->name('expedition.photo');
        Route::get('/planning', [ExpeditionPlanningController::class, 'index'])->name('expedition.planning.index');
        Route::patch('/planning/{submission}', [ExpeditionPlanningController::class, 'update'])->name('expedition.planning.update');
    });
    Route::middleware(['role:driver', 'permission:driver.escort'])->prefix('driver')->group(function () {
        Route::get('/escorts', [DriverEscortController::class, 'index'])->name('driver.escorts.index');
        Route::patch('/escorts/{report}/claim', [DriverEscortController::class, 'claim'])->name('driver.escorts.claim');
        Route::post('/escorts/{report}/close', [DriverEscortController::class, 'close'])->name('driver.escorts.close');
        Route::get('/escorts/{report}/photo', [DriverEscortController::class, 'photo'])->name('driver.escorts.photo');
        Route::get('/reverse-geocode', [DriverEscortController::class, 'reverseGeocode'])->name('driver.reverse-geocode');
    });
    Route::middleware(['role:purchasing_admin', 'permission:purchasing.requests'])->prefix('purchasing')->group(function () {
        Route::get('/requests', [ExpeditionRequestController::class, 'index'])->name('purchasing.requests.index');
        Route::post('/requests', [ExpeditionRequestController::class, 'store'])->name('purchasing.requests.store');
        Route::patch('/requests/{expeditionRequest}/procurement', [ExpeditionRequestController::class, 'updateProcurement'])->name('purchasing.requests.procurement');
        Route::get('/vendor-submissions/IJIN_LINTAS', [CoordinatorController::class, 'submissions'])->defaults('type', 'IJIN_LINTAS')->name('purchasing.vendor-submissions');
        Route::get('/vendor-submission/{submission}', [CoordinatorController::class, 'showSubmission'])->name('purchasing.vendor-submission.show');
        Route::patch('/vendor-submission/{submission}/status', [CoordinatorController::class, 'updateStatus'])->name('purchasing.vendor-submission.status');
        Route::post('/vendor-submission/{submission}/request-resubmission', [CoordinatorController::class, 'requestResubmission'])->name('purchasing.vendor-submission.request-resubmission');
        Route::patch('/vendor-document/{document}/verify', [CoordinatorController::class, 'verifyDocument'])->name('purchasing.vendor-document.verify');
    });
    Route::get('/expedition-requests/{expeditionRequest}/evidence', [ExpeditionRequestController::class, 'evidence'])->name('expedition-requests.evidence');
    Route::middleware('role:coordinator')->get('/coordinator/reports/{report}/escort-photo', [DriverEscortController::class, 'photo'])->name('coordinator.expedition.escort-photo');
    Route::middleware('role:coordinator')->get('/coordinator/reports/{report}/photos/{kind}', [ExpeditionController::class, 'photo'])->name('coordinator.expedition.photo');
    Route::middleware(['role:vendor', 'permission:vendor.submission'])->prefix('vendor')->group(function () {
        Route::get('/', [VendorController::class, 'index'])->name('vendor.index');
        Route::get('/expedition-requests', [VendorController::class, 'expeditionRequests'])->name('vendor.expedition-requests');
        Route::post('/submissions', [VendorController::class, 'store'])->name('vendor.store');
        Route::get('/submissions/{submission}', [VendorController::class, 'show'])->name('vendor.show');
        Route::get('/monitoring-sarana', [VendorController::class, 'monitoringSarana'])->name('vendor.monitoring-sarana');
    });
    Route::middleware('role:coordinator')->prefix('coordinator')->group(function () {
        Route::middleware('permission:coordinator.expedition')->group(function () {
            Route::get('/', [CoordinatorController::class, 'index'])->name('coordinator.index');
            Route::get('/export', [CoordinatorController::class, 'export'])->name('coordinator.export');
        });
        Route::middleware('permission:coordinator.requests')->group(function () {
            Route::get('/expedition-requests', [ExpeditionRequestController::class, 'index'])->name('coordinator.expedition-requests.index');
            Route::post('/expedition-requests', [ExpeditionRequestController::class, 'store'])->name('coordinator.expedition-requests.store');
            Route::patch('/expedition-requests/{expeditionRequest}/procurement', [ExpeditionRequestController::class, 'updateProcurement'])->name('coordinator.expedition-requests.procurement');
        });
        Route::middleware('permission:coordinator.planning')->group(function () {
            Route::get('/planning', [PlanningController::class, 'index'])->name('coordinator.planning');
            Route::patch('/planning/{planningItem}', [PlanningController::class, 'move'])->name('coordinator.planning.move');
            Route::post('/planning/{planningItem}/evidence', [PlanningController::class, 'evidence'])->name('coordinator.planning.evidence');
            Route::get('/planning/{planningItem}/evidence', [PlanningController::class, 'showEvidence'])->name('coordinator.planning.evidence.show');
        });
        Route::middleware('permission:coordinator.vendor')->group(function () {
            Route::get('/submissions/{type}', [CoordinatorController::class, 'submissions'])->name('coordinator.submissions');
            Route::get('/submissions/{type}/export', [CoordinatorController::class, 'exportSubmissions'])->name('coordinator.submissions.export');
            Route::get('/submission/{submission}', [CoordinatorController::class, 'showSubmission'])->name('coordinator.show');
            Route::patch('/submission/{submission}/status', [CoordinatorController::class, 'updateStatus'])->name('coordinator.status');
            Route::post('/submission/{submission}/request-resubmission', [CoordinatorController::class, 'requestResubmission'])->name('coordinator.request-resubmission');
            Route::patch('/document/{document}/verify', [CoordinatorController::class, 'verifyDocument'])->name('coordinator.document.verify');
        });
        Route::middleware('permission:vendors.manage')->group(function () {
            Route::get('/vendors', [VendorCompanyController::class, 'index'])->name('coordinator.vendors.index');
            Route::post('/vendors', [VendorCompanyController::class, 'store'])->name('coordinator.vendors.store');
            Route::patch('/vendors/{vendorCompany}', [VendorCompanyController::class, 'update'])->name('coordinator.vendors.update');
        });
        Route::middleware('permission:users.manage')->group(function () {
            Route::get('/users', [UserManagementController::class, 'index'])->name('coordinator.users.index');
            Route::post('/users', [UserManagementController::class, 'store'])->name('coordinator.users.store');
            Route::patch('/users/{managedUser}', [UserManagementController::class, 'update'])->name('coordinator.users.update');
        });
    });
    Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
    Route::get('/submissions/{submission}/documents/download-all', [DocumentController::class, 'downloadAll'])->name('documents.download-all');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
require __DIR__.'/auth.php';
