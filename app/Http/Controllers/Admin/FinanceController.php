<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Modules\Booking\Application\ActorResolver;
use App\Modules\Billing\Application\OfflinePaymentService;
use App\Modules\Payments\Application\PaymentService;
use App\Modules\Payments\Infrastructure\PaymentAttempt;
use App\Modules\Wallet\Application\RefundService;
use Illuminate\Support\Facades\DB;

final class FinanceController extends Controller
{
    public function index()
    {
        return view('payments.management', ['settings' => DB::table('payment_settings')->where('id', 1)->first(), 'refunds' => DB::table('refunds')->orderByDesc('id')->paginate(20), 'attempts' => PaymentAttempt::whereIn('state', ['requesting', 'uncertain', 'credited'])->orderByDesc('id')->limit(50)->get()]);
    }
    public function settings(PaymentRequest $request, ActorResolver $actors, OfflinePaymentService $payments)
    {
        $data = $request->validated();
        $payments->emergencySetting($actors->resolve($request), (bool) $data['enabled'], (int) $data['minutes'], $data['reason']);
        return back()->with('status', 'سیاست اضطراری ثبت شد.');
    }
    public function decision(PaymentRequest $request, string $reference, ActorResolver $actors, RefundService $refunds)
    {
        $data = $request->validated();
        $refunds->decide($actors->resolve($request), $reference, $data['decision'], $data['reason'], $data['bank_reference'] ?? null);
        return back()->with('status', 'تصمیم استرداد ثبت شد.');
    }
    public function reconcile(string $reference, PaymentService $payments)
    {
        $payments->verify(PaymentAttempt::where('reference', $reference)->firstOrFail());
        return back()->with('status', 'استعلام انجام شد؛ نتیجه را در فهرست بررسی کنید.');
    }
}
