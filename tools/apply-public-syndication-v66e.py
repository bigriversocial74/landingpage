from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding="utf-8")


def replace_once(content: str, old: str, new: str, label: str) -> str:
    count = content.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected one anchor, found {count}")
    return content.replace(old, new, 1)


# Administrator route and action integration.
admin = read("portal/admin.php")
admin = replace_once(
    admin,
    "require_once __DIR__ . '/unified-inbox.php';\n",
    "require_once __DIR__ . '/unified-inbox.php';\nrequire_once __DIR__ . '/syndication-admin.php';\n",
    "admin require",
)
admin = replace_once(
    admin,
    "'portfolio','blog','events'",
    "'portfolio','blog','syndication','events'",
    "admin allowed view",
)
admin = replace_once(
    admin,
    "    try{\n        if(unified_inbox_handle_admin_action($action,$user)){",
    "    try{\n        if(syndication_handle_admin_action($action,$user)){\n            exit;\n        }\n        if(unified_inbox_handle_admin_action($action,$user)){",
    "admin action handler",
)
admin = replace_once(
    admin,
    "if($view==='feeds'){\n    feed_reader_render($user);",
    "if($view==='syndication'){\n    syndication_render_admin($user);\n    portal_footer();\n    exit;\n}\n\nif($view==='feeds'){\n    feed_reader_render($user);",
    "admin render",
)
write("portal/admin.php", admin)

# Administrator navigation and conditional stylesheet.
bootstrap = read("portal/bootstrap.php")
bootstrap = replace_once(
    bootstrap,
    "            'blog' => 'Blog',\n            'feeds' => 'Feed Reader',",
    "            'blog' => 'Blog',\n            'syndication' => 'Syndication',\n            'feeds' => 'Feed Reader',",
    "bootstrap navigation",
)
bootstrap = replace_once(
    bootstrap,
    "    <?php if($active==='inbox'):?><link rel=\"stylesheet\" href=\"<?= e(app_url('assets/css/unified-inbox.css?v=20260730-v66D')) ?>\"><?php endif;?>\n",
    "    <?php if($active==='inbox'):?><link rel=\"stylesheet\" href=\"<?= e(app_url('assets/css/unified-inbox.css?v=20260730-v66D')) ?>\"><?php endif;?>\n    <?php if($active==='syndication'):?><link rel=\"stylesheet\" href=\"<?= e(app_url('assets/css/syndication-admin.css?v=20260730-v66E')) ?>\"><?php endif;?>\n",
    "bootstrap stylesheet",
)
write("portal/bootstrap.php", bootstrap)

# Publication hook queues WebSub without blocking the editor.
publishing_admin = read("portal/publishing-admin.php")
publishing_admin = replace_once(
    publishing_admin,
    "require_once __DIR__ . '/content-interactions-admin.php';\n",
    "require_once __DIR__ . '/content-interactions-admin.php';\nrequire_once __DIR__ . '/websub-service.php';\n",
    "publishing admin require",
)
publishing_admin = replace_once(
    publishing_admin,
    "        flash('success', $message);\n        redirect(\n            'portal/admin.php?view=blog&edit=' . $id\n        );",
    "        if ($status === 'published') {\n            syndication_queue_websub(\n                $event === 'blog_post_created' ? 'publish' : 'update',\n                (int)$user['id'],\n                $id\n            );\n        }\n        flash('success', $message);\n        redirect(\n            'portal/admin.php?view=blog&edit=' . $id\n        );",
    "publishing WebSub hook",
)
write("portal/publishing-admin.php", publishing_admin)

# Extend the existing RSS and Atom renderer instead of replacing it.
feed = read("portal/blog-feed-output.php")
feed = replace_once(
    feed,
    "/* North Mountain Media build: 20260730-rich-blog-media-v66A */\n",
    "/* North Mountain Media build: 20260730-public-syndication-v66E */\n\nrequire_once __DIR__ . '/public-syndication.php';\n",
    "feed require",
)
context_pattern = re.compile(
    r"function publishing_feed_context\(string \$format\): array\n\{.*?\n\}\n\nfunction publishing_feed_send",
    re.S,
)
context_replacement = """function publishing_feed_context(string $format): array
{
    $filter = syndication_filter_from_request();
    $context = syndication_context($format === 'atom' ? 'atom' : 'rss', $filter);
    $context['category'] = (string)$filter['category'];
    return $context;
}

function publishing_feed_send"""
feed, count = context_pattern.subn(context_replacement, feed, count=1)
if count != 1:
    raise SystemExit(f"feed context: expected one function, found {count}")
