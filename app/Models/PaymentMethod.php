<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory, SoftDeletes;

    public const KINDS = [
        'cash'   => 'نقدي (يدخل الصندوق)',
        'bank'   => 'تحويل بنكي (اسم صاحب الحساب إجباري)',
        'wallet' => 'محفظة إلكترونية (الاسم ورقم الجوال إجباريان)',
    ];

    protected $fillable = [
        'name',
        'code',
        'kind',
        'icon',
        'color',
        'requires_wallet',
        'is_active',
    ];

    protected static function booted(): void
    {
        // النوع يحدد هل تمر على الصندوق؛ وإن لم يُحدد يُستنتج من الاسم
        static::saving(function (PaymentMethod $m) {
            $m->kind = in_array($m->kind, array_keys(self::KINDS), true)
                ? $m->kind
                : self::guessKind((string) $m->name, (string) $m->code, (bool) $m->requires_wallet);
            $m->requires_wallet = $m->kind !== 'cash';
        });
    }

    /** استنتاج النوع لطرق الدفع القديمة: كلمة «بنك/bank» = بنك، وأي طريقة غير نقدية أخرى = محفظة */
    public static function guessKind(string $name, string $code, bool $requiresWallet): string
    {
        if ($code === 'cash' || !$requiresWallet) {
            return 'cash';
        }
        $text = mb_strtolower($name . ' ' . $code);

        return (str_contains($text, 'بنك') || str_contains($text, 'bank') || str_contains($text, 'iban') || str_contains($text, 'تحويل')) ? 'bank' : 'wallet';
    }

    public function isBank(): bool
    {
        return $this->kind === 'bank';
    }

    public function isWallet(): bool
    {
        return $this->kind === 'wallet';
    }

    /**
     * التحقق من بيانات المحوِّل حسب النوع. يرجع رسالة الخطأ أو null.
     * $hasCustomer: إن كان هناك عميل مسجل فاسمه يُستخدم افتراضياً.
     */
    public function payerError(?string $name, ?string $phone, bool $hasCustomer = false): ?string
    {
        $nameMissing = trim((string) $name) === '' && !$hasCustomer;

        if ($this->isBank() && $nameMissing) {
            return 'أدخل اسم صاحب الحساب البنكي المحوِّل (كما يظهر في كشف البنك).';
        }
        if ($this->isWallet() && $nameMissing) {
            return 'أدخل اسم المحوِّل كما يظهر في المحفظة.';
        }
        if ($this->isWallet() && trim((string) $phone) === '') {
            return 'أدخل رقم جوال/محفظة المحوِّل.';
        }

        return null;
    }
}
