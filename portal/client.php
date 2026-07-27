<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/communications.php';
require_once __DIR__ . '/communications-view.php';
require_once __DIR__ . '/call-center-view.php';
require_once __DIR__ . '/notifications-view.php';
$user=require_role('client');
$view=(string)($_GET['view']??'dashboard');
$allowed=['dashboard','call-center','projects','communications','notifications','messages','files','account'];
if(!in_array($view,$allowed,true))$view='dashboard';
if($view==='messages')$view='communications';

if(is_post()){
    verify_csrf();
    enforce_authenticated_action_limit($user);
    $action=input('action');
    try{
        if($action==='mark_all_notifications_read'){
            notification_mark_all_read((int)$user['id']);
            flash('success','All notifications were marked as read.');
            redirect('portal/client.php?view=notifications');
        }
        if($action==='send_message'){
            $project=int_input('project_id');
            $subject=input('subject');
            $body=input('body');

            if($subject===''||$body===''){
                throw new RuntimeException('Enter the subject and message.');
            }

            $threadId=communication_create_thread(
                $user,
                (int)$user['id'],
                $project>0?$project:null,
                $subject
            );
            communication_insert_message(
                $threadId,
                (int)$user['id'],
                'client',
                'text',
                $body
            );

            flash('success','Your communication was sent.');
            redirect('portal/client.php?view=communications&thread='.$threadId);
        }
        if($action==='save_account_profile'){
            save_account_profile(
                (int)$user['id'],
                [
                    'display_name'=>input('display_name'),
                    'email'=>input('email'),
                    'company'=>input('company'),
                    'phone'=>input('phone'),
                ],
                $_FILES['profile_image']??null,
                isset($_POST['remove_profile_image'])
            );
            flash('success','Account settings updated.');
            redirect('portal/client.php?view=account');
        }

        if($action==='reset_password'||$action==='change_password'){
            $cur=(string)($_POST['current_password']??'');$new=(string)($_POST['new_password']??'');$confirm=(string)($_POST['confirm_password']??'');$s=db()->prepare('SELECT password_hash FROM users WHERE id=:id');$s->execute(['id'=>$user['id']]);if(!password_verify($cur,(string)$s->fetchColumn()))throw new RuntimeException('Current password is not correct.');$errors=password_policy_errors($new,(string)$user['email']);if($errors)throw new RuntimeException(implode(' ',$errors));if(!hash_equals($new,$confirm))throw new RuntimeException('The new passwords do not match.');db()->prepare('UPDATE users SET password_hash=:h,must_change_password=0 WHERE id=:id')->execute(['h'=>password_hash($new,PASSWORD_DEFAULT),'id'=>$user['id']]);flash('success','Password reset.');redirect('portal/client.php?view=account');
        }
    }catch(Throwable $e){flash('error',$e->getMessage());redirect('portal/client.php?view='.$view);}
}

$projectsStmt=db()->prepare('SELECT * FROM projects WHERE client_user_id=:c ORDER BY FIELD(status,"active","review","planning","discovery","on_hold","completed","archived"),updated_at DESC');
$projectsStmt->execute(['c'=>$user['id']]);$projects=$projectsStmt->fetchAll();

$title=status_label($view);
portal_header($title,$view,$user);

