<?php

namespace App\Support;

class Format
{
    /**
     * Format monetary values as Rupiah with thousand separators and no decimal fraction.
     * Example: 1000000 -> "Rp 1.000.000", -50000 -> "-Rp 50.000"
     */
    public static function currency(float|int|string $amount): string
    {
        $val = (float) $amount;
        if ($val < 0) {
            return '-Rp ' . number_format(abs($val), 0, ',', '.');
        }
        return 'Rp ' . number_format($val, 0, ',', '.');
    }

    /**
     * Format quantity removing trailing decimal zeros while preserving meaningful fractions.
     * Example: 3.00 -> "3", 3.50 -> "3,5", 3.25 -> "3,25"
     */
    public static function quantity(float|int|string $qty): string
    {
        $val = (float) $qty;
        if (floor($val) == $val) {
            return number_format($val, 0, ',', '.');
        }
        $formatted = number_format($val, 2, ',', '.');
        return rtrim(rtrim($formatted, '0'), ',');
    }

    /**
     * Format discount percentage removing trailing decimal zeros.
     * Example: 30.00 -> "30%", 12.50 -> "12,5%"
     */
    public static function percentage(float|int|string $pct): string
    {
        $val = (float) $pct;
        if (floor($val) == $val) {
            return (int) $val . '%';
        }
        $formatted = number_format($val, 2, ',', '.');
        return rtrim(rtrim($formatted, '0'), ',') . '%';
    }
}
