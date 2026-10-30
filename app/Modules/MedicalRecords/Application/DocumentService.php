<?php

namespace App\Modules\MedicalRecords\Application;

use App\Modules\Identity\Infrastructure\User;
use App\Modules\MedicalRecords\Infrastructure\MedicalDocument;
use App\Modules\Operations\Infrastructure\PrivatePaths;
use App\Modules\Patients\Infrastructure\Patient;
use App\Modules\Scheduling\Application\JalaliDate;
use App\Policies\MedicalRecordPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DocumentService
{
    public function upload(User $actor, Patient $patient, UploadedFile $file, array $data): MedicalDocument
    {
        abort_unless(app(MedicalRecordPolicy::class)->administrative($actor), 403);
        $mime = $file->getMimeType();
        if (!in_array($mime, config('medical-records.mime_types'), true) || $file->getSize() > config('medical-records.max_file_kb') * 1024) {
            throw ValidationException::withMessages(['file' => 'نوع یا حجم فایل معتبر نیست.']);
        }
        $bytes = file_get_contents($file->getRealPath());
        $extensions = ['application/pdf' => ['pdf'], 'image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png']];
        $signature = match ($mime) {
            'application/pdf' => str_starts_with($bytes, '%PDF-') && str_contains(substr($bytes, -2048), '%%EOF'),
            'image/jpeg' => str_starts_with($bytes, "\xff\xd8\xff"),
            'image/png' => str_starts_with($bytes, "\x89PNG\r\n\x1a\n"),
            default => false,
        };
        if (!$signature || !in_array(strtolower($file->getClientOriginalExtension()), $extensions[$mime] ?? [], true)) {
            throw ValidationException::withMessages(['file' => 'پسوند و امضای فایل با نوع واقعی سازگار نیست.']);
        }
        if ($mime !== 'application/pdf') {
            $size = @getimagesizefromstring($bytes);
            if (!$size || $size[0] * $size[1] > 20000000) {
                throw ValidationException::withMessages(['file' => 'ابعاد تصویر بیش از حد مجاز یا تصویر خراب است.']);
            }
            $image = @imagecreatefromstring($bytes);
            if (!$image) {
                throw ValidationException::withMessages(['file' => 'تصویر قابل پردازش نیست.']);
            }
            imagedestroy($image);
            // Validate decodability without recompressing clinical evidence. The encrypted
            // original is attachment-only; no untrusted image or PDF is rendered inline.
        }
        $clinical = app(MedicalRecordPolicy::class)->clinical($actor);
        $metadata = $clinical ? array_intersect_key($data, array_flip(['title', 'category', 'source', 'description'])) : [];
        // Secretary uploads intentionally discard clinical titles, filenames and linkage input.
        if ($clinical && !empty($data['document_jalali'])) {
            try {
                $metadata['document_date'] = app(JalaliDate::class)->parse(\App\Modules\Identity\Domain\MobileNumber::normalize($data['document_jalali']))->format('Y-m-d');
            } catch (\InvalidArgumentException) {
                throw ValidationException::withMessages(['document_jalali' => 'تاریخ شمسی معتبر وارد کنید.']);
            }
        }
        $root = app(PrivatePaths::class)->directory(storage_path('app/private/emr'));
        return DB::transaction(function () use ($actor, $patient, $data, $bytes, $mime, $metadata, $clinical, $root): MedicalDocument {
            Patient::whereKey($patient->id)->lockForUpdate()->firstOrFail();
            app(ProfileService::class)->ensureFileNumber($patient);
            if ($existing = MedicalDocument::where('request_key', $data['request_key'])->first()) {
                abort_unless($existing->patient_id === $patient->id && hash_equals($existing->sha256, hash('sha256', $bytes)), 409);
                return $existing;
            }
            $visit = $clinical ? ($data['visit_id'] ?? null) : null;
            $episode = $clinical ? ($data['episode_id'] ?? null) : null;
            app(ClinicalService::class)->link($patient, $visit, 'visit');
            app(ClinicalService::class)->link($patient, $episode, 'pregnancy');
            $name = (string) Str::uuid().'.enc';
            $path = $root.'/'.$name;
            $encrypted = Crypt::encryptString($bytes);
            if (file_put_contents($path, $encrypted, LOCK_EX) !== strlen($encrypted)) {
                throw new \RuntimeException('EMR_DOCUMENT_WRITE_FAILED');
            }
            chmod($path, 0600);
            try {
                $document = MedicalDocument::create(['patient_id' => $patient->id, 'visit_id' => $visit, 'episode_id' => $episode, 'request_key' => $data['request_key'], 'metadata' => $metadata, 'path' => $name, 'mime' => $mime, 'sha256' => hash('sha256', $bytes), 'bytes' => strlen($bytes), 'created_by' => $actor->id, 'status' => 'pending']);
                app(MedicalAudit::class)->record($actor->id, 'document.uploaded', $patient->id, $document->id);
                return $document;
            } catch (\Throwable $error) {
                unlink($path);
                throw $error;
            }
        });
    }

    public function bytes(User $actor, Patient $patient, MedicalDocument $document): string
    {
        abort_unless(app(MedicalRecordPolicy::class)->clinical($actor), 403);
        abort_unless($document->patient_id === $patient->id, 404);
        abort_unless(preg_match('/\A[0-9a-f-]{36}\.enc\z/', $document->path), 404);
        $root = app(PrivatePaths::class)->directory(storage_path('app/private/emr'));
        $path = $root.'/'.$document->path;
        abort_unless(is_file($path) && !is_link($path), 404);
        $bytes = Crypt::decryptString(file_get_contents($path));
        abort_unless(hash_equals($document->sha256, hash('sha256', $bytes)), 503);
        app(MedicalAudit::class)->record($actor->id, 'document.downloaded', $patient->id, $document->id);
        return $bytes;
    }
}
