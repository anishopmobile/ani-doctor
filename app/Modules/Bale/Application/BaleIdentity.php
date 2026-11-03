<?php

namespace App\Modules\Bale\Application;

use App\Modules\Bale\Infrastructure\BaleClient;
use App\Modules\Identity\Application\AuthService;
use App\Modules\Identity\Application\LocalOtpPreview;
use App\Modules\Identity\Infrastructure\User;
use App\Modules\Identity\Domain\BookingActor;
use App\Modules\Booking\Domain\BookingFailure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class BaleIdentity
{
    private function auth(): AuthService
    {
        return new AuthService(app(BaleClient::class), new LocalOtpPreview());
    }

    public function requestCode(string $id, string $mobile): array
    {
        $result = $this->auth()->requestCode($mobile, 'login', false);
        Cache::put('bale-otp:'.$id, ['mobile' => $mobile, 'id' => $result['id']], now()->addMinutes(5));
        unset($result['preview_code']);
        return $result;
    }

    public function verify(string $id, string $code): void
    {
        $pending = Cache::get('bale-otp:'.$id);
        if (!$pending) {
            throw new BookingFailure('VERIFICATION_REQUIRED', 401);
        }
        $user = $this->auth()->verify($pending['mobile'], $pending['id'], 'login', $code);
        if (!$user || !$user->hasPermission('panel.patient') || $user->hasPermission('appointments.manage')) {
            throw new BookingFailure('FORBIDDEN', 403);
        }
        DB::transaction(function () use ($id, $user): void {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = DB::table('bale_links')->where('bale_id', $id)->lockForUpdate()->first();
            if (($existing && (int) $existing->user_id !== $user->id) || DB::table('bale_links')->where('user_id', $user->id)->where('bale_id', '!=', $id)->exists()) {
                throw new BookingFailure('FORBIDDEN', 403);
            }
            DB::table('bale_links')->updateOrInsert(['bale_id' => $id], ['user_id' => $user->id, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }, 3);
        Cache::forget('bale-otp:'.$id);
    }

    public function actor(string $id): BookingActor
    {
        $link = DB::table('bale_links')->where('bale_id', $id)->where('active', true)->first();
        $user = $link ? User::find($link->user_id) : null;
        if (!$user || !$user->hasPermission('panel.patient') || $user->hasPermission('appointments.manage')) {
            throw new BookingFailure('VERIFICATION_REQUIRED', 401);
        }
        // Linking a staff account must never turn the messaging channel into a staff session.
        return new BookingActor($user->id, $user->mobile, [], (string) config('app.key'));
    }
}
