<?php
declare(strict_types=1);

namespace App\Core;

class Config
{
    private static ?self $instance = null;

    private string $dataDir;
    private string $cacheDir;
    private array $settings = [];

    private function __construct()
    {
        $this->resolvePaths();
        $this->loadSettings();
    }

    public static function get(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    private function resolvePaths(): void
    {
        $basePath = dirname(__DIR__, 2);
        $dataDir  = $basePath . '/data/';
        $cacheDir = $basePath . '/cache/';

        $electronPath = getenv('APP_USER_DATA_PATH') ?: ($_SERVER['APP_USER_DATA_PATH'] ?? '');

        if (!empty($electronPath)) {
            $base = rtrim(str_replace('\\', '/', $electronPath), '/');
            $dataDir  = $base . '/AlbumFightsData/';
            $cacheDir = $base . '/AlbumFightsCache/';
        } elseif (getenv('APPDATA')) {
            $dataDir  = str_replace('\\', '/', (string)getenv('APPDATA')) . '/AlbumFights/data/';
            $cacheDir = str_replace('\\', '/', (string)getenv('LOCALAPPDATA')) . '/AlbumFights/cache/';
        } elseif (getenv('FLATPAK_ID')) {
            $dataDir  = rtrim((string)getenv('XDG_DATA_HOME'), '/') . '/AlbumFightsData/';
            $cacheDir = rtrim((string)getenv('XDG_CACHE_HOME'), '/') . '/AlbumFightsCache/';
        }

        $this->dataDir  = $dataDir;
        $this->cacheDir = $cacheDir;

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0777, true);
        }
    }

    private function loadSettings(): void
    {
        $defaults = [
            'lastfm_api_key'         => getenv('LASTFM_API_KEY') ?: '',
            'listenbrainz_api_key'   => getenv('LISTENBRAINZ_API_KEY') ?: '',
            'listenbrainz_username'  => getenv('LISTENBRAINZ_USERNAME') ?: '',
            'subsonic_base_url'      => getenv('SUBSONIC_BASE_URL') ?: '',
            'subsonic_username'      => getenv('SUBSONIC_USERNAME') ?: '',
            'subsonic_password'      => getenv('SUBSONIC_PASSWORD') ?: '',
            'gemini_api_key'         => getenv('GEMINI_API_KEY') ?: '',
            'openai_api_key'         => getenv('OPENAI_API_KEY') ?: '',
            'ai_provider'            => 'gemini',
            'gemini_model'           => 'gemini-3-flash-preview',
            'openai_model'           => 'gpt-4o-mini',
            'nerd_comments_enabled'  => true,
            'import_min_plays'       => 8,
            'tag_blacklist'          => [],
            'duel_category_weights'  => [
                'top_25_vs'        => 20,
                'top_50_vs'        => 20,
                'top_100_vs'       => 20,
                'playcount_gt_20'  => 15,
                'duel_counter_zero'=> 15,
                'random'           => 10,
            ],
        ];

        $file = $this->dataDir . 'settings.json';
        if (file_exists($file)) {
            $user = json_decode(file_get_contents($file), true) ?: [];
            $this->settings = array_merge($defaults, $user);
        } else {
            $this->settings = $defaults;
            $this->saveSettings();
        }
    }

    public function saveSettings(): void
    {
        file_put_contents(
            $this->dataDir . 'settings.json',
            json_encode($this->settings, JSON_PRETTY_PRINT)
        );
    }

    public function getDataDir(): string
    {
        return $this->dataDir;
    }

    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    public function getEloFile(): string
    {
        return $this->dataDir . 'elo_state.csv';
    }

    public function getQueueFile(): string
    {
        return $this->dataDir . 'listening_queue.csv';
    }

    public function getSettingsFile(): string
    {
        return $this->dataDir . 'settings.json';
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->settings[$key] = $value;
    }

    public function all(): array
    {
        return $this->settings;
    }

    public function isDev(): bool
    {
        return getenv('APP_ENV') === 'dev';
    }

    public function isPerfLogEnabled(): bool
    {
        return $this->isDev() && getenv('DEV_PERF_LOG') === '1';
    }

    public function getPerfLogFile(): string
    {
        return $this->dataDir . 'dev_perf.log';
    }
}
