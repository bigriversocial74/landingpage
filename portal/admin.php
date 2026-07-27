<?php
declare(strict_types=1);
// North Mountain Media build: 20260727-visual-site-builder-v61
require __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/knowledge-assets.php';
require_once __DIR__ . '/transcription.php';
require_once __DIR__ . '/communications.php';
require_once __DIR__ . '/communications-view.php';
require_once __DIR__ . '/call-center-view.php';
require_once __DIR__ . '/crm-messages.php';
require_once __DIR__ . '/portfolio.php';
require_once __DIR__ . '/visitor-intelligence.php';
require_once __DIR__ . '/music-library.php';
require_once __DIR__ . '/publishing.php';
require_once __DIR__ . '/publishing-workflow.php';
require_once __DIR__ . '/publishing-workflow-view.php';
require_once __DIR__ . '/publishing-admin.php';
require_once __DIR__ . '/events-calendar.php';
require_once __DIR__ . '/events-admin.php';
require_once __DIR__ . '/appointments-booking.php';
require_once __DIR__ . '/bookings-admin.php';
require_once __DIR__ . '/proposals-intake.php';
require_once __DIR__ . '/proposals-admin.php';
require_once __DIR__ . '/notifications-view.php';
require_once __DIR__ . '/microgifter-connectors.php';
require_once __DIR__ . '/site-analytics-view.php';
require_once __DIR__ . '/menus-admin.php';
$user=require_role('admin');
$view=(string)($_GET['view']??'dashboard');
$allowed=['dashboard','music','analytics','call-center','clients','administrators','crm','portfolio','blog','events','bookings','proposals','resume','projects','leads','communications','notifications','messages','files','knowledge','builder','menus','site-analytics','settings','account'];
if(!in_array($view,$allowed,true))$view='dashboard';
if($view==='messages')$view='communications';

function admin_clients(): array {
    return db()->query('SELECT id,display_name,email,company,status,last_login_at FROM users WHERE role="client" ORDER BY status,COALESCE(company,display_name),display_name')->fetchAll();
}
function admin_projects(): array {
    return db()->query('SELECT p.*,u.display_name AS client_name,u.company FROM projects p JOIN users u ON u.id=p.client_user_id ORDER BY FIELD(p.status,"active","review","planning","discovery","on_hold","completed","archived"),p.updated_at DESC')->fetchAll();
}

