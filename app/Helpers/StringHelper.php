<?php

namespace App\Helpers;

class StringHelper
{
    public static function removeHyphensAndCapitalize($string)
    {
        return ucwords(str_replace('-', ' ', $string));
    }
}
