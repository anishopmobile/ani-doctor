<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SmsTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('notifications.manage') ?? false;
    }
    public function rules(): array
    {
        return ['event' => ['required', Rule::in(\App\Modules\Notifications\Application\NotificationOutbox::EVENTS)], 'provider_template_id' => 'required|integer|min:1|max:2147483647', 'enabled' => 'required|boolean', 'secretary_id' => 'nullable|required_if:event,secretary|integer|exists:users,id', 'reminder_minutes' => 'required|integer|min:5|max:1440', 'reviewed' => 'required|accepted', 'reason' => 'required|string|max:500'];
    }
    public function attributes(): array
    {
        return ['event' => 'رویداد', 'provider_template_id' => 'شناسه قالب', 'enabled' => 'وضعیت ارسال', 'secretary_id' => 'منشی دریافت‌کننده', 'reminder_minutes' => 'زمان یادآوری', 'reviewed' => 'تأیید بررسی قالب'];
    }
}