if(is_post()){
    verify_csrf();
    enforce_authenticated_action_limit($user);
    $action=input('action');
    try{
        if(publishing_handle_admin_action($action,$user)){
            exit;
        }
        if(events_handle_admin_action($action,$user)){
            exit;
        }
        if(booking_handle_admin_action($action,$user)){
            exit;
        }
        if(proposals_handle_admin_action($action,$user)){
            exit;
        }
        if(site_menu_handle_admin_action($action,$user)){
            exit;
        }
        if($action==='mark_all_notifications_read'){
            notification_mark_all_read((int)$user['id']);
            flash('success','All notifications were marked as read.');
            redirect('portal/admin.php?view=notifications');
        }
        if($action==='save_administrator'){
            $id=int_input('id');
            $email=strtolower(input('email'));
            $name=input('display_name');
            $status=input('status')==='inactive'?'inactive':'active';

            if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$name===''){
                throw new RuntimeException('Enter a valid administrator name and email.');
            }

            if($id>0){
                if($id===(int)$user['id']&&$status!=='active'){
                    throw new RuntimeException('You cannot deactivate the administrator account currently in use.');
                }

                $s=db()->prepare('UPDATE users SET email=:e,display_name=:n,company=:c,phone=:p,status=:st WHERE id=:id AND role="admin"');
                $s->execute([
                    'e'=>$email,
                    'n'=>$name,
                    'c'=>nullable_input('company'),
                    'p'=>nullable_input('phone'),
                    'st'=>$status,
                    'id'=>$id
                ]);
                flash('success','Administrator updated.');
                log_activity('administrator_updated','user',$id);
            }else{
                $password=input('temporary_password')?:random_password();
                $errors=password_policy_errors($password,$email);

                if($errors){
                    throw new RuntimeException(implode(' ',$errors));
                }

                $s=db()->prepare('INSERT INTO users(role,email,password_hash,display_name,company,phone,status,must_change_password) VALUES("admin",:e,:h,:n,:c,:p,"active",1)');
                $s->execute([
                    'e'=>$email,
                    'h'=>password_hash($password,PASSWORD_DEFAULT),
                    'n'=>$name,
                    'c'=>nullable_input('company'),
                    'p'=>nullable_input('phone')
                ]);
                $id=(int)db()->lastInsertId();
                flash('success','Administrator created. Temporary password: '.$password.' — copy it now.');
                log_activity('administrator_created','user',$id);
            }

            redirect('portal/admin.php?view=administrators&edit='.$id);
        }

        if($action==='reset_administrator_password'){
            $id=int_input('id');
            $password=random_password();

            db()->prepare('UPDATE users SET password_hash=:h,must_change_password=1 WHERE id=:id AND role="admin"')
                ->execute(['h'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$id]);

            flash('success','Administrator temporary password: '.$password.' — copy it now.');
            log_activity('administrator_password_reset','user',$id);
            redirect('portal/admin.php?view=administrators&edit='.$id);
        }


        if($action==='create_crm_contact'){
            $name=input('display_name');
            $email=strtolower(input('email'));
            $stage=input('lifecycle_stage');
            $owner=int_input('owner_user_id');
            $followUp=nullable_input('next_follow_up_at');
            $allowedStages=['lead','prospect','qualified','client','partner','closed'];

            if($name===''){
                throw new RuntimeException('Enter a CRM contact name.');
            }

            if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL)){
                throw new RuntimeException('Enter a valid email or leave it blank.');
            }

            if(!in_array($stage,$allowedStages,true)){
                $stage='lead';
            }

            if($followUp!==null){
                $followUp=str_replace('T',' ',$followUp);
            }

            if($email!==''){
                $duplicate=db()->prepare(
                    'SELECT id
                     FROM crm_contacts
                     WHERE email=:email
                     LIMIT 1'
                );
                $duplicate->execute(['email'=>$email]);
                $existingId=(int)($duplicate->fetchColumn()?:0);

                if($existingId>0){
                    flash('warning','A CRM contact already uses that email.');
                    redirect('portal/admin.php?view=crm&id='.$existingId);
                }
            }

            $statement=db()->prepare(
                'INSERT INTO crm_contacts
                    (email,display_name,company,phone,lifecycle_stage,
                     source,owner_user_id,last_inquiry_at,next_follow_up_at,notes)
                 VALUES
                    (:email,:display_name,:company,:phone,:lifecycle_stage,
                     "manual",:owner_user_id,UTC_TIMESTAMP(),
                     :next_follow_up_at,:notes)'
            );
            $statement->execute([
                'email'=>$email!==''?$email:null,
                'display_name'=>$name,
                'company'=>nullable_input('company'),
                'phone'=>nullable_input('phone'),
                'lifecycle_stage'=>$stage,
                'owner_user_id'=>$owner>0?$owner:null,
                'next_follow_up_at'=>$followUp,
                'notes'=>nullable_input('notes'),
            ]);
            $id=(int)db()->lastInsertId();

            db()->prepare(
                'INSERT INTO crm_activities
                    (contact_id,admin_user_id,activity_type,subject,body)
                 VALUES
                    (:contact_id,:admin_user_id,"system",
                     "CRM contact created",:body)'
            )->execute([
                'contact_id'=>$id,
                'admin_user_id'=>$user['id'],
                'body'=>'Created manually · '.status_label($stage),
            ]);

            log_activity(
                'crm_contact_created',
                'crm_contact',
                $id,
                ['stage'=>$stage,'source'=>'manual']
            );
            flash('success','CRM contact created.');
            redirect('portal/admin.php?view=crm&id='.$id);
        }

        if($action==='save_crm_contact'){
            $id=int_input('contact_id');
            $name=input('display_name');
            $email=strtolower(input('email'));
            $stage=input('lifecycle_stage');
            $owner=int_input('owner_user_id');
            $followUp=nullable_input('next_follow_up_at');
            $allowedStages=['lead','prospect','qualified','client','partner','closed'];

            if($id<=0||$name===''){
                throw new RuntimeException('Enter a valid CRM contact name.');
            }

            if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL)){
                throw new RuntimeException('Enter a valid email or leave it blank.');
            }

            if(!in_array($stage,$allowedStages,true)){
                $stage='lead';
            }

            if($followUp!==null){
                $followUp=str_replace('T',' ',$followUp);
            }

            $statement=db()->prepare(
                'UPDATE crm_contacts
                 SET display_name=:display_name,
                     email=:email,
                     company=:company,
                     phone=:phone,
                     lifecycle_stage=:lifecycle_stage,
                     owner_user_id=:owner_user_id,
                     next_follow_up_at=:next_follow_up_at,
                     notes=:notes
                 WHERE id=:id'
            );
            $statement->execute([
                'display_name'=>$name,
                'email'=>$email!==''?$email:null,
                'company'=>nullable_input('company'),
                'phone'=>nullable_input('phone'),
                'lifecycle_stage'=>$stage,
                'owner_user_id'=>$owner>0?$owner:null,
                'next_follow_up_at'=>$followUp,
                'notes'=>nullable_input('notes'),
                'id'=>$id,
            ]);

            db()->prepare(
                'INSERT INTO crm_activities
                    (contact_id,admin_user_id,activity_type,subject,body)
                 VALUES
                    (:contact_id,:admin_user_id,"status_change","CRM contact updated",:body)'
            )->execute([
                'contact_id'=>$id,
                'admin_user_id'=>$user['id'],
                'body'=>'Lifecycle stage: '.status_label($stage),
            ]);

            log_activity('crm_contact_updated','crm_contact',$id,['stage'=>$stage]);
            flash('success','CRM contact updated.');
            redirect('portal/admin.php?view=crm&id='.$id);
        }

        if($action==='save_crm_opportunity'){
            $id=int_input('opportunity_id');
            $contactId=int_input('contact_id');
            $stage=input('stage');
            $allowedStages=['new','reviewing','contacted','qualified','proposal','won','lost'];
            $probability=max(0,min(100,int_input('probability',10)));
            $nextActionAt=nullable_input('next_action_at');

            if($id<=0||$contactId<=0){
                throw new RuntimeException('CRM opportunity not found.');
            }

            if(!in_array($stage,$allowedStages,true)){
                $stage='new';
            }

            if($nextActionAt!==null){
                $nextActionAt=str_replace('T',' ',$nextActionAt);
            }

            $old=db()->prepare('SELECT stage FROM crm_opportunities WHERE id=:id AND contact_id=:contact_id');
            $old->execute(['id'=>$id,'contact_id'=>$contactId]);
            $oldStage=(string)($old->fetchColumn()?:'');

            $statement=db()->prepare(
                'UPDATE crm_opportunities
                 SET stage=:stage,
                     estimated_value=:estimated_value,
                     probability=:probability,
                     next_action=:next_action,
                     next_action_at=:next_action_at
                 WHERE id=:id AND contact_id=:contact_id'
            );
            $statement->execute([
                'stage'=>$stage,
                'estimated_value'=>nullable_input('estimated_value'),
                'probability'=>$probability,
                'next_action'=>nullable_input('next_action'),
                'next_action_at'=>$nextActionAt,
                'id'=>$id,
                'contact_id'=>$contactId,
            ]);

            if($oldStage!==$stage){
                db()->prepare(
                    'INSERT INTO crm_activities
                        (contact_id,opportunity_id,admin_user_id,activity_type,subject,body)
                     VALUES
                        (:contact_id,:opportunity_id,:admin_user_id,"status_change","Opportunity stage changed",:body)'
                )->execute([
                    'contact_id'=>$contactId,
                    'opportunity_id'=>$id,
                    'admin_user_id'=>$user['id'],
                    'body'=>status_label($oldStage).' → '.status_label($stage),
                ]);
            }

            log_activity('crm_opportunity_updated','crm_opportunity',$id,['stage'=>$stage]);
            flash('success','CRM opportunity updated.');
            redirect('portal/admin.php?view=crm&id='.$contactId.'&opportunity='.$id);
        }

        if($action==='add_crm_activity'){
            $contactId=int_input('contact_id');
            $opportunityId=int_input('opportunity_id');
            $type=input('activity_type');
            $subject=input('subject');
            $body=input('body');
            $allowedTypes=['note','email','call','meeting'];

            if($contactId<=0||$subject===''){
                throw new RuntimeException('Enter an activity subject.');
            }

            if(!in_array($type,$allowedTypes,true)){
                $type='note';
            }

            db()->prepare(
                'INSERT INTO crm_activities
                    (contact_id,opportunity_id,admin_user_id,activity_type,subject,body)
                 VALUES
                    (:contact_id,:opportunity_id,:admin_user_id,:activity_type,:subject,:body)'
            )->execute([
                'contact_id'=>$contactId,
                'opportunity_id'=>$opportunityId>0?$opportunityId:null,
                'admin_user_id'=>$user['id'],
                'activity_type'=>$type,
                'subject'=>$subject,
                'body'=>$body!==''?$body:null,
            ]);

            if(in_array($type,['email','call','meeting'],true)){
                db()->prepare(
                    'UPDATE crm_contacts
                     SET last_contacted_at=UTC_TIMESTAMP()
                     WHERE id=:id'
                )->execute(['id'=>$contactId]);
            }

            log_activity('crm_activity_created','crm_contact',$contactId,['type'=>$type]);
            flash('success','CRM activity added.');
            redirect('portal/admin.php?view=crm&id='.$contactId);
        }

        if($action==='convert_crm_contact'){
            $contactId=int_input('contact_id');
            $opportunityId=int_input('opportunity_id');

            $contactStatement=db()->prepare('SELECT * FROM crm_contacts WHERE id=:id');
            $contactStatement->execute(['id'=>$contactId]);
            $contact=$contactStatement->fetch();

            if(!$contact){
                throw new RuntimeException('CRM contact not found.');
            }

            $clientId=(int)($contact['client_user_id']??0);
            $password=null;

            if($clientId<=0){
                $existing=db()->prepare('SELECT id FROM users WHERE email=:email AND role="client"');
                $existing->execute(['email'=>$contact['email']]);
                $clientId=(int)($existing->fetchColumn()?:0);
            }

            if($clientId<=0){
                $password=random_password();
                $create=db()->prepare(
                    'INSERT INTO users
                        (role,email,password_hash,display_name,company,phone,status,must_change_password)
                     VALUES
                        ("client",:email,:password_hash,:display_name,:company,:phone,"active",1)'
                );
                $create->execute([
                    'email'=>$contact['email'],
                    'password_hash'=>password_hash($password,PASSWORD_DEFAULT),
                    'display_name'=>$contact['display_name'],
                    'company'=>$contact['company'],
                    'phone'=>$contact['phone'],
                ]);
                $clientId=(int)db()->lastInsertId();
                db()->prepare('INSERT INTO client_profiles(user_id) VALUES(:id)')
                    ->execute(['id'=>$clientId]);
            }

            db()->prepare(
                'UPDATE crm_contacts
                 SET lifecycle_stage="client",
                     client_user_id=:client_user_id,
                     last_contacted_at=UTC_TIMESTAMP()
                 WHERE id=:id'
            )->execute([
                'client_user_id'=>$clientId,
                'id'=>$contactId,
            ]);

            if($opportunityId>0){
                db()->prepare(
                    'UPDATE crm_opportunities
                     SET stage="won",probability=100
                     WHERE id=:id AND contact_id=:contact_id'
                )->execute([
                    'id'=>$opportunityId,
                    'contact_id'=>$contactId,
                ]);
            }

            db()->prepare(
                'INSERT INTO crm_activities
                    (contact_id,opportunity_id,admin_user_id,activity_type,subject,body)
                 VALUES
                    (:contact_id,:opportunity_id,:admin_user_id,"conversion","Converted to client portal",:body)'
            )->execute([
                'contact_id'=>$contactId,
                'opportunity_id'=>$opportunityId>0?$opportunityId:null,
                'admin_user_id'=>$user['id'],
                'body'=>'Client account ID: '.$clientId,
            ]);

            log_activity('crm_contact_converted','crm_contact',$contactId,['client_user_id'=>$clientId]);

            flash(
                'success',
                $password
                    ? 'CRM contact converted. Temporary client password: '.$password.' — copy it now.'
                    : 'CRM contact linked to the existing client account.'
            );
            redirect('portal/admin.php?view=crm&id='.$contactId);
        }

        if($action==='save_client'){
            $id=int_input('id');$email=strtolower(input('email'));$name=input('display_name');
            if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$name==='')throw new RuntimeException('Enter a valid name and email.');
            if($id>0){
                $s=db()->prepare('UPDATE users SET email=:e,display_name=:n,company=:c,phone=:p,status=:st WHERE id=:id AND role="client"');
                $s->execute(['e'=>$email,'n'=>$name,'c'=>nullable_input('company'),'p'=>nullable_input('phone'),'st'=>input('status')==='inactive'?'inactive':'active','id'=>$id]);
                flash('success','Client updated.');log_activity('client_updated','user',$id);
            }else{
                $password=input('temporary_password')?:random_password();
                $errors=password_policy_errors($password,$email);if($errors)throw new RuntimeException(implode(' ',$errors));
                $s=db()->prepare('INSERT INTO users(role,email,password_hash,display_name,company,phone,status,must_change_password) VALUES("client",:e,:h,:n,:c,:p,"active",1)');
                $s->execute(['e'=>$email,'h'=>password_hash($password,PASSWORD_DEFAULT),'n'=>$name,'c'=>nullable_input('company'),'p'=>nullable_input('phone')]);
                $id=(int)db()->lastInsertId();
                db()->prepare('INSERT INTO client_profiles(user_id) VALUES(:id)')->execute(['id'=>$id]);
                flash('success','Client created. Temporary password: '.$password.' — copy it now.');
                log_activity('client_created','user',$id);
            }
            redirect('portal/admin.php?view=clients&edit='.$id);
        }
        if($action==='reset_client_password'){
            $id=int_input('id');$password=random_password();
            db()->prepare('UPDATE users SET password_hash=:h,must_change_password=1 WHERE id=:id AND role="client"')->execute(['h'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$id]);
            flash('success','Temporary password: '.$password.' — copy it now.');
            redirect('portal/admin.php?view=clients&edit='.$id);
        }


        if($action==='adopt_music_asset'){
            $assetId=int_input('asset_id');
            $trackId=music_adopt_asset(
                $assetId,
                (int)$user['id']
            );
            flash(
                'success',
                'Audio asset added to the Music Library.'
            );
            redirect(
                'portal/admin.php?view=music&section=tracks&edit='
                .$trackId
            );
        }

        if($action==='adopt_all_music_assets'){
            if(!music_library_schema_available()){
                throw new RuntimeException(
                    'Import database/music_library_v44.sql before importing audio.'
                );
            }

            $assets=music_audio_assets(true);
            $imported=0;

            foreach($assets as $asset){
                music_adopt_asset(
                    (int)$asset['id'],
                    (int)$user['id']
                );
                $imported++;
            }

            flash(
                $imported>0?'success':'warning',
                $imported>0
                    ?$imported.' audio asset'.($imported===1?'':'s').' added to the Music Library.'
                    :'No unlinked audio assets were found.'
            );
            redirect(
                'portal/admin.php?view=music&section=import'
            );
        }

        if($action==='save_music_demo_mode'){
            $enabled=isset($_POST['enabled']);
            $bannerEnabled=(
                $enabled
                && isset($_POST['banner_enabled'])
            );

            music_save_settings([
                'music_demo_mode_enabled'=>$enabled?'1':'0',
                'music_demo_banner_enabled'=>$bannerEnabled?'1':'0',
            ]);

            log_activity(
                'music_demo_mode_updated',
                'music_demo_mode',
                0,
                [
                    'enabled'=>$enabled,
                    'banner_enabled'=>$bannerEnabled,
                ]
            );

            flash(
                'success',
                $enabled
                    ?'Demo Music Mode is active. The public Music Library now uses the playable demo catalog.'
                    :'Demo Music Mode is off. The public Music Library now uses the live published catalog.'
            );
            redirect(
                'portal/admin.php?view=music&section=demo'
            );
        }

        if($action==='save_music_banner'){
            $banner=music_banner_settings();
            $storedName=(string)$banner['stored_name'];
            $mimeType=(string)$banner['mime_type'];
            $sizeBytes=(int)$banner['size_bytes'];
            $sha256=(string)$banner['sha256'];

            if(isset($_POST['remove_banner'])){
                if($storedName!==''){
                    @unlink(
                        music_banner_storage_directory()
                        .'/'
                        .basename($storedName)
                    );
                }

                $storedName='';
                $mimeType='';
                $sizeBytes=0;
                $sha256='';
            }elseif(
                isset($_FILES['banner_image'])
                && is_array($_FILES['banner_image'])
            ){
                $upload=music_store_banner(
                    $_FILES['banner_image'],
                    $storedName!==''?$storedName:null
                );

                if($upload['changed']){
                    $storedName=(string)$upload['stored_name'];
                    $mimeType=(string)$upload['mime_type'];
                    $sizeBytes=(int)$upload['size_bytes'];
                    $sha256=(string)$upload['sha256'];
                }
            }

            $eyebrow=substr(input('eyebrow'),0,120);
            $title=substr(input('title'),0,190);
            $subtitle=substr(input('subtitle'),0,700);
            $altText=substr(input('alt_text'),0,190);
            $linkUrl=substr(input('link_url'),0,1000);

            if(
                $linkUrl!==''
                && !str_starts_with($linkUrl,'/')
                && !filter_var($linkUrl,FILTER_VALIDATE_URL)
            ){
                throw new RuntimeException(
                    'Banner links must be a valid HTTPS/HTTP URL or a site-relative path.'
                );
            }

            $enabled=(
                isset($_POST['enabled'])
                && $storedName!==''
                && is_file(
                    music_banner_storage_directory()
                    .'/'
                    .basename($storedName)
                )
            );

            music_save_settings([
                'music_banner_enabled'=>$enabled?'1':'0',
                'music_banner_stored_name'=>$storedName,
                'music_banner_mime_type'=>$mimeType,
                'music_banner_size_bytes'=>(string)$sizeBytes,
                'music_banner_sha256'=>$sha256,
                'music_banner_eyebrow'=>$eyebrow,
                'music_banner_title'=>$title,
                'music_banner_subtitle'=>$subtitle,
                'music_banner_alt_text'=>$altText,
                'music_banner_link_url'=>$linkUrl,
            ]);

            log_activity(
                'music_banner_updated',
                'music_banner',
                0,
                [
                    'enabled'=>$enabled,
                    'has_image'=>$storedName!=='',
                ]
            );

            flash(
                $storedName===''&&isset($_POST['enabled'])
                    ?'warning'
                    :'success',
                $storedName===''&&isset($_POST['enabled'])
                    ?'Banner settings saved, but the banner remains hidden until an image is uploaded.'
                    :'Music banner settings updated.'
            );
            redirect(
                'portal/admin.php?view=music&section=banner'
            );
        }

        if($action==='save_music_track'){
            if(!music_library_schema_available()){
                throw new RuntimeException(
                    'Import database/music_library_v44.sql before managing music.'
                );
            }

            $trackId=int_input('track_id');
            $assetId=int_input('knowledge_asset_id');
            $title=input('title');
            $slug=slugify(input('slug')?:$title);
            $artist=input('artist_name')?:'David Evans';
            $featuredArtist=nullable_input(
                'featured_artist'
            );
            $status=input('status');

            if(
                $title===''
                || $slug===''
                || $assetId<=0
            ){
                throw new RuntimeException(
                    'Track title, slug, and audio asset are required.'
                );
            }

            if(
                strlen($title)>190
                || strlen($slug)>190
                || strlen($artist)>190
            ){
                throw new RuntimeException(
                    'One of the music identity fields is too long.'
                );
            }

            if(!in_array(
                $status,
                ['draft','active','archived'],
                true
            )){
                $status='draft';
            }

            $assetStatement=db()->prepare(
                'SELECT id
                 FROM knowledge_assets
                 WHERE id=:asset_id
                   AND media_kind="audio"
                 LIMIT 1'
            );
            $assetStatement->execute([
                'asset_id'=>$assetId,
            ]);

            if(!$assetStatement->fetchColumn()){
                throw new RuntimeException(
                    'The selected audio asset was not found.'
                );
            }

            $duplicate=db()->prepare(
                'SELECT id
                 FROM music_tracks
                 WHERE (
                    slug=:slug
                    OR knowledge_asset_id=:asset_id
                 )
                   AND id<>:track_id
                 LIMIT 1'
            );
            $duplicate->execute([
                'slug'=>$slug,
                'asset_id'=>$assetId,
                'track_id'=>$trackId,
            ]);

            if($duplicate->fetchColumn()){
                throw new RuntimeException(
                    'That track slug or audio asset is already in the Music Library.'
                );
            }

            $albumId=int_input('album_id');
            $albumId=$albumId>0?$albumId:null;
            $releaseYear=int_input('release_year');
            $releaseYear=(
                $releaseYear>=1900
                && $releaseYear<=2100
            )?$releaseYear:null;
            $duration=int_input('duration_seconds');
            $duration=$duration>0?$duration:null;
            $trackNumber=int_input('track_number');
            $trackNumber=$trackNumber>0
                ?$trackNumber
                :null;
            $discNumber=max(
                1,
                int_input('disc_number')
            );

            $values=[
                'knowledge_asset_id'=>$assetId,
                'album_id'=>$albumId,
                'title'=>$title,
                'slug'=>$slug,
                'artist_name'=>$artist,
                'featured_artist'=>$featuredArtist,
                'status'=>$status,
                'featured'=>isset($_POST['featured'])?1:0,
                'sort_order'=>max(
                    0,
                    int_input('sort_order')
                ),
                'disc_number'=>$discNumber,
                'track_number'=>$trackNumber,
                'genre'=>nullable_input('genre'),
                'release_year'=>$releaseYear,
                'duration_seconds'=>$duration,
                'description'=>nullable_input(
                    'description'
                ),
                'lyrics'=>nullable_input('lyrics'),
                'is_explicit'=>isset(
                    $_POST['is_explicit']
                )?1:0,
                'is_downloadable'=>isset(
                    $_POST['is_downloadable']
                )?1:0,
                'updated_by'=>(int)$user['id'],
            ];

            if($trackId>0){
                $statement=db()->prepare(
                    'UPDATE music_tracks
                     SET knowledge_asset_id=:knowledge_asset_id,
                         album_id=:album_id,
                         title=:title,
                         slug=:slug,
                         artist_name=:artist_name,
                         featured_artist=:featured_artist,
                         status=:status,
                         featured=:featured,
                         sort_order=:sort_order,
                         disc_number=:disc_number,
                         track_number=:track_number,
                         genre=:genre,
                         release_year=:release_year,
                         duration_seconds=:duration_seconds,
                         description=:description,
                         lyrics=:lyrics,
                         is_explicit=:is_explicit,
                         is_downloadable=:is_downloadable,
                         updated_by=:updated_by,
                         published_at=CASE
                            WHEN :publish_status="active"
                            THEN COALESCE(
                                published_at,
                                UTC_TIMESTAMP()
                            )
                            ELSE published_at
                         END
                     WHERE id=:track_id'
                );
                $statement->execute(
                    $values+[
                        'publish_status'=>$status,
                        'track_id'=>$trackId,
                    ]
                );
                log_activity(
                    'music_track_updated',
                    'music_track',
                    $trackId
                );
                flash(
                    'success',
                    'Music track updated.'
                );
            }else{
                $statement=db()->prepare(
                    'INSERT INTO music_tracks
                        (knowledge_asset_id,album_id,title,
                         slug,artist_name,featured_artist,
                         status,featured,sort_order,
                         disc_number,track_number,genre,
                         release_year,duration_seconds,
                         description,lyrics,is_explicit,
                         is_downloadable,created_by,
                         updated_by,published_at)
                     VALUES
                        (:knowledge_asset_id,:album_id,:title,
                         :slug,:artist_name,:featured_artist,
                         :status,:featured,:sort_order,
                         :disc_number,:track_number,:genre,
                         :release_year,:duration_seconds,
                         :description,:lyrics,:is_explicit,
                         :is_downloadable,:created_by,
                         :updated_by,
                         CASE WHEN :publish_status="active"
                              THEN UTC_TIMESTAMP()
                              ELSE NULL END)'
                );
                $statement->execute(
                    $values+[
                        'created_by'=>(int)$user['id'],
                        'publish_status'=>$status,
                    ]
                );
                $trackId=(int)db()->lastInsertId();
                log_activity(
                    'music_track_created',
                    'music_track',
                    $trackId
                );
                flash(
                    'success',
                    'Music track created.'
                );
            }

            music_detach_asset_from_chat($assetId);

            if($status==='active'){
                db()->prepare(
                    'UPDATE knowledge_assets
                     SET status="published",
                         is_public=1,
                         published_at=COALESCE(
                            published_at,
                            UTC_TIMESTAMP()
                         )
                     WHERE id=:asset_id'
                )->execute([
                    'asset_id'=>$assetId,
                ]);
            }

            redirect(
                'portal/admin.php?view=music&section=tracks&edit='
                .$trackId
            );
        }

        if($action==='archive_music_track'){
            $trackId=int_input('track_id');

            if(
                !music_library_schema_available()
                || $trackId<=0
            ){
                throw new RuntimeException(
                    'Music track not found.'
                );
            }

            db()->prepare(
                'UPDATE music_tracks
                 SET status="archived",
                     updated_by=:updated_by
                 WHERE id=:track_id'
            )->execute([
                'updated_by'=>(int)$user['id'],
                'track_id'=>$trackId,
            ]);

            log_activity(
                'music_track_archived',
                'music_track',
                $trackId
            );
            flash(
                'success',
                'Music track archived.'
            );
            redirect(
                'portal/admin.php?view=music&section=tracks'
            );
        }

        if($action==='save_music_album'){
            if(!music_library_schema_available()){
                throw new RuntimeException(
                    'Import database/music_library_v44.sql before managing albums.'
                );
            }

            $albumId=int_input('album_id');
            $title=input('title');
            $slug=slugify(input('slug')?:$title);
            $artist=input('artist_name')?:'David Evans';
            $type=input('album_type');
            $status=input('status');

            if($title===''||$slug===''){
                throw new RuntimeException(
                    'Album title and slug are required.'
                );
            }

            if(
                strlen($title)>190
                || strlen($slug)>190
                || strlen($artist)>190
            ){
                throw new RuntimeException(
                    'One of the album identity fields is too long.'
                );
            }

            if(!in_array(
                $type,
                ['album','ep','single','compilation'],
                true
            )){
                $type='album';
            }

            if(!in_array(
                $status,
                ['draft','active','archived'],
                true
            )){
                $status='draft';
            }

            $duplicate=db()->prepare(
                'SELECT id
                 FROM music_albums
                 WHERE slug=:slug
                   AND id<>:album_id
                 LIMIT 1'
            );
            $duplicate->execute([
                'slug'=>$slug,
                'album_id'=>$albumId,
            ]);

            if($duplicate->fetchColumn()){
                throw new RuntimeException(
                    'That album slug is already in use.'
                );
            }

            $existing=$albumId>0
                ?music_admin_album($albumId)
                :null;
            $cover=[
                'stored_name'=>$existing['cover_stored_name']??null,
                'extension'=>$existing['cover_extension']??null,
                'mime_type'=>$existing['cover_mime_type']??null,
                'size_bytes'=>$existing['cover_size_bytes']??null,
                'sha256'=>$existing['cover_sha256']??null,
                'changed'=>false,
            ];

            if(isset($_POST['remove_cover'])){
                if(!empty($cover['stored_name'])){
                    @unlink(
                        music_cover_storage_directory()
                        .'/'
                        .basename(
                            (string)$cover['stored_name']
                        )
                    );
                }

                $cover=[
                    'stored_name'=>null,
                    'extension'=>null,
                    'mime_type'=>null,
                    'size_bytes'=>null,
                    'sha256'=>null,
                    'changed'=>true,
                ];
            }elseif(
                isset($_FILES['cover_image'])
                && is_array($_FILES['cover_image'])
            ){
                $newCover=music_store_cover(
                    $_FILES['cover_image'],
                    $cover['stored_name']
                );

                if($newCover['changed']){
                    $cover=$newCover;
                }
            }

            $releaseDate=nullable_input(
                'release_date'
            );
            $releaseYear=int_input(
                'release_year'
            );
            $releaseYear=(
                $releaseYear>=1900
                && $releaseYear<=2100
            )?$releaseYear:null;

            $values=[
                'title'=>$title,
                'slug'=>$slug,
                'artist_name'=>$artist,
                'album_type'=>$type,
                'status'=>$status,
                'featured'=>isset(
                    $_POST['featured']
                )?1:0,
                'sort_order'=>max(
                    0,
                    int_input('sort_order')
                ),
                'release_date'=>$releaseDate,
                'release_year'=>$releaseYear,
                'genre'=>nullable_input('genre'),
                'description'=>nullable_input(
                    'description'
                ),
                'cover_stored_name'=>$cover['stored_name'],
                'cover_extension'=>$cover['extension'],
                'cover_mime_type'=>$cover['mime_type'],
                'cover_size_bytes'=>$cover['size_bytes'],
                'cover_sha256'=>$cover['sha256'],
                'updated_by'=>(int)$user['id'],
            ];

            if($albumId>0){
                $statement=db()->prepare(
                    'UPDATE music_albums
                     SET title=:title,
                         slug=:slug,
                         artist_name=:artist_name,
                         album_type=:album_type,
                         status=:status,
                         featured=:featured,
                         sort_order=:sort_order,
                         release_date=:release_date,
                         release_year=:release_year,
                         genre=:genre,
                         description=:description,
                         cover_stored_name=:cover_stored_name,
                         cover_extension=:cover_extension,
                         cover_mime_type=:cover_mime_type,
                         cover_size_bytes=:cover_size_bytes,
                         cover_sha256=:cover_sha256,
                         updated_by=:updated_by,
                         published_at=CASE
                            WHEN :publish_status="active"
                            THEN COALESCE(
                                published_at,
                                UTC_TIMESTAMP()
                            )
                            ELSE published_at
                         END
                     WHERE id=:album_id'
                );
                $statement->execute(
                    $values+[
                        'publish_status'=>$status,
                        'album_id'=>$albumId,
                    ]
                );
                log_activity(
                    'music_album_updated',
                    'music_album',
                    $albumId
                );
                flash(
                    'success',
                    'Music album updated.'
                );
            }else{
                $statement=db()->prepare(
                    'INSERT INTO music_albums
                        (title,slug,artist_name,album_type,
                         status,featured,sort_order,
                         release_date,release_year,genre,
                         description,cover_stored_name,
                         cover_extension,cover_mime_type,
                         cover_size_bytes,cover_sha256,
                         created_by,updated_by,published_at)
                     VALUES
                        (:title,:slug,:artist_name,:album_type,
                         :status,:featured,:sort_order,
                         :release_date,:release_year,:genre,
                         :description,:cover_stored_name,
                         :cover_extension,:cover_mime_type,
                         :cover_size_bytes,:cover_sha256,
                         :created_by,:updated_by,
                         CASE WHEN :publish_status="active"
                              THEN UTC_TIMESTAMP()
                              ELSE NULL END)'
                );
                $statement->execute(
                    $values+[
                        'created_by'=>(int)$user['id'],
                        'publish_status'=>$status,
                    ]
                );
                $albumId=(int)db()->lastInsertId();
                log_activity(
                    'music_album_created',
                    'music_album',
                    $albumId
                );
                flash(
                    'success',
                    'Music album created.'
                );
            }

            redirect(
                'portal/admin.php?view=music&section=albums&edit='
                .$albumId
            );
        }

        if($action==='save_music_playlist'){
            if(!music_library_schema_available()){
                throw new RuntimeException(
                    'Import database/music_library_v44.sql before managing playlists.'
                );
            }

            $playlistId=int_input('playlist_id');
            $title=input('title');
            $slug=slugify(input('slug')?:$title);
            $status=input('status');

            if($title===''||$slug===''){
                throw new RuntimeException(
                    'Playlist title and slug are required.'
                );
            }

            if(
                strlen($title)>190
                || strlen($slug)>190
            ){
                throw new RuntimeException(
                    'One of the playlist identity fields is too long.'
                );
            }

            if(!in_array(
                $status,
                ['draft','active','archived'],
                true
            )){
                $status='draft';
            }

            $duplicate=db()->prepare(
                'SELECT id
                 FROM music_playlists
                 WHERE slug=:slug
                   AND id<>:playlist_id
                 LIMIT 1'
            );
            $duplicate->execute([
                'slug'=>$slug,
                'playlist_id'=>$playlistId,
            ]);

            if($duplicate->fetchColumn()){
                throw new RuntimeException(
                    'That playlist slug is already in use.'
                );
            }

            $existing=$playlistId>0
                ?music_admin_playlist($playlistId)
                :null;
            $cover=[
                'stored_name'=>$existing['cover_stored_name']??null,
                'extension'=>$existing['cover_extension']??null,
                'mime_type'=>$existing['cover_mime_type']??null,
                'size_bytes'=>$existing['cover_size_bytes']??null,
                'sha256'=>$existing['cover_sha256']??null,
                'changed'=>false,
            ];

            if(isset($_POST['remove_cover'])){
                if(!empty($cover['stored_name'])){
                    @unlink(
                        music_cover_storage_directory()
                        .'/'
                        .basename(
                            (string)$cover['stored_name']
                        )
                    );
                }

                $cover=[
                    'stored_name'=>null,
                    'extension'=>null,
                    'mime_type'=>null,
                    'size_bytes'=>null,
                    'sha256'=>null,
                    'changed'=>true,
                ];
            }elseif(
                isset($_FILES['cover_image'])
                && is_array($_FILES['cover_image'])
            ){
                $newCover=music_store_cover(
                    $_FILES['cover_image'],
                    $cover['stored_name']
                );

                if($newCover['changed']){
                    $cover=$newCover;
                }
            }

            $values=[
                'title'=>$title,
                'slug'=>$slug,
                'description'=>nullable_input(
                    'description'
                ),
                'status'=>$status,
                'featured'=>isset(
                    $_POST['featured']
                )?1:0,
                'sort_order'=>max(
                    0,
                    int_input('sort_order')
                ),
                'cover_stored_name'=>$cover['stored_name'],
                'cover_extension'=>$cover['extension'],
                'cover_mime_type'=>$cover['mime_type'],
                'cover_size_bytes'=>$cover['size_bytes'],
                'cover_sha256'=>$cover['sha256'],
                'updated_by'=>(int)$user['id'],
            ];

            $pdo=db();
            $pdo->beginTransaction();

            try{
                if($playlistId>0){
                    $statement=$pdo->prepare(
                        'UPDATE music_playlists
                         SET title=:title,
                             slug=:slug,
                             description=:description,
                             status=:status,
                             featured=:featured,
                             sort_order=:sort_order,
                             cover_stored_name=:cover_stored_name,
                             cover_extension=:cover_extension,
                             cover_mime_type=:cover_mime_type,
                             cover_size_bytes=:cover_size_bytes,
                             cover_sha256=:cover_sha256,
                             updated_by=:updated_by,
                             published_at=CASE
                                WHEN :publish_status="active"
                                THEN COALESCE(
                                    published_at,
                                    UTC_TIMESTAMP()
                                )
                                ELSE published_at
                             END
                         WHERE id=:playlist_id'
                    );
                    $statement->execute(
                        $values+[
                            'publish_status'=>$status,
                            'playlist_id'=>$playlistId,
                        ]
                    );
                }else{
                    $statement=$pdo->prepare(
                        'INSERT INTO music_playlists
                            (title,slug,description,status,
                             featured,sort_order,
                             cover_stored_name,
                             cover_extension,
                             cover_mime_type,
                             cover_size_bytes,
                             cover_sha256,created_by,
                             updated_by,published_at)
                         VALUES
                            (:title,:slug,:description,:status,
                             :featured,:sort_order,
                             :cover_stored_name,
                             :cover_extension,
                             :cover_mime_type,
                             :cover_size_bytes,
                             :cover_sha256,:created_by,
                             :updated_by,
                             CASE WHEN :publish_status="active"
                                  THEN UTC_TIMESTAMP()
                                  ELSE NULL END)'
                    );
                    $statement->execute(
                        $values+[
                            'created_by'=>(int)$user['id'],
                            'publish_status'=>$status,
                        ]
                    );
                    $playlistId=(int)$pdo->lastInsertId();
                }

                $pdo->prepare(
                    'DELETE FROM music_playlist_tracks
                     WHERE playlist_id=:playlist_id'
                )->execute([
                    'playlist_id'=>$playlistId,
                ]);

                $trackIds=array_values(
                    array_unique(
                        array_filter(
                            array_map(
                                'intval',
                                is_array(
                                    $_POST['track_ids']??null
                                )
                                    ?$_POST['track_ids']
                                    :[]
                            ),
                            static fn(int $id): bool =>
                                $id>0
                        )
                    )
                );
                $postedPositions=is_array(
                    $_POST['track_positions']??null
                )?$_POST['track_positions']:[];
                usort(
                    $trackIds,
                    static function(
                        int $left,
                        int $right
                    ) use ($postedPositions): int {
                        $leftPosition=max(
                            1,
                            (int)(
                                $postedPositions[$left]
                                ?? 999999
                            )
                        );
                        $rightPosition=max(
                            1,
                            (int)(
                                $postedPositions[$right]
                                ?? 999999
                            )
                        );

                        return $leftPosition
                            <=>$rightPosition
                            ?:($left<=>$right);
                    }
                );
                $position=1;
                $insertTrack=$pdo->prepare(
                    'INSERT INTO music_playlist_tracks
                        (playlist_id,track_id,position,
                         added_by)
                     VALUES
                        (:playlist_id,:track_id,:position,
                         :added_by)'
                );

                foreach($trackIds as $selectedTrackId){
                    $insertTrack->execute([
                        'playlist_id'=>$playlistId,
                        'track_id'=>$selectedTrackId,
                        'position'=>$position,
                        'added_by'=>(int)$user['id'],
                    ]);
                    $position++;
                }

                $pdo->commit();
            }catch(Throwable $exception){
                if($pdo->inTransaction()){
                    $pdo->rollBack();
                }
                throw $exception;
            }

            log_activity(
                $existing
                    ?'music_playlist_updated'
                    :'music_playlist_created',
                'music_playlist',
                $playlistId,
                ['track_count'=>count($trackIds)]
            );
            flash(
                'success',
                $existing
                    ?'Music playlist updated.'
                    :'Music playlist created.'
            );
            redirect(
                'portal/admin.php?view=music&section=playlists&edit='
                .$playlistId
            );
        }

        if($action==='save_portfolio_project'){
            if(!portfolio_schema_available()){
                throw new RuntimeException(
                    'Import database/portfolio_backend_v41.sql before managing portfolio projects.'
                );
            }

            $id=int_input('id');
            $title=input('title');
            $slug=slugify(input('slug')?:$title);
            $status=input('status');
            $projectUrl=trim(input('project_url'));
            $projectUrlLabel=input('project_url_label')?:'View project';

            if($title===''||$slug===''){
                throw new RuntimeException('Enter a portfolio title and slug.');
            }

            if(
                strlen($title)>190
                || strlen($slug)>190
                || strlen($projectUrl)>500
                || strlen($projectUrlLabel)>120
            ){
                throw new RuntimeException('One of the portfolio identity fields is too long.');
            }

            if(!in_array($status,['draft','active','archived'],true)){
                $status='draft';
            }

            if(
                $projectUrl!==''
                && (
                    !filter_var($projectUrl,FILTER_VALIDATE_URL)
                    || !preg_match('/^https?:\/\//i',$projectUrl)
                )
            ){
                throw new RuntimeException('Enter a valid HTTP or HTTPS project URL.');
            }

            $duplicate=db()->prepare(
                'SELECT id
                 FROM portfolio_projects
                 WHERE slug=:slug
                   AND id<>:project_id
                 LIMIT 1'
            );
            $duplicate->execute([
                'slug'=>$slug,
                'project_id'=>$id,
            ]);

            if($duplicate->fetchColumn()){
                throw new RuntimeException('That portfolio slug is already in use.');
            }

            $values=[
                'title'=>$title,
                'slug'=>$slug,
                'status'=>$status,
                'featured'=>isset($_POST['featured'])?1:0,
                'sort_order'=>max(0,int_input('sort_order')),
                'project_url'=>$projectUrl!==''?$projectUrl:null,
                'project_url_label'=>$projectUrlLabel,
                'client_name'=>nullable_input('client_name'),
                'project_type'=>nullable_input('project_type'),
                'industry'=>nullable_input('industry'),
                'year_label'=>nullable_input('year_label'),
                'role_title'=>nullable_input('role_title'),
                'services'=>nullable_input('services'),
                'technologies'=>nullable_input('technologies'),
                'summary'=>nullable_input('summary'),
                'overview'=>nullable_input('overview'),
                'challenge'=>nullable_input('challenge'),
                'solution'=>nullable_input('solution'),
                'results'=>nullable_input('results'),
                'keywords'=>nullable_input('keywords'),
                'updated_by'=>(int)$user['id'],
            ];

            if($id>0){
                $statement=db()->prepare(
                    'UPDATE portfolio_projects
                     SET title=:title,
                         slug=:slug,
                         status=:status,
                         featured=:featured,
                         sort_order=:sort_order,
                         project_url=:project_url,
                         project_url_label=:project_url_label,
                         client_name=:client_name,
                         project_type=:project_type,
                         industry=:industry,
                         year_label=:year_label,
                         role_title=:role_title,
                         services=:services,
                         technologies=:technologies,
                         summary=:summary,
                         overview=:overview,
                         challenge=:challenge,
                         solution=:solution,
                         results=:results,
                         keywords=:keywords,
                         updated_by=:updated_by,
                         published_at=CASE
                            WHEN :publish_status="active"
                            THEN COALESCE(published_at,UTC_TIMESTAMP())
                            ELSE published_at
                         END
                     WHERE id=:id'
                );
                $statement->execute(
                    $values+[
                        'publish_status'=>$status,
                        'id'=>$id,
                    ]
                );
                log_activity('portfolio_project_updated','portfolio_project',$id);
                flash('success','Portfolio project updated.');
            }else{
                $statement=db()->prepare(
                    'INSERT INTO portfolio_projects
                        (title,slug,status,featured,sort_order,project_url,
                         project_url_label,client_name,project_type,industry,
                         year_label,role_title,services,technologies,summary,
                         overview,challenge,solution,results,keywords,
                         created_by,updated_by,published_at)
                     VALUES
                        (:title,:slug,:status,:featured,:sort_order,:project_url,
                         :project_url_label,:client_name,:project_type,:industry,
                         :year_label,:role_title,:services,:technologies,:summary,
                         :overview,:challenge,:solution,:results,:keywords,
                         :created_by,:updated_by,
                         CASE WHEN :publish_status="active"
                              THEN UTC_TIMESTAMP() ELSE NULL END)'
                );
                $statement->execute(
                    $values+[
                        'created_by'=>(int)$user['id'],
                        'publish_status'=>$status,
                    ]
                );
                $id=(int)db()->lastInsertId();
                log_activity('portfolio_project_created','portfolio_project',$id);
                flash('success','Portfolio project created.');
            }

            $mediaIds=array_map(
                'intval',
                array_keys(is_array($_POST['media_alt']??null)?$_POST['media_alt']:[]
            ));

            foreach($mediaIds as $mediaId){
                if($mediaId<=0){
                    continue;
                }

                $alt=trim((string)($_POST['media_alt'][$mediaId]??''));
                $caption=trim((string)($_POST['media_caption'][$mediaId]??''));
                $sort=max(0,(int)($_POST['media_sort'][$mediaId]??0));

                db()->prepare(
                    'UPDATE portfolio_media
                     SET alt_text=:alt_text,
                         caption=:caption,
                         sort_order=:sort_order
                     WHERE id=:media_id
                       AND project_id=:project_id'
                )->execute([
                    'alt_text'=>$alt!==''?$alt:null,
                    'caption'=>$caption!==''?$caption:null,
                    'sort_order'=>$sort,
                    'media_id'=>$mediaId,
                    'project_id'=>$id,
                ]);
            }

            $coverMediaId=int_input('cover_media_id');

            if($coverMediaId>0){
                $pdo=db();
                $pdo->beginTransaction();

                try{
                    $pdo->prepare(
                        'UPDATE portfolio_media
                         SET media_role="gallery"
                         WHERE project_id=:project_id'
                    )->execute(['project_id'=>$id]);

                    $pdo->prepare(
                        'UPDATE portfolio_media
                         SET media_role="cover"
                         WHERE id=:media_id
                           AND project_id=:project_id'
                    )->execute([
                        'media_id'=>$coverMediaId,
                        'project_id'=>$id,
                    ]);

                    $pdo->commit();
                }catch(Throwable $exception){
                    if($pdo->inTransaction()){
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
            }

            if(
                isset($_FILES['cover_image'])
                && (int)($_FILES['cover_image']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE
            ){
                portfolio_store_image(
                    $_FILES['cover_image'],
                    $id,
                    'cover',
                    (int)$user['id'],
                    input('cover_alt'),
                    input('cover_caption'),
                    0
                );
            }

            $galleryUploads=isset($_FILES['gallery_images'])
                ?portfolio_multiple_uploads($_FILES['gallery_images'])
                :[];

            foreach($galleryUploads as $index=>$galleryUpload){
                portfolio_store_image(
                    $galleryUpload,
                    $id,
                    'gallery',
                    (int)$user['id'],
                    input('gallery_alt'),
                    input('gallery_caption'),
                    10+$index
                );
            }

            redirect('portal/admin.php?view=portfolio&edit='.$id);
        }


        if($action==='save_portfolio_media'){
            $mediaId=int_input('media_id');
            $projectId=int_input('project_id');

            if(!portfolio_schema_available()||$mediaId<=0||$projectId<=0){
                throw new RuntimeException('The portfolio image was not found.');
            }

            $pdo=db();
            $pdo->beginTransaction();

            try{
                $pdo->prepare(
                    'UPDATE portfolio_media
                     SET alt_text=:alt_text,
                         caption=:caption,
                         sort_order=:sort_order
                     WHERE id=:media_id
                       AND project_id=:project_id'
                )->execute([
                    'alt_text'=>nullable_input('alt_text'),
                    'caption'=>nullable_input('caption'),
                    'sort_order'=>max(0,int_input('sort_order')),
                    'media_id'=>$mediaId,
                    'project_id'=>$projectId,
                ]);

                if(isset($_POST['make_cover'])){
                    $pdo->prepare(
                        'UPDATE portfolio_media
                         SET media_role="gallery"
                         WHERE project_id=:project_id'
                    )->execute(['project_id'=>$projectId]);

                    $pdo->prepare(
                        'UPDATE portfolio_media
                         SET media_role="cover"
                         WHERE id=:media_id
                           AND project_id=:project_id'
                    )->execute([
                        'media_id'=>$mediaId,
                        'project_id'=>$projectId,
                    ]);
                }

                $pdo->commit();
            }catch(Throwable $exception){
                if($pdo->inTransaction()){
                    $pdo->rollBack();
                }
                throw $exception;
            }

            log_activity(
                'portfolio_media_updated',
                'portfolio_project',
                $projectId,
                ['media_id'=>$mediaId]
            );
            flash('success','Portfolio image updated.');
            redirect('portal/admin.php?view=portfolio&edit='.$projectId);
        }

        if($action==='delete_portfolio_media'){
            $mediaId=int_input('media_id');
            $projectId=int_input('project_id');
            portfolio_delete_media($mediaId,(int)$user['id']);
            flash('success','Portfolio image removed.');
            redirect('portal/admin.php?view=portfolio&edit='.$projectId);
        }

        if($action==='archive_portfolio_project'){
            $projectId=int_input('project_id');

            if(!portfolio_schema_available()||$projectId<=0){
                throw new RuntimeException('The portfolio project was not found.');
            }

            db()->prepare(
                'UPDATE portfolio_projects
                 SET status="archived",
                     updated_by=:updated_by
                 WHERE id=:project_id'
            )->execute([
                'updated_by'=>(int)$user['id'],
                'project_id'=>$projectId,
            ]);

            log_activity(
                'portfolio_project_archived',
                'portfolio_project',
                $projectId
            );
            flash('success','Portfolio project archived.');
            redirect('portal/admin.php?view=portfolio');
        }

        if($action==='save_project'){
            $id=int_input('id');$client=int_input('client_user_id');$title=input('title');
            if($client<=0||$title==='')throw new RuntimeException('Select a client and enter a project title.');
            $status=input('status');if(!in_array($status,['discovery','planning','active','review','on_hold','completed','archived'],true))$status='planning';
            $priority=input('priority');if(!in_array($priority,['low','normal','high','urgent'],true))$priority='normal';
            $values=['client'=>$client,'title'=>$title,'summary'=>nullable_input('summary'),'status'=>$status,'priority'=>$priority,'progress'=>max(0,min(100,int_input('progress'))),'start'=>nullable_input('start_date'),'due'=>nullable_input('due_date'),'budget'=>nullable_input('budget'),'mile'=>nullable_input('next_milestone'),'mdate'=>nullable_input('next_milestone_date')];
            if($id>0){
                $s=db()->prepare('UPDATE projects SET client_user_id=:client,title=:title,summary=:summary,status=:status,priority=:priority,progress=:progress,start_date=:start,due_date=:due,budget=:budget,next_milestone=:mile,next_milestone_date=:mdate WHERE id=:id');
                $s->execute($values+['id'=>$id]);flash('success','Project updated.');log_activity('project_updated','project',$id);
            }else{
                $s=db()->prepare('INSERT INTO projects(client_user_id,title,slug,summary,status,priority,progress,start_date,due_date,budget,next_milestone,next_milestone_date,created_by) VALUES(:client,:title,:slug,:summary,:status,:priority,:progress,:start,:due,:budget,:mile,:mdate,:created)');
                $s->execute($values+['slug'=>slugify($title).'-'.bin2hex(random_bytes(2)),'created'=>$user['id']]);$id=(int)db()->lastInsertId();flash('success','Project created.');log_activity('project_created','project',$id);
            }
            redirect('portal/admin.php?view=projects&edit='.$id);
        }
        if($action==='add_project_update'){
            $project=int_input('project_id');$title=input('update_title');$body=input('update_body');$visibility=input('visibility')==='admin'?'admin':'client';
            if($project<=0||$title===''||$body==='')throw new RuntimeException('Enter an update title and message.');
            $s=db()->prepare('INSERT INTO project_updates(project_id,title,body,visibility,created_by) VALUES(:p,:t,:b,:v,:u)');
            $s->execute(['p'=>$project,'t'=>$title,'b'=>$body,'v'=>$visibility,'u'=>$user['id']]);
            flash('success','Project update posted.');redirect('portal/admin.php?view=projects&edit='.$project);
        }
        if($action==='lead_status'){
            $id=int_input('id');
            $status=input('status');

            if(!in_array($status,['new','contacted','qualified','converted','closed'],true)){
                throw new RuntimeException('Invalid lead status.');
            }

            db()->prepare('UPDATE leads SET status=:status WHERE id=:id')
                ->execute(['status'=>$status,'id'=>$id]);

            $stageMap=[
                'new'=>'new',
                'contacted'=>'contacted',
                'qualified'=>'qualified',
                'converted'=>'won',
                'closed'=>'lost',
            ];
            $contactStageMap=[
                'new'=>'lead',
                'contacted'=>'prospect',
                'qualified'=>'qualified',
                'converted'=>'client',
                'closed'=>'closed',
            ];

            $probabilityMap=[
                'new'=>10,
                'contacted'=>35,
                'qualified'=>65,
                'won'=>100,
                'lost'=>0,
            ];
            $crmOpportunityStage=$stageMap[$status];

            db()->prepare(
                'UPDATE crm_opportunities
                 SET stage=:stage,
                     probability=:probability
                 WHERE lead_id=:lead_id'
            )->execute([
                'stage'=>$crmOpportunityStage,
                'probability'=>$probabilityMap[$crmOpportunityStage],
                'lead_id'=>$id,
            ]);

            db()->prepare(
                'UPDATE crm_contacts c
                 JOIN crm_opportunities o ON o.contact_id=c.id
                 SET c.lifecycle_stage=:contact_stage
                 WHERE o.lead_id=:lead_id'
            )->execute([
                'contact_stage'=>$contactStageMap[$status],
                'lead_id'=>$id,
            ]);

            flash('success','Lead and CRM status updated.');
            redirect('portal/admin.php?view=leads&id='.$id);
        }
        if($action==='convert_lead'){
            $id=int_input('id');$s=db()->prepare('SELECT * FROM leads WHERE id=:id');$s->execute(['id'=>$id]);$lead=$s->fetch();if(!$lead)throw new RuntimeException('Lead not found.');
            $find=db()->prepare('SELECT id FROM users WHERE email=:e AND role="client"');$find->execute(['e'=>$lead['email']]);$client=(int)($find->fetchColumn()?:0);$password=null;
            if(!$client){$password=random_password();$c=db()->prepare('INSERT INTO users(role,email,password_hash,display_name,company,status,must_change_password) VALUES("client",:e,:h,:n,:c,"active",1)');$c->execute(['e'=>$lead['email'],'h'=>password_hash($password,PASSWORD_DEFAULT),'n'=>$lead['name'],'c'=>$lead['company']]);$client=(int)db()->lastInsertId();db()->prepare('INSERT INTO client_profiles(user_id) VALUES(:id)')->execute(['id'=>$client]);}
            db()->prepare('UPDATE leads SET status="converted" WHERE id=:id')->execute(['id'=>$id]);
            db()->prepare(
                'UPDATE crm_contacts c
                 JOIN crm_opportunities o ON o.contact_id=c.id
                 SET c.lifecycle_stage="client",
                     c.client_user_id=:client_user_id,
                     c.last_contacted_at=UTC_TIMESTAMP(),
                     o.stage="won",
                     o.probability=100
                 WHERE o.lead_id=:lead_id'
            )->execute([
                'client_user_id'=>$client,
                'lead_id'=>$id,
            ]);
            flash('success',$password?'Lead converted. Temporary password: '.$password:'Lead linked to an existing account.');
            redirect('portal/admin.php?view=clients&edit='.$client);
        }
        if($action==='send_message'){
            $client=int_input('client_user_id');
            $project=int_input('project_id');
            $subject=input('subject');
            $body=input('body');

            if($client<=0||$subject===''||$body===''){
                throw new RuntimeException('Select a client and enter the subject and message.');
            }

            $threadId=communication_create_thread(
                $user,
                $client,
                $project>0?$project:null,
                $subject
            );
            communication_insert_message(
                $threadId,
                (int)$user['id'],
                'admin',
                'text',
                $body
            );

            flash('success','Communication sent.');
            redirect('portal/admin.php?view=communications&thread='.$threadId);
        }
        if($action==='upload_file'){
            $client=int_input('client_user_id');$project=int_input('project_id');$visibility=input('visibility')==='admin'?'admin':'client';
            if($client<=0||!isset($_FILES['file']))throw new RuntimeException('Select a client and file.');
            $f=$_FILES['file'];if((int)$f['error']!==UPLOAD_ERR_OK)throw new RuntimeException('Upload failed.');
            $size=(int)$f['size'];$max=(int)(nmm_config('app')['max_upload_bytes']??15728640);if($size<=0||$size>$max)throw new RuntimeException('File exceeds the upload limit.');
            $original=basename((string)$f['name']);$ext=strtolower(pathinfo($original,PATHINFO_EXTENSION));if(in_array($ext,['php','phtml','phar','cgi','pl','py','sh','exe','dll','bat','cmd','com','js','html','htm','svg'],true))throw new RuntimeException('This file type is blocked.');
            $mime=(new finfo(FILEINFO_MIME_TYPE))->file((string)$f['tmp_name'])?:'application/octet-stream';$stored=bin2hex(random_bytes(24)).($ext?'.'.$ext:'');$dest=NMM_ROOT.'/storage/client-files/'.$stored;
            if(!move_uploaded_file((string)$f['tmp_name'],$dest))throw new RuntimeException('Could not store uploaded file.');chmod($dest,0640);
            $s=db()->prepare('INSERT INTO files(client_user_id,project_id,uploaded_by,original_name,stored_name,mime_type,size_bytes,description,visibility) VALUES(:c,:p,:u,:o,:st,:m,:z,:d,:v)');
            $s->execute(['c'=>$client,'p'=>$project?:null,'u'=>$user['id'],'o'=>$original,'st'=>$stored,'m'=>$mime,'z'=>$size,'d'=>nullable_input('description'),'v'=>$visibility]);flash('success','File uploaded.');redirect('portal/admin.php?view=files');
        }
        if($action==='upload_knowledge_asset'){
            if(!isset($_FILES['knowledge_file'])||!is_array($_FILES['knowledge_file'])){
                throw new RuntimeException('Select a knowledge file to upload.');
            }

            $upload=$_FILES['knowledge_file'];

            if((int)$upload['error']!==UPLOAD_ERR_OK){
                throw new RuntimeException('The knowledge upload did not complete successfully.');
            }

            $size=(int)$upload['size'];
            $maximum=knowledge_upload_limit_bytes();

            if($size<=0||$size>$maximum){
                throw new RuntimeException('The knowledge file exceeds the configured upload limit of '.format_bytes($maximum).'.');
            }

            $original=basename((string)$upload['name']);
            $extension=strtolower(pathinfo($original,PATHINFO_EXTENSION));
            $allowed=knowledge_allowed_extensions();

            if(!isset($allowed[$extension])){
                throw new RuntimeException('Unsupported knowledge file type: .'.$extension);
            }

            $temporary=(string)$upload['tmp_name'];
            $detected=(new finfo(FILEINFO_MIME_TYPE))->file($temporary)?:'application/octet-stream';
            [$preferredMime,$mediaKind]=$allowed[$extension];

            $compatibleMimes=[
                $preferredMime,
                'application/octet-stream',
                'application/zip',
                'text/plain',
            ];

            if(
                !in_array($detected,$compatibleMimes,true)
                && !str_starts_with($detected,$mediaKind.'/')
                && !($mediaKind==='document'&&in_array($detected,['application/pdf','application/rtf'],true))
                && !($mediaKind==='data'&&in_array($detected,['text/csv','application/json','application/xml','text/xml'],true))
            ){
                throw new RuntimeException('The uploaded file content does not match the selected extension.');
            }

            $stored=bin2hex(random_bytes(24)).'.'.$extension;
            $destination=knowledge_storage_path($stored);

            if(!move_uploaded_file($temporary,$destination)){
                throw new RuntimeException('The server could not store the knowledge file.');
            }

            chmod($destination,0640);
            $extraction=knowledge_extract_file($destination,$extension);
            $text=knowledge_clean_text((string)($extraction['text']??''));
            $error=trim((string)($extraction['error']??''));
            $status=$text!==''?'ready':'needs_text';
            $title=trim((string)($_POST['media_title']??''));
            if($title===''){
                $title=trim(pathinfo($original,PATHINFO_FILENAME));
            }
            $title=$title!==''?$title:'Uploaded knowledge';
            $category=trim((string)($_POST['media_category']??''));
            $category=$category!==''?$category:'uploaded-knowledge';
            $summary=knowledge_auto_summary($text,$title);
            $keywords=implode(', ',knowledge_auto_keywords($text,$title));
            $sha256=hash_file('sha256',$destination);

            if($sha256===false){
                @unlink($destination);
                throw new RuntimeException('The server could not verify the uploaded file.');
            }

            $coverStored=null;
            $coverExtension=null;
            $coverMime=null;
            $coverSize=null;
            $coverSha256=null;
            $coverDestination=null;

            if(
                isset($_FILES['cover_image'])
                && is_array($_FILES['cover_image'])
                && (int)$_FILES['cover_image']['error']!==UPLOAD_ERR_NO_FILE
            ){
                $cover=$_FILES['cover_image'];

                if((int)$cover['error']!==UPLOAD_ERR_OK){
                    @unlink($destination);
                    throw new RuntimeException('The cover image upload did not complete successfully.');
                }

                $coverSize=(int)$cover['size'];

                if($coverSize<=0||$coverSize>8*1024*1024){
                    @unlink($destination);
                    throw new RuntimeException('The cover image must be under 8 MB.');
                }

                $coverOriginal=basename((string)$cover['name']);
                $coverExtension=strtolower(
                    pathinfo($coverOriginal,PATHINFO_EXTENSION)
                );
                $coverAllowed=[
                    'jpg'=>'image/jpeg',
                    'jpeg'=>'image/jpeg',
                    'png'=>'image/png',
                    'webp'=>'image/webp',
                ];

                if(!isset($coverAllowed[$coverExtension])){
                    @unlink($destination);
                    throw new RuntimeException(
                        'Cover images must be JPG, PNG, or WebP.'
                    );
                }

                $coverTemporary=(string)$cover['tmp_name'];
                $coverDetected=(new finfo(FILEINFO_MIME_TYPE))
                    ->file($coverTemporary)
                    ?:'application/octet-stream';
                $coverMime=$coverAllowed[$coverExtension];

                if(
                    $coverDetected!==$coverMime
                    && $coverDetected!=='application/octet-stream'
                ){
                    @unlink($destination);
                    throw new RuntimeException(
                        'The cover image content does not match its file type.'
                    );
                }

                $coverStored=bin2hex(random_bytes(24)).'.'.$coverExtension;
                $coverDestination=knowledge_storage_path($coverStored);

                if(!move_uploaded_file($coverTemporary,$coverDestination)){
                    @unlink($destination);
                    throw new RuntimeException(
                        'The server could not store the cover image.'
                    );
                }

                chmod($coverDestination,0640);
                $coverSha256=hash_file('sha256',$coverDestination);

                if($coverSha256===false){
                    @unlink($destination);
                    @unlink($coverDestination);
                    throw new RuntimeException(
                        'The server could not verify the cover image.'
                    );
                }
            }

            $statement=db()->prepare(
                'INSERT INTO knowledge_assets
                    (original_name,stored_name,cover_stored_name,cover_extension,
                     cover_mime_type,cover_size_bytes,cover_sha256,
                     extension,mime_type,media_kind,size_bytes,sha256,
                     title,category,summary,keywords,audiences_json,extracted_text,
                     extraction_method,extraction_status,extraction_error,uploaded_by)
                 VALUES
                    (:original_name,:stored_name,:cover_stored_name,:cover_extension,
                     :cover_mime_type,:cover_size_bytes,:cover_sha256,
                     :extension,:mime_type,:media_kind,:size_bytes,:sha256,
                     :title,:category,:summary,:keywords,:audiences_json,:extracted_text,
                     :extraction_method,:extraction_status,:extraction_error,:uploaded_by)'
            );
            try{
                $statement->execute([
                    'original_name'=>$original,
                    'stored_name'=>$stored,
                    'cover_stored_name'=>$coverStored,
                    'cover_extension'=>$coverExtension,
                    'cover_mime_type'=>$coverMime,
                    'cover_size_bytes'=>$coverSize,
                    'cover_sha256'=>$coverSha256,
                    'extension'=>$extension,
                    'mime_type'=>$preferredMime,
                    'media_kind'=>$mediaKind,
                    'size_bytes'=>$size,
                    'sha256'=>$sha256,
                    'title'=>$title,
                    'category'=>$category,
                    'summary'=>$summary,
                    'keywords'=>$keywords,
                    'audiences_json'=>json_encode(['recruiter','investor','client']),
                    'extracted_text'=>$text!==''?$text:null,
                    'extraction_method'=>$extraction['method']??null,
                    'extraction_status'=>$status,
                    'extraction_error'=>$error!==''?$error:null,
                    'uploaded_by'=>$user['id'],
                ]);
            }catch(Throwable $exception){
                @unlink($destination);

                if($coverDestination&&is_file($coverDestination)){
                    @unlink($coverDestination);
                }

                throw $exception;
            }

            $assetId=(int)db()->lastInsertId();
            log_activity('knowledge_asset_uploaded','knowledge_asset',$assetId,[
                'extension'=>$extension,
                'media_kind'=>$mediaKind,
                'extraction_status'=>$status,
            ]);

            $transcriptionConfig=transcription_config();
            $queuedTranscription=false;

            if(
                in_array($mediaKind,['audio','video'],true)
                && (bool)($transcriptionConfig['auto_queue_on_upload']??false)
                && transcription_enabled()
            ){
                transcription_queue(
                    $assetId,
                    (int)$user['id'],
                    false,
                    trim((string)($transcriptionConfig['language']??''))?:null,
                    trim((string)($transcriptionConfig['prompt']??''))?:null
                );
                $queuedTranscription=true;
            }

            if($queuedTranscription){
                flash(
                    'success',
                    'Knowledge media uploaded and queued for automatic transcription. The transcript will move to Review when processing finishes.'
                );
            }elseif($text!==''){
                flash(
                    'success',
                    'Knowledge file uploaded and text extracted. Review it, then publish it to chat.'
                );
            }else{
                flash(
                    'warning',
                    in_array($mediaKind,['audio','video'],true)
                        ? 'Knowledge media uploaded. Configure automatic transcription or enter a transcript manually.'
                        : 'Knowledge file uploaded. Add a description or source text before publishing it to chat.'
                );
            }

            redirect('portal/admin.php?view=knowledge&section=media&asset='.$assetId);
        }

        if($action==='queue_knowledge_transcription'){
            $assetId=int_input('asset_id');
            $speakerDiarization=isset($_POST['speaker_diarization']);
            $language=nullable_input('transcription_language');
            $prompt=nullable_input('transcription_prompt');

            $jobId=transcription_queue(
                $assetId,
                (int)$user['id'],
                $speakerDiarization,
                $language,
                $prompt,
                true
            );

            flash(
                'success',
                'Transcription queued. It will process through the cron worker, or you can click Process now.'
            );
            redirect('portal/admin.php?view=knowledge&section=media&asset='.$assetId.'&job='.$jobId);
        }

        if($action==='process_knowledge_transcription'){
            $assetId=int_input('asset_id');
            $jobId=int_input('job_id');

            if($jobId<=0){
                throw new RuntimeException('Transcription job not found.');
            }

            @set_time_limit(0);
            $results=transcription_run_queue(1,$jobId);
            $result=$results[0]??null;

            if(!$result){
                throw new RuntimeException('The queued transcription job could not be claimed.');
            }

            flash(
                ($result['ok']??false)?'success':'warning',
                ($result['ok']??false)
                    ? 'Transcription completed and is ready for review.'
                    : 'Transcription did not complete: '.($result['error']??'Unknown error.')
            );
            redirect('portal/admin.php?view=knowledge&section=media&asset='.$assetId);
        }

        if($action==='save_transcription_review'||$action==='approve_transcription_publish'){
            $assetId=int_input('asset_id');
            $jobId=int_input('job_id');
            $transcript=knowledge_clean_text(input('reviewed_transcript'));

            if($assetId<=0||$jobId<=0){
                throw new RuntimeException('Transcription review record not found.');
            }

            if($transcript===''){
                throw new RuntimeException('The reviewed transcript cannot be empty.');
            }

            $assetStatement=db()->prepare('SELECT * FROM knowledge_assets WHERE id=:id');
            $assetStatement->execute(['id'=>$assetId]);
            $asset=$assetStatement->fetch();

            if(!$asset){
                throw new RuntimeException('Knowledge asset not found.');
            }

            $summary=knowledge_auto_summary($transcript,(string)$asset['title']);
            $keywords=implode(', ',knowledge_auto_keywords($transcript,(string)$asset['title']));

            db()->prepare(
                'UPDATE knowledge_transcription_jobs
                 SET reviewed_transcript_text=:reviewed_transcript_text,
                     error_message=NULL
                 WHERE id=:id AND asset_id=:asset_id'
            )->execute([
                'reviewed_transcript_text'=>$transcript,
                'id'=>$jobId,
                'asset_id'=>$assetId,
            ]);

            db()->prepare(
                'UPDATE knowledge_assets
                 SET extracted_text=:extracted_text,
                     extraction_status="ready",
                     extraction_error=NULL,
                     summary=:summary,
                     keywords=:keywords
                 WHERE id=:id'
            )->execute([
                'extracted_text'=>$transcript,
                'summary'=>$summary,
                'keywords'=>$keywords,
                'id'=>$assetId,
            ]);

            if($action==='approve_transcription_publish'){
                $assetStatement->execute(['id'=>$assetId]);
                $asset=$assetStatement->fetch();

                if(!$asset){
                    throw new RuntimeException('Knowledge asset not found after transcript review.');
                }

                $entryId=knowledge_publish_asset($asset);

                db()->prepare(
                    'UPDATE knowledge_assets
                     SET entry_id=:entry_id,
                         status="published",
                         is_public=1,
                         published_at=COALESCE(published_at,UTC_TIMESTAMP())
                     WHERE id=:id'
                )->execute([
                    'entry_id'=>$entryId,
                    'id'=>$assetId,
                ]);

                db()->prepare(
                    'UPDATE knowledge_transcription_jobs
                     SET status="approved",
                         reviewed_by=:reviewed_by,
                         reviewed_at=UTC_TIMESTAMP()
                     WHERE id=:id AND asset_id=:asset_id'
                )->execute([
                    'reviewed_by'=>$user['id'],
                    'id'=>$jobId,
                    'asset_id'=>$assetId,
                ]);

                log_activity(
                    'knowledge_transcription_approved',
                    'knowledge_transcription_job',
                    $jobId,
                    ['asset_id'=>$assetId,'entry_id'=>$entryId]
                );

                flash(
                    'success',
                    'Transcript approved and the media was published to chat.'
                );
            }else{
                log_activity(
                    'knowledge_transcription_review_saved',
                    'knowledge_transcription_job',
                    $jobId,
                    ['asset_id'=>$assetId]
                );

                flash('success','Transcript review saved.');
            }

            redirect('portal/admin.php?view=knowledge&section=media&asset='.$assetId);
        }

        if($action==='cancel_knowledge_transcription'){
            $assetId=int_input('asset_id');
            $jobId=int_input('job_id');

            db()->prepare(
                'UPDATE knowledge_transcription_jobs
                 SET status="cancelled",
                     error_message="Cancelled by an administrator."
                 WHERE id=:id
                   AND asset_id=:asset_id
                   AND status IN("queued","failed")'
            )->execute([
                'id'=>$jobId,
                'asset_id'=>$assetId,
            ]);

            log_activity(
                'knowledge_transcription_cancelled',
                'knowledge_transcription_job',
                $jobId,
                ['asset_id'=>$assetId]
            );
            flash('success','Transcription job cancelled.');
            redirect('portal/admin.php?view=knowledge&section=media&asset='.$assetId);
        }

        if($action==='replace_knowledge_cover'){
            $assetId=int_input('asset_id');

            if($assetId<=0){
                throw new RuntimeException('Knowledge asset not found.');
            }

            if(
                !isset($_FILES['cover_image'])
                || !is_array($_FILES['cover_image'])
                || (int)$_FILES['cover_image']['error']!==UPLOAD_ERR_OK
            ){
                throw new RuntimeException('Choose a cover image to upload.');
            }

            $assetStatement=db()->prepare(
                'SELECT * FROM knowledge_assets WHERE id=:id'
            );
            $assetStatement->execute(['id'=>$assetId]);
            $asset=$assetStatement->fetch();

            if(!$asset){
                throw new RuntimeException('Knowledge asset not found.');
            }

            $cover=$_FILES['cover_image'];
            $coverSize=(int)$cover['size'];

            if($coverSize<=0||$coverSize>8*1024*1024){
                throw new RuntimeException('The cover image must be under 8 MB.');
            }

            $coverOriginal=basename((string)$cover['name']);
            $coverExtension=strtolower(
                pathinfo($coverOriginal,PATHINFO_EXTENSION)
            );
            $coverAllowed=[
                'jpg'=>'image/jpeg',
                'jpeg'=>'image/jpeg',
                'png'=>'image/png',
                'webp'=>'image/webp',
            ];

            if(!isset($coverAllowed[$coverExtension])){
                throw new RuntimeException(
                    'Cover images must be JPG, PNG, or WebP.'
                );
            }

            $coverTemporary=(string)$cover['tmp_name'];
            $coverDetected=(new finfo(FILEINFO_MIME_TYPE))
                ->file($coverTemporary)
                ?:'application/octet-stream';
            $coverMime=$coverAllowed[$coverExtension];

            if(
                $coverDetected!==$coverMime
                && $coverDetected!=='application/octet-stream'
            ){
                throw new RuntimeException(
                    'The cover image content does not match its file type.'
                );
            }

            $coverStored=bin2hex(random_bytes(24)).'.'.$coverExtension;
            $coverDestination=knowledge_storage_path($coverStored);

            if(!move_uploaded_file($coverTemporary,$coverDestination)){
                throw new RuntimeException(
                    'The server could not store the cover image.'
                );
            }

            chmod($coverDestination,0640);
            $coverSha256=hash_file('sha256',$coverDestination);

            if($coverSha256===false){
                @unlink($coverDestination);
                throw new RuntimeException(
                    'The server could not verify the cover image.'
                );
            }

            try{
                db()->prepare(
                    'UPDATE knowledge_assets
                     SET cover_stored_name=:cover_stored_name,
                         cover_extension=:cover_extension,
                         cover_mime_type=:cover_mime_type,
                         cover_size_bytes=:cover_size_bytes,
                         cover_sha256=:cover_sha256
                     WHERE id=:id'
                )->execute([
                    'cover_stored_name'=>$coverStored,
                    'cover_extension'=>$coverExtension,
                    'cover_mime_type'=>$coverMime,
                    'cover_size_bytes'=>$coverSize,
                    'cover_sha256'=>$coverSha256,
                    'id'=>$assetId,
                ]);
            }catch(Throwable $exception){
                @unlink($coverDestination);
                throw $exception;
            }

            if(!empty($asset['cover_stored_name'])){
                $oldCover=knowledge_storage_path(
                    (string)$asset['cover_stored_name']
                );

                if(is_file($oldCover)){
                    @unlink($oldCover);
                }
            }

            log_activity(
                'knowledge_asset_cover_updated',
                'knowledge_asset',
                $assetId,
                ['cover_extension'=>$coverExtension]
            );
            flash('success','Media cover image updated.');
            redirect(
                'portal/admin.php?view=knowledge&asset='.
                $assetId
            );
        }

        if($action==='save_knowledge_asset'||$action==='publish_knowledge_asset'){
            $assetId=int_input('asset_id');

            if($assetId<=0){
                throw new RuntimeException('Knowledge asset not found.');
            }

            $title=input('title');
            $category=input('category');
            $text=knowledge_clean_text(input('extracted_text'));
            $summary=input('summary');
            $keywords=input('keywords');
            $audiences=array_values(array_intersect(
                $_POST['audiences']??[],
                ['recruiter','investor','client']
            ));

            if($title===''||$category===''){
                throw new RuntimeException('Enter the knowledge title and category.');
            }

            if(!$audiences){
                $audiences=['recruiter','investor','client'];
            }

            if($summary===''){
                $summary=knowledge_auto_summary($text,$title);
            }

            if($keywords===''){
                $keywords=implode(', ',knowledge_auto_keywords($text,$title));
            }

            db()->prepare(
                'UPDATE knowledge_assets
                 SET title=:title,
                     category=:category,
                     summary=:summary,
                     keywords=:keywords,
                     audiences_json=:audiences_json,
                     extracted_text=:extracted_text,
                     extraction_status=:extraction_status,
                     extraction_error=NULL
                 WHERE id=:id'
            )->execute([
                'title'=>$title,
                'category'=>$category,
                'summary'=>$summary,
                'keywords'=>$keywords,
                'audiences_json'=>json_encode($audiences),
                'extracted_text'=>$text!==''?$text:null,
                'extraction_status'=>$text!==''?'ready':'needs_text',
                'id'=>$assetId,
            ]);

            if($action==='publish_knowledge_asset'){
                $assetStatement=db()->prepare('SELECT * FROM knowledge_assets WHERE id=:id');
                $assetStatement->execute(['id'=>$assetId]);
                $asset=$assetStatement->fetch();

                if(!$asset){
                    throw new RuntimeException('Knowledge asset not found.');
                }

                $entryId=knowledge_publish_asset($asset);

                db()->prepare(
                    'UPDATE knowledge_assets
                     SET entry_id=:entry_id,
                         status="published",
                         is_public=1,
                         published_at=COALESCE(published_at,UTC_TIMESTAMP())
                     WHERE id=:id'
                )->execute([
                    'entry_id'=>$entryId,
                    'id'=>$assetId,
                ]);

                log_activity('knowledge_asset_published','knowledge_asset',$assetId,[
                    'entry_id'=>$entryId,
                ]);
                flash('success','Knowledge asset published to the chat knowledge base.');
            }else{
                log_activity('knowledge_asset_updated','knowledge_asset',$assetId);
                flash('success','Knowledge asset saved.');
            }

            redirect('portal/admin.php?view=knowledge&section=media&asset='.$assetId);
        }

        if($action==='reextract_knowledge_asset'){
            $assetId=int_input('asset_id');
            $statement=db()->prepare('SELECT * FROM knowledge_assets WHERE id=:id');
            $statement->execute(['id'=>$assetId]);
            $asset=$statement->fetch();

            if(!$asset){
                throw new RuntimeException('Knowledge asset not found.');
            }

            $path=knowledge_storage_path((string)$asset['stored_name']);

            if(!is_file($path)){
                throw new RuntimeException('The stored knowledge file is missing.');
            }

            $extraction=knowledge_extract_file($path,(string)$asset['extension']);
            $text=knowledge_clean_text((string)($extraction['text']??''));
            $error=trim((string)($extraction['error']??''));

            db()->prepare(
                'UPDATE knowledge_assets
                 SET extracted_text=:extracted_text,
                     extraction_method=:extraction_method,
                     extraction_status=:extraction_status,
                     extraction_error=:extraction_error
                 WHERE id=:id'
            )->execute([
                'extracted_text'=>$text!==''?$text:null,
                'extraction_method'=>$extraction['method']??null,
                'extraction_status'=>$text!==''?'ready':'needs_text',
                'extraction_error'=>$error!==''?$error:null,
                'id'=>$assetId,
            ]);

            flash(
                $text!==''?'success':'warning',
                $text!==''?'The file text was extracted again.':'Automatic extraction is unavailable for this file. Add the transcript or notes manually.'
            );
            redirect('portal/admin.php?view=knowledge&section=media&asset='.$assetId);
        }

        if($action==='unpublish_knowledge_asset'){
            $assetId=int_input('asset_id');
            $statement=db()->prepare('SELECT entry_id FROM knowledge_assets WHERE id=:id');
            $statement->execute(['id'=>$assetId]);
            $entryId=$statement->fetchColumn();

            knowledge_remove_published_entry($entryId!==false?(string)$entryId:null);

            db()->prepare(
                'UPDATE knowledge_assets
                 SET status="draft",is_public=0
                 WHERE id=:id'
            )->execute(['id'=>$assetId]);

            log_activity('knowledge_asset_unpublished','knowledge_asset',$assetId);
            flash('success','Knowledge asset removed from public chat results.');
            redirect('portal/admin.php?view=knowledge&section=media&asset='.$assetId);
        }

        if($action==='delete_knowledge_asset'){
            $assetId=int_input('asset_id');
            $statement=db()->prepare('SELECT * FROM knowledge_assets WHERE id=:id');
            $statement->execute(['id'=>$assetId]);
            $asset=$statement->fetch();

            if(!$asset){
                throw new RuntimeException('Knowledge asset not found.');
            }

            knowledge_remove_published_entry(
                $asset['entry_id']!==null?(string)$asset['entry_id']:null
            );

            $file=knowledge_storage_path((string)$asset['stored_name']);

            if(is_file($file)){
                @unlink($file);
            }

            if(!empty($asset['cover_stored_name'])){
                $coverFile=knowledge_storage_path(
                    (string)$asset['cover_stored_name']
                );

                if(is_file($coverFile)){
                    @unlink($coverFile);
                }
            }

            db()->prepare('DELETE FROM knowledge_assets WHERE id=:id')
                ->execute(['id'=>$assetId]);

            log_activity('knowledge_asset_deleted','knowledge_asset',$assetId);
            flash('success','Knowledge asset and its chat entry were deleted.');
            redirect('portal/admin.php?view=knowledge');
        }

        if($action==='save_knowledge'){
            $id=input('entry_id');$json=NMM_ROOT.'/chat-knowledge-base/knowledge-base.json';$js=NMM_ROOT.'/chat-knowledge-base/knowledge-base.js';$data=json_decode((string)file_get_contents($json),true,512,JSON_THROW_ON_ERROR);$found=false;
            foreach($data['entries'] as &$entry){if(($entry['id']??'')!==$id)continue;$entry['title']=input('title');$entry['category']=input('category');$entry['summary']=input('summary');$entry['answer']=input('answer');$entry['keywords']=array_values(array_filter(array_map('trim',preg_split('/[\r\n,]+/',input('keywords'))?:[])));$entry['audiences']=array_values(array_intersect($_POST['audiences']??[],['recruiter','investor','client']));$found=true;break;}unset($entry);
            if(!$found)throw new RuntimeException('Knowledge entry not found.');$data['updated']=gmdate('Y-m-d');$encoded=json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);copy($json,NMM_ROOT.'/storage/knowledge-backups/knowledge-'.gmdate('Ymd-His').'.json');file_put_contents($json,$encoded.PHP_EOL,LOCK_EX);file_put_contents($js,'window.DAVE_KNOWLEDGE_BASE = '.$encoded.';'.PHP_EOL,LOCK_EX);flash('success','Knowledge entry updated.');redirect('portal/admin.php?view=knowledge&section=text&id='.rawurlencode($id));
        }
        if($action==='save_settings'){
            $siteName=input('site_name');
            if($siteName===''){
                throw new RuntimeException('Enter a site name.');
            }

            $mobileLogoMode=input('mobile_header_logo_mode');
            if(!in_array($mobileLogoMode,['logo','name','hidden'],true)){
                $mobileLogoMode='logo';
            }
            $pairs=[
                'site_name'=>substr($siteName,0,190),
                'portal_welcome'=>substr(input('portal_welcome'),0,2000),
                'site_logo_alt'=>substr(input('site_logo_alt'),0,190),
                'mobile_header_logo_mode'=>$mobileLogoMode,
                'seo_site_url'=>rtrim(substr(input('seo_site_url'),0,500),'/'),
                'microgifter_connection_mode'=>in_array(input('microgifter_connection_mode'),['disabled','demo','api','mcp','homeserver'],true)?input('microgifter_connection_mode'):'disabled',
                'microgifter_endpoint'=>substr(input('microgifter_endpoint'),0,500),
                'microgifter_merchant_id'=>substr(input('microgifter_merchant_id'),0,190),
                'microgifter_cache_minutes'=>(string)max(1,min(1440,int_input('microgifter_cache_minutes',15))),
                'microgifter_timeout_seconds'=>(string)max(2,min(30,int_input('microgifter_timeout_seconds',8))),
                'microgifter_live_transactions_enabled'=>isset($_POST['microgifter_live_transactions_enabled'])?'1':'0',
                'microgifter_contact_sync_enabled'=>isset($_POST['microgifter_contact_sync_enabled'])?'1':'0',
                'microgifter_analytics_sync_enabled'=>isset($_POST['microgifter_analytics_sync_enabled'])?'1':'0',
            ];
            foreach(nmm_module_definitions() as $moduleKey=>$definition){
                $pairs['module_'.$moduleKey.'_enabled']=isset($_POST['module_'.$moduleKey.'_enabled'])?'1':'0';
            }

            $uploadSlots=[
                'site_logo'=>['type'=>'logo','stored'=>'site_logo_stored_name','mime'=>'site_logo_mime','remove'=>'remove_site_logo'],
            ];

            foreach($uploadSlots as $field=>$slot){
                $currentStored=nmm_site_setting($slot['stored']);
                $uploaded=nmm_store_site_image($_FILES[$field]??null,$slot['type']==='logo'?'logo':$field);
                if($uploaded){
                    if($currentStored!==''){
                        nmm_remove_site_media_file($currentStored,$slot['type']);
                    }
                    $pairs[$slot['stored']]=$uploaded['stored_name'];
                    $pairs[$slot['mime']]=$uploaded['mime'];
                }elseif(isset($_POST[$slot['remove']])){
                    nmm_remove_site_media_file($currentStored,$slot['type']);
                    $pairs[$slot['stored']]='';
                    $pairs[$slot['mime']]='';
                }
            }

            if(input('microgifter_token')!==''){
                $pairs['microgifter_token_encrypted']=microgifter_encrypt(input('microgifter_token'));
            }elseif(isset($_POST['remove_microgifter_token'])){
                $pairs['microgifter_token_encrypted']='';
            }

            $statement=db()->prepare(
                'INSERT INTO settings(setting_key,setting_value)
                 VALUES(:k,:v)
                 ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'
            );
            foreach($pairs as $key=>$value){
                $statement->execute(['k'=>$key,'v'=>$value]);
            }
            log_activity('site_settings_updated','settings');
            flash('success','Site modules, branding, and integration settings updated.');
            redirect('portal/admin.php?view=settings');
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
            redirect('portal/admin.php?view=account');
        }

        if($action==='reset_password'||$action==='change_password'){
            $cur=(string)($_POST['current_password']??'');$new=(string)($_POST['new_password']??'');$confirm=(string)($_POST['confirm_password']??'');$s=db()->prepare('SELECT password_hash FROM users WHERE id=:id');$s->execute(['id'=>$user['id']]);if(!password_verify($cur,(string)$s->fetchColumn()))throw new RuntimeException('Current password is not correct.');$errors=password_policy_errors($new,(string)$user['email']);if($errors)throw new RuntimeException(implode(' ',$errors));if(!hash_equals($new,$confirm))throw new RuntimeException('The new passwords do not match.');db()->prepare('UPDATE users SET password_hash=:h,must_change_password=0 WHERE id=:id')->execute(['h'=>password_hash($new,PASSWORD_DEFAULT),'id'=>$user['id']]);flash('success','Password reset.');redirect('portal/admin.php?view=account');
        }
    }catch(Throwable $e){flash('error',$e->getMessage());redirect('portal/admin.php?view='.$view);}
}

