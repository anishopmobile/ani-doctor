<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\LocalPaymentRequest;
use App\Modules\Payments\Infrastructure\LocalSimulatorGateway;

final class LocalPaymentController extends Controller
{
    public function show(string $authority, LocalSimulatorGateway $gateway)
    {
        $record = $gateway->checkout($authority);
        return view('payments.local-simulator', ['authority' => $authority, 'amountIrr' => $record['amount_irr']]);
    }

    public function submit(LocalPaymentRequest $request, string $authority, LocalSimulatorGateway $gateway)
    {
        return redirect()->away($gateway->decide($authority, $request->validated('outcome')), 303);
    }
}
