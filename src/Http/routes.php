<?php

Route::get('/auth/redirect', 'Furdarius\OIDConnect\Http\Controllers\AuthController@redirect');
Route::get('/auth/callback', 'Furdarius\OIDConnect\Http\Controllers\AuthController@callback');
Route::post('/auth/refresh', 'Furdarius\OIDConnect\Http\Controllers\AuthController@refresh');
