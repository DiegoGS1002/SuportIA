<?php

use Illuminate\Support\Facades\Route;

Route::get('/login', function () {
    return view('app');
})->name('login');

Route::middleware('auth')->get('/{any?}', function () {
    return view('app');
})->where('any', '.*');