if($view==='builder'){redirect('portal/site-builder.php');}

$title=$view==='site-analytics'?'Site Analytics':($view==='menus'?'Navigation':status_label($view));
portal_header($title,$view,$user);

if($view==='menus'){
    site_menu_render_admin($user);
    portal_footer();
    exit;
}

if($view==='site-analytics'){
    site_analytics_render_admin();
    portal_footer();
    exit;
}

if($view==='blog'){
    publishing_render_blog_admin($user);
    portal_footer();
    exit;
}

if($view==='resume'){
    publishing_render_resume_admin($user);
    portal_footer();
    exit;
}

if($view==='events'){
    events_render_admin($user);
    portal_footer();
    exit;
}

if($view==='bookings'){
    booking_render_admin($user);
    portal_footer();
    exit;
}

if($view==='proposals'){
    proposals_render_admin($user);
    portal_footer();
    exit;
}

if($view==='dashboard'){
    $stats=[
        'clients'=>(int)db()->query('SELECT COUNT(*) FROM users WHERE role="client" AND status="active"')->fetchColumn(),
        'projects'=>(int)db()->query('SELECT COUNT(*) FROM projects WHERE status NOT IN("completed","archived")')->fetchColumn(),
        'contacts'=>(int)db()->query('SELECT COUNT(*) FROM crm_contacts WHERE lifecycle_stage<>"closed"')->fetchColumn(),
        'opportunities'=>(int)db()->query('SELECT COUNT(*) FROM crm_opportunities WHERE stage NOT IN("won","lost")')->fetchColumn(),
        'new_inquiries'=>(int)db()->query('SELECT COUNT(*) FROM crm_opportunities WHERE stage="new"')->fetchColumn(),
        'follow_ups'=>(int)db()->query('SELECT COUNT(*) FROM crm_contacts WHERE next_follow_up_at IS NOT NULL AND next_follow_up_at<=UTC_TIMESTAMP() AND lifecycle_stage<>"closed"')->fetchColumn(),
        'communications_unread'=>(int)db()->query(
            'SELECT COUNT(*)
             FROM communication_messages m
             JOIN communication_threads t ON t.id=m.thread_id
             LEFT JOIN communication_thread_members thread_member
               ON thread_member.thread_id=t.id
              AND thread_member.user_id='.(int)$user['id'].'
             WHERE m.id>COALESCE(thread_member.last_read_message_id,0)
               AND (
                   m.sender_user_id IS NULL
                   OR m.sender_user_id<>'.(int)$user['id'].'
               )'
        )->fetchColumn(),
        'active_calls'=>(int)db()->query(
            'SELECT COUNT(*)
             FROM communication_calls
             WHERE status IN("ringing","accepted")'
        )->fetchColumn(),
        'call_center_waiting'=>(int)db()->query(
            'SELECT COUNT(*)
             FROM call_center_requests
             WHERE status IN("new","queued","scheduled","ringing")'
        )->fetchColumn(),
        'notifications_unread'=>notification_unread_count((int)$user['id']),
        'events_upcoming'=>events_schema_available()
            ?(int)db()->query('SELECT COUNT(*) FROM calendar_events WHERE status IN("published","draft") AND COALESCE(end_at,start_at)>=UTC_TIMESTAMP()')->fetchColumn()
            :0,
    ];
    $projects=array_slice(admin_projects(),0,6);
    $contacts=db()->query(
        'SELECT c.*,
                (SELECT o.title FROM crm_opportunities o WHERE o.contact_id=c.id ORDER BY o.created_at DESC LIMIT 1) AS latest_opportunity
         FROM crm_contacts c
         ORDER BY COALESCE(c.last_inquiry_at,c.updated_at) DESC
         LIMIT 6'
    )->fetchAll();
    $callHistory=call_center_dashboard_history(8);
    $dashboardMessageStagesReady=crm_message_stage_columns_available();
?>
<div class="stats-grid dashboard-stats">
<article class="stat-card"><span>Active clients</span><strong><?=$stats['clients']?></strong><small>Portal accounts</small></article>
<article class="stat-card"><span>Open projects</span><strong><?=$stats['projects']?></strong><small>Current work</small></article>
<article class="stat-card"><span>CRM contacts</span><strong><?=$stats['contacts']?></strong><small>Active relationships</small></article>
<article class="stat-card"><span>Open opportunities</span><strong><?=$stats['opportunities']?></strong><small><?=$stats['new_inquiries']?> new inquiries</small></article>
<article class="stat-card"><span>Follow-ups due</span><strong><?=$stats['follow_ups']?></strong><small>CRM next actions</small></article>
<article class="stat-card"><span>Unread communications</span><strong><?=$stats['communications_unread']?></strong><small><?=$stats['active_calls']?> active calls</small></article>
<article class="stat-card"><span>Call Center queue</span><strong><?=$stats['call_center_waiting']?></strong><small>Public and client requests</small></article>
<article class="stat-card"><span>Notifications</span><strong><?=$stats['notifications_unread']?></strong><small>Unread activity</small></article>
<article class="stat-card"><span>Upcoming events</span><strong><?=$stats['events_upcoming']?></strong><small>Calendar schedule</small></article>
</div>


<section
    class="panel dashboard-history-panel"
    data-dashboard-history
    data-message-api="<?=e(app_url('portal/crm-message-api.php'))?>"
>
<header class="panel-header dashboard-history-header">
<div>
<span>Recent contact activity</span>
<h2>Call &amp; Message History</h2>
</div>
<a href="?view=call-center">Open Call Center</a>
</header>

<?php if(!$callHistory):?>
<div class="empty-state">Calls, messages, callbacks, and voicemail recordings will appear here.</div>
<?php else:?>
<div class="dashboard-history-list">
<?php foreach($callHistory as $historyItem):?>
<?php
$historyName=trim((string)($historyItem['caller_name']??''))?:'Unknown caller';
$historyType=call_center_request_type_label($historyItem);
$historyTime=call_center_history_time($historyItem);
$historyDuration=$historyItem['duration_seconds']!==null
    ?(int)$historyItem['duration_seconds']
    :(
        $historyItem['media_duration_seconds']!==null
            ?(int)round((float)$historyItem['media_duration_seconds'])
            :null
    );
$historyMessage=trim((string)($historyItem['message']??''));
$historyTranscript=trim((string)($historyItem['media_transcript']??$historyItem['transcript_text']??''));
$historyMediaId=(int)($historyItem['media_id']??0);
$historyContactId=(int)($historyItem['crm_contact_id']??0);
$historyRequestId=(int)$historyItem['id'];
$historyStage=(string)($historyItem['message_stage']??'new');
$isRecordedMessage=$historyMediaId>0;
?>
<article
    class="dashboard-history-item"
    data-dashboard-history-item
    data-message-stage="<?=e($historyStage)?>"
>
<header>
<div class="dashboard-history-identity">
<span class="dashboard-history-type"><?=e($historyType)?></span>
<div>
<h3><?=e($historyName)?></h3>
<p>
<?=e($historyItem['subject']?:$historyType)?>
<?php if($historyItem['caller_company']):?>
<span>· <?=e($historyItem['caller_company'])?></span>
<?php endif;?>
</p>
</div>
</div>

<div class="dashboard-history-status">
<span class="status status-<?=e(
    in_array($historyItem['status'],['completed','resolved','voicemail'],true)
        ?'active'
        :(
            in_array($historyItem['status'],['missed','declined','failed'],true)
                ?'on_hold'
                :'planning'
        )
)?>"><?=e(status_label($historyItem['status']))?></span>
<?php if(
    $dashboardMessageStagesReady
    && in_array($historyItem['request_type'],['voicemail','call_request','callback'],true)
):?>
<span
    class="status status-crm-message-<?=e($historyStage)?>"
    data-dashboard-history-stage
