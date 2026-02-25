<?php
require_once 'config.php';

function getAlbumCacheBaseName($artist, $album) {
    // We use an MD5 hash so filenames never exceed the OS 255-character limit
    $hash = md5(strtolower($artist . "_" . $album));
    return "album_" . $hash;
}

function fetchItunesAlbumData($artist, $album) {
    $term = trim($artist . ' ' . $album);
    if ($term === '') {
        return null;
    }

    $url = 'https://itunes.apple.com/search?term=' . urlencode($term) . '&entity=album&limit=1';
    $response = @file_get_contents($url);
    if ($response === false) {
        return null;
    }

    $decoded = json_decode($response, true);
    if (!isset($decoded['results'][0]) || !is_array($decoded['results'][0])) {
        return null;
    }

    $result = $decoded['results'][0];
    $genres = [];

    if (!empty($result['primaryGenreName'])) {
        $genres[] = ucwords(trim((string)$result['primaryGenreName']));
    }

    $year = '';
    if (!empty($result['releaseDate']) && preg_match('/\b(19|20)\d{2}\b/', (string)$result['releaseDate'], $matches)) {
        $year = $matches[0];
    }

    $coverUrl = '';
    if (!empty($result['artworkUrl100'])) {
        $coverUrl = str_replace('100x100bb', '600x600bb', (string)$result['artworkUrl100']);
    }

    return [
        'url' => $result['collectionViewUrl'] ?? '',
        'summary' => 'Metadata provided by iTunes fallback.',
        'genres' => array_values(array_unique($genres)),
        'year' => $year,
        'image_url' => $coverUrl
    ];
}

function getAlbumData($artist, $album) {
    global $APP_SETTINGS;

    $safeName = getAlbumCacheBaseName($artist, $album);
    
    $jsonFile = DIR_CACHE . $safeName . '.json';
    $imgFile = DIR_CACHE . $safeName . '.jpg';
    $imgUrl = 'cache/' . $safeName . '.jpg';

    // 1. Load cache
    if (file_exists($jsonFile)) {
        $data = json_decode(file_get_contents($jsonFile), true);
        if (file_exists($imgFile)) $data['local_image'] = $imgUrl;
        
        // Use a flag to verify this is a fully patched cache file.
        if (isset($data['full_data_fetched']) && $data['full_data_fetched'] === true) {
            return $data;
        }
    }

    // Initialize with the new flag set to true
    $result = [
        'summary' => 'No info available.', 
        'local_image' => '', 
        'url' => '', 
        'genres' => [], 
        'year' => '', 
        'full_data_fetched' => true
    ];

    // 2. Try Last.fm (if configured)
    $foundImage = false;
    if (!empty(LASTFM_API_KEY)) {
        $url = "http://ws.audioscrobbler.com/2.0/?method=album.getinfo&api_key=" . LASTFM_API_KEY . "&artist=" . urlencode($artist) . "&album=" . urlencode($album) . "&format=json";
        $response = @file_get_contents($url);

        if ($response) {
        $decoded = json_decode($response, true);
        if (isset($decoded['album'])) {
            $result['url'] = $decoded['album']['url'] ?? '';
            
            // Extract Summary
            if (isset($decoded['album']['wiki']['summary'])) {
                $result['summary'] = strip_tags(explode('<a href', $decoded['album']['wiki']['summary'])[0]);
            }
            
            // Extract Year (from published date or summary text)
            if (isset($decoded['album']['wiki']['published'])) {
                if (preg_match('/\b(19|20)\d{2}\b/', $decoded['album']['wiki']['published'], $matches)) {
                    $result['year'] = $matches[0];
                }
            }
            if (empty($result['year']) && isset($result['summary'])) {
                if (preg_match('/\b(19|20)\d{2}\b/', $result['summary'], $matches)) {
                    $result['year'] = $matches[0];
                }
            }

            // Extract Genres / Tags
            if (isset($decoded['album']['tags']['tag'])) {
                $tags = $decoded['album']['tags']['tag'];
                if (isset($tags['name'])) {
                    $result['genres'][] = ucwords($tags['name']);
                } else {
                    foreach ($tags as $tag) {
                        if (isset($tag['name'])) $result['genres'][] = ucwords($tag['name']);
                    }
                }
            }

            // Extract Image
            if (isset($decoded['album']['image'])) {
                foreach (array_reverse($decoded['album']['image']) as $img) {
                    if (!empty($img['#text'])) {
                        $imgData = @file_get_contents($img['#text']);
                        if ($imgData) {
                            file_put_contents($imgFile, $imgData);
                            $result['local_image'] = $imgUrl;
                            $foundImage = true;
                            break;
                        }
                    }
                }
            }
        }
    }
    }

    // 3. Fallback to iTunes when Last.fm is unavailable/incomplete
    $itunesData = fetchItunesAlbumData($artist, $album);
    if (is_array($itunesData)) {
        if (empty($result['url']) && !empty($itunesData['url'])) {
            $result['url'] = $itunesData['url'];
        }

        if ($result['summary'] === 'No info available.' && !empty($itunesData['summary'])) {
            $result['summary'] = $itunesData['summary'];
        }

        if (empty($result['genres']) && !empty($itunesData['genres'])) {
            $result['genres'] = $itunesData['genres'];
        }

        if (empty($result['year']) && !empty($itunesData['year'])) {
            $result['year'] = $itunesData['year'];
        }

        if (!$foundImage && !empty($itunesData['image_url'])) {
            $imgData = @file_get_contents($itunesData['image_url']);
            if ($imgData !== false) {
                file_put_contents($imgFile, $imgData);
                $result['local_image'] = $imgUrl;
                $foundImage = true;
            }
        }
    }

    if (!$foundImage && file_exists($imgFile)) {
        $result['local_image'] = $imgUrl;
    }

    // Save the fully populated data back to the cache
    file_put_contents($jsonFile, json_encode($result));
    
    return $result;
}

