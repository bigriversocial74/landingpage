from pathlib import Path


def read(path: str) -> str:
    return Path(path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    Path(path).write_text(content, encoding="utf-8")


def replace_once(text: str, old: str, new: str, label: str) -> str:
    if old not in text:
        raise SystemExit(f"Missing finalization anchor: {label}")
    return text.replace(old, new, 1)


# Hide Story creation when the Stories module is disabled.
path = "portal/social-posts.php"
text = read(path)
old = '''<div><button type="button" data-publishing-open="story">Add story</button><button type="button" data-publishing-open="social-post">Create post</button><button type="button" data-feed-settings-open aria-label="Open Social Feed settings">Settings</button><a href="<?=e(app_url('social-feed.php'))?>" target="_blank" rel="noopener">Public feed</a></div>'''
new = '''<div><?php if(nmm_module_enabled('stories')):?><button type="button" data-publishing-open="story">Add story</button><?php endif;?><button type="button" data-publishing-open="social-post">Create post</button><button type="button" data-feed-settings-open aria-label="Open Social Feed settings">Settings</button><a href="<?=e(app_url('social-feed.php'))?>" target="_blank" rel="noopener">Public feed</a></div>'''
text = replace_once(text, old, new, "conditional Add story action")
write(path, text)


# Complete administrator and client module-aware navigation.
path = "portal/bootstrap.php"
text = read(path)
old = '''            'events' => [['Work','events']],
            'bookings' => [['Work','bookings']],
        ];
        foreach ($moduleNavigationMap as $moduleKey => $locations) {
            if (nmm_module_enabled($moduleKey)) continue;
            foreach ($locations as [$groupName,$itemKey]) {
                unset($adminNavigationGroups[$groupName][$itemKey]);
            }
        }
    }

    $script = $isAdmin ? 'admin.php' : 'client.php';'''
new = '''            'events' => [['Work','events']],
            'bookings' => [['Work','bookings']],
            'project_intake' => [['Work','proposals']],
            'call_us' => [['Operations','call-center']],
        ];
        foreach ($moduleNavigationMap as $moduleKey => $locations) {
            if (nmm_module_enabled($moduleKey)) continue;
            foreach ($locations as [$groupName,$itemKey]) {
                unset($adminNavigationGroups[$groupName][$itemKey]);
            }
        }
    } else {
        if (!nmm_module_enabled('clients')) {
            unset($navigation['projects'], $navigation['files']);
        }
        if (!nmm_module_enabled('call_us')) {
            unset($navigation['call-center']);
        }
    }

    $script = $isAdmin ? 'admin.php' : 'client.php';'''
text = replace_once(text, old, new, "complete module navigation mapping")

old = '''                <a class="portal-top-action" href="<?= e($callCenterUrl) ?>">
                    <?= $isAdmin ? 'Call Center' : 'Call Us' ?>
                </a>'''
new = '''                <?php if(nmm_module_enabled('call_us')):?>
                <a class="portal-top-action" href="<?= e($callCenterUrl) ?>">
                    <?= $isAdmin ? 'Call Center' : 'Call Us' ?>
                </a>
                <?php endif;?>'''
text = replace_once(text, old, new, "conditional Call Center header action")
write(path, text)


# Make Proposals follow the existing Project Intake module setting.
path = "portal/publishing-center.php"
text = read(path)
old = '''            'key' => 'proposal',
            'group' => 'Work',
            'label' => 'Proposal',
            'description' => 'Build a proposal or estimate.',
            'module' => null,'''
new = '''            'key' => 'proposal',
            'group' => 'Work',
            'label' => 'Proposal',
            'description' => 'Build a proposal or estimate.',
            'module' => 'project_intake','''
text = replace_once(text, old, new, "proposal module ownership")
write(path, text)


# Expand the permanent source contract for the final module boundaries.
path = "tests/publishing-center-v66q.php"
text = read(path)
text = replace_once(
    text,
    '''        "['Work','projects']",
        'publishing_center_render_modal',''',
    '''        "['Work','projects']",
        "'project_intake' =>",
        "'call_us' =>",
        'publishing_center_render_modal',''',
    "module mapping assertions",
)
text = replace_once(
    text,
    '''        'data-publishing-open="social-post"',
        'Save draft',''',
    '''        'data-publishing-open="social-post"',
        "nmm_module_enabled('stories')",
        'Save draft',''',
    "story action module assertion",
)
text = replace_once(
    text,
    '''    '.github/workflows/repair-publishing-center-v66q.yml',
    '.github/v66q-build-trigger',''',
    '''    '.github/workflows/repair-publishing-center-v66q.yml',
    '.github/workflows/finalize-publishing-center-v66q.yml',
    '.github/v66q-build-trigger',''',
    "finalizer cleanup assertion",
)
write(path, text)


# The one-time runner removes its non-workflow inputs before committing.
for temporary in [
    Path("tools/finalize-publishing-center-v66q.py"),
    Path(".github/v66q-finalize-trigger"),
]:
    temporary.unlink(missing_ok=True)