><?=e(crm_message_stage_label($historyStage))?></span>
<?php endif;?>
</div>
</header>

<div class="dashboard-history-meta">
<span><strong>Activity</strong><?=e(format_datetime($historyTime))?></span>
<span><strong>Source</strong><?=e(status_label($historyItem['source']))?></span>
<span><strong>Duration</strong><?=e(call_center_seconds_label($historyDuration))?></span>
<?php if($historyItem['answered_at']):?>
<span><strong>Answered</strong><?=e(format_datetime($historyItem['answered_at']))?></span>
<?php elseif($historyItem['ringing_at']):?>
<span><strong>Rang</strong><?=e(format_datetime($historyItem['ringing_at']))?></span>
<?php endif;?>
</div>

<?php if($historyMessage!==''):?>
<p class="dashboard-history-message"><?=e($historyMessage)?></p>
<?php endif;?>

<?php if($isRecordedMessage):?>
<div class="dashboard-history-player">
<div>
<span><?=e(
    $historyItem['media_type']==='call_recording'
        ?'Call recording'
        :'Voice message'
)?></span>
<small>
<?=e(format_datetime($historyItem['media_created_at']))?>
<?php if($historyDuration!==null):?>
 · <?=e(call_center_seconds_label($historyDuration))?>
<?php endif;?>
</small>
</div>
<audio
    controls
    preload="metadata"
    src="<?=e(app_url('portal/call-center-media.php?id='.$historyMediaId))?>"
    data-dashboard-history-audio
    data-request-id="<?=$historyRequestId?>"
    data-contact-id="<?=$historyContactId?>"
    data-current-stage="<?=e($historyStage)?>"
></audio>
</div>
<?php endif;?>

<?php if($historyTranscript!==''):?>
<details class="dashboard-history-transcript">
<summary>Transcript</summary>
<p><?=e($historyTranscript)?></p>
</details>
<?php endif;?>

<footer>
<div>
<?php if($historyItem['caller_email']):?><span><?=e($historyItem['caller_email'])?></span><?php endif;?>
<?php if($historyItem['caller_phone']):?><span><?=e($historyItem['caller_phone'])?></span><?php endif;?>
</div>
<a
    class="button button-small"
    href="?view=call-center&amp;request=<?=$historyRequestId?>"
>Open record</a>
</footer>
</article>
<?php endforeach;?>
</div>
<?php endif;?>
</section>

<div class="dashboard-grid dashboard-recent-grid">
<section class="panel">
<header class="panel-header"><h2>Recent projects</h2><a href="?view=projects">View all</a></header>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Project</th><th>Client</th><th>Status</th><th>Progress</th></tr></thead><tbody>
<?php foreach($projects as $p):?>
<tr><td><a href="?view=projects&edit=<?=(int)$p['id']?>"><?=e($p['title'])?></a></td><td><?=e($p['company']?:$p['client_name'])?></td><td><span class="status status-<?=e($p['status'])?>"><?=e(status_label($p['status']))?></span></td><td><div class="progress"><div class="progress-track"><span style="width:<?=(int)$p['progress']?>%"></span></div><small><?=(int)$p['progress']?>%</small></div></td></tr>
<?php endforeach;?>
</tbody></table></div>
</section>
<section class="panel">
<header class="panel-header"><h2>Recent CRM contacts</h2><a href="?view=crm">View CRM</a></header>
<div class="panel-body">
<?php if(!$contacts):?><div class="empty-state">No CRM contacts yet.</div><?php else:?><div class="timeline">
<?php foreach($contacts as $contact):?>
<article class="timeline-item">
<h3><a href="?view=crm&id=<?=(int)$contact['id']?>"><?=e($contact['display_name'])?></a></h3>
<p><?=e($contact['latest_opportunity']?:$contact['company']?:'Website contact')?></p>
<small><?=e(status_label($contact['lifecycle_stage']))?> · <?=e(format_datetime($contact['last_inquiry_at']?:$contact['updated_at']))?></small>
</article>
<?php endforeach;?>
</div><?php endif;?>
</div>
</section>
</div>
<?php
}



if($view==='music'){
    $musicReady=music_library_schema_available();
    $musicSection=(string)($_GET['section']??'tracks');

    if(!in_array(
        $musicSection,
        ['tracks','albums','playlists','import','banner','demo'],
        true
    )){
        $musicSection='tracks';
    }

    $musicTracks=$musicReady
        ?music_admin_tracks()
        :[];
    $musicAlbums=$musicReady
        ?music_admin_albums()
        :[];
    $musicPlaylists=$musicReady
        ?music_admin_playlists()
        :[];
    $unlinkedAudio=$musicReady
        ?music_audio_assets(true)
        :[];
    $musicBannerSettings=music_banner_settings();
    $musicBannerHasImage=music_banner_image_exists(
        $musicBannerSettings
    );
    $musicDemoSettings=music_demo_mode_settings();
    $edit=(string)($_GET['edit']??'');
    $selectedTrack=(
        $musicReady
        && $musicSection==='tracks'
        && ctype_digit($edit)
    )?music_admin_track((int)$edit):null;
    $selectedAlbum=(
        $musicReady
        && $musicSection==='albums'
        && ctype_digit($edit)
    )?music_admin_album((int)$edit):null;
    $selectedPlaylist=(
        $musicReady
        && $musicSection==='playlists'
        && ctype_digit($edit)
    )?music_admin_playlist((int)$edit):null;
    $selectedPlaylistTrackIds=array_map(
        static fn(array $track): int =>
            (int)$track['id'],
        $selectedPlaylist['tracks']??[]
    );
    $selectedPlaylistPositions=[];

    foreach($selectedPlaylist['tracks']??[] as $playlistTrack){
        $selectedPlaylistPositions[
            (int)$playlistTrack['id']
        ]=(int)$playlistTrack['position'];
    }
    $activeTrackCount=count(
        array_filter(
            $musicTracks,
            static fn(array $track): bool =>
                $track['status']==='active'
        )
    );
    $totalPlayCount=array_sum(
        array_map(
            static fn(array $track): int =>
                (int)$track['play_count'],
            $musicTracks
        )
    );
?>
<?php if(!$musicReady):?>
<div class="alert alert-warning">
Import <strong>database/music_library_v44.sql</strong> to enable tracks, albums, playlists, cover art, and the public audio player.
</div>
<?php else:?>
<div class="page-actions music-admin-actions">
<nav class="music-admin-tabs" aria-label="Music Library sections">
<?php foreach([
    'tracks'=>'Songs',
    'albums'=>'Albums',
    'playlists'=>'Playlists',
    'import'=>'Audio imports',
    'banner'=>'Banner',
    'demo'=>'Demo Mode',
] as $sectionKey=>$sectionLabel):?>
<a
    class="button <?=$musicSection===$sectionKey?'button-primary':''?>"
    href="?view=music&amp;section=<?=e($sectionKey)?>"
><?=e($sectionLabel)?></a>
<?php endforeach;?>
</nav>
<span class="spacer"></span>
<a class="button" href="<?=e(app_url('music-library.php'))?>" target="_blank" rel="noopener">Open public player</a>
<a class="button" href="?view=knowledge&amp;section=add">Upload MP3</a>
</div>

<div class="stats-grid music-admin-stats">
<article class="stat-card"><span>Music tracks</span><strong><?=count($musicTracks)?></strong><small><?=$activeTrackCount?> public</small></article>
<article class="stat-card"><span>Albums</span><strong><?=count($musicAlbums)?></strong><small><?=count(array_filter($musicAlbums,static fn(array $album):bool=>$album['status']==='active'))?> public releases</small></article>
<article class="stat-card"><span>Playlists</span><strong><?=count($musicPlaylists)?></strong><small><?=count(array_filter($musicPlaylists,static fn(array $playlist):bool=>$playlist['status']==='active'))?> public collections</small></article>
<article class="stat-card"><span>Recorded plays</span><strong><?=$totalPlayCount?></strong><small>First-party player events</small></article>
<article class="stat-card"><span>Public source</span><strong><?=$musicDemoSettings['enabled']?'Demo':'Live'?></strong><small><?=$musicDemoSettings['enabled']?'Playable sample catalog':'Published database catalog'?></small></article>
</div>

<?php if($musicSection==='demo'):?>
<form method="post" class="form-panel music-demo-admin-form">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_music_demo_mode">

<header class="music-editor-header">
<div>
<span>Public catalog source</span>
<h2>Demo Music Mode</h2>
<p>Switch the public Music Library between the live published catalog and a complete playable sample catalog. Your uploaded songs, albums, playlists, artwork, play totals, and publishing settings are not modified or deleted.</p>
</div>
<span class="music-demo-admin-badge <?=$musicDemoSettings['enabled']?'active':'live'?>">
<?=$musicDemoSettings['enabled']?'Demo active':'Live catalog'?>
</span>
</header>

<div class="music-demo-admin-layout">
<section>
<label class="music-admin-toggle">
<input
    type="checkbox"
    name="enabled"
    value="1"
    <?=$musicDemoSettings['enabled']?'checked':''?>
>
<span>
<strong>Enable Demo Music Mode</strong>
<small>Uses eight sample albums, ten original synthesized MP3 demos, four playlists, New Songs, Top Songs, Recently Played, Trending Now, Featured Songs, and All Songs.</small>
</span>
</label>

<label class="music-admin-toggle">
<input
    type="checkbox"
    name="banner_enabled"
    value="1"
    <?=(
        $musicDemoSettings['enabled']
        && $musicDemoSettings['banner_enabled']
    )?'checked':''?>
>
<span>
<strong>Display the demo featured banner</strong>
<small>The demo mountain banner appears only while Demo Music Mode is active. Your custom Banner tab remains independent and takes priority when enabled.</small>
</span>
</label>
</section>

<aside>
<h3>Included demo behavior</h3>
<ul>
<li>Byte-range MP3 playback and seeking</li>
<li>Play, pause, previous, next, shuffle, repeat, volume, and queue</li>
<li>Recently Played stored in the visitor browser</li>
<li>music_library_view, music_album_view, music_playlist_view, and music_track_play analytics</li>
<li>Visitor/session attribution and CRM relationship timeline activity</li>
<li>No changes to the live song database</li>
</ul>
</aside>
</div>

<div class="form-footer">
<button class="button button-primary" type="submit">Save Demo Mode</button>
<a
    class="button"
    href="<?=e(app_url('music-library.php?v=49'))?>"
    target="_blank"
    rel="noopener"
>Open public Music Library</a>
</div>
</form>

<?php elseif($musicSection==='banner'):?>
<form
    method="post"
    enctype="multipart/form-data"
    class="form-panel music-banner-admin-form"
>
<?=csrf_field()?>
<input type="hidden" name="action" value="save_music_banner">

<header class="music-editor-header">
<div>
<span>Optional public banner</span>
<h2>Music page banner</h2>
<p>The banner is rendered only when an image has been uploaded and Enable banner is checked. When no image is configured, the public page starts directly with the Albums row.</p>
</div>
<?php if($musicBannerHasImage):?>
<img
    class="music-banner-admin-preview"
    src="<?=e(app_url('music-banner.php?preview=1'))?>"
    alt=""
>
<?php endif;?>
</header>

<div class="form-grid">
<label class="field full">
<span>Banner image</span>
<input
    type="file"
    name="banner_image"
    accept="image/jpeg,image/png,image/webp"
>
<small>JPG, PNG, or WebP. Minimum 900 × 240 pixels. Maximum <?=e(format_bytes(music_banner_limit_bytes()))?>.</small>
</label>

<label class="field">
<span>Eyebrow</span>
<input
    name="eyebrow"
    maxlength="120"
    value="<?=e($musicBannerSettings['eyebrow'])?>"
    placeholder="Featured release"
>
</label>

<label class="field">
<span>Banner title</span>
<input
    name="title"
    maxlength="190"
    value="<?=e($musicBannerSettings['title'])?>"
>
</label>

<label class="field full">
<span>Subtitle</span>
<textarea
    name="subtitle"
    rows="3"
    maxlength="700"
><?=e($musicBannerSettings['subtitle'])?></textarea>
</label>

<label class="field">
<span>Image alt text</span>
<input
    name="alt_text"
    maxlength="190"
    value="<?=e($musicBannerSettings['alt_text'])?>"
>
</label>

<label class="field">
<span>Optional link</span>
<input
    name="link_url"
    maxlength="1000"
    value="<?=e($musicBannerSettings['link_url'])?>"
    placeholder="https://… or /portfolio-page"
>
</label>

<label class="checkbox-row full">
<input
    type="checkbox"
    name="enabled"
    value="1"
    <?=(
        $musicBannerSettings['enabled']
        && $musicBannerHasImage
    )?'checked':''?>
>
<span>Enable the public banner.</span>
</label>

<?php if($musicBannerHasImage):?>
<label class="checkbox-row full">
<input
    type="checkbox"
    name="remove_banner"
    value="1"
>
<span>Remove the banner image and hide the banner.</span>
</label>
<?php endif;?>
</div>

<div class="music-banner-admin-status">
<?php if($musicBannerHasImage&&$musicBannerSettings['enabled']):?>
<span class="status status-active">Public</span>
<p>The configured banner is displayed above the music header.</p>
<?php elseif($musicBannerHasImage):?>
<span class="status status-draft">Hidden</span>
<p>An image is stored, but the public banner is disabled.</p>
<?php else:?>
<span class="status status-draft">Not configured</span>
<p>No banner markup is rendered on the public music page.</p>
<?php endif;?>
</div>

<div class="form-footer">
<button class="button button-primary" type="submit">Save banner</button>
<a
    class="button"
    href="<?=e(app_url('music-library.php'))?>"
    target="_blank"
    rel="noopener"
>Open public player</a>
</div>
</form>

<?php elseif($musicSection==='tracks'):?>
<?php if($edit==='new'||$selectedTrack):?>
<form method="post" class="form-panel music-editor-form">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_music_track">
<input type="hidden" name="track_id" value="<?=(int)($selectedTrack['id']??0)?>">

<header class="music-editor-header">
<div>
<span>Music track</span>
<h2><?=e($selectedTrack['title']??'Add song')?></h2>
<p>Connect an uploaded audio asset, add traditional song metadata, assign an album, and publish it to the public streaming player.</p>
</div>
<?php if($selectedTrack):?>
<a class="button" href="<?=e(music_track_stream_url((int)$selectedTrack['id']))?>" target="_blank" rel="noopener">Test audio</a>
<?php endif;?>
</header>

<div class="form-grid">
<label class="field full">
<span>Uploaded audio asset</span>
<select name="knowledge_asset_id" required>
<option value="">Select audio</option>
<?php foreach(music_audio_assets(false) as $audioAsset):?>
<option
    value="<?=(int)$audioAsset['id']?>"
    <?=(
        (int)($selectedTrack['knowledge_asset_id']??0)
        ===(int)$audioAsset['id']
    )?'selected':''?>
><?=e($audioAsset['title'])?> — <?=e($audioAsset['original_name'])?></option>
<?php endforeach;?>
</select>
<small>Upload MP3/audio through Knowledge Center, then select it here. The protected source file is reused.</small>
</label>

<label class="field">
<span>Song title</span>
<input name="title" maxlength="190" value="<?=e($selectedTrack['title']??'')?>" required>
</label>

<label class="field">
<span>Slug</span>
<input name="slug" maxlength="190" value="<?=e($selectedTrack['slug']??'')?>" placeholder="generated-from-title">
</label>

<label class="field">
<span>Artist</span>
<input name="artist_name" maxlength="190" value="<?=e($selectedTrack['artist_name']??'David Evans')?>">
</label>

<label class="field">
<span>Featured artist</span>
<input name="featured_artist" maxlength="190" value="<?=e($selectedTrack['featured_artist']??'')?>">
</label>

<label class="field">
<span>Album</span>
<select name="album_id">
<option value="">Single / no album</option>
<?php foreach($musicAlbums as $album):?>
<option
    value="<?=(int)$album['id']?>"
    <?=(
        (int)($selectedTrack['album_id']??0)
        ===(int)$album['id']
    )?'selected':''?>
><?=e($album['title'])?></option>
<?php endforeach;?>
</select>
</label>

<label class="field">
<span>Status</span>
<select name="status">
<?php foreach(['draft','active','archived'] as $musicStatus):?>
<option value="<?=e($musicStatus)?>" <?=($selectedTrack['status']??'draft')===$musicStatus?'selected':''?>><?=e(status_label($musicStatus))?></option>
<?php endforeach;?>
</select>
</label>

<label class="field">
<span>Disc number</span>
<input type="number" name="disc_number" min="1" value="<?=(int)($selectedTrack['disc_number']??1)?>">
</label>

<label class="field">
<span>Track number</span>
<input type="number" name="track_number" min="1" value="<?=e((string)($selectedTrack['track_number']??''))?>">
</label>

<label class="field">
<span>Genre</span>
<input name="genre" maxlength="120" value="<?=e($selectedTrack['genre']??'')?>">
</label>

<label class="field">
<span>Release year</span>
<input type="number" name="release_year" min="1900" max="2100" value="<?=e((string)($selectedTrack['release_year']??''))?>">
</label>

<label class="field">
<span>Duration in seconds</span>
<input type="number" name="duration_seconds" min="1" value="<?=e((string)($selectedTrack['duration_seconds']??''))?>">
</label>

<label class="field">
<span>Display order</span>
<input type="number" name="sort_order" min="0" value="<?=(int)($selectedTrack['sort_order']??100)?>">
</label>

<label class="field full">
<span>Description</span>
<textarea name="description" rows="4"><?=e($selectedTrack['description']??'')?></textarea>
</label>

<label class="field full">
<span>Lyrics or liner notes</span>
<textarea name="lyrics" rows="8"><?=e($selectedTrack['lyrics']??'')?></textarea>
</label>

<label class="checkbox-row">
<input type="checkbox" name="featured" value="1" <?=(int)($selectedTrack['featured']??0)===1?'checked':''?>>
<span>Feature this song.</span>
</label>

<label class="checkbox-row">
<input type="checkbox" name="is_explicit" value="1" <?=(int)($selectedTrack['is_explicit']??0)===1?'checked':''?>>
<span>Mark explicit.</span>
</label>

<label class="checkbox-row">
<input type="checkbox" name="is_downloadable" value="1" <?=(int)($selectedTrack['is_downloadable']??0)===1?'checked':''?>>
<span>Allow public MP3 download.</span>
</label>
</div>

<div class="form-footer">
<button class="button button-primary" type="submit">Save song</button>
<a class="button" href="?view=music&amp;section=tracks">Cancel</a>
</div>
</form>

<?php if($selectedTrack&&$selectedTrack['status']!=='archived'):?>
<form method="post" class="music-archive-form" onsubmit="return confirm('Archive this song?')">
<?=csrf_field()?>
<input type="hidden" name="action" value="archive_music_track">
<input type="hidden" name="track_id" value="<?=(int)$selectedTrack['id']?>">
<button class="button button-danger" type="submit">Archive song</button>
</form>
<?php endif;?>

<?php else:?>
<div class="page-actions">
<a class="button button-primary" href="?view=music&amp;section=tracks&amp;edit=new">Add song</a>
<a class="button" href="?view=music&amp;section=import">Import uploaded audio</a>
</div>

<section class="panel">
<header class="panel-header"><h2><?=count($musicTracks)?> songs</h2></header>
<div class="table-wrap">
<table class="data-table music-admin-table">
<thead><tr><th>Song</th><th>Album</th><th>Release</th><th>Status</th><th>Plays</th><th>Audio</th></tr></thead>
<tbody>
<?php foreach($musicTracks as $track):?>
<tr>
<td>
<a href="?view=music&amp;section=tracks&amp;edit=<?=(int)$track['id']?>"><?=e($track['title'])?></a>
<br><small><?=e($track['artist_name'])?><?php if($track['featured_artist']):?> feat. <?=e($track['featured_artist'])?><?php endif;?></small>
</td>
<td><?=e($track['album_title']?:'Single')?><br><small><?=e($track['genre']?:'—')?></small></td>
<td><?=e((string)($track['release_year']?:'—'))?><br><small>Track <?=e((string)($track['track_number']?:'—'))?></small></td>
<td><span class="status status-<?=e($track['status'])?>"><?=e(status_label($track['status']))?></span><?php if((int)$track['featured']===1):?><br><small>Featured</small><?php endif;?></td>
<td><strong><?=(int)$track['play_count']?></strong><br><small><?=e(music_duration_label($track['duration_seconds']!==null?(int)$track['duration_seconds']:null))?></small></td>
<td><audio controls preload="none" src="<?=e(music_track_stream_url((int)$track['id']))?>"></audio></td>
</tr>
<?php endforeach;?>
<?php if(!$musicTracks):?>
<tr><td colspan="6"><div class="empty-state">No songs have been added. Import an uploaded MP3 or audio file.</div></td></tr>
<?php endif;?>
</tbody>
</table>
</div>
</section>
<?php endif;?>

<?php elseif($musicSection==='albums'):?>
<?php if($edit==='new'||$selectedAlbum):?>
<form method="post" enctype="multipart/form-data" class="form-panel music-editor-form">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_music_album">
<input type="hidden" name="album_id" value="<?=(int)($selectedAlbum['id']??0)?>">

<header class="music-editor-header">
<div><span>Album / release</span><h2><?=e($selectedAlbum['title']??'Create album')?></h2><p>Group songs into an album, EP, single, or compilation and provide release cover art.</p></div>
<?php if($selectedAlbum):?>
<div class="music-editor-preview-actions">
<img class="music-editor-cover-preview" src="<?=e(music_cover_url('album',(int)$selectedAlbum['id']))?>" alt="">
<?php if($selectedAlbum['status']==='active'):?>
<a
    class="button button-small"
    href="<?=e(music_collection_public_url('album',(string)$selectedAlbum['slug']))?>"
    target="_blank"
    rel="noopener"
>Open public album</a>
<?php endif;?>
</div>
<?php endif;?>
</header>

<div class="form-grid">
<label class="field"><span>Title</span><input name="title" maxlength="190" value="<?=e($selectedAlbum['title']??'')?>" required></label>
<label class="field"><span>Slug</span><input name="slug" maxlength="190" value="<?=e($selectedAlbum['slug']??'')?>"></label>
<label class="field"><span>Artist</span><input name="artist_name" maxlength="190" value="<?=e($selectedAlbum['artist_name']??'David Evans')?>"></label>
<label class="field"><span>Release type</span><select name="album_type"><?php foreach(['album','ep','single','compilation'] as $albumType):?><option value="<?=e($albumType)?>" <?=($selectedAlbum['album_type']??'album')===$albumType?'selected':''?>><?=e(status_label($albumType))?></option><?php endforeach;?></select></label>
<label class="field"><span>Status</span><select name="status"><?php foreach(['draft','active','archived'] as $albumStatus):?><option value="<?=e($albumStatus)?>" <?=($selectedAlbum['status']??'draft')===$albumStatus?'selected':''?>><?=e(status_label($albumStatus))?></option><?php endforeach;?></select></label>
<label class="field"><span>Display order</span><input type="number" name="sort_order" min="0" value="<?=(int)($selectedAlbum['sort_order']??100)?>"></label>
<label class="field"><span>Release date</span><input type="date" name="release_date" value="<?=e($selectedAlbum['release_date']??'')?>"></label>
<label class="field"><span>Release year</span><input type="number" name="release_year" min="1900" max="2100" value="<?=e((string)($selectedAlbum['release_year']??''))?>"></label>
<label class="field"><span>Genre</span><input name="genre" maxlength="120" value="<?=e($selectedAlbum['genre']??'')?>"></label>
<label class="field"><span>Cover art</span><input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp"><small>When omitted, the first song cover is used.</small></label>
<label class="field full"><span>Description</span><textarea name="description" rows="5"><?=e($selectedAlbum['description']??'')?></textarea></label>
<label class="checkbox-row"><input type="checkbox" name="featured" value="1" <?=(int)($selectedAlbum['featured']??0)===1?'checked':''?>><span>Feature this release.</span></label>
<?php if(!empty($selectedAlbum['cover_stored_name'])):?><label class="checkbox-row"><input type="checkbox" name="remove_cover" value="1"><span>Remove uploaded cover and use song artwork.</span></label><?php endif;?>
</div>
<div class="form-footer"><button class="button button-primary" type="submit">Save album</button><a class="button" href="?view=music&amp;section=albums">Cancel</a></div>
</form>
<?php else:?>
<div class="page-actions"><a class="button button-primary" href="?view=music&amp;section=albums&amp;edit=new">Create album</a></div>
<div class="music-admin-card-grid">
<?php foreach($musicAlbums as $album):?>
<article class="music-admin-card">
<img src="<?=e(music_cover_url('album',(int)$album['id']))?>" alt="<?=e($album['title'])?> cover">
<div><span><?=e(status_label($album['album_type']))?> · <?=e(status_label($album['status']))?></span><h2><?=e($album['title'])?></h2><p><?=e($album['artist_name'])?> · <?=(int)$album['track_count']?> songs · <?=e(music_duration_label((int)$album['total_seconds']))?></p></div>
<footer>
<a class="button button-small button-primary" href="?view=music&amp;section=albums&amp;edit=<?=(int)$album['id']?>">Manage</a>
<?php if($album['status']==='active'):?>
<a
    class="button button-small"
    href="<?=e(music_collection_public_url('album',(string)$album['slug']))?>"
    target="_blank"
    rel="noopener"
>Open public</a>
<?php endif;?>
</footer>
</article>
<?php endforeach;?>
<?php if(!$musicAlbums):?><div class="empty-state">No albums have been created.</div><?php endif;?>
</div>
<?php endif;?>

<?php elseif($musicSection==='playlists'):?>
<?php if($edit==='new'||$selectedPlaylist):?>
<form method="post" enctype="multipart/form-data" class="form-panel music-editor-form">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_music_playlist">
<input type="hidden" name="playlist_id" value="<?=(int)($selectedPlaylist['id']??0)?>">

<header class="music-editor-header">
<div><span>Playlist</span><h2><?=e($selectedPlaylist['title']??'Create playlist')?></h2><p>Select songs in playback order and add optional playlist cover art.</p></div>
<?php if($selectedPlaylist):?>
<div class="music-editor-preview-actions">
<img class="music-editor-cover-preview" src="<?=e(music_cover_url('playlist',(int)$selectedPlaylist['id']))?>" alt="">
<?php if($selectedPlaylist['status']==='active'):?>
<a
    class="button button-small"
    href="<?=e(music_collection_public_url('playlist',(string)$selectedPlaylist['slug']))?>"
    target="_blank"
    rel="noopener"
>Open public playlist</a>
<?php endif;?>
</div>
<?php endif;?>
</header>

<div class="form-grid">
<label class="field"><span>Title</span><input name="title" maxlength="190" value="<?=e($selectedPlaylist['title']??'')?>" required></label>
<label class="field"><span>Slug</span><input name="slug" maxlength="190" value="<?=e($selectedPlaylist['slug']??'')?>"></label>
<label class="field"><span>Status</span><select name="status"><?php foreach(['draft','active','archived'] as $playlistStatus):?><option value="<?=e($playlistStatus)?>" <?=($selectedPlaylist['status']??'draft')===$playlistStatus?'selected':''?>><?=e(status_label($playlistStatus))?></option><?php endforeach;?></select></label>
<label class="field"><span>Display order</span><input type="number" name="sort_order" min="0" value="<?=(int)($selectedPlaylist['sort_order']??100)?>"></label>
<label class="field full"><span>Description</span><textarea name="description" rows="4"><?=e($selectedPlaylist['description']??'')?></textarea></label>
<label class="field"><span>Cover art</span><input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp"><small>When omitted, the first selected song cover is used.</small></label>
<label class="checkbox-row"><input type="checkbox" name="featured" value="1" <?=(int)($selectedPlaylist['featured']??0)===1?'checked':''?>><span>Feature this playlist.</span></label>
<?php if(!empty($selectedPlaylist['cover_stored_name'])):?><label class="checkbox-row"><input type="checkbox" name="remove_cover" value="1"><span>Remove uploaded cover and use song artwork.</span></label><?php endif;?>
</div>

<section class="music-playlist-selector">
<header><span>Playlist order</span><h3>Select songs</h3><p>Checked songs are saved in the order shown. Drag ordering is not required; use the song display order and album track order.</p></header>
<div class="music-playlist-track-grid">
<?php foreach($musicTracks as $track):?>
<div class="music-playlist-track-option">
<label>
<input
    type="checkbox"
    name="track_ids[]"
    value="<?=(int)$track['id']?>"
    <?=in_array((int)$track['id'],$selectedPlaylistTrackIds,true)?'checked':''?>
>
<img src="<?=e(music_cover_url('track',(int)$track['id']))?>" alt="">
<span><strong><?=e($track['title'])?></strong><small><?=e($track['artist_name'])?> · <?=e($track['album_title']?:'Single')?></small></span>
</label>
<input
    class="music-playlist-position"
    type="number"
    name="track_positions[<?=(int)$track['id']?>]"
    min="1"
    value="<?=(int)($selectedPlaylistPositions[(int)$track['id']]??($track['sort_order']?:100))?>"
    aria-label="Playlist position for <?=e($track['title'])?>"
>
</div>
<?php endforeach;?>
</div>
</section>

<div class="form-footer"><button class="button button-primary" type="submit">Save playlist</button><a class="button" href="?view=music&amp;section=playlists">Cancel</a></div>
</form>
<?php else:?>
<div class="page-actions"><a class="button button-primary" href="?view=music&amp;section=playlists&amp;edit=new">Create playlist</a></div>
<div class="music-admin-card-grid">
<?php foreach($musicPlaylists as $playlist):?>
<article class="music-admin-card">
<img src="<?=e(music_cover_url('playlist',(int)$playlist['id']))?>" alt="<?=e($playlist['title'])?> cover">
<div><span>Playlist · <?=e(status_label($playlist['status']))?></span><h2><?=e($playlist['title'])?></h2><p><?=(int)$playlist['track_count']?> songs · <?=e(music_duration_label((int)$playlist['total_seconds']))?></p></div>
<footer>
<a class="button button-small button-primary" href="?view=music&amp;section=playlists&amp;edit=<?=(int)$playlist['id']?>">Manage</a>
<?php if($playlist['status']==='active'):?>
<a
    class="button button-small"
    href="<?=e(music_collection_public_url('playlist',(string)$playlist['slug']))?>"
    target="_blank"
    rel="noopener"
>Open public</a>
<?php endif;?>
</footer>
</article>
<?php endforeach;?>
<?php if(!$musicPlaylists):?><div class="empty-state">No playlists have been created.</div><?php endif;?>
</div>
<?php endif;?>

<?php else:?>
<div class="music-import-layout">
<section class="panel">
<header class="panel-header">
<div><span>Existing protected uploads</span><h2><?=count($unlinkedAudio)?> audio files ready to import</h2></div>
<?php if($unlinkedAudio):?>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="adopt_all_music_assets">
<button class="button button-primary" type="submit">Import all audio</button>
</form>
<?php endif;?>
</header>
<div class="table-wrap">
<table class="data-table">
<thead><tr><th>Audio asset</th><th>Format</th><th>Knowledge status</th><th>Cover</th><th></th></tr></thead>
<tbody>
<?php foreach($unlinkedAudio as $audioAsset):?>
<tr>
<td><strong><?=e($audioAsset['title'])?></strong><br><small><?=e($audioAsset['original_name'])?> · <?=e(format_bytes((int)$audioAsset['size_bytes']))?></small></td>
<td><?=e(strtoupper($audioAsset['extension']))?><br><small><?=e($audioAsset['mime_type'])?></small></td>
<td><span class="status status-<?=e($audioAsset['status'])?>"><?=e(status_label($audioAsset['status']))?></span></td>
<td><?=!empty($audioAsset['cover_stored_name'])?'Attached':'Uses fallback'?></td>
<td>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="adopt_music_asset">
<input type="hidden" name="asset_id" value="<?=(int)$audioAsset['id']?>">
<button class="button button-small" type="submit">Add to Music Library</button>
</form>
</td>
</tr>
<?php endforeach;?>
<?php if(!$unlinkedAudio):?><tr><td colspan="5"><div class="empty-state">Every uploaded audio asset is already linked to a music track.</div></td></tr><?php endif;?>
</tbody>
</table>
</div>
</section>

<aside class="panel music-import-guide">
<header class="panel-header"><h2>Upload workflow</h2></header>
<div class="panel-body">
<ol>
<li>Upload MP3, WAV, M4A, AAC, OGG, or FLAC through Knowledge Center.</li>
<li>Add square cover art during the upload.</li>
<li>Return here and adopt the audio as a song.</li>
<li>Add artist, album, track number, genre, duration, and release details.</li>
<li>Set the song Active to make it playable in chat and the public Music Library.</li>
</ol>
<a class="button button-primary" href="?view=knowledge&amp;section=add">Upload audio</a>
</div>
</aside>
</div>
<?php endif;?>
<?php endif;?>
<?php
}

