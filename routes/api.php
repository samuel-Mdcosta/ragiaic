<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\tentativasController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UsuarioController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


#UsuarioController
Route::post('/users/login', [UsuarioController::class, 'login']);
Route::post('/users/cadastro', [UsuarioController::class, 'cadastro']);
Route::post('/users/atualizar', [UsuarioController::class, 'atualizar'])->middleware('auth:sanctum');

#tentativasController
Route::post('/users/login/tentativas', [tentativasController::class, 'salvarTentaivas'])->middleware('auth:sanctum');

#chatController
Route::post('users/login/salvarUsoChat', [ChatController::class, 'salvarUsoChat'])->middleware('auth:sanctum');
Route::get('users/login/quantUsoChat', [ChatController::class, 'quantUsoChat'])->middleware('auth:sanctum');
Route::get('users/login/tempoUsoChat', [ChatController::class, 'tempoUsoChat'])->middleware('auth:sanctum');
