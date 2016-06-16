<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use GuzzleHttp\Psr7;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use App\Utils;
use Cache;
use Carbon\Carbon;
use Log;

class BoxcastController extends Controller
{
    public $token = false;
    public $client;

    public function __construct() {
        $this->token = $this->getToken();
    }

    public function getCountdown(Request $request, $channelId = false) {
        $this->client = new Client([
            'base_uri' => config('boxcast.apiEndpointUri'),
        ]);

        try {
            $response = $this->client->get('/broadcasts', [
                'query' => [
                    'channel_id' => $channelId,
                ]
            ]);
        } catch (ClientException $e) {
            return response()->json([
                'status' => 'failure',
                'request' => Psr7\str($e->getRequest()),
                'response' => Psr7\str($e->getResponse()),
            ]);

        }

        $broadcasts = json_decode($response->getBody());

        $broadcasts = array_filter($broadcasts, function($b) {
            if (strtotime($b->stops_at) < strtotime('now')) {
                return false;
            }

            return true;
        });

        if (empty($broadcasts)) {
            return response()->json([
                'status' => 'failure',
            ]);
        }

        $broadcasts = array_values($broadcasts);

        $next = $broadcasts[0];


        $data = (object) array(
            'status' => 'success',
            'details' => $next,
        );

        return response()->json($data);
    }

