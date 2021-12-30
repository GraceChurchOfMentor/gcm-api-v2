<?php

namespace App\Jobs;

use App\Jobs\Job;
use App\Utils;
use Cache;
use Carbon\Carbon;
// GCM-specific stuff
use GuzzleHttp\Client;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Storage;

class RetrieveEventCalendar extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    private $timeZone;

    private $dateToday;

    private $ranges;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->timeZone = 'America/New_York';
        $this->dateToday = Carbon::now($this->timeZone);
        $this->ranges = [];

        $outerRange = self::parseRanges(config('gcm.events.dateRanges'));

        foreach ($outerRange as $i) {
            $m = Carbon::now($this->timeZone)->addMonths($i);
            $r = self::findInRanges(config('gcm.events.dateRanges'), $i);

            $this->ranges[$i] = [
                'offset'      => $i,
                'dateStart'   => Carbon::create($m->year, $m->month, 01)->format('Y-m-d'),
                'dateEnd'     => Carbon::create($m->year, $m->month, $m->daysInMonth)->format('Y-m-d'),
                'partOfRange' => $r['description'],
                'cacheMaxAge' => $r['cacheMaxAge'],
            ];
        }
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $output = [];

        foreach ($this->ranges as $r) {
            $dateStart = $r['dateStart'];
            $dateEnd = $r['dateEnd'];
            $cacheMaxAge = $r['cacheMaxAge'];
            $cacheKey = "ccb-public-calendar-listing-$dateStart-$dateEnd";

            if (! Cache::tags('events')->has($cacheKey)) {
                $data = json_decode(self::getEvents($r));

                // add "last updated" property to each event
                $updatedDate = Carbon::now();
                foreach ($data->events as &$event) {
                    $event->last_updated = $updatedDate;
                }

                Cache::tags('events')->put($cacheKey, json_encode($data), $cacheMaxAge);
            }

            $data = json_decode(Cache::tags('events')->get($cacheKey));
        }

        $output = array_merge($output, $data->events);

        Storage::put('ccb-events-most-recent.json', json_encode($output));
    }

    private static function getEvents($range)
    {
        $client = new Client([
            'base_uri' => config('ccb.apiEndpointUri'),
        ]);

        $response = $client->request('GET', '', [
            'auth' => [
                config('ccb.apiUsername'),
                config('ccb.apiPassword'),
                'basic',
            ],
            'query' => [
                'srv' => 'public_calendar_listing',
                'date_start' => $range['dateStart'],
                'date_end' => $range['dateEnd'],
            ],
        ]);

        $xml = (string) $response->getBody();

        $events = self::formatRawListing($xml);

        return json_encode($events);
    }

    private static function formatRawListing($xml)
    {
        $data = simplexml_load_string($xml);
        $output = (object) [
            'parsed' => (object) [
                'dateStart' => (string) $data->request->parameters->xpath('argument[@name="date_start"]')[0]->attributes()['value'],
                'dateEnd' => (string) $data->request->parameters->xpath('argument[@name="date_end"]')[0]['value'],
            ],
            'count' => count($data->response->items->item),
            'events' => $data->response->items->xpath('//item'),
        ];

        return $output;
    }

    private static function parseRanges($ranges)
    {
        $low = 0;
        $high = 0;

        foreach ($ranges as $r) {
            if ($r['rangeStart'] < $low) {
                $low = $r['rangeStart'];
            }
            if ($r['rangeEnd'] > $high) {
                $high = $r['rangeEnd'];
            }
        }

        return range($low, $high);
    }

    private static function findInRanges($ranges, $num)
    {
        foreach ($ranges as $r) {
            if (($num >= $r['rangeStart']) && ($num <= $r['rangeEnd'])) {
                return $r;
            }
        }
    }
}
