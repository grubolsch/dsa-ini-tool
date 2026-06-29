# CLAUDE.md — Turn Tracker for Pen & Paper RPG

A "for fun" visual turn-tracking tool for tabletop RPG combat. The headline feature is a
**fancy animated initiative line** that makes it instantly obvious whose turn it is, kept in
perfect sync between the Dungeon Master's control screen and read-only player screens.

> Status: **design locked, not yet implemented.** This file is the single source of truth for
> decisions. Anything marked **(confirm)** is my interpretation of an ambiguous part of the
> brief — correct it here and we adjust.

---

## 1. Tech stack (decided)

| Layer        | Choice                                                           |
|--------------|------------------------------------------------------------------|
| Backend      | **Symfony 7**, **PHP 8.4** (deps require ≥8.4.1)                                        |
| Database     | **MySQL 8** (Doctrine ORM + migrations)                          |
| Real-time    | **Mercure hub** (Server-Sent Events) — players subscribe by code |
| Frontend     | **React 18 + Vite + TypeScript**                                 |
| Animation    | **Framer Motion** (the initiative line, transitions, death FX)   |
| Icons        | **Font Awesome** (`@fortawesome/react-fontawesome` + free-solid, tree-shaken) |
| Styling      | Theme: **medieval fantasy** (see §9)                             |
| Testing      | **PHPUnit** (backend), Vitest + React Testing Library (frontend) |
| Container    | **Docker Compose** + **Makefile**                                |
| Auth         | **None** (proof of concept, explicitly out of scope)             |

### API style
Plain Symfony controllers returning JSON under `/api/*`. No API Platform (keeps it transparent
and easy to PHPUnit-test). DTOs + the Serializer component for request/response shaping.

---

## 2. Core domain model

### Persistent entities (saved templates — never mutated by a live fight)

- **Hero** — a party member.
  - `id`, `name`, `picture` (uploaded file path), `initiative` (base INI), timestamps.
  - **No LE on heroes** — heroes do not track health (decided). Only their INI varies per fight.
- **MonsterTemplate** — a reusable monster (only saved when "Save this monster template" is ticked).
  - `id`, `name`, `picture`, `initiative`, `le` (health), `description` (long text), timestamps.
- **Encounter** — a prepared fight.
  - `id`, `name` (**unique**), `atmospherePicture` (optional), `createdAt`, `updatedAt`.
  - Has a collection of **EncounterMonster** rows.
- **EncounterMonster** — a monster instance placed inside an encounter (the same template can be
  added multiple times → each row is its own combatant).
  - `id`, `encounter_id`, optional `monster_template_id` (null if ad-hoc/not saved),
    `name`, `picture`, `initiative`, `le`, `description`. Snapshot of stats at add-time so
    later template edits don't silently change a built encounter.

> Heroes belong to a single global **party**, reused across encounters (decided). "Manage party"
> = CRUD over `Hero`. Per fight, **only INI changes** for a hero; nothing else is mutated and
> heroes have no LE. **Future phase:** the party list may become account-bound (out of scope now).

### Live / transient entities (the running fight — keyed by the share code)

- **LiveEncounter** — created when "Start encounter" is pressed.
  - `id`, `code` (unique 8-char `[A-Z0-9]`), `encounter_id`, `round` (int, starts 1),
    `activeIndex` (whose turn), `phase` (`COMBAT` | `END_OF_ROUND`), `createdAt`.
- **Combatant** — one fighter in this live fight. **All per-fight mutations happen here, never on
  the template.**
  - `id`, `live_encounter_id`, `side` (`PARTY` | `ENEMIES`), `name`, `picture`,
    `initiative` (rolled, per-fight), `description`,
    `le`, `maxLe` (**enemies only**; null/unused for heroes — heroes have no health),
    `isDead` (enemy removed at LE 0), `isOutOfCombat` (hero greyed but still acts),
    `sortOrder`, `pendingInitiative` (see §5 re-ordering rule).
