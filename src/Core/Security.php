<?php
declare(strict_types=1);

namespace App\Core;

class Security
{
    private const CSRF_KEY = '_csrf_token';

    public static function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['duel_count'])) {
            $_SESSION['duel_count'] = 0;
        }
        if (!isset($_SESSION['history'])) {
            $_SESSION['history'] = [];
        }
        if (!isset($_SESSION['recent_picks'])) {
            $_SESSION['recent_picks'] = [];
        }
    }

    public static function generateToken(): string
    {
        if (empty($_SESSION[self::CSRF_KEY])) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::CSRF_KEY];
    }

    public static function validateToken(?string $token): bool
    {
        if (empty($token) || empty($_SESSION[self::CSRF_KEY])) {
            return false;
        }
        return hash_equals($_SESSION[self::CSRF_KEY], $token);
    }

    public static function csrfField(): string
    {
        $token = self::generateToken();
        return '<input type="hidden" name="' . self::CSRF_KEY . '" value="' . htmlspecialchars($token) . '">';
    }

    public static function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }

        $token = $_POST[self::CSRF_KEY] ?? null;
        if (!self::validateToken($token)) {
            http_response_code(403);
            exit('Invalid or missing CSRF token');
        }
    }

    public static function getString(array $data, string $key, string $default = ''): string
    {
        if (!isset($data[$key]) || !is_string($data[$key])) {
            return $default;
        }
        $value = trim($data[$key]);
        return $value;
    }

    public static function getInt(array $data, string $key, int $default = 0): int
    {
        if (!isset($data[$key])) {
            return $default;
        }
        return (int)$data[$key];
    }

    public static function getFloat(array $data, string $key, float $default = 0.0): float
    {
        if (!isset($data[$key])) {
            return $default;
        }
        return (float)$data[$key];
    }

    public static function getBool(array $data, string $key, bool $default = false): bool
    {
        return isset($data[$key]);
    }

    public static function slugifyToken(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = strtolower(trim($value));
        $value = preg_replace('/\b(deluxe|edition|remaster(?:ed)?|expanded|anniversary|version)\b/', ' ', $value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }
}
