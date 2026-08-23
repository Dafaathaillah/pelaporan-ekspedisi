<?php

use App\Http\Controllers\CoordinatorController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ExpeditionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => redirect(request()->user()->homePath()))->name('dashboard');
    Route::middleware(['role:expedition', 'permission:expedition.report'])->prefix('expedition')->group(function () {
        Route::get('/', [ExpeditionController::class, 'index'])->name('expedition.index');
        Route::post('/reports', [ExpeditionController::class, 'store'])->name('expedition.store');
    });
    Route::middleware(['role:vendor', 'permission:vendor.submission'])->prefix('vendor')->group(function () {
        Route::get('/', [VendorController::class, 'index'])->name('vendor.index');
        Route::post('/submissions', [VendorController::class, 'store'])->name('vendor.store');
        Route::get('/submissions/{submission}', [VendorController::class, 'show'])->name('vendor.show');
    });
    Route::middleware('role:coordinator')->prefix('coordinator')->group(function () {
        Route::middleware('permission:coordinator.expedition')->group(function () {
            Route::get('/', [CoordinatorController::class, 'index'])->name('coordinator.index');
            Route::get('/export', [CoordinatorController::class, 'export'])->name('coordinator.export');
        });
        Route::middleware('permission:coordinator.vendor')->group(function () {
            Route::get('/submissions/{type}', [CoordinatorController::class, 'submissions'])->name('coordinator.submissions');
            Route::get('/submissions/{type}/export', [CoordinatorController::class, 'exportSubmissions'])->name('coordinator.submissions.export');
            Route::get('/submission/{submission}', [CoordinatorController::class, 'showSubmission'])->name('coordinator.show');
            Route::patch('/submission/{submission}/status', [CoordinatorController::class, 'updateStatus'])->name('coordinator.status');
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
