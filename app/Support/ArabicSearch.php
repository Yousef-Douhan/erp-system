<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * بحث عربي غير حساس للحروف:
 * أ/إ/آ = ا ، ة = ه ، ى/ئ = ي ، ؤ = و ، بدون تشكيل أو تطويل، والأرقام العربية = الإنجليزية.
 * كل كلمة من كلمات البحث يجب أن تظهر في أحد الحقول (بأي ترتيب).
 */
class ArabicSearch
{
    private const MAP = [
        'إ' => 'ا', 'أ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ى' => 'ي', 'ئ' => 'ي', 'ؤ' => 'و', 'ة' => 'ه', 'ـ' => '',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    public static function normalize(?string $text): string
    {
        $text = mb_strtolower((string) $text);
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text); // التشكيل
        $text = strtr($text, self::MAP);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * تعبير SQL يوحّد العمود بنفس القواعد (MySQL)
     */
    public static function sqlColumn(string $column): string
    {
        $sql = "LOWER(COALESCE({$column}, ''))";
        foreach (self::MAP as $from => $to) {
            $sql = "REPLACE({$sql}, '{$from}', '{$to}')";
        }
        // أهم علامات التشكيل
        foreach (['ً', 'ٌ', 'ٍ', 'َ', 'ُ', 'ِ', 'ّ', 'ْ'] as $mark) {
            $sql = "REPLACE({$sql}, '{$mark}', '')";
        }

        return $sql;
    }

    /**
     * يضيف شرط البحث على الاستعلام
     */
    public static function apply(Builder $query, ?string $search, array $columns): Builder
    {
        $words = array_filter(explode(' ', self::normalize($search)), 'strlen');
        if (!$words) {
            return $query;
        }

        return $query->where(function ($outer) use ($words, $columns) {
            foreach ($words as $word) {
                $like = '%' . addcslashes($word, '%_\\') . '%';
                $outer->where(function ($q) use ($columns, $like) {
                    foreach ($columns as $column) {
                        $q->orWhereRaw(self::sqlColumn($column) . ' LIKE ?', [$like]);
                    }
                });
            }
        });
    }
}