    private function getToken() {
        if ( ! Cache::tags('boxcast')->has('token')) {
            $authBasicToken = base64_encode(config('boxcast.apiClientId') . ":" . config('boxcast.apiClientSecret'));

            $this->client = new Client([
                'base_uri' => config('boxcast.apiLoginEndpointUri'),
            ]);

            $response = $this->client->post('/oauth2/token', [
                'headers' => [
                    'Authorization' => "Basic $authBasicToken",
                ],
                'form_params' => [
                    'grant_type' => "password",
                    'username' => config('boxcast.accountUsername'),
                    'password' => config('boxcast.accountPassword'),
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return false;
            }

            $data = json_decode($response->getBody()->getContents());

            $expireTime = Carbon::now()->addSeconds($data->expires_in)->subMinutes(5);

            Cache::tags('boxcast')->put('token', $data, $expireTime);
        }

        return Cache::tags('boxcast')->get('token');
    }

    public function getEvents(Request $request, $featured = false) {
        /*
            // html view options
            'show_details' => $this->get('show_details'),
            'trim'         => $this->get('trim')
         */

        $result = $this->getRawListing($request->input('dateStart'), $request->input('dateEnd'), $request->input('timeframe'));

        if ($request->input('dateStart')) {
            $result->request->dateStart = $request->input('dateStart');
        }

        if ($request->input('dateEnd')) {
            $result->request->dateEnd = $request->input('dateEnd');
        }

        if ($request->input('timeframe')) {
            $result->request->timeframe = $request->input('timeframe');
        }

        if ($featured) {
            $result->request->featured = $featured;
            $result = self::filterFeatured($result);
        }

        if ($request->input('groups')) {
            $result->request->groups = $request->input('groups');
            $result = self::filterGroups($result, $request->input('groups'));
        }

        if ($request->input('search')) {
            $result->request->search = $request->input('search');
            $result = self::filterSearch($result, $request->input('search'), $request->input('searchOperation'));
        }

        if ($request->input('searchOperation')) {
            $result->request->searchOperation = $request->input('searchOperation');
        }

        if ($request->input('count')) {
            $result->request->count = $request->input('count');
            $result = self::filterCount($result, $request->input('count'));
        }

        return self::outputEvents($result);
    }

    public function clearCache() {
        Cache::tags('events')->flush();

        return "Events cache cleared.";
    }

    private function getRawListing($dateStart = FALSE, $dateEnd = FALSE, $timeframe = FALSE) {
        $timeframe = $timeframe ? $timeframe : config('gcm.events.defaultTimeframe');
        $dateStart = Utils::normalizeTime($dateStart);
        $dateEnd = $dateEnd ? Utils::normalizeTime($dateEnd) : Utils::normalizeTime("$dateStart $timeframe");

        $cacheTime = 15;
        $cacheKey = "events-public-calendar-listing-" . base64_encode("$dateStart$dateEnd");

        if ( ! Cache::tags('events')->has($cacheKey)) {
            $client = new Client([
                'base_uri' => config('ccb.apiEndpointUri'),
            ]);

            $response = $client->request('GET', '', [
                'auth' => [
                    config('ccb.apiUsername'),
                    config('ccb.apiPassword'),
                    'basic'
                ],
                'query' => [
                    'srv' => 'public_calendar_listing',
                    'date_start' => $dateStart,
                    'date_end' => $dateEnd,
                ]
            ]);

            $data = (string) $response->getBody();

            Storage::put('ccb-events-most-recent.xml', $data);
            Cache::tags('events')->put($cacheKey, $data, $cacheTime);
        }

        return self::formatRawListing(Cache::tags('events')->get($cacheKey));
    }

    private static function formatRawListing($xml) {
        $data = simplexml_load_string($xml);

        $data = (object) [
            'request' => (object) [],
            'parsed' => (object) [
                'dateStart' => (string) $data->request->parameters->xpath('argument[@name="date_start"]')[0]->attributes()['value'],
                'dateEnd' => (string) $data->request->parameters->xpath('argument[@name="date_end"]')[0]['value'],
            ],
            'count' => count($data->response->items->item),
            'events' => $data->response->items->xpath('//item'),
        ];

        return $data;
    }

    private static function outputEvents($events) {
        // remove asterisks from featured events
        array_walk($events->events, function(&$event) {
            if (substr($event->event_name, 0, 1) == "*") {
                $event->event_name = preg_replace("/^\*[\s]*/", "", $event->event_name);
            }
        });

        $events = (object) [
            'request' => $events->request,
            'parsed' => $events->parsed,
            'count' => count($events->events),
            'events' => array_values($events->events),
        ];

        return response()->json($events);
    }

    private static function filterFeatured($events) {
        $events->events = array_filter($events->events, function($e) {
            if (substr($e->event_name, 0, 1) == "*") {
                return true;
            }

            return false;
        });

        return $events;
    }

    private static function filterCount($events, $count) {
        $events->events = array_slice($events->events, 0, $count);

        return $events;
    }

    private static function filterGroups($events, $groups) {
        $groups = urldecode($groups);

        // turn the $groups argument into an array
        if (Utils::isJson($groups)) {
            $groups = json_decode($groups);
        } elseif (is_string($groups)) {
            $groups = [ $groups ];
        }

        $events->events = array_filter($events->events, function($e) use ($groups) {
            foreach ($groups as $g) {
                if ($e->group_name == $g) {
                    return true;
                }
            }

            return false;
        });

        $events->parsed->groups = $groups;

        return $events;
    }

    private static function filterSearch($events, $query, $searchOperation = "all") {
        $query = urldecode($query);

        if (Utils::isJson($query)) {
            $query = (object) [ "fields" => json_decode($query) ];
        } elseif (is_string($query)) {
            $query = (object) [ "all" => $query ];
        }

        $events->parsed->search = $query;

        if (isset($query->all)) {
            $events = self::searchAll($events, $query->all);
        } elseif (isset($query->fields)) {
            if ($searchOperation == "any") {
                $events->parsed->searchOperation = "any";
                $events = self::searchFieldsAny($events, $query->fields);
            } else {
                $events->parsed->searchOperation = "all";
                $events = self::searchFieldsAll($events, $query->fields);
            }
        }

        return $events;
    }

    private static function searchAll($events, $query) {
        $events->events = array_filter($events->events, function($e) use ($query) {
            $string = "";
            foreach ($e as $value) {
                $string .= "$value ";
            }

            $string = html_entity_decode($string);
            $string = strip_tags($string);

            if (stripos($string, $query) !== false) {
                return true;
            }

            return false;
        });

        return $events;
    }

    private static function searchFieldsAll($events, $fields) {
        $events->events = array_filter($events->events, function($e) use ($fields) {
            foreach ($fields as $key => $value) {
                if ( ! isset($e->$key)) {
                    return false;
                }
                if (stripos($e->$key, $value) === false) {
                    return false;
                }
            }

            return true;
        });

        return $events;
    }

    private static function searchFieldsAny($events, $fields) {
        $events->events = array_filter($events->events, function($e) use ($fields) {
            foreach ($fields as $key => $value) {
                if (isset($e->$key)) {
                    if (stripos($e->$key, $value) !== false) {
                        return true;
                    }
                }
            }

            return false;
        });

        return $events;
    }
}