function triggerNerdComment($recentPicksText) {
    global $APP_SETTINGS;
    
    if (!$APP_SETTINGS['nerd_comments_enabled']) {
        return false;
    }

    $prompt = "You are a highly opinionated, passionate music nerd. I just completed 25 album duels! Here are the albums I favored in these recent matchups:\n\n" . $recentPicksText . "\n\n";
    $prompt .= "Roast or praise my recent choices. Point out patterns in my current mood or taste regarding DECADES and GENRES. Am I being basic? Be witty, analytical and comprehensive. Max 250 words. English.";

    return callAIProvider($prompt);
}

function triggerBootcampComment($top50Text, $top50History = [], $commentHistory = []) {
    global $APP_SETTINGS;

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
    
    $prompt = "You are a highly opinionated, incredibly knowledgeable, and slightly snobbish music critic (think Anthony Fantano or a seasoned Pitchfork reviewer). You are reviewing my current Top 50 albums list:\n\n" . $top50Text . "\n\n";
    $prompt .= $historyBlock;
    $prompt .= "Analyze this Top 50 list in earnest. Use the history to comment on taste evolution and recurring patterns over time. Drop any military or drill instructor vibes; you are a pure, passionate music nerd. Keep the tone snobbish, arrogant-but-kind, witty, and analytical. Point out the genuinely great picks and praise the highlights, but don't hold back on critiques of basic, overrated, or questionable choices. Suggest superior alternatives (e.g., 'how can you listen to X when Y exists?'). Point out missing genres, glaring omissions of essential classics, and extreme biases toward specific eras or artists. Include at least one concrete comparison to earlier snapshots (for example: an artist appearing less often now). End with a Fantano-style overall score from 1 to 10 in a clear format like 'Score: 7/10'. Max 380 words. English only.";

    return callAIProvider($prompt);
}

function callAIProvider($prompt) {
    global $APP_SETTINGS;
    
    if ($APP_SETTINGS['ai_provider'] === 'openai') {
        $url = "https://api.openai.com/v1/chat/completions";
        $data = [
            "model" => $APP_SETTINGS['openai_model'],
            "messages" => [
                ["role" => "system", "content" => "You are a witty, analytical music snob."],
                ["role" => "user", "content" => $prompt]
            ]
        ];
        $options = [
            'http' => [
                'header' => "Content-type: application/json\r\nAuthorization: Bearer " . $APP_SETTINGS['openai_api_key'] . "\r\n",
                'method' => 'POST',
                'content' => json_encode($data)
            ]
        ];
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response) {
            $res = json_decode($response, true);
            return $res['choices'][0]['message']['content'] ?? false;
        }
    } else {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . $APP_SETTINGS['gemini_model'] . ":generateContent?key=" . $APP_SETTINGS['gemini_api_key'];
        $data = ["contents" => [["parts" => [["text" => $prompt]]]]];
        $options = [
            'http' => [
                'header' => "Content-type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($data)
            ]
        ];
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        
        if ($response) {
            $res = json_decode($response, true);
            return $res['candidates'][0]['content']['parts'][0]['text'] ?? false;
        }
    }
    
    return false;
}
?>
