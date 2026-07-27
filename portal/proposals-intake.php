<?php
declare(strict_types=1);

/* North Mountain Media build: 20260727-site-controls-landing-v60 */

function proposals_schema_available(): bool
{
    static $available = null;
    if ($available !== null) return $available;
    if (!function_exists('db')) return false;
    try {
        $s = db()->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ("intake_templates","intake_questions","project_intakes","project_intake_answers","proposal_templates","proposals","proposal_line_items","proposal_revisions","proposal_audit_events","proposal_reminders")');
        $available = (int)$s->fetchColumn() === 10;
    } catch (Throwable) {
        $available = false;
    }
    return $available;
}

function proposals_settings(): array
{
    return [
        'intake_public_enabled' => setting('intake_public_enabled','1') !== '0',
        'intake_default_template_slug' => trim((string)setting('intake_default_template_slug','project-intake')) ?: 'project-intake',
        'company_name' => trim((string)setting('proposals_company_name','North Mountain Media')) ?: 'North Mountain Media',
        'company_location' => trim((string)setting('proposals_company_location','Phoenix, Arizona')),
        'default_valid_days' => max(1,min(365,(int)setting('proposals_default_valid_days','14'))),
        'default_tax_percent' => max(0,min(100,(float)setting('proposals_default_tax_percent','0'))),
        'default_deposit_percent' => max(0,min(100,(float)setting('proposals_default_deposit_percent','50'))),
        'follow_up_days' => max(1,min(90,(int)setting('proposals_follow_up_days','3'))),
        'pdf_footer' => trim((string)setting('proposals_pdf_footer','Prepared by North Mountain Media')),
        'acceptance_statement' => trim((string)setting('proposals_acceptance_statement','I approve this proposal, estimate, scope, and terms.')) ?: 'I approve this proposal, estimate, scope, and terms.',
    ];
}

function proposals_save_setting(string $key,string $value): void
{
    db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(:k,:v) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute(['k'=>$key,'v'=>$value]);
}

function proposal_statuses(): array
{
    return ['draft'=>'Draft','sent'=>'Sent','viewed'=>'Viewed','accepted'=>'Accepted','declined'=>'Declined','expired'=>'Expired','converted'=>'Converted','archived'=>'Archived'];
}

function intake_statuses(): array
{
    return ['started'=>'Started','submitted'=>'Submitted','reviewed'=>'Reviewed','converted'=>'Converted','archived'=>'Archived'];
}

function intake_field_types(): array
{
    return ['short_text'=>'Short text','long_text'=>'Long text','email'=>'Email','phone'=>'Phone','number'=>'Number','date'=>'Date','select'=>'Select','checkbox'=>'Checkbox'];
}

function proposal_money(int|float $cents,string $currency='USD'): string
{
    $amount=((float)$cents)/100;
    return strtoupper($currency)==='USD' ? '$'.number_format($amount,2) : strtoupper($currency).' '.number_format($amount,2);
}

function proposal_amount_to_cents(mixed $value): int
{
    $number=preg_replace('/[^0-9.\-]/','',(string)$value) ?? '0';
    return (int)round(((float)$number)*100);
}

function proposal_line_total_cents(array $item): int
{
    $gross=max(0,(float)($item['quantity']??0))*(int)($item['unit_amount_cents']??0);
    $discount=max(0,min(100,(float)($item['discount_percent']??0)));
    return (int)round($gross*(1-$discount/100));
}

function proposal_calculate_totals(array $items,int $discount,float $tax,float $deposit): array
{
    $subtotal=0;$taxable=0;
    foreach($items as $item){$line=proposal_line_total_cents($item);$subtotal+=$line;if(!empty($item['taxable']))$taxable+=$line;}
    $discount=max(0,min($subtotal,$discount));
    $ratio=$subtotal>0?$discount/$subtotal:0;
    $taxCents=(int)round(($taxable*(1-$ratio))*(max(0,min(100,$tax))/100));
    $total=max(0,$subtotal-$discount+$taxCents);
    return ['subtotal_cents'=>$subtotal,'discount_cents'=>$discount,'tax_cents'=>$taxCents,'total_cents'=>$total,'deposit_amount_cents'=>(int)round($total*(max(0,min(100,$deposit))/100))];
}

function intake_templates(bool $activeOnly=false): array
{
    if(!proposals_schema_available())return [];
    $sql='SELECT t.*,b.name AS booking_type_name FROM intake_templates t LEFT JOIN booking_types b ON b.id=t.booking_type_id';
    if($activeOnly)$sql.=' WHERE t.status="active"';
    $sql.=' ORDER BY t.sort_order,t.name,t.id';
    return db()->query($sql)->fetchAll();
}

function intake_template_by_id(int $id): ?array
{
    if($id<=0||!proposals_schema_available())return null;
    $s=db()->prepare('SELECT t.*,b.name AS booking_type_name FROM intake_templates t LEFT JOIN booking_types b ON b.id=t.booking_type_id WHERE t.id=:id LIMIT 1');$s->execute(['id'=>$id]);return $s->fetch()?:null;
}

function intake_template_by_slug(string $slug,bool $activeOnly=true): ?array
{
    if(trim($slug)===''||!proposals_schema_available())return null;
    $sql='SELECT t.*,b.name AS booking_type_name FROM intake_templates t LEFT JOIN booking_types b ON b.id=t.booking_type_id WHERE t.slug=:slug';
    if($activeOnly)$sql.=' AND t.status="active"';
    $s=db()->prepare($sql.' LIMIT 1');$s->execute(['slug'=>trim($slug)]);return $s->fetch()?:null;
}

function intake_template_for_booking_type(int $bookingTypeId): ?array
{
    if($bookingTypeId<=0||!proposals_schema_available())return null;
    $s=db()->prepare('SELECT t.*,b.name AS booking_type_name FROM intake_templates t LEFT JOIN booking_types b ON b.id=t.booking_type_id WHERE t.status="active" AND (t.booking_type_id=:id OR t.booking_type_id IS NULL) ORDER BY t.booking_type_id IS NULL,t.sort_order,t.id LIMIT 1');$s->execute(['id'=>$bookingTypeId]);return $s->fetch()?:null;
}

function intake_questions(int $templateId): array
{
    if($templateId<=0||!proposals_schema_available())return [];
    $s=db()->prepare('SELECT * FROM intake_questions WHERE template_id=:id ORDER BY sort_order,id');$s->execute(['id'=>$templateId]);return $s->fetchAll();
}

function intake_question_options(array $question): array
{
    $items=json_decode((string)($question['options_json']??''),true);
    return is_array($items)?array_values(array_filter(array_map(static fn($v)=>trim((string)$v),$items))):[];
}

function intake_answers(int $intakeId): array
{
    $s=db()->prepare('SELECT a.*,q.question_key,q.label,q.help_text,q.field_type,q.options_json,q.required,q.sort_order FROM project_intake_answers a JOIN intake_questions q ON q.id=a.question_id WHERE a.intake_id=:id ORDER BY q.sort_order,q.id');$s->execute(['id'=>$intakeId]);return $s->fetchAll();
}

