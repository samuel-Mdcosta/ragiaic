<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\tentativasController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UsuarioController;

#retorna o grupo completo do usuário autenticado: nome, email, id, etc.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

#UsuarioController
Route::post('/users/login', [UsuarioController::class, 'login']);
Route::post('/users/cadastro', [UsuarioController::class, 'cadastro']);
Route::post('/users/atualizar', [UsuarioController::class, 'atualizar'])->middleware('auth:sanctum');
Route::post('/users/logout', [UsuarioController::class, 'logout'])->middleware('auth:sanctum');

#tentativasController
Route::post('/users/login/tentativas', [tentativasController::class, 'registrarTentativa'])->middleware('auth:sanctum');
Route::post('/users/login/tentativas/perguntas', [tentativasController::class, 'requestPerguntas'])->middleware('auth:sanctum');
Route::get('/users/login/tentativas/quantidade', [tentativasController::class, 'quantTentativas'])->middleware('auth:sanctum');

#chatController
Route::post('users/login/chat/salvarUso', [ChatController::class, 'salvarUsoChat'])->middleware('auth:sanctum');
Route::post('users/login/chat/mensagem', [ChatController::class, 'requestChat'])->middleware('auth:sanctum');
Route::get('users/login/chat/quantidade', [ChatController::class, 'quantUsoChat'])->middleware('auth:sanctum');
Route::get('users/login/chat/tempo', [ChatController::class, 'tempoUsoChat'])->middleware('auth:sanctum');