if($view==='analytics'){
    $analyticsReady=visitor_intelligence_schema_available();
    $analyticsDays=(int)($_GET['days']??30);

    if(!in_array($analyticsDays,[7,30,90,365],true)){
        $analyticsDays=30;
    }

    $analyticsSummary=$analyticsReady
        ?visitor_intelligence_summary($analyticsDays)
        :[];
    $portfolioMetrics=$analyticsReady
        ?visitor_intelligence_portfolio_metrics($analyticsDays)
        :[];
    $visitorTrend=$analyticsReady
        ?visitor_intelligence_daily_trend(
            min($analyticsDays,30)
        )
        :[];
    $recentVisitors=$analyticsReady
        ?visitor_intelligence_recent_visitors(30)
        :[];
    $topReferrers=$analyticsReady
        ?visitor_intelligence_top_referrers(
            $analyticsDays,
            10
        )
        :[];
    $selectedVisitorId=query_int('visitor');
    $selectedVisitor=$analyticsReady
        ?visitor_intelligence_visitor_profile(
            $selectedVisitorId
        )
        :null;
    $selectedVisitorEvents=$selectedVisitor
        ?visitor_intelligence_visitor_events(
            $selectedVisitorId,
            120
        )
        :[];
    $maxTrend=max(
        1,
        ...array_map(
            static fn(array $row): int => max(
                (int)$row['visitors'],
                (int)$row['portfolio_views'],
                (int)$row['conversions']
            ),
            $visitorTrend
        )
    );
    $uniqueVisitors=(int)($analyticsSummary['unique_visitors']??0);
    $conversions=(int)($analyticsSummary['conversions']??0);
    $conversionRate=$uniqueVisitors>0
        ?round(($conversions/$uniqueVisitors)*100,1)
        :0.0;
    $pendingHomeServerEvents=$analyticsReady
        ?(int)db()->query(
            'SELECT COUNT(*)
             FROM visitor_events
             WHERE homeserver_exported_at IS NULL'
        )->fetchColumn()
        :0;
?>
<?php if(!$analyticsReady):?>
<div class="alert alert-warning">
Import <strong>database/visitor_intelligence_v43.sql</strong> to enable first-party visitor activity, portfolio analytics, and CRM attribution.
</div>
<?php else:?>
<div class="page-actions visitor-analytics-actions">
<div class="visitor-range-switch" aria-label="Analytics date range">
<?php foreach([7,30,90,365] as $rangeDays):?>
<a
    class="button <?=$analyticsDays===$rangeDays?'button-primary':''?>"
    href="?view=analytics&amp;days=<?=$rangeDays?>"
><?=e($rangeDays===365?'1 year':$rangeDays.' days')?></a>
<?php endforeach;?>
</div>
<span class="spacer"></span>
<a class="button" href="?view=crm">Open CRM</a>
<a class="button" href="?view=portfolio">Open Portfolio</a>
</div>

<div class="stats-grid visitor-analytics-stats">
<article class="stat-card">
<span>Unique visitors</span>
<strong><?=$uniqueVisitors?></strong>
<small><?=(int)($analyticsSummary['known_visitors']??0)?> identified contacts</small>
</article>
<article class="stat-card">
<span>Sessions</span>
<strong><?=(int)($analyticsSummary['sessions']??0)?></strong>
<small><?=(int)($analyticsSummary['return_visits']??0)?> known-contact returns · <?=(int)($analyticsSummary['events']??0)?> actions</small>
</article>
<article class="stat-card">
<span>Portfolio views</span>
<strong><?=(int)($analyticsSummary['portfolio_views']??0)?></strong>
<small><?=(int)($analyticsSummary['project_clicks']??0)?> project-site clicks</small>
</article>
<article class="stat-card">
<span>Chat prompts</span>
<strong><?=(int)($analyticsSummary['chat_prompts']??0)?></strong>
<small>Resume and portfolio questions</small>
</article>
<article class="stat-card">
<span>Music plays</span>
<strong><?=(int)($analyticsSummary['music_plays']??0)?></strong>
<small><?=(int)($analyticsSummary['music_tracks_played']??0)?> unique songs played</small>
</article>
<article class="stat-card">
<span>Resume activity</span>
<strong><?=(int)($analyticsSummary['resume_views']??0)?></strong>
<small><?=(int)($analyticsSummary['resume_downloads']??0)?> downloads</small>
</article>
<article class="stat-card">
<span>Voice contacts</span>
<strong><?=(int)($analyticsSummary['voice_contacts']??0)?></strong>
<small>Calls, callbacks, and voicemail</small>
</article>
<article class="stat-card">
<span>Contact forms</span>
<strong><?=(int)($analyticsSummary['contact_forms']??0)?></strong>
<small><?=(int)($analyticsSummary['opportunities']??0)?> attributed opportunities</small>
</article>
<article class="stat-card">
<span>Conversion rate</span>
<strong><?=e(number_format($conversionRate,1))?>%</strong>
<small><?=$conversions?> identified contact actions</small>
</article>
</div>

<section class="panel visitor-trend-panel">
<header class="panel-header">
<div>
<span>First-party activity</span>
<h2><?=count($visitorTrend)?>-day engagement trend</h2>
</div>
<div class="visitor-trend-legend">
<span><i class="visitor-legend-visitors"></i>Visitors</span>
<span><i class="visitor-legend-portfolio"></i>Portfolio views</span>
<span><i class="visitor-legend-conversions"></i>Conversions</span>
</div>
</header>
<div class="visitor-trend-chart" role="img" aria-label="Daily visitor, portfolio view, and conversion activity">
<?php foreach($visitorTrend as $trendDay):?>
<div class="visitor-trend-day" title="<?=e($trendDay['date'])?>">
<div class="visitor-trend-bars">
<i
    class="visitor-trend-visitors"
    style="height:<?=max(2,round(((int)$trendDay['visitors']/$maxTrend)*100,2))?>%"
></i>
<i
    class="visitor-trend-portfolio"
    style="height:<?=max(2,round(((int)$trendDay['portfolio_views']/$maxTrend)*100,2))?>%"
></i>
<i
    class="visitor-trend-conversions"
    style="height:<?=max(2,round(((int)$trendDay['conversions']/$maxTrend)*100,2))?>%"
></i>
</div>
<span><?=e(date('M j',strtotime($trendDay['date'])))?></span>
<small>
<?= (int)$trendDay['visitors'] ?> /
<?= (int)$trendDay['portfolio_views'] ?> /
<?= (int)$trendDay['conversions'] ?>
</small>
</div>
<?php endforeach;?>
</div>
</section>

<section class="panel visitor-portfolio-performance">
<header class="panel-header">
<div>
<span>Portfolio attribution</span>
<h2>Project performance</h2>
</div>
<a href="?view=portfolio">Manage portfolio</a>
</header>
<div class="table-wrap">
<table class="data-table">
<thead>
<tr>
<th>Project</th>
<th>Views</th>
<th>Visitors</th>
<th>Engagement</th>
<th>Intent</th>
<th>Conversions</th>
<th>Last activity</th>
</tr>
</thead>
<tbody>
<?php foreach($portfolioMetrics as $metric):?>
<tr>
<td>
<a href="?view=portfolio&amp;edit=<?=(int)$metric['id']?>"><?=e($metric['title'])?></a>
<br>
<small>
<?=e(status_label($metric['status']))?>
<?php if((int)$metric['featured']===1):?> · Featured<?php endif;?>
</small>
</td>
<td><strong><?=(int)$metric['views']?></strong></td>
<td><?=(int)$metric['unique_visitors']?></td>
<td>
<?=(int)$metric['gallery_actions']?> gallery
<br><small><?=e(call_center_seconds_label((int)$metric['active_seconds']))?> active</small>
</td>
<td>
<?=(int)$metric['project_clicks']?> project clicks
<br><small><?=(int)$metric['inquiry_intents']?> inquiry intents · <?=(int)$metric['chat_prompts']?> chats</small>
</td>
<td><strong><?=(int)$metric['conversions']?></strong></td>
<td><?=e(format_datetime($metric['last_activity_at']))?></td>
</tr>
<?php endforeach;?>
<?php if(!$portfolioMetrics):?>
<tr><td colspan="7"><div class="empty-state">Portfolio performance will appear after visitor activity is recorded.</div></td></tr>
<?php endif;?>
</tbody>
</table>
</div>
</section>

<div class="dashboard-grid visitor-intelligence-grid">
<section class="panel visitor-session-panel">
<header class="panel-header">
<div>
<span>Known and anonymous traffic</span>
<h2>Recent visitors</h2>
</div>
</header>
<div class="table-wrap">
<table class="data-table">
<thead>
<tr>
<th>Visitor</th>
<th>Latest session</th>
<th>Last project</th>
<th>Activity</th>
<th>Device</th>
</tr>
</thead>
<tbody>
<?php foreach($recentVisitors as $visitorRecord):?>
<tr class="<?=$selectedVisitorId===(int)$visitorRecord['id']?'is-selected':''?>">
<td>
<a href="?view=analytics&amp;days=<?=$analyticsDays?>&amp;visitor=<?=(int)$visitorRecord['id']?>">
<?=e(
    $visitorRecord['contact_name']
        ?: 'Anonymous visitor #'.(int)$visitorRecord['id']
)?>
</a>
<?php if($visitorRecord['contact_email']):?>
<br><small><?=e($visitorRecord['contact_email'])?></small>
<?php else:?>
<br><small>First-party anonymous profile</small>
<?php endif;?>
</td>
<td>
<?=e(format_datetime($visitorRecord['latest_session_activity_at']))?>
<br><small><?=e($visitorRecord['landing_path']?:'—')?></small>
</td>
<td>
<?php if($visitorRecord['last_project_title']):?>
<?=e($visitorRecord['last_project_title'])?>
<?php else:?>—<?php endif;?>
</td>
<td>
<?=(int)$visitorRecord['page_view_count']?> pages
<br><small><?=(int)$visitorRecord['event_count']?> actions · <?=e(call_center_seconds_label((int)$visitorRecord['active_seconds']))?></small>
</td>
<td>
<?=e(status_label($visitorRecord['device_type']?:'unknown'))?>
<br><small><?=e($visitorRecord['platform']?:'Unknown')?><?php if($visitorRecord['referrer_host']):?> · <?=e($visitorRecord['referrer_host'])?><?php endif;?></small>
</td>
</tr>
<?php endforeach;?>
<?php if(!$recentVisitors):?>
<tr><td colspan="5"><div class="empty-state">No visitor sessions have been recorded.</div></td></tr>
<?php endif;?>
</tbody>
</table>
</div>
</section>

<section class="stack visitor-intelligence-side">
<section class="panel">
<header class="panel-header">
<div>
<span>Acquisition</span>
<h2>Top referrers</h2>
</div>
</header>
<div class="visitor-referrer-list">
<?php foreach($topReferrers as $referrer):?>
<article>
<div>
<strong><?=e($referrer['referrer'])?></strong>
<small><?=(int)$referrer['visitors']?> visitors</small>
</div>
<span><?=(int)$referrer['sessions']?> sessions</span>
</article>
<?php endforeach;?>
<?php if(!$topReferrers):?>
<div class="empty-state">Referrer data will appear after sessions are recorded.</div>
<?php endif;?>
</div>
</section>

<section class="panel visitor-homeserver-card">
<header class="panel-header">
<div>
<span>Integration readiness</span>
<h2>Microgifter HomeServer</h2>
</div>
<span class="status status-planning">Prepared</span>
</header>
<div class="panel-body">
<p>Visitor events already include stable UUIDs, attribution, timestamps, and export-state fields for the upcoming private HomeServer connection.</p>
<div class="visitor-homeserver-count">
<strong><?=$pendingHomeServerEvents?></strong>
<span>events waiting for a future secure export worker</span>
</div>
<p class="visitor-homeserver-note">No remote connection or data export is enabled in v43.</p>
</div>
</section>
</section>
</div>

<?php if($selectedVisitor):?>
<section class="panel visitor-detail-panel">
<header class="panel-header">
<div>
<span>Visitor timeline</span>
<h2><?=e($selectedVisitor['contact_name']?:'Anonymous visitor #'.$selectedVisitorId)?></h2>
</div>
<div class="visitor-detail-actions">
<?php if((int)($selectedVisitor['identified_contact_id']??0)>0):?>
<a class="button button-small" href="?view=crm&amp;id=<?=(int)$selectedVisitor['identified_contact_id']?>">Open CRM contact</a>
<?php endif;?>
<a class="button button-small" href="?view=analytics&amp;days=<?=$analyticsDays?>">Close timeline</a>
</div>
</header>

<div class="visitor-detail-summary">
<article><span>First seen</span><strong><?=e(format_datetime($selectedVisitor['first_seen_at']))?></strong></article>
<article><span>Last seen</span><strong><?=e(format_datetime($selectedVisitor['last_seen_at']))?></strong></article>
<article><span>Sessions</span><strong><?=(int)$selectedVisitor['session_total']?></strong></article>
<article><span>Events</span><strong><?=(int)$selectedVisitor['event_total']?></strong></article>
<article><span>Active time</span><strong><?=e(call_center_seconds_label((int)$selectedVisitor['total_active_seconds']))?></strong></article>
<article><span>First source</span><strong><?=e($selectedVisitor['first_referrer_host']?:'Direct / internal')?></strong></article>
</div>

<div class="visitor-event-timeline">
<?php foreach($selectedVisitorEvents as $visitorEvent):?>
<?php $eventMetadata=visitor_intelligence_metadata_decode($visitorEvent['metadata_json']);?>
<article class="visitor-event-item">
<div class="visitor-event-marker"></div>
<div>
<header>
<strong><?=e(visitor_intelligence_event_label($visitorEvent['event_type']))?></strong>
<time><?=e(format_datetime($visitorEvent['occurred_at']))?></time>
</header>
<p>
<?=e($visitorEvent['event_label']?:$visitorEvent['page_path']?:'Recorded activity')?>
<?php if($visitorEvent['project_title']):?>
<span> · <?=e($visitorEvent['project_title'])?></span>
<?php endif;?>
</p>
<?php if(!empty($eventMetadata['prompt'])):?>
<blockquote><?=e($eventMetadata['prompt'])?></blockquote>
<?php endif;?>
<small>
<?=e($visitorEvent['page_path']?:'—')?>
<?php if($visitorEvent['duration_seconds']):?>
 · <?=e(call_center_seconds_label((int)$visitorEvent['duration_seconds']))?>
<?php endif;?>
<?php if($visitorEvent['event_uuid']):?>
 · Event <?=e(substr($visitorEvent['event_uuid'],0,8))?>
<?php endif;?>
</small>
</div>
</article>
<?php endforeach;?>
<?php if(!$selectedVisitorEvents):?>
<div class="empty-state">No timeline events were recorded for this visitor.</div>
<?php endif;?>
</div>
</section>
<?php endif;?>
<?php endif;?>
<?php
}

if($view==='clients'){
    $clients=admin_clients();$edit=(string)($_GET['edit']??'');$selected=null;if(ctype_digit($edit)){foreach($clients as $c)if((int)$c['id']===(int)$edit)$selected=$c;}
?>
<div class="page-actions"><a class="button button-primary" href="?view=clients&edit=new">Create client</a></div>
<div class="dashboard-grid"><section class="panel"><header class="panel-header"><h2>Client accounts</h2></header><div class="table-wrap"><table class="data-table"><thead><tr><th>Client</th><th>Company</th><th>Status</th><th>Last login</th></tr></thead><tbody><?php foreach($clients as $c):?><tr><td><a href="?view=clients&edit=<?=(int)$c['id']?>"><?=e($c['display_name'])?></a><br><small><?=e($c['email'])?></small></td><td><?=e($c['company']?:'—')?></td><td><span class="status status-<?=e($c['status'])?>"><?=e($c['status'])?></span></td><td><?=e(format_datetime($c['last_login_at']))?></td></tr><?php endforeach;?></tbody></table></div></section>
<section><?php if($edit===''):?><div class="panel"><div class="empty-state">Select a client or create a new account.</div></div><?php else:?><form method="post" class="form-panel"><?=csrf_field()?><input type="hidden" name="action" value="save_client"><input type="hidden" name="id" value="<?=(int)($selected['id']??0)?>"><div class="form-grid"><label class="field"><span>Name</span><input name="display_name" value="<?=e($selected['display_name']??'')?>" required></label><label class="field"><span>Email</span><input type="email" name="email" value="<?=e($selected['email']??'')?>" required></label><label class="field"><span>Company</span><input name="company" value="<?=e($selected['company']??'')?>"></label><label class="field"><span>Phone</span><input name="phone" value="<?=e($selected['phone']??'')?>"></label><?php if(!$selected):?><label class="field full"><span>Temporary password</span><input name="temporary_password" minlength="12" placeholder="Leave blank to generate"></label><?php else:?><label class="field"><span>Status</span><select name="status"><option value="active" <?=$selected['status']==='active'?'selected':''?>>Active</option><option value="inactive" <?=$selected['status']==='inactive'?'selected':''?>>Inactive</option></select></label><?php endif;?></div><div class="form-footer"><button class="button button-primary">Save client</button></div></form><?php if($selected):?><form method="post" class="form-panel" style="margin-top:16px"><?=csrf_field()?><input type="hidden" name="action" value="reset_client_password"><input type="hidden" name="id" value="<?=(int)$selected['id']?>"><button class="button button-danger" data-confirm="Reset this client's password?">Reset password</button></form><?php endif;?><?php endif;?></section></div>
<?php
}


if($view==='administrators'){
    $administrators=db()->query('SELECT id,display_name,email,company,phone,status,last_login_at,created_at FROM users WHERE role="admin" ORDER BY status,display_name')->fetchAll();
    $edit=(string)($_GET['edit']??'');
    $selected=null;

    if(ctype_digit($edit)){
        foreach($administrators as $administrator){
            if((int)$administrator['id']===(int)$edit){
                $selected=$administrator;
                break;
            }
        }
    }
?>
<div class="page-actions">
    <a class="button button-primary" href="?view=administrators&edit=new">Create administrator</a>
</div>

<div class="dashboard-grid">
    <section class="panel">
        <header class="panel-header"><h2>Administrator accounts</h2></header>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Administrator</th><th>Company / role</th><th>Status</th><th>Last login</th></tr></thead>
                <tbody>
                <?php foreach($administrators as $administrator): ?>
                    <tr>
                        <td>
                            <a href="?view=administrators&edit=<?=(int)$administrator['id']?>"><?=e($administrator['display_name'])?></a>
                            <br><small><?=e($administrator['email'])?></small>
                        </td>
                        <td><?=e($administrator['company']?:'North Mountain Media')?></td>
                        <td><span class="status status-<?=e($administrator['status'])?>"><?=e($administrator['status'])?></span></td>
                        <td><?=e(format_datetime($administrator['last_login_at']))?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section>
        <?php if($edit===''): ?>
            <div class="panel"><div class="empty-state">Select an administrator or create a new account.</div></div>
        <?php else: ?>
            <form method="post" class="form-panel">
                <?=csrf_field()?>
                <input type="hidden" name="action" value="save_administrator">
                <input type="hidden" name="id" value="<?=(int)($selected['id']??0)?>">

                <div class="form-grid">
                    <label class="field">
                        <span>Name</span>
                        <input name="display_name" value="<?=e($selected['display_name']??'')?>" required>
                    </label>
                    <label class="field">
                        <span>Email</span>
                        <input type="email" name="email" value="<?=e($selected['email']??'')?>" required>
                    </label>
                    <label class="field">
                        <span>Company / role</span>
                        <input name="company" value="<?=e($selected['company']??'North Mountain Media')?>">
                    </label>
                    <label class="field">
                        <span>Phone</span>
                        <input name="phone" value="<?=e($selected['phone']??'')?>">
                    </label>

                    <?php if(!$selected): ?>
                        <label class="field full">
                            <span>Temporary password</span>
                            <input name="temporary_password" minlength="12" placeholder="Leave blank to generate a secure password">
                            <small>The new administrator must change it after the first login.</small>
                        </label>
                    <?php else: ?>
                        <label class="field">
                            <span>Status</span>
                            <select name="status">
                                <option value="active" <?=$selected['status']==='active'?'selected':''?>>Active</option>
                                <option value="inactive" <?=$selected['status']==='inactive'?'selected':''?>>Inactive</option>
                            </select>
                        </label>
                    <?php endif; ?>
                </div>

                <div class="form-footer">
                    <button class="button button-primary">Save administrator</button>
                </div>
            </form>

            <?php if($selected): ?>
                <form method="post" class="form-panel" style="margin-top:16px">
                    <?=csrf_field()?>
                    <input type="hidden" name="action" value="reset_administrator_password">
                    <input type="hidden" name="id" value="<?=(int)$selected['id']?>">
                    <button class="button button-danger" data-confirm="Reset this administrator password?">Reset password</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>
<?php
}


