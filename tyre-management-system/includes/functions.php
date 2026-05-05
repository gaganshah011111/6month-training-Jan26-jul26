<?php
declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $page): void
{
    header('Location: index.php?page=' . urlencode($page));
    exit;
}

function route_url(string $page): string
{
    return 'index.php?page=' . urlencode($page);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function has_role(array $roles): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }
    return in_array($user['role'], $roles, true);
}

function format_currency(float $amount): string
{
    return 'INR ' . number_format($amount, 2);
}

