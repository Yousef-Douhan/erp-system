<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * خدمة القيود المحاسبية: كل قيد يُحفظ مع سطوره داخل Transaction
 * ويُرفض إذا لم يتساوَ المدين مع الدائن.
 */
class AccountingService
{
    // أكواد الحسابات الأساسية التي ينشئها النظام تلقائياً عند الحاجة
    public const CASH        = ['1010', 'الصندوق / النقدية', 'asset'];
    public const BANK        = ['1020', 'البنك والمحافظ الإلكترونية', 'asset'];
    public const RECEIVABLES = ['1200', 'ذمم العملاء (المدينون)', 'asset'];
    public const PAYABLES    = ['2100', 'ذمم الموردين (الدائنون)', 'liability'];
    public const OWNER       = ['3100', 'جاري المالك (إيداعات ومسحوبات)', 'equity'];
    public const SALES       = ['4100', 'إيرادات المبيعات', 'revenue'];
    public const SALES_RETURNS = ['4150', 'مردودات المبيعات', 'revenue'];
    public const OTHER_INCOME = ['4900', 'إيرادات أخرى', 'revenue'];
    public const PURCHASES   = ['5000', 'المشتريات (تكلفة البضاعة)', 'expense'];
    public const PURCHASE_RETURNS = ['5050', 'مردودات المشتريات', 'expense'];
    public const EXPENSES    = ['5100', 'المصروفات التشغيلية', 'expense'];
    public const OTHER_EXPENSES = ['5900', 'مصروفات أخرى', 'expense'];

    /**
     * جلب (أو إنشاء) حساب أساسي من دليل الحسابات
     */
    public static function systemAccount(array $definition, ?int $organizationId = null): Account
    {
        [$code, $name, $type] = $definition;

        // كل صيدلية لها دليل حسابات خاص بها
        $organizationId ??= \App\Support\Tenancy::orgId() ?? Organization::withoutGlobalScope('tenant')->value('id');

        return \App\Support\Tenancy::bypass(fn () => Account::firstOrCreate(
            ['account_code' => $code, 'organization_id' => $organizationId],
            ['name' => $name, 'account_type' => $type]
        ));
    }

    /**
     * توليد رقم قيد فريد
     */
    public static function nextEntryNumber(string $prefix = 'JE'): string
    {
        do {
            $number = $prefix . '-' . date('Ymd') . '-' . strtoupper(Str::random(6));
        } while (JournalEntry::where('entry_number', $number)->exists());

        return $number;
    }

    /**
     * إنشاء قيد متوازن مع سطوره
     *
     * @param array $lines [['account_id' => 1, 'debit' => 100, 'credit' => 0], ...]
     */
    public static function post(
        int $organizationId,
        string $description,
        array $lines,
        ?string $entryNumber = null,
        $entryDate = null,
        ?string $refType = null,
        ?int $refId = null
    ): JournalEntry {
        $lines = array_values(array_filter($lines, fn ($l) => (float) ($l['debit'] ?? 0) > 0 || (float) ($l['credit'] ?? 0) > 0));

        self::assertBalanced($lines);

        return DB::transaction(function () use ($organizationId, $description, $lines, $entryNumber, $entryDate, $refType, $refId) {
            $entry = JournalEntry::create([
                'organization_id' => $organizationId,
                'entry_number'    => $entryNumber ?: self::nextEntryNumber(),
                'entry_date'      => $entryDate ?: now()->toDateString(),
                'description'     => $description,
                'ref_type'        => $refType,
                'ref_id'          => $refId,
            ]);

            foreach ($lines as $line) {
                $entry->lines()->create([
                    'account_id' => $line['account_id'],
                    'debit'      => round((float) ($line['debit'] ?? 0), 2),
                    'credit'     => round((float) ($line['credit'] ?? 0), 2),
                ]);
            }

            return $entry;
        });
    }

    /**
     * التحقق من توازن القيد
     */
    public static function assertBalanced(array $lines): void
    {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('القيد يجب أن يحتوي على سطرين على الأقل.');
        }

        $debit = 0;
        $credit = 0;
        foreach ($lines as $line) {
            $d = (float) ($line['debit'] ?? 0);
            $c = (float) ($line['credit'] ?? 0);
            if ($d > 0 && $c > 0) {
                throw new InvalidArgumentException('لا يجوز أن يحتوي السطر الواحد على مدين ودائن معاً.');
            }
            $debit += $d;
            $credit += $c;
        }

        if ($debit <= 0 || abs(round($debit - $credit, 2)) > 0.001) {
            throw new InvalidArgumentException(
                'القيد غير متوازن: إجمالي المدين (' . number_format($debit, 2) . ') لا يساوي إجمالي الدائن (' . number_format($credit, 2) . ').'
            );
        }
    }
}