if($view==='crm'){
    $contactId=query_int('id');
    $selectedOpportunityId=query_int('opportunity');
    $search=trim((string)($_GET['q']??''));
    $stageFilter=trim((string)($_GET['stage']??''));
    $params=[];
    $sql='SELECT c.*,
                 (SELECT owner.display_name FROM users owner WHERE owner.id=c.owner_user_id LIMIT 1) AS owner_name,
                 (SELECT client.display_name FROM users client WHERE client.id=c.client_user_id LIMIT 1) AS client_name,
                 (SELECT COUNT(*) FROM crm_opportunities counted WHERE counted.contact_id=c.id) AS opportunity_count,
                 (SELECT COUNT(*) FROM crm_opportunities opened WHERE opened.contact_id=c.id AND opened.stage NOT IN ("won","lost")) AS open_opportunity_count,
                 (SELECT MAX(latest.created_at) FROM crm_opportunities latest WHERE latest.contact_id=c.id) AS latest_opportunity_at,
                 (SELECT newest.title FROM crm_opportunities newest WHERE newest.contact_id=c.id ORDER BY newest.created_at DESC LIMIT 1) AS latest_opportunity,
                 COALESCE((SELECT call_stats.total_calls FROM crm_contact_call_stats call_stats WHERE call_stats.contact_id=c.id),0) AS call_count,
                 (SELECT call_stats.last_call_at FROM crm_contact_call_stats call_stats WHERE call_stats.contact_id=c.id) AS last_call_at,
                 (SELECT COUNT(*) FROM call_center_requests vm WHERE vm.crm_contact_id=c.id AND vm.request_type="voicemail") AS voicemail_count,
                 (SELECT COUNT(*) FROM call_center_requests msg WHERE msg.crm_contact_id=c.id AND msg.source="public" AND msg.request_type IN ("call_request","callback")) AS message_count
          FROM crm_contacts c
          WHERE 1=1';

    if($search!==''){
        $sql.=' AND (c.display_name LIKE :search OR c.email LIKE :search OR c.company LIKE :search OR c.phone LIKE :search)';
        $params['search']='%'.$search.'%';
    }

    if(in_array($stageFilter,['lead','prospect','qualified','client','partner','closed'],true)){
        $sql.=' AND c.lifecycle_stage=:stage';
        $params['stage']=$stageFilter;
    }

    $sql.=' ORDER BY COALESCE(c.next_follow_up_at,c.last_inquiry_at,c.updated_at) DESC';
    $contactsStatement=db()->prepare($sql);
    $contactsStatement->execute($params);
    $contacts=$contactsStatement->fetchAll();

    $selected=null;
    if($contactId>0){
        $selectedStatement=db()->prepare(
            'SELECT c.*,owner.display_name AS owner_name,client.display_name AS client_name
             FROM crm_contacts c
             LEFT JOIN users owner ON owner.id=c.owner_user_id
             LEFT JOIN users client ON client.id=c.client_user_id
             WHERE c.id=:id'
        );
        $selectedStatement->execute(['id'=>$contactId]);
        $selected=$selectedStatement->fetch()?:null;
    }

    $opportunities=[];
    $activities=[];
    $selectedOpportunity=null;
    $selectedCommunicationThreadId=0;
    $selectedCallRequestId=0;
    $selectedCallStats=null;
    $selectedInteractionStats=[];
    $selectedCallCenterHistory=[];
    $selectedVisitorSummary=[];
    $selectedVisitorEvents=[];
    $unifiedRelationshipTimeline=[];

    if($selected){
        $opportunityStatement=db()->prepare(
            'SELECT *
             FROM crm_opportunities
             WHERE contact_id=:contact_id
             ORDER BY created_at DESC'
        );
        $opportunityStatement->execute(['contact_id'=>$contactId]);
        $opportunities=$opportunityStatement->fetchAll();

        foreach($opportunities as $opportunity){
            if((int)$opportunity['id']===$selectedOpportunityId){
                $selectedOpportunity=$opportunity;
                break;
            }
        }

        if(!$selectedOpportunity&&$opportunities){
            $selectedOpportunity=$opportunities[0];
        }

        $activityStatement=db()->prepare(
            'SELECT a.*,admin.display_name AS admin_name,o.title AS opportunity_title
             FROM crm_activities a
             LEFT JOIN users admin ON admin.id=a.admin_user_id
             LEFT JOIN crm_opportunities o ON o.id=a.opportunity_id
             WHERE a.contact_id=:contact_id
             ORDER BY a.created_at DESC
             LIMIT 100'
        );
        $activityStatement->execute(['contact_id'=>$contactId]);
        $activities=$activityStatement->fetchAll();

        $communicationStatement=db()->prepare(
            'SELECT id
             FROM communication_threads
             WHERE crm_contact_id=:contact_id
                OR (
                    :client_user_id>0
                    AND client_user_id=:client_user_id_match
                )
             ORDER BY COALESCE(last_message_at,created_at) DESC
             LIMIT 1'
        );
        $communicationStatement->execute([
            'contact_id'=>$contactId,
            'client_user_id'=>(int)($selected['client_user_id']??0),
            'client_user_id_match'=>(int)($selected['client_user_id']??0),
        ]);
        $selectedCommunicationThreadId=(int)($communicationStatement->fetchColumn()?:0);

        $callRequestStatement=db()->prepare(
            'SELECT id
             FROM call_center_requests
             WHERE crm_contact_id=:contact_id
             ORDER BY COALESCE(ended_at,answered_at,ringing_at,requested_at) DESC
             LIMIT 1'
        );
        $callRequestStatement->execute(['contact_id'=>$contactId]);
        $selectedCallRequestId=(int)($callRequestStatement->fetchColumn()?:0);

        $callStatsStatement=db()->prepare(
            'SELECT *
             FROM crm_contact_call_stats
             WHERE contact_id=:contact_id'
        );
        $callStatsStatement->execute(['contact_id'=>$contactId]);
        $selectedCallStats=$callStatsStatement->fetch()?:null;
        $selectedInteractionStats=call_center_contact_interaction_counts(
            $contactId
        );

        $historyStatement=db()->prepare(
            'SELECT request_record.*,
                    (
                        SELECT media_record.id
                        FROM call_center_media media_record
                        WHERE media_record.request_id=request_record.id
                        ORDER BY media_record.id DESC
                        LIMIT 1
                    ) AS media_id,
                    (
                        SELECT media_record.transcript_status
                        FROM call_center_media media_record
                        WHERE media_record.request_id=request_record.id
                        ORDER BY media_record.id DESC
                        LIMIT 1
                    ) AS media_transcript_status
             FROM call_center_requests request_record
             WHERE request_record.crm_contact_id=:contact_id
             ORDER BY request_record.requested_at DESC
             LIMIT 12'
        );
        $historyStatement->execute(['contact_id'=>$contactId]);
        $selectedCallCenterHistory=$historyStatement->fetchAll();

        if(visitor_intelligence_schema_available()){
            $selectedVisitorSummary=
                visitor_intelligence_contact_summary($contactId);
            $selectedVisitorEvents=
                visitor_intelligence_contact_events(
                    $contactId,
                    100
                );
        }

        foreach($selectedVisitorEvents as $visitorEvent){
            $metadata=visitor_intelligence_metadata_decode(
                $visitorEvent['metadata_json']??null
            );
            $unifiedRelationshipTimeline[]=[
                'kind'=>'visitor',
                'timestamp'=>(string)$visitorEvent['occurred_at'],
                'title'=>visitor_intelligence_event_label(
                    (string)$visitorEvent['event_type']
                ),
                'body'=>(string)(
                    $visitorEvent['event_label']
                    ?:(
                        $visitorEvent['project_title']
                        ?:(
                            $visitorEvent['page_path']
                            ?:'Website activity'
                        )
                    )
                ),
                'detail'=>(string)(
                    (string)$visitorEvent['event_type']
                    ==='music_track_play'
                        ?implode(
                            ' · ',
                            array_filter([
                                $metadata['artist']??null,
                                $metadata['album_title']??null,
                                $metadata['genre']??null,
                                !empty($metadata['demo_mode'])
                                    ?'Demo Music Mode'
                                    :null,
                            ])
                        )
                        :($metadata['prompt']??'')
                ),
                'meta'=>implode(
                    ' · ',
                    array_filter([
                        $visitorEvent['project_title']??null,
                        $visitorEvent['opportunity_title']??null,
                        isset($visitorEvent['opportunity_stage'])
                            ?status_label((string)$visitorEvent['opportunity_stage'])
                            :null,
                        $visitorEvent['page_path']??null,
                        $visitorEvent['referrer_host']??null,
                        $visitorEvent['device_type']??null,
                    ])
                ),
                'url'=>null,
            ];
        }

        foreach($selectedCallCenterHistory as $callEvent){
            $unifiedRelationshipTimeline[]=[
                'kind'=>'call',
                'timestamp'=>(string)(
                    $callEvent['ended_at']
                    ?:(
                        $callEvent['answered_at']
                        ?:(
                            $callEvent['ringing_at']
                            ?:$callEvent['requested_at']
                        )
                    )
                ),
                'title'=>call_center_request_type_label(
                    $callEvent
                ),
                'body'=>(string)(
                    $callEvent['subject']
                    ?:status_label($callEvent['status'])
                ),
                'detail'=>(string)(
                    $callEvent['message']
                    ?:(
                        $callEvent['transcript_text']
                        ?:''
                    )
                ),
                'meta'=>implode(
                    ' · ',
                    array_filter([
                        status_label(
                            (string)$callEvent['status']
                        ),
                        call_center_seconds_label(
                            $callEvent['duration_seconds']!==null
                                ?(int)$callEvent['duration_seconds']
                                :null
                        ),
                    ])
                ),
                'url'=>'?view=call-center&request='
                    .(int)$callEvent['id'],
            ];
        }

        foreach($activities as $crmActivity){
            $unifiedRelationshipTimeline[]=[
                'kind'=>'crm',
                'timestamp'=>(string)$crmActivity['created_at'],
                'title'=>(string)$crmActivity['subject'],
                'body'=>(string)(
                    $crmActivity['body']
                    ?:status_label(
                        (string)$crmActivity['activity_type']
                    )
                ),
                'detail'=>'',
                'meta'=>implode(
                    ' · ',
                    array_filter([
                        status_label(
                            (string)$crmActivity['activity_type']
                        ),
                        $crmActivity['opportunity_title']??null,
                        $crmActivity['admin_name']??null,
                    ])
                ),
                'url'=>null,
            ];
        }

        usort(
            $unifiedRelationshipTimeline,
            static fn(array $left,array $right): int =>
                strcmp(
                    (string)$right['timestamp'],
                    (string)$left['timestamp']
                )
        );
        $unifiedRelationshipTimeline=array_slice(
            $unifiedRelationshipTimeline,
            0,
            120
        );
    }

    $administrators=db()->query(
        'SELECT id,display_name
         FROM users
         WHERE role="admin" AND status="active"
         ORDER BY display_name'
    )->fetchAll();
?>
<div class="page-actions">
<form method="get" style="display:flex;flex-wrap:wrap;gap:8px">
<input type="hidden" name="view" value="crm">
<input name="q" value="<?=e($search)?>" placeholder="Search CRM contacts" style="min-height:40px;padding:8px 11px;border:1px solid #dfe5eb;border-radius:10px">
<select name="stage" style="min-height:40px;padding:8px;border:1px solid #dfe5eb;border-radius:10px">
<option value="">All lifecycle stages</option>
<?php foreach(['lead','prospect','qualified','client','partner','closed'] as $crmStage):?>
<option value="<?=e($crmStage)?>" <?=$stageFilter===$crmStage?'selected':''?>><?=e(status_label($crmStage))?></option>
<?php endforeach;?>
</select>
<button class="button" type="submit">Filter CRM</button>
</form>
<span class="spacer"></span>
<button
    class="button button-primary"
    type="button"
    data-crm-contact-open
>
    Add CRM Contact
</button>
<a class="button" href="?view=leads">Raw inquiry archive</a>
</div>

<div class="crm-contact-modal" data-crm-contact-modal hidden>
<div
    class="crm-contact-dialog"
    role="dialog"
    aria-modal="true"
    aria-labelledby="crmContactModalTitle"
>
<header>
<div>
<span>CRM</span>
<h2 id="crmContactModalTitle">Add CRM Contact</h2>
<p>Create a contact record without waiting for an inquiry, call, or voicemail.</p>
</div>
<button
    type="button"
    aria-label="Close Add CRM Contact"
    data-crm-contact-close
>×</button>
</header>

<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="create_crm_contact">

<div class="form-grid">
<label class="field">
<span>Name</span>
<input name="display_name" autocomplete="name" required>
</label>

<label class="field">
<span>Email <em>optional</em></span>
<input type="email" name="email" autocomplete="email">
</label>

<label class="field">
<span>Phone <em>optional</em></span>
<input name="phone" autocomplete="tel">
</label>

<label class="field">
<span>Company <em>optional</em></span>
<input name="company" autocomplete="organization">
</label>

<label class="field">
<span>Lifecycle stage</span>
<select name="lifecycle_stage">
<?php foreach(['lead','prospect','qualified','client','partner','closed'] as $crmStage):?>
<option value="<?=e($crmStage)?>"><?=e(status_label($crmStage))?></option>
<?php endforeach;?>
</select>
</label>

<label class="field">
<span>Owner</span>
<select name="owner_user_id">
<option value="">Unassigned</option>
<?php foreach($administrators as $administrator):?>
<option value="<?=(int)$administrator['id']?>"><?=e($administrator['display_name'])?></option>
<?php endforeach;?>
</select>
</label>

<label class="field full">
<span>Next follow-up <em>optional</em></span>
<input type="datetime-local" name="next_follow_up_at">
</label>

<label class="field full">
<span>CRM notes <em>optional</em></span>
<textarea name="notes" placeholder="Relationship context, next steps, or important details."></textarea>
</label>
</div>

<div class="form-footer">
<button class="button button-primary" type="submit">
Create CRM contact
</button>
<button
    class="button"
    type="button"
    data-crm-contact-close
>
Cancel
</button>
</div>
</form>
</div>
</div>


<?php if(!crm_message_stage_columns_available()):?>
<div class="alert alert-warning">
Import <strong>database/crm_message_stage_v40.sql</strong> to enable message stages and automatic Listened tracking.
</div>
<?php endif;?>

<div class="dashboard-grid crm-workspace">
<section class="panel">
<header class="panel-header"><h2><?=count($contacts)?> CRM contacts</h2></header>
<?php if(!$contacts):?>
<div class="empty-state">Contact Dave submissions will appear here automatically.</div>
<?php else:?>
<div class="table-wrap">
<table class="data-table">
<thead><tr><th>Contact</th><th>Opportunity</th><th>Calls</th><th>Stage</th><th>Follow-up</th></tr></thead>
<tbody>
<?php foreach($contacts as $contact):?>
<?php
$messageTotal=(int)$contact['voicemail_count']+(int)$contact['message_count'];
$contactRowId='crm-contact-messages-'.(int)$contact['id'];
?>
<tr class="crm-contact-row">
<td>
<a href="?view=crm&id=<?=(int)$contact['id']?>"><?=e($contact['display_name'])?></a>
<?php if($contact['email']):?><br><small><?=e($contact['email'])?></small><?php else:?><br><small>No email</small><?php endif;?>
<?php if($contact['company']):?><br><small><?=e($contact['company'])?></small><?php endif;?>
</td>
<td>
<?=e($contact['latest_opportunity']?:'No opportunity')?>
<br><small><?=(int)$contact['open_opportunity_count']?> open / <?=(int)$contact['opportunity_count']?> total</small>
</td>
<td>
<strong><?=(int)$contact['call_count']?> calls</strong>
<?php if($messageTotal>0):?>
<br>
<button
    class="crm-message-count"
    type="button"
    data-crm-message-toggle
    data-contact-id="<?=(int)$contact['id']?>"
    data-message-api="<?=e(app_url('portal/crm-message-api.php'))?>"
    aria-expanded="false"
    aria-controls="<?=e($contactRowId)?>"
>
    <span><?=(int)$contact['voicemail_count']?> voicemail · <?=(int)$contact['message_count']?> messages</span>
    <span aria-hidden="true">⌄</span>
</button>
<?php else:?>
<br><small>0 voicemail · 0 messages</small>
<?php endif;?>
<br><small><?=e(format_datetime($contact['last_call_at']))?></small>
</td>
<td><span class="status status-<?=e($contact['lifecycle_stage'])?>"><?=e(status_label($contact['lifecycle_stage']))?></span></td>
<td><?=e(format_datetime($contact['next_follow_up_at']))?></td>
</tr>
<?php if($messageTotal>0):?>
<tr
    class="crm-message-accordion-row"
    id="<?=e($contactRowId)?>"
    data-crm-message-row="<?=(int)$contact['id']?>"
    hidden
>
<td colspan="5">
<div
    class="crm-message-accordion"
    data-crm-message-panel="<?=(int)$contact['id']?>"
>
<div class="crm-message-loading">Loading messages…</div>
</div>
</td>
</tr>
<?php endif;?>
<?php endforeach;?>
</tbody>
</table>
</div>
<?php endif;?>
</section>

<section class="stack">
<?php if(!$selected):?>
<div class="panel"><div class="empty-state">Select a CRM contact to review the inquiry, opportunity, and activity history.</div></div>
<?php else:?>

<?php if($selectedCallStats):?>
<div class="crm-call-stats">
<article><span>Total requests</span><strong><?=(int)$selectedCallStats['total_requests']?></strong></article>
<article><span>Total calls</span><strong><?=(int)$selectedCallStats['total_calls']?></strong></article>
<article><span>Completed</span><strong><?=(int)$selectedCallStats['completed_calls']?></strong></article>
<article><span>Missed</span><strong><?=(int)$selectedCallStats['missed_calls']?></strong></article>
<article><span>Talk time</span><strong><?=e(call_center_seconds_label((int)$selectedCallStats['total_duration_seconds']))?></strong></article>
<article><span>Last call</span><strong><?=e(format_datetime($selectedCallStats['last_call_at']))?></strong></article>
<article><span>Voicemails</span><strong><?=(int)($selectedInteractionStats['voicemails']??0)?></strong></article>
<article><span>Messages</span><strong><?=(int)($selectedInteractionStats['messages']??0)?></strong></article>
</div>
<?php endif;?>


<?php if($selectedVisitorSummary):?>
<div class="crm-visitor-summary">
<article><span>Visitor profiles</span><strong><?=(int)($selectedVisitorSummary['visitor_profiles']??0)?></strong></article>
<article><span>Sessions</span><strong><?=(int)($selectedVisitorSummary['sessions']??0)?></strong></article>
<article><span>Page views</span><strong><?=(int)($selectedVisitorSummary['page_views']??0)?></strong></article>
<article><span>Portfolio views</span><strong><?=(int)($selectedVisitorSummary['portfolio_views']??0)?></strong></article>
<article><span>Chat prompts</span><strong><?=(int)($selectedVisitorSummary['chat_prompts']??0)?></strong></article>
<article><span>Music plays</span><strong><?=(int)($selectedVisitorSummary['music_plays']??0)?></strong><small><?=(int)($selectedVisitorSummary['music_tracks_played']??0)?> unique tracks</small></article>
<article><span>Resume activity</span><strong><?=(
    (int)($selectedVisitorSummary['resume_views']??0)
    +(int)($selectedVisitorSummary['resume_downloads']??0)
)?></strong></article>
</div>
<?php endif;?>

<?php if($unifiedRelationshipTimeline):?>
<section class="panel">
<header class="panel-header">
<div>
<span>Unified contact activity</span>
<h2>Relationship timeline</h2>
</div>
<?php if(visitor_intelligence_schema_available()):?>
<a class="button button-small" href="?view=analytics">Visitor Intelligence</a>
<?php endif;?>
</header>
<div class="unified-relationship-timeline">
<?php foreach($unifiedRelationshipTimeline as $timelineEvent):?>
<?php
$timelineIcon=match($timelineEvent['kind']){
    'visitor'=>'Web',
    'call'=>'Call',
    default=>'CRM',
};
?>
<article class="unified-timeline-item">
<div class="unified-timeline-icon"><?=e($timelineIcon)?></div>
<div class="unified-timeline-copy">
<header>
<strong>
<?php if($timelineEvent['url']):?>
<a href="<?=e($timelineEvent['url'])?>"><?=e($timelineEvent['title'])?></a>
<?php else:?>
<?=e($timelineEvent['title'])?>
<?php endif;?>
</strong>
<time><?=e(format_datetime($timelineEvent['timestamp']))?></time>
</header>
<?php if($timelineEvent['body']):?>
<p><?=nl2br(e($timelineEvent['body']))?></p>
<?php endif;?>
<?php if($timelineEvent['detail']):?>
<blockquote><?=nl2br(e($timelineEvent['detail']))?></blockquote>
<?php endif;?>
<?php if($timelineEvent['meta']):?>
<small><?=e($timelineEvent['meta'])?></small>
<?php endif;?>
</div>
</article>
<?php endforeach;?>
</div>
</section>
<?php endif;?>

<?php if($selectedCallCenterHistory):?>
<section class="panel crm-call-center-history">
<header class="panel-header">
<div>
<span>Call Center CRM</span>
<h2>Calls, voicemail, and messages</h2>
</div>
<a class="button button-small" href="?view=call-center">Open Call Center</a>
</header>
<div class="crm-call-center-list">
<?php foreach($selectedCallCenterHistory as $history):?>
<article>
<div>
<strong><?=e(call_center_request_type_label($history))?> · <?=e($history['subject'])?></strong>
<small><?=e(format_datetime($history['requested_at']))?> · <?=e(status_label($history['status']))?></small>
<?php if($history['message']):?><p><?=nl2br(e($history['message']))?></p><?php endif;?>
<?php if($history['transcript_text']):?><blockquote><?=nl2br(e($history['transcript_text']))?></blockquote><?php endif;?>
</div>
<div>
<?php if($history['media_id']):?>
<audio controls preload="metadata" src="<?=e(app_url('portal/call-center-media.php?id='.(int)$history['media_id']))?>"></audio>
<span class="status status-planning"><?=e(status_label($history['media_transcript_status']?:'not_requested'))?></span>
<?php endif;?>
<a class="button button-small" href="?view=call-center&request=<?=(int)$history['id']?>">Open record</a>
</div>
</article>
<?php endforeach;?>
</div>
</section>
<?php endif;?>

<form method="post" class="form-panel">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_crm_contact">
<input type="hidden" name="contact_id" value="<?=(int)$selected['id']?>">
<h2 style="margin-top:0;font-size:1rem">Contact record</h2>
<div class="form-grid">
<label class="field"><span>Name</span><input name="display_name" value="<?=e($selected['display_name'])?>" required></label>
<label class="field"><span>Email <em>optional</em></span><input type="email" name="email" value="<?=e($selected['email']??'')?>"></label>
<label class="field"><span>Company</span><input name="company" value="<?=e($selected['company']??'')?>"></label>
<label class="field"><span>Phone</span><input name="phone" value="<?=e($selected['phone']??'')?>"></label>
<label class="field"><span>Lifecycle stage</span><select name="lifecycle_stage">
<?php foreach(['lead','prospect','qualified','client','partner','closed'] as $crmStage):?>
<option value="<?=e($crmStage)?>" <?=$selected['lifecycle_stage']===$crmStage?'selected':''?>><?=e(status_label($crmStage))?></option>
<?php endforeach;?>
</select></label>
<label class="field"><span>Owner</span><select name="owner_user_id"><option value="">Unassigned</option>
<?php foreach($administrators as $administrator):?>
<option value="<?=(int)$administrator['id']?>" <?=(int)($selected['owner_user_id']??0)===(int)$administrator['id']?'selected':''?>><?=e($administrator['display_name'])?></option>
<?php endforeach;?>
</select></label>
<label class="field full"><span>Next follow-up</span><input type="datetime-local" name="next_follow_up_at" value="<?=e($selected['next_follow_up_at']?date('Y-m-d\TH:i',strtotime($selected['next_follow_up_at'])):'')?>"></label>
<label class="field full"><span>CRM notes</span><textarea name="notes"><?=e($selected['notes']??'')?></textarea></label>
</div>
<div class="form-footer">
<button class="button button-primary" type="submit">Save CRM contact</button>
<?php if($selected['client_user_id']):?>
<a class="button" href="?view=clients&edit=<?=(int)$selected['client_user_id']?>">Open client account</a>
<?php if($selectedCallRequestId>0):?>
<a class="button" href="?view=call-center&request=<?=$selectedCallRequestId?>">Open Call Center history</a>
<?php else:?>
<a class="button" href="?view=call-center">Open Call Center</a>
<?php endif;?>
<?php if($selectedCommunicationThreadId>0):?>
<a class="button" href="?view=communications&thread=<?=$selectedCommunicationThreadId?>">Open Communications</a>
<?php else:?>
<a class="button" href="?view=communications&client=<?=(int)$selected['client_user_id']?>&new=1">Start conversation</a>
<?php endif;?>
<?php endif;?>
</div>
</form>

<?php if($selectedOpportunity):?>
<form method="post" class="form-panel">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_crm_opportunity">
<input type="hidden" name="contact_id" value="<?=(int)$selected['id']?>">
<input type="hidden" name="opportunity_id" value="<?=(int)$selectedOpportunity['id']?>">
<h2 style="margin-top:0;font-size:1rem"><?=e($selectedOpportunity['title'])?></h2>
<p style="color:#687586;font-size:.76rem"><?=nl2br(e($selectedOpportunity['message']?:'No inquiry message.'))?></p>
<div class="form-grid">
<label class="field"><span>Stage</span><select name="stage">
<?php foreach(['new','reviewing','contacted','qualified','proposal','won','lost'] as $opportunityStage):?>
<option value="<?=e($opportunityStage)?>" <?=$selectedOpportunity['stage']===$opportunityStage?'selected':''?>><?=e(status_label($opportunityStage))?></option>
<?php endforeach;?>
</select></label>
<label class="field"><span>Probability</span><input type="number" name="probability" min="0" max="100" value="<?=(int)$selectedOpportunity['probability']?>"></label>
<label class="field"><span>Estimated value</span><input type="number" name="estimated_value" min="0" step=".01" value="<?=e($selectedOpportunity['estimated_value']??'')?>"></label>
<label class="field"><span>Next action date</span><input type="datetime-local" name="next_action_at" value="<?=e($selectedOpportunity['next_action_at']?date('Y-m-d\TH:i',strtotime($selectedOpportunity['next_action_at'])):'')?>"></label>
<label class="field full"><span>Next action</span><input name="next_action" value="<?=e($selectedOpportunity['next_action']??'')?>"></label>
</div>
<div class="form-footer">
<button class="button button-primary" type="submit">Save opportunity</button>
<?php if(!$selected['client_user_id']):?>
<button class="button" type="submit" form="crmConvertForm">Convert to client</button>
<?php endif;?>
</div>
</form>

<?php if(!$selected['client_user_id']):?>
<form method="post" id="crmConvertForm">
<?=csrf_field()?>
<input type="hidden" name="action" value="convert_crm_contact">
<input type="hidden" name="contact_id" value="<?=(int)$selected['id']?>">
<input type="hidden" name="opportunity_id" value="<?=(int)$selectedOpportunity['id']?>">
</form>
<?php endif;?>

<?php if(count($opportunities)>1):?>
<section class="panel">
<header class="panel-header"><h2>All opportunities</h2></header>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Opportunity</th><th>Stage</th><th>Value</th><th>Created</th></tr></thead><tbody>
<?php foreach($opportunities as $opportunity):?>
<tr>
<td><a href="?view=crm&id=<?=(int)$selected['id']?>&opportunity=<?=(int)$opportunity['id']?>"><?=e($opportunity['title'])?></a></td>
<td><span class="status status-<?=e($opportunity['stage'])?>"><?=e(status_label($opportunity['stage']))?></span></td>
<td><?=e(format_money($opportunity['estimated_value']))?></td>
<td><?=e(format_datetime($opportunity['created_at']))?></td>
</tr>
<?php endforeach;?>
</tbody></table></div>
</section>
<?php endif;?>
<?php endif;?>

<form method="post" class="form-panel">
<?=csrf_field()?>
<input type="hidden" name="action" value="add_crm_activity">
<input type="hidden" name="contact_id" value="<?=(int)$selected['id']?>">
<input type="hidden" name="opportunity_id" value="<?=(int)($selectedOpportunity['id']??0)?>">
<h2 style="margin-top:0;font-size:1rem">Add activity</h2>
<div class="form-grid">
<label class="field"><span>Type</span><select name="activity_type"><option value="note">Note</option><option value="email">Email</option><option value="call">Call</option><option value="meeting">Meeting</option></select></label>
<label class="field"><span>Subject</span><input name="subject" required></label>
<label class="field full"><span>Details</span><textarea name="body"></textarea></label>
</div>
<div class="form-footer"><button class="button button-primary" type="submit">Add activity</button></div>
</form>

<section class="panel">
<header class="panel-header"><h2>CRM activity log</h2></header>
<div class="panel-body">
<?php if(!$activities):?><div class="empty-state">No activity recorded.</div><?php else:?><div class="timeline">
<?php foreach($activities as $activity):?>
<article class="timeline-item">
<h3><?=e($activity['subject'])?></h3>
<p><?=nl2br(e($activity['body']?:status_label($activity['activity_type'])))?></p>
<small><?=e(status_label($activity['activity_type']))?><?php if($activity['opportunity_title']):?> · <?=e($activity['opportunity_title'])?><?php endif;?><?php if($activity['admin_name']):?> · <?=e($activity['admin_name'])?><?php endif;?> · <?=e(format_datetime($activity['created_at']))?></small>
</article>
<?php endforeach;?>
</div><?php endif;?>
</div>
</section>
<?php endif;?>
</section>
</div>
<?php
}


