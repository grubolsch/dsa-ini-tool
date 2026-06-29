# API CONTRACT — Turn Tracker

Shared contract between Symfony backend and React frontend. Both sides MUST conform to this.
Read alongside `CLAUDE.md` (domain rules). All requests/responses are JSON.
Base path: `/api`. Frontend dev server proxies `/api` and `/.well-known/mercure` to backend.

---

## Conventions

- Timestamps: ISO-8601 strings.
- IDs: integers.
- `side`: `"PARTY" | "ENEMIES"`.
- `phase`: `"COMBAT" | "END_OF_ROUND"`.
- `groupTag`: `null | "ALL_ENEMIES" | "ALL_HEROES"`.
- Pictures: returned as a URL path string (e.g. `/uploads/ab12cd.png`) or `null`.
- Errors: HTTP 4xx/5xx with `{ "error": "message" }`.

---

## Resource shapes

### Hero
```json
{ "id": 1, "name": "Aria", "picture": "/uploads/x.png", "initiative": 12 }
```
Heroes have NO health.

### MonsterTemplate
```json
{ "id": 1, "name": "Goblin", "picture": "/uploads/g.png", "initiative": 8, "le": 15, "description": "..." }
```

### Encounter (summary, used in list)
```json
{ "id": 1, "name": "Goblin Ambush", "atmospherePicture": "/uploads/a.png", "createdAt": "...", "updatedAt": "...", "monsterCount": 4 }
```

### Encounter (detail)
```json
{
  "id": 1, "name": "Goblin Ambush", "atmospherePicture": "/uploads/a.png",
  "createdAt": "...", "updatedAt": "...",
  "monsters": [
    { "id": 10, "name": "Goblin", "picture": "/uploads/g.png", "initiative": 8, "le": 15, "description": "...", "monsterTemplateId": 1 }
  ]
}
```
`monsters[]` are `EncounterMonster` rows (snapshots). `monsterTemplateId` may be null.

### Combatant (live)
```json
{
  "id": 100, "side": "ENEMIES", "name": "Goblin", "displayName": "Goblin #1", "picture": "/uploads/g.png",
  "initiative": 11, "le": 15, "maxLe": 15, "description": "...",
  "isDead": false, "isOutOfCombat": false, "sortOrder": 0,
  "iniChangedThisRound": false,
  "healthBand": "FULL",            // enemies only; null for heroes
  "statusEffects": [ /* StatusEffect[] */ ]
}
```
`healthBand`: `"FULL" | "DAMAGED" | "HEAVILY_DAMAGED" | "ALMOST_DEFEATED" | null`.
For PARTY combatants: `le`, `maxLe`, `healthBand` are `null`.

`displayName`: equals `name` when unique; when 2+ combatants share the same `name`, each gets a
`" #n"` suffix numbered by `sortOrder` ascending (1-based), e.g. `"Goblin #1"`, `"Goblin #2"`.
Numbering includes dead combatants so labels stay stable across a fight.

### StatusEffect (live)
```json
{ "id": 5, "name": "Poisoned", "description": "1 dmg/round", "durationRounds": 3, "triggerAtRoundEnd": true, "groupTag": "ALL_ENEMIES" }
```

### LiveEncounter (full state — the broadcast payload)
```json
{
  "code": "A1B2C3D4",
  "encounter": { "id": 1, "name": "Goblin Ambush", "atmospherePicture": "/uploads/a.png" },
  "round": 1,
  "activeIndex": 0,                 // index into ordered living combatants for THIS round
  "phase": "COMBAT",
  "order": [100, 101, 102],         // combatant ids in this round's turn order (living only)
  "activeCombatantId": 100,
  "combatants": [ /* Combatant[] — ALL incl. dead, full data for DM */ ],
  "roundEndEffects": [              // populated when phase == END_OF_ROUND
    { "label": "All enemies", "name": "Poisoned", "description": "...", "durationRounds": 2 }
  ]
}
```
`order` is the per-round frozen order (does not change mid-round). `activeIndex`/`activeCombatantId`
track whose turn it is. Backend computes `healthBand` for enemies and strips raw `le`/`maxLe` for
the **player view** (see auth note below).

> **Player vs DM payload:** same endpoint shape. For the player SSE/topic, backend OMITS raw `le`,
> `maxLe`, and `description` of enemies (keeps `healthBand`). DM payload includes everything.
> Differentiation: DM uses `GET /api/live/{code}?dm=1` and DM-only mutation routes; players use
> `GET /api/live/{code}` (sanitized). Mercure publishes to two topics (see below).

---

## REST endpoints

### Gallery (selectable picture library)
- `GET /api/gallery` → `GalleryImage[]`
  ```json
  [ { "name": "cdvorf1_330.bmp", "url": "/api/gallery/image?name=cdvorf1_330.bmp" } ]
  ```
  Lists images in `public/gallery` with extension `png|jpg|jpeg|bmp`, sorted by name.
- `GET /api/gallery/image?name=<file>` → image bytes. `.bmp` is converted to **PNG** on the fly
  (browsers render BMP inconsistently); `.png/.jpg/.jpeg` are streamed as-is. Cached 24h.
  Path-traversal and unknown files → 404.

