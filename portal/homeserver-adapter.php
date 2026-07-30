<?php
declare(strict_types=1);

/* North Mountain Media build: 20260730-homeserver-adapter-v66D */

function homeserver_adapter_setting(string $key, string $default = ''): string
{
    try {
        if (function_exists('nmm_site_setting')) {
            $value = trim((string)nmm_site_setting($key));
            return $value !== '' ? $value : $default;
        }
        if (function_exists('setting')) {
            $value = trim((string)setting($key, ''));
            return $value !== '' ? $value : $default;
        }
    } catch (Throwable) {
    }
    return $default;
}

function homeserver_adapter_status(): array
{
    $configured = function_exists('nmm_config') ? nmm_config('homeserver') : [];
    $paired = filter_var(
        $configured['paired'] ?? homeserver_adapter_setting('homeserver_paired', '0'),
        FILTER_VALIDATE_BOOL
    );
    $endpoint = trim((string)($configured['endpoint'] ?? homeserver_adapter_setting('homeserver_endpoint')));
    $lastSeen = trim((string)($configured['last_seen_at'] ?? homeserver_adapter_setting('homeserver_last_seen_at')));
    $online = false;
    if ($paired && $lastSeen !== '') {
        $seen = strtotime($lastSeen);
        $online = $seen !== false && $seen >= time() - 300;
    }

    $capabilities = $configured['capabilities'] ?? [];
    if (is_string($capabilities)) {
        $decoded = json_decode($capabilities, true);
        $capabilities = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $capabilities)));
    }
    if (!is_array($capabilities)) $capabilities = [];
    $capabilities = array_values(array_unique(array_filter(array_map('strval', $capabilities))));

    return [
        'paired' => $paired,
        'online' => $online,
        'endpoint_configured' => $endpoint !== '',
        'last_seen_at' => $lastSeen !== '' ? $lastSeen : null,
        'capabilities' => $capabilities,
        'mode' => $paired ? ($online ? 'connected' : 'offline') : 'standalone',
    ];
}

function homeserver_is_connected(): bool
{
    $status = homeserver_adapter_status();
    return $status['paired'] && $status['online'];
}

function homeserver_capability_available(string $capability): bool
{
    $capability = trim($capability);
    if ($capability === '' || !homeserver_is_connected()) return false;
    $status = homeserver_adapter_status();
    return in_array($capability, $status['capabilities'], true)
        || function_exists('homeserver_connector_capability_available')
            && homeserver_connector_capability_available($capability);
}

function homeserver_request(string $capability, array $payload = []): array
{
    $capability = trim($capability);
    if ($capability === '') {
        return ['ok' => false, 'available' => false, 'message' => 'A HomeServer capability is required.'];
    }
    if (!homeserver_capability_available($capability)) {
        return [
            'ok' => false,
            'available' => false,
            'capability' => $capability,
            'message' => 'This private HomeServer capability is not currently available.',
        ];
    }
    if (!function_exists('homeserver_connector_request')) {
        return [
            'ok' => false,
            'available' => false,
            'capability' => $capability,
            'message' => 'The HomeServer connector contract is not installed yet.',
        ];
    }

    try {
        $result = homeserver_connector_request($capability, $payload);
        return is_array($result)
            ? $result + ['ok' => true, 'available' => true, 'capability' => $capability]
            : ['ok' => true, 'available' => true, 'capability' => $capability, 'result' => $result];
    } catch (Throwable $exception) {
        return [
            'ok' => false,
            'available' => true,
            'capability' => $capability,
            'message' => 'The HomeServer request could not be completed.',
            'error_code' => 'homeserver_request_failed',
        ];
    }
}