if($view==='dashboard'){
    $open=count(array_filter($projects,fn($p)=>!in_array($p['status'],['completed','archived'],true)));
    $u=db()->prepare(
        'SELECT COUNT(*)
         FROM communication_messages message
         JOIN communication_threads conversation ON conversation.id=message.thread_id
         LEFT JOIN communication_thread_members thread_member
           ON thread_member.thread_id=conversation.id
          AND thread_member.user_id=:member_user_id
         WHERE conversation.client_user_id=:client_user_id
           AND message.visibility="client"
           AND message.id>COALESCE(thread_member.last_read_message_id,0)
           AND (
               message.sender_user_id IS NULL
               OR message.sender_user_id<>:unread_user_id
           )'
    );
    $u->execute([
        'member_user_id'=>$user['id'],
        'client_user_id'=>$user['id'],
        'unread_user_id'=>$user['id'],
    ]);
    $unread=(int)$u->fetchColumn();

    $f=db()->prepare('SELECT COUNT(*) FROM files WHERE client_user_id=:c AND visibility="client"');
    $f->execute(['c'=>$user['id']]);
    $fileCount=(int)$f->fetchColumn();

    $m=db()->prepare(
        'SELECT conversation.id,
                conversation.subject,
                conversation.last_message_at,
                project.title AS project_title,
                (
                    SELECT message.body
                    FROM communication_messages message
                    WHERE message.thread_id=conversation.id
                      AND message.visibility="client"
                    ORDER BY message.id DESC
                    LIMIT 1
                ) AS latest_message
         FROM communication_threads conversation
         LEFT JOIN projects project ON project.id=conversation.project_id
         WHERE conversation.client_user_id=:client_user_id
           AND conversation.status<>"archived"
         ORDER BY COALESCE(conversation.last_message_at,conversation.created_at) DESC
         LIMIT 5'
    );
    $m->execute(['client_user_id'=>$user['id']]);
    $messages=$m->fetchAll();
?>
<section class="panel" style="margin-bottom:20px"><div class="panel-body client-dashboard-welcome"><div><span style="color:#687586;font-size:.65rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase">Welcome back</span><h2 style="margin:4px 0 5px"><?=e($user['display_name'])?></h2><p style="margin:0;color:#687586"><?=e(setting('portal_welcome','Project updates, secure communications, voice notes, calls, and shared files in one place.'))?></p></div><a class="button button-primary" href="?view=call-center">Call Us</a></div></section>
<div class="stats-grid"><article class="stat-card"><span>Open projects</span><strong><?=$open?></strong><small>Current work</small></article><article class="stat-card"><span>Unread communications</span><strong><?=$unread?></strong><small>Messages, voice notes and calls</small></article><article class="stat-card"><span>Shared files</span><strong><?=$fileCount?></strong><small>Protected downloads</small></article><article class="stat-card"><span>Account</span><strong style="font-size:1rem;margin-top:9px">Active</strong><small><?=e($user['email'])?></small></article></div>
<div class="dashboard-grid"><section class="panel"><header class="panel-header"><h2>Your projects</h2><a href="?view=projects">View all</a></header><div class="table-wrap"><table class="data-table"><thead><tr><th>Project</th><th>Status</th><th>Progress</th><th>Next milestone</th></tr></thead><tbody><?php foreach(array_slice($projects,0,6) as $p):?><tr><td><a href="?view=projects&id=<?=(int)$p['id']?>"><?=e($p['title'])?></a></td><td><span class="status status-<?=e($p['status'])?>"><?=e(status_label($p['status']))?></span></td><td><div class="progress"><div class="progress-track"><span style="width:<?=(int)$p['progress']?>%"></span></div><small><?=(int)$p['progress']?>%</small></div></td><td><?=e($p['next_milestone']?:'—')?><br><small><?=e(format_date($p['next_milestone_date']))?></small></td></tr><?php endforeach;?></tbody></table></div></section><section class="panel"><header class="panel-header"><h2>Recent communications</h2><a href="?view=communications">Open Communications</a></header><div class="panel-body"><?php if(!$messages):?><div class="empty-state">No conversations yet.</div><?php else:?><div class="timeline"><?php foreach($messages as $message):?><article class="timeline-item"><h3><a href="?view=communications&thread=<?=(int)$message['id']?>"><?=e($message['subject'])?></a></h3><p><?=e($message['project_title']?:'General')?> · <?=e(strlen((string)($message['latest_message']?:'No messages yet.'))>90?substr((string)$message['latest_message'],0,87).'...':(string)($message['latest_message']?:'No messages yet.'))?></p><small><?=e(format_datetime($message['last_message_at']))?></small></article><?php endforeach;?></div><?php endif;?></div></section></div>
<?php
}

