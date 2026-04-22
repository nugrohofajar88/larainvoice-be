<?php

use App\Support\InvoiceFormatter;

if (!function_exists('isSuperAdmin')) {
    function isSuperAdmin($user)
    {
        return in_array($user->role->name, ['administrator', 'admin pusat']);
    }
}

if (!function_exists('isSuperAdminRole')) {
    function isSuperAdminRole($role)
    {
        $roleName = is_string($role) ? $role : $role->name;
        return in_array($roleName, ['administrator', 'admin pusat']);
    }
}

if (!function_exists('toIndonesianDay')) {
    function toIndonesianDay(?string $day = ''): string
    {
        $days = [
            'sunday' => 'minggu',
            'monday' => 'senin',
            'tuesday' => 'selasa',
            'wednesday' => 'rabu',
            'thursday' => 'kamis',
            'friday' => 'jumat',
            'saturday' => 'sabtu',
        ];

        return $days[strtolower(trim((string) $day))] ?? '';
    }
}

if (!function_exists('toIndonesianMonth')) {
    function toIndonesianMonth(?string $month = ''): string
    {
        $months = [
            'january' => 'januari',
            'february' => 'februari',
            'march' => 'maret',
            'april' => 'april',
            'may' => 'mei',
            'june' => 'juni',
            'july' => 'juli',
            'august' => 'agustus',
            'september' => 'september',
            'october' => 'oktober',
            'november' => 'november',
            'december' => 'desember',
        ];

        return $months[strtolower(trim((string) $month))] ?? '';
    }
}

if (!function_exists('formatMoney')) {
    function formatMoney($number): string
    {
        $number = ($number === null || $number === '') ? 0 : (float) $number;

        return number_format($number, 0, ',', '.');
    }
}

if (!function_exists('unformatMoney')) {
    function unformatMoney(?string $money): string
    {
        $money = trim((string) $money);

        if ($money === '') {
            return '0';
        }

        return str_replace('.', '', $money);
    }
}

if (!function_exists('numberToText')) {
    function numberToText($nilai): string
    {
        $nilai = abs((int) $nilai);
        $huruf = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

        if ($nilai < 12) {
            return $huruf[$nilai];
        }

        if ($nilai < 20) {
            return trim(numberToText($nilai - 10) . ' belas');
        }

        if ($nilai < 100) {
            return trim(numberToText(intdiv($nilai, 10)) . ' puluh ' . numberToText($nilai % 10));
        }

        if ($nilai < 200) {
            return trim('seratus ' . numberToText($nilai - 100));
        }

        if ($nilai < 1000) {
            return trim(numberToText(intdiv($nilai, 100)) . ' ratus ' . numberToText($nilai % 100));
        }

        if ($nilai < 2000) {
            return trim('seribu ' . numberToText($nilai - 1000));
        }

        if ($nilai < 1000000) {
            return trim(numberToText(intdiv($nilai, 1000)) . ' ribu ' . numberToText($nilai % 1000));
        }

        if ($nilai < 1000000000) {
            return trim(numberToText(intdiv($nilai, 1000000)) . ' juta ' . numberToText($nilai % 1000000));
        }

        if ($nilai < 1000000000000) {
            return trim(numberToText(intdiv($nilai, 1000000000)) . ' milyar ' . numberToText($nilai % 1000000000));
        }

        if ($nilai < 1000000000000000) {
            return trim(numberToText(intdiv($nilai, 1000000000000)) . ' trilyun ' . numberToText($nilai % 1000000000000));
        }

        return '';
    }
}

if (!function_exists('toRomawi')) {
    function toRomawi($month): string
    {
        return InvoiceFormatter::toRomanMonth($month);
    }
}

if (!function_exists('toDigit')) {
    function toDigit($romawi): int|string
    {
        return InvoiceFormatter::fromRomanMonth($romawi);
    }
}

if (!function_exists('formatInvoice')) {
    function formatInvoice(?string $invoiceNumber): string
    {
        return InvoiceFormatter::format($invoiceNumber);
    }
}

if (!function_exists('unformatInvoice')) {
    function unformatInvoice(?string $invoiceNumber): string
    {
        return InvoiceFormatter::unformat($invoiceNumber);
    }
}
