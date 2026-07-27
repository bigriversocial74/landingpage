<?php
declare(strict_types=1);

function knowledge_allowed_extensions(): array
{
    return [
        'pdf' => ['application/pdf', 'document'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'document',
        ],
        'pptx' => [
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'document',
        ],
        'doc' => ['application/msword', 'document'],
        'ppt' => ['application/vnd.ms-powerpoint', 'document'],
        'xls' => ['application/vnd.ms-excel', 'data'],
        'xlsx' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'data',
        ],
        'odt' => [
            'application/vnd.oasis.opendocument.text',
            'document',
        ],
        'rtf' => ['application/rtf', 'document'],
        'txt' => ['text/plain', 'document'],
        'md' => ['text/markdown', 'document'],
        'csv' => ['text/csv', 'data'],
        'json' => ['application/json', 'data'],
        'xml' => ['application/xml', 'data'],
        'yaml' => ['application/yaml', 'data'],
        'yml' => ['application/yaml', 'data'],
        'log' => ['text/plain', 'document'],
        'srt' => ['application/x-subrip', 'document'],
        'vtt' => ['text/vtt', 'document'],
        'epub' => ['application/epub+zip', 'document'],
        'html' => ['text/html', 'document'],
        'htm' => ['text/html', 'document'],

        'jpg' => ['image/jpeg', 'image'],
        'jpeg' => ['image/jpeg', 'image'],
        'png' => ['image/png', 'image'],
        'gif' => ['image/gif', 'image'],
        'webp' => ['image/webp', 'image'],
        'bmp' => ['image/bmp', 'image'],

        'mp3' => ['audio/mpeg', 'audio'],
        'wav' => ['audio/wav', 'audio'],
        'm4a' => ['audio/mp4', 'audio'],
        'aac' => ['audio/aac', 'audio'],
        'ogg' => ['audio/ogg', 'audio'],
        'oga' => ['audio/ogg', 'audio'],
        'flac' => ['audio/flac', 'audio'],

        'mp4' => ['video/mp4', 'video'],
        'm4v' => ['video/x-m4v', 'video'],
        'webm' => ['video/webm', 'video'],
        'mov' => ['video/quicktime', 'video'],
        'ogv' => ['video/ogg', 'video'],
    ];
}

function knowledge_upload_limit_bytes(): int
{
    $app = nmm_config('app');
    return max(
        5 * 1024 * 1024,
        (int)($app['max_knowledge_upload_bytes'] ?? 100 * 1024 * 1024)
    );
}

function knowledge_storage_path(string $storedName): string
{
    return NMM_ROOT . '/storage/knowledge-assets/' . basename($storedName);
}

