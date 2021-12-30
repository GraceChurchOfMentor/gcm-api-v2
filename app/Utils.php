<?php

namespace App;

class Utils
{
    public static function normalizeTime($time)
    {
        return $time ? date('Y-m-d', strtotime($time)) : date('Y-m-d');
    }

    public static function isJson($string)
    {
        json_decode($string);

        return json_last_error() == JSON_ERROR_NONE;
    }
}
