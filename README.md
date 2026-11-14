
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
- **Question bank quiz** — serves 10 questions per quiz from a reviewed question bank (generated offline by the AI pipeline from the textbooks), drawn per student: 30% easy / 50% medium / 20% hard, never-answered first, no repeats within 7 days. The answer key is only revealed after the answer is recorded, and every attempt is recorded server-side
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

### Quiz (question bank) — `QuizController`

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | `/api/users/login/tentativas/perguntas` | Yes | Body `{ tema, modo? }` (`modo`: `com_feedback` (default) or `sem_feedback`). Draws the quiz for the student and returns `{ quiz_id, tema, modo, questoes: [{ id, dificuldade, pergunta, opcoes[4], subtema, referencias }], aviso, info }` — **without** the answer key. `tema` must be in `config/temas.php`; `aviso` explains repeats or a short quiz |
| POST | `/api/users/login/quiz/{quiz}/responder` | Yes | Body `{ questao_id, indice (0-3) }`. Records the student's **initial** answer (it never changes; sending again returns `ja_respondida: true`). In `com_feedback` mode the response then includes `feedback` (`correta`, `acertou`, `explicacao`, `explicacoes_alternativas`, `fontes`). Answering the last question completes the quiz and records the attempt (`quiz_finalizado: true`) |
| GET | `/api/users/login/quiz/{quiz}/gabarito` | Yes | Answer key with justifications and full bibliographic references, only for the questions already answered |

Only the quiz owner can answer or read the answer key (404 otherwise). For a **completed** quiz, the answer key is only released after the satisfaction survey (403 with `avaliacao_pendente: true` before it).

### Satisfaction survey — `AvaliacaoQuizController`

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/api/users/login/avaliacao/questionario` | Yes | The questionnaire from `config/avaliacao.php`: `{ versao, escala: { min, max, rotulo_min, rotulo_max }, perguntas: [{ chave, texto }], comentario: { texto, max } }` |
| POST | `/api/users/login/quiz/{quiz}/avaliacao` | Yes | Body `{ notas: { <chave>: 0-5, ... }, comentario? }`. Every rating is required (0 = very bad, 5 = excellent); the comment is optional. Only for a completed quiz of the student (422/404 otherwise). The first evaluation counts: sending again returns `ja_avaliado: true` and changes nothing |

### Attempts — `tentativasController`

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | `/api/users/login/tentativas` | Yes | Legacy: record an attempt with client-side numbers (old front). Quizzes from the question bank are recorded by the server when the last answer arrives, with `acertos`/`erros` computed from the recorded answers |
| GET | `/api/users/login/tentativas/quantidade` | Yes | Total attempts for user |

### Performance feedback — `DesempenhoController`

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/api/users/login/desempenho` | Yes | Performance per theme: hit rate, recent rate (last 3 attempts), level (`critico` < 50% ≤ `atencao` < 70% ≤ `bom`), trend, themes not practiced yet |
| GET | `/api/users/login/desempenho/feedback` | Yes | AI feedback about the student's performance: weakest areas, what / why / where to study. Numbers and priority areas are computed here (`DesempenhoService`); the AI engine (`POST /feedback`) only writes the diagnosis. The last feedback is reused until the student finishes a new attempt |

The AI engine URL comes from `TUTOR_IA_URL`, falling back to `PYTHON_SERVICE_URL` and then to the Render deployment. `TUTOR_IA_TIMEOUT` sets the timeout in seconds (default 90). Every call to the engine (`/llm`, `/quizz`, `/feedback`) sends the `X-API-Key` header with `PYTHON_SERVICE_SECRET`, which must match `SHARED_SECRET` in the engine.

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

# Load the question bank (320 reviewed questions in database/data/questoes_40_por_tema.json)
php artisan questoes:importar --status=revisada

# Start development server
php artisan serve
```

API available at `http://localhost:8000`. Interactive API docs if using a tool like Scribe or Swagger can be configured separately.

---

## Question bank

The questions come ready-made from the generation pipeline (clinical case, command, 4 alternatives with explanations, justification and textbook references) and are stored in the `questoes` table. Nothing is generated by the AI at quiz time.

### Importing

```bash
php artisan questoes:importar --dry-run                      # validate and summarize, write nothing
php artisan questoes:importar --status=revisada              # import database/data/questoes_40_por_tema.json
php artisan questoes:importar path/to/file.json --status=revisada --somente-estas
```

