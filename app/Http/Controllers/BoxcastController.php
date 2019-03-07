<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Request;
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

    public function getCountdown($channelId = false) {
        $this->client = new Client();

        foreach (array('current', 'preroll', 'future') as $timeframe) {
            try {
                $baseUri = config('boxcast.apiEndpointUri') . '/channels/' . $channelId . '/broadcasts';
                $request = new Request('GET', $baseUri);
                $response = $this->client->send($request, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->token->access_token
                    ],
                    'query' => [
                        'q' => "timeframe:$timeframe"
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

            if ( ! empty($broadcasts)) {
                break;
            }
        }

        if (empty($broadcasts)) {
            return response()->json([
                'status' => 'failure',
                'reason' => 'empty',
            ]);
        }

        $broadcasts = array_values($broadcasts);

        // get the full detail for the next broadcast
        try {
            $baseUri = config('boxcast.apiEndpointUri') . '/broadcasts/' . $broadcasts[0]->id;
            $request = new Request('GET', $baseUri);
            $response = $this->client->send($request);
        } catch (ClientException $e) {
            return response()->json([
                'status' => 'failure',
                'path' => $e->getRequest()->getUri()->getPath(),
                'request' => Psr7\str($e->getRequest()),
                'response' => Psr7\str($e->getResponse()),
            ]);
        }

        $next = json_decode($response->getBody());

        if ( ! empty($next)) {
        }

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
                    'grant_type' => "client_credentials",
                    'scope' => "owner",
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
}
