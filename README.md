# 🎧 The Album Fights

### *Album Duel Engine*

![Docker](https://img.shields.io/badge/docker-ready-blue)
![License](https://img.shields.io/badge/license-MIT-green) ![AI
Powered](https://img.shields.io/badge/AI-powered-purple)
![Status](https://img.shields.io/badge/status-active-success)

A web-based, Elo-driven application that helps you definitively rank
your favorite music albums by pitting them against each other in 1v1
duels.

Inspired by Flickchart --- but built for music nerds.

------------------------------------------------------------------------

## ✨ Features

### 🧮 Elo Rating System

Albums gain or lose points based on who they beat or lose to,
mathematically sorting your taste over time.

### 🥊 Tiered Matchmaking

Automatically forces albums in similar brackets (Top 20, Top 50, etc.)
to duel, preventing ranking stagnation.

### 🎵 Last.fm Integration

-   Fetch your recent scrobbles\
-   Import your Top 1000 albums\
-   Sync live play counts

### 🤖 AI Music Snob

Connect **OpenAI** or **Google Gemini**.\
Every 25 duels, a highly opinionated AI music critic will: - Analyze
your recent picks\
- Roast (or praise) your taste

### 🪖 The Boot Camp

Generate an on-demand, comprehensive AI assessment of your current Top
50 albums.

### 📥 CSV Import

Easily upload and import existing album lists.

------------------------------------------------------------------------

## 🚀 Quick Start (Docker)

### 1️⃣ Clone the repository

``` bash
git clone https://github.com/yourusername/album-fights.git
cd album-fights
```

### 2️⃣ Start the container

``` bash
docker-compose up -d
```

### 3️⃣ Open in your browser

    http://localhost:8989

------------------------------------------------------------------------

## ⚙️ Configuration

On first launch, click **⚙️ Settings** in the top navigation bar and
configure:

### 🔑 Last.fm API Key

Required for: - Fetching album artwork\
- Retrieving genres\
- Importing scrobbles

### 🧠 AI Provider

Choose between: - OpenAI\
- Google Gemini

Provide the respective API key to enable the AI Nerd features.

### 🎚 Import Thresholds

Define how many times an album must be scrobbled before it is allowed
into your duel database.

------------------------------------------------------------------------

## 📂 File Structure & Data Persistence

All user data is stored locally inside the `/data` directory:

``` text
/data
├── elo_state.csv        # Main database containing album ranks and stats
├── listening_queue.csv  # Albums set aside for re-listening
└── settings.json        # Saved API keys and preferences
```

------------------------------------------------------------------------

## 🔒 Security Note

The `/data` and `/cache` directories are excluded from Git to protect: -
Your API keys\
- Your personal rankings

------------------------------------------------------------------------

## 🧠 Why?

Because ranking albums once is easy.\
Defending your taste against brutal 1v1 duels over time?

That's where the truth comes out.

------------------------------------------------------------------------

## 📜 License

MIT License --- do whatever you want, but don't blame the AI if it
judges your music taste.