if($view==='projects'){
    $id=query_int('id');$selected=null;foreach($projects as $p)if((int)$p['id']===$id)$selected=$p;
    if(!$selected){
?>
<div class="card-grid"><?php foreach($projects as $p):?><article class="project-card"><span class="status status-<?=e($p['status'])?>"><?=e(status_label($p['status']))?></span><h2 style="margin-top:10px"><?=e($p['title'])?></h2><p><?=e($p['summary']?:'No summary has been published.')?></p><div class="card-meta"><span>Due <?=e(format_date($p['due_date']))?></span><span><?=e($p['next_milestone']?:'No milestone scheduled')?></span></div><div class="progress"><div class="progress-track"><span style="width:<?=(int)$p['progress']?>%"></span></div><small><?=(int)$p['progress']?>% complete</small></div><div class="card-actions"><a class="button button-small" href="?view=projects&id=<?=(int)$p['id']?>">View project</a></div></article><?php endforeach;?><?php if(!$projects):?><section class="panel" style="grid-column:1/-1"><div class="empty-state">No projects have been assigned.</div></section><?php endif;?></div>
<?php
    }else{
        $u=db()->prepare('SELECT pu.*,us.display_name FROM project_updates pu JOIN users us ON us.id=pu.created_by WHERE pu.project_id=:p AND pu.visibility="client" ORDER BY pu.created_at DESC');$u->execute(['p'=>$selected['id']]);$updates=$u->fetchAll();
        $f=db()->prepare('SELECT * FROM files WHERE project_id=:p AND client_user_id=:c AND visibility="client" ORDER BY created_at DESC');$f->execute(['p'=>$selected['id'],'c'=>$user['id']]);$files=$f->fetchAll();
?>
<div class="page-actions"><a class="button" href="?view=projects">Back to projects</a><a class="button" href="?view=communications&project=<?=(int)$selected['id']?>">Open project communications</a></div>
<div class="stats-grid"><article class="stat-card"><span>Status</span><strong style="font-size:1rem;margin-top:9px"><?=e(status_label($selected['status']))?></strong><small><?=e(ucfirst($selected['priority']))?> priority</small></article><article class="stat-card"><span>Progress</span><strong><?=(int)$selected['progress']?>%</strong><small>Current completion</small></article><article class="stat-card"><span>Due date</span><strong style="font-size:1rem;margin-top:9px"><?=e(format_date($selected['due_date']))?></strong><small>Project target</small></article><article class="stat-card"><span>Next milestone</span><strong style="font-size:.95rem;margin-top:9px"><?=e($selected['next_milestone']?:'Not scheduled')?></strong><small><?=e(format_date($selected['next_milestone_date']))?></small></article></div>
<div class="dashboard-grid"><div class="stack"><section class="panel"><header class="panel-header"><h2>Project summary</h2></header><div class="panel-body"><p><?=nl2br(e($selected['summary']?:'No project summary has been published.'))?></p><div class="progress"><div class="progress-track"><span style="width:<?=(int)$selected['progress']?>%"></span></div><small><?=(int)$selected['progress']?>% complete</small></div></div></section><section class="panel"><header class="panel-header"><h2>Project updates</h2></header><div class="panel-body"><?php if(!$updates):?><div class="empty-state">No updates have been posted.</div><?php else:?><div class="timeline"><?php foreach($updates as $update):?><article class="timeline-item"><h3><?=e($update['title'])?></h3><p><?=nl2br(e($update['body']))?></p><small><?=e($update['display_name'])?> · <?=e(format_datetime($update['created_at']))?></small></article><?php endforeach;?></div><?php endif;?></div></section></div><section class="panel"><header class="panel-header"><h2>Project files</h2></header><div class="panel-body"><?php if(!$files):?><div class="empty-state">No files have been shared.</div><?php else:?><div class="message-list"><?php foreach($files as $file):?><article class="file-card"><h2><?=e($file['original_name'])?></h2><p><?=e($file['description']?:'Project file')?></p><div class="card-meta"><span><?=e(format_bytes((int)$file['size_bytes']))?></span><span><?=e(format_datetime($file['created_at']))?></span></div><a class="button button-small" href="<?=e(app_url('portal/download.php?id='.$file['id']))?>">Download</a></article><?php endforeach;?></div><?php endif;?></div></section></div>
<?php
    }
}

if($view==='call-center'){
    call_center_render_client($user);
}

if($view==='notifications'){
    notification_render_feed($user);
}

if($view==='communications'){
    communication_render_page($user,false);
}

if($view==='messages'){
    $project=query_int('project');$m=db()->prepare('SELECT m.*,p.title AS project_title,s.display_name AS sender_name FROM messages m LEFT JOIN projects p ON p.id=m.project_id LEFT JOIN users s ON s.id=m.sender_user_id WHERE m.client_user_id=:c ORDER BY m.created_at DESC LIMIT 100');$m->execute(['c'=>$user['id']]);$messages=$m->fetchAll();$r=db()->prepare('UPDATE messages SET is_read_by_client=1 WHERE client_user_id=:c AND is_read_by_client=0');$r->execute(['c'=>$user['id']]);
?>
<div class="dashboard-grid"><form method="post" class="form-panel"><?=csrf_field()?><input type="hidden" name="action" value="send_message"><div class="form-grid"><label class="field full"><span>Project</span><select name="project_id"><option value="">General</option><?php foreach($projects as $p):?><option value="<?=(int)$p['id']?>" <?=$project===(int)$p['id']?'selected':''?>><?=e($p['title'])?></option><?php endforeach;?></select></label><label class="field full"><span>Subject</span><input name="subject" required></label><label class="field full"><span>Message</span><textarea name="body" required></textarea></label></div><div class="form-footer"><button class="button button-primary">Send message</button></div></form><section class="panel"><div class="panel-body"><div class="message-list"><?php foreach($messages as $message):?><article class="message-card <?=!(int)$message['is_read_by_client']?'unread':''?>"><header><div><h2><?=e($message['subject'])?></h2><p><?=e($message['project_title']?:'General')?></p></div><time><?=e(format_datetime($message['created_at']))?></time></header><div class="message-body"><?=e($message['body'])?></div><div class="card-meta"><span>From <?=e($message['sender_name']?:status_label($message['sender_type']))?></span></div></article><?php endforeach;?><?php if(!$messages):?><div class="empty-state">No messages have been exchanged.</div><?php endif;?></div></div></section></div>
<?php
}

