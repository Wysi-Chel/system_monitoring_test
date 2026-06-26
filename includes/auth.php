<?php
const MONITORING_AUTH_PASSWORD = "@Micei2026";
const MONITORING_AUTH_SESSION_KEY = "system_monitoring_authenticated";

function startMonitoringSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        "httponly" => true,
        "samesite" => "Lax",
        "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
    ]);
    session_start();
}

function isMonitoringAuthenticated(): bool
{
    startMonitoringSession();
    return !empty($_SESSION[MONITORING_AUTH_SESSION_KEY]);
}

function getSafeAuthRedirectTarget(?string $target): string
{
    $target = trim((string) $target);
    if ($target === "" || str_starts_with($target, "//") || preg_match('/^[a-z][a-z0-9+.-]*:/i', $target)) {
        return "index.php";
    }

    return $target;
}

function requireMonitoringAuthentication(): void
{
    if (isMonitoringAuthenticated()) {
        return;
    }

    $currentUrl = $_SERVER["REQUEST_URI"] ?? "index.php";
    header("Location: login.php?next=" . rawurlencode(getSafeAuthRedirectTarget($currentUrl)));
    exit;
}

function authenticateMonitoringPassword(string $password): bool
{
    if (!hash_equals(MONITORING_AUTH_PASSWORD, $password)) {
        return false;
    }

    startMonitoringSession();
    session_regenerate_id(true);
    $_SESSION[MONITORING_AUTH_SESSION_KEY] = true;
    return true;
}