- **StatusEffect** — applied to one combatant.
  - `id`, `combatant_id`, `name`, `description`, `durationRounds`, `triggerAtRoundEnd` (bool),
    `groupTag` (nullable: `ALL_ENEMIES` | `ALL_HEROES` — for display wording, see §7).

Live state is **DB-backed** (decided): a DM refresh or a player joining by code rehydrates from
these rows. Every mutation re-publishes the full live state to the Mercure topic `encounter/{code}`.

---

## 3. Initiative & turn order rules

- Order within a round: **highest INI first**. Tie-break: **heroes (PARTY) act before enemies**.
  Secondary stable tie-break: `sortOrder` / id.
- **Start-of-encounter roll:** when starting, add a **random 1–6 to each combatant's INI**
  (independent roll per combatant). This is applied to the `Combatant.initiative` only —
  **the Hero/MonsterTemplate rows are never touched.** (decided)
- The **initiative line** renders combatants left→right in current turn order. The active one is
  the big circle; others are small.

---

## 4. Screen flow

### Home screen
Buttons:
1. **Start encounter** — disabled until an encounter is loaded **and** the party has ≥1 hero.
2. **Manage party** — CRUD heroes.
3. **Load encounter** — dropdown of saved encounters, **newest first**.
4. **Edit / Create encounter** — label is "Edit encounter" if one is loaded, else "Create encounter".

Plus a **CODE form**: enter an 8-char code → opens the **player view** of that live encounter.

When an encounter is loaded, show its **name and (atmosphere) picture** on the home screen.

### Manage party
Add/edit/delete heroes: name, picture upload, base INI. Persisted.

### Edit / Create encounter
- Unique encounter **name**, optional **atmosphere picture**.
- Add/edit **monsters** for this encounter: name, picture, INI, **LE (health)**, big
  **description/stats textarea**.
- Checkbox **"Save this monster template"** → also persists a `MonsterTemplate` for reuse.
- **Typeahead** to search existing `MonsterTemplate`s and add them; the **same monster can be
  added multiple times**.

### Start encounter → Live encounter screen
- Rolls INI (§3), creates `LiveEncounter` + `Combatant`s, shows the **8-char code** at the top.
- Two flavors, **same visual flow**:
  - **Player style** (joined via code): sees the line, the pictures, the atmosphere big picture,
    the **active character's INI**, and **active status effects (with duration)**. For enemy
    health they see a **qualitative band, never the number** (see §6.1). **No interaction.**
  - **DM style** (the session that started it): all the controls below.

---

## 5. DM controls (live encounter)

- **End of turn** → advance to next combatant in the line.
- **Edit active combatant's INI or LE** → mutates the `Combatant` only, not the template.
  - **INI re-order rule:** changing INI re-sorts the line **starting NEXT round**. A combatant
    whose INI changed must **not act again this round**. Implemented via `pendingInitiative`
    applied at round rollover; current-round position is frozen.
- **LE reaches 0 on an enemy** → removed from the line with a **dramatic disappear animation**
  (`isDead = true`). Heroes are not auto-removed (death checks are future work).
- **Mark "out of combat"** — **heroes only**. Picture goes grey, but the hero **still gets a turn**.
  Shows a **Resurrect** button to re-enable.
- **Add characters mid-fight** → modal, same form as add/edit monster; inserts a new `Combatant`.
- **Add status effect** (see §6/§7).

---

## 6.1 Enemy health display (players)

The DM sees the exact LE number. **Players never see the number** — only a qualitative band
derived from `le / maxLe` (decided):

| Band              | Condition (of max LE)        |
|-------------------|------------------------------|
| **Full health**   | no damage (`le == maxLe`)    |
| **Damaged**       | `> 50%` and below full       |
| **Heavily damaged** | `> 25%` and `<= 50%`       |
| **Almost defeated** | `> 0%` and `<= 25%`        |
| (removed)         | `0%` → enemy dies/disappears |

