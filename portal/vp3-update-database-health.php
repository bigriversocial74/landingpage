<?php
declare(strict_types=1);

final class Vp3SqlRunner
{
    public function execute(string $sql, bool $rollback = false): int
    {
        if (!$rollback) {
            $this->assertNonDestructive($sql);
        }
        $statements = $this->split($sql);
        $count = 0;
        foreach ($statements as $statement) {
            if (trim($statement) === '') {
                continue;
            }
            db()->exec($statement);
            $count++;
        }
        return $count;
    }

    public function assertNonDestructive(string $sql): void
    {
        $stripped = preg_replace('#/\*.*?\*/#s', ' ', $sql) ?? $sql;
        $stripped = preg_replace('/^\s*--.*$/m', ' ', $stripped) ?? $stripped;
        if (preg_match('/\b(DROP\s+(TABLE|DATABASE)|TRUNCATE\s+TABLE|ALTER\s+TABLE\s+[^;]+\s+DROP\s+|DELETE\s+FROM\s+[^;]+(?:;|$))/i', $stripped)) {
            throw new RuntimeException('The signed migration contains a destructive SQL operation and was blocked.');
        }
    }

    public function split(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $escaped = false;
        $lineComment = false;
        $blockComment = false;
        $length = strlen($sql);
        for ($index = 0; $index < $length; $index++) {
            $char = $sql[$index];
            $next = $index + 1 < $length ? $sql[$index + 1] : '';
            if ($lineComment) {
                $buffer .= $char;
                if ($char === "\n") {
                    $lineComment = false;
                }
                continue;
            }
            if ($blockComment) {
                $buffer .= $char;
                if ($char === '*' && $next === '/') {
                    $buffer .= '/';
                    $index++;
                    $blockComment = false;
                }
                continue;
            }
            if ($quote !== null) {
                $buffer .= $char;
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === $quote) {
                    if ($next === $quote) {
                        $buffer .= $next;
                        $index++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if (($char === '-' && $next === '-' && ($index + 2 >= $length || ctype_space($sql[$index + 2]))) || $char === '#') {
                $lineComment = true;
                $buffer .= $char;
                continue;
            }
            if ($char === '/' && $next === '*') {
                $blockComment = true;
                $buffer .= '/*';
                $index++;
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === ';') {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }
        return $statements;
    }
}

final class Vp3UpdateHealth
{
    public function local(string $expectedVersion): array
    {
        $checks = [
            'database' => false,
            'root_writable' => false,
            'storage_writable' => false,
            'bootstrap_present' => is_file(NMM_ROOT . '/portal/bootstrap.php'),
            'index_present' => is_file(NMM_ROOT . '/index.php'),
            'version_matches' => hash_equals($expectedVersion, vp3_update_installed_version()),
        ];
        try {
            $checks['database'] = (int)db()->query('SELECT 1')->fetchColumn() === 1;
        } catch (Throwable) {
            $checks['database'] = false;
        }
        $checks['root_writable'] = is_writable(NMM_ROOT);
        $checks['storage_writable'] = is_writable(NMM_ROOT . '/storage');
        return [
            'ok' => !in_array(false, $checks, true),
            'checks' => $checks,
            'version' => vp3_update_installed_version(),
        ];
    }

    public function remote(string $expectedVersion, string $healthToken): array
    {
        $base = rtrim((string)(nmm_config('app')['base_url'] ?? ''), '/');
        if ($base === '') {
            return ['ok' => false, 'warning' => 'app_base_url_missing'];
        }
        $url = $base . '/api/vp3-update/health.php';
        $response = Vp3UpdateHttp::requestJson(
            'GET',
            $url,
            '',
            [
                'Accept: application/json',
                'X-VP3-Health-Token: ' . $healthToken,
                'X-VP3-Expected-Version: ' . $expectedVersion,
            ],
            30,
            256 * 1024
        );
        $json = (array)$response['json'];
        return [
            'ok' => !empty($json['ok']) && hash_equals($expectedVersion, (string)($json['installed_version'] ?? '')),
            'response' => $json,
            'latency_ms' => (int)$response['latency_ms'],
        ];
    }
}
