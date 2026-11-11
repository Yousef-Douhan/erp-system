<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

/**
 * حماية من إدخال سعر شراء أعلى من سعر البيع.
 * أغلب الحالات سببها كتابة سعر العلبة بدل سعر الحبة (الوحدة الأساسية)، فيقلب تقرير الأرباح لخسارة.
 */
class PriceGuard
{
    /**
     * @param array<string, array{0:int|string, 1:float|string|null}> $entries  [اسم الحقل => [رقم المنتج, سعر الشراء]]
     */
    public static function check(array $entries): void
    {
        $entries = array_filter($entries, fn ($e) => $e[1] !== null && $e[1] !== '' && (float) $e[1] > 0);
        if (!$entries) {
            return;
        }

        $products = Product::whereIn('id', array_map(fn ($e) => (int) $e[0], $entries))
            ->get(['id', 'name', 'selling_price'])
            ->keyBy('id');

        $errors = [];
        foreach ($entries as $field => [$productId, $cost]) {
            $p = $products[(int) $productId] ?? null;
            if ($p && (float) $p->selling_price > 0 && (float) $cost > (float) $p->selling_price) {
                $errors[$field] = self::message($p->name, (float) $cost, (float) $p->selling_price);
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    public static function message(string $name, float $cost, float $price): string
    {
        return "سعر الشراء لـ ({$name}) = " . number_format($cost, 2) . ' أعلى من سعر بيع الحبة (' . number_format($price, 2)
            . '). أدخل سعر الحبة الواحدة وليس العلبة، أو عدّل سعر البيع أولاً.';
    }
}
