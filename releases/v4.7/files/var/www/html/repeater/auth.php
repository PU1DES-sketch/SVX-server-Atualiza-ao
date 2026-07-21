<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function is_logged_in(): bool {
    return !empty($_SESSION['repeater_logged']);
}

function safe_next_path(string $next): string {
    $next = trim($next);
    if ($next === '') {
        return 'index.php?page=config';
    }
    if (preg_match('#^(https?:)?//#i', $next)) {
        return 'index.php?page=config';
    }
    if ($next[0] === '/') {
        $next = ltrim($next, '/');
    }
    return $next;
}

function require_login(string $next = 'index.php?page=config'): void {
    if (is_logged_in()) {
        return;
    }
    header('Location: login.php?next=' . urlencode(safe_next_path($next)));
    exit;
}

