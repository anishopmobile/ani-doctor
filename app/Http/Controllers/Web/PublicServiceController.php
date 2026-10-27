<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Infrastructure\Service;
use Illuminate\View\View;

final class PublicServiceController extends Controller
{
    public function index(): View
    {
        return view('website.services.index', [
            'services' => Service::query()
                ->where('is_active', true)
                ->where('is_online', true)
                ->orderBy('name')
                ->paginate(12, ['id', 'name', 'duration_minutes', 'price_irr']),
        ]);
    }

    public function show(int $service): View
    {
        $item = Service::query()
            ->where('is_active', true)
            ->where('is_online', true)
            ->findOrFail($service, ['id', 'name', 'duration_minutes', 'price_irr']);

        return view('website.services.show', ['service' => $item]);
    }
}
