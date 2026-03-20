<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/users/login/{id}', 'UserController@login');
Route::post('/users/cadastro', 'UserController@cadastro');
Route::post('/users/atualizar/{id}', 'UserController@atualizar');
