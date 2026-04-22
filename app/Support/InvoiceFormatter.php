<?php

namespace App\Support;

class InvoiceFormatter
{
    private const ROMAN_MONTHS = [
        1 => 'I',
        2 => 'II',
        3 => 'III',
        4 => 'IV',
        5 => 'V',
        6 => 'VI',
        7 => 'VII',
        8 => 'VIII',
        9 => 'IX',
        10 => 'X',
        11 => 'XI',
        12 => 'XII',
    ];

    public static function toRomanMonth($month): string
    {
        $month = (int) $month;

        return self::ROMAN_MONTHS[$month] ?? '';
    }

    public static function fromRomanMonth($roman): int|string
    {
        $roman = strtoupper(trim((string) $roman));
        $months = array_flip(self::ROMAN_MONTHS);

        return $months[$roman] ?? '';
    }

    public static function format(?string $invoiceNumber): string
    {
        $parts = explode('/', (string) $invoiceNumber);

        if (count($parts) !== 5) {
            return (string) $invoiceNumber;
        }

        $month = self::toRomanMonth($parts[3]);

        if ($month === '') {
            return (string) $invoiceNumber;
        }

        return implode('/', [$parts[0], $parts[1], $parts[2], $month, $parts[4]]);
    }

    public static function unformat(?string $invoiceNumber): string
    {
        $parts = explode('/', (string) $invoiceNumber);

        if (count($parts) !== 5) {
            return (string) $invoiceNumber;
        }

        $month = self::fromRomanMonth($parts[3]);

        if ($month === '') {
            return (string) $invoiceNumber;
        }

        return implode('/', [$parts[0], $parts[1], $parts[2], str_pad((string) $month, 2, '0', STR_PAD_LEFT), $parts[4]]);
    }
}
