# CLAUDE.md — RagiaIC

## Visão Geral do Projeto

API REST em Laravel 12 para uma plataforma de aprendizado com IA. Gerencia autenticação de usuários, rastreamento de uso do chat com IA e tentativas de quiz.

**Stack:** PHP 8.2 · Laravel 12 · Laravel Sanctum · SQLite · Vite · Tailwind CSS 4

---

## Arquitetura

O projeto segue o padrão **Controller → Service → Model**:

- **Controllers** (`app/Http/Controllers/`) — recebem a request, validam, delegam ao Service, retornam JSON
- **Services** (`app/Providers/`) — lógica de negócio
- **Models** (`app/Models/`) — Eloquent ORM

### Convenção de nomenclatura

O código usa **português** para nomes de variáveis, métodos, rotas e campos de banco de dados.

---

## Módulos

### Autenticação — `UsuarioController` + `UsuarioService`

| Rota                   | Método | Auth | Descrição                            |
| ---------------------- | ------ | ---- | ------------------------------------ |
| `/api/users/login`     | POST   | Não  | Login → retorna token Sanctum        |
| `/api/users/cadastro`  | POST   | Não  | Registro de novo usuário             |
| `/api/users/atualizar` | POST   | Sim  | Atualiza senha                       |
| `/api/users/logout`    | POST   | Sim  | Revoga token atual                   |
| `/api/user`            | GET    | Sim  | Retorna dados do usuário autenticado |

**Model `Usuario`:** tabela `usuarios`, campo senha (hidden), sem timestamps, usa `HasApiTokens`.

### Chat — `ChatController` + `ChatService`

| Rota                               | Método | Auth | Descrição                                  |
| ---------------------------------- | ------ | ---- | ------------------------------------------ |
| `/api/users/login/chat/salvarUso`  | POST   | Sim  | Registra sessão de uso (tempo em segundos) |
| `/api/users/login/chat/mensagem`   | POST   | Sim  | Proxy para API externa do chat             |
| `/api/users/login/chat/quantidade` | GET    | Sim  | Total de sessões do usuário                |
| `/api/users/login/chat/tempo`      | GET    | Sim  | Tempo total acumulado de uso               |

**Model `parametroChat`:** campos `usuario_id`, `usoChat` (bool), `tempoUsoChat` (int).

### Quiz/Tentativas — `tentativasController` + `TentativaService`

| Rota                                     | Método | Auth | Descrição                                   |
| ---------------------------------------- | ------ | ---- | ------------------------------------------- |
| `/api/users/login/tentativas`            | POST   | Sim  | Registra tentativa (acertos/erros/conteúdo) |
| `/api/users/login/tentativas/perguntas`  | POST   | Sim  | Proxy para API externa de perguntas         |
| `/api/users/login/tentativas/quantidade` | GET    | Sim  | Total de tentativas do usuário              |

**Model `TentativaQuizz`:** campos `usuario_id`, `conteudoAcessado`, `acertos`, `erros`.

---

## Padrões de Resposta

```php
// Sucesso com criação
return response()->json(['message' => '...', 'dado' => $dado], 201);

// Sucesso simples
return response()->json(['message' => '...']);

// Erro
return response()->json(['message' => '...'], 4xx|5xx);
```

Toda rota protegida usa middleware `auth:sanctum`. O token vai no header `Authorization: Bearer {token}`.

## Migrações (referência rápida)

| Arquivo                                                 | Tabela                   |
| ------------------------------------------------------- | ------------------------ |
| `2026_03_20_025208_create_usuarios_table`               | `usuarios`               |
| `2026_03_22_012745_create_personal_access_tokens_table` | `personal_access_tokens` |
| `2026_03_22_020137_parametros_quiz`                     | `tentativa_quizzs`       |
| `2026_03_22_153124_parametro_chat`                      | `parametro_chats`        |

---

## Arquivos Irrelevantes — NÃO LER

Os arquivos abaixo **não são relevantes** para tarefas de desenvolvimento. Ignore-os para não consumir tokens desnecessários.

### Configuração do Laravel (`config/`)

```
config/app.php
config/auth.php
config/cache.php
config/cors.php
config/database.php
config/filesystems.php
config/logging.php
config/mail.php
config/queue.php
config/services.php
config/session.php
```

### Configuração de Frontend/Build

```
vite.config.js
tailwind.config.js (não existe, Tailwind 4 usa CSS nativo)
postcss.config.js
package.json
package-lock.json
```

### Dependências e Lock Files

```
vendor/          (toda a pasta)
composer.lock
node_modules/    (toda a pasta)
```

### Bootstrap e Infraestrutura Laravel

```
bootstrap/app.php
bootstrap/providers.php
bootstrap/cache/
public/index.php
public/.htaccess
```

### Storage e Logs

```
storage/         (toda a pasta)
.env
.env.example
```

### Qualidade de Código e CI

```
.editorconfig
.gitattributes
.gitignore
phpunit.xml
pint.json
```

### Frontend (quando não trabalhando em UI)

```
resources/css/
resources/js/
resources/views/welcome.blade.php
```

### Testes e Factories (quando não trabalhando neles)

```
tests/
database/factories/
database/seeders/
```

---

## Observações Importantes

- As APIs externas (chat e perguntas) estão com placeholder `#url da api#` nos controllers — ainda não configuradas
- O model `User.php` padrão do Laravel existe mas **não é usado** — o projeto usa `Usuario.php`
- Services ficam em `app/Providers/` (fora do padrão Laravel, mas é a estrutura deste projeto)
- Banco de dados: SQLite por padrão (`database/database.sqlite`)
