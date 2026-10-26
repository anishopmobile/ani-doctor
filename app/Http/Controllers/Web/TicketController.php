<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Application\ActorResolver;
use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Reporting\Application\ReportExporter;
use App\Modules\Reporting\Application\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class TicketController extends Controller
{
    public function __invoke(Request $request, string $reference, ActorResolver $actors, TicketService $tickets, ReportExporter $exporter)
    {
        $appointment = Appointment::where('reference', $reference)->with('patient')->firstOrFail();
        $pdf = $request->routeIs('tickets.pdf');
        $report = $tickets->build($actors->resolve($request), $appointment, $pdf ? 'pdf' : 'html');
        if ($pdf) {
            return response($exporter->pdf($report), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="appointment-ticket-'.Str::uuid().'.pdf"']);
        }
        return view('reports.document', ['report' => $report, 'pdf' => false]);
    }
}
