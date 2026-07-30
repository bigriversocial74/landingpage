from pathlib import Path

path = Path('tools/apply-rich-blog-media-v66a.py')
source = path.read_text(encoding='utf-8')


def remove_block(marker: str) -> None:
    global source
    start = source.find(marker)
    if start < 0:
        return
    end = source.find('\nreplace_once(', start + len(marker))
    if end < 0:
        end = source.find("\nprint('Rich Blog Media", start + len(marker))
    if end < 0:
        raise SystemExit(f'Unable to close patch block: {marker}')
    source = source[:start] + source[end + 1:]


def swap_block(marker: str, replacement: str) -> None:
    global source
    start = source.find(marker)
    if start < 0:
        raise SystemExit(f'Unable to locate patch block: {marker}')
    end = source.find('\nreplace_once(', start + len(marker))
    if end < 0:
        raise SystemExit(f'Unable to close patch block: {marker}')
    source = source[:start] + replacement + '\n' + source[end + 1:]


remove_block(
    'replace_once(\n    "portal/publishing-admin.php",\n'
    '    "Body content supports'
)
remove_block(
    'replace_once(\n    "portal/publishing-admin.php",\n'
    '    "Use ## Heading'
)

swap_block(
    'replace_once(\n    "portal/blog-feed-output.php",\n'
    "    '''        if ($cover !== '') {",
    '''replace_once(
    "portal/blog-feed-output.php",
    '        $xml .= "</item>\\\\n";',
    \'\'\'        if ($audio) {
            $xml .= '<enclosure url="' . publishing_feed_xml($audio['url']) . '" length="' . (int)$audio['length'] . '" type="' . publishing_feed_xml($audio['type']) . '" />' . "\\\\n";
            $xml .= '<media:content url="' . publishing_feed_xml($audio['url']) . '" medium="audio" type="' . publishing_feed_xml($audio['type']) . '"' . ($audio['duration_seconds'] ? ' duration="' . (int)$audio['duration_seconds'] . '"' : '') . '><media:title>' . publishing_feed_xml($audio['title']) . '</media:title></media:content>' . "\\\\n";
            if ($audio['duration_seconds']) $xml .= '<itunes:duration>' . (int)$audio['duration_seconds'] . "</itunes:duration>\\\\n";
        }
        $xml .= "</item>\\\\n";\'\'\',
)'''
)

swap_block(
    'replace_once(\n    "portal/blog-feed-output.php",\n'
    "    '''        foreach ($post['tags'] as $tag) {",
    '''replace_once(
    "portal/blog-feed-output.php",
    '        $xml .= "</entry>\\\\n";',
    \'\'\'        if ($audio) {
            $xml .= '<link rel="enclosure" href="' . publishing_feed_xml($audio['url']) . '" type="' . publishing_feed_xml($audio['type']) . '" length="' . (int)$audio['length'] . '" title="' . publishing_feed_xml($audio['title']) . '" />' . "\\\\n";
        }
        $xml .= "</entry>\\\\n";\'\'\',
)'''
)

workflow_marker = 'replace_once(\n    ".github/workflows/portal-quality.yml",\n'
remove_block(workflow_marker)
remove_block(workflow_marker)

path.write_text(source, encoding='utf-8')
print('Rich Blog Media patch boundaries normalized.')