if($view==='portfolio'){
    $portfolioReady=portfolio_schema_available();
    $portfolioProjects=$portfolioReady?portfolio_admin_projects():[];
    $edit=(string)($_GET['edit']??'');
    $selected=null;

    if($portfolioReady&&ctype_digit($edit)){
        $selected=portfolio_admin_project((int)$edit);
    }

    $portfolioMedia=$selected['media']??[];
?>
<?php if(!$portfolioReady):?>
<div class="alert alert-warning">
Import <strong>database/portfolio_backend_v41.sql</strong> to create the portfolio backend and active project data.
</div>
<?php else:?>
<div class="page-actions">
<a class="button button-primary" href="?view=portfolio&edit=new">Add portfolio project</a>
<?php if($edit!==''):?>
<a class="button" href="?view=portfolio">Back to portfolio</a>
<?php endif;?>
<span class="spacer"></span>
<a class="button" href="<?=e(app_url('index.php'))?>" target="_blank" rel="noopener">Open public portfolio</a>
</div>

<?php if($edit===''):?>
<div class="portfolio-admin-grid">
<?php foreach($portfolioProjects as $project):?>
<article class="portfolio-admin-card">
<div class="portfolio-admin-cover">
<?php if(!empty($project['cover_media_id'])):?>
<img
    src="<?=e(portfolio_media_url((int)$project['cover_media_id']))?>"
    alt="<?=e($project['cover_alt_text']?:$project['title'])?>"
>
<?php else:?>
<div class="portfolio-admin-placeholder">
<span><?=e(strtoupper(substr((string)$project['title'],0,1)))?></span>
</div>
<?php endif;?>
<div class="portfolio-admin-badges">
<span class="status status-<?=e($project['status'])?>"><?=e(status_label($project['status']))?></span>
<?php if((int)$project['featured']===1):?><span class="portfolio-featured-badge">Featured</span><?php endif;?>
</div>
</div>
<div class="portfolio-admin-copy">
<span><?=e($project['project_type']?:'Portfolio project')?><?php if($project['year_label']):?> · <?=e($project['year_label'])?><?php endif;?></span>
<h2><?=e($project['title'])?></h2>
<p><?=e($project['summary']?:'Add a project summary.')?></p>
<div class="portfolio-admin-meta">
<?php if($project['client_name']):?><span><?=e($project['client_name'])?></span><?php endif;?>
<span>Order <?=(int)$project['sort_order']?></span>
</div>
</div>
<footer>
<a class="button button-small button-primary" href="?view=portfolio&edit=<?=(int)$project['id']?>">Manage</a>
<?php if($project['status']==='active'):?>
<a
    class="button button-small"
    href="<?=e(app_url('index.php?portfolio='.rawurlencode((string)$project['slug'])))?>"
    target="_blank"
    rel="noopener"
>Preview</a>
<?php endif;?>
<?php if($project['project_url']):?>
<a class="button button-small" href="<?=e($project['project_url'])?>" target="_blank" rel="noopener">Project site</a>
<?php endif;?>
</footer>
</article>
<?php endforeach;?>
</div>

<?php if(!$portfolioProjects):?>
<div class="panel"><div class="empty-state">No portfolio projects have been created.</div></div>
<?php endif;?>

<?php else:?>
<div class="portfolio-editor-layout">
<form
    method="post"
    enctype="multipart/form-data"
    class="form-panel portfolio-editor-form"
>
<?=csrf_field()?>
<input type="hidden" name="action" value="save_portfolio_project">
<input type="hidden" name="id" value="<?=(int)($selected['id']??0)?>">

<header class="portfolio-editor-header">
<div>
<span>Portfolio project</span>
<h2><?=e($selected['title']??'Create portfolio project')?></h2>
<p>Manage the public case study, project link, cover image, gallery, role, services, tools and outcomes.</p>
</div>
<?php if($selected&&$selected['status']==='active'):?>
<a
    class="button"
    href="<?=e(app_url('index.php?portfolio='.rawurlencode((string)$selected['slug'])))?>"
    target="_blank"
    rel="noopener"
>Public preview</a>
<?php endif;?>
</header>

<section class="portfolio-form-section">
<header>
<span>Identity</span>
<h3>Project basics</h3>
</header>
<div class="form-grid">
<label class="field">
<span>Project title</span>
<input name="title" value="<?=e($selected['title']??'')?>" required>
</label>

<label class="field">
<span>Slug</span>
<input name="slug" value="<?=e($selected['slug']??'')?>" placeholder="generated-from-title">
</label>

<label class="field">
<span>Status</span>
<select name="status">
<?php foreach(['draft','active','archived'] as $portfolioStatus):?>
<option value="<?=e($portfolioStatus)?>" <?=($selected['status']??'draft')===$portfolioStatus?'selected':''?>><?=e(status_label($portfolioStatus))?></option>
<?php endforeach;?>
</select>
</label>

<label class="field">
<span>Display order</span>
<input type="number" name="sort_order" min="0" value="<?=(int)($selected['sort_order']??100)?>">
</label>

<label class="checkbox-row full portfolio-featured-control">
<input type="checkbox" name="featured" value="1" <?=(int)($selected['featured']??0)===1?'checked':''?>>
<span>Feature this project before other active portfolio projects.</span>
</label>
</div>
</section>

<section class="portfolio-form-section">
<header>
<span>Project details</span>
<h3>Traditional portfolio information</h3>
</header>
<div class="form-grid">
<label class="field">
<span>Client or brand</span>
<input name="client_name" value="<?=e($selected['client_name']??'')?>">
</label>

<label class="field">
<span>Project type</span>
<input name="project_type" value="<?=e($selected['project_type']??'')?>" placeholder="CRM platform, website, product system…">
</label>

<label class="field">
<span>Industry</span>
<input name="industry" value="<?=e($selected['industry']??'')?>">
</label>

<label class="field">
<span>Year or date</span>
<input name="year_label" value="<?=e($selected['year_label']??'')?>" placeholder="2026 or 2024–Present">
</label>

<label class="field full">
<span>My role</span>
<input name="role_title" value="<?=e($selected['role_title']??'')?>" placeholder="Product strategy, systems architecture, UI/UX and implementation">
</label>

<label class="field full">
<span>Summary</span>
<textarea name="summary" rows="3"><?=e($selected['summary']??'')?></textarea>
</label>

<label class="field full">
<span>Overview</span>
<textarea name="overview" rows="5"><?=e($selected['overview']??'')?></textarea>
</label>
</div>
</section>

<section class="portfolio-form-section">
<header>
<span>Case study</span>
<h3>Challenge, solution and results</h3>
</header>
<div class="form-grid">
<label class="field full">
<span>Challenge</span>
<textarea name="challenge" rows="5"><?=e($selected['challenge']??'')?></textarea>
</label>

<label class="field full">
<span>Solution</span>
<textarea name="solution" rows="5"><?=e($selected['solution']??'')?></textarea>
</label>

<label class="field full">
<span>Results</span>
<textarea name="results" rows="5"><?=e($selected['results']??'')?></textarea>
</label>
</div>
</section>

<section class="portfolio-form-section">
<header>
<span>Capabilities</span>
<h3>Services, tools and discovery</h3>
</header>
<div class="form-grid">
<label class="field">
<span>Services</span>
<textarea name="services" rows="7" placeholder="One service per line"><?=e($selected['services']??'')?></textarea>
</label>

<label class="field">
<span>Technologies and tools</span>
<textarea name="technologies" rows="7" placeholder="One item per line"><?=e($selected['technologies']??'')?></textarea>
</label>

<label class="field full">
<span>Search keywords</span>
<textarea name="keywords" rows="3" placeholder="One keyword or phrase per line"><?=e($selected['keywords']??'')?></textarea>
</label>
</div>
</section>

<section class="portfolio-form-section">
<header>
<span>Primary project link</span>
<h3>Where visitors continue</h3>
</header>
<div class="form-grid">
<label class="field full">
<span>Project URL</span>
<input type="url" name="project_url" value="<?=e($selected['project_url']??'')?>" placeholder="https://">
</label>

<label class="field full">
<span>Button label</span>
<input name="project_url_label" value="<?=e($selected['project_url_label']??'View project')?>">
</label>
</div>
</section>

<section class="portfolio-form-section">
<header>
<span>Media uploads</span>
<h3>Cover and gallery</h3>
</header>
<div class="form-grid">
<label class="field full">
<span>New cover image</span>
<input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp,image/gif">
<small>Landscape images work best. Uploading a new cover keeps the previous cover in the gallery.</small>
</label>

<label class="field">
<span>Cover alt text</span>
<input name="cover_alt" value="">
</label>

<label class="field">
<span>Cover caption</span>
<input name="cover_caption" value="">
</label>

<label class="field full">
<span>Add gallery images</span>
<input type="file" name="gallery_images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
<small>Select multiple images. Edit individual captions and ordering after upload.</small>
</label>

<label class="field">
<span>Default gallery alt text</span>
<input name="gallery_alt" value="">
</label>

<label class="field">
<span>Default gallery caption</span>
<input name="gallery_caption" value="">
</label>
</div>
</section>

<div class="form-footer">
<button class="button button-primary" type="submit">Save portfolio project</button>
<a class="button" href="?view=portfolio">Cancel</a>
</div>
</form>

<aside class="portfolio-media-manager">
<?php if(!$selected):?>
<section class="panel">
<div class="empty-state">Save the project before uploading and organizing gallery images.</div>
</section>
<?php else:?>
<section class="panel portfolio-media-panel">
<header class="panel-header">
<div>
<span>Project media</span>
<h2><?=count($portfolioMedia)?> images</h2>
</div>
</header>

<?php if(!$portfolioMedia):?>
<div class="empty-state">No cover or gallery images have been uploaded.</div>
<?php else:?>
<div class="portfolio-media-grid">
<?php foreach($portfolioMedia as $media):?>
<article class="portfolio-media-card">
<div class="portfolio-media-preview">
<img
    src="<?=e(portfolio_media_url((int)$media['id']))?>"
    alt="<?=e($media['alt_text']?:$selected['title'])?>"
>
<span><?=e(status_label($media['media_role']))?></span>
</div>

<form method="post" class="portfolio-media-form">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_portfolio_media">
<input type="hidden" name="project_id" value="<?=(int)$selected['id']?>">
<input type="hidden" name="media_id" value="<?=(int)$media['id']?>">

<label class="field">
<span>Alt text</span>
<input name="alt_text" value="<?=e($media['alt_text']??'')?>">
</label>

<label class="field">
<span>Caption</span>
<textarea name="caption" rows="2"><?=e($media['caption']??'')?></textarea>
</label>

<label class="field">
<span>Order</span>
<input type="number" name="sort_order" min="0" value="<?=(int)$media['sort_order']?>">
</label>

<label class="checkbox-row">
<input type="checkbox" name="make_cover" value="1" <?=$media['media_role']==='cover'?'checked':''?>>
<span>Use as cover image</span>
</label>

<div class="portfolio-media-actions">
<button class="button button-small" type="submit">Save image</button>
</div>
</form>

<form method="post" onsubmit="return confirm('Remove this portfolio image?')">
<?=csrf_field()?>
<input type="hidden" name="action" value="delete_portfolio_media">
<input type="hidden" name="project_id" value="<?=(int)$selected['id']?>">
<input type="hidden" name="media_id" value="<?=(int)$media['id']?>">
<button class="button button-small button-danger" type="submit">Remove</button>
</form>
</article>
<?php endforeach;?>
</div>
<?php endif;?>
</section>

<section class="panel portfolio-publish-panel">
<header class="panel-header"><h2>Publishing</h2></header>
<div class="panel-body">
<p>
<strong><?=e(status_label($selected['status']))?></strong><br>
<span>Public portfolio slug: <?=e($selected['slug'])?></span>
</p>
<?php if($selected['status']!=='archived'):?>
<form method="post" onsubmit="return confirm('Archive this portfolio project?')">
<?=csrf_field()?>
<input type="hidden" name="action" value="archive_portfolio_project">
<input type="hidden" name="project_id" value="<?=(int)$selected['id']?>">
<button class="button button-danger" type="submit">Archive project</button>
</form>
<?php endif;?>
</div>
</section>
<?php endif;?>
</aside>
</div>
<?php endif;?>
<?php endif;?>
<?php
}

if($view==='projects'){
    $projects=admin_projects();$clients=admin_clients();$edit=(string)($_GET['edit']??'');$selected=null;if(ctype_digit($edit)){foreach($projects as $p)if((int)$p['id']===(int)$edit)$selected=$p;}
    $updates=[];if($selected){$s=db()->prepare('SELECT pu.*,u.display_name FROM project_updates pu JOIN users u ON u.id=pu.created_by WHERE pu.project_id=:id ORDER BY pu.created_at DESC');$s->execute(['id'=>$selected['id']]);$updates=$s->fetchAll();}
?>
<div class="page-actions"><a class="button button-primary" href="?view=projects&edit=new">Create project</a></div>
<?php if($edit===''):?><div class="card-grid"><?php foreach($projects as $p):?><article class="project-card"><span class="status status-<?=e($p['status'])?>"><?=e(status_label($p['status']))?></span><h2 style="margin-top:10px"><?=e($p['title'])?></h2><p><?=e($p['summary']?:'No summary.')?></p><div class="card-meta"><span><?=e($p['company']?:$p['client_name'])?></span><span>Due <?=e(format_date($p['due_date']))?></span></div><div class="progress"><div class="progress-track"><span style="width:<?=(int)$p['progress']?>%"></span></div><small><?=(int)$p['progress']?>% complete</small></div><div class="card-actions"><a class="button button-small" href="?view=projects&edit=<?=(int)$p['id']?>">Manage</a></div></article><?php endforeach;?></div>
<?php else:?><div class="dashboard-grid"><form method="post" class="form-panel"><?=csrf_field()?><input type="hidden" name="action" value="save_project"><input type="hidden" name="id" value="<?=(int)($selected['id']??0)?>"><div class="form-grid"><label class="field"><span>Client</span><select name="client_user_id" required><option value="">Select client</option><?php foreach($clients as $c):?><option value="<?=(int)$c['id']?>" <?=(int)($selected['client_user_id']??0)===(int)$c['id']?'selected':''?>><?=e($c['company']?:$c['display_name'])?></option><?php endforeach;?></select></label><label class="field"><span>Title</span><input name="title" value="<?=e($selected['title']??'')?>" required></label><label class="field full"><span>Summary</span><textarea name="summary"><?=e($selected['summary']??'')?></textarea></label><label class="field"><span>Status</span><select name="status"><?php foreach(['discovery','planning','active','review','on_hold','completed','archived'] as $st):?><option value="<?=e($st)?>" <?=($selected['status']??'planning')===$st?'selected':''?>><?=e(status_label($st))?></option><?php endforeach;?></select></label><label class="field"><span>Priority</span><select name="priority"><?php foreach(['low','normal','high','urgent'] as $pr):?><option value="<?=e($pr)?>" <?=($selected['priority']??'normal')===$pr?'selected':''?>><?=e(ucfirst($pr))?></option><?php endforeach;?></select></label><label class="field"><span>Progress</span><input type="number" name="progress" min="0" max="100" value="<?=(int)($selected['progress']??0)?>"></label><label class="field"><span>Budget</span><input type="number" name="budget" step=".01" min="0" value="<?=e($selected['budget']??'')?>"></label><label class="field"><span>Start</span><input type="date" name="start_date" value="<?=e($selected['start_date']??'')?>"></label><label class="field"><span>Due</span><input type="date" name="due_date" value="<?=e($selected['due_date']??'')?>"></label><label class="field"><span>Next milestone</span><input name="next_milestone" value="<?=e($selected['next_milestone']??'')?>"></label><label class="field"><span>Milestone date</span><input type="date" name="next_milestone_date" value="<?=e($selected['next_milestone_date']??'')?>"></label></div><div class="form-footer"><button class="button button-primary">Save project</button><a class="button" href="?view=projects">Cancel</a></div></form>
<section><?php if(!$selected):?><div class="panel"><div class="empty-state">Save the project before adding updates.</div></div><?php else:?><form method="post" class="form-panel"><?=csrf_field()?><input type="hidden" name="action" value="add_project_update"><input type="hidden" name="project_id" value="<?=(int)$selected['id']?>"><div class="form-grid"><label class="field full"><span>Update title</span><input name="update_title" required></label><label class="field full"><span>Message</span><textarea name="update_body" required></textarea></label><label class="field"><span>Visibility</span><select name="visibility"><option value="client">Client</option><option value="admin">Admin only</option></select></label></div><div class="form-footer"><button class="button button-primary">Post update</button></div></form><div class="panel" style="margin-top:16px"><div class="panel-body"><div class="timeline"><?php foreach($updates as $u):?><article class="timeline-item"><h3><?=e($u['title'])?></h3><p><?=nl2br(e($u['body']))?></p><small><?=e($u['display_name'])?> · <?=e(status_label($u['visibility']))?> · <?=e(format_datetime($u['created_at']))?></small></article><?php endforeach;?></div></div></div><?php endif;?></section></div><?php endif;?>
<?php
}

if($view==='leads'){
    $leads=db()->query('SELECT * FROM leads ORDER BY created_at DESC')->fetchAll();$id=query_int('id');$selected=null;foreach($leads as $l)if((int)$l['id']===$id)$selected=$l;
?>
<div class="dashboard-grid"><section class="panel"><header class="panel-header"><h2>Website inquiries</h2></header><div class="table-wrap"><table class="data-table"><thead><tr><th>Contact</th><th>Opportunity</th><th>Status</th><th>Received</th></tr></thead><tbody><?php foreach($leads as $l):?><tr><td><a href="?view=leads&id=<?=(int)$l['id']?>"><?=e($l['name'])?></a><br><small><?=e($l['email'])?></small></td><td><?=e($l['opportunity']?:'General')?></td><td><span class="status status-<?=e($l['status'])?>"><?=e(status_label($l['status']))?></span></td><td><?=e(format_datetime($l['created_at']))?></td></tr><?php endforeach;?></tbody></table></div></section><section class="panel"><header class="panel-header"><h2><?=e($selected['name']??'Lead details')?></h2></header><div class="panel-body"><?php if(!$selected):?><div class="empty-state">Select a lead.</div><?php else:?><p><strong><?=e($selected['company']?:'No company')?></strong><br><a href="mailto:<?=e($selected['email'])?>"><?=e($selected['email'])?></a></p><div class="message-card"><h2><?=e($selected['opportunity']?:'Inquiry')?></h2><div class="message-body"><?=e($selected['message'])?></div></div><form method="post" style="margin-top:15px"><?=csrf_field()?><input type="hidden" name="action" value="lead_status"><input type="hidden" name="id" value="<?=(int)$selected['id']?>"><label class="field"><span>Status</span><select name="status"><?php foreach(['new','contacted','qualified','converted','closed'] as $st):?><option value="<?=e($st)?>" <?=$selected['status']===$st?'selected':''?>><?=e(status_label($st))?></option><?php endforeach;?></select></label><div class="form-footer"><button class="button">Update status</button></div></form><?php if($selected['status']!=='converted'):?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="convert_lead"><input type="hidden" name="id" value="<?=(int)$selected['id']?>"><button class="button button-primary">Convert to client</button></form><?php endif;?><?php endif;?></div></section></div>
<?php
}

if($view==='call-center'){
    call_center_render_admin($user);
}

if($view==='notifications'){
    notification_render_feed($user);
}

if($view==='communications'){
    communication_render_page($user,true);
}

if($view==='messages'){
    $clients=admin_clients();$projects=admin_projects();$clientFilter=query_int('client');$params=[];$sql='SELECT m.*,u.display_name AS client_name,u.company,p.title AS project_title,s.display_name AS sender_name FROM messages m JOIN users u ON u.id=m.client_user_id LEFT JOIN projects p ON p.id=m.project_id LEFT JOIN users s ON s.id=m.sender_user_id';if($clientFilter>0){$sql.=' WHERE m.client_user_id=:c';$params['c']=$clientFilter;}$sql.=' ORDER BY m.created_at DESC LIMIT 100';$s=db()->prepare($sql);$s->execute($params);$messages=$s->fetchAll();db()->exec('UPDATE messages SET is_read_by_admin=1 WHERE is_read_by_admin=0');
?>
<div class="dashboard-grid"><form method="post" class="form-panel"><?=csrf_field()?><input type="hidden" name="action" value="send_message"><div class="form-grid"><label class="field"><span>Client</span><select name="client_user_id" required><option value="">Select</option><?php foreach($clients as $c):?><option value="<?=(int)$c['id']?>" <?=$clientFilter===(int)$c['id']?'selected':''?>><?=e($c['company']?:$c['display_name'])?></option><?php endforeach;?></select></label><label class="field"><span>Project</span><select name="project_id"><option value="">General</option><?php foreach($projects as $p):?><option value="<?=(int)$p['id']?>"><?=e($p['title'])?></option><?php endforeach;?></select></label><label class="field full"><span>Subject</span><input name="subject" required></label><label class="field full"><span>Message</span><textarea name="body" required></textarea></label></div><div class="form-footer"><button class="button button-primary">Send</button></div></form><section class="panel"><div class="panel-body"><div class="message-list"><?php foreach($messages as $m):?><article class="message-card"><header><div><h2><?=e($m['subject'])?></h2><p><?=e($m['company']?:$m['client_name'])?> · <?=e($m['project_title']?:'General')?></p></div><time><?=e(format_datetime($m['created_at']))?></time></header><div class="message-body"><?=e($m['body'])?></div><div class="card-meta"><span>From <?=e($m['sender_name']?:status_label($m['sender_type']))?></span></div></article><?php endforeach;?></div></div></section></div>
<?php
}

if($view==='files'){
    $clients=admin_clients();$projects=admin_projects();$files=db()->query('SELECT f.*,u.display_name AS client_name,u.company,p.title AS project_title FROM files f JOIN users u ON u.id=f.client_user_id LEFT JOIN projects p ON p.id=f.project_id ORDER BY f.created_at DESC LIMIT 100')->fetchAll();
?>
<div class="dashboard-grid"><form method="post" enctype="multipart/form-data" class="form-panel"><?=csrf_field()?><input type="hidden" name="action" value="upload_file"><div class="form-grid"><label class="field"><span>Client</span><select name="client_user_id" required><option value="">Select</option><?php foreach($clients as $c):?><option value="<?=(int)$c['id']?>"><?=e($c['company']?:$c['display_name'])?></option><?php endforeach;?></select></label><label class="field"><span>Project</span><select name="project_id"><option value="">General</option><?php foreach($projects as $p):?><option value="<?=(int)$p['id']?>"><?=e($p['title'])?></option><?php endforeach;?></select></label><label class="field full"><span>File</span><input type="file" name="file" required><small>Executable and script formats are blocked.</small></label><label class="field full"><span>Description</span><input name="description"></label><label class="field"><span>Visibility</span><select name="visibility"><option value="client">Client</option><option value="admin">Admin only</option></select></label></div><div class="form-footer"><button class="button button-primary">Upload</button></div></form><section class="panel"><div class="panel-body"><div class="message-list"><?php foreach($files as $f):?><article class="file-card"><h2><?=e($f['original_name'])?></h2><p><?=e($f['description']?:'No description')?></p><div class="card-meta"><span><?=e($f['company']?:$f['client_name'])?></span><span><?=e($f['project_title']?:'General')?></span><span><?=e(format_bytes((int)$f['size_bytes']))?></span><span><?=e(status_label($f['visibility']))?></span></div><a class="button button-small" href="<?=e(app_url('portal/download.php?id='.$f['id']))?>">Download</a></article><?php endforeach;?></div></div></section></div>
<?php
}