if($view==='files'){
    $f=db()->prepare('SELECT f.*,p.title AS project_title FROM files f LEFT JOIN projects p ON p.id=f.project_id WHERE f.client_user_id=:c AND f.visibility="client" ORDER BY f.created_at DESC');$f->execute(['c'=>$user['id']]);$files=$f->fetchAll();
?>
<div class="card-grid"><?php foreach($files as $file):?><article class="file-card"><h2><?=e($file['original_name'])?></h2><p><?=e($file['description']?:'Shared client file')?></p><div class="card-meta"><span><?=e($file['project_title']?:'General')?></span><span><?=e(format_bytes((int)$file['size_bytes']))?></span><span><?=e(format_datetime($file['created_at']))?></span></div><a class="button button-small" href="<?=e(app_url('portal/download.php?id='.$file['id']))?>">Download</a></article><?php endforeach;?><?php if(!$files):?><section class="panel" style="grid-column:1/-1"><div class="empty-state">No files have been shared.</div></section><?php endif;?></div>
<?php
}

if($view==='account'){
$accountUser=current_user()?:$user;
$accountImageUrl=user_profile_image_url($accountUser);
?>
<?php if(isset($_GET['required'])):?><div class="alert alert-warning">Reset the temporary password before continuing.</div><?php endif;?>

<div class="account-settings-grid">
<form method="post" enctype="multipart/form-data" class="form-panel account-profile-form">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_account_profile">

<header class="account-form-header">
<img src="<?=e($accountImageUrl)?>" alt="<?=e($accountUser['display_name'])?> profile photo">
<div>
<span>Account profile</span>
<h2>Profile and contact settings</h2>
<p>Update the information used across the client portal and logged-in account menu.</p>
</div>
</header>

<div class="form-grid">
<label class="field">
<span>Display name</span>
<input name="display_name" value="<?=e($accountUser['display_name'])?>" required>
</label>
<label class="field">
<span>Email</span>
<input type="email" name="email" value="<?=e($accountUser['email'])?>" required>
</label>
<label class="field">
<span>Phone</span>
<input name="phone" value="<?=e($accountUser['phone']??'')?>" autocomplete="tel">
</label>
<label class="field">
<span>Company</span>
<input name="company" value="<?=e($accountUser['company']??'')?>" autocomplete="organization">
</label>
<label class="field full">
<span>Profile photo</span>
<input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp,image/gif">
<small>JPG, PNG, WebP, or GIF. Maximum 5 MB.</small>
</label>
<?php if(!empty($accountUser['profile_image_stored_name'])):?>
<label class="checkbox-row full">
<input type="checkbox" name="remove_profile_image" value="1">
<span>Remove the current profile photo and use the default image.</span>
</label>
<?php endif;?>
</div>

<div class="form-footer">
<button class="button button-primary">Save account settings</button>
</div>
</form>

<form method="post" class="form-panel account-password-form">
<?=csrf_field()?>
<input type="hidden" name="action" value="reset_password">
<header class="account-password-header">
<span>Security</span>
<h2>Reset password</h2>
<p>Confirm the current password, then choose a new password with at least 12 characters.</p>
</header>
<div class="form-grid">
<label class="field full">
<span>Current password</span>
<input type="password" name="current_password" autocomplete="current-password" required>
</label>
<label class="field">
<span>New password</span>
<input type="password" name="new_password" minlength="12" autocomplete="new-password" required>
</label>
<label class="field">
<span>Confirm password</span>
<input type="password" name="confirm_password" minlength="12" autocomplete="new-password" required>
</label>
</div>
<div class="form-footer">
<button class="button button-primary">Reset password</button>
</div>
</form>
</div>
<?php
}

portal_footer();
