# dsa-ini-tool

A "for fun" **initiative / turn tracker** for pen-and-paper RPG combat (built with the DSA —
*Das Schwarze Auge* — style of play in mind). The centrepiece is a fancy animated **initiative
line**: a medieval-fantasy rail of character portraits where the active combatant grows large and
glows, so everyone at the table can instantly see whose turn it is.

A Dungeon Master drives an encounter on their screen; players can join a read-only view by entering
an 8-character code, and both stay in sync in real time. On top of the visual aid it handles the
bookkeeping: initiative order, health (LE), and status effects.

### Features
- **Initiative line** with smooth turn/round transitions and a dramatic enemy-death animation.
- **DM vs player views**, kept in sync live over [Mercure](https://mercure.rocks) (SSE). Players
  see qualitative enemy health ("Damaged", "Almost defeated") rather than exact numbers.
- **Party & encounter management** — heroes, enemies, reusable enemy templates, atmosphere images.
- **Picture gallery** — pick a portrait from `backend/public/gallery` (BMP is converted to PNG on
  the fly) or upload your own.
- **Status effects** with durations, round-end triggers, and quick "all heroes / all enemies"
  targeting; an end-of-round summary screen.
- Per-fight initiative roll (+1–6) and live LE/INI edits that **never mutate** the saved templates.

### Tech stack
Symfony 7 (PHP 8.4) · MySQL 8 · Mercure · React 18 + Vite + TypeScript · Framer Motion ·
Font Awesome · Docker Compose. Tested with PHPUnit and Vitest.

> Design decisions live in [`CLAUDE.md`](CLAUDE.md); the REST/Mercure contract is in
> [`CONTRACT.md`](CONTRACT.md).

---

## Setup & run

### Prerequisites
- Docker + Docker Compose
- `make`

Everything runs in containers — no local PHP/Node/MySQL needed.

### First run
```bash
make up        # build images and start all services (php, nginx, mysql, mercure, node)
make migrate   # create the database schema
make seed      # load demo heroes + a sample "Goblin Ambush" encounter
```

Then open:

| What                         | URL                                            |
|------------------------------|------------------------------------------------|
| **App (web UI)**             | http://localhost:5173                          |
| API + Mercure (via nginx)    | http://localhost:8080                          |
| Mercure hub (debug)          | http://localhost:8081                          |
| MySQL (host port)            | `localhost:3307` (db `turntracker`, `app`/`app`) |

> The Node container installs npm deps and starts the Vite dev server on first `make up`, so the
> very first start takes a little longer.

### Try it
1. **Manage party** → add a hero or two (the demo seed already has four).
2. **Load encounter** → pick "Goblin Ambush" (or create your own).
3. **Start encounter** → you land on the DM screen with an 8-character code at the top.
4. Open the app in another tab/window, enter that code in the join form → read-only **player view**.
5. Drive turns, edit LE/INI, and add status effects from the DM tab; the player view follows live.

### Tests
```bash
make test            # backend (PHPUnit) + frontend (Vitest)
make test-backend    # PHPUnit only (runs against SQLite)
make test-frontend   # Vitest only
```

### Common tasks
```bash
make down            # stop everything
make logs            # tail logs
make shell           # shell into the PHP container
make reset-db        # drop, recreate, migrate and re-seed the database
make build-front     # production build of the React app
make help            # list all targets
```

---

## Notes
- **Gallery images** are not committed (the sample BMP portraits are excluded via `.gitignore`).
  Drop your own `.png` / `.jpg` / `.bmp` files into `backend/public/gallery/` and they'll appear in
  the in-app picker.
- **No authentication** — this is a proof of concept. `backend/.env` ships only non-sensitive dev
  defaults; set real values via `.env.local` (gitignored) before any real deployment.
- The host MySQL port is `3307` to avoid clashing with a local MySQL on `3306`; containers still
  talk to MySQL on `3306` over the internal Docker network.