if($view==='knowledge'){
    $knowledgeWarnings=[];
    $knowledgeJsonPath=NMM_ROOT.'/chat-knowledge-base/knowledge-base.json';
    $data=['entries'=>[]];

    try{
        if(!is_file($knowledgeJsonPath)||!is_readable($knowledgeJsonPath)){
            throw new RuntimeException(
                'The manual knowledge JSON file is missing or unreadable.'
            );
        }

        $knowledgeJson=(string)file_get_contents($knowledgeJsonPath);

        if(trim($knowledgeJson)===''){
            throw new RuntimeException(
                'The manual knowledge JSON file is empty.'
            );
        }

        $decodedKnowledge=json_decode(
            $knowledgeJson,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if(!is_array($decodedKnowledge)){
            throw new RuntimeException(
                'The manual knowledge JSON file does not contain an object.'
            );
        }

        $data=$decodedKnowledge;
    }catch(Throwable $exception){
        $knowledgeWarnings[]=
            'Manual knowledge entries could not be loaded: '.
            $exception->getMessage().
            ' Uploaded database assets remain available below.';
    }

    $rawEntries=is_array($data['entries']??null)
        ? $data['entries']
        : [];

    if(!is_array($data['entries']??null)){
        $knowledgeWarnings[]=
            'The manual knowledge file has no valid entries collection.';
    }

    $entries=array_values(array_filter(
        $rawEntries,
        static fn(mixed $entry): bool =>
            is_array($entry)
            && trim((string)($entry['id']??''))!==''
            && trim((string)($entry['title']??''))!==''
    ));

    if(count($entries)!==count($rawEntries)){
        $knowledgeWarnings[]=
            'One or more malformed manual knowledge entries were skipped.';
    }

    $entryId=(string)($_GET['id']??'');
    $selectedEntry=null;

    foreach($entries as $entry){
        if(
            is_array($entry)
            && ($entry['id']??'')===$entryId
        ){
            $selectedEntry=$entry;
            break;
        }
    }

    $tableReady=static function(string $table): bool {
        $allowed=[
            'knowledge_assets',
            'knowledge_transcription_jobs'
        ];

        if(!in_array($table,$allowed,true)){
            return false;
        }

        try{
            db()->query(
                'SELECT 1 FROM '.$table.' LIMIT 1'
            );
            return true;
        }catch(Throwable){
            return false;
        }
    };

    $knowledgeAssetsReady=$tableReady('knowledge_assets');
    $transcriptionConfig=transcription_config();
    $transcriptionConfigured=(bool)(
        $transcriptionConfig['enabled']
        ?? false
    );
    $transcriptionTableReady=$tableReady(
        'knowledge_transcription_jobs'
    );

    if(!$knowledgeAssetsReady){
        $knowledgeWarnings[]=
            'The knowledge_assets database table is unavailable. '.
            'Confirm the Knowledge Center migration was imported.';
    }

    if(
        $transcriptionConfigured
        && !$transcriptionTableReady
    ){
        $knowledgeWarnings[]=
            'Automatic transcription is enabled, but its job table is unavailable. '.
            'Import the transcription migration or disable transcription in config.php.';
    }

    $assetId=query_int('asset');
    $assets=[];
    $selectedAsset=null;

    if($knowledgeAssetsReady){
        try{
            $transcriptionColumn=$transcriptionTableReady
                ? '(
                    SELECT j.status
                    FROM knowledge_transcription_jobs j
                    WHERE j.asset_id=ka.id
                    ORDER BY j.id DESC
                    LIMIT 1
                ) AS transcription_status'
                : 'NULL AS transcription_status';

            $assets=db()->query(
                'SELECT ka.*,
                        u.display_name AS uploaded_by_name,
                        '.$transcriptionColumn.'
                 FROM knowledge_assets ka
                 JOIN users u ON u.id=ka.uploaded_by
                 ORDER BY ka.updated_at DESC'
            )->fetchAll();
        }catch(Throwable $exception){
            $knowledgeWarnings[]=
                'Uploaded knowledge records could not be loaded: '.
                $exception->getMessage();
            $assets=[];
        }

        if($assetId>0){
            try{
                $assetStatement=db()->prepare(
                    'SELECT ka.*,
                            u.display_name AS uploaded_by_name
                     FROM knowledge_assets ka
                     JOIN users u ON u.id=ka.uploaded_by
                     WHERE ka.id=:id'
                );
                $assetStatement->execute(['id'=>$assetId]);
                $selectedAsset=$assetStatement->fetch()?:null;
            }catch(Throwable $exception){
                $knowledgeWarnings[]=
                    'The selected knowledge asset could not be loaded: '.
                    $exception->getMessage();
            }
        }
    }

    $assetAudiences=$selectedAsset
        ? json_decode(
            (string)($selectedAsset['audiences_json']??'[]'),
            true
        )
        : [];

    if(!is_array($assetAudiences)||!$assetAudiences){
        $assetAudiences=[
            'recruiter',
            'investor',
            'client'
        ];
    }

    $latestTranscription=null;

    if($selectedAsset&&$transcriptionTableReady){
        try{
            $latestTranscription=transcription_latest_job(
                (int)$selectedAsset['id']
            );
        }catch(Throwable $exception){
            $knowledgeWarnings[]=
                'Transcription status could not be loaded: '.
                $exception->getMessage();
        }
    }

    $transcriptionAvailable=
        $transcriptionConfigured
        && $transcriptionTableReady
        && transcription_enabled();

    $transcriptionMedia=
        $selectedAsset
        && $transcriptionConfigured
        && $transcriptionTableReady
        && transcription_supported_asset($selectedAsset);

    $defaultTranscriptionLanguage=trim(
        (string)($transcriptionConfig['language']??'')
    );
    $defaultTranscriptionPrompt=trim(
        (string)($transcriptionConfig['prompt']??'')
    );

    $ffmpegResolved=null;
    $workerTokenReady=false;

    if($transcriptionConfigured){
        try{
            $ffmpegResolved=transcription_resolve_binary(
                (string)(
                    $transcriptionConfig['ffmpeg_path']
                    ?? 'ffmpeg'
                )
            );
        }catch(Throwable){
            $ffmpegResolved=null;
        }

        $workerToken=trim(
            (string)(
                $transcriptionConfig['worker_token']
                ?? ''
            )
        );
        $workerTokenReady=
            $workerToken!==''
            && $workerToken!==
                'replace-with-a-long-random-transcription-worker-token';
    }

    $acceptedExtensions=implode(
        ', ',
        array_map(
            static fn(string $extension): string => '.'.$extension,
            array_keys(knowledge_allowed_extensions())
        )
    );

    $knowledgeSection=(string)($_GET['section']??'library');

    if($assetId>0){
        $knowledgeSection='media';
    }elseif($entryId!==''){
        $knowledgeSection='text';
    }

    if(!in_array(
        $knowledgeSection,
        ['library','add','text','media'],
        true
    )){
        $knowledgeSection='library';
    }

    $assetGroups=[];

    foreach($assets as $asset){
        $extension=strtolower(
            (string)($asset['extension']??'file')
        );
        $assetGroups[$extension][]=$asset;
    }

    uksort(
        $assetGroups,
        static function(string $left,string $right): int {
            $priority=[
                'mp3'=>10,
                'm4a'=>11,
                'wav'=>12,
                'ogg'=>13,
                'oga'=>14,
                'flac'=>15,
                'aac'=>16,
                'mp4'=>20,
                'm4v'=>21,
                'mov'=>22,
                'webm'=>23,
                'ogv'=>24,
                'jpg'=>30,
                'jpeg'=>31,
                'png'=>32,
                'gif'=>33,
                'webp'=>34,
                'bmp'=>35,
                'pdf'=>40,
            ];

            return ($priority[$left]??100)
                <=>($priority[$right]??100)
                ?:strcmp($left,$right);
        }
    );

    $selectedLibraryTab=strtolower(
        trim((string)($_GET['tab']??'text'))
    );

    if(
        $selectedLibraryTab!=='text'
        && !isset($assetGroups[$selectedLibraryTab])
    ){
        $selectedLibraryTab='text';
    }

    $visibleAssets=$selectedLibraryTab==='text'
        ?[]
        :($assetGroups[$selectedLibraryTab]??[]);

    $libraryBackUrl='?view=knowledge';

    if($selectedAsset){
        $assetExtension=strtolower(
            (string)($selectedAsset['extension']??'')
        );

        if(
            $assetExtension!==''
            && isset($assetGroups[$assetExtension])
        ){
            $libraryBackUrl=
                '?view=knowledge&tab='.
                rawurlencode($assetExtension);
        }
    }
?>
<?php if($knowledgeWarnings):?>
<section class="knowledge-repair-notice" role="status">
<strong>Knowledge Center loaded with repair notices</strong>
<?php foreach($knowledgeWarnings as $knowledgeWarning):?>
<p><?=e($knowledgeWarning)?></p>
<?php endforeach;?>
</section>
<?php endif;?>

<div class="knowledge-center-shell">

<?php if($knowledgeSection==='add'):?>
<header class="knowledge-library-header">
<div>
<span>Knowledge Center</span>
<h2>Add Media</h2>
<p>
Upload one media item, assign its display information,
and add the artwork used in the library.
</p>
</div>
<div class="page-actions">
<a class="button" href="?view=knowledge">Back to Library</a>
</div>
</header>

<div class="knowledge-add-layout">
<form method="post" enctype="multipart/form-data" class="form-panel knowledge-add-form">
<?=csrf_field()?>
<input type="hidden" name="action" value="upload_knowledge_asset">
<h3>Add media</h3>
<p>
Upload documents, images, audio, or video. A cover image is recommended for media cards and required for the best MP3 and video presentation.
</p>

<div class="form-grid">
<label class="field">
<span>Display title</span>
<input name="media_title" maxlength="190" placeholder="Uses the filename when blank">
</label>

<label class="field">
<span>Category</span>
<input name="media_category" maxlength="120" placeholder="Music, video, case study, document…">
</label>

<label class="field full knowledge-media-file-field">
<span>Media file</span>
<input
    type="file"
    name="knowledge_file"
    accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.odt,.rtf,.txt,.md,.csv,.json,.xml,.yaml,.yml,.log,.srt,.vtt,.epub,.html,.htm,.jpg,.jpeg,.png,.gif,.webp,.bmp,.mp3,.wav,.m4a,.aac,.ogg,.oga,.flac,.mp4,.m4v,.webm,.mov,.ogv"
    required
>
<small>Maximum <?=e(format_bytes(knowledge_upload_limit_bytes()))?>. Supported: <?=e($acceptedExtensions)?></small>
</label>

<label class="field full knowledge-cover-field">
<span>Cover image</span>
<input
    type="file"
    name="cover_image"
    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
>
<small>JPG, PNG, or WebP up to 8 MB. Use square artwork for MP3/audio and 9:16 portrait artwork for video/reels.</small>
</label>
</div>

<?php if($transcriptionConfigured):?>
<div class="transcription-diagnostics">
<div>
<span class="diagnostic-dot <?=$transcriptionAvailable?'ready':'missing'?>"></span>
<strong>Transcription service</strong>
<small><?=$transcriptionAvailable?'Ready':'Configuration or database migration incomplete'?></small>
</div>
<div>
<span class="diagnostic-dot <?=$ffmpegResolved?'ready':'missing'?>"></span>
<strong>FFmpeg</strong>
<small><?=$ffmpegResolved?'Available':'Required for long or converted media'?></small>
</div>
<div>
<span class="diagnostic-dot <?=$workerTokenReady?'ready':'missing'?>"></span>
<strong>Worker</strong>
<small><?=$workerTokenReady?'Token configured':'Set a private worker token'?></small>
</div>
</div>
<?php endif;?>

<div class="form-footer">
<button class="button button-primary" type="submit">Upload media</button>
<a class="button" href="?view=knowledge">Cancel</a>
</div>
</form>

<aside class="knowledge-cover-guide">
<span>Cover guidance</span>
<h3>Design for the media format</h3>
<div class="knowledge-cover-guide-grid">
<article class="square"><div>1:1</div><strong>MP3 / Audio</strong><small>Album artwork or a square brand image.</small></article>
<article class="reel"><div>9:16</div><strong>Video / Reels</strong><small>Portrait cover sized for reel presentation.</small></article>
<article class="document"><div>4:3</div><strong>Documents</strong><small>Optional report, project, or presentation cover.</small></article>
</div>
<p>Cover art is stored privately with the media and displayed through the protected Knowledge Center media endpoint.</p>
</aside>
</div>

<?php elseif(
    $knowledgeSection==='media'
    && $selectedAsset
):?>
<header class="knowledge-library-header knowledge-detail-header">
<div>
<span><?=e(strtoupper((string)$selectedAsset['extension']))?> Media</span>
<h2><?=e($selectedAsset['title'])?></h2>
<p>
Manage the media, cover image, searchable knowledge text,
transcription, and chat publication settings.
</p>
</div>
<div class="page-actions">
<a class="button" href="<?=e($libraryBackUrl)?>">Back to Library</a>
</div>
</header>

<section class="knowledge-detail-page">
<section class="panel">
<header class="panel-header">
<h2><?=e($selectedAsset['title'])?></h2>
<span class="status status-<?=e($selectedAsset['status']==='published'?'active':'planning')?>" style="margin-left:auto"><?=e(status_label($selectedAsset['status']))?></span>
</header>
<div class="panel-body">
<div class="knowledge-asset-meta">
<span><?=e(strtoupper($selectedAsset['extension']))?></span>
<span><?=e(status_label($selectedAsset['media_kind']))?></span>
<span><?=e(format_bytes((int)$selectedAsset['size_bytes']))?></span>
<span><?=e($selectedAsset['mime_type'])?></span>
<span>Uploaded by <?=e($selectedAsset['uploaded_by_name'])?></span>
</div>

<div class="knowledge-cover-manager">
<div class="knowledge-cover-current <?=e($selectedAsset['media_kind']==='video'?'reel':($selectedAsset['media_kind']==='audio'?'album':'document'))?>">
<?php if(!empty($selectedAsset['cover_stored_name'])):?>
<img
    src="<?=e(app_url('knowledge-media.php?id='.(int)$selectedAsset['id'].'&cover=1'))?>"
    alt="<?=e($selectedAsset['title'])?> cover"
>
<?php elseif($selectedAsset['media_kind']==='image'):?>
<img
    src="<?=e(app_url('knowledge-media.php?id='.(int)$selectedAsset['id']))?>"
    alt="<?=e($selectedAsset['title'])?>"
>
<?php else:?>
<div><span><?=e(strtoupper($selectedAsset['extension']))?></span><small>No cover image</small></div>
<?php endif;?>
</div>

<form method="post" enctype="multipart/form-data" class="knowledge-cover-replace-form">
<?=csrf_field()?>
<input type="hidden" name="action" value="replace_knowledge_cover">
<input type="hidden" name="asset_id" value="<?=(int)$selectedAsset['id']?>">
<label class="field">
<span><?=empty($selectedAsset['cover_stored_name'])?'Add cover image':'Replace cover image'?></span>
<input
    type="file"
    name="cover_image"
    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
    required
>
<small>
Use square art for audio/MP3 and 9:16 portrait art for videos.
</small>
</label>
<button class="button button-small" type="submit">Save cover</button>
</form>
</div>

<?php if($selectedAsset['extraction_error']):?>
<div class="alert alert-warning" style="margin-top:14px"><?=e($selectedAsset['extraction_error'])?></div>
<?php endif;?>

<?php if($selectedAsset['status']==='published'):?>
<div class="knowledge-admin-preview">
<?php if($selectedAsset['media_kind']==='image'):?>
<img src="<?=e(app_url('knowledge-media.php?id='.$selectedAsset['id']))?>" alt="<?=e($selectedAsset['title'])?>">
<?php elseif($selectedAsset['media_kind']==='audio'):?>
<audio controls preload="metadata" src="<?=e(app_url('knowledge-media.php?id='.$selectedAsset['id']))?>"></audio>
<?php elseif($selectedAsset['media_kind']==='video'):?>
<video controls preload="metadata" src="<?=e(app_url('knowledge-media.php?id='.$selectedAsset['id']))?>"></video>
<?php elseif($selectedAsset['extension']==='pdf'):?>
<iframe title="<?=e($selectedAsset['title'])?>" src="<?=e(app_url('knowledge-media.php?id='.$selectedAsset['id']))?>"></iframe>
<?php endif;?>
</div>
<div class="page-actions" style="margin:14px 0 0">
<a class="button button-small" href="<?=e(app_url('knowledge-media.php?id='.$selectedAsset['id']))?>" target="_blank" rel="noopener">Open media</a>
<a class="button button-small" href="<?=e(app_url('knowledge-media.php?id='.$selectedAsset['id'].'&download=1'))?>">Download</a>
</div>
<?php endif;?>
</div>
</section>

<?php if($transcriptionMedia):?>
<section class="panel transcription-panel">
<header class="panel-header">
<h2>Automatic transcription</h2>
<span
    class="status status-transcription-<?=e($latestTranscription['status']??'none')?>"
    style="margin-left:auto"
    <?php if($latestTranscription&&in_array($latestTranscription['status'],['queued','processing'],true)):?>
    data-transcription-status
    data-job-id="<?=(int)$latestTranscription['id']?>"
    data-current-status="<?=e($latestTranscription['status'])?>"
    <?php endif;?>
><?=e(status_label($latestTranscription['status']??'not queued'))?></span>
</header>
<div class="panel-body">
<?php if(!$transcriptionAvailable):?>
<div class="alert alert-warning">
Automatic transcription is not ready. Add the OpenAI API key to the server environment or <code>config.php</code>, then confirm the transcription worker configuration.
</div>
<?php endif;?>

<?php if($latestTranscription):?>
<div class="transcription-meta">
<span>Model: <?=e($latestTranscription['model'])?></span>
<span>Attempts: <?=(int)$latestTranscription['attempt_count']?> / <?=(int)$latestTranscription['max_attempts']?></span>
<?php if($latestTranscription['language']):?><span>Language: <?=e($latestTranscription['language'])?></span><?php endif;?>
<?php if((int)$latestTranscription['speaker_diarization']===1):?><span>Speaker labels</span><?php endif;?>
<?php if($latestTranscription['queued_at']):?><span>Queued <?=e(format_datetime($latestTranscription['queued_at']))?></span><?php endif;?>
<?php if($latestTranscription['completed_at']):?><span>Completed <?=e(format_datetime($latestTranscription['completed_at']))?></span><?php endif;?>
</div>
<?php endif;?>

<?php if($latestTranscription&&$latestTranscription['error_message']):?>
<div class="alert alert-warning" style="margin-top:12px">
<?=e($latestTranscription['error_message'])?>
</div>
<?php endif;?>

<?php if($latestTranscription&&$latestTranscription['status']==='queued'):?>
<p class="transcription-copy">
This media is waiting for the cron worker. The administrator can also process it immediately; long recordings may keep this request open for several minutes.
</p>
<div class="page-actions" style="margin:12px 0 0">
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="process_knowledge_transcription">
<input type="hidden" name="asset_id" value="<?=(int)$selectedAsset['id']?>">
<input type="hidden" name="job_id" value="<?=(int)$latestTranscription['id']?>">
<button class="button button-primary" type="submit">Process now</button>
</form>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="cancel_knowledge_transcription">
<input type="hidden" name="asset_id" value="<?=(int)$selectedAsset['id']?>">
<input type="hidden" name="job_id" value="<?=(int)$latestTranscription['id']?>">
<button class="button" type="submit">Cancel</button>
</form>
<a class="button" href="?view=knowledge&section=media&asset=<?=(int)$selectedAsset['id']?>">Refresh status</a>
</div>

<?php elseif($latestTranscription&&$latestTranscription['status']==='processing'):?>
<div class="transcription-processing" aria-live="polite">
<span class="transcription-spinner" aria-hidden="true"></span>
<div>
<strong>Transcription is processing</strong>
<p>The worker is preparing the media, sending the audio, and assembling the transcript.</p>
</div>
</div>
<div class="page-actions" style="margin:12px 0 0">
<a class="button button-primary" href="?view=knowledge&section=media&asset=<?=(int)$selectedAsset['id']?>">Refresh status</a>
</div>

<?php elseif($latestTranscription&&$latestTranscription['status']==='review'):?>
<p class="transcription-copy">
The automatic transcript is ready. Correct names, punctuation, speaker labels, and technical terms before approving it.
</p>

<details class="transcription-raw">
<summary>View raw transcript</summary>
<pre><?=e($latestTranscription['raw_transcript_text']??'')?></pre>
</details>

<form method="post" class="transcription-review-form">
<?=csrf_field()?>
<input type="hidden" name="asset_id" value="<?=(int)$selectedAsset['id']?>">
<input type="hidden" name="job_id" value="<?=(int)$latestTranscription['id']?>">
<label class="field">
<span>Reviewed transcript</span>
<textarea name="reviewed_transcript" style="min-height:420px" required><?=e($latestTranscription['reviewed_transcript_text']?:$selectedAsset['extracted_text']?:$latestTranscription['raw_transcript_text'])?></textarea>
<small>Approval publishes this transcript as searchable knowledge and keeps the audio/video player attached to matching chat responses.</small>
</label>
<div class="form-footer">
<button class="button" type="submit" name="action" value="save_transcription_review">Save review</button>
<button class="button button-primary" type="submit" name="action" value="approve_transcription_publish">Approve and publish to chat</button>
</div>
</form>

<?php elseif($latestTranscription&&$latestTranscription['status']==='approved'):?>
<div class="alert alert-success">
The transcript was reviewed and approved. This media is available as a searchable chat source.
<?php if($latestTranscription['reviewed_by_name']):?>
 Reviewed by <?=e($latestTranscription['reviewed_by_name'])?>.
<?php endif;?>
</div>
<?php endif;?>

<?php if(
    !$latestTranscription
    || in_array($latestTranscription['status'],['failed','cancelled','approved'],true)
):?>
<form method="post" class="transcription-queue-form">
<?=csrf_field()?>
<input type="hidden" name="action" value="queue_knowledge_transcription">
<input type="hidden" name="asset_id" value="<?=(int)$selectedAsset['id']?>">
<h3><?=e($latestTranscription?'Transcribe again':'Queue automatic transcription')?></h3>
<div class="form-grid">
<label class="field">
<span>Language</span>
<input
    name="transcription_language"
    value="<?=e($defaultTranscriptionLanguage)?>"
    maxlength="20"
    placeholder="en"
>
<small>ISO-639-1 code. Leave blank for automatic detection.</small>
</label>
<label class="field">
<span>Speaker handling</span>
<label class="checkbox-row">
<input type="checkbox" name="speaker_diarization" value="1">
<span>Identify and label different speakers</span>
</label>
</label>
<label class="field full">
<span>Vocabulary guidance</span>
<textarea name="transcription_prompt"><?=e($defaultTranscriptionPrompt)?></textarea>
<small>Names, brands, products, and technical vocabulary expected in the recording.</small>
</label>
</div>
<div class="form-footer">
<button class="button button-primary" type="submit" <?=$transcriptionAvailable?'':'disabled'?>>Queue transcription</button>
</div>
</form>
<?php endif;?>
</div>
</section>
<?php endif;?>

<form method="post" class="form-panel">
<?=csrf_field()?>
<input type="hidden" name="asset_id" value="<?=(int)$selectedAsset['id']?>">
<h2 style="margin-top:0;font-size:1rem">Knowledge and chat settings</h2>
<div class="form-grid">
<label class="field">
<span>Title</span>
<input name="title" value="<?=e($selectedAsset['title'])?>" required>
</label>
<label class="field">
<span>Category</span>
<input name="category" value="<?=e($selectedAsset['category'])?>" required>
</label>
<fieldset class="field full knowledge-audience-field">
<span>Chat audiences</span>
<div>
<?php foreach(['recruiter','investor','client'] as $audience):?>
<label><input type="checkbox" name="audiences[]" value="<?=e($audience)?>" <?=in_array($audience,$assetAudiences,true)?'checked':''?>> <?=e(ucfirst($audience))?></label>
<?php endforeach;?>
</div>
</fieldset>
<label class="field full">
<span>Summary shown in chat</span>
<textarea name="summary"><?=e($selectedAsset['summary']??'')?></textarea>
</label>
<label class="field full">
<span>Keywords</span>
<textarea name="keywords"><?=e($selectedAsset['keywords']??'')?></textarea>
<small>Separate keywords with commas or line breaks.</small>
</label>
<label class="field full">
<span>Knowledge text / transcript</span>
<textarea name="extracted_text" style="min-height:360px"><?=e($selectedAsset['extracted_text']??'')?></textarea>
<small>This content becomes searchable and supplies the assistant answer. For MP3, MP4, images, or scanned PDFs, enter a transcript, detailed description, or approved source notes.</small>
</label>
</div>
<div class="form-footer">
<button class="button" type="submit" name="action" value="save_knowledge_asset">Save draft</button>
<button class="button button-primary" type="submit" name="action" value="publish_knowledge_asset">Publish to chat</button>
</div>
</form>

<div class="page-actions">
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="reextract_knowledge_asset">
<input type="hidden" name="asset_id" value="<?=(int)$selectedAsset['id']?>">
<button class="button button-small" type="submit">Extract again</button>
</form>

<?php if($selectedAsset['status']==='published'):?>
<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="unpublish_knowledge_asset">
<input type="hidden" name="asset_id" value="<?=(int)$selectedAsset['id']?>">
<button class="button button-small" type="submit">Remove from chat</button>
</form>
<?php endif;?>

<form method="post">
<?=csrf_field()?>
<input type="hidden" name="action" value="delete_knowledge_asset">
<input type="hidden" name="asset_id" value="<?=(int)$selectedAsset['id']?>">
<button class="button button-small button-danger" type="submit" data-confirm="Delete this file and its chat knowledge entry?">Delete asset</button>
</form>
</div>
</section>

<?php elseif(
    $knowledgeSection==='text'
    && $selectedEntry
):?>
<header class="knowledge-library-header knowledge-detail-header">
<div>
<span>Text Knowledge</span>
<h2><?=e($selectedEntry['title'])?></h2>
<p>
Edit the assistant answer, summary, audiences, category,
and searchable keywords for this text entry.
</p>
</div>
<div class="page-actions">
<a class="button" href="?view=knowledge&tab=text">Back to Library</a>
</div>
</header>

<section class="knowledge-detail-page">
<form method="post" class="form-panel">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_knowledge">
<input type="hidden" name="entry_id" value="<?=e($selectedEntry['id'])?>">
<h2 style="margin-top:0;font-size:1rem">Edit manual knowledge entry</h2>
<div class="form-grid knowledge-entry-form-grid">
<label class="field full">
<span>Title</span>
<input
    name="title"
    value="<?=e($selectedEntry['title']??'')?>"
    required
>
</label>

<label class="field full knowledge-category-field">
<span>Category</span>
<input
    name="category"
    value="<?=e($selectedEntry['category']??'')?>"
    required
>
</label>

<fieldset class="field full knowledge-audience-row">
<legend>Audiences</legend>
<div>
<?php foreach(['recruiter','investor','client'] as $audience):?>
<label>
<input
    type="checkbox"
    name="audiences[]"
    value="<?=e($audience)?>"
    <?=in_array(
        $audience,
        $selectedEntry['audiences']??[],
        true
    )?'checked':''?>
>
<span><?=e(ucfirst($audience))?></span>
</label>
<?php endforeach;?>
</div>
</fieldset>

<label class="field full"><span>Summary</span><textarea name="summary"><?=e($selectedEntry['summary']??'')?></textarea></label>
<label class="field full"><span>Answer</span><textarea name="answer" style="min-height:300px"><?=e($selectedEntry['answer']??'')?></textarea></label>
<label class="field full"><span>Keywords</span><textarea name="keywords"><?=e(implode("
",$selectedEntry['keywords']??[]))?></textarea></label>
</div>
<div class="form-footer"><button class="button button-primary">Save entry</button></div>
</form>
</section>

<?php else:?>
<header class="knowledge-library-header">
<div>
<span>Knowledge Center</span>
<h2>Library</h2>
<p>
Text knowledge is the default library. Media tabs appear
only after that exact file type has been uploaded.
</p>
</div>
<div class="page-actions">
<a
    class="button button-primary"
    href="?view=knowledge&section=add"
>Add Media</a>
</div>
</header>

<nav
    class="knowledge-media-tabs knowledge-library-tabs"
    aria-label="Knowledge library types"
>
<a
    class="<?=$selectedLibraryTab==='text'?'active':''?>"
    href="?view=knowledge&tab=text"
>
<span>Text</span>
<small><?=count($entries)?></small>
</a>

<?php foreach($assetGroups as $extension=>$groupAssets):?>
<a
    class="<?=$selectedLibraryTab===$extension?'active':''?>"
    href="?view=knowledge&tab=<?=rawurlencode($extension)?>"
>
<span><?=e(strtoupper($extension))?></span>
<small><?=count($groupAssets)?></small>
</a>
<?php endforeach;?>
</nav>

<section class="panel knowledge-library-panel-full">
<?php if($selectedLibraryTab==='text'):?>
<header class="panel-header">
<div>
<span>Assistant knowledge</span>
<h2>Text Content</h2>
</div>
<span class="knowledge-library-count">
<?=count($entries)?> entr<?=count($entries)===1?'y':'ies'?>
</span>
</header>

<?php if(!$entries):?>
<div class="empty-state">
No text knowledge entries are available.
</div>
<?php else:?>
<div class="knowledge-manual-grid knowledge-manual-grid-full">
<?php foreach($entries as $entry):?>
<a
    href="?view=knowledge&section=text&id=<?=rawurlencode((string)$entry['id'])?>"
>
<strong><?=e($entry['title'])?></strong>
<small><?=e($entry['category']??'General')?></small>
<span>Open content</span>
</a>
<?php endforeach;?>
</div>
<?php endif;?>

<?php else:?>
<header class="panel-header">
<div>
<span>Uploaded media</span>
<h2><?=e(strtoupper($selectedLibraryTab))?> Library</h2>
</div>
<span class="knowledge-library-count">
<?=count($visibleAssets)?> item<?=count($visibleAssets)===1?'':'s'?>
</span>
</header>

<?php if(!$visibleAssets):?>
<div class="knowledge-library-empty">
<strong>No <?=e(strtoupper($selectedLibraryTab))?> media uploaded</strong>
<p>Add a media file to populate this tab.</p>
<a
    class="button button-primary"
    href="?view=knowledge&section=add"
>Add Media</a>
</div>
<?php else:?>
<div class="knowledge-media-grid knowledge-media-grid-full">
<?php foreach($visibleAssets as $asset):?>
<?php
$assetIdValue=(int)$asset['id'];
$extension=strtolower((string)$asset['extension']);
$mediaKind=(string)$asset['media_kind'];
$isAudio=$mediaKind==='audio';
$isVideo=$mediaKind==='video';
$isImage=$mediaKind==='image';
$coverUrl=!empty($asset['cover_stored_name'])
    ?app_url(
        'knowledge-media.php?id='.
        $assetIdValue.
        '&cover=1'
    )
    :null;
$mediaUrl=app_url(
    'knowledge-media.php?id='.$assetIdValue
);
$detailUrl=
    '?view=knowledge&section=media&asset='.
    $assetIdValue;
$cardClass=$isVideo
    ?'reel'
    :(
        $isAudio
            ?'album'
            :($isImage?'image':'document')
    );
?>
<a
    class="knowledge-media-card <?=$cardClass?>"
    href="<?=e($detailUrl)?>"
>
<div class="knowledge-media-art">
<?php if($coverUrl):?>
<img
    src="<?=e($coverUrl)?>"
    alt="<?=e($asset['title'])?> cover"
>
<?php elseif($isVideo):?>
<video
    muted
    playsinline
    preload="metadata"
    src="<?=e($mediaUrl)?>"
></video>
<?php elseif($isImage):?>
<img
    src="<?=e($mediaUrl)?>"
    alt="<?=e($asset['title'])?>"
>
<?php else:?>
<div class="knowledge-media-placeholder">
<span><?=e(strtoupper($extension))?></span>
<strong><?=e(
    $isAudio
        ?'Album'
        :status_label($mediaKind)
)?></strong>
</div>
<?php endif;?>
<span class="knowledge-media-state <?=e(
    $asset['status']==='published'
        ?'published'
        :'draft'
)?>">
<?=e(status_label($asset['status']))?>
</span>
</div>

<div class="knowledge-media-card-copy">
<span>
<?=e(strtoupper($extension))?>
· <?=e($asset['category'])?>
</span>
<h3><?=e($asset['title'])?></h3>
<small>
<?=e(format_bytes((int)$asset['size_bytes']))?>
· Updated <?=e(format_datetime($asset['updated_at']))?>
</small>
</div>

<span class="knowledge-card-open">Open content</span>
</a>
<?php endforeach;?>
</div>
<?php endif;?>
<?php endif;?>
</section>
<?php endif;?>

</div>
<?php
}

if($view==='settings'){
$moduleDefinitions=nmm_module_definitions();
$logoUrl=nmm_site_logo_url();
?>
<form method="post" enctype="multipart/form-data" class="site-settings-form">
<?=csrf_field()?>
<input type="hidden" name="action" value="save_settings">

<section class="form-panel site-settings-panel">
<header class="site-settings-heading">
<div><span>Public visibility</span><h2>Module controls</h2><p>Turn public modules on or off without removing their data or administrator tools.</p></div>
<a class="button" href="<?=e(app_url('index.php'))?>" target="_blank" rel="noopener">Open public site</a>
</header>
<div class="module-control-grid">
<?php foreach($moduleDefinitions as $moduleKey=>$module):?>
<label class="module-control-card">
<input type="checkbox" name="module_<?=e($moduleKey)?>_enabled" value="1" <?=nmm_module_enabled($moduleKey)?'checked':''?>>
<span class="module-control-switch" aria-hidden="true"><i></i></span>
<span><strong><?=e($module['label'])?></strong><small><?=e($module['description'])?></small></span>
</label>
<?php endforeach;?>
</div>
</section>

<section class="form-panel site-settings-panel">
<header class="site-settings-heading"><div><span>Brand identity</span><h2>Site name and logo</h2><p>The uploaded logo replaces the packaged North Mountain Media logo in public and portal navigation.</p></div></header>
<div class="site-branding-grid">
<div class="site-logo-preview"><?php if($logoUrl!==''):?><img src="<?=e($logoUrl)?>" alt="<?=e(nmm_site_logo_alt())?>"><?php endif;?></div>
<div class="form-grid">
<label class="field"><span>Site name</span><input name="site_name" value="<?=e(setting('site_name','North Mountain Media'))?>" required maxlength="190"></label>
<label class="field"><span>Logo alternative text</span><input name="site_logo_alt" value="<?=e(nmm_site_setting('site_logo_alt','North Mountain Media'))?>" maxlength="190"></label>
<label class="field full"><span>Upload logo</span><input type="file" name="site_logo" accept="image/jpeg,image/png,image/webp,image/gif"><small>JPG, PNG, WebP, or GIF. Maximum 8 MB. Transparent PNG or WebP is recommended.</small></label>
<?php if(nmm_site_setting('site_logo_stored_name')!==''):?><label class="checkbox-row full"><input type="checkbox" name="remove_site_logo" value="1"><span>Remove the uploaded logo and restore the packaged default.</span></label><?php endif;?>
<label class="field full"><span>Mobile header branding</span><select name="mobile_header_logo_mode"><option value="logo" <?=nmm_site_logo_mode()==='logo'?'selected':''?>>Display uploaded logo</option><option value="name" <?=nmm_site_logo_mode()==='name'?'selected':''?>>Display site name as text</option><option value="hidden" <?=nmm_site_logo_mode()==='hidden'?'selected':''?>>Hide branding; show menu and account actions only</option></select><small>This controls the compact public header shown on phones and tablets.</small></label>
<label class="field full"><span>Client portal welcome</span><textarea name="portal_welcome" rows="3"><?=e(setting('portal_welcome','Project updates, secure communications, voice notes, calls, and shared files in one place.'))?></textarea></label>
<label class="field full"><span>Public site URL</span><input type="url" name="seo_site_url" value="<?=e(nmm_site_setting('seo_site_url'))?>" placeholder="https://northmountainmedia.com"><small>Used as the global canonical base. Page title, description, social image, and indexing are managed inside the Page Editor.</small></label>
</div>
</div>
</section>

<section class="form-panel site-settings-panel microgifter-settings-panel">
<header class="site-settings-heading"><div><span>Connected commerce</span><h2>Microgifter integration</h2><p>Prepare landing-page conversion blocks for the Microgifter API, existing MCP server, or HomeServer/MCP. Live transactions remain disabled until explicitly enabled.</p></div><button class="button" type="button" data-microgifter-test>Test connection</button></header>
<div class="form-grid">
<label class="field"><span>Connection mode</span><select name="microgifter_connection_mode"><option value="disabled" <?=nmm_site_setting('microgifter_connection_mode','disabled')==='disabled'?'selected':''?>>Disabled</option><option value="demo" <?=nmm_site_setting('microgifter_connection_mode')==='demo'?'selected':''?>>Local demonstration</option><option value="api" <?=nmm_site_setting('microgifter_connection_mode')==='api'?'selected':''?>>Microgifter REST API</option><option value="mcp" <?=nmm_site_setting('microgifter_connection_mode')==='mcp'?'selected':''?>>Microgifter MCP server</option><option value="homeserver" <?=nmm_site_setting('microgifter_connection_mode')==='homeserver'?'selected':''?>>Microgifter HomeServer/MCP</option></select></label>
<label class="field"><span>Merchant/account ID</span><input name="microgifter_merchant_id" value="<?=e(nmm_site_setting('microgifter_merchant_id'))?>" maxlength="190"></label>
<label class="field full"><span>API or MCP endpoint</span><input type="url" name="microgifter_endpoint" value="<?=e(nmm_site_setting('microgifter_endpoint'))?>" placeholder="https://microgifter.example/api or /mcp"></label>
<label class="field"><span>Authentication token</span><input type="password" name="microgifter_token" autocomplete="new-password" placeholder="Leave blank to preserve the encrypted token"><small><?=nmm_site_setting('microgifter_token_encrypted')!==''?'An encrypted credential is stored.':'No credential is stored.'?></small></label>
<label class="field"><span>Cache duration (minutes)</span><input type="number" name="microgifter_cache_minutes" min="1" max="1440" value="<?=e(nmm_site_setting('microgifter_cache_minutes','15'))?>"></label>
<label class="field"><span>Request timeout (seconds)</span><input type="number" name="microgifter_timeout_seconds" min="2" max="30" value="<?=e(nmm_site_setting('microgifter_timeout_seconds','8'))?>"></label>
<label class="checkbox-row"><input type="checkbox" name="remove_microgifter_token" value="1"><span>Remove the stored credential.</span></label>
<label class="checkbox-row full"><input type="checkbox" name="microgifter_live_transactions_enabled" value="1" <?=nmm_setting_bool('microgifter_live_transactions_enabled',false)?'checked':''?>><span>Allow live gift, reward, campaign, or claim transactions. Keep disabled until the connector contract is verified.</span></label>
<label class="checkbox-row"><input type="checkbox" name="microgifter_contact_sync_enabled" value="1" <?=nmm_setting_bool('microgifter_contact_sync_enabled',false)?'checked':''?>><span>Synchronize contacts.</span></label>
<label class="checkbox-row"><input type="checkbox" name="microgifter_analytics_sync_enabled" value="1" <?=nmm_setting_bool('microgifter_analytics_sync_enabled',false)?'checked':''?>><span>Synchronize conversion analytics.</span></label>
</div><p class="microgifter-test-result" data-microgifter-test-result></p>
</section>

<div class="site-settings-savebar"><span>Module, branding, and connection changes become active after saving.</span><button class="button button-primary">Save site settings</button></div>
</form>
<script>
(() => {
  const button=document.querySelector('[data-microgifter-test]');
  const result=document.querySelector('[data-microgifter-test-result]');
  button?.addEventListener('click',async()=>{button.disabled=true;if(result)result.textContent='Testing connection…';try{const response=await fetch('<?=e(app_url('portal/microgifter-test.php'))?>',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':'<?=e(csrf_token())?>','Accept':'application/json'}});const data=await response.json();if(result)result.textContent=data.message||(data.ok?'Connection succeeded.':'Connection failed.');}catch(error){if(result)result.textContent='Connection test failed.';}finally{button.disabled=false;}});
})();
</script>
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
<p>This information powers the logged-in account menu, public contact details, sidebar profile, and Call Us page.</p>
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
