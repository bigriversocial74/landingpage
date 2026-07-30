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
    """    $feed = [
        'version'=>'https://jsonfeed.org/version/1.1',
        'title'=>$context['title'],
        'home_page_url'=>$context['blog_url'],
        'feed_url'=>$context['self_url'],
        'description'=>$context['description'],
        'language'=>$context['settings']['feed_language'],
        'authors'=>[['name'=>$context['settings']['podcast_author']]],
        'items'=>$items,
    ];
""",
    """    $feed = [
        'version'=>'https://jsonfeed.org/version/1.1',
        'user_comment'=>'This feed can be added to a compatible reader using its feed_url.',
        'title'=>$context['title'],
        'home_page_url'=>$context['blog_url'],
        'feed_url'=>$context['self_url'],
        'description'=>$context['description'],
        'language'=>$context['settings']['feed_language'],
        'authors'=>[['name'=>$context['settings']['podcast_author']]],
        'items'=>$items,
    ];
    if ($context['settings']['websub_enabled']) {
        $feed['hubs'] = [[
            'type'=>'WebSub',
            'url'=>$context['settings']['websub_hub_url'],
        ]];
    }
""",
    'JSON Feed hubs',
)
core = replace_once(
    core,
    """    $xml .= '<podcast:locked owner="' . syndication_xml($settings['podcast_owner_email']) . '">no</podcast:locked>' . "\n";
    if ($settings['podcast_owner_email'] !== '') {
        $xml .= '<itunes:owner><itunes:name>' . syndication_xml($settings['podcast_owner_name']) . '</itunes:name><itunes:email>' . syndication_xml($settings['podcast_owner_email']) . "</itunes:email></itunes:owner>\n";
    }
""",
    """    if ($settings['podcast_owner_email'] !== '') {
        $xml .= '<podcast:locked owner="' . syndication_xml($settings['podcast_owner_email']) . '">no</podcast:locked>' . "\n";
        $xml .= '<itunes:owner><itunes:name>' . syndication_xml($settings['podcast_owner_name']) . '</itunes:name><itunes:email>' . syndication_xml($settings['podcast_owner_email']) . "</itunes:email></itunes:owner>\n";
    }
""",
    'podcast owner metadata',
)
write('portal/public-syndication.php', core)

test = read('tests/public-syndication-db-v66e.php')
test = replace_once(
    test,
    """require_once NMM_ROOT . '/portal/public-syndication.php';
require_once NMM_ROOT . '/portal/blog-feed-output.php';
""",
    """require_once NMM_ROOT . '/portal/public-syndication.php';
require_once NMM_ROOT . '/portal/blog-feed-output.php';
require_once NMM_ROOT . '/portal/webmention-service.php';
""",
    'database Webmention service include',
)
test = replace_once(
    test,
    """    $json=syndication_render_json_feed(['category'=>'','tag'=>'open-web','author'=>'']);
    $decoded=json_decode($json,true,512,JSON_THROW_ON_ERROR);
""",
    """    $pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES("blog_websub_hub_url","https://hub.example.test/"),("blog_websub_enabled","1") ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute();
    $json=syndication_render_json_feed(['category'=>'','tag'=>'open-web','author'=>'']);
    $decoded=json_decode($json,true,512,JSON_THROW_ON_ERROR);
""",
    'database JSON hub setup',
)
test = replace_once(
    test,
    """    if(!str_contains((string)($decoded['feed_url']??''),'tag=open-web')) $fail('JSON Feed self URL filter failed.');

    $_GET=['tag'=>'open-web'];
""",
    """    if(!str_contains((string)($decoded['feed_url']??''),'tag=open-web')) $fail('JSON Feed self URL filter failed.');
    if(($decoded['hubs'][0]['type']??'')!=='WebSub' || ($decoded['hubs'][0]['url']??'')!=='https://hub.example.test/') $fail('JSON Feed WebSub hub rendering failed.');

    $_GET=['tag'=>'open-web'];
""",
    'database JSON hub assertion',
)
test = replace_once(
    test,
    """    if(!str_contains($podcast,'<itunes:type>episodic</itunes:type>')) $fail('Podcast channel metadata failed.');

    $pdo->prepare(
""",
    """    if(!str_contains($podcast,'<itunes:type>episodic</itunes:type>')) $fail('Podcast channel metadata failed.');
    if(str_contains($podcast,'<podcast:locked owner="">')) $fail('Empty podcast owner metadata rendered.');

    $pdo->prepare(
""",
    'database podcast owner assertion',
)
write('tests/public-syndication-db-v66e.php', test)

source_test = read('tests/public-syndication-v66e.php')
source_test = replace_once(
    source_test,
    """    ['JSON MIME','application/feed+json',$source['core'].$source['json']],
""",
    """    ['JSON MIME','application/feed+json',$source['core'].$source['json']],
    ['JSON WebSub hubs','$feed[\\'hubs\\']',$source['core']],
""",
    'source JSON hub assertion',
)
source_test = replace_once(
    source_test,
    """    'tools/fix-public-syndication-security-v66e.py','.github/workflows/fix-public-syndication-security-v66e.yml',
""",
    """    'tools/fix-public-syndication-security-v66e.py','.github/workflows/fix-public-syndication-security-v66e.yml',
    'tools/refine-public-syndication-v66e.py','.github/workflows/refine-public-syndication-v66e.yml',
""",
    'source cleanup assertion',
)
write('tests/public-syndication-v66e.php', source_test)

print('Public Syndication v66E standards refinements applied.')
