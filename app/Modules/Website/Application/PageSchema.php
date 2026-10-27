<?php

namespace App\Modules\Website\Application;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class PageSchema
{
    public const TYPES = ['hero', 'text', 'image', 'split', 'services', 'articles', 'contact', 'cta', 'features', 'faq', 'spacer'];

    public function validate(array $blocks): array
    {
        if (strlen(json_encode($blocks, JSON_THROW_ON_ERROR)) > 150000) {
            throw ValidationException::withMessages(['blocks' => 'اندازه صفحه بیش از حد مجاز است.']);
        }
        $data = Validator::make(['blocks' => $blocks], [
            'blocks' => 'required|array|min:1|max:40',
            'blocks.*' => 'array:id,type,heading,eyebrow,text,asset_id,button_label,button_url,layout,background,items,count,anchor,height',
            'blocks.*.id' => 'required|uuid|distinct',
            'blocks.*.type' => 'required|in:'.implode(',', self::TYPES),
            'blocks.*.heading' => 'nullable|string|max:180',
            'blocks.*.eyebrow' => 'nullable|string|max:180',
            'blocks.*.text' => 'nullable|string|max:4000',
            'blocks.*.asset_id' => 'nullable|integer|min:1|required_if:blocks.*.type,image',
            'blocks.*.button_label' => 'nullable|string|max:80|required_with:blocks.*.button_url',
            'blocks.*.button_url' => ['nullable', 'string', 'max:255', 'required_with:blocks.*.button_label', 'regex:~\A(?:/(?![/\\\\])[^\s\\\\]*|#[a-zA-Z][a-zA-Z0-9_-]*)\z~D'],
            'blocks.*.layout' => 'required|in:stacked,centered,split-left,split-right,grid',
            'blocks.*.background' => 'required|in:plain,blush,lilac',
            'blocks.*.items' => 'sometimes|array|max:12|required_if:blocks.*.type,features,faq',
            'blocks.*.items.*' => 'array:title,text',
            'blocks.*.items.*.title' => 'required|string|max:180',
            'blocks.*.items.*.text' => 'nullable|string|max:1000',
            'blocks.*.count' => 'sometimes|integer|min:1|max:12',
            'blocks.*.anchor' => ['nullable', 'string', 'max:40', 'distinct', 'not_in:main', 'regex:/\A[a-z][a-z0-9-]*\z/D'],
            'blocks.*.height' => 'sometimes|in:small,medium,large',
        ], ['blocks.*.type.in' => 'نوع بلوک مجاز نیست.', 'blocks.*.button_url.regex' => 'فقط لینک داخلی معتبر مجاز است.'])->validate();

        return array_values($data['blocks']);
    }
}
