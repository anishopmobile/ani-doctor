<?php

namespace App\Modules\MedicalRecords\Application;

final class ClinicalSchema
{
    public static function labels(): array
    {
        return ['history' => 'سوابق پزشکی', 'allergy' => 'حساسیت‌ها', 'visit' => 'ویزیت‌ها', 'medication' => 'داروهای گزارش‌شده بیمار', 'prescription' => 'نسخه ثبت‌شده پزشک', 'result' => 'نتایج آزمایش و درخواست‌ها', 'menstrual' => 'قاعدگی و سوابق زنان', 'pregnancy' => 'دوره‌های بارداری', 'followup' => 'پیگیری بارداری', 'fertility' => 'ناباروری و دوره درمان', 'note' => 'یادداشت محرمانه پزشک'];
    }

    /** [Persian label, validation, input kind, optional choices]. Dates use the existing Jalali input. */
    public static function fields(string $kind): array
    {
        $text = fn (string $label, int $max = 4000): array => [$label, "nullable|string|max:$max", 'textarea'];
        $date = fn (string $label): array => [$label, 'nullable|date_format:Y-m-d', 'date'];
        $number = fn (string $label, int $max): array => [$label, "nullable|integer|min:0|max:$max", 'number'];
        $select = fn (string $label, array $options): array => [$label, 'required|in:'.implode(',', array_keys($options)), 'select', $options];
        $observation = $select('وضعیت (نامشخص با منفی متفاوت است)', ['unknown' => 'نامشخص / ثبت نشده', 'positive' => 'مثبت', 'negative' => 'منفی تأییدشده']);
        $source = $select('منبع', $kind === 'prescription' ? ['physician' => 'پزشک'] : ['patient' => 'گزارش بیمار', 'physician' => 'پزشک', 'document' => 'سند']);
        return match ($kind) {
            'history' => ['category' => $select('نوع سابقه', ['disease' => 'بیماری زمینه‌ای', 'surgery' => 'جراحی', 'admission' => 'بستری', 'family' => 'خانوادگی', 'other' => 'سایر']), 'observation' => $observation, 'description' => $text('شرح سابقه'), 'source' => $source, 'review_date' => $date('تاریخ بازبینی')],
            'allergy' => ['observation' => $observation, 'substance' => $text('دارو / غذا / عامل حساسیت', 250), 'reaction' => $text('واکنش'), 'severity' => $select('شدت', ['unknown' => 'نامشخص', 'mild' => 'خفیف', 'moderate' => 'متوسط', 'severe' => 'شدید']), 'source' => $source, 'review_date' => $date('تاریخ بازبینی')],
            'visit' => ['complaint' => $text('دلیل مراجعه'), 'history' => $text('شرح حال'), 'vitals' => $text('علائم حیاتی با واحد صریح و منبع اندازه‌گیری'), 'examination' => $text('معاینه'), 'provisional_diagnosis' => $text('تشخیص احتمالی'), 'confirmed_diagnosis' => $text('تشخیص قطعی ثبت‌شده پزشک'), 'procedures' => $text('اقدامات'), 'orders' => $text('درخواست آزمایش / سونوگرافی'), 'plan' => $text('برنامه و توصیه‌ها'), 'next_date' => $date('پیگیری بعدی')],
            'medication', 'prescription' => ['name' => ['نام دارو', 'required|string|max:250', 'text'], 'form_strength' => $text('شکل و قدرت دارو', 250), 'dose_unit' => $text('دوز و واحد ثبت‌شده پزشک', 250), 'route' => $text('مسیر مصرف', 150), 'frequency' => $text('دفعات مصرف', 250), 'duration' => $text('مدت', 250), 'start_date' => $date('شروع'), 'end_date' => $date('پایان'), 'instructions' => $text('دستور مصرف'), 'source' => $source],
            'result' => ['category' => $select('نوع', ['order' => 'درخواست پزشک', 'laboratory' => 'آزمایش', 'ultrasound' => 'سونوگرافی', 'pathology' => 'پاتولوژی', 'other' => 'سایر']), 'description' => $text('عنوان / نتیجه ثبت‌شده پزشک'), 'value_unit' => $text('مقدار و واحد (بدون تفسیر خودکار)'), 'method_reference' => $text('روش و محدوده مرجع آزمایشگاه'), 'sample_date' => $date('تاریخ نمونه'), 'source' => $source],
            'menstrual' => ['lmp' => $date('LMP'), 'cycle_days' => $number('طول چرخه به روز', 365), 'regularity' => $select('نظم', ['unknown' => 'نامشخص', 'regular' => 'منظم', 'irregular' => 'نامنظم']), 'bleeding_days' => $number('مدت خونریزی به روز', 90), 'bleeding_pain' => $text('شدت خونریزی، درد و علائم'), 'contraception_screening' => $text('پیشگیری، غربالگری و سوابق مرتبط'), 'source' => $source],
            'pregnancy' => ['episode_status' => $select('وضعیت دوره', ['unknown' => 'نامشخص', 'active' => 'در جریان', 'ended' => 'خاتمه‌یافته']), 'lmp' => $date('LMP در صورت معلوم‌بودن'), 'edd' => $date('EDD تأییدشده پزشک'), 'dating_source' => $select('مبنای dating', ['unknown' => 'نامشخص', 'lmp' => 'LMP', 'ultrasound' => 'سونوگرافی', 'physician' => 'اصلاح پزشک']), 'dating_date' => $date('تاریخ مرجع dating'), 'gestation_days' => $number('سن بارداری در تاریخ مرجع به روز', 315), 'fetuses' => ['تعداد جنین در صورت معلوم‌بودن', 'nullable|integer|min:1|max:12', 'number'], 'ultrasound_date' => $date('تاریخ سونوگرافی'), 'dating_decision' => $text('تصمیم پزشک و منبع تعیین تاریخ'), 'outcome_date' => $date('تاریخ پایان دوره'), 'outcome' => $text('پیامد و عوارض ثبت‌شده پزشک'), 'obstetric_history' => $text('تاریخچه باروری / G و P با تعریف و علت پزشک')],
            'followup' => ['maternal' => $text('وضعیت و عوارض مادر'), 'fetal' => $text('وضعیت و عوارض جنین / جنین‌ها'), 'findings' => $text('یافته‌ها و ارجاع اسناد'), 'plan' => $text('تصمیم و پیگیری پزشک'), 'next_date' => $date('پیگیری بعدی')],
            'fertility' => ['trying_months' => $number('مدت تلاش به ماه در صورت معلوم‌بودن', 600), 'evaluation' => $text('ارزیابی و سوابق درمان'), 'cycle' => $text('دوره درمان'), 'treatment' => $text('دارو / اقدام ثبت‌شده پزشک'), 'results' => $text('نتایج و مستندات'), 'partner_consent' => $text('اطلاعات ضروری شریک زندگی و مبنای رضایت مستقل؛ اختیاری'), 'plan' => $text('پیگیری پزشک'), 'next_date' => $date('پیگیری بعدی')],
            'note' => ['body' => ['متن محرمانه پزشک (متن ساده)', 'required|string|max:20000', 'textarea']],
            default => [],
        };
    }
}
