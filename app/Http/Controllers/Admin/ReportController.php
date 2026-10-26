<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportRequest;
use App\Modules\Reporting\Application\ReportExporter;
use App\Modules\Reporting\Application\ReportService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final class ReportController extends Controller
{
    public function index(ReportRequest $request, ReportService $reports, ReportExporter $exporter)
    {
        $filters = $request->filters();
        $exportFilters = array_intersect_key($filters, array_flip(['kind', 'from', 'to', 'sort', 'direction', 'service_id', 'operator_id', 'status', 'channel', 'payment_status', 'attendance_status']));
        $format = $filters['format'] ?? 'html';
        $report = $reports->build($request->user(), $filters, $format);
        if ($format !== 'html') {
            $binary = $format === 'pdf' ? $exporter->pdf($report) : $exporter->xlsx($report);
            return response($binary, 200, ['Content-Type' => $format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition' => 'attachment; filename="clinic-report-'.Str::uuid().'.'.$format.'"']);
        }
        $sections = array_map(function ($section) use ($request, $exportFilters) {
            $pageName = 'page_'.substr(hash('sha256', $section['title']), 0, 6);
            $page = max(1, min(40, (int) $request->input($pageName, 1)));
            return [...$section, 'rows' => (new LengthAwarePaginator(array_slice($section['rows'], ($page - 1) * 25, 25), count($section['rows']), 25, $page, ['path' => $request->url(), 'pageName' => $pageName]))->appends($exportFilters)];
        }, $report['sections']);
        return view('reports.index', ['report' => [...$report, 'sections' => $sections], 'filters' => $filters, 'exportFilters' => $exportFilters, 'services' => \App\Modules\Catalog\Infrastructure\Service::orderBy('name')->get(), 'operators' => \App\Modules\Identity\Infrastructure\User::whereHas('roles', fn ($q) => $q->whereIn('name', ['secretary', 'admin']))->get()]);
    }
}
