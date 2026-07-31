<?php
declare(strict_types=1);

/*
 * North Mountain Media administrator front controller.
 *
 * The long-lived administrator controller remains isolated in
 * admin-controller.php. This front controller adds the v66K Automation Action
 * Center without weakening the existing authentication, CSRF, rate-limit, or
 * role boundaries.
 */

$automationView = (string)($_GET['view'] ?? '') === 'automation';

function automation_admin_front_controller_decorate(string $html): string
{
    if (!function_exists('app_url') || !str_contains($html, 'data-admin-navigation')) {
        return $html;
    }

    $active = (string)($_GET['view'] ?? '') === 'automation';
    $link = '<a class="' . ($active ? 'active' : '') . '" href="'
        . e(app_url('portal/admin.php?view=automation'))
        . '">Action Center</a>';

    if (!str_contains($html, 'portal/admin.php?view=automation')) {
        $decorated = preg_replace(
            '/(<div\s+class="portal-nav-group-links"\s+id="admin-nav-system"[^>]*>)/s',
            '$1' . $link,
            $html,
            1
        );
        if (is_string($decorated)) {
            $html = $decorated;
        }
    }

    if ($active) {
        $css = '<link rel="stylesheet" href="'
            . e(app_url('assets/css/automation-center.css?v=20260731-v66K'))
            . '">';
        $javascript = '<script src="'
            . e(app_url('assets/js/automation-center.js?v=20260731-v66K'))
            . '" defer></script>';
        $html = str_replace('</head>', $css . '</head>', $html);
        $html = str_replace('</body>', $javascript . '</body>', $html);
    }

    return $html;
}

ob_start('automation_admin_front_controller_decorate');

if (!$automationView) {
    require __DIR__ . '/admin-controller.php';
    exit;
}

require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/automation-admin.php';
require_once __DIR__ . '/automation-recovery.php';

$user = require_role('admin');
automation_recover_interrupted_approvals_complete();

if (is_post()) {
    verify_csrf();
    enforce_authenticated_action_limit($user);

    try {
        $action = input('action');
        if (!automation_handle_admin_action($action, $user)) {
            throw new RuntimeException('Unsupported Automation Action Center request.');
        }
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
        automation_admin_redirect(
            trim((string)($_GET['section'] ?? 'overview')) ?: 'overview'
        );
    }
}

portal_header('Automation Action Center', 'automation', $user);
automation_render_admin($user);
portal_footer();
