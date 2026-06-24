<?php
declare(strict_types=1);

function load_env_file(string $filePath): void
{
    if (!is_file($filePath) || !is_readable($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            continue;
        }

        [$name, $value] = array_map('trim', explode('=', $trimmed, 2));

        if ($name === '') {
            continue;
        }

        $length = strlen($value);
        if ($length >= 2) {
            $first = $value[0];
            $last = $value[$length - 1];

            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        if (getenv($name) !== false) {
            continue;
        }

        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

load_env_file(dirname(__DIR__) . '/.env');

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');

define('APP_NAME', 'PPE Lapsing System');
define('APP_ROOT', dirname(__DIR__));
define('CURRENT_YEAR', (int) date('Y'));

$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? APP_ROOT;
$resolvedDocumentRoot = is_string($documentRoot) ? (realpath($documentRoot) ?: APP_ROOT) : APP_ROOT;
$resolvedProjectRoot = realpath(APP_ROOT) ?: APP_ROOT;
$normalizedDocumentRoot = str_replace('\\', '/', $resolvedDocumentRoot);
$normalizedProjectRoot = str_replace('\\', '/', $resolvedProjectRoot);
$baseUrl = '';

if ($normalizedDocumentRoot !== '' && str_starts_with($normalizedProjectRoot, $normalizedDocumentRoot)) {
    $relativePath = substr($normalizedProjectRoot, strlen($normalizedDocumentRoot));
    $baseUrl = rtrim(str_replace('\\', '/', $relativePath), '/');
}

define('BASE_URL', $baseUrl);

require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/functions/depreciation_function.php';
require_once APP_ROOT . '/functions/asset_workflow.php';
require_once APP_ROOT . '/includes/auth.php';

// Ollama local LLM configuration (adjust via .env if desired)
$OLLAMA_CONFIG = [
    'enabled' => filter_var(getenv('OLLAMA_ENABLED') ?: true, FILTER_VALIDATE_BOOLEAN),
    'url' => getenv('OLLAMA_URL') ?: 'http://localhost:11434',
    'endpoint' => getenv('OLLAMA_ENDPOINT') ?: '/api/generate',
    'model' => getenv('OLLAMA_MODEL') ?: 'ggml-alpaca-q4o',
    'timeout' => (int) (getenv('OLLAMA_TIMEOUT') ?: 10),
];

function get_ollama_config(): array
{
    global $OLLAMA_CONFIG;
    return $OLLAMA_CONFIG;
}

// Cache TTL for Ollama monitor (seconds)
define('OLLAMA_CACHE_TTL', (int) (getenv('OLLAMA_CACHE_TTL') ?: 60));
