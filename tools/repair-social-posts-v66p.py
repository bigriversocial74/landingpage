from pathlib import Path

source = Path("tests/social-posts-v66p.php")
text = source.read_text(encoding="utf-8")
if "'activitypub' => 'portal/activitypub-service.php'" not in text:
    text = text.replace(
        "    'service' => 'portal/social-posts-service.php',",
        "    'service' => 'portal/social-posts-service.php',\n    'activitypub' => 'portal/activitypub-service.php',",
        1,
    )
anchor = "$expect('service', 'activitypub_queue_approved_followers', 'Delivery must reuse approved ActivityPub followers.');"
addition = anchor + "\n$expect('activitypub', 'activitypub_payload_is_public', 'Public outbox audience filtering is required.');\n$expect('activitypub', 'AND payload_json LIKE :public_marker', 'Public outbox must prefilter public payload candidates.');"
if "Public outbox audience filtering is required." not in text:
    if anchor not in text:
        raise SystemExit("Source privacy test anchor missing")
    text = text.replace(anchor, addition, 1)
if "'.github/workflows/apply-private-outbox-v66p.yml'" not in text:
    text = text.replace(
        "    '.github/workflows/apply-social-posts-navigation-v66p.yml',",
        "    '.github/workflows/apply-social-posts-navigation-v66p.yml',\n    '.github/workflows/apply-private-outbox-v66p.yml',\n    '.github/workflows/repair-social-posts-v66p.yml',\n    'tools/repair-social-posts-v66p.py',",
        1,
    )
source.write_text(text, encoding="utf-8")


dbtest = Path("tests/social-posts-db-v66p.php")
text = dbtest.read_text(encoding="utf-8")
anchor = """    if (in_array($followersId, $publicIds, true)) {
        $fail('Followers-only post leaked into the public feed.');
    }
"""
addition = anchor + """

    $publicOutbox = activitypub_outbox_document();
    if ((int)($publicOutbox['totalItems'] ?? 0) !== 3) {
        $fail('Public outbox count includes a follower-only activity or omits a public activity.');
    }
    foreach (($publicOutbox['orderedItems'] ?? []) as $activity) {
        if (!is_array($activity) || !activitypub_payload_is_public($activity)) {
            $fail('Public outbox exposed a non-public activity.');
        }
        $objectUri = (string)($activity['object']['id'] ?? $activity['object'] ?? '');
        if ($objectUri === social_posts_object_url($followers)) {
            $fail('Followers-only social post leaked into the public ActivityPub outbox.');
        }
    }
"""
if "Followers-only social post leaked into the public ActivityPub outbox." not in text:
    if anchor not in text:
        raise SystemExit("Database privacy test anchor missing")
    text = text.replace(anchor, addition, 1)
dbtest.write_text(text, encoding="utf-8")


audit = Path("SOCIAL-POSTS-AUDIT-v66P.md")
text = audit.read_text(encoding="utf-8")
if "public outbox could expose follower-only payloads" not in text:
    text = text.replace(
        "- no Social Posts migration, retained evidence, or MySQL/MariaDB certification",
        "- no Social Posts migration, retained evidence, or MySQL/MariaDB certification\n- the inherited public outbox could expose follower-only payloads without audience filtering",
        1,
    )
if "public outbox contains only verified Public-audience activities" not in text:
    text = text.replace(
        "- protected media remains same-origin; external links require HTTPS",
        "- protected media remains same-origin; external links require HTTPS\n- the public outbox contains only verified Public-audience activities",
        1,
    )
audit.write_text(text, encoding="utf-8")


setup = Path("SOCIAL-POSTS-SETUP-v66P.md")
text = setup.read_text(encoding="utf-8")
if "public ActivityPub outbox excludes follower-only" not in text:
    text = text.replace(
        "- Confirm the existing blog archive and `blog-feed.php` continue working unchanged.",
        "- Confirm the existing blog archive and `blog-feed.php` continue working unchanged.\n- Confirm the public ActivityPub outbox excludes follower-only social posts and Stories.",
        1,
    )
setup.write_text(text, encoding="utf-8")


score = Path("V66P-SCORECARD.md")
text = score.read_text(encoding="utf-8")
if "Public outbox audience privacy" not in text:
    text = text.replace(
        "| Media/link privacy and safety | 6.0 | 10.0 |",
        "| Media/link privacy and safety | 6.0 | 10.0 |\n| Public outbox audience privacy | 3.0 | 10.0 |",
        1,
    )
score.write_text(text, encoding="utf-8")


validation = Path("V66P-VALIDATION.txt")
text = validation.read_text(encoding="utf-8")
if "public ActivityPub outbox audience filtering" not in text:
    text = text.replace(
        "- approved-follower delivery through the existing ActivityPub outbox",
        "- approved-follower delivery through the existing ActivityPub outbox\n- public ActivityPub outbox audience filtering that excludes follower-only payloads",
        1,
    )
validation.write_text(text, encoding="utf-8")

Path("tools/repair-social-posts-v66p.py").unlink(missing_ok=True)
print("v66P source, tests, and documentation finalized")
