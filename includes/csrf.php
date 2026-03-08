<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_validate(?string $token): bool {
    if (empty($_SESSION['_csrf_token']) || !is_string($token)) {
        return false;
    }
    return hash_equals($_SESSION['_csrf_token'], $token);
}

