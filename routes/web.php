<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('boleto', [
    'as' => 'boleto',
    'uses' => 'ShopifyController@boleto'
]);


$router->get('localizacao', [
    'as' => 'localizacao',
    'uses' => 'ShopifyController@localizacao'
]);

$router->post('webhooks', [
    'as' => 'webhooks',
    'uses' => 'ShopifyController@webhooks'
]);

$router->post('delivery_notification', [
    'as' => 'delivery_notification',
    'uses' => 'ShopifyController@delivery_notification'
]);