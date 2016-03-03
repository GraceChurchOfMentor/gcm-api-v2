<?php

namespace App\Http\Controllers\Calendar;

use Cache;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class IcalController extends Controller
{
	public function getCalendar($id) {
		$url = self::getUrl($id);

		$data = Cache::get($id);
		$data = false;

		if ($data) {
			return $data;
		}

		try {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			$data = curl_exec($ch);
		} catch (Exception $e) {
			return "Could not retrieve file.";
		}

		Cache::put($id, $data, (60*24));

		return response($data, 200)
			->header('Content-Type', 'text/calendar; charset=utf-8')
			->header('Content-Disposition', 'inline; filename=calendar.ics');

	}

	public function forgetCalendar($id) {
		Cache::forget($id);

		return 'Cache cleared.';
	}

	private static function getUrl($id) {
		/**
		 * Right now we're just using the base64-encoded string of the URL to the source calendar.
		 * I don't see any reason to get more complicated than that.
		 */
		return base64_decode($id);
	}
}