function intake_by_id(int $id): ?array
{
    if($id<=0||!proposals_schema_available())return null;
    $s=db()->prepare('SELECT i.*,t.name AS template_name,t.title AS template_title,t.introduction,t.completion_message,c.display_name AS contact_name,c.email AS contact_email,o.title AS opportunity_title,p.proposal_number,p.title AS proposal_title FROM project_intakes i JOIN intake_templates t ON t.id=i.template_id LEFT JOIN crm_contacts c ON c.id=i.crm_contact_id LEFT JOIN crm_opportunities o ON o.id=i.crm_opportunity_id LEFT JOIN proposals p ON p.id=i.converted_proposal_id WHERE i.id=:id LIMIT 1');$s->execute(['id'=>$id]);$row=$s->fetch();if(!$row)return null;$row['answers']=intake_answers($id);return $row;
}

function intake_by_token(string $token): ?array
{
    $token=strtolower(trim($token));if(!preg_match('/^[a-f0-9]{64}$/',$token)||!proposals_schema_available())return null;
    $s=db()->prepare('SELECT id FROM project_intakes WHERE secure_token=:t LIMIT 1');$s->execute(['t'=>$token]);$id=(int)($s->fetchColumn()?:0);return $id?intake_by_id($id):null;
}

function intakes(array $filters=[]): array
{
    if(!proposals_schema_available())return [];
    $where=[];$params=[];
    if(!empty($filters['status'])){$where[]='i.status=:status';$params['status']=$filters['status'];}
    $limit=max(1,min(1000,(int)($filters['limit']??250)));
    $sql='SELECT i.*,t.name AS template_name,c.display_name AS contact_name,c.email AS contact_email,o.title AS opportunity_title,p.proposal_number FROM project_intakes i JOIN intake_templates t ON t.id=i.template_id LEFT JOIN crm_contacts c ON c.id=i.crm_contact_id LEFT JOIN crm_opportunities o ON o.id=i.crm_opportunity_id LEFT JOIN proposals p ON p.id=i.converted_proposal_id';
    if($where)$sql.=' WHERE '.implode(' AND ',$where);
    $sql.=' ORDER BY FIELD(i.status,"submitted","started","reviewed","converted","archived"),i.updated_at DESC,i.id DESC LIMIT '.$limit;
    $s=db()->prepare($sql);$s->execute($params);return $s->fetchAll();
}

function proposal_templates(bool $activeOnly=false): array
{
    if(!proposals_schema_available())return [];$sql='SELECT * FROM proposal_templates';if($activeOnly)$sql.=' WHERE status="active"';return db()->query($sql.' ORDER BY sort_order,name,id')->fetchAll();
}

function proposal_template_by_id(int $id): ?array
{
    if($id<=0||!proposals_schema_available())return null;$s=db()->prepare('SELECT * FROM proposal_templates WHERE id=:id LIMIT 1');$s->execute(['id'=>$id]);return $s->fetch()?:null;
}

function proposal_template_payload(array $template): array
{
    $value=json_decode((string)($template['payload_json']??''),true);return is_array($value)?$value:[];
}

function proposal_contacts(): array
{
    if(!proposals_schema_available())return [];
    return db()->query('SELECT * FROM crm_contacts ORDER BY updated_at DESC,display_name')->fetchAll();
}

function proposal_opportunities(): array
{
    if(!proposals_schema_available())return [];
    return db()->query('SELECT o.*,c.display_name AS contact_name,c.company,c.email FROM crm_opportunities o JOIN crm_contacts c ON c.id=o.contact_id ORDER BY FIELD(o.stage,"proposal","qualified","contacted","reviewing","new","won","lost"),o.updated_at DESC')->fetchAll();
}

function proposal_line_items(int $id): array
{
    $s=db()->prepare('SELECT * FROM proposal_line_items WHERE proposal_id=:id ORDER BY sort_order,id');$s->execute(['id'=>$id]);return $s->fetchAll();
}

function proposal_revisions(int $id): array
{
    $s=db()->prepare('SELECT r.*,u.display_name AS created_by_name FROM proposal_revisions r LEFT JOIN users u ON u.id=r.created_by WHERE r.proposal_id=:id ORDER BY r.revision_number DESC,r.id DESC');$s->execute(['id'=>$id]);return $s->fetchAll();
}

function proposal_audit_events(int $id): array
{
    $s=db()->prepare('SELECT a.*,u.display_name AS actor_user_name FROM proposal_audit_events a LEFT JOIN users u ON u.id=a.actor_user_id WHERE a.proposal_id=:id ORDER BY a.created_at DESC,a.id DESC LIMIT 250');$s->execute(['id'=>$id]);return $s->fetchAll();
}

function proposal_reminders(?int $proposalId=null): array
{
    if(!proposals_schema_available())return [];
    $sql='SELECT r.*,p.proposal_number,p.title,p.status AS proposal_status,c.display_name AS contact_name,c.email AS contact_email FROM proposal_reminders r JOIN proposals p ON p.id=r.proposal_id JOIN crm_contacts c ON c.id=p.crm_contact_id';$params=[];
    if($proposalId){$sql.=' WHERE r.proposal_id=:id';$params['id']=$proposalId;}
    $sql.=' ORDER BY FIELD(r.status,"ready","pending","failed","sent","cancelled"),r.scheduled_for,r.id';$s=db()->prepare($sql);$s->execute($params);return $s->fetchAll();
}

function proposal_raw_by_id(int $id): ?array
{
    if($id<=0||!proposals_schema_available())return null;
    $s=db()->prepare('SELECT p.*,c.display_name AS contact_name,c.email AS contact_email,c.phone AS contact_phone,c.company AS contact_company,c.client_user_id,o.title AS opportunity_title,i.project_title AS intake_project_title,pr.title AS converted_project_title FROM proposals p JOIN crm_contacts c ON c.id=p.crm_contact_id LEFT JOIN crm_opportunities o ON o.id=p.crm_opportunity_id LEFT JOIN project_intakes i ON i.id=p.intake_id LEFT JOIN projects pr ON pr.id=p.converted_project_id WHERE p.id=:id LIMIT 1');$s->execute(['id'=>$id]);return $s->fetch()?:null;
}

function proposal_expire_if_needed(array $proposal): array
{
    if(in_array($proposal['status'],['sent','viewed'],true)&&!empty($proposal['valid_until'])&&$proposal['valid_until']<gmdate('Y-m-d')){
        db()->prepare('UPDATE proposals SET status="expired" WHERE id=:id AND status IN("sent","viewed")')->execute(['id'=>$proposal['id']]);$proposal['status']='expired';proposal_audit((int)$proposal['id'],'expired');
    }
    return $proposal;
}

