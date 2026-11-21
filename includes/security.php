<?php
/**
 * Security Hardening Configuration - Futsalsayan
 * Handles Session Hardening, Security Headers, Error Logging, and CSRF Protection
 */

// 1. Error Reporting Configuration (Production Security: Hide errors from users, log to file)
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../error_log');
error_reporting(E_ALL);

// 2. Hide PHP Version
ini_set('expose_php', 'Off');
if (function_exists('header_remove')) {
    header_remove('X-Powered-By');
}

// 3. Send Security Headers (if headers not already sent)
if (!headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // Content Security Policy (allows Tailwind JIT Blob Web Workers, CDNs, and Google Maps)
    $csp = "default-src 'self'; " .
           "script-src 'self' 'unsafe-inline' 'unsafe-eval' blob: https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
           "style-src 'self' 'unsafe-inline' https: blob:; " .
           "font-src 'self' https: data:; " .
           "img-src 'self' data: https: blob:; " .
           "media-src 'self' data:; " .
           "frame-src 'self' https://www.google.com https://maps.google.com; " .
           "worker-src 'self' blob:; " .
           "child-src 'self' blob:; " .
           "connect-src 'self' https: blob:; " .
           "frame-ancestors 'self';";
    header("Content-Security-Policy: " . $csp);
}

// 4. Session Cookie Hardening (HttpOnly, SameSite=Lax, Secure if HTTPS)
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_only_cookies', '1');
    
    $isHttps = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1'))
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => $cookieParams['lifetime'] ?? 0,
        'path'     => $cookieParams['path'] ?? '/',
        'domain'   => $cookieParams['domain'] ?? '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    @session_start();
}

// 5. CSRF Protection Helpers
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        return generate_csrf_token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        $token = generate_csrf_token();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token) {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('validate_csrf_request')) {
    function validate_csrf_request() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? '';
            // Also check headers for AJAX/fetch if passed
            if (empty($token) && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
                $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
            }
            if (!verify_csrf_token($token)) {
                return false;
            }
        }
        return true;
    }
}

