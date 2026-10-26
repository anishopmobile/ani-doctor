<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\OtpController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [OtpController::class, 'show'])->name('login');
    Route::post('/otp/request', [OtpController::class, 'requestCode'])->middleware('throttle:otp-send')->name('otp.request');
    Route::post('/otp/verify', [OtpController::class, 'verify'])->middleware('throttle:otp-verify')->name('otp.verify');
});
Route::post('/logout', [OtpController::class, 'logout'])->name('logout');
Route::get('/dashboard', DashboardController::class)->name('dashboard');
foreach (['patient', 'secretary', 'admin'] as $panel) {
    Route::view('/'.$panel, 'panel', ['panel' => $panel])->middleware(['auth', 'can:panel.'.$panel])->name('panel.'.$panel);
}