function proposal_by_id(int $id): ?array
{
    $row=proposal_raw_by_id($id);if(!$row)return null;$row=proposal_expire_if_needed($row);$row['line_items']=proposal_line_items($id);$row['revisions']=proposal_revisions($id);$row['audit_events']=proposal_audit_events($id);$row['reminders']=proposal_reminders($id);return $row;
}

function proposal_by_token(string $token): ?array
{
    $token=strtolower(trim($token));if(!preg_match('/^[a-f0-9]{64}$/',$token)||!proposals_schema_available())return null;$s=db()->prepare('SELECT id FROM proposals WHERE secure_token=:t LIMIT 1');$s->execute(['t'=>$token]);$id=(int)($s->fetchColumn()?:0);return $id?proposal_by_id($id):null;
}

function proposals(array $filters=[]): array
{
    if(!proposals_schema_available())return [];$where=[];$params=[];
    if(!empty($filters['status'])){$where[]='p.status=:status';$params['status']=$filters['status'];}
    $limit=max(1,min(1000,(int)($filters['limit']??250)));
    $sql='SELECT p.*,c.display_name AS contact_name,c.company AS contact_company,c.email AS contact_email,o.title AS opportunity_title,pr.title AS converted_project_title FROM proposals p JOIN crm_contacts c ON c.id=p.crm_contact_id LEFT JOIN crm_opportunities o ON o.id=p.crm_opportunity_id LEFT JOIN projects pr ON pr.id=p.converted_project_id';if($where)$sql.=' WHERE '.implode(' AND ',$where);$sql.=' ORDER BY FIELD(p.status,"viewed","sent","draft","accepted","declined","expired","converted","archived"),p.updated_at DESC,p.id DESC LIMIT '.$limit;$s=db()->prepare($sql);$s->execute($params);return $s->fetchAll();
}

function proposal_parse_line_items(array $rows): array
{
    $items=[];foreach($rows as $index=>$row){if(!is_array($row))continue;$name=substr(trim((string)($row['name']??'')),0,190);if($name==='')continue;$type=(string)($row['item_type']??'service');if(!in_array($type,['service','product','expense','discount'],true))$type='service';$items[]=['item_type'=>$type,'name'=>$name,'description'=>substr(trim((string)($row['description']??'')),0,5000),'quantity'=>max(0,min(999999,(float)($row['quantity']??1))),'unit_amount_cents'=>isset($row['unit_amount_cents'])?(int)$row['unit_amount_cents']:proposal_amount_to_cents($row['unit_amount']??0),'discount_percent'=>max(0,min(100,(float)($row['discount_percent']??0))),'taxable'=>!empty($row['taxable'])?1:0,'sort_order'=>(count($items)+1)*10];}
    return $items;
}

function proposal_number_unique(string $requested=''): string
{
    $base=strtoupper(trim($requested));$base=preg_replace('/[^A-Z0-9\-]+/','-',$base)??'';$base=trim($base,'-');if($base==='')$base='NMM-'.gmdate('Ymd').'-'.strtoupper(bin2hex(random_bytes(2)));$candidate=substr($base,0,60);$n=2;
    while(true){$s=db()->prepare('SELECT id FROM proposals WHERE proposal_number=:n LIMIT 1');$s->execute(['n'=>$candidate]);if(!$s->fetchColumn())return $candidate;$candidate=substr($base,0,52).'-'.$n++;}
}

function proposal_audit(int $proposalId,string $event,string $actor='system',?int $userId=null,?string $name=null,array $metadata=[]): void
{
    $events=['created','updated','sent','viewed','accepted','declined','expired','duplicated','revision_restored','converted','pdf_downloaded','reminder'];if(!in_array($event,$events,true))$event='updated';if(!in_array($actor,['admin','client','public','system'],true))$actor='system';
    db()->prepare('INSERT INTO proposal_audit_events(proposal_id,event_type,actor_type,actor_user_id,actor_name,ip_address,user_agent,metadata_json) VALUES(:p,:e,:a,:u,:n,:ip,:ua,:m)')->execute(['p'=>$proposalId,'e'=>$event,'a'=>$actor,'u'=>$userId,'n'=>$name?substr(trim($name),0,160):null,'ip'=>function_exists('request_ip')?request_ip():null,'ua'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)?:null,'m'=>$metadata?json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE):null]);
}

function proposal_snapshot(int $proposalId): array
{
    $row=proposal_raw_by_id($proposalId);if(!$row)throw new RuntimeException('Proposal not found.');return ['proposal'=>$row,'line_items'=>proposal_line_items($proposalId)];
}

function proposal_create_revision(int $proposalId,?int $userId,string $note=''): int
{
    $s=db()->prepare('SELECT COALESCE(MAX(revision_number),0)+1 FROM proposal_revisions WHERE proposal_id=:id');$s->execute(['id'=>$proposalId]);$next=(int)$s->fetchColumn();$snapshot=proposal_snapshot($proposalId);
    db()->prepare('INSERT INTO proposal_revisions(proposal_id,revision_number,snapshot_json,revision_note,created_by) VALUES(:p,:r,:s,:n,:u)')->execute(['p'=>$proposalId,'r'=>$next,'s'=>json_encode($snapshot,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),'n'=>$note!==''?substr($note,0,500):null,'u'=>$userId]);db()->prepare('UPDATE proposals SET current_revision=:r WHERE id=:id')->execute(['r'=>$next,'id'=>$proposalId]);return $next;
}

