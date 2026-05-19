<?php
declare(strict_types=1);

namespace App\Utils;

class HttpClient
{
    private int $defaultTimeout;

    public function __construct(int $defaultTimeout = 20)
    {
        $this->defaultTimeout = $defaultTimeout;
    }

    /**
     * @param list<string> $headers
     * @return array{ok: bool, data: ?array, http_code: ?int, error: string}
     */
    public function fetchJson(string $url, array $headers = [], int $timeout = 0): array
    {
        $timeout = $timeout > 0 ? $timeout : $this->defaultTimeout;
        $result  = $this->fetch($url, $headers, $timeout);

        if ($result['response'] === null) {
            return [
                'ok'        => false,
                'data'      => null,
                'http_code' => $result['http_code'],
                'error'     => $result['error'],
            ];
        }

        $decoded = json_decode($result['response'], true);
        if (!is_array($decoded)) {
            return [
                'ok'        => false,
                'data'      => null,
                'http_code' => $result['http_code'],
                'error'     => 'Invalid JSON response from API.',
            ];
        }

        return [
            'ok'        => true,
            'data'      => $decoded,
            'http_code' => $result['http_code'],
            'error'     => '',
        ];
    }

    /**
     * @param list<string> $headers
     */
    public function fetchBinary(string $url, array $headers = [], int $timeout = 0): ?string
    {
        $timeout = $timeout > 0 ? $timeout : $this->defaultTimeout;
        $result  = $this->fetch($url, $headers, $timeout);
        return $result['response'];
    }

    /**
     * @param list<string> $headers
     * @return array{response: ?string, http_code: ?int, error: string}
     */
    private function fetch(string $url, array $headers, int $timeout): array
    {
        $userAgent = "User-Agent: AlbumDuelApp/1.0\r\n";
        $headerStr = $userAgent;
        if (!empty($headers)) {
            $headerStr .= implode("\r\n", $headers) . "\r\n";
        }

        $opts = [
            'http' => [
                'method'  => 'GET',
                'timeout' => $timeout,
                'header'  => $headerStr,
            ],
        ];

        $sslOpts = self::getSslOptionsForDesktop();
        if (!empty($sslOpts)) {
            $opts['ssl'] = $sslOpts;
        }

        $response = @file_get_contents($url, false, stream_context_create($opts));

        $statusLine = $http_response_header[0] ?? '';
        $httpCode   = null;
        if (preg_match('/\s(\d{3})\s/', $statusLine, $m)) {
            $httpCode = (int)$m[1];
        }

        if ($response === false) {
            $lastError = error_get_last();
            $message   = 'Connection failed.';
            if (!empty($lastError['message'])) {
                $message = $lastError['message'];
            } elseif ($httpCode !== null) {
                $message = 'HTTP ' . $httpCode;
            }

            return [
                'response'  => null,
                'http_code' => $httpCode,
                'error'     => trim($message),
            ];
        }

        return [
            'response'  => $response,
            'http_code' => $httpCode,
            'error'     => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getSslOptionsForDesktop(): array
    {
        if (
            getenv('APP_USER_DATA_PATH')
            || getenv('FLATPAK_ID')
            || getenv('APPDATA')
            || getenv('ELECTRON_RUN_AS_NODE')
            || getenv('ALBUMFIGHTS_DESKTOP') === '1'
        ) {
            return [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ];
        }
        return [];
    }

    /**
     * @param array<string, mixed> $postData
     * @param list<string> $headers
     */
    public function postJson(string $url, array $postData, array $headers = [], int $timeout = 20): ?array
    {
        $headerLines = [
            "Content-type: application/json\r\n",
        ];
        foreach ($headers as $h) {
            $headerLines[] = $h . "\r\n";
        }

        $opts = [
            'http' => [
                'method'  => 'POST',
                'header'  => implode('', $headerLines),
                'content' => json_encode($postData),
                'timeout' => $timeout,
            ],
        ];

        $sslOpts = self::getSslOptionsForDesktop();
        if (!empty($sslOpts)) {
            $opts['ssl'] = $sslOpts;
        }

        $response = @file_get_contents($url, false, stream_context_create($opts));
        if ($response === false) {
            return null;
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }
}
