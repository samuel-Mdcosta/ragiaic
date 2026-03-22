<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UsuarioController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/users/login/{id}', [UsuarioController::class, 'login']);
Route::post('/users/cadastro', [UsuarioController::class, 'cadastro']);
Route::post('/users/atualizar/{id}', [UsuarioController::class, 'atualizar']);
