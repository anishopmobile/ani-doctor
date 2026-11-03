<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Bale\Application\BaleIdentity;
use App\Modules\Bale\Application\InitDataVerifier;
use App\Modules\Booking\Application\BookingService;
use App\Modules\Booking\Application\AppointmentAccess;
use App\Modules\Booking\Domain\BookingFailure;
use App\Modules\Catalog\Infrastructure\Service;
use App\Modules\Medical\Application\MedicalInbox;
use App\Modules\Patients\Application\BookingPatientService;
use App\Modules\Payments\Application\CardTransferService;
use App\Modules\Scheduling\Application\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

final class BaleController extends Controller
{
    public function index()
    {
        abort_unless(config('bale.enabled'), 404);
        return view('bale.index');
    }

    public function handle(Request $request, InitDataVerifier $verifier, BaleIdentity $identity)
    {
        $id = $verifier->verify((string) $request->header('X-Bale-Init-Data'));
        $action = $request->route('bale_action');
        $limit = $action === 'otp' ? 5 : 60;
        $key = 'bale:'.$action.':'.$id;
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            throw new BookingFailure('RATE_LIMITED', 429);
        }
        RateLimiter::hit($key, 60);
        if ($action === 'otp') {
            $data = $request->validate(['mobile' => ['required', 'regex:/\A09[0-9]{9}\z/']]);
            return $this->json($identity->requestCode($id, $data['mobile']));
        }
        if ($action === 'verify') {
            $data = $request->validate(['code' => ['required', 'regex:/\A[0-9]{6}\z/']]);
            $identity->verify($id, $data['code']);
        }
        $actor = $identity->actor($id);
        if (in_array($action, ['me', 'verify'], true)) {
            return $this->json(['patients' => app(BookingPatientService::class)->choices($actor)->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->all(),
                'services' => Service::where('is_active', true)->where('is_online', true)->get(['id', 'name', 'price_irr'])]);
        }
        if ($action === 'days') {
            $data = $request->validate(['service_id' => 'required|integer|min:1', 'date' => 'required|date_format:Y-m-d']);
            $service = Service::where('is_active', true)->where('is_online', true)->findOrFail($data['service_id']);
            $date = CarbonImmutable::parse($data['date'], 'Asia/Tehran')->startOfDay();
            if ($date->lessThan(now('Asia/Tehran')->startOfDay()) || $date->greaterThan(now('Asia/Tehran')->addDays(90))) {
                throw new BookingFailure('INVALID_STATE');
            }
            return $this->json(['days' => app(AvailabilityService::class)->days($service, $date, $date->addDays(6))]);
        }
        if ($action === 'book') {
            $data = $request->validate(['idempotency_key' => 'required|uuid', 'service_id' => 'required|integer|min:1', 'expected_price_irr' => 'required|integer|min:0',
                'start_at' => ['required', 'date', 'regex:/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(Z|[+-][0-9]{2}:[0-9]{2})\z/'],
                'patient_id' => 'nullable|integer|min:1', 'patient_name' => 'required_without:patient_id|nullable|string|max:150']);
            $data['origin_channel'] = 'bale';
            $appointment = DB::transaction(function () use ($actor, $data) {
                // Match web card begin lock order, including configuration read.
                DB::table('card_transfer_settings')->where('id', 1)->sharedLock()->first();
                $appointment = app(BookingService::class)->create($actor, $data);
                app(CardTransferService::class)->begin($actor, $appointment, $appointment->version);
                return $appointment->fresh();
            }, 3);
            $transfer = \App\Modules\Payments\Infrastructure\CardTransfer::where('appointment_id', $appointment->id)->sole();
            return $this->json(['reference' => $appointment->reference, 'card' => $transfer->card_number, 'holder' => $transfer->holder, 'amount_irr' => $transfer->amount_irr, 'deadline' => $transfer->deadline->toIso8601String()]);
        }
        if ($action === 'receipt') {
            $data = $request->validate(['reference' => 'required|uuid', 'receipt' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120']);
            $appointment = app(AppointmentAccess::class)->query($actor)->where('reference', $data['reference'])->firstOrFail();
            $transfer = app(CardTransferService::class)->submit($actor, $appointment, $request->file('receipt'));
            return $this->json(['state' => $transfer->state]);
        }
        if ($action === 'document') {
            $data = $request->validate(['patient_id' => 'required|integer|min:1', 'consent' => 'required|accepted', 'document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120']);
            return $this->json(['reference' => app(MedicalInbox::class)->submit($actor, (int) $data['patient_id'], $request->file('document'))]);
        }
        if ($action === 'responses') {
            $data = $request->validate(['patient_id' => 'required|integer|min:1']);
            return $this->json(['documents' => app(MedicalInbox::class)->responses($actor, (int) $data['patient_id'])]);
        }
        throw new BookingFailure('INVALID_STATE');
    }

    private function json(array $data)
    {
        return response()->json(['success' => true, 'data' => $data, 'error' => null, 'meta' => ['server_now' => now()->toIso8601String()]]);
    }
}