**Choosing a gallery image for a monster:** the monster create/edit endpoints accept a form field
`galleryImage` = the gallery filename, as an alternative to uploading a `picture` file. If both are
present, the uploaded file wins. The stored `picture` becomes the gallery image URL. Applies to:
`POST/PUT /api/encounters/{id}/monsters`, `POST /api/monster-templates`, `PUT /api/monster-templates/{id}`,
and `POST /api/live/{code}/combatants`.

### Party (Heroes)
- `GET    /api/heroes` → `Hero[]`
- `POST   /api/heroes` (multipart: `name`, `initiative`, `picture?` file) → `Hero`
- `PUT    /api/heroes/{id}` (multipart, same fields) → `Hero`
- `DELETE /api/heroes/{id}` → 204

### Monster templates
- `GET    /api/monster-templates?q=goblin` → `MonsterTemplate[]` (typeahead; `q` optional)
- `POST   /api/monster-templates` (multipart: `name`, `initiative`, `le`, `description?`, `picture?`) → `MonsterTemplate`
- `PUT    /api/monster-templates/{id}` → `MonsterTemplate`
- `DELETE /api/monster-templates/{id}` → 204

### Encounters
- `GET    /api/encounters` → `Encounter[]` (summary, **newest first**)
- `GET    /api/encounters/{id}` → Encounter detail
- `POST   /api/encounters` (multipart: `name`, `atmospherePicture?`) → Encounter detail
  - 409 `{ "error": "Encounter name already exists" }` on duplicate name.
- `PUT    /api/encounters/{id}` (multipart: `name?`, `atmospherePicture?`) → Encounter detail
- `DELETE /api/encounters/{id}` → 204
- `POST   /api/encounters/{id}/monsters` (multipart) → EncounterMonster
  - Body: `name`, `initiative`, `le`, `description?`, `picture?` file, `saveTemplate?` (bool),
    `monsterTemplateId?` (when adding from existing template — copies snapshot).
- `PUT    /api/encounters/{id}/monsters/{monsterId}` (multipart) → EncounterMonster
- `DELETE /api/encounters/{id}/monsters/{monsterId}` → 204

### Live encounter
- `POST   /api/encounters/{id}/start` → LiveEncounter (DM payload)
  - Creates LiveEncounter + Combatants (heroes + encounter monsters), rolls +1..6 INI per combatant,
    generates unique 8-char code, builds round-1 order. Returns full DM state.
- `GET    /api/live/{code}` → LiveEncounter (PLAYER payload, sanitized)
- `GET    /api/live/{code}?dm=1` → LiveEncounter (DM payload, full)

#### DM mutations (all return updated DM LiveEncounter AND publish to both topics)
- `POST /api/live/{code}/next-turn` → advance active. If past last combatant → `phase=END_OF_ROUND`.
- `POST /api/live/{code}/next-round` → leave END_OF_ROUND: decrement status durations, drop 0s,
  apply pending INI, rebuild order, `round++`, `activeIndex=0`, `phase=COMBAT`.
- `PATCH /api/live/{code}/combatants/{cid}` body `{ "initiative"?: int, "le"?: int }`
  - INI change: stored as pending, applied next round; combatant flagged `iniChangedThisRound`,
    does NOT act again this round. LE change: if enemy `le<=0` → `isDead=true`, removed from order.
- `POST /api/live/{code}/combatants/{cid}/out-of-combat` → heroes only; `isOutOfCombat=true`.
- `POST /api/live/{code}/combatants/{cid}/resurrect` → `isOutOfCombat=false`.
- `POST /api/live/{code}/combatants` (multipart) → add a combatant mid-fight (same fields as encounter
  monster add; rolls INI +1..6; inserted, plays next round per INI rule). Returns state.
  - Also accepts `monsterTemplateId` (load defaults from the monster library; explicit fields override)
    and `quantity` (default `1`) to add several at once. Each created combatant rolls its own INI;
    duplicate names are auto-numbered via `displayName`.
- `POST /api/live/{code}/status-effects` body:
  ```json
  { "name": "Poisoned", "description": "...", "durationRounds": 3, "triggerAtRoundEnd": true,
    "targets": { "combatantIds": [100,101], "allEnemies": false, "allHeroes": false } }
  ```
  Applies to each resolved target. If `allEnemies`/`allHeroes`, applied effects carry the matching
  `groupTag` for round-end wording.
- `DELETE /api/live/{code}/status-effects/{seId}` → remove one effect. Returns state.

---

## Mercure

- Hub URL (browser): `/.well-known/mercure` (proxied).
- Topics:
  - `encounter/{code}/dm`    — full DM payload.
  - `encounter/{code}/player` — sanitized player payload.
- After every mutation, backend publishes the freshly serialized state to BOTH topics.
- Frontend: DM subscribes to `…/dm`, player subscribes to `…/player`. Initial hydrate via REST,
  then live updates via SSE replace state wholesale.

---

## Health band derivation (enemies)

Given `le` (current), `maxLe`:
- `le >= maxLe`           → `FULL`
- `le > 0.5*maxLe`        → `DAMAGED`
- `le > 0.25*maxLe`       → `HEAVILY_DAMAGED`
- `le > 0`                → `ALMOST_DEFEATED`
- `le <= 0`               → dead (removed)

Player labels (frontend): FULL→"Full health", DAMAGED→"Damaged",
HEAVILY_DAMAGED→"Heavily damaged", ALMOST_DEFEATED→"Almost defeated".