feed = feed.replace("North Mountain Media Portal v66A", "North Mountain Media Portal v66E")
feed = replace_once(
    feed,
    "    $xml .= '<atom:link href=\"' . publishing_feed_xml($context['self_url']) . '\" rel=\"self\" type=\"application/rss+xml\" />' . \"\\n\";\n",
    "    $xml .= '<atom:link href=\"' . publishing_feed_xml($context['self_url']) . '\" rel=\"self\" type=\"application/rss+xml\" />' . \"\\n\";\n    if ($settings['websub_enabled']) {\n        $xml .= '<atom:link href=\"' . publishing_feed_xml($settings['websub_hub_url']) . '\" rel=\"hub\" />' . \"\\n\";\n    }\n    if ($settings['json_enabled']) {\n        $xml .= '<atom:link href=\"' . publishing_feed_xml(publishing_absolute_url('blog-json-feed.php' . syndication_filter_query($context['filter']))) . '\" rel=\"alternate\" type=\"application/feed+json\" />' . \"\\n\";\n    }\n",
    "rss discovery",
)
feed = replace_once(
    feed,
    "publishing_absolute_url('blog-atom.php' . ($context['category'] !== '' ? '?category=' . rawurlencode($context['category']) : ''))",
    "publishing_absolute_url('blog-atom.php' . syndication_filter_query($context['filter']))",
    "rss atom filter",
)
feed = replace_once(
    feed,
    "    $rssPath = 'blog-feed.php' . ($context['category'] !== '' ? '?category=' . rawurlencode($context['category']) : '');",
    "    $rssPath = 'blog-feed.php' . syndication_filter_query($context['filter']);",
    "atom rss filter",
)
feed = replace_once(
    feed,
    "    $xml .= '<link rel=\"self\" type=\"application/atom+xml\" href=\"' . publishing_feed_xml($context['self_url']) . '\" />' . \"\\n\";\n",
    "    $xml .= '<link rel=\"self\" type=\"application/atom+xml\" href=\"' . publishing_feed_xml($context['self_url']) . '\" />' . \"\\n\";\n    if ($settings['websub_enabled']) {\n        $xml .= '<link rel=\"hub\" href=\"' . publishing_feed_xml($settings['websub_hub_url']) . '\" />' . \"\\n\";\n    }\n    if ($settings['json_enabled']) {\n        $xml .= '<link rel=\"alternate\" type=\"application/feed+json\" href=\"' . publishing_feed_xml(publishing_absolute_url('blog-json-feed.php' . syndication_filter_query($context['filter']))) . '\" />' . \"\\n\";\n    }\n",
    "atom discovery",
)
write("portal/blog-feed-output.php", feed)

# Public archive discovery and feed directory link.
blog = read("blog.php")
blog = replace_once(
    blog,
    "require_once __DIR__ . '/portal/publishing-workflow.php';\n",
    "require_once __DIR__ . '/portal/publishing-workflow.php';\nrequire_once __DIR__ . '/portal/public-syndication.php';\n",
    "blog require",
)
blog_feed_pattern = re.compile(
    r"<\?php if\(\$blogSettings\['rss_enabled'\]\):\?>.*?<\?php endif;\?>\n<\?php if\(\$blogSettings\['atom_enabled'\]\):\?>.*?<\?php endif;\?>",
    re.S,
)
blog, count = blog_feed_pattern.subn(
    "<?=syndication_discovery_links(['category'=>$category,'tag'=>'','author'=>''])?>",
    blog,
    count=1,
)
if count != 1:
    raise SystemExit(f"blog discovery: expected one block, found {count}")
blog = replace_once(
    blog,
    "<a class=\"<?=$category===''?'active':''?>\" href=\"<?=e(app_url('blog.php'))?>\">All posts</a>",
    "<a class=\"<?=$category===''?'active':''?>\" href=\"<?=e(app_url('blog.php'))?>\">All posts</a>\n<a href=\"<?=e(app_url('blog-feeds.php'))?>\">Feeds &amp; podcast</a>",
    "blog feed directory link",
)
write("blog.php", blog)

