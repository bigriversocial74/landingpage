from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    file = Path(path)
    text = file.read_text(encoding="utf-8")
    if new in text:
        return
    if old not in text:
        raise SystemExit(f"Repair anchor not found in {path}")
    file.write_text(text.replace(old, new, 1), encoding="utf-8")


service = Path("portal/social-posts-service.php")
text = service.read_text(encoding="utf-8")
text = text.replace(
    'WHEN :published="published" THEN COALESCE(published_at,UTC_TIMESTAMP())',
    'WHEN :publish_state="published" THEN COALESCE(published_at,UTC_TIMESTAMP())',
    1,
)
text = text.replace(
    'edited_at=CASE WHEN :published="published" THEN UTC_TIMESTAMP() ELSE edited_at END',
    'edited_at=CASE WHEN :edit_state="published" THEN UTC_TIMESTAMP() ELSE edited_at END',
    1,
)
text = text.replace(
    "        'published' => $status,\n        'id' => $postId,",
    "        'publish_state' => $status,\n        'edit_state' => $status,\n        'id' => $postId,",
    1,
)
if 'WHEN :published="published" THEN COALESCE(published_at,UTC_TIMESTAMP())' in text:
    raise SystemExit("Published-at PDO placeholder repair did not apply")
if 'edited_at=CASE WHEN :published="published"' in text:
    raise SystemExit("Edited-at PDO placeholder repair did not apply")
service.write_text(text, encoding="utf-8")

old_outbox = '''function activitypub_outbox_document(): array
{
    activitypub_require_schema();
    $count = (int)db()->query(
        'SELECT COUNT(*) FROM activitypub_outbox_activities
         WHERE activity_type IN ("Create","Update","Delete")'
    )->fetchColumn();
    $rows = db()->query(
        'SELECT payload_json FROM activitypub_outbox_activities
         WHERE activity_type IN ("Create","Update","Delete")
         ORDER BY published_at DESC,id DESC LIMIT 50'
    )->fetchAll();
    $items = [];
    foreach ($rows as $row) {
        $payload = json_decode((string)$row['payload_json'], true);
        if (is_array($payload)) $items[] = $payload;
    }
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => activitypub_outbox_url(),
        'type' => 'OrderedCollection',
        'totalItems' => $count,
        'orderedItems' => $items,
    ];
}
'''
new_outbox = '''function activitypub_audience_contains_public(mixed $audience): bool
{
    $public = 'https://www.w3.org/ns/activitystreams#Public';
    if (is_string($audience)) {
        return activitypub_normalize_url($audience) === activitypub_normalize_url($public);
    }
    if (!is_array($audience)) return false;
    foreach ($audience as $value) {
        if (activitypub_audience_contains_public($value)) return true;
    }
    return false;
}

function activitypub_payload_is_public(array $payload): bool
{
    foreach (['to', 'cc', 'audience'] as $field) {
        if (activitypub_audience_contains_public($payload[$field] ?? null)) return true;
    }
    $object = $payload['object'] ?? null;
    if (is_array($object)) {
        foreach (['to', 'cc', 'audience'] as $field) {
            if (activitypub_audience_contains_public($object[$field] ?? null)) return true;
        }
    }
    return false;
}

function activitypub_outbox_document(): array
{
    activitypub_require_schema();
    $statement = db()->prepare(
        'SELECT payload_json FROM activitypub_outbox_activities
         WHERE activity_type IN ("Create","Update","Delete")
           AND payload_json LIKE :public_marker
         ORDER BY published_at DESC,id DESC'
    );
    $statement->execute([
        'public_marker' => '%https://www.w3.org/ns/activitystreams#Public%',
    ]);
    $items = [];
    $count = 0;
    while ($row = $statement->fetch()) {
        $payload = json_decode((string)$row['payload_json'], true);
        if (!is_array($payload) || !activitypub_payload_is_public($payload)) continue;
        $count++;
        if (count($items) < 50) $items[] = $payload;
    }
    return [
        '@context' => 'https://www.w3.org/ns/activitystreams',
        'id' => activitypub_outbox_url(),
        'type' => 'OrderedCollection',
        'totalItems' => $count,
        'orderedItems' => $items,
    ];
}
'''
replace_once("portal/activitypub-service.php", old_outbox, new_outbox)
print("v66P repairs applied")
