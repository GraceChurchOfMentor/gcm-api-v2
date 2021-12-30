<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Cache;
use Illuminate\Http\Response;
use Log;

class IcalController extends Controller
{
    public function getCalendar($id)
    {
        $url = self::getUrl($id);

        $data = Cache::get($id);

        if ($data) {
            return $data;
        }

        $data = self::getRemoteCalendar($url);
        $data = self::fixLongLines($data);

        Cache::tags('ical')->put($id, $data, now()->addHours(24));

        return response($data, 200)
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', 'inline; filename=calendar.ics');
    }

    public function forgetCalendar($id)
    {
        Cache::forget($id);

        return 'Cache cleared.';
    }

    private static function getUrl($id)
    {
        /**
         * Right now we're just using the base64-encoded string of the URL to the source calendar.
         * I don't see any reason to get more complicated than that.
         */
        return base64_decode($id);
    }

    private static function getRemoteCalendar($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $data = curl_exec($ch);

        return $data;
    }

    private static function fixLongLines($str)
    {
        $str = str_replace("\r\n\t", '', $str);
        $str = str_replace("\r\n ", '', $str);
        $str = preg_replace("/(\r\n)+/", "\r\n", $str);

        $lines = preg_split("/\r\n/", $str);
        $str = '';

        foreach ($lines as $line) {
            /*
            $match = preg_match("/([A-Z]+):(.*)/", $line, $matches);

            if ($match) {
                $preamble = $matches[1];
                $content = $matches[2];

                $content = wordwrap($content, 75, "\n\t", true);

                $str .= $preamble . ':' . $content;
            } else {
                $str .= $line;
            }
            */

            $str .= wordwrap($line, 72, "\r\n\t");

            $str .= "\r\n";
        }

        return $str;
    }
}
