from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    file = Path(path)
    text = file.read_text(encoding="utf-8")
    if new in text:
        return
    if old not in text:
        raise SystemExit(f"Finalization anchor not found in {path}")
    file.write_text(text.replace(old, new, 1), encoding="utf-8")


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


workflow = r'''name: POD Social Posts Quality

on:
  pull_request:
    branches: [main]
    paths:
      - '.github/workflows/social-posts-quality.yml'
      - 'portal/social-posts-service.php'
      - 'portal/activitypub-service.php'
      - 'portal/social-posts.php'
      - 'portal/bootstrap.php'
      - 'portal/federated-feed.php'
      - 'portal/site-builder-core.php'
      - 'activitypub-social-post.php'
      - 'social-post.php'
      - 'social-feed.php'
      - 'follow-pod.php'
      - 'landing-page.php'
      - 'assets/css/social-posts-v66p.css'
      - 'assets/js/social-posts-v66p.js'
      - 'database/social_posts_v66p.sql'
      - 'database/north_mountain_portal_v66p.sql'
      - 'tests/social-posts-v66p.php'
      - 'tests/social-posts-db-v66p.php'
      - 'SOCIAL-POSTS-AUDIT-v66P.md'
      - 'SOCIAL-POSTS-SETUP-v66P.md'
      - 'V66P-SCORECARD.md'
      - 'V66P-VALIDATION.txt'

permissions:
  contents: read

jobs:
  source-quality:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: PHP syntax
        run: |
          set -euo pipefail
          for file in \
            portal/social-posts-service.php portal/activitypub-service.php portal/social-posts.php \
            portal/bootstrap.php portal/federated-feed.php portal/site-builder-core.php \
            activitypub-social-post.php social-post.php social-feed.php follow-pod.php \
            landing-page.php tests/social-posts-v66p.php tests/social-posts-db-v66p.php; do
              php -l "$file"
          done
      - name: JavaScript syntax
        run: node --check assets/js/social-posts-v66p.js
      - name: Source, privacy, landing, and UX regression
        run: php tests/social-posts-v66p.php
      - name: CSS structure
        run: |
          python3 - <<'PY'
          from pathlib import Path
          css = Path('assets/css/social-posts-v66p.css').read_text(encoding='utf-8')
          if css.count('{') != css.count('}'):
              raise SystemExit('Unbalanced Social Posts CSS braces.')
          print('Social Posts CSS structure passed.')
          PY
      - name: Retained federation and Stories regressions
        run: |
          set -euo pipefail
          php tests/activitypub-v66f.php
          php tests/federated-timeline-v66h.php
          php tests/federated-interactions-v66g.php
          php tests/stories-v66o.php
      - name: Permanent cleanup contract
        run: |
          set -euo pipefail
          test ! -e .github/workflows/apply-social-posts-v66p.yml
          test ! -e .github/workflows/apply-social-posts-navigation-v66p.yml
          test ! -e .github/workflows/apply-private-outbox-v66p.yml
          test ! -e .github/workflows/repair-social-posts-v66p.yml
          test ! -e tools/apply-social-posts-v66p.py
          test ! -e tools/repair-social-posts-v66p.py
          grep -F "social_posts_render_landing" landing-page.php >/dev/null
          grep -F "social_posts_render_landing" portal/site-builder-core.php >/dev/null
          grep -F "social_posts_render_portal_stream" portal/federated-feed.php >/dev/null
          grep -F "'social-posts' => 'Social Posts'" portal/bootstrap.php >/dev/null
          grep -F "activitypub_payload_is_public" portal/activitypub-service.php >/dev/null

  mysql-8-integration:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.4
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: nmm
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping -h 127.0.0.1 -proot"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=12
    steps:
      - uses: actions/checkout@v4
      - name: Install PHP database extensions
        run: sudo apt-get update && sudo apt-get install -y php-mysql php-mbstring
      - name: Import cumulative schema and repeat-safe migration
        run: |
          set -euo pipefail
          mysql -h 127.0.0.1 -uroot -proot nmm < database/north_mountain_portal_v66p.sql
          mysql -h 127.0.0.1 -uroot -proot nmm < database/social_posts_v66p.sql
      - name: Live Social Posts integration
        env:
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_NAME: nmm
          DB_USER: root
          DB_PASS: root
        run: php tests/social-posts-db-v66p.php

  mariadb-integration:
    runs-on: ubuntu-latest
    services:
      mariadb:
        image: mariadb:11.4
        env:
          MARIADB_ROOT_PASSWORD: root
          MARIADB_DATABASE: nmm
        ports:
          - 3307:3306
        options: >-
          --health-cmd="healthcheck.sh --connect --innodb_initialized"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=12
    steps:
      - uses: actions/checkout@v4
      - name: Install MariaDB client and PHP extensions
        run: sudo apt-get update && sudo apt-get install -y mariadb-client php-mysql php-mbstring
      - name: Import cumulative schema and repeat-safe migration
        run: |
          set -euo pipefail
          mariadb -h 127.0.0.1 -P 3307 -uroot -proot nmm < database/north_mountain_portal_v66p.sql
          mariadb -h 127.0.0.1 -P 3307 -uroot -proot nmm < database/social_posts_v66p.sql
      - name: Live Social Posts integration
        env:
          DB_HOST: 127.0.0.1
          DB_PORT: 3307
          DB_NAME: nmm
          DB_USER: root
          DB_PASS: root
        run: php tests/social-posts-db-v66p.php
'''
Path(".github/workflows/social-posts-quality.yml").write_text(workflow, encoding="utf-8")

for temporary in [
    ".github/workflows/apply-private-outbox-v66p.yml",
    ".github/workflows/repair-social-posts-v66p.yml",
    "tools/repair-social-posts-v66p.py",
]:
    Path(temporary).unlink(missing_ok=True)

print("v66P final certification diff assembled")