Heroes show no health at all.

## 6. Status effects

A status effect has: **name**, **description**, **duration in rounds**, and a checkbox
**"trigger at round end"**.

- Displayed as a **big bulleted list** on screen for the **active character**, with **rounds left**.
- At the **end of each round**: decrement every effect's duration by 1; **remove effects at 0**.

### Applying effects (DM picker)
- DM gets a list of **all combatants** and selects who to apply to.
- Two extra checkboxes: **(all enemies)** and **(all heroes)** to apply to a whole side quickly.
- **Layout:** the character picker is shown in **two columns** (heroes | enemies) for clarity,
  with the two group checkboxes also laid out in those two columns.

---

## 7. End-of-round screen

- Reaching the **end of the line** (advancing past the last combatant) ends the round and shows a
  **transition screen**, then the line **resets to the start** and `round++`.
- There is **one screen**, titled **"End of round"** (decided — not a separate per-turn screen):
  - Always shown briefly as the dramatic "new round" transition.
  - If any active effect has **"trigger at round end"**, that screen **prominently lists those
    effects and who they apply to**.
  - **Group wording:** if an effect was applied via **all enemies / all heroes**, display it as
    *"All enemies"* / *"All heroes"* rather than listing each character individually.
- Duration decrement/removal (§6) happens on leaving this screen.

---

## 8. Real-time sync (Mercure)

- Topic per fight: `encounter/{code}`.
- Players subscribe (SSE, read-only). DM mutations `POST /api/live/{code}/...` then publish the
  **full serialized live state**; all clients re-render from it (simple, race-free).
- Player join: `GET /api/live/{code}` to hydrate, then subscribe for updates.

---

## 9. Frontend & theme

- **Medieval fantasy** look: parchment textures, gold/iron trim, serif display font for headings,
  candle-glow accents. Tasteful, not garish.
- **The initiative line is the hero feature** — a long ornate rail; combatants are framed
  portrait medallions; the active one scales up with a glow; smooth Framer Motion layout
  transitions when order changes or a combatant dies.
- Animations: turn advance (slide along the rail), enemy death (shatter/fade + fall), round
  rollover (banner sweep), status-effect add (sigil pop).
- Smooth, identical motion in **both** DM and player styles.

---

## 10. Images / uploads (decided)

- DM uploads image files; stored on a **mounted Docker volume** (e.g. `var/uploads/`), served as
  static assets. Used for hero, monster, and atmosphere pictures.
- Basic validation (mime/size). Filenames randomized to avoid collisions.

---

## 10.1 Picture gallery (decided)

In addition to uploading a picture, a monster's picture can be chosen from a **gallery** of images
in `public/gallery` (e.g. pre-existing DSA creature portraits). The monster form shows an upload
input **and** a "Choose an image from the gallery" button that opens a searchable grid; clicking a
tile selects it.

- Supported extensions: **png, jpg, jpeg, bmp**.
- `.bmp` files are converted to **PNG on the fly** by `GET /api/gallery/image` so they render in any
  browser; png/jpg are served as-is. The chosen picture is stored as that image URL.
- Backend guards: basename-only (no path traversal), extension allow-list, file-must-exist.
- Applies to monster create/edit, monster templates, and mid-fight "add character".
- **Display:** gallery tiles and the picture preview use `object-fit: contain` (show the WHOLE
  portrait, no centered crop). Cropped circular portraits (line medallions, list thumbs, active
  portrait) are top-anchored (`object-position: center top`) so faces aren't cut off. Atmosphere
  banners stay `cover`.

## 10.2 Terminology & display refinements (decided)

- **UI wording:** combatants/monsters are called **"enemy"** in the UI. The data model keeps
  `MonsterTemplate`/`EncounterMonster`/`monsterTemplateId` etc. — only user-facing text changed.
