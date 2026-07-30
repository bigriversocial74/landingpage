from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding='utf-8')


def replace_once(content: str, old: str, new: str, label: str) -> str:
    count = content.count(old)
    if count != 1:
        raise SystemExit(f'{label}: expected one anchor, found {count}')
    return content.replace(old, new, 1)


core = read('portal/public-syndication.php')
core = replace_once(
    core,
    """require_once __DIR__ . '/publishing.php';
require_once __DIR__ . '/publishing-workflow.php';
require_once __DIR__ . '/blog-rich-media.php';
""",
    """if (!function_exists('blog_public_posts')) {
    require_once __DIR__ . '/publishing.php';
}
if (
    !function_exists('publishing_blog_settings')
    || !function_exists('publishing_absolute_url')
) {
    require_once __DIR__ . '/publishing-workflow.php';
}
if (!function_exists('blog_rich_media_first_enclosure')) {
    require_once __DIR__ . '/blog-rich-media.php';
}
""",
    'conditional syndication dependencies',
)
core = replace_once(
    core,
    '''function syndication_public_posts(array $filter, int $limit = 30): array
{
    if (!publishing_schema_available()) return [];
    $limit = max(1, min(100, $limit));
    $where = [
        'post.status="published"',
        '(post.published_at IS NULL OR post.published_at<=UTC_TIMESTAMP())',
    ];
    $params = [];
    $category = trim((string)($filter['category'] ?? ''));
    $tag = trim((string)($filter['tag'] ?? ''));
    $author = trim((string)($filter['author'] ?? ''));
''',
    '''function syndication_public_posts(array $filter, int $limit = 30): array
{
    $limit = max(1, min(100, $limit));
    $category = trim((string)($filter['category'] ?? ''));
    $tag = trim((string)($filter['tag'] ?? ''));
    $author = trim((string)($filter['author'] ?? ''));
    if ($tag === '' && $author === '' && function_exists('blog_public_posts')) {
        return blog_public_posts($category, null, $limit, 0);
    }
    if (!publishing_schema_available()) return [];
    $where = [
        'post.status="published"',
        '(post.published_at IS NULL OR post.published_at<=UTC_TIMESTAMP())',
    ];
    $params = [];
''',
    'existing Blog query continuity',
)
write('portal/public-syndication.php', core)

retained = read('tests/rss-feed-reader-v62.php')
retained = replace_once(
    retained,
    """    'output' => 'portal/blog-feed-output.php',
    'blog' => 'blog.php',
""",
    """    'output' => 'portal/blog-feed-output.php',
    'syndication' => 'portal/public-syndication.php',
    'blog' => 'blog.php',
""",
    'retained syndication source path',
)
retained = replace_once(
    retained,
    """    'RSS discovery' => ['application/rss+xml', $source['blog'] . $source['post']],
    'Atom discovery' => ['application/atom+xml', $source['blog'] . $source['post']],
""",
    """    'RSS discovery' => ['application/rss+xml', $source['blog'] . $source['post'] . $source['syndication']],
    'Atom discovery' => ['application/atom+xml', $source['blog'] . $source['post'] . $source['syndication']],
""",
    'retained centralized discovery contract',
)
write('tests/rss-feed-reader-v62.php', retained)

source_test = read('tests/public-syndication-v66e.php')
source_test = replace_once(
    source_test,
    """    'tools/refine-public-syndication-v66e.py','.github/workflows/refine-public-syndication-v66e.yml',
""",
    """    'tools/refine-public-syndication-v66e.py','.github/workflows/refine-public-syndication-v66e.yml',
    'tools/fix-syndication-retained-rss-v66e.py','.github/workflows/fix-syndication-retained-rss-v66e.yml',
""",
    'temporary retained repair cleanup',
)
write('tests/public-syndication-v66e.php', source_test)

print('Retained RSS compatibility repair applied.')
