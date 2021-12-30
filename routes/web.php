<?php

use App\Http\Controllers\BoxcastController;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\IcalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes File
|--------------------------------------------------------------------------
|
| Here is where you will register all of the routes in an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| This route group applies the "web" middleware group to every route
| it contains. The "web" middleware group is defined in your HTTP
| kernel and includes session state, CSRF protection, and more.
|
*/

Route::middleware('web')->group(function () {
    //
});

Route::get('events/fetch', [EventsController::class, 'dispatchRetrieveEventCalendar']);
Route::get('events/cache/clear', [EventsController::class, 'clearCache']);
Route::get('events/{featured?}', [EventsController::class, 'getEvents']);

Route::get('calendar/ical/get/{base64url}', [IcalController::class, 'getCalendar']);
Route::get('calendar/ical/forget/{base64url}', [IcalController::class, 'forgetCalendar']);

Route::get('livestream/countdown/{channelId}', [BoxcastController::class, 'getCountdown']);
Route::get('livestream/countdown', [BoxcastController::class, 'getCountdown']);