# Article discovery and approved Webmention display.
post = read("blog-post.php")
post = replace_once(
    post,
    "require_once __DIR__ . '/portal/content-interactions.php';\n",
    "require_once __DIR__ . '/portal/content-interactions.php';\nrequire_once __DIR__ . '/portal/webmention-service.php';\n",
    "post require",
)
post = replace_once(
    post,
    "    : ['schema_ready'=>false,'settings'=>[],'comments'=>[],'comment_count'=>0,'reactions'=>[],'viewer_reaction'=>''];\n",
    "    : ['schema_ready'=>false,'settings'=>[],'comments'=>[],'comment_count'=>0,'reactions'=>[],'viewer_reaction'=>''];\n$webmentions = $post && !$isAdminPreview\n    ? syndication_approved_webmentions((int)$post['id'])\n    : [];\n",
    "post webmention context",
)
post_feed_pattern = re.compile(
    r"<\?php \$feedSettings=publishing_blog_settings\(\);\?>\n<\?php if\(\$feedSettings\['rss_enabled'\]\):\?>.*?<\?php endif;\?>\n<\?php if\(\$feedSettings\['atom_enabled'\]\):\?>.*?<\?php endif;\?>",
    re.S,
)
post, count = post_feed_pattern.subn(
    "<?=syndication_discovery_links(['category'=>'','tag'=>'','author'=>''], !$isAdminPreview)?>",
    post,
    count=1,
)
if count != 1:
    raise SystemExit(f"post discovery: expected one block, found {count}")
post = replace_once(
    post,
    "<link rel=\"stylesheet\" href=\"<?=e(app_url('assets/css/content-interactions.css?v=20260730-v66C'))?>\">",
    "<link rel=\"stylesheet\" href=\"<?=e(app_url('assets/css/content-interactions.css?v=20260730-v66C'))?>\">\n<link rel=\"stylesheet\" href=\"<?=e(app_url('assets/css/public-syndication.css?v=20260730-v66E'))?>\">",
    "post syndication stylesheet",
)
webmention_markup = """<?php if($webmentions):?>
<section class="webmentions" aria-labelledby="webmentions-title">
<h2 id="webmentions-title">From around the web</h2>
<div class="webmention-list">
<?php foreach($webmentions as $mention):?>
<article class="webmention-item">
<div class="webmention-avatar" aria-hidden="true"><?=e(mb_strtoupper(mb_substr($mention['author_name']?:'W',0,1)))?></div>
<div>
<strong><a href="<?=e($mention['source_url'])?>" rel="ugc nofollow noopener noreferrer" target="_blank"><?=e($mention['author_name']?:$mention['source_title']?:'External mention')?></a></strong>
<span><?=e(status_label($mention['mention_type']))?> · <?=e(format_datetime($mention['received_at']))?></span>
<?php if($mention['source_excerpt']):?><small><?=e($mention['source_excerpt'])?></small><?php endif;?>
</div>
</article>
<?php endforeach;?>
</div>
</section>
<?php endif;?>

"""
post = replace_once(
    post,
    "<?php if(!$isAdminPreview):?><?php content_interactions_render_public($post,$viewer,$interactionContext);?><?php endif;?>",
    webmention_markup + "<?php if(!$isAdminPreview):?><?php content_interactions_render_public($post,$viewer,$interactionContext);?><?php endif;?>",
    "post Webmention display",
)
write("blog-post.php", post)

# Fresh-install schema contains the same additive tables and defaults exactly once.
schema = read("database/north_mountain_portal.sql")
marker = "-- Public Syndication v66E fresh-install schema"
if marker in schema:
    raise SystemExit("fresh schema already contains v66E marker")
migration = read("database/public_syndication_v66e.sql")
start = migration.index("CREATE TABLE IF NOT EXISTS syndication_webmentions")
end = migration.index("SELECT 'North Mountain Media Public Syndication v66E migration complete'")
append = migration[start:end].rstrip()
schema = schema.rstrip() + "\n\n" + marker + "\n" + append + "\n"
write("database/north_mountain_portal.sql", schema)

print("Public Syndication v66E integration applied.")
