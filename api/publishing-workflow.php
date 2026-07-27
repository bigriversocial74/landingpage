<?php
declare(strict_types=1);

require dirname(__DIR__) . '/portal/bootstrap.php';
require_once dirname(__DIR__) . '/portal/visitor-intelligence.php';
require_once dirname(__DIR__) . '/portal/publishing.php';
require_once dirname(__DIR__) . '/portal/publishing-workflow.php';

if (!is_post()) {
    json_response([
        'ok' => false,
        'message' => 'Method not allowed.',
    ], 405);
}

if (!same_origin_request()) {
    json_response([
        'ok' => false,
        'message' => 'Invalid request origin.',
    ], 403);
}

$user = current_user();

if (
    !$user
    || ($user['role'] ?? '') !== 'admin'
    || ($user['status'] ?? '') !== 'active'
) {
    json_response([
        'ok' => false,
        'message' => 'Administrator access is required.',
    ], 403);
}

verify_csrf();

$contentType = strtolower(
    (string)($_SERVER['CONTENT_TYPE'] ?? '')
);
$data = str_contains(
    $contentType,
    'application/json'
)
    ? json_decode(
        (string)file_get_contents('php://input'),
        true
    )
    : $_POST;

if (!is_array($data)) {
    json_response([
        'ok' => false,
        'message' => 'Invalid workflow payload.',
    ], 400);
}

$action = trim((string)($data['action'] ?? ''));

try {
    if ($action === 'autosave_blog') {
        $postId = max(
            0,
            (int)($data['post_id'] ?? 0)
        );

        if ($postId <= 0 || !blog_admin_post($postId)) {
            json_response([
                'ok' => false,
                'message' => 'Save the blog post once before autosave begins.',
            ], 422);
        }

        $snapshot = [
            'author_user_id' => max(
                0,
                (int)($data['author_user_id'] ?? $user['id'])
            ),
            'title' => substr(
                trim((string)($data['title'] ?? '')),
                0,
                190
            ),
            'slug' => substr(
                slugify((string)($data['slug'] ?? '')),
                0,
                190
            ),
            'status' => in_array(
                (string)($data['status'] ?? ''),
                ['draft', 'published', 'archived'],
                true
            )
                ? (string)$data['status']
                : 'draft',
            'featured' => !empty($data['featured']) ? 1 : 0,
            'category' => substr(
                trim((string)($data['category'] ?? '')),
                0,
                120
            ) ?: null,
            'excerpt' => trim(
                (string)($data['excerpt'] ?? '')
            ) ?: null,
            'body' => trim(
                (string)($data['body'] ?? '')
            ),
            'tags' => trim(
                (string)($data['tags'] ?? '')
            ) ?: null,
            'seo_title' => substr(
                trim((string)($data['seo_title'] ?? '')),
                0,
                190
            ) ?: null,
            'seo_description' => substr(
                trim((string)($data['seo_description'] ?? '')),
                0,
                320
            ) ?: null,
            'canonical_url' => substr(
                trim((string)($data['canonical_url'] ?? '')),
                0,
                500
            ) ?: null,
            'published_at' => trim(
                (string)($data['published_at'] ?? '')
            ) ?: null,
        ];

        publishing_save_blog_autosave(
            $postId,
            $snapshot,
            (int)$user['id']
        );

        json_response([
            'ok' => true,
            'saved_at' => gmdate('c'),
            'message' => 'Draft autosaved.',
        ]);
    }

    if ($action === 'autosave_resume') {
        $postId = max(
            0,
            (int)($data['post_id'] ?? 0)
        );

        if ($postId <= 0 || !resume_admin_post($postId)) {
            json_response([
                'ok' => false,
                'message' => 'Save the resume post once before autosave begins.',
            ], 422);
        }

        $postTypes = [
            'profile',
            'experience',
            'education',
            'skill_group',
            'strengths',
            'certification',
            'award',
            'project',
            'volunteer',
            'custom',
        ];

        $snapshot = [
            'title' => substr(
                trim((string)($data['title'] ?? '')),
                0,
                190
            ),
            'slug' => substr(
                slugify((string)($data['slug'] ?? '')),
                0,
                190
            ),
            'post_type' => in_array(
                (string)($data['post_type'] ?? ''),
                $postTypes,
                true
            )
                ? (string)$data['post_type']
                : 'experience',
            'column_name' => in_array(
                (string)($data['column_name'] ?? ''),
                ['main', 'sidebar'],
                true
            )
                ? (string)$data['column_name']
                : 'main',
            'status' => in_array(
                (string)($data['status'] ?? ''),
                ['draft', 'published', 'archived'],
                true
            )
                ? (string)$data['status']
                : 'draft',
            'featured' => !empty($data['featured']) ? 1 : 0,
            'sort_order' => max(
                0,
                (int)($data['sort_order'] ?? 100)
            ),
            'section_label' => trim(
                (string)($data['section_label'] ?? '')
            ) ?: null,
            'subtitle' => trim(
                (string)($data['subtitle'] ?? '')
            ) ?: null,
            'organization' => trim(
                (string)($data['organization'] ?? '')
            ) ?: null,
            'location' => trim(
                (string)($data['location'] ?? '')
            ) ?: null,
            'date_label' => trim(
                (string)($data['date_label'] ?? '')
            ) ?: null,
            'start_date' => trim(
                (string)($data['start_date'] ?? '')
            ) ?: null,
            'end_date' => trim(
                (string)($data['end_date'] ?? '')
            ) ?: null,
            'is_current' => !empty($data['is_current']) ? 1 : 0,
            'summary' => trim(
                (string)($data['summary'] ?? '')
            ) ?: null,
            'body' => trim(
                (string)($data['body'] ?? '')
            ) ?: null,
            'achievements' => trim(
                (string)($data['achievements'] ?? '')
            ) ?: null,
            'skills' => trim(
                (string)($data['skills'] ?? '')
            ) ?: null,
            'link_url' => trim(
                (string)($data['link_url'] ?? '')
            ) ?: null,
            'link_label' => trim(
                (string)($data['link_label'] ?? '')
            ) ?: null,
            'published_at' => trim(
                (string)($data['published_at'] ?? '')
            ) ?: null,
        ];

        publishing_save_resume_autosave(
            $postId,
            $snapshot,
            (int)$user['id']
        );

        json_response([
            'ok' => true,
            'saved_at' => gmdate('c'),
            'message' => 'Resume draft autosaved.',
        ]);
    }

    if ($action === 'reorder_resume') {
        $groups = is_array($data['groups'] ?? null)
            ? $data['groups']
            : [];

        publishing_reorder_resume_posts(
            $groups,
            (int)$user['id']
        );

        json_response([
            'ok' => true,
            'message' => 'Resume order saved.',
        ]);
    }

    json_response([
        'ok' => false,
        'message' => 'Unsupported publishing action.',
    ], 422);
} catch (Throwable $exception) {
    error_log(
        'North Mountain Media publishing workflow failed: '
        . $exception->getMessage()
    );

    json_response([
        'ok' => false,
        'message' => $exception->getMessage(),
    ], 500);
}
