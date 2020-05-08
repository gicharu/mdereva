<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Set Webhook
|--------------------------------------------------------------------------
| This route is used to set your webhook with Telegram,
| just request from your browser once and then disable it.
|
| Example: http://domain.com/bot/set-webhook
*/
Route::get('/m-dereva-bot/set-webhook', 'M_DerevaBotController@setWebHook')->name('m_dereva-set-webhook');

/*
|--------------------------------------------------------------------------
| Remove Webhook
|--------------------------------------------------------------------------
| This route is used to remove your webhook with Telegram,
| just request from your browser once and then disable it.
|
| Example: http://domain.com/bot/remove-webhook
*/
Route::get('/m-dereva-bot/remove-webhook', 'M_DerevaBotController@removeWebhook')->name('m_dereva-remove-webhook');

/*
|--------------------------------------------------------------------------
| Webhook (Incoming Handler)
|--------------------------------------------------------------------------
| This route handles incoming webhook updates.
|
| !! IMPORTANT !!
| THIS IS AN INSECURE ENDPOINT FOR DEMO PURPOSES,
| MAKE SURE TO SECURE THIS ENDPOINT, EXAMPLE: "/bot/<SECRET-KEY>/webhook"
|
| THEN SET THAT WEBHOOK WITH TELEGRAM.
| SO YOU CAN BE SURE THE UPDATES ARE COMING FROM TELEGRAM ONLY.
*/
Route::post( '/m_dereva-bot/' . env('TELEGRAM_BOT_TOKEN') . '/webhook', 'M_DerevaBotController@webhook')->name('m_dereva-webhook');
