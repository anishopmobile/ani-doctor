<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Modules\Booking\Application\ActorResolver;
use App\Modules\Booking\Application\AppointmentAccess;
use App\Modules\Billing\Application\OfflinePaymentService;
use App\Modules\Payments\Application\PaymentService;
use Illuminate\Http\Request;

final class PaymentController extends Controller
{
    public function initiate(PaymentRequest $request, string $reference, ActorResolver $actors, AppointmentAccess $access, PaymentService $payments)
    {
        $actor = $actors->resolve($request);
        $appointment = $access->query($actor)->where('reference', $reference)->firstOrFail();
        $attempt = $payments->initiate($actor, $appointment, $request->validated());
        $redirect = $payments->redirect($attempt);
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => ['reference' => $attempt->reference, 'state' => $attempt->state, 'redirect_url' => $redirect], 'error' => null, 'meta' => (object) []]);
        }
        return $redirect ? redirect()->away($redirect, 303) : redirect()->route('appointments.show', $reference)->with('status', $attempt->state === 'settled' ? 'تسویه کامل انجام شد؛ وضعیت نوبت را بررسی کنید.' : 'پرداخت در حال بررسی است؛ از پرداخت مجدد خودداری کنید.');
    }
    public function callback(Request $request, string $reference, PaymentService $payments)
    {
        $data = $request->validate(['Authority' => ['required', 'string', 'regex:/^[AS][a-zA-Z0-9]{35}$/D'], 'Status' => 'required|in:OK,NOK']);
        $payments->callback($reference, $data['Authority'], $data['Status']);
        // Callback is public. Do not disclose appointment, patient, amount or result to a bearer URL.
        return response()->view('payments.callback');
    }
    public function offline(PaymentRequest $request, string $reference, ActorResolver $actors, AppointmentAccess $access, OfflinePaymentService $payments)
    {
        $actor = $actors->resolve($request);
        $appointment = $access->query($actor)->where('reference', $reference)->firstOrFail();
        $data = $request->validated();
        if ($request->route('finance_action') === 'emergency') {
            $payments->confirmEmergency($actor, $appointment, (int) $data['expected_version'], $data['reason']);
        } elseif ($request->route('finance_action') === 'collect_hold') {
            $payments->confirmCollectedHold($actor, $appointment, (int) $data['amount_irr'], $data['receipt'], $data['reason'], $data['method']);
        } else {
            $payments->collect($actor, $appointment, (int) $data['amount_irr'], $data['receipt'], $data['reason'], $data['method']);
        }
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => ['reference' => $reference], 'error' => null, 'meta' => (object) []]);
        }
        return redirect()->route('appointments.show', $reference)->with('status', 'وضعیت مالی ثبت شد.');
    }
}
