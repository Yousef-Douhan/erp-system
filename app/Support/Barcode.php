<?php

namespace App\Support;

/**
 * مولّد باركود Code128 (المجموعة B للنصوص، و C للأرقام) كصورة SVG — بدون أي مكتبة خارجية.
 * يقرؤه أي قارئ باركود عادي، ويصلح للأرقام والحروف الإنجليزية.
 */
class Barcode
{
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const START_B = 104;
    private const START_C = 105;
    private const CODE_B = 100;
    private const STOP = 106;

    /**
     * عرض الأشرطة: سلسلة أرقام تمثل عرض كل شريط/فراغ بالتناوب (يبدأ بشريط)
     */
    public static function widths(string $text): string
    {
        $text = self::sanitize($text);

        // الأرقام الطويلة (مثل باركود EAN) تُشفَّر بمجموعة C: كل رقمين في رمز واحد، فيصبح الباركود أقصر وأسهل قراءة
        if (preg_match('/^\d{4,}$/', $text)) {
            $codes = [self::START_C];
            $pairs = intdiv(strlen($text), 2) * 2;
            for ($i = 0; $i < $pairs; $i += 2) {
                $codes[] = (int) substr($text, $i, 2);
            }
            if ($pairs < strlen($text)) {
                $codes[] = self::CODE_B;
                $codes[] = ord($text[$pairs]) - 32;
            }
        } else {
            $codes = [self::START_B];
            foreach (str_split($text) as $ch) {
                $codes[] = ord($ch) - 32;
            }
        }

        $sum = $codes[0];
        foreach (array_slice($codes, 1) as $i => $code) {
            $sum += ($i + 1) * $code;
        }
        $codes[] = $sum % 103;
        $codes[] = self::STOP;

        return implode('', array_map(fn ($c) => self::PATTERNS[$c], $codes));
    }

    public static function svg(string $text, int $height = 48, float $module = 1.6, bool $withText = true): string
    {
        $text = self::sanitize($text);
        $widths = self::widths($text);
        $quiet = 10 * $module;
        $x = $quiet;
        $bars = '';

        foreach (str_split($widths) as $i => $w) {
            $w = (int) $w * $module;
            if ($i % 2 === 0) {
                $bars .= sprintf('<rect x="%.2f" y="0" width="%.2f" height="%d"/>', $x, $w, $height);
            }
            $x += $w;
        }

        $total = $x + $quiet;
        $textH = $withText ? 14 : 0;
        $label = $withText
            ? sprintf('<text x="%.2f" y="%d" font-family="monospace" font-size="12" text-anchor="middle" fill="#000">%s</text>', $total / 2, $height + 12, htmlspecialchars($text))
            : '';

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %.2f %d" width="%.2f" height="%d" role="img" aria-label="%s"><rect width="100%%" height="100%%" fill="#fff"/><g fill="#000">%s</g>%s</svg>',
            $total, $height + $textH, $total, $height + $textH, htmlspecialchars($text), $bars, $label
        );
    }

    /**
     * Code128B يدعم ASCII من 32 إلى 126 فقط
     */
    public static function sanitize(string $text): string
    {
        $clean = trim(preg_replace('/[^\x20-\x7E]/', '', $text));

        return $clean === '' ? '0' : substr($clean, 0, 40);
    }
}
