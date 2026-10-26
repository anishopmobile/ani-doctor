<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $guest = $request->session()->get('guest_identity');
        abort_unless($request->user() || ($guest && $guest['expires_at'] > now()->timestamp), 403);
        abort_if($request->user() && (!$request->user()->is_active || !$request->user()->mobile_verified_at), 403);
        return view('dashboard', ['isGuest' => !$request->user()]);
    }
}
