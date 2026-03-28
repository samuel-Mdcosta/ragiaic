# ♟️ ChessTutor AI

> **AI-powered chess performance analyzer.** Upload your PGN games, and the system uses Stockfish + Gemini to identify your patterns of mistakes, classify blunders, and generate personalized coaching feedback grounded in real engine data.
> 
<img width="1919" height="1028" alt="Captura de tela 2026-03-27 223609" src="https://github.com/user-attachments/assets/c1b8943f-0f48-4e83-ad8d-58e3aefe0e0d" />

[![Python](https://img.shields.io/badge/Python-3.11+-3776AB?style=flat&logo=python&logoColor=white)](https://python.org)
[![FastAPI](https://img.shields.io/badge/FastAPI-async-009688?style=flat&logo=fastapi&logoColor=white)](https://fastapi.tiangolo.com)
[![MongoDB](https://img.shields.io/badge/MongoDB-Atlas-47A248?style=flat&logo=mongodb&logoColor=white)](https://mongodb.com/atlas)
[![Gemini](https://img.shields.io/badge/Google-Gemini_API-4285F4?style=flat&logo=google&logoColor=white)](https://ai.google.dev)
[![License](https://img.shields.io/badge/license-MIT-green?style=flat)](LICENSE)

---

## What it does

Most chess players know they blunder — but not *why* or *how often*. ChessTutor AI processes up to **20 games at once** from Chess.com or Lichess PGN exports and returns:

- A classification of every move (blunder, mistake, inaccuracy, good)
- The centipawn loss per move, calculated by Stockfish
- The best alternative move for each critical position
- A coaching report written by Gemini, grounded in real engine data — not generic advice

The result: actionable feedback about your specific weaknesses, referencing actual positions from your games.

---

## Architecture

```
PGN input (1–20 games)
        │
        ▼
┌───────────────────┐
│   PGN Parser      │  python-chess — validates and extracts moves
└────────┬──────────┘
         │
         ▼
┌───────────────────┐
│  Stockfish Engine │  runs locally or via API — evaluates every ply
│  (engine pool)    │  outputs: best_move, cp_loss, classification
└────────┬──────────┘
         │
         ▼
┌───────────────────┐
│  Prompt Builder   │  structures blunders, mistakes & best moves
│  (single_analysis)│  into a grounded LLM context
└────────┬──────────┘
         │
         ▼
┌───────────────────┐
│   Gemini API      │  generates coaching report in natural language
│   (app/ia/)       │  moves are converted from UCI to algebraic
└────────┬──────────┘
         │
         ▼
┌───────────────────┐
│   MongoDB Atlas   │  persists games, analyses, user history
│   (async Motor)   │
└───────────────────┘
```

---

## Tech stack

| Layer | Technology |
|---|---|
| API framework | FastAPI (async) |
| Chess engine | Stockfish (local pool + API fallback) |
| PGN parsing | python-chess |
| AI / LLM | Google Gemini API |
| Database | MongoDB Atlas via Motor (async) |
| Auth | JWT-based user management |
| Deploy | Docker + GitHub |

---

## Project structure

```
chessTutor-AI/
├── app/
│   ├── main.py              # FastAPI entry point, CORS, lifespan, routes
│   ├── core/
│   │   ├── analyze.py       # Core analysis orchestration
│   │   ├── pgnGetter.py     # PGN fetching helpers
│   │   └── stockfish.py     # Stockfish engine pool
│   ├── database/
│   │   └── mongo.py         # MongoDB async client
│   ├── ia/
│   │   ├── client.py        # Gemini API client
│   │   └── prompts.py       # LLM prompt templates
│   ├── models/
│   │   ├── gameModel.py
│   │   ├── pgn_request.py
│   │   └── userModel.py
│   └── service/
│       ├── game_analysis.py    # Full multi-game analysis pipeline
│       ├── single_analysis.py  # Builds Gemini prompt from Stockfish data
│       ├── tutorService.py     # Coaching logic with caching
│       ├── gameService.py
│       ├── pgn_parser.py
│       └── userService.py
├── engineStockfish/
├── models.py
├── requirements.txt
└── CLAUDE.md
```

---

## Getting started

### Prerequisites

- Python 3.11+
- [Stockfish](https://stockfishchess.org/download/) installed locally
- MongoDB Atlas cluster (or local MongoDB)
- Google Gemini API key

### Installation

```bash
# Clone the repository
git clone https://github.com/samuel-Mdcosta/chessTutor-AI.git
cd chessTutor-AI

# Create and activate virtual environment
python -m venv .venv
source .venv/bin/activate  # Windows: .venv\Scripts\activate

# Install dependencies
pip install -r requirements.txt
```

### Environment variables

Create a `.env` file in the root directory:

```env
GEMINI_API_KEY=your_gemini_api_key
MONGODB_URI=your_mongodb_atlas_connection_string
STOCKFISH_PATH=/usr/local/bin/stockfish   # adjust to your local path
SECRET_KEY=your_jwt_secret_key
```

### Running

```bash
uvicorn app.main:app --reload
```

API will be available at `http://localhost:8000`. Interactive docs at `http://localhost:8000/docs`.

---

## Key endpoint

```http
POST /analyze
Content-Type: application/json

{
  "pgn": "<your PGN string with 1–20 games>"
}
```

**Response includes:**
- Per-move analysis: `move_played`, `best_move`, `cp_loss`, `classification`, `evaluation`
- Gemini coaching report referencing actual blunders and best alternatives
- Aggregated stats: blunder rate, average centipawn loss, most common mistake type

---

## Design decisions

**Why ground Gemini in Stockfish data?**
Generic chess advice ("control the center", "develop your pieces") is everywhere. The value here is that Gemini only receives *real engine output* — actual moves, actual centipawn losses, actual best alternatives from your specific games. The coaching is contextual, not generic.

**Why an engine pool?**
Stockfish startup has overhead. Keeping a pool of pre-initialized engines makes batch analysis of 20 games significantly faster.

**Why async throughout?**
Both MongoDB (Motor) and the Gemini API calls are I/O-bound. Using FastAPI with full async support means the server handles multiple analysis requests concurrently without blocking.

---

## Frontend

The React frontend for this project lives at:
[github.com/samuel-Mdcosta/frontChessTutorAi](https://github.com/samuel-Mdcosta/frontChessTutorAi)

Live demo: [chess-tutor-ai-nine.vercel.app](https://chess-tutor-ai-nine.vercel.app)

---

## Author

**Samuel M. Costa** — Backend Developer | AI & LLMs

- LinkedIn: [linkedin.com/in/samuelmdcosta](https://linkedin.com/in/samuelmdcosta)
- Email: costadev19@gmail.com
- GitHub: [github.com/samuel-Mdcosta](https://github.com/samuel-Mdcosta)
