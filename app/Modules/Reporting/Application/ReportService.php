<?php

namespace App\Modules\Reporting\Application;

use App\Modules\Booking\Infrastructure\Appointment;
use App\Modules\Identity\Infrastructure\User;
use App\Modules\Scheduling\Application\JalaliDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReportService
{
    public const STATUS = ['pending_payment' => 'رزرو موقت', 'confirmed' => 'قطعی', 'expired' => 'منقضی', 'cancelled' => 'لغوشده', 'completed' => 'انجام‌شده', 'no_show' => 'عدم مراجعه'];
    public const CHANNEL = ['guest' => 'مهمان', 'user' => 'کاربر', 'secretary' => 'منشی', 'phone' => 'تلفنی', 'urgent' => 'فوری'];
    public function build(User $actor, array $filters, string $format = 'html'): array
    {
        abort_unless($actor->hasPermission($filters['kind'] === 'finance' ? 'reports.finance' : 'reports.appointments'), 403);
        return DB::transaction(function () use ($actor, $filters, $format): array {
            $report = $filters['kind'] === 'finance' ? $this->finance($filters) : $this->appointments($filters);
            if (DB::table('payment_attempts')->where('provider', 'local_simulator')->exists()) {
                $report['basis'] = 'هشدار: این دیتابیس شامل پرداخت شبیه‌سازی لوکال است؛ مبالغ و تطبیق شامل داده ساختگی هستند و سند دریافت وجه واقعی نیستند. '.$report['basis'];
            }
            $report += ['from' => $filters['from'], 'to' => $filters['to'], 'generated_at' => now()->toIso8601String(), 'generated_label' => $this->date(now()).' به وقت تهران', 'kind' => $filters['kind']];
            $count = array_sum(array_map(fn ($section) => count($section['rows']), $report['sections']));
            $this->limit($count, $format === 'pdf' ? 250 : 1000);
            $this->audit($actor->id, hash_hmac('sha256', 'user:'.$actor->id, (string) config('app.key')), $filters['kind'], $format, array_intersect_key($filters, array_flip(['from', 'to', 'kind', 'sort', 'direction', 'service_id', 'operator_id', 'status', 'channel', 'payment_status', 'attendance_status'])), $count);
            return $report;
        });
    }
    private function appointments(array $filters): array
    {
        $query = Appointment::query()->with('patient')->where('local_date', '>=', $filters['start_date'])->where('local_date', '<', $filters['end_date']);
        foreach (['service_id', 'channel', 'payment_status', 'attendance_status'] as $key) {
            if (!empty($filters[$key])) {
                $query->where($key, $filters[$key]);
            }
        }
        if (!empty($filters['operator_id'])) {
            $query->where('created_by', $filters['operator_id']);
        }
        if (!empty($filters['status'])) {
            $status = $filters['status'];
            if ($status === 'expired') {
                $query->where(fn ($q) => $q->where('status', 'expired')->orWhere(fn ($h) => $h->where('status', 'pending_payment')->where('hold_expires_at', '<=', now())));
            } elseif ($status === 'pending_payment') {
                $query->where('status', $status)->where('hold_expires_at', '>', now());
            } else {
                $query->where('status', $status);
            }
        }
        $items = $query->orderBy($filters['sort'] === 'amount' ? 'price_irr' : 'start_at', $filters['direction'])->orderBy('id')->limit(1001)->get();
        $this->limit($items->count());
        $operators = User::whereIn('id', $items->pluck('created_by')->filter()->unique())->get()->keyBy('id');
        $rows = $items->map(fn ($a) => [$a->reference, $this->date($a->start_at), $a->patient->name, $a->service_title, self::STATUS[$a->effectiveStatus()].' / '.self::CHANNEL[$a->channel].($a->is_override ? ' / خارج ظرفیت' : '').' / '.['scheduled' => 'در انتظار', 'arrived' => 'مراجعه', 'completed' => 'انجام', 'no_show' => 'غیبت'][$a->attendance_status], (int) $a->price_irr, (int) $a->balance_due_irr, $this->operator($operators->get($a->created_by))])->all();
        $summary = ['تعداد نوبت' => $items->count(), 'تخفیف ثبت‌شده (ریال)' => (int) $items->sum('discount_irr'), 'تعرفه خدمات انجام‌شده (ریال)' => (int) $items->where('status', 'completed')->sum('price_irr'), 'بدهی نوبت قطعی/انجام/غیبت (ریال)' => (int) $items->whereIn('status', ['confirmed', 'completed', 'no_show'])->sum('balance_due_irr'), 'نوبت فوری' => $items->where('channel', 'urgent')->count(), 'عدم مراجعه' => $items->where('attendance_status', 'no_show')->count()];
        return ['title' => 'گزارش نوبت‌ها', 'basis' => 'بازه بر اساس روز نوبت به وقت تهران؛ وضعیت‌ها جاری‌اند. تعرفه انجام‌شده معادل دریافت نقدی نیست.', 'summary' => $summary, 'sections' => [['title' => 'نوبت‌ها', 'headers' => ['کد پیگیری', 'روز و ساعت شمسی', 'بیمار', 'خدمت', 'وضعیت / روش / حضور', 'مبلغ نهایی ریال', 'مانده ریال', 'ثبت‌کننده'], 'rows' => $rows]]];
    }
    private function finance(array $filters): array
    {
        $period = fn ($query, string $column) => $query->where($column, '>=', $filters['start'])->where($column, '<', $filters['end']);
        $payments = $period(DB::table('payments')->leftJoin('users', 'users.id', '=', 'payments.actor_id')->join('appointments', 'appointments.id', '=', 'payments.appointment_id'), 'payments.created_at')->select('payments.*', 'users.display_name', 'appointments.reference as appointment_reference')->orderBy($filters['sort'] === 'amount' ? 'payments.amount_irr' : 'payments.created_at', $filters['direction'])->orderBy('payments.id')->limit(1001)->get();
        $refunds = $period(DB::table('refunds')->where('status', 'completed'), 'updated_at')->orderBy('updated_at')->limit(1001)->get();
        $this->limit(max($payments->count(), $refunds->count()));
        $cash = (int) $payments->sum('amount_irr');
        $out = (int) $refunds->sum('amount_irr');
        $opening = (int) DB::table('payments')->where('created_at', '<', $filters['start'])->sum('amount_irr') - (int) DB::table('refunds')->where('status', 'completed')->where('updated_at', '<', $filters['start'])->sum('amount_irr');
        $summary = ['دریافت نقدی/بانکی بازه ریال' => $cash, 'دریافت حضوری بازه ریال' => (int) $payments->where('provider', 'clinic')->sum('amount_irr'), 'دریافت اینترنتی بازه ریال' => (int) $payments->where('provider', 'zarinpal')->sum('amount_irr'), 'دریافت تخصیص‌نیافته بازه ریال' => (int) $payments->where('allocated', false)->sum('amount_irr'), 'مصرف اعتبار در بازه ریال (غیرنقدی)' => (int) $payments->sum('credit_irr'), 'استرداد تکمیل‌شده بازه ریال' => $out, 'خالص جریان نقد بازه ریال' => $cash - $out, 'مانده جریان نقد پیش از بازه ریال' => $opening, 'مانده جریان نقد تا پایان بازه ریال' => $opening + $cash - $out];
        if ($payments->where('provider', 'local_simulator')->isNotEmpty()) {
            $summary['شبیه‌سازی لوکال در جمع‌های بالا؛ بدون وجه واقعی (ریال)'] = (int) $payments->where('provider', 'local_simulator')->sum('amount_irr');
        }
        $rows = $payments->map(fn ($p) => [$p->id, $this->date($p->created_at), ['clinic' => 'حضوری', 'zarinpal' => 'زرین‌پال', 'local_simulator' => 'آزمایشی لوکال؛ بدون وجه واقعی', 'wallet' => 'اعتبار'][$p->provider] ?? $p->provider, $p->display_name ?: ($p->actor_id ? 'اپراتور #'.$p->actor_id : 'سیستمی/درگاه'), $p->appointment_reference, (int) $p->amount_irr, (int) $p->credit_irr, $p->allocated ? 'تخصیص‌یافته' : 'مغایرت/اعتبار', $p->provider_reference])->all();
        $refundRows = $refunds->map(fn ($r) => [$r->reference, $this->date($r->updated_at), (int) $r->amount_irr, 'تکمیل ثبت‌شده', $r->decided_by ? 'مدیر #'.$r->decided_by : 'ثبت قبلی', $r->bank_reference])->all();
        $staff = $period(DB::table('appointment_events')->whereNotNull('actor_id')->whereIn('action', ['held', 'reschedule', 'cancel'])->join('users', 'users.id', '=', 'appointment_events.actor_id')->whereExists(fn ($q) => $q->selectRaw('1')->from('user_roles')->join('roles', 'roles.id', '=', 'user_roles.role_id')->whereColumn('user_roles.user_id', 'users.id')->whereIn('roles.name', ['secretary', 'admin']))->select('actor_id', 'users.display_name', 'action')->selectRaw('COUNT(*) as total')->groupBy('actor_id', 'users.display_name', 'action'), 'appointment_events.created_at')->get();
        $staffRows = $staff->map(fn ($s) => [$s->actor_id, $s->display_name ?: 'اپراتور #'.$s->actor_id, ['held' => 'ایجاد نوبت', 'reschedule' => 'تغییر زمان', 'cancel' => 'لغو'][$s->action], (int) $s->total])->all();
        foreach ($payments->where('provider', 'clinic')->groupBy('actor_id') as $id => $receipts) {
            $name = $receipts->first()->display_name ?: 'اپراتور #'.$id;
            $staffRows[] = [(int) $id, $name, 'دریافت حضوری (تعداد رسید)', $receipts->count()];
            $staffRows[] = [(int) $id, $name, 'دریافت حضوری (ریال)', (int) $receipts->sum('amount_irr')];
        }
        $attendance = $period(DB::table('attendance_events')->join('users', 'users.id', '=', 'attendance_events.actor_id')->select('actor_id', 'users.display_name', 'action')->selectRaw('COUNT(*) as total')->groupBy('actor_id', 'users.display_name', 'action'), 'attendance_events.created_at')->get();
        foreach ($attendance as $event) {
            $staffRows[] = [$event->actor_id, $event->display_name ?: 'اپراتور #'.$event->actor_id, ['arrived' => 'ثبت ورود (تعداد)', 'scheduled' => 'اصلاح ورود (تعداد)', 'completed' => 'پایان خدمت (تعداد)', 'no_show' => 'ثبت غیبت (تعداد)'][$event->action], (int) $event->total];
        }
        return ['title' => 'گزارش مالی و تطبیق', 'basis' => 'بازه مالی بر اساس زمان ثبت رسید/تکمیل استرداد، نه روز نوبت؛ مبالغ به ریال و مربوط به زمان تهیه این خروجی هستند. مصرف اعتبار دریافت نقدی دوباره نیست. ثبت تکمیل استرداد گواه بانک نیست.', 'summary' => $summary, 'reconciliation' => $this->reconciliation(), 'sections' => [['title' => 'رسیدها', 'headers' => ['شناسه رسید', 'زمان شمسی', 'روش', 'ثبت‌کننده', 'کد نوبت', 'دریافت ریال', 'اعتبار ریال', 'تخصیص', 'ارجاع رسید'], 'rows' => $rows], ['title' => 'استرداد تکمیل‌شده', 'headers' => ['شناسه پیگیری', 'زمان تکمیل شمسی', 'مبلغ ریال', 'وضعیت', 'ثبت‌کننده', 'ارجاع ثبت‌شده استرداد'], 'rows' => $refundRows], ['title' => 'فعالیت اپراتور در بازه', 'headers' => ['شناسه', 'نام نمایشی', 'عملیات و واحد', 'مقدار'], 'rows' => $staffRows]]];
    }
    private function reconciliation(): array
    {
        $cash = (int) DB::table('payments')->sum('amount_irr');
        $refunds = (int) DB::table('refunds')->where('status', 'completed')->sum('amount_irr');
        $wallet = (int) DB::table('wallet_entries')->sum('amount_irr');
        $pending = -(int) DB::table('wallet_entries')->whereIn('kind', ['refund_reserved', 'refund_reversed'])->sum('amount_irr') - $refunds;
        $settled = (int) DB::table('appointments')->whereIn('status', ['confirmed', 'completed', 'no_show'])->selectRaw('COALESCE(SUM(price_irr - balance_due_irr),0) as total')->first()->total;
        $projection = (int) DB::table('wallet_accounts')->sum('balance_irr');
        $ledger = DB::table('wallet_entries')->select('owner_hash')->selectRaw('SUM(amount_irr) as ledger_balance')->groupBy('owner_hash');
        $mismatches = DB::table('wallet_accounts')->leftJoinSub($ledger, 'ledger', 'ledger.owner_hash', '=', 'wallet_accounts.owner_hash')->whereRaw('wallet_accounts.balance_irr <> COALESCE(ledger.ledger_balance,0)')->count();
        $mismatches += DB::query()->fromSub($ledger, 'ledger')->leftJoin('wallet_accounts', 'wallet_accounts.owner_hash', '=', 'ledger.owner_hash')->whereNull('wallet_accounts.owner_hash')->count();
        return ['کل دریافت خارجی ریال' => $cash, 'کل استرداد تکمیل ریال' => $refunds, 'ارزش تسویه‌شده نوبت لغونشده ریال' => $settled, 'مانده دفترکل اعتبار ریال' => $wallet, 'استرداد در جریان ریال' => $pending, 'اختلاف تطبیق ریال (باید صفر باشد)' => $cash - $refunds - $settled - $wallet - $pending, 'اختلاف مانده حساب و دفترکل ریال (باید صفر باشد)' => $projection - $wallet, 'تعداد حساب ناسازگار (باید صفر باشد)' => $mismatches];
    }
    public function audit(?int $actor, string $hash, string $kind, string $format, array $filters, int $rows): void
    {
        DB::table('report_access_events')->insert(['actor_id' => $actor, 'actor_hash' => $hash, 'kind' => $kind, 'format' => $format, 'filters' => json_encode($filters), 'row_count' => $rows, 'created_at' => now()]);
    }
    private function limit(int $count, int $maximum = 1000): void
    {
        if ($count > $maximum) {
            throw ValidationException::withMessages(['from' => 'گزارش بیش از '.$maximum.' ردیف دارد؛ بازه یا فیلتر را کوچک‌تر کنید. داده‌ای حذف نشده است.']);
        }
    }
    private function date($value): string
    {
        $date = $value instanceof \DateTimeInterface ? CarbonImmutable::instance($value) : CarbonImmutable::parse($value, 'UTC');
        return app(JalaliDate::class)->format($date).' '.$date->setTimezone('Asia/Tehran')->format('H:i');
    }
    private function operator(?User $user): string
    {
        return $user ? ($user->display_name ?: 'حساب #'.$user->id) : 'مهمان';
    }
}
