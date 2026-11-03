<?php

use Illuminate\Support\Facades\Route;

// Stateless signed requests: never use session/cookie identity for the messaging channel.
foreach (['me', 'otp', 'verify', 'days', 'book', 'receipt', 'document', 'responses'] as $action) {
    Route::post('/bale/'.$action, [\App\Http\Controllers\Web\BaleController::class, 'handle'])
        ->defaults('bale_action', $action)->middleware('throttle:60,1')->name('bale.'.$action);
}
