<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Application\ActorResolver;
use App\Modules\Medical\Application\MedicalInbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class MedicalInboxController extends Controller
{
    public function index()
    {
        return view('admin.medical-inbox', ['documents' => DB::table('medical_documents')->orderByDesc('id')->paginate(25)]);
    }

    public function review(Request $request, string $reference, ActorResolver $actors, MedicalInbox $inbox)
    {
        $data = $request->validate(['decision' => 'required|in:accepted,rejected', 'response' => 'required|string|max:3000']);
        $inbox->review($actors->resolve($request), $reference, $data['decision'], $data['response']);
        return back()->with('status', 'بررسی ثبت شد.');
    }

    public function download(Request $request, string $reference, ActorResolver $actors, MedicalInbox $inbox)
    {
        $file = $inbox->download($actors->resolve($request), $reference);
        return response($file['bytes'])->header('Content-Type', $file['mime'])->header('Content-Disposition', 'attachment; filename="document.'.($file['mime'] === 'application/pdf' ? 'pdf' : ($file['mime'] === 'image/png' ? 'png' : 'jpg')).'"');
    }
}
