<?php

namespace App\Modules\Medical\Application;

use App\Modules\Booking\Domain\BookingFailure;
use App\Modules\Identity\Domain\BookingActor;
use App\Modules\Bale\Application\BaleOutbox;
use App\Modules\Billing\Application\FinancialAudit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class MedicalInbox
{
    public function submit(BookingActor $actor, int $patient, UploadedFile $file, ?string $ingestionKey = null): string
    {
        $this->owns($actor, $patient);
        if ($ingestionKey && ($prior = DB::table('medical_documents')->where('ingestion_key', $ingestionKey)->first())) {
            if ((int) $prior->patient_id !== $patient || (int) $prior->submitted_by !== $actor->userId) {
                throw new BookingFailure('FORBIDDEN', 404);
            }
            return $prior->reference;
        }
        Validator::make(['document' => $file], ['document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120'])->validate();
        $path = 'medical/'.Str::uuid().'.encrypted';
        if (!Storage::disk('local')->put($path, Crypt::encryptString(base64_encode(file_get_contents($file->getRealPath()))))) {
            throw new BookingFailure('UPLOAD_FAILED', 503);
        }
        try {
            return DB::transaction(function () use ($actor, $patient, $file, $path, $ingestionKey): string {
                $this->owns($actor, $patient);
                $reference = (string) Str::uuid();
                DB::table('medical_documents')->insert(['reference' => $reference, 'ingestion_key' => $ingestionKey, 'patient_id' => $patient, 'submitted_by' => $actor->userId, 'path' => $path, 'mime' => $file->getMimeType(), 'state' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
                app(FinancialAudit::class)->record('medical.submitted', $actor->userId, 'medical_document', $reference, [], ['state' => 'pending'], 'مدرک خصوصی دریافت شد');
                return $reference;
            }, 3);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
    }

    public function review(BookingActor $actor, string $reference, string $decision, string $response): void
    {
        $this->staff($actor);
        if (!in_array($decision, ['accepted', 'rejected'], true)) {
            throw new BookingFailure('INVALID_STATE');
        }
        DB::transaction(function () use ($actor, $reference, $decision, $response): void {
            $doc = DB::table('medical_documents')->where('reference', $reference)->lockForUpdate()->first();
            if (!$doc || $doc->state !== 'pending') {
                throw new BookingFailure('INVALID_STATE');
            }
            DB::table('medical_documents')->where('id', $doc->id)->update(['state' => $decision, 'reviewed_by' => $actor->userId, 'reviewed_at' => now(), 'response' => Crypt::encryptString($response), 'updated_at' => now()]);
            app(FinancialAudit::class)->record('medical.reviewed', $actor->userId, 'medical_document', $reference, ['state' => 'pending'], ['state' => $decision], 'بررسی مدرک خصوصی');
            $link = DB::table('bale_links')->where('user_id', $doc->submitted_by)->where('active', true)->first();
            if ($link) {
                app(BaleOutbox::class)->enqueue('medical:'.$reference, $link->bale_id, 'پاسخ مطب در بخش امن مدارک شما آماده است.');
            }
        }, 3);
    }

    public function download(BookingActor $actor, string $reference): array
    {
        $this->staff($actor);
        $doc = DB::table('medical_documents')->where('reference', $reference)->first();
        abort_unless($doc, 404);
        app(FinancialAudit::class)->record('medical.viewed', $actor->userId, 'medical_document', $reference, [], [], 'مشاهده خصوصی مدرک');
        return ['bytes' => base64_decode(Crypt::decryptString(Storage::disk('local')->get($doc->path)), true), 'mime' => $doc->mime];
    }

    public function responses(BookingActor $actor, int $patient): array
    {
        $this->owns($actor, $patient);
        return DB::table('medical_documents')->where('patient_id', $patient)->where('submitted_by', $actor->userId)->orderByDesc('id')->limit(50)->get(['reference', 'state', 'response', 'reviewed_at'])->map(fn ($doc) => ['reference' => $doc->reference, 'state' => $doc->state, 'response' => $doc->response ? Crypt::decryptString($doc->response) : null])->all();
    }

    private function owns(BookingActor $actor, int $patient): void
    {
        if (!$actor->userId || !DB::table('patient_user_links')->where('user_id', $actor->userId)->where('patient_id', $patient)->exists()) {
            throw new BookingFailure('FORBIDDEN', 404);
        }
    }

    private function staff(BookingActor $actor): void
    {
        if (!$actor->userId || !$actor->can('medical.review')) {
            throw new BookingFailure('FORBIDDEN', 403);
        }
    }
}
