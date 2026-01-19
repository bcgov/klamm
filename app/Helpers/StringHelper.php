<?php

namespace App\Helpers;

class StringHelper
{
    public static function removeHyphensAndCapitalize($string)
    {
        return ucwords(str_replace('-', ' ', $string));
    }

    public static function formatFileSize(?int $bytes, int $decimals = 2, string $nullPlaceholder = '—'): string
    {
        if ($bytes === null) {
            return $nullPlaceholder;
        }

        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return number_format($bytes / (1024 ** $power), $decimals) . ' ' . $units[$power];
    }
}
