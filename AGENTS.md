# Agent Guidance for Album Fights

## Project Context

Album Fights is a **PHP web application** (no Composer, no framework) for ranking music albums via Elo-based 1v1 duels. It also runs as a Docker container and as a packaged desktop app (Electron / AppImage / Windows).

---

## Build & Run

There is **no build step** for the PHP side. To run locally:

```bash
# Docker (recommended)
docker-compose up -d
# open http://localhost:8989

# Or any PHP-enabled web server pointing at the project root
php -S localhost:8080   # if PHP CLI is available
```

For the desktop build, see the separate Electron wrapper repo (not in this codebase).

---

## Testing

There is **no test suite yet**. Before adding one, prefer keeping business logic in `src/Service/*` so it can be unit-tested later without touching HTML.

Manual verification checklist after changes:
1. Open the **Duel** page (`index.php`) and cast a vote.
2. Check the **List** page (`list.php`) sorting and pagination.
3. Run an **Import** preview from at least one source.
4. If metadata was touched, verify a cover loads via `serve_image.php`.

---

## Code Conventions

- **Namespace**: `App\` (mapped to `src/` via `includes/autoload.php`)
- **Strict types**: Every PHP file starts with `<?php declare(strict_types=1);`
- **Entry points**: Thin controllers in the project root (`index.php`, `list.php`, …). They must:
  - `require_once 'includes/config.php'` first
  - Use `Security::get*()` to read request data
  - Call `Security::requirePost()` for any mutating action
  - Include `$csrfField = Security::csrfField()` and echo it into every `<form method="POST">`
  - End with `require __DIR__ . '/templates/{page}.php'`
- **Templates**: No business logic, only loops/conditionals and `htmlspecialchars()`.
- **Services**: Stateless where possible; dependencies injected via constructor.
- **Repositories**: Only layer allowed to touch the filesystem directly.

---

## Critical Files

| File | Purpose |
|------|---------|
| `includes/autoload.php` | PSR-4 autoloader for `App\` namespace |
| `includes/config.php` | Session bootstrap; creates `Config::get()` singleton |
| `src/Core/Config.php` | Path resolution (web / Docker / desktop), settings I/O |
| `src/Core/Security.php` | CSRF tokens, typed input getters |
| `src/Utils/CsvHelper.php` | Atomic CSV writes with `.bak1/2/3` rotation |
| `src/Repository/AlbumRepository.php` | All CSV data access for albums and queue |
| `src/Service/EloService.php` | Matchmaking + Elo math |
| `src/Service/MetadataService.php` | Multi-source album metadata fetching |

---

## Common Pitfalls

1. **Forgetting CSRF**: Any new POST form must include `<?= $csrfField ?>` and the action handler must call `Security::requirePost()`.
2. **Breaking desktop paths**: `Config::resolvePaths()` relies on env vars (`APP_USER_DATA_PATH`, `APPDATA`, `FLATPAK_ID`). Do not hardcode `data/` or `cache/` paths elsewhere.
3. **Corrupting CSV**: Never write CSV directly. Use `CsvHelper::write()` (atomic + backup rotation) or `AlbumRepository::saveElo()` / `saveQueue()`.
4. **Image path traversal**: `serve_image.php` and `cover.php` must validate filenames with a strict regex and only read from `Config::get()->getCacheDir()`.
5. **Duplicating API logic**: Subsonic, Last.fm, ListenBrainz logic lives in `SubsonicClient`, `HttpClient`, and `ImportService`. Do not add new inline API calls in entry points.

---

## Data Formats

### `data/elo_state.csv` / `data/listening_queue.csv`
```csv
Artist,Album,Elo,Duels,Playcount,Wins,Losses,Ratio
```

### `data/settings.json`
```json
{
  "lastfm_api_key": "...",
  "listenbrainz_api_key": "...",
  "listenbrainz_username": "...",
  "subsonic_base_url": "...",
  "subsonic_username": "...",
  "subsonic_password": "...",
  "gemini_api_key": "...",
  "openai_api_key": "...",
  "ai_provider": "gemini",
  "gemini_model": "gemini-3-flash-preview",
  "openai_model": "gpt-4o-mini",
  "nerd_comments_enabled": true,
  "import_min_plays": 8,
  "tag_blacklist": [],
  "duel_category_weights": {
    "top_25_vs": 20,
    "top_50_vs": 20,
    "top_100_vs": 20,
    "playcount_gt_20": 15,
    "duel_counter_zero": 15,
    "random": 10
  }
}
```

### Cache file (`cache/album_<md5>.json`)
```json
{
  "summary": "...",
  "local_image": "serve_image.php?file=album_....jpg",
  "url": "...",
  "genres": ["Rock", "Alternative"],
  "year": "1997",
  "tracks": ["..."],
  "full_data_fetched": true,
  "metadata_source": "lastfm",
  "refresh_attempted_at": 1234567890
}
```

---

## Environment Variables

| Variable | Effect |
|----------|--------|
| `APP_ENV=dev` + `DEV_PERF_LOG=1` | Writes `data/dev_perf.log` with request timings |
| `APP_USER_DATA_PATH` | Desktop (Linux AppImage / Mac) data path override |
| `APPDATA` / `LOCALAPPDATA` | Windows data / cache path override |
| `FLATPAK_ID` | Linux Flatpak path override |
| `LASTFM_API_KEY` | Default API key baked into settings |
| `LISTENBRAINZ_API_KEY` | Default API key baked into settings |
| `SUBSONIC_*` | Default Subsonic credentials baked into settings |
| `GEMINI_API_KEY` / `OPENAI_API_KEY` | Default AI keys baked into settings |

---

## Preferred Change Patterns

- **New page**: Add root `{page}.php` + `templates/{page}.php`.
- **New business rule**: Add a method to the appropriate `Service` class, call it from the entry point.
- **New data source**: Add a method to `ImportService` or `MetadataService`, reuse `HttpClient`.
- **UI-only change**: Edit the relevant `templates/*.php` file.
- **Security fix**: Start in `Security.php`, then update all forms in `templates/`.