function knowledge_clean_text(string $text): string
{
    $text = str_replace(["\0", "\r\n", "\r"], ['', "\n", "\n"], $text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
    $text = preg_replace('/\n[ \t]+/', "\n", $text) ?? $text;
    $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
    return trim($text);
}

function knowledge_extract_xml_text(string $xml): string
{
    $xml = preg_replace(
        [
            '/<w:tab\b[^>]*\/>/i',
            '/<\/w:p>/i',
            '/<\/a:p>/i',
            '/<\/text:p>/i',
            '/<\/office:text>/i',
            '/<br\b[^>]*>/i',
        ],
        ["\t", "\n", "\n", "\n", "\n", "\n"],
        $xml
    ) ?? $xml;

    return knowledge_clean_text(strip_tags($xml));
}

function knowledge_extract_zip_members(
    string $path,
    callable $memberFilter
): array {
    $maximumUncompressed = 25 * 1024 * 1024;
    $maximumMembers = 500;
    $parts = [];
    $totalUncompressed = 0;

    if (class_exists(ZipArchive::class)) {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return [
                'text' => '',
                'method' => null,
                'error' => 'The Office document could not be opened as a ZIP package.',
            ];
        }

        $memberCount = min($zip->numFiles, $maximumMembers);

        for ($index = 0; $index < $memberCount; $index++) {
            $stat = $zip->statIndex($index);

            if (!is_array($stat)) {
                continue;
            }

            $name = (string)($stat['name'] ?? '');
            $size = (int)($stat['size'] ?? 0);

            if (!$memberFilter($name)) {
                continue;
            }

            $totalUncompressed += $size;

            if ($totalUncompressed > $maximumUncompressed) {
                $zip->close();
                return [
                    'text' => '',
                    'method' => null,
                    'error' => 'The document contains too much expanded XML data.',
                ];
            }

            $content = $zip->getFromIndex($index);

            if (is_string($content) && $content !== '') {
                $parts[$name] = $content;
            }
        }

        $zip->close();

        return [
            'text' => $parts,
            'method' => 'ziparchive-xml',
            'error' => null,
        ];
    }

    if (!knowledge_shell_allowed('shell_exec')) {
        return [
            'text' => '',
            'method' => null,
            'error' => 'PHP ZipArchive or the unzip command is required to extract this file type.',
        ];
    }

    $binary = trim((string)@shell_exec('command -v unzip 2>/dev/null'));

    if ($binary === '') {
        return [
            'text' => '',
            'method' => null,
            'error' => 'PHP ZipArchive or the unzip command is required to extract this file type.',
        ];
    }

    $listingCommand = escapeshellarg($binary)
        . ' -Z1 '
        . escapeshellarg($path)
        . ' 2>/dev/null';

    $listing = (string)@shell_exec($listingCommand);
    $members = array_slice(
        array_values(array_filter(array_map(
            'trim',
            preg_split('/?
/', $listing) ?: []
        ))),
        0,
        $maximumMembers
    );

    foreach ($members as $name) {
        if (!$memberFilter($name)) {
            continue;
        }

        $contentCommand = escapeshellarg($binary)
            . ' -p '
            . escapeshellarg($path)
            . ' '
            . escapeshellarg($name)
            . ' 2>/dev/null';

        $content = (string)@shell_exec($contentCommand);

        if ($content === '') {
            continue;
        }

        $totalUncompressed += strlen($content);

        if ($totalUncompressed > $maximumUncompressed) {
            return [
                'text' => '',
                'method' => null,
                'error' => 'The document contains too much expanded XML data.',
            ];
        }

        $parts[$name] = $content;
    }

    return [
        'text' => $parts,
        'method' => 'unzip-xml',
        'error' => null,
    ];
}

function knowledge_extract_docx(string $path): array
{
    $result = knowledge_extract_zip_members(
        $path,
        static fn (string $name): bool =>
            $name === 'word/document.xml'
            || preg_match('#^word/(header|footer)\d+\.xml$#', $name) === 1
    );

    if (!is_array($result['text'])) {
        return $result;
    }

    ksort($result['text']);
    $parts = array_map(
        'knowledge_extract_xml_text',
        array_values($result['text'])
    );

    return [
        'text' => knowledge_clean_text(implode("\n\n", $parts)),
        'method' => 'docx-xml',
        'error' => null,
    ];
}

function knowledge_extract_pptx(string $path): array
{
    $result = knowledge_extract_zip_members(
        $path,
        static fn (string $name): bool =>
            preg_match('#^ppt/slides/slide\d+\.xml$#', $name) === 1
            || preg_match('#^ppt/notesSlides/notesSlide\d+\.xml$#', $name) === 1
    );

    if (!is_array($result['text'])) {
        return $result;
    }

    uksort(
        $result['text'],
        static fn (string $left, string $right): int =>
            strnatcasecmp($left, $right)
    );

    $parts = [];
    $slide = 0;

    foreach ($result['text'] as $name => $xml) {
        if (str_contains($name, '/slides/')) {
            $slide++;
            $parts[] = 'Slide ' . $slide . "\n" . knowledge_extract_xml_text($xml);
        } else {
            $parts[] = 'Speaker notes' . "\n" . knowledge_extract_xml_text($xml);
        }
    }

    return [
        'text' => knowledge_clean_text(implode("\n\n", $parts)),
        'method' => 'pptx-xml',
        'error' => null,
    ];
}

function knowledge_extract_xlsx(string $path): array
{
    $result = knowledge_extract_zip_members(
        $path,
        static fn (string $name): bool =>
            $name === 'xl/sharedStrings.xml'
            || preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name) === 1
    );

    if (!is_array($result['text'])) {
        return $result;
    }

    $shared = [];
    $sharedXml = $result['text']['xl/sharedStrings.xml'] ?? '';

    if ($sharedXml !== '') {
        preg_match_all('/<si\b[^>]*>(.*?)<\/si>/is', $sharedXml, $matches);

        foreach ($matches[1] ?? [] as $item) {
            $shared[] = knowledge_extract_xml_text((string)$item);
        }
    }

    $sheets = array_filter(
        $result['text'],
        static fn (string $name): bool => str_contains($name, '/worksheets/'),
        ARRAY_FILTER_USE_KEY
    );

    uksort(
        $sheets,
        static fn (string $left, string $right): int =>
            strnatcasecmp($left, $right)
    );

    $parts = [];
    $sheetNumber = 0;

    foreach ($sheets as $xml) {
        $sheetNumber++;
        $rows = [];

        preg_match_all('/<row\b[^>]*>(.*?)<\/row>/is', $xml, $rowMatches);

        foreach ($rowMatches[1] ?? [] as $rowXml) {
            $cells = [];

            preg_match_all('/<c\b([^>]*)>(.*?)<\/c>/is', (string)$rowXml, $cellMatches, PREG_SET_ORDER);

            foreach ($cellMatches as $cellMatch) {
                $attributes = (string)($cellMatch[1] ?? '');
                $body = (string)($cellMatch[2] ?? '');
                $type = '';

                if (preg_match('/\bt="([^"]+)"/i', $attributes, $typeMatch)) {
                    $type = (string)$typeMatch[1];
                }

                $value = '';

                if ($type === 'inlineStr') {
                    $value = knowledge_extract_xml_text($body);
                } elseif (preg_match('/<v\b[^>]*>(.*?)<\/v>/is', $body, $valueMatch)) {
                    $raw = html_entity_decode(strip_tags((string)$valueMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                    if ($type === 's' && ctype_digit(trim($raw))) {
                        $value = $shared[(int)trim($raw)] ?? $raw;
                    } else {
                        $value = trim($raw);
                    }
                }

                if ($value !== '') {
                    $cells[] = $value;
                }
            }

            if ($cells) {
                $rows[] = implode(' | ', $cells);
            }
        }

        if ($rows) {
            $parts[] = 'Worksheet ' . $sheetNumber . "\n" . implode("\n", $rows);
        }
    }

    return [
        'text' => knowledge_clean_text(implode("\n\n", $parts)),
        'method' => 'xlsx-xml',
        'error' => null,
    ];
}

function knowledge_extract_odt(string $path): array
{
    $result = knowledge_extract_zip_members(
        $path,
        static fn (string $name): bool => $name === 'content.xml'
    );

    if (!is_array($result['text'])) {
        return $result;
    }

    return [
        'text' => knowledge_extract_xml_text(
            (string)($result['text']['content.xml'] ?? '')
        ),
        'method' => 'odt-xml',
        'error' => null,
    ];
}


function knowledge_extract_epub(string $path): array
{
    $result = knowledge_extract_zip_members(
        $path,
        static fn (string $name): bool =>
            preg_match('/\.(xhtml|html|htm)$/i', $name) === 1
    );

    if (!is_array($result['text'])) {
        return $result;
    }

    uksort(
        $result['text'],
        static fn (string $left, string $right): int =>
            strnatcasecmp($left, $right)
    );

    $parts = array_map(
        static fn (string $content): string =>
            knowledge_clean_text(strip_tags($content)),
        array_values($result['text'])
    );

    return [
        'text' => knowledge_clean_text(implode("\n\n", $parts)),
        'method' => 'epub-html',
        'error' => null,
    ];
}

function knowledge_extract_rtf(string $content): string
{
    $content = preg_replace('/\\\\par[d]?\b/i', "\n", $content) ?? $content;
    $content = preg_replace('/\\\\tab\b/i', "\t", $content) ?? $content;
    $content = preg_replace('/\\\\\'([0-9a-f]{2})/i', '', $content) ?? $content;
    $content = preg_replace('/\\\\[a-z]+-?\d* ?/i', '', $content) ?? $content;
    $content = str_replace(['{', '}'], '', $content);
    return knowledge_clean_text($content);
}

function knowledge_shell_allowed(string $function): bool
{
    $disabled = array_map(
        'trim',
        explode(',', (string)ini_get('disable_functions'))
    );

    return function_exists($function) && !in_array($function, $disabled, true);
}

function knowledge_extract_pdf(string $path): array
{
    if (!knowledge_shell_allowed('shell_exec')) {
        return [
            'text' => '',
            'method' => null,
            'error' => 'Automatic PDF extraction requires the pdftotext command. Add or paste the document text below.',
        ];
    }

    $binary = trim((string)@shell_exec('command -v pdftotext 2>/dev/null'));

    if ($binary === '') {
        return [
            'text' => '',
            'method' => null,
            'error' => 'The server does not provide pdftotext. Add or paste the document text below.',
        ];
    }

    $temporary = tempnam(sys_get_temp_dir(), 'nmm-pdf-');

    if ($temporary === false) {
        return [
            'text' => '',
            'method' => null,
            'error' => 'Could not allocate a temporary extraction file.',
        ];
    }

    $command = escapeshellarg($binary)
        . ' -layout -enc UTF-8 '
        . escapeshellarg($path)
        . ' '
        . escapeshellarg($temporary)
        . ' 2>&1';

    @shell_exec($command);
    $text = is_file($temporary)
        ? (string)file_get_contents($temporary)
        : '';
    @unlink($temporary);

    $text = knowledge_clean_text($text);

    if ($text === '') {
        return [
            'text' => '',
            'method' => null,
            'error' => 'No selectable PDF text was found. Paste a transcript or summary before publishing.',
        ];
    }

    return [
        'text' => $text,
        'method' => 'pdftotext',
        'error' => null,
    ];
}

function knowledge_extract_file(string $path, string $extension): array
{
    $extension = strtolower($extension);

    if (in_array($extension, ['txt', 'md', 'csv', 'json', 'xml', 'yaml', 'yml', 'log', 'srt', 'vtt'], true)) {
        $text = (string)file_get_contents($path);

        if ($extension === 'json') {
            try {
                $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
                $text = json_encode(
                    $decoded,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                ) ?: $text;
            } catch (Throwable) {
            }
        }

        return [
            'text' => knowledge_clean_text($text),
            'method' => $extension . '-text',
            'error' => null,
        ];
    }

    if (in_array($extension, ['html', 'htm'], true)) {
        return [
            'text' => knowledge_clean_text(
                strip_tags((string)file_get_contents($path))
            ),
            'method' => 'html-text',
            'error' => null,
        ];
    }

    if ($extension === 'rtf') {
        return [
            'text' => knowledge_extract_rtf(
                (string)file_get_contents($path)
            ),
            'method' => 'rtf-text',
            'error' => null,
        ];
    }

    if ($extension === 'docx') {
        return knowledge_extract_docx($path);
    }

    if ($extension === 'pptx') {
        return knowledge_extract_pptx($path);
    }

    if ($extension === 'xlsx') {
        return knowledge_extract_xlsx($path);
    }

    if ($extension === 'odt') {
        return knowledge_extract_odt($path);
    }

    if ($extension === 'pdf') {
        return knowledge_extract_pdf($path);
    }

    if ($extension === 'epub') {
        return knowledge_extract_epub($path);
    }

    if (in_array($extension, ['doc', 'xls', 'ppt'], true)) {
        return [
            'text' => '',
            'method' => null,
            'error' => 'Legacy Microsoft Office files can be stored and displayed as downloads, but require manually supplied text or conversion to DOCX, XLSX, or PPTX for automatic extraction.',
        ];
    }

    return [
        'text' => '',
        'method' => null,
        'error' => 'This media type needs a written description or transcript before it can answer chat questions.',
    ];
}

function knowledge_auto_summary(string $text, string $fallbackTitle): string
{
    $text = knowledge_clean_text($text);

    if ($text === '') {
        return $fallbackTitle;
    }

    $sentences = preg_split('/(?<=[.!?])\s+/', $text) ?: [$text];
    $summary = trim(implode(' ', array_slice($sentences, 0, 3)));

    if (strlen($summary) > 700) {
        $summary = substr($summary, 0, 697) . '...';
    }

    return $summary !== '' ? $summary : $fallbackTitle;
}

function knowledge_auto_keywords(string $text, string $title = ''): array
{
    $stop = array_flip([
        'about','after','again','also','among','and','are','because','been','before',
        'being','between','both','but','can','could','did','does','each','for','from',
        'had','has','have','how','into','its','may','more','most','not','other','our',
        'out','over','should','some','such','than','that','the','their','them','then',
        'there','these','they','this','those','through','under','very','was','were',
        'what','when','where','which','while','who','will','with','would','you','your',
    ]);

    $normalized = strtolower($title . ' ' . $text);
    $normalized = preg_replace('/[^a-z0-9\s-]/', ' ', $normalized) ?? '';
    $words = preg_split('/\s+/', $normalized) ?: [];
    $counts = [];

    foreach ($words as $word) {
        $word = trim($word, '-');

        if (
            strlen($word) < 4
            || isset($stop[$word])
            || ctype_digit($word)
        ) {
            continue;
        }

        $counts[$word] = ($counts[$word] ?? 0) + 1;
    }

    arsort($counts);
    return array_slice(array_keys($counts), 0, 24);
}

function knowledge_entry_identifier(int $assetId, string $title): string
{
    return 'uploaded-' . $assetId . '-' . slugify($title);
}

function knowledge_base_paths(): array
{
    return [
        'json' => NMM_ROOT . '/chat-knowledge-base/knowledge-base.json',
        'js' => NMM_ROOT . '/chat-knowledge-base/knowledge-base.js',
        'backup' => NMM_ROOT . '/storage/knowledge-backups',
    ];
}

function knowledge_write_base(array $data): void
{
    $paths = knowledge_base_paths();

    if (!is_writable($paths['json']) || !is_writable($paths['js'])) {
        throw new RuntimeException(
            'The knowledge-base JSON and JavaScript files must be writable by PHP.'
        );
    }

    if (!is_dir($paths['backup'])) {
        mkdir($paths['backup'], 0770, true);
    }

    copy(
        $paths['json'],
        $paths['backup'] . '/knowledge-' . gmdate('Ymd-His') . '.json'
    );

    $data['updated'] = gmdate('Y-m-d');
    $encoded = json_encode(
        $data,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_THROW_ON_ERROR
    );

    file_put_contents($paths['json'], $encoded . PHP_EOL, LOCK_EX);
    file_put_contents(
        $paths['js'],
        'window.DAVE_KNOWLEDGE_BASE = ' . $encoded . ';' . PHP_EOL,
        LOCK_EX
    );
}

function knowledge_publish_asset(array $asset): string
{
    $text = knowledge_clean_text((string)($asset['extracted_text'] ?? ''));

    if ($text === '') {
        throw new RuntimeException(
            'Add extracted text, notes, or a transcript before publishing this asset to chat.'
        );
    }

    $paths = knowledge_base_paths();
    $data = json_decode(
        (string)file_get_contents($paths['json']),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    $entryId = (string)($asset['entry_id'] ?? '');

    if ($entryId === '') {
        $entryId = knowledge_entry_identifier(
            (int)$asset['id'],
            (string)$asset['title']
        );
    }

    $keywords = array_values(array_filter(array_map(
        'trim',
        preg_split('/[\r\n,]+/', (string)($asset['keywords'] ?? '')) ?: []
    )));

    if (!$keywords) {
        $keywords = knowledge_auto_keywords(
            $text,
            (string)$asset['title']
        );
    }

    $audiences = json_decode(
        (string)($asset['audiences_json'] ?? '[]'),
        true
    );

    if (!is_array($audiences) || !$audiences) {
        $audiences = ['recruiter', 'investor', 'client'];
    }

    $summary = trim((string)($asset['summary'] ?? ''));

    if ($summary === '') {
        $summary = knowledge_auto_summary(
            $text,
            (string)$asset['title']
        );
    }

    $entry = [
        'id' => $entryId,
        'title' => (string)$asset['title'],
        'category' => (string)$asset['category'],
        'keywords' => $keywords,
        'summary' => $summary,
        'answer' => $text,
        'searchText' => substr($text, 0, 60000),
        'source' => 'Uploaded knowledge: ' . (string)$asset['original_name'],
        'audiences' => array_values(array_intersect(
            $audiences,
            ['recruiter', 'investor', 'client']
        )),
        'rich' => [
            'type' => 'media',
            'label' => 'Knowledge source',
            'mediaType' => (string)$asset['media_kind'],
            'mimeType' => (string)$asset['mime_type'],
            'extension' => (string)$asset['extension'],
            'title' => (string)$asset['title'],
            'description' => $summary,
            'url' => 'knowledge-media.php?id=' . (int)$asset['id'],
            'downloadUrl' => 'knowledge-media.php?id=' . (int)$asset['id'] . '&download=1',
            'originalName' => (string)$asset['original_name'],
            'sizeBytes' => (int)$asset['size_bytes'],
        ],
    ];

    $found = false;

    foreach ($data['entries'] as $index => $existing) {
        if (($existing['id'] ?? '') === $entryId) {
            $data['entries'][$index] = $entry;
            $found = true;
            break;
        }
    }

    if (!$found) {
        $data['entries'][] = $entry;
    }

    knowledge_write_base($data);
    return $entryId;
}

function knowledge_remove_published_entry(?string $entryId): void
{
    if (!$entryId) {
        return;
    }

    $paths = knowledge_base_paths();
    $data = json_decode(
        (string)file_get_contents($paths['json']),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    $before = count($data['entries'] ?? []);
    $data['entries'] = array_values(array_filter(
        $data['entries'] ?? [],
        static fn (array $entry): bool =>
            (string)($entry['id'] ?? '') !== $entryId
    ));

    if (count($data['entries']) !== $before) {
        knowledge_write_base($data);
    }
}
