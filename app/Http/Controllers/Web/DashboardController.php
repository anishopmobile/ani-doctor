<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, \App\Modules\Booking\Application\ActorResolver $actors, \App\Modules\Booking\Application\AppointmentAccess $access, \App\Modules\Scheduling\Application\JalaliDate $jalali)
    {
        $guest = $request->session()->get('guest_identity');
        abort_unless($request->user() || ($guest && $guest['expires_at'] > now()->timestamp), 403);
        abort_if($request->user() && (!$request->user()->is_active || !$request->user()->mobile_verified_at), 403);
        if ($request->user()?->hasPermission('desk.view')) {
            return redirect()->route($request->user()->hasPermission('panel.admin') ? 'panel.admin' : 'panel.secretary');
        }
        $actor = $actors->resolve($request);
        return view('dashboard', ['isGuest' => !$request->user(), 'appointments' => $access->query($actor)->with('patient')->orderByDesc('start_at')->paginate(10), 'jalali' => $jalali]);
    }
}
