<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiscountPreviewRequest;
use App\Modules\Billing\Application\DiscountService;
use App\Modules\Booking\Application\ActorResolver;
use App\Modules\Catalog\Infrastructure\Service;
use Illuminate\Support\Facades\DB;

final class DiscountPreviewController extends Controller
{
    public function __invoke(DiscountPreviewRequest $request, ActorResolver $actors, DiscountService $discounts)
    {
        $data = $request->validated();
        $service = Service::where('is_active', true)->when(!$actors->resolve($request)->staff(), fn ($q) => $q->where('is_online', true))->findOrFail($data['service_id']);
        $quote = DB::transaction(fn () => $discounts->quote($data['discount_code'], $service->id, 0, $service->price_irr));
        return response()->json(['success' => true, 'data' => ['base_irr' => $quote['base'], 'discount_irr' => $quote['discount'], 'net_irr' => $quote['net']], 'error' => null, 'meta' => ['estimate_only' => true]]);
    }
}