- **Idempotent.** Each question is identified by `codigo` = sha256 of its normalized clinical case, command and theme (the same rule as the pipeline's `hash_questao`). Importing again updates the row instead of duplicating it, so the students' history keeps pointing to it.
- **Review status.** Only `revisada` questions are served to students. New questions take `--status` (default `pendente_revisao`, i.e. not served); on existing questions the status only moves forward to `revisada`, so a re-import never undoes a review.
- **`--somente-estas`** archives (`status = arquivada`) the questions that are not in the file. Nothing is deleted.
- **Student-facing text.** The pipeline cites book passages by id (`[6af4b26d…]`, "o chunk … descreve"). The import replaces those with the book name ("o livro «Neurociências»") or "o material de referência", and completes each reference with title, author, edition, publisher and year from `database/data/livros.json`. The original record is kept untouched in `questoes.bruto`.
- **Sibling questions.** Two questions of the same theme whose book passages overlap by 40% or more (Jaccard) are stored in `questoes_irmas` ("same concept, different clinical case"). Recomputed on every import.
- Themes must match `config/temas.php` byte by byte (and the front's `src/data/temasQuiz.js`); the import warns otherwise.

### Drawing a quiz (`SorteioQuizService`)

1. Mix of 30% easy / 50% medium / 20% hard (largest remainder: 10 → 3/5/2), completed with other levels when one is short.
2. Never-answered questions first, then the ones answered more than 7 days ago (oldest first).
3. Only if the theme runs out: questions answered within the 7 days, oldest first, and the student is warned (`aviso`).
4. Avoids two sibling questions in the same quiz and, when a concept comes back, brings the case seen longest ago.

Only **completed** quizzes count as history (an abandoned quiz counts as not done). Quiz size and window come from `config/quiz.php` (`QUIZ_QUESTOES`, `QUIZ_JANELA_DIAS`).

### Production (Render + Supabase)

`start.sh` runs the migrations on deploy, but the import is a separate step. After deploying, run it once against the production database (for example, from a machine with the production `DB_*` variables):

```bash
php artisan questoes:importar --status=revisada
```

Deploy the backend (and run the import) before the front: the new front relies on `quiz_id` and the `/quiz/{quiz}/...` routes.

---

## Satisfaction survey

When a quiz is completed, the student must rate it before seeing the result: one rating from 0 (very bad) to 5 (excellent) per question, all required, plus an optional comment. Only then the result (answer key with justifications) and the tutor's analysis are released. The rule is enforced by the backend, not only by the front:

- `GET /quiz/{quiz}/gabarito` returns 403 (`avaliacao_pendente: true`) for a completed quiz without an evaluation.
- `GET /desempenho/feedback` returns `status: "avaliacao_pendente"` (with `avaliacao.quiz_id` and `avaliacao.tema`), **without calling the AI**, while the student's latest completed quiz has no evaluation. The "Meu Desempenho" page then shows the survey in place of the analysis, so a student who closed the tab still has to evaluate before seeing it.

### Changing the questions

The questions in `config/avaliacao.php` are **examples** (still to be defined). The front builds the form from the API, so only this file changes:

- Edit `perguntas` (`chave` + `texto`) and the optional `comentario` text, and **bump `versao`**.
- Never reuse a `chave` for a different question. Each answer stores the questionnaire version and the question text as it was when answered.

### Analyzing the answers

Tables: `avaliacoes_quiz` (one per quiz: `quiz_id`, `usuario_id`, `versao_questionario`, `comentario`) and `avaliacoes_quiz_notas` (one row per rating: `pergunta`, `texto`, `nota`). Join with `quizzes` (theme, mode) and `parametros_quiz` (score) for context:

```sql
-- Average and number of answers per question
SELECT n.pergunta, n.texto, round(avg(n.nota), 2) AS media, count(*) AS respostas
FROM avaliacoes_quiz_notas n JOIN avaliacoes_quiz a ON a.id = n.avaliacao_id
WHERE a.versao_questionario = 'exemplo-1'
GROUP BY n.pergunta, n.texto ORDER BY media;

-- Distribution of the ratings (0-5) per question
SELECT pergunta, nota, count(*) AS respostas FROM avaliacoes_quiz_notas GROUP BY pergunta, nota ORDER BY pergunta, nota;

-- Average per theme and mode
SELECT q.tema, q.modo, round(avg(n.nota), 2) AS media, count(DISTINCT a.id) AS avaliacoes
FROM avaliacoes_quiz a
JOIN quizzes q ON q.id = a.quiz_id
JOIN avaliacoes_quiz_notas n ON n.avaliacao_id = a.id
GROUP BY q.tema, q.modo ORDER BY media;

-- Comments, with the theme and the student's score in that quiz
SELECT a.created_at, q.tema, t.acertos, t.erros, a.comentario
FROM avaliacoes_quiz a
JOIN quizzes q ON q.id = a.quiz_id
LEFT JOIN parametros_quiz t ON t.id = q.tentativa_id
WHERE a.comentario IS NOT NULL ORDER BY a.created_at DESC;
```

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

respostas_quiz
  id, tentativa_id → parametros_quiz, usuario_id → usuarios, tema, subtema,
  pergunta, resposta_escolhida, resposta_correta, acertou (bool), referencias

feedbacks_desempenho
  id, usuario_id → usuarios, total_tentativas (cache key), conteudo (json), uso_tokens (json)

questoes
  id, codigo (unique content hash), tema, subtema, dificuldade (facil | intermediaria | dificil),
  objetivo_aprendizagem, caso_clinico, comando, alternativas (json: letra, texto, explicacao),
  gabarito (letter), justificativa, referencias (json), status (pendente_revisao | revisada |
  reprovada | arquivada), lote_id, posicao, gerada_em, bruto (json: original pipeline record)

questoes_irmas
  questao_id → questoes, irma_id → questoes, similaridade

quizzes
  id, usuario_id → usuarios, tema, modo (com_feedback | sem_feedback), finalizado_em,
  tentativa_id → parametros_quiz

quiz_questoes
  id, quiz_id → quizzes, questao_id → questoes, ordem, letra_escolhida, acertou, respondida_em

avaliacoes_quiz
  id, quiz_id → quizzes (unique), usuario_id → usuarios, versao_questionario, comentario

avaliacoes_quiz_notas
  id, avaliacao_id → avaliacoes_quiz, pergunta (question key), texto (question text when answered), nota (0-5)

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