function proposal_find_or_create_contact(array $data): int
{
    $email=strtolower(trim((string)($data['email']??'')));$name=substr(trim((string)($data['display_name']??'')),0,160);$phone=substr(trim((string)($data['phone']??'')),0,60);$company=substr(trim((string)($data['company']??'')),0,190);
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');if($name==='')throw new RuntimeException('Enter a name.');
    $s=db()->prepare('SELECT id FROM crm_contacts WHERE email=:e LIMIT 1');$s->execute(['e'=>$email]);$id=(int)($s->fetchColumn()?:0);
    if($id){db()->prepare('UPDATE crm_contacts SET display_name=:n,phone=COALESCE(NULLIF(:p,""),phone),company=COALESCE(NULLIF(:c,""),company),last_inquiry_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['n'=>$name,'p'=>$phone,'c'=>$company,'id'=>$id]);return $id;}
    db()->prepare('INSERT INTO crm_contacts(email,display_name,company,phone,lifecycle_stage,source,last_inquiry_at) VALUES(:e,:n,:c,:p,"lead","project_intake",UTC_TIMESTAMP())')->execute(['e'=>$email,'n'=>$name,'c'=>$company?:null,'p'=>$phone?:null]);return (int)db()->lastInsertId();
}

function intake_create_or_get_for_appointment(array $appointment,array $template): array
{
    $appointmentId=(int)($appointment['id']??0);if(!$appointmentId)throw new RuntimeException('Appointment unavailable.');$s=db()->prepare('SELECT id FROM project_intakes WHERE appointment_id=:a AND template_id=:t ORDER BY id DESC LIMIT 1');$s->execute(['a'=>$appointmentId,'t'=>$template['id']]);$id=(int)($s->fetchColumn()?:0);
    if(!$id){db()->prepare('INSERT INTO project_intakes(template_id,appointment_id,crm_contact_id,crm_opportunity_id,status,display_name,email,phone,company,project_title,secure_token) VALUES(:t,:a,:c,:o,"started",:n,:e,:p,:co,:pt,:token)')->execute(['t'=>$template['id'],'a'=>$appointmentId,'c'=>(int)($appointment['crm_contact_id']??0)?:null,'o'=>(int)($appointment['crm_opportunity_id']??0)?:null,'n'=>$appointment['display_name']??null,'e'=>$appointment['email']??null,'p'=>$appointment['phone']??null,'co'=>$appointment['company']??null,'pt'=>$appointment['subject']??null,'token'=>bin2hex(random_bytes(32))]);$id=(int)db()->lastInsertId();}
    return intake_by_id($id)??throw new RuntimeException('Intake could not be created.');
}

function intake_public_context(string $slug='',string $appointmentToken='',string $intakeToken=''): array
{
    $settings=proposals_settings();$template=null;$intake=null;$appointment=null;
    if($intakeToken!==''){$intake=intake_by_token($intakeToken);if($intake)$template=intake_template_by_id((int)$intake['template_id']);}
    if(!$template&&$appointmentToken!==''&&function_exists('booking_appointment_by_token')){$appointment=booking_appointment_by_token($appointmentToken);if($appointment){$template=intake_template_for_booking_type((int)$appointment['booking_type_id']);if($template)$intake=intake_create_or_get_for_appointment($appointment,$template);}}
    if(!$template)$template=intake_template_by_slug($slug!==''?$slug:$settings['intake_default_template_slug'],true);
    return ['template'=>$template,'intake'=>$intake,'questions'=>$template?intake_questions((int)$template['id']):[],'appointment'=>$appointment];
}

function intake_submit_public(array $template,array $questions,array $data,?array $existing=null): array
{
    $name=substr(trim((string)($data['display_name']??'')),0,160);$email=strtolower(substr(trim((string)($data['email']??'')),0,190));$phone=substr(trim((string)($data['phone']??'')),0,60);$company=substr(trim((string)($data['company']??'')),0,190);if($name==='')throw new RuntimeException('Enter your name.');if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');
    $input=is_array($data['answers']??null)?$data['answers']:[];$answers=[];$projectTitle='';$summary='';
    foreach($questions as $q){$key=(string)$q['question_key'];$value=$q['field_type']==='checkbox'?(!empty($input[$key])?'Yes':'No'):trim((string)($input[$key]??''));if(!empty($q['required'])&&$value==='')throw new RuntimeException('Complete the required field: '.$q['label'].'.');if($q['field_type']==='select'&&$value!==''&&!in_array($value,intake_question_options($q),true))throw new RuntimeException('Choose a valid option for '.$q['label'].'.');$value=substr($value,0,20000);$answers[(int)$q['id']]=$value;if($key==='project_name')$projectTitle=substr($value,0,190);if($key==='project_summary')$summary=substr($value,0,5000);}
    $pdo=db();$pdo->beginTransaction();
    try{$contactId=proposal_find_or_create_contact(['display_name'=>$name,'email'=>$email,'phone'=>$phone,'company'=>$company]);$opportunityId=(int)($existing['crm_opportunity_id']??0);if(!$opportunityId&&!empty($template['create_opportunity'])){db()->prepare('INSERT INTO crm_opportunities(contact_id,title,opportunity_type,stage,probability,next_action,next_action_at,source,message) VALUES(:c,:t,:ot,"reviewing",20,"Review project intake",UTC_TIMESTAMP(),"project_intake",:m)')->execute(['c'=>$contactId,'t'=>$projectTitle?:'Project intake','ot'=>$template['opportunity_type']?:'Project Intake','m'=>$summary?:null]);$opportunityId=(int)db()->lastInsertId();}
        $id=(int)($existing['id']??0);if($id){db()->prepare('UPDATE project_intakes SET crm_contact_id=:c,crm_opportunity_id=:o,status="submitted",display_name=:n,email=:e,phone=:p,company=:co,project_title=:pt,summary=:s,submitted_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['c'=>$contactId,'o'=>$opportunityId?:null,'n'=>$name,'e'=>$email,'p'=>$phone?:null,'co'=>$company?:null,'pt'=>$projectTitle?:null,'s'=>$summary?:null,'id'=>$id]);}else{db()->prepare('INSERT INTO project_intakes(template_id,crm_contact_id,crm_opportunity_id,status,display_name,email,phone,company,project_title,summary,secure_token,submitted_at) VALUES(:t,:c,:o,"submitted",:n,:e,:p,:co,:pt,:s,:token,UTC_TIMESTAMP())')->execute(['t'=>$template['id'],'c'=>$contactId,'o'=>$opportunityId?:null,'n'=>$name,'e'=>$email,'p'=>$phone?:null,'co'=>$company?:null,'pt'=>$projectTitle?:null,'s'=>$summary?:null,'token'=>bin2hex(random_bytes(32))]);$id=(int)db()->lastInsertId();}
        $stmt=db()->prepare('INSERT INTO project_intake_answers(intake_id,question_id,answer_text) VALUES(:i,:q,:a) ON DUPLICATE KEY UPDATE answer_text=VALUES(answer_text),updated_at=CURRENT_TIMESTAMP');foreach($answers as $qid=>$answer)$stmt->execute(['i'=>$id,'q'=>$qid,'a'=>$answer!==''?$answer:null]);db()->prepare('INSERT INTO crm_activities(contact_id,opportunity_id,activity_type,subject,body) VALUES(:c,:o,"inquiry","Project intake submitted",:b)')->execute(['c'=>$contactId,'o'=>$opportunityId?:null,'b'=>implode("\n",array_filter([$projectTitle?'Project: '.$projectTitle:'',$summary?'Summary: '.$summary:'','Template: '.$template['name']]))]);$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    require_once __DIR__.'/notifications.php';notification_create_for_role('admin','contact','New project intake',$name.' submitted '.$template['name'].'.','portal/admin.php?view=proposals&mode=intakes&intake='.$id,'project_intake',$id,'normal');
    try{if(function_exists('visitor_intelligence_attach_contact'))visitor_intelligence_attach_contact($contactId,'intake_submitted',['event_label'=>$template['name'],'page_path'=>'intake.php','crm_opportunity_id'=>$opportunityId?:null,'metadata'=>['intake_id'=>$id,'template_id'=>$template['id']]]);}catch(Throwable $e){error_log($e->getMessage());}
    $saved=intake_by_id($id);return ['intake_id'=>$id,'contact_id'=>$contactId,'opportunity_id'=>$opportunityId,'token'=>$saved['secure_token']??'','message'=>$template['completion_message']?:'Your project intake was received.','confirmation_url'=>'intake-confirmation.php?token='.rawurlencode((string)($saved['secure_token']??''))];
}

function proposal_save(array $data,array $user): int
{
    $id=max(0,(int)($data['id']??0));$isNew=$id===0;$contact=max(0,(int)($data['crm_contact_id']??0));$opportunity=max(0,(int)($data['crm_opportunity_id']??0));$intake=max(0,(int)($data['intake_id']??0));$appointment=max(0,(int)($data['appointment_id']??0));$title=substr(trim((string)($data['title']??'')),0,190);if(!$contact||$title==='')throw new RuntimeException('Select a CRM contact and enter a proposal title.');$status=(string)($data['status']??'draft');if(!isset(proposal_statuses()[$status]))$status='draft';$currency=strtoupper(substr(trim((string)($data['currency_code']??'USD')),0,3));if(!preg_match('/^[A-Z]{3}$/',$currency))$currency='USD';$items=proposal_parse_line_items(is_array($data['line_items']??null)?$data['line_items']:[]);$tax=max(0,min(100,(float)($data['tax_percent']??0)));$deposit=max(0,min(100,(float)($data['deposit_percent']??0)));$totals=proposal_calculate_totals($items,proposal_amount_to_cents($data['discount_amount']??0),$tax,$deposit);$valid=trim((string)($data['valid_until']??''));if($valid!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$valid))throw new RuntimeException('Enter a valid expiration date.');if($valid==='')$valid=gmdate('Y-m-d',time()+proposals_settings()['default_valid_days']*86400);$follow=trim((string)($data['next_follow_up_at']??''));$follow=$follow!==''?str_replace('T',' ',$follow):null;
    $fields=['contact'=>$contact,'opportunity'=>$opportunity?:null,'intake'=>$intake?:null,'appointment'=>$appointment?:null,'title'=>$title,'status'=>$status,'currency'=>$currency,'valid'=>$valid,'intro'=>substr(trim((string)($data['public_intro']??'')),0,10000)?:null,'scope'=>substr(trim((string)($data['scope_text']??'')),0,50000)?:null,'deliverables'=>substr(trim((string)($data['deliverables_text']??'')),0,50000)?:null,'timeline'=>substr(trim((string)($data['timeline_text']??'')),0,50000)?:null,'assumptions'=>substr(trim((string)($data['assumptions_text']??'')),0,50000)?:null,'exclusions'=>substr(trim((string)($data['exclusions_text']??'')),0,50000)?:null,'terms'=>substr(trim((string)($data['terms_text']??'')),0,50000)?:null,'notes'=>substr(trim((string)($data['internal_notes']??'')),0,50000)?:null,'discount'=>$totals['discount_cents'],'tax'=>$tax,'subtotal'=>$totals['subtotal_cents'],'taxc'=>$totals['tax_cents'],'total'=>$totals['total_cents'],'deposit'=>$deposit,'depositc'=>$totals['deposit_amount_cents'],'payment'=>substr(trim((string)($data['payment_url']??'')),0,500)?:null,'follow'=>$follow,'user'=>(int)$user['id']];
    $pdo=db();$pdo->beginTransaction();try{if($id){if(!proposal_raw_by_id($id))throw new RuntimeException('Proposal not found.');proposal_create_revision($id,(int)$user['id'],'Snapshot before administrator update');db()->prepare('UPDATE proposals SET crm_contact_id=:contact,crm_opportunity_id=:opportunity,intake_id=:intake,appointment_id=:appointment,title=:title,status=:status,currency_code=:currency,valid_until=:valid,public_intro=:intro,scope_text=:scope,deliverables_text=:deliverables,timeline_text=:timeline,assumptions_text=:assumptions,exclusions_text=:exclusions,terms_text=:terms,internal_notes=:notes,discount_cents=:discount,tax_percent=:tax,subtotal_cents=:subtotal,tax_cents=:taxc,total_cents=:total,deposit_percent=:deposit,deposit_amount_cents=:depositc,payment_url=:payment,next_follow_up_at=:follow,updated_by=:user,sent_at=IF(:status2="sent",COALESCE(sent_at,UTC_TIMESTAMP()),sent_at) WHERE id=:id')->execute($fields+['status2'=>$status,'id'=>$id]);}else{db()->prepare('INSERT INTO proposals(proposal_number,crm_contact_id,crm_opportunity_id,intake_id,appointment_id,title,status,currency_code,valid_until,public_intro,scope_text,deliverables_text,timeline_text,assumptions_text,exclusions_text,terms_text,internal_notes,discount_cents,tax_percent,subtotal_cents,tax_cents,total_cents,deposit_percent,deposit_amount_cents,payment_url,secure_token,sent_at,next_follow_up_at,created_by,updated_by) VALUES(:number,:contact,:opportunity,:intake,:appointment,:title,:status,:currency,:valid,:intro,:scope,:deliverables,:timeline,:assumptions,:exclusions,:terms,:notes,:discount,:tax,:subtotal,:taxc,:total,:deposit,:depositc,:payment,:token,:sent,:follow,:created_by,:updated_by)')->execute(array_diff_key($fields,['user'=>true])+['created_by'=>$fields['user'],'updated_by'=>$fields['user'],'number'=>proposal_number_unique((string)($data['proposal_number']??'')),'token'=>bin2hex(random_bytes(32)),'sent'=>$status==='sent'?gmdate('Y-m-d H:i:s'):null]);$id=(int)db()->lastInsertId();}
        db()->prepare('DELETE FROM proposal_line_items WHERE proposal_id=:id')->execute(['id'=>$id]);$stmt=db()->prepare('INSERT INTO proposal_line_items(proposal_id,item_type,name,description,quantity,unit_amount_cents,discount_percent,taxable,sort_order) VALUES(:proposal_id,:item_type,:name,:description,:quantity,:unit_amount_cents,:discount_percent,:taxable,:sort_order)');foreach($items as $item)$stmt->execute(['proposal_id'=>$id]+$item);
        if($opportunity){db()->prepare('UPDATE crm_opportunities SET stage=:stage,estimated_value=:value,probability=:prob,next_action=:action,next_action_at=:next WHERE id=:id')->execute(['stage'=>in_array($status,['accepted','converted'],true)?'won':'proposal','value'=>$totals['total_cents']/100,'prob'=>in_array($status,['accepted','converted'],true)?100:60,'action'=>in_array($status,['accepted','converted'],true)?'Begin project conversion':'Follow up on proposal','next'=>$follow,'id'=>$opportunity]);}
        if($intake)db()->prepare('UPDATE project_intakes SET converted_proposal_id=:p,status=IF(status="submitted","reviewed",status),reviewed_at=COALESCE(reviewed_at,UTC_TIMESTAMP()) WHERE id=:i')->execute(['p'=>$id,'i'=>$intake]);$pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    proposal_audit($id,$isNew?'created':'updated','admin',(int)$user['id'],$user['display_name']??null,['status'=>$status,'total_cents'=>$totals['total_cents']]);if($status==='sent')proposal_schedule_follow_up($id);return $id;
}

function proposal_schedule_follow_up(int $id): void
{
    $p=proposal_raw_by_id($id);if(!$p)return;$scheduled=$p['next_follow_up_at']?:gmdate('Y-m-d H:i:s',time()+proposals_settings()['follow_up_days']*86400);$status=$scheduled<=gmdate('Y-m-d H:i:s')?'ready':'pending';db()->prepare('INSERT INTO proposal_reminders(proposal_id,reminder_type,scheduled_for,status) VALUES(:p,"follow_up",:s,:st) ON DUPLICATE KEY UPDATE status=VALUES(status),last_error=NULL,sent_at=NULL')->execute(['p'=>$id,'s'=>$scheduled,'st'=>$status]);
}

function proposal_duplicate(int $id,array $user): int
{
    $p=proposal_by_id($id);if(!$p)throw new RuntimeException('Proposal not found.');$data=$p;$data['id']=0;$data['proposal_number']=$p['proposal_number'].'-COPY';$data['title']=$p['title'].' Copy';$data['status']='draft';$data['intake_id']=0;$data['appointment_id']=0;$data['valid_until']=gmdate('Y-m-d',time()+proposals_settings()['default_valid_days']*86400);$data['discount_amount']=$p['discount_cents']/100;$data['line_items']=$p['line_items'];$new=proposal_save($data,$user);proposal_audit($new,'duplicated','admin',(int)$user['id'],$user['display_name']??null,['source_proposal_id'=>$id]);return $new;
}

function proposal_restore_revision(int $proposalId,int $revisionId,array $user): void
{
    $s=db()->prepare('SELECT * FROM proposal_revisions WHERE id=:r AND proposal_id=:p LIMIT 1');$s->execute(['r'=>$revisionId,'p'=>$proposalId]);$rev=$s->fetch();if(!$rev)throw new RuntimeException('Proposal revision not found.');$snapshot=json_decode((string)$rev['snapshot_json'],true);if(!is_array($snapshot)||!is_array($snapshot['proposal']??null))throw new RuntimeException('Invalid proposal revision.');proposal_create_revision($proposalId,(int)$user['id'],'Undo snapshot before revision restore');$p=$snapshot['proposal'];$fields=['crm_contact_id','crm_opportunity_id','intake_id','appointment_id','title','currency_code','valid_until','public_intro','scope_text','deliverables_text','timeline_text','assumptions_text','exclusions_text','terms_text','internal_notes','discount_cents','tax_percent','subtotal_cents','tax_cents','total_cents','deposit_percent','deposit_amount_cents','payment_url','next_follow_up_at'];$set=[];$params=['id'=>$proposalId,'user'=>(int)$user['id']];foreach($fields as $f){$set[]="$f=:$f";$params[$f]=$p[$f]??null;}$pdo=db();$pdo->beginTransaction();try{db()->prepare('UPDATE proposals SET '.implode(',',$set).',updated_by=:user WHERE id=:id')->execute($params);db()->prepare('DELETE FROM proposal_line_items WHERE proposal_id=:id')->execute(['id'=>$proposalId]);$stmt=db()->prepare('INSERT INTO proposal_line_items(proposal_id,item_type,name,description,quantity,unit_amount_cents,discount_percent,taxable,sort_order) VALUES(:proposal_id,:item_type,:name,:description,:quantity,:unit_amount_cents,:discount_percent,:taxable,:sort_order)');foreach(($snapshot['line_items']??[]) as $item){$stmt->execute(['proposal_id'=>$proposalId,'item_type'=>$item['item_type']??'service','name'=>substr((string)($item['name']??'Item'),0,190),'description'=>$item['description']??null,'quantity'=>(float)($item['quantity']??1),'unit_amount_cents'=>(int)($item['unit_amount_cents']??0),'discount_percent'=>(float)($item['discount_percent']??0),'taxable'=>!empty($item['taxable'])?1:0,'sort_order'=>(int)($item['sort_order']??100)]);}$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}proposal_audit($proposalId,'revision_restored','admin',(int)$user['id'],$user['display_name']??null,['revision_id'=>$revisionId]);
}

function proposal_public_mark_viewed(array $p): array
{
    if(!in_array($p['status'],['sent','viewed'],true))return $p;$first=empty($p['first_viewed_at']);db()->prepare('UPDATE proposals SET status="viewed",first_viewed_at=COALESCE(first_viewed_at,UTC_TIMESTAMP()),last_viewed_at=UTC_TIMESTAMP(),view_count=view_count+1 WHERE id=:id')->execute(['id'=>$p['id']]);proposal_audit((int)$p['id'],'viewed','public',null,$p['contact_name']??null,['first_view'=>$first]);if($first){require_once __DIR__.'/notifications.php';notification_create_for_role('admin','contact','Proposal viewed',($p['contact_name']??'Client').' viewed '.$p['proposal_number'].'.','portal/admin.php?view=proposals&edit='.$p['id'],'proposal',(int)$p['id'],'normal');}try{if(function_exists('visitor_intelligence_attach_contact'))visitor_intelligence_attach_contact((int)$p['crm_contact_id'],'proposal_viewed',['event_label'=>$p['proposal_number'],'page_path'=>'proposal.php','crm_opportunity_id'=>(int)($p['crm_opportunity_id']??0)?:null,'metadata'=>['proposal_id'=>$p['id'],'total_cents'=>$p['total_cents']]]);}catch(Throwable $e){error_log($e->getMessage());}return proposal_by_id((int)$p['id'])??$p;
}

function proposal_accept(string $token,string $name): array
{
    $p=proposal_by_token($token);if(!$p)throw new RuntimeException('Proposal not found.');if(!in_array($p['status'],['sent','viewed'],true))throw new RuntimeException('This proposal can no longer be accepted.');$name=substr(trim($name),0,160);if(strlen($name)<2)throw new RuntimeException('Type your full name to accept the proposal.');$pdo=db();$pdo->beginTransaction();try{db()->prepare('UPDATE proposals SET status="accepted",accepted_at=UTC_TIMESTAMP(),accepted_name=:n,accepted_ip=:ip,accepted_user_agent=:ua,next_follow_up_at=NULL WHERE id=:id')->execute(['n'=>$name,'ip'=>request_ip(),'ua'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500),'id'=>$p['id']]);db()->prepare('UPDATE proposal_reminders SET status="cancelled" WHERE proposal_id=:id AND status IN("pending","ready")')->execute(['id'=>$p['id']]);if(!empty($p['crm_opportunity_id']))db()->prepare('UPDATE crm_opportunities SET stage="won",probability=100,next_action="Convert accepted proposal to project",next_action_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id'=>$p['crm_opportunity_id']]);db()->prepare('INSERT INTO crm_activities(contact_id,opportunity_id,activity_type,subject,body) VALUES(:c,:o,"conversion","Proposal accepted",:b)')->execute(['c'=>$p['crm_contact_id'],'o'=>$p['crm_opportunity_id']?:null,'b'=>$p['proposal_number'].' accepted by '.$name.' for '.proposal_money((int)$p['total_cents'],$p['currency_code'])]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}proposal_audit((int)$p['id'],'accepted','public',null,$name,['total_cents'=>$p['total_cents']]);require_once __DIR__.'/notifications.php';notification_create_for_role('admin','contact','Proposal accepted',$name.' accepted '.$p['proposal_number'].'.','portal/admin.php?view=proposals&edit='.$p['id'],'proposal',(int)$p['id'],'high');try{if(function_exists('visitor_intelligence_attach_contact'))visitor_intelligence_attach_contact((int)$p['crm_contact_id'],'proposal_accepted',['event_label'=>$p['proposal_number'],'page_path'=>'proposal.php','crm_opportunity_id'=>(int)($p['crm_opportunity_id']??0)?:null,'metadata'=>['proposal_id'=>$p['id'],'total_cents'=>$p['total_cents']]]);}catch(Throwable $e){error_log($e->getMessage());}return proposal_by_id((int)$p['id'])??$p;
}

function proposal_decline(string $token,string $reason): array
{
    $p=proposal_by_token($token);if(!$p)throw new RuntimeException('Proposal not found.');if(!in_array($p['status'],['sent','viewed'],true))throw new RuntimeException('This proposal can no longer be declined.');$reason=substr(trim($reason),0,5000);db()->prepare('UPDATE proposals SET status="declined",declined_at=UTC_TIMESTAMP(),declined_reason=:r,next_follow_up_at=NULL WHERE id=:id')->execute(['r'=>$reason?:null,'id'=>$p['id']]);db()->prepare('UPDATE proposal_reminders SET status="cancelled" WHERE proposal_id=:id AND status IN("pending","ready")')->execute(['id'=>$p['id']]);if(!empty($p['crm_opportunity_id']))db()->prepare('UPDATE crm_opportunities SET stage="lost",probability=0,next_action=NULL,next_action_at=NULL WHERE id=:id')->execute(['id'=>$p['crm_opportunity_id']]);db()->prepare('INSERT INTO crm_activities(contact_id,opportunity_id,activity_type,subject,body) VALUES(:c,:o,"status_change","Proposal declined",:b)')->execute(['c'=>$p['crm_contact_id'],'o'=>$p['crm_opportunity_id']?:null,'b'=>$reason?:null]);proposal_audit((int)$p['id'],'declined','public',null,$p['contact_name']??null,['reason'=>$reason]);require_once __DIR__.'/notifications.php';notification_create_for_role('admin','contact','Proposal declined',($p['contact_name']??'Client').' declined '.$p['proposal_number'].'.','portal/admin.php?view=proposals&edit='.$p['id'],'proposal',(int)$p['id'],'normal');try{if(function_exists('visitor_intelligence_attach_contact'))visitor_intelligence_attach_contact((int)$p['crm_contact_id'],'proposal_declined',['event_label'=>$p['proposal_number'],'page_path'=>'proposal.php','crm_opportunity_id'=>(int)($p['crm_opportunity_id']??0)?:null,'metadata'=>['proposal_id'=>$p['id']]]);}catch(Throwable $e){error_log($e->getMessage());}return proposal_by_id((int)$p['id'])??$p;
}

function proposal_convert_to_project(int $id,array $user): array
{
    $p=proposal_by_id($id);if(!$p)throw new RuntimeException('Proposal not found.');if(!in_array($p['status'],['accepted','converted'],true))throw new RuntimeException('Only accepted proposals can become client projects.');if(!empty($p['converted_project_id']))return ['project_id'=>(int)$p['converted_project_id'],'temporary_password'=>null];$s=db()->prepare('SELECT * FROM crm_contacts WHERE id=:id');$s->execute(['id'=>$p['crm_contact_id']]);$contact=$s->fetch();if(!$contact)throw new RuntimeException('CRM contact not found.');$clientId=(int)($contact['client_user_id']??0);$temporary=null;if(!$clientId){$email=strtolower(trim((string)$contact['email']));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('The CRM contact needs a valid email.');$s=db()->prepare('SELECT id,role FROM users WHERE email=:e LIMIT 1');$s->execute(['e'=>$email]);$existing=$s->fetch();if($existing){if($existing['role']!=='client')throw new RuntimeException('The contact email belongs to a non-client account.');$clientId=(int)$existing['id'];}else{$temporary=random_password();db()->prepare('INSERT INTO users(role,email,password_hash,display_name,company,phone,status,must_change_password) VALUES("client",:e,:h,:n,:c,:p,"active",1)')->execute(['e'=>$email,'h'=>password_hash($temporary,PASSWORD_DEFAULT),'n'=>$contact['display_name'],'c'=>$contact['company'],'p'=>$contact['phone']]);$clientId=(int)db()->lastInsertId();}db()->prepare('UPDATE crm_contacts SET client_user_id=:u,lifecycle_stage="client" WHERE id=:id')->execute(['u'=>$clientId,'id'=>$contact['id']]);}
    $pdo=db();$pdo->beginTransaction();try{db()->prepare('INSERT INTO projects(client_user_id,title,slug,summary,status,priority,progress,start_date,budget,next_milestone,created_by) VALUES(:u,:t,:slug,:s,"planning","normal",0,UTC_DATE(),:b,"Project kickoff",:created)')->execute(['u'=>$clientId,'t'=>$p['title'],'slug'=>slugify($p['title']).'-'.bin2hex(random_bytes(2)),'s'=>$p['public_intro']?:$p['scope_text'],'b'=>$p['total_cents']/100,'created'=>$user['id']]);$projectId=(int)db()->lastInsertId();db()->prepare('UPDATE proposals SET status="converted",converted_project_id=:project,converted_at=UTC_TIMESTAMP(),updated_by=:user WHERE id=:id')->execute(['project'=>$projectId,'user'=>$user['id'],'id'=>$id]);if(!empty($p['intake_id']))db()->prepare('UPDATE project_intakes SET status="converted" WHERE id=:id')->execute(['id'=>$p['intake_id']]);db()->prepare('INSERT INTO project_updates(project_id,title,body,visibility,created_by) VALUES(:p,"Project created from accepted proposal",:b,"admin",:u)')->execute(['p'=>$projectId,'b'=>$p['proposal_number'].' was accepted and converted into this client project.','u'=>$user['id']]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}proposal_audit($id,'converted','admin',(int)$user['id'],$user['display_name']??null,['project_id'=>$projectId]);return ['project_id'=>$projectId,'temporary_password'=>$temporary];
}

function proposals_admin_stats(): array
{
    if(!proposals_schema_available())return ['draft_count'=>0,'sent_count'=>0,'viewed_count'=>0,'accepted_count'=>0,'follow_ups_due'=>0];$r=db()->query('SELECT SUM(status="draft") draft_count,SUM(status="sent") sent_count,SUM(status="viewed") viewed_count,SUM(status="accepted") accepted_count,SUM(status IN("sent","viewed") AND next_follow_up_at IS NOT NULL AND next_follow_up_at<=UTC_TIMESTAMP()) follow_ups_due FROM proposals')->fetch()?:[];return array_map(static fn($v)=>(int)($v??0),$r);
}

function proposals_analytics(int $days=30): array
{
    $days=max(1,min(365,$days));$r=['intake_submissions'=>0,'proposal_views'=>0,'proposal_acceptances'=>0,'proposal_declines'=>0,'pdf_downloads'=>0,'accepted_value_cents'=>0];try{if(function_exists('visitor_intelligence_schema_available')&&visitor_intelligence_schema_available()){$row=db()->query('SELECT SUM(event_type="intake_submitted") intake_submissions,SUM(event_type="proposal_viewed") proposal_views,SUM(event_type="proposal_accepted") proposal_acceptances,SUM(event_type="proposal_declined") proposal_declines,SUM(event_type="proposal_pdf_downloaded") pdf_downloads FROM visitor_events WHERE occurred_at>=UTC_TIMESTAMP()-INTERVAL '.$days.' DAY')->fetch()?:[];foreach(array_keys($r) as $key)if($key!=='accepted_value_cents')$r[$key]=(int)($row[$key]??0);}}catch(Throwable){}$s=db()->query('SELECT COALESCE(SUM(total_cents),0) FROM proposals WHERE status IN("accepted","converted") AND accepted_at>=UTC_TIMESTAMP()-INTERVAL '.$days.' DAY');$r['accepted_value_cents']=(int)$s->fetchColumn();return $r;
}

function proposal_absolute_url(string $path): string
{
    $base=trim((string)(nmm_config('app')['base_url']??''));if($base!=='')return rtrim($base,'/').'/'.ltrim($path,'/');$scheme=!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off'?'https':'http';$host=trim((string)($_SERVER['HTTP_HOST']??'localhost'));$script=str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME']??'/'));$dir=rtrim(dirname($script),'/.');return $scheme.'://'.$host.($dir!==''?$dir:'').'/'.ltrim($path,'/');
}

function proposal_pdf_escape(string $text): string
{
    return preg_replace('/[^\x20-\x7E\n]/','?',str_replace(['\\','(',')',"\r"],['\\\\','\\(','\\)',''],$text))??'';
}

function proposal_pdf_wrap(string $text,int $width=88): array
{
    $out=[];foreach(preg_split('/\n+/u',trim(strip_tags($text)))?:[] as $p){if(trim($p)==='')continue;foreach(explode("\n",wordwrap(trim($p),$width,"\n",true)) as $line)$out[]=$line;$out[]='';}return $out;
}

function proposal_pdf_document(array $p): string
{
    $settings=proposals_settings();$lines=[[$settings['company_name'],18,1],[$settings['company_location'],9,0],['',8,0],['PROPOSAL '.$p['proposal_number'],10,1],[$p['title'],16,1],['Prepared for '.$p['contact_name'].(!empty($p['contact_company'])?' — '.$p['contact_company']:''),10,0],['Valid through '.($p['valid_until']?:'No expiration date'),9,0],['',8,0]];foreach(proposal_pdf_wrap((string)($p['public_intro']??'')) as $line)$lines[]=[$line,10,0];foreach(['Scope'=>'scope_text','Deliverables'=>'deliverables_text','Timeline'=>'timeline_text','Assumptions'=>'assumptions_text','Exclusions'=>'exclusions_text','Terms'=>'terms_text'] as $heading=>$key){if(empty($p[$key]))continue;$lines[]=[$heading,11,1];foreach(proposal_pdf_wrap((string)$p[$key]) as $line)$lines[]=[$line,9,0];}$lines[]=['Estimate',11,1];foreach($p['line_items'] as $item){$lines[]=[$item['name'].' — '.proposal_money(proposal_line_total_cents($item),$p['currency_code']),9,1];foreach(proposal_pdf_wrap((string)($item['description']??''),80) as $line)$lines[]=[$line,8,0];}$lines[]=['',8,0];$lines[]=['Subtotal: '.proposal_money((int)$p['subtotal_cents'],$p['currency_code']),10,0];if((int)$p['discount_cents']>0)$lines[]=['Discount: -'.proposal_money((int)$p['discount_cents'],$p['currency_code']),10,0];if((int)$p['tax_cents']>0)$lines[]=['Tax: '.proposal_money((int)$p['tax_cents'],$p['currency_code']),10,0];$lines[]=['Total: '.proposal_money((int)$p['total_cents'],$p['currency_code']),13,1];$lines[]=['Requested deposit: '.proposal_money((int)$p['deposit_amount_cents'],$p['currency_code']).' ('.number_format((float)$p['deposit_percent'],1).'%)',10,0];if(!empty($p['accepted_at'])){$lines[]=['',8,0];$lines[]=['ACCEPTED',11,1];$lines[]=['Accepted by '.$p['accepted_name'].' on '.$p['accepted_at'],9,0];}$lines[]=['',8,0];$lines[]=[$settings['pdf_footer'],8,0];
    $pages=[];$current=[];$y=748;foreach($lines as [$text,$size,$bold]){$leading=$size+5;if($y<52){$pages[]=$current;$current=[];$y=748;}$current[]=['text'=>proposal_pdf_escape((string)$text),'size'=>$size,'bold'=>$bold,'y'=>$y];$y-=$leading;}if($current||!$pages)$pages[]=$current;$objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',4=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>'];$ids=[];$next=5;foreach($pages as $page){$pid=$next++;$cid=$next++;$ids[]=$pid;$stream="BT\n";foreach($page as $line)$stream.=($line['bold']?'/F2':'/F1').' '.$line['size']." Tf\n1 0 0 1 52 ".$line['y']." Tm\n(".$line['text'].") Tj\n";$stream.="ET\n";$objects[$cid]='<< /Length '.strlen($stream)." >>\nstream\n".$stream."endstream";$objects[$pid]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$cid.' 0 R >>';}$objects[2]='<< /Type /Pages /Kids [ '.implode(' ',array_map(static fn($id)=>$id.' 0 R',$ids)).' ] /Count '.count($ids).' >>';ksort($objects);$pdf="%PDF-1.4\n";$offset=[0];foreach($objects as $id=>$obj){$offset[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".$obj."\nendobj\n";}$xref=strlen($pdf);$max=max(array_keys($objects));$pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";for($i=1;$i<=$max;$i++)$pdf.=sprintf("%010d 00000 n \n",$offset[$i]??0);return $pdf.'trailer << /Size '.($max+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
}
