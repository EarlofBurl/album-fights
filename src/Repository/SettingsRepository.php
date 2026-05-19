<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Config;

class SettingsRepository
{
    public function __construct(private Config $config)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config->getSetting($key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $this->config->set($key, $value);
    }

    public function all(): array
    {
        return $this->config->all();
    }

    public function save(): void
    {
        $this->config->saveSettings();
    }

    public function getLastFmApiKey(): string
    {
        return (string)$this->config->getSetting('lastfm_api_key', '');
    }

    public function getListenbrainzApiKey(): string
    {
        return (string)$this->config->getSetting('listenbrainz_api_key', '');
    }

    public function getListenbrainzUsername(): string
    {
        return (string)$this->config->getSetting('listenbrainz_username', '');
    }

    public function getGeminiApiKey(): string
    {
        return (string)$this->config->getSetting('gemini_api_key', '');
    }

    public function getOpenAiApiKey(): string
    {
        return (string)$this->config->getSetting('openai_api_key', '');
    }

    public function getAiProvider(): string
    {
        return (string)$this->config->getSetting('ai_provider', 'gemini');
    }

    public function getGeminiModel(): string
    {
        return (string)$this->config->getSetting('gemini_model', 'gemini-3-flash-preview');
    }

    public function getOpenAiModel(): string
    {
        return (string)$this->config->getSetting('openai_model', 'gpt-4o-mini');
    }

    public function isNerdCommentsEnabled(): bool
    {
        return (bool)$this->config->getSetting('nerd_comments_enabled', true);
    }

    public function getImportMinPlays(): int
    {
        return (int)$this->config->getSetting('import_min_plays', 8);
    }

    /**
     * @return list<string>
     */
    public function getTagBlacklist(): array
    {
        $blacklist = $this->config->getSetting('tag_blacklist', []);
        return is_array($blacklist) ? array_values($blacklist) : [];
    }

    /**
     * @param list<string> $tags
     */
    public function setTagBlacklist(array $tags): void
    {
        $this->config->set('tag_blacklist', $tags);
    }

    /**
     * @return array<string, int>
     */
    public function getDuelCategoryWeights(): array
    {
        return (array)$this->config->getSetting('duel_category_weights', []);
    }

    /**
     * @param array<string, int> $weights
     */
    public function setDuelCategoryWeights(array $weights): void
    {
        $this->config->set('duel_category_weights', $weights);
    }
}
