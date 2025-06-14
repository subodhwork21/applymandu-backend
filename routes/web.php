<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Broadcast::routes(['middleware' => ['web', 'multiAuth']]);
