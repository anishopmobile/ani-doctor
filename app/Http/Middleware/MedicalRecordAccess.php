<?php

namespace App\Http\Middleware;

use App\Modules\MedicalRecords\Application\MedicalAudit;
use App\Policies\MedicalRecordPolicy;
use Closure;
use Illuminate\Http\Request;

final class MedicalRecordAccess
{
    public function handle(Request $request, Closure $next, string $level = 'administrative')
    {
        $user = $request->user();
        if (!$user || !app(MedicalRecordPolicy::class)->{$level}($user)) {
            if ($user) {
                app(MedicalAudit::class)->record($user->id, 'access', null, null, 'denied');
            }
            abort(403);
        }
        return $next($request);
    }
}
