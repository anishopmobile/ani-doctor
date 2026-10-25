<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrivateFormRequest;
use App\Modules\Booking\Application\ActorResolver;
use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\DynamicForms\Application\PrivateFormService;
use Illuminate\Http\Request;

final class PrivateFormController extends Controller
{
    public function __construct(private readonly ActorResolver $actors, private readonly PrivateFormService $forms)
    {
    }
    public function index(Request $request, string $reference)
    {
        $appointment = Appointment::where('reference', $reference)->firstOrFail();
        $actor = $this->actors->resolve($request);
        $this->forms->authorize($actor, $appointment);
        return view('forms.index', ['appointment' => $appointment, 'forms' => $this->forms->available($appointment)]);
    }
    public function show(Request $request, string $reference, int $form)
    {
        $appointment = Appointment::where('reference', $reference)->firstOrFail();
        $actor = $this->actors->resolve($request);
        $context = $this->forms->read($actor, $appointment, $form);
        return view('forms.answer', ['appointment' => $appointment, 'canWrite' => (!$actor->staff() || $actor->can('forms.answers.write')) && $this->forms->assigned($context['form'], $appointment), ...$context]);
    }
    public function store(PrivateFormRequest $request, string $reference, int $form)
    {
        $appointment = Appointment::where('reference', $reference)->firstOrFail();
        $this->forms->submit($this->actors->resolve($request), $appointment, $form, $request->validated());
        return redirect()->route('private-forms.show', [$reference, $form])->with('status', 'پاسخ خصوصی ثبت شد؛ نسخه قبلی پاسخ حفظ می‌شود.');
    }
}