- **Duplicate names:** combatants get a server-computed `displayName` (`"Goblin #1"`, `"Goblin #2"`)
  numbered by `sortOrder`, stable across deaths (see CONTRACT.md). Used everywhere a name renders.
- **Live status list:** shows the active combatant's effects ordered **soonest-to-expire first**,
  and **hides** effects flagged `triggerAtRoundEnd` (those appear only on the End-of-round screen).
- **Mid-fight add:** can load an enemy from the library (`monsterTemplateId`) and add several via a
  `quantity` field (default 1). The shared enemy form clears after a successful save.
- **End-of-round screen:** title + effect text are dark (was unreadable gold).

## 11. Infrastructure

### Docker Compose services
- `php` (PHP 8.3-FPM + Symfony), `nginx`, `mysql:8`, `mercure`, `node` (Vite dev / build).
- Volumes: MySQL data, uploads.

### Makefile targets (planned)
- `make up` / `make down` — start/stop stack.
- `make install` — composer + npm install.
- `make migrate` — run Doctrine migrations.
- `make seed` — demo heroes/monsters/encounter.
- `make test` — PHPUnit (backend) + frontend tests.
- `make test-backend` / `make test-frontend`.
- `make build-front` — production React build.
- `make shell` — shell into php container.

---

## 11.1 Running locally (verified)

```
make up        # build + start all 5 containers
make migrate   # create schema (MySQL)
make seed      # demo party + Goblin Ambush encounter
make test      # backend PHPUnit (32) + frontend vitest (6)
```

URLs:
- **App (React/Vite):** http://localhost:5173
- **API + Mercure (nginx):** http://localhost:8080  (`/api/*`, `/.well-known/mercure`, `/uploads`)
- **MySQL host port:** `3307` (remapped from 3306 to avoid clashing with a local MySQL).
- **Mercure (direct, debug):** http://localhost:8081

Integration notes (fixes applied during wire-up):
- **PHP 8.4** in the container (locked deps require ≥8.4.1).
- nginx proxies Mercure via Docker DNS (`resolver 127.0.0.11` + variable `proxy_pass`) so it
  starts even if the hub is briefly down.
- Mercure `cors_origins` takes space-separated origins (not a single quoted string).
- Tests run on **sqlite** (`make test-backend` sets `DATABASE_URL=sqlite…` + `APP_ENV=test`),
  keeping them DB-light and avoiding MySQL grant issues on a `_test` database.

## 12. Testing strategy

- **PHPUnit** (required by brief):
  - Initiative ordering + tie-break (heroes before enemies on equal INI).
  - Start-of-encounter roll applies 1–6 and **does not** mutate templates.
  - Turn advance & round rollover; INI change re-orders next round only (no double turn).
  - Enemy at LE 0 removed; hero out-of-combat still in order.
  - Status-effect duration decrement/removal at round end; group-tag wording.
  - Encounter name uniqueness; "save template" persistence; add-same-monster-twice.
- Frontend: unit tests for the line ordering/active-index logic and the code-join flow.

---

## 13. Resolved clarifications

All previously open items are now decided:

1. ✅ **Party** is a single global list reused across encounters; only INI changes per fight;
   heroes have **no LE**. May become account-bound in a future phase.
2. ✅ One **"End of round"** screen (no separate per-turn screen).
3. ✅ Player view also shows **active status effects + duration**, and enemy health as a
   **qualitative band** (§6.1), never the raw number.

---

## 14. Build order (once confirmed)

1. Docker + Makefile + Symfony skeleton + MySQL + Mercure wired up.
2. Entities + migrations + seed.
3. Backend API: party, monsters/templates, encounters, live encounter + mutations + Mercure.
4. PHPUnit for domain rules.
5. React app: home → manage/load/edit → live screen (DM + player), initiative line, animations.
6. Polish theme + animations + end-to-end smoke.
