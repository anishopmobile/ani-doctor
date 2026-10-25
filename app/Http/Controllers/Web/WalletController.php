<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Modules\Booking\Application\ActorResolver;
use App\Modules\Wallet\Application\RefundService;
use App\Modules\Wallet\Application\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class WalletController extends Controller
{
    public function index(Request $request, ActorResolver $actors, WalletService $wallet)
    {
        $actor = $actors->resolve($request);
        return view('payments.wallet', ['summary' => $wallet->summary($actor->mobileHash), 'entries' => DB::table('wallet_entries')->where('owner_hash', $actor->mobileHash)->orderByDesc('id')->paginate(20), 'refunds' => DB::table('refunds')->where('owner_hash', $actor->mobileHash)->orderByDesc('id')->limit(50)->get()]);
    }
    public function refund(PaymentRequest $request, ActorResolver $actors, RefundService $refunds)
    {
        $data = $request->validated();
        $refund = $refunds->request($actors->resolve($request), (int) $data['amount_irr'], $data['idempotency_key'], $data['reason']);
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => ['reference' => $refund->reference, 'status' => $refund->status], 'error' => null, 'meta' => (object) []]);
        }
        return redirect()->route('wallet')->with('status', 'درخواست استرداد ثبت و مبلغ آن از اعتبار قابل مصرف جدا شد. انتقال بانکی پس از بررسی انجام می‌شود.');
    }
}
