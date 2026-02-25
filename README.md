# 🎧 The Album Fights (Album Duel Engine)

![Docker](https://img.shields.io/badge/docker-ready-blue)
![Docker Pulls](https://img.shields.io/docker/pulls/earlofburl/album-fights)
![Status](https://img.shields.io/badge/status-active-success)

A web app for ranking albums with an Elo system via 1v1 duels.
Inspired by Flickchart, but focused on albums.

---

## ✨ Feature Overview

### Core Duel Engine
- **Elo-based ranking** with win/loss/draw support.
- **Weighted matchmaking categories** (Top 25 / Top 50 / Top 100 / high playcount / never-dueled / random).
- **Configurable category weights** in Settings (normalized to 100%).
- **Session-safe duel handling** (current duel is kept stable until voted).
- **Quick actions in duel view**: send album to queue or delete it.
- **Top 20 live table** directly on the duel page.

### Metadata & artwork
- Album metadata + cover caching in `/cache`.
- Multi-source metadata pipeline:
  1. Last.fm (primary when configured)
  2. ListenBrainz/MusicBrainz + Cover Art Archive (fallback/enrichment)
  3. iTunes (final fallback)
- Cached metadata is refreshed strategically (e.g. enrichment for missing year/genres).

### Import & maintenance
- **Sync playcounts** from Last.fm or ListenBrainz top albums (up to Top 1000).
- **Candidate import via API**
  - Last 400 scrobbles/listens mode
  - Top albums mode (Top 100 / 200 / 500 / 1000)
- **CSV upload import** with preview and selectable rows.
- **Bundled `1000_best_albums.csv` one-click preview**.
- Import destination per album or in bulk:
  - Duel DB (`elo_state.csv`)
  - Listening Queue (`listening_queue.csv`)
- Import threshold: minimum plays configurable in Settings.

### Queue workflow
- Dedicated queue page for parked albums.
- Restore queued albums back into duel DB.
- Delete queued albums permanently.

### Analysis & list views
- **Stats dashboard** with:
  - Top 10 / Flop 10
  - battle-hardened veterans
  - hidden gems / disappointments
  - top artists (power score)
  - best/worst genres and decades (derived from cached metadata)
- **The List page**:
  - sortable columns (Artist/Album/Elo/Duels/Playcount)
  - pagination (100 per page)
  - CSV export of current sorting

### AI features
- **AI Nerd comments** every 25 duels (optional).
- **Boot Camp page** for on-demand AI assessment of your current Top 50.
- Supports **OpenAI** and **Gemini**, selectable in Settings.

---

## 📸 Interface Preview

### 🥊 Duel Experience

<p align="center">
  <img src="docs/screenshots/duel.png" width="47%" style="border-radius:12px; box-shadow:0 6px 18px rgba(0,0,0,0.12); margin:10px;">
  <img src="docs/screenshots/rankings.png" width="47%" style="border-radius:12px; box-shadow:0 6px 18px rgba(0,0,0,0.12); margin:10px;">
</p>

### ⚙️ Control Center

<p align="center">
  <img src="docs/screenshots/settings.png" width="47%" style="border-radius:12px; box-shadow:0 6px 18px rgba(0,0,0,0.12); margin:10px;">
  <img src="docs/screenshots/import.png" width="47%" style="border-radius:12px; box-shadow:0 6px 18px rgba(0,0,0,0.12); margin:10px;">
</p>

---

## 🚀 Quick Start (Docker)

### 1) `docker-compose.yml`

```yaml
version: '3.8'

services:
  album-fights:
    image: earlofburl/album-fights:latest
    container_name: album-fights
    ports:
      - "8989:80"
    volumes:
      - ./data:/var/www/html/data
      - ./cache:/var/www/html/cache
    restart: unless-stopped
```

### 2) Start

```bash
docker-compose up -d
```

### 3) Open

```text
http://localhost:8989
```

---

## ⚙️ Configuration

Use **Settings** in the UI for all runtime configuration.

### Integrations
- Last.fm API key
- ListenBrainz API key + optional default username
- OpenAI API key + model
- Gemini API key + model

### Behavior toggles
- Enable/disable AI nerd comments
- Active AI provider
- Duel matchmaking category weights
- Import minimum plays threshold

> Settings are persisted in `data/settings.json`.

---

## 📂 Data & Persistence

Mounted directories:

```text
/data   -> CSV state + settings + bootcamp history + optional perf log
/cache  -> cached metadata JSON + downloaded cover images
```

Main files:

```text
data/elo_state.csv          # Duel database
data/listening_queue.csv    # Queue database
data/settings.json          # App settings
data/bootcamp_last.json     # Last bootcamp output + short history
```

Backup/migration: copy `/data` and `/cache`.

---

## 🧪 Dev: performance logging (optional)

Enable lightweight request timing logs:

```bash
APP_ENV=dev DEV_PERF_LOG=1
```

Output file:

```text
data/dev_perf.log
```

Disabled by default.

---

## 📜 License

MIT License.
