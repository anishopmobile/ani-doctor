<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CardTransferRequest;
use App\Modules\Booking\Application\ActorResolver;
use App\Modules\Booking\Application\AppointmentAccess;
use App\Modules\Payments\Application\CardTransferService;
use App\Modules\Payments\Infrastructure\CardTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class CardTransferController extends Controller
{
    public function inbox()
    {
        return view('payments.card-inbox', ['transfers' => DB::table('card_transfers')->join('appointments', 'appointments.id', '=', 'card_transfers.appointment_id')
            ->whereIn('card_transfers.state', ['pending_review', 'manager_review'])->orderBy('card_transfers.id')
            ->select('appointments.reference as appointment_reference', 'appointments.service_title', 'appointments.start_at', 'card_transfers.state', 'card_transfers.amount_irr')->paginate(25)]);
    }

    public function settings(Request $request)
    {
        return view('payments.card-settings', ['settings' => DB::table('card_transfer_settings')->where('id', 1)->first()]);
    }

    public function configure(CardTransferRequest $request, ActorResolver $actors, CardTransferService $cards)
    {
        $cards->configure($actors->resolve($request), $request->validated());
        return back()->with('status', 'تنظیمات کارت ذخیره شد.');
    }

    public function mutate(CardTransferRequest $request, string $reference, ActorResolver $actors, AppointmentAccess $access, CardTransferService $cards)
    {
        $actor = $actors->resolve($request);
        $appointment = $access->query($actor)->where('reference', $reference)->firstOrFail();
        $data = $request->validated();
        $transfer = match ($request->route('card_action')) {
            'begin' => $cards->begin($actor, $appointment, (int) $data['expected_version']),
            'submit' => $cards->submit($actor, $appointment, $request->file('receipt')),
            'review' => $cards->review($actor, $appointment, $data['decision'], $data['reason']),
        };
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => ['state' => $transfer->state], 'error' => null, 'meta' => (object) []]);
        }
        return redirect()->route('appointments.show', $reference)->with('status', 'درخواست کارت‌به‌کارت ثبت شد.');
    }

    public function receipt(Request $request, string $reference, ActorResolver $actors, AppointmentAccess $access)
    {
        $actor = $actors->resolve($request);
        $appointment = $access->query($actor)->where('reference', $reference)->firstOrFail();
        abort_unless(!$actor->staff() || $actor->can('payments.card_review'), 403);
        $transfer = CardTransfer::where('appointment_id', $appointment->id)->firstOrFail();
        abort_unless($transfer->receipt_path, 404);
        return Storage::disk('local')->download($transfer->receipt_path, 'receipt.'.pathinfo($transfer->receipt_path, PATHINFO_EXTENSION));
    }
}
