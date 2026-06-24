<?php
declare(strict_types=1);

function e(string|null|int|float $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function base_url(string $path = ''): string
{
    $path = ltrim($path, '/');

    if ($path === '') {
        return BASE_URL === '' ? '/' : BASE_URL . '/';
    }

    return (BASE_URL === '' ? '' : BASE_URL) . '/' . $path;
}

function redirect(string $path): never
{
    $url = str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
        ? $path
        : base_url($path);

    header('Location: ' . $url);
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function pull_flashes(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);

    return is_array($messages) ? $messages : [];
}

function render_flash_messages(): void
{
    $classMap = [
        'success' => 'success',
        'danger' => 'danger',
        'warning' => 'warning',
        'info' => 'info',
    ];

    foreach (pull_flashes() as $message) {
        $type = $classMap[$message['type'] ?? 'info'] ?? 'info';
        echo '<div class="alert alert-' . e($type) . ' shadow-sm border-0" role="alert">';
        echo e((string) ($message['message'] ?? ''));
        echo '</div>';
    }
}

function money(float|int|string|null $amount): string
{
    return 'PHP ' . number_format((float) $amount, 2);
}

function format_date(?string $date, string $format = 'M d, Y'): string
{
    if (!$date) {
        return 'Not set';
    }

    $timestamp = strtotime($date);

    return $timestamp ? date($format, $timestamp) : 'Invalid date';
}

function status_badge_class(string $status): string
{
    return match ($status) {
        'Active' => 'text-bg-success',
        'Disposed' => 'text-bg-secondary',
        'Fully Depreciated' => 'text-bg-warning',
        default => 'text-bg-light',
    };
}

function condition_badge_class(string $condition): string
{
    return match ($condition) {
        'Healthy' => 'text-bg-success',
        'Monitor' => 'text-bg-warning',
        'Critical' => 'text-bg-danger',
        default => 'text-bg-light',
    };
}

function role_badge_class(string $role): string
{
    return match ($role) {
        'Admin' => 'text-bg-primary',
        'Accounting Staff' => 'text-bg-warning',
        'Auditor' => 'text-bg-info',
        default => 'text-bg-light',
    };
}

function active_path(string|array $needle): bool
{
    $scriptName = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
    $needles = is_array($needle) ? $needle : [$needle];

    foreach ($needles as $candidate) {
        if (str_ends_with($scriptName, (string) $candidate)) {
            return true;
        }
    }

    return false;
}

function request_value(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function selected_if(mixed $left, mixed $right): string
{
    return (string) $left === (string) $right ? 'selected' : '';
}

function checked_if(bool $condition): string
{
    return $condition ? 'checked' : '';
}

function pluralize(int $count, string $singular, ?string $plural = null): string
{
    return $count === 1 ? $singular : ($plural ?? $singular . 's');
}

function excerpt(string $text, int $limit = 180): string
{
    $text = trim($text);

    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $limit, '...');
    }

    return strlen($text) > $limit ? substr($text, 0, max(0, $limit - 3)) . '...' : $text;
}

function download_csv(string $filename, array $headerRow, array $rows): never
{
    $safeFilename = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($filename)) ?: 'export.csv';

    if (!str_ends_with(strtolower($safeFilename), '.csv')) {
        $safeFilename .= '.csv';
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $stream = fopen('php://output', 'wb');

    if ($stream === false) {
        throw new RuntimeException('Unable to open the CSV output stream.');
    }

    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, $headerRow);

    foreach ($rows as $row) {
        $values = array_map(
            static fn (mixed $value): string => $value === null ? '' : (string) $value,
            $row
        );
        fputcsv($stream, $values);
    }

    fclose($stream);
    exit;
}
