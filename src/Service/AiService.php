<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Repository\SettingsRepository;
use App\Utils\HttpClient;

class AiService
{
    private HttpClient $http;

    public function __construct(
        private Config $config,
        private SettingsRepository $settings
    ) {
        $this->http = new HttpClient();
    }

    public function triggerNerdComment(string $recentPicksText): ?string
    {
        if (!$this->settings->isNerdCommentsEnabled()) {
            return null;
        }

        $prompt = "You are a highly opinionated, passionate music nerd. I just completed 25 album duels! Here are the albums I favored in these recent matchups:\n\n"
            . $recentPicksText . "\n\n"
            . "Roast or praise my recent choices. Point out patterns in my current mood or taste regarding DECADES and GENRES. Am I being basic? Be witty, analytical and comprehensive. Max 250 words. English.";

        return $this->callProvider($prompt);
    }

    /**
     * @param list<string> $top50History
     * @param list<string> $commentHistory
     */
    public function triggerBootcampComment(string $top50Text, array $top50History = [], array $commentHistory = []): ?string
    {
        $historyBlock = "";
        if (!empty($top50History)) {
            $historyBlock .= "Previous Top 50 snapshots (oldest to newest):\n";
            foreach ($top50History as $index => $snapshot) {
                $historyBlock .= "--- Snapshot " . ($index + 1) . " ---\n" . $snapshot . "\n\n";
            }
        }

        if (!empty($commentHistory)) {
            $historyBlock .= "Your last critic comments (oldest to newest):\n";
            foreach ($commentHistory as $index => $comment) {
                $historyBlock .= "--- Comment " . ($index + 1) . " ---\n" . $comment . "\n\n";
            }
        }

        $prompt = "You are a highly opinionated, incredibly knowledgeable, and slightly snobbish music critic (think Anthony Fantano or a seasoned Pitchfork reviewer). You are reviewing my current Top 50 albums list:\n\n"
            . $top50Text . "\n\n"
            . $historyBlock
            . "Analyze this Top 50 list in earnest. Use the history to comment on taste evolution and recurring patterns over time. Drop any military or drill instructor vibes; you are a pure, passionate music nerd. Keep the tone snobbish, arrogant-but-kind, witty, and analytical. Point out the genuinely great picks and praise the highlights, but don't hold back on critiques of basic, overrated, or questionable choices. Suggest superior alternatives (e.g., 'how can you listen to X when Y exists?'). Point out missing genres, glaring omissions of essential classics, and extreme biases toward specific eras or artists. Include at least one concrete comparison to earlier snapshots (for example: an artist appearing less often now). End with a Fantano-style overall score from 1 to 10 in a clear format like 'Score: 7/10'. Max 380 words. English only.";

        return $this->callProvider($prompt);
    }

    private function callProvider(string $prompt): ?string
    {
        if ($this->settings->getAiProvider() === 'openai' && !empty($this->settings->getOpenAiApiKey())) {
            return $this->callOpenAi($prompt);
        }

        if (!empty($this->settings->getGeminiApiKey())) {
            return $this->callGemini($prompt);
        }

        return null;
    }

    private function callOpenAi(string $prompt): ?string
    {
        $url = "https://api.openai.com/v1/chat/completions";
        $data = [
            "model"    => $this->settings->getOpenAiModel(),
            "messages" => [
                ["role" => "system", "content" => "You are a witty, analytical music snob."],
                ["role" => "user", "content" => $prompt],
            ],
        ];

        $res = $this->http->postJson($url, $data, [
            'Authorization: Bearer ' . $this->settings->getOpenAiApiKey(),
        ]);

        if ($res === null) {
            return null;
        }

        return $res['choices'][0]['message']['content'] ?? null;
    }

    private function callGemini(string $prompt): ?string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/"
            . $this->settings->getGeminiModel()
            . ":generateContent?key="
            . $this->settings->getGeminiApiKey();

        $data = ["contents" => [["parts" => [["text" => $prompt]]]]];
        $res  = $this->http->postJson($url, $data);

        if ($res === null) {
            return null;
        }

        return $res['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }
}
