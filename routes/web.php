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

// Route::get('/index', function () {
//     return view('index');
// });

Route::get('/', 'App\Http\Controllers\PrincipalController@index');

Route::post('/store', 'App\Http\Controllers\PrincipalController@store');


Route::post('update', 'App\Http\Controllers\PrincipalController@update');

Route::post('storeGol', 'App\Http\Controllers\PrincipalController@storeGol');
Route::post('updateGol', 'App\Http\Controllers\PrincipalController@updateGol');
Route::post('deleteJogador', 'App\Http\Controllers\PrincipalController@deleteJogador');
Route::get('getGolsJogador', 'App\Http\Controllers\PrincipalController@getGolsJogador');
Route::get('getEstatisticas', 'App\Http\Controllers\PrincipalController@getEstatisticas');
Route::get('getJogadores', 'App\Http\Controllers\PrincipalController@getJogadores');