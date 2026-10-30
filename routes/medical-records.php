<?php

use App\Http\Controllers\Admin\ClinicalRecordController as Clinical;
use App\Http\Controllers\Secretary\MedicalRecordController as Administrative;
use App\Http\Middleware\MedicalRecordAccess;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', MedicalRecordAccess::class.':administrative', 'throttle:desk'])->prefix('management/medical-records')->group(function (): void {
    Route::get('/', [Administrative::class, 'index'])->name('emr.index');
    Route::post('/search', [Administrative::class, 'search'])->name('emr.search');
    Route::get('/new', [Administrative::class, 'create'])->name('emr.create');
    Route::post('/', [Administrative::class, 'save'])->name('emr.store');
    Route::get('/{patient}', [Administrative::class, 'show'])->whereNumber('patient')->name('emr.show');
    Route::get('/{patient}/identity', [Administrative::class, 'edit'])->whereNumber('patient')->name('emr.profile.edit');
    Route::put('/{patient}/identity', [Administrative::class, 'save'])->whereNumber('patient')->name('emr.profile.update');
    Route::post('/{patient}/documents', [Administrative::class, 'upload'])->whereNumber('patient')->name('emr.documents.store');
    Route::middleware(MedicalRecordAccess::class.':clinical')->group(function (): void {
        Route::get('/{patient}/new/{kind}', [Clinical::class, 'create'])->name('emr.records.create');
        Route::post('/{patient}/records/{kind}', [Clinical::class, 'store'])->name('emr.records.store');
        Route::get('/{patient}/records/{record}', [Clinical::class, 'edit'])->name('emr.records.edit');
        Route::put('/{patient}/records/{record}', [Clinical::class, 'update'])->name('emr.records.update');
        Route::post('/{patient}/records/{record}/sign', [Clinical::class, 'sign'])->name('emr.sign');
        Route::get('/{patient}/documents/{document}', [Clinical::class, 'download'])->name('emr.documents.download');
        Route::post('/{patient}/documents/{document}/review', [Clinical::class, 'review'])->name('emr.documents.review');
        Route::get('/{patient}/audit', [Clinical::class, 'audit'])->name('emr.audit');
        Route::get('/{patient}/print', [Clinical::class, 'print'])->middleware('throttle:reports')->name('emr.print');
    });
});
