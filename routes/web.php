<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/users/login/{id}', 'UserController@login');
Route::post('/users/cadastro', 'UserController@cadastro');
