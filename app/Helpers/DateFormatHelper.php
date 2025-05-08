<?php

namespace App\Helpers;

class DateFormatHelper
{
    public static function dateFormats()
    {
        return [
            'DD/MM/YY' => 'DD/MM/YY',
            'D-MMM-YY' => 'D-MMM-YY',
            'MMMM D, YYYY' => 'MMMM D, YYYY',
            'EEEE, MMMM D, YYYY' => 'EEEE, MMMM D, YYYY',
            'YYYY-MM-DD' => 'YYYY-MM-DD',
            'DD/MM/YYYY' => 'DD/MM/YYYY',
            'D/M/YY' => 'D/M/YY',
            'YY-MM-DD' => 'YY-MM-DD',
            'M/DD/YY' => 'M/DD/YY',
            'DD-MMM-YY' => 'DD-MMM-YY',
            'DD-MMM-YYYY' => 'DD-MMM-YYYY',
            'M/D/YYYY' => 'M/D/YYYY',
            'M/D/YY' => 'M/D/YY',
            'MM/DD/YY' => 'MM/DD/YY',
            'MM/DD/YYYY' => 'MM/DD/YYYY',
            'EEEE, MMMM DD, YYYY' => 'EEEE, MMMM DD, YYYY',
            'MMMM-DD-YY' => 'MMMM-DD-YY',
            'MMMM DD, YYYY' => 'MMMM DD, YYYY',
            'MMMM, YYYY' => 'MMMM, YYYY',
        ];
    }
}
