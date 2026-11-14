<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DesempenhoController;
use App\Http\Controllers\QuizController;
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
Route::post('/users/login', [UsuarioController::class, 'login'])->middleware('throttle:login');
Route::post('/users/cadastro', [UsuarioController::class, 'cadastro'])->middleware('throttle:cadastro');
Route::post('/users/atualizar', [UsuarioController::class, 'atualizar'])->middleware('auth:sanctum');
Route::post('/users/atualizarPerfil', [UsuarioController::class, 'atualizarPerfil'])->middleware('auth:sanctum');
Route::post('/users/logout', [UsuarioController::class, 'logout'])->middleware('auth:sanctum');

#tentativasController
Route::post('/users/login/tentativas', [tentativasController::class, 'registrarTentativa'])->middleware('auth:sanctum');
Route::get('/users/login/tentativas/quantidade', [tentativasController::class, 'quantTentativas'])->middleware('auth:sanctum');
Route::get('/users/login/tentativas/stats', [tentativasController::class, 'stats'])->middleware('auth:sanctum');

#QuizController: quiz com o banco de questões (sorteio sem gabarito; a tentativa é registrada ao concluir)
Route::post('/users/login/tentativas/perguntas', [QuizController::class, 'iniciar'])->middleware(['auth:sanctum', 'throttle:perguntas']);
Route::post('/users/login/quiz/{quiz}/responder', [QuizController::class, 'responder'])->whereNumber('quiz')->middleware('auth:sanctum');
Route::get('/users/login/quiz/{quiz}/gabarito', [QuizController::class, 'gabarito'])->whereNumber('quiz')->middleware('auth:sanctum');

#DesempenhoController
Route::get('/users/login/desempenho', [DesempenhoController::class, 'desempenho'])->middleware('auth:sanctum');
Route::get('/users/login/desempenho/feedback', [DesempenhoController::class, 'feedback'])->middleware('auth:sanctum');

#AdminController
Route::get('/admin/alunos', [AdminController::class, 'listarAlunos'])->middleware('auth:sanctum');
Route::get('/admin/alunos/{id}/temas', [AdminController::class, 'listarTemasAluno'])->middleware('auth:sanctum');
#redefinição de senha: só o admin pode redefinir a senha de um aluno, a pedido do monitor.
Route::post('/admin/alunos/{id}/redefinirSenha', [AdminController::class, 'redefinirSenhaAluno'])->middleware('auth:sanctum');

#chatController
Route::post('/users/login/chat/salvarUso', [ChatController::class, 'salvarUsoChat'])->middleware('auth:sanctum');
Route::post('/users/login/chat/mensagem', [ChatController::class, 'requestChat'])->middleware('auth:sanctum');
Route::get('/users/login/chat/quantidade', [ChatController::class, 'quantUsoChat'])->middleware('auth:sanctum');
Route::get('/users/login/chat/tempo', [ChatController::class, 'tempoUsoChat'])->middleware('auth:sanctum');
