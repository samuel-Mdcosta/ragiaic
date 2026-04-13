
# 🧠 RagiaIC — AI Learning Platform Backend

> **REST API backend for an AI-powered neuroscience learning platform**, built as part of a university research group (Iniciação Científica). Orchestrates requests between the frontend, an LLM-based chat assistant, and an intelligent quiz engine — while tracking student engagement and performance over time.

[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=flat&logo=php&logoColor=white)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat&logo=laravel&logoColor=white)](https://laravel.com)
[![Sanctum](https://img.shields.io/badge/Auth-Laravel_Sanctum-FF2D20?style=flat&logo=laravel&logoColor=white)](https://laravel.com/docs/sanctum)
[![SQLite](https://img.shields.io/badge/Database-SQLite-003B57?style=flat&logo=sqlite&logoColor=white)](https://sqlite.org)
[![Tailwind](https://img.shields.io/badge/CSS-Tailwind_v4-06B6D4?style=flat&logo=tailwindcss&logoColor=white)](https://tailwindcss.com)

---

## What it does

University students studying neuroscience need more than static PDFs — they need active recall, personalized feedback, and access to an AI tutor when professors aren't available. RagiaIC is the backend that powers all of that:

- **AI chat proxy** — routes student questions to an external LLM and tracks usage time per session
- **Intelligent quiz engine** — proxies dynamically generated questions from an AI API and records every attempt (correct answers, mistakes, content accessed)
- **Engagement analytics** — exposes aggregated stats per user (total chat sessions, accumulated time, quiz attempts) to generate faculty reports
- **Secure auth** — token-based authentication via Laravel Sanctum, with role-aware route protection

The system was designed to produce data that feeds **analytical reports for the teaching staff**, enabling evidence-based decisions about student performance and content gaps.

---

## Architecture

```
Frontend (React)
      │
      ▼
┌─────────────────────────────────────────┐
│              Laravel 12 API             │
│                                         │
│  ┌──────────────┐  ┌──────────────────┐ │
│  │  Auth module │  │   Chat module    │ │
│  │  (Sanctum)   │  │  proxy + tracker │ │
│  └──────────────┘  └──────────────────┘ │
│                                         │
│  ┌──────────────────────────────────┐   │
│  │          Quiz module             │   │
│  │  proxy + attempt recorder        │   │
│  └──────────────────────────────────┘   │
│                                         │
│  Controller → Service → Model (Eloquent)│
└───────────┬─────────────────────────────┘
            │
            ▼
       SQLite database
  (usuarios, parametro_chats,
   tentativa_quizzs, personal_access_tokens)
```

**Design pattern:** every module follows `Controller → Service → Model`. Controllers handle HTTP concerns only (validation, response format). Business logic lives exclusively in Services (`app/Providers/`). Models are thin Eloquent wrappers.

---

## Modules & API reference

All protected routes require: `Authorization: Bearer {token}`

### Authentication — `UsuarioController`

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | `/api/users/login` | No | Login → returns Sanctum token |
| POST | `/api/users/cadastro` | No | Register new student account |
| POST | `/api/users/atualizar` | Yes | Update password |
| POST | `/api/users/logout` | Yes | Revoke current token |
| GET | `/api/user` | Yes | Return authenticated user data |

### Chat (AI Tutor) — `ChatController`

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | `/api/users/login/chat/mensagem` | Yes | Proxy message to external LLM API |
| POST | `/api/users/login/chat/salvarUso` | Yes | Record session usage (duration in seconds) |
| GET | `/api/users/login/chat/quantidade` | Yes | Total chat sessions for user |
| GET | `/api/users/login/chat/tempo` | Yes | Total accumulated usage time |

### Quiz & Attempts — `tentativasController`

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | `/api/users/login/tentativas/perguntas` | Yes | Proxy to external AI quiz-generation API |
| POST | `/api/users/login/tentativas` | Yes | Record quiz attempt (hits, errors, content) |
| GET | `/api/users/login/tentativas/quantidade` | Yes | Total attempts for user |

---

## Tech stack

| Layer | Technology |
|---|---|
| Framework | Laravel 12 (PHP 8.2) |
| Authentication | Laravel Sanctum (token-based) |
| ORM | Eloquent |
| Database | SQLite |
| Frontend assets | Vite + Tailwind CSS v4 |
| Architecture | REST API — Controller → Service → Model |

---

## Project structure

```
ragiaic/
├── app/
│   ├── Http/Controllers/
│   │   ├── UsuarioController.php    # Auth endpoints
│   │   ├── ChatController.php       # Chat proxy + usage tracking
│   │   └── tentativasController.php # Quiz proxy + attempt recording
│   ├── Models/
│   │   ├── Usuario.php              # Custom user model (HasApiTokens)
│   │   ├── parametroChat.php        # Chat usage record
│   │   └── TentativaQuizz.php       # Quiz attempt record
│   └── Providers/                   # Services (business logic layer)
│       ├── UsuarioService.php
│       ├── ChatService.php
│       └── TentativaService.php
├── database/
│   └── migrations/                  # usuarios, parametro_chats, tentativa_quizzs
├── routes/
│   └── api.php                      # All API route definitions
└── CLAUDE.md
```

---

## Getting started

### Prerequisites

- PHP 8.2+
- Composer
- Node.js 18+ (for frontend assets)

### Installation

```bash
# Clone the repository
git clone https://github.com/samuel-Mdcosta/ragiaic.git
cd ragiaic

# Install PHP dependencies
composer install

# Install JS dependencies
npm install

# Configure environment
cp .env.example .env
php artisan key:generate
```

### Environment variables

Edit `.env` with your settings:

```env
APP_NAME=RagiaIC
APP_ENV=local
APP_KEY=        # generated by artisan key:generate
APP_URL=http://localhost

DB_CONNECTION=sqlite
# database/database.sqlite is created automatically

# External AI API endpoints (chat and quiz)
CHAT_API_URL=your_llm_api_endpoint
QUIZ_API_URL=your_quiz_generation_endpoint
```

### Running

```bash
# Run database migrations
php artisan migrate

# Start development server
php artisan serve
```

API available at `http://localhost:8000`. Interactive API docs if using a tool like Scribe or Swagger can be configured separately.

### Build frontend assets

```bash
npm run dev   # development with HMR
npm run build # production build
```

---

## Response format

All endpoints return consistent JSON:

```json
// Success with resource creation (201)
{ "message": "...", "dado": { } }

// Success (200)
{ "message": "..." }

// Error (4xx / 5xx)
{ "message": "..." }
```

---

## Database schema

```
usuarios
  id, nome, email, senha (hashed), created_at

parametro_chats
  id, usuario_id → usuarios, usoChat (bool), tempoUsoChat (int, seconds)

tentativa_quizzs
  id, usuario_id → usuarios, conteudoAcessado, acertos (int), erros (int)

personal_access_tokens
  (standard Laravel Sanctum table)
```

---

## Context

This backend was built as part of a **research group at Universidade Uniderp**, collaborating with a master's student to develop AI-assisted tools for neuroscience education. The platform uses a RAG (Retrieval-Augmented Generation) pipeline to ground the AI tutor in curated academic content, generating vector embeddings from PDFs for high-precision information retrieval.

This repository contains the **Laravel orchestration layer** — the component that ties together user management, AI service proxying, and engagement data persistence.

---

## Related repositories

- **Frontend:** [github.com/samuel-Mdcosta/Iniciacao-Cientifica_Tutor_Virtual-main](https://github.com/samuel-Mdcosta/Iniciacao-Cientifica_Tutor_Virtual-main)

---

## Author

**Samuel M. Costa** — Backend Developer | AI & LLMs

- LinkedIn: [linkedin.com/in/samuelmdcosta](https://linkedin.com/in/samuelmdcosta)
- Email: costadev19@gmail.com
- GitHub: [github.com/samuel-Mdcosta](https://github.com/samuel-Mdcosta)
