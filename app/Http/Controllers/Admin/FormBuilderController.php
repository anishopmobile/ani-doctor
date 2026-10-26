<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\FormPublishRequest;
use App\Modules\DynamicForms\Application\PrivateFormService;
use Illuminate\Support\Facades\DB;

final class FormBuilderController extends Controller
{
    public function index()
    {
        return view('forms.builder-index', ['forms' => DB::table('forms')->orderByDesc('id')->paginate(20)]);
    }
    public function edit(?int $form = null)
    {
        $definition = $form ? DB::table('forms')->find($form) : null;
        abort_if($form && !$definition, 404);
        $version = $definition ? DB::table('form_versions')->where('form_id', $form)->where('number', $definition->published_version)->first() : null;
        return view('forms.builder', ['definition' => $definition, 'fields' => $version ? json_decode($version->schema, true) : [], 'services' => \App\Modules\Catalog\Infrastructure\Service::orderBy('name')->get()]);
    }
    public function save(FormPublishRequest $request, PrivateFormService $forms, ?int $form = null)
    {
        $id = $forms->publish($request->user(), $form, $request->validated());
        return redirect()->route('forms.edit', $id)->with('status', 'نسخه جدید منتشر شد؛ ساختار و پاسخ نسخه‌های قبلی حفظ شدند.');
    }
}
