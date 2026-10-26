<?php

namespace App\Modules\Booking\Application;

use App\Modules\Identity\Domain\BookingActor;
use App\Modules\Booking\Domain\BookingFailure;
use Illuminate\Http\Request;

final class ActorResolver
{
    public function resolve(Request $request): BookingActor
    {
        $user = $request->user();
        if ($user) {
            if (!$user->is_active || !$user->mobile_verified_at) {
                throw new BookingFailure('FORBIDDEN', 403);
            }
            $permissions = array_values(array_filter(['appointments.manage','appointments.override_capacity','appointments.override_window','booking.settings', 'payments.settings', 'payments.collect', 'refunds.manage', 'payments.review'], fn ($permission) => $user->hasPermission($permission)));
            return new BookingActor($user->id, $user->mobile, $permissions, (string)config('app.key'));
        }
        $guest = $request->session()->get('guest_identity');
        if (!$guest || $guest['expires_at'] <= now()->timestamp) {
            throw new BookingFailure('VERIFICATION_REQUIRED', 401);
        }
        return new BookingActor(null, $guest['mobile'], [], (string)config('app.key'));
    }
}
