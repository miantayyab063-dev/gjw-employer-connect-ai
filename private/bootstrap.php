<?php
declare(strict_types=1);
$configFile=__DIR__.'/config.php';
if(!is_file($configFile)){http_response_code(503);exit('Configuration missing. See docs/INSTALL.md');}
$c=require $configFile;
function db():PDO{global $c;static $p=null;if(!$p){$p=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}return $p;}
function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function csrf():string{return $_SESSION['csrf']??=bin2hex(random_bytes(32));}
function require_auth():void{if(empty($_SESSION['auth'])){http_response_code(403);exit('Login required');}}
function redirect(string $p='index.php'):never{header('Location: '.$p);exit;}
function audit(string $action,string $detail=''):void{$s=db()->prepare('INSERT INTO audit_log(action,detail) VALUES(?,?)');$s->execute([$action,mb_substr($detail,0,1000)]);}
function clean_domain(string $v):string{$v=strtolower(trim($v));$host=parse_url(str_contains($v,'://')?$v:'https://'.$v,PHP_URL_HOST);$host=preg_replace('/^www\./','',(string)$host);return preg_match('/^(?:[a-z0-9-]+\.)+[a-z]{2,}$/',$host)?$host:'';}
function api_json(string $url,array $headers=[],?array $payload=null):array{
 if(!str_starts_with($url,'https://'))throw new RuntimeException('HTTPS required');
 $ch=curl_init($url);$opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>7,CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true];
 if($payload!==null){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=json_encode($payload,JSON_THROW_ON_ERROR);}
 curl_setopt_array($ch,$opts);$body=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
 if($body===false||$code<200||$code>=300)throw new RuntimeException('Provider HTTP '.$code.' '.mb_substr($err?:((string)$body),0,160));
 return json_decode((string)$body,true,512,JSON_THROW_ON_ERROR);
}
function discover(string $country,string $industry):array{
 global $c;
 if(empty($c['brave_api_key']))throw new RuntimeException('Configure Brave Search API key');
 $query=$industry.' companies hiring recruitment careers '.$country;
 $url='https://api.search.brave.com/res/v1/web/search?'.http_build_query(['q'=>$query,'count'=>20]);
 $data=api_json($url,['Accept: application/json','X-Subscription-Token: '.$c['brave_api_key']]);
 $found=0;$added=0;
 foreach(($data['web']['results']??[]) as $r){$url=(string)($r['url']??'');$host=clean_domain($url);if(!$host||str_ends_with($host,'linkedin.com')||str_ends_with($host,'indeed.com')||str_ends_with($host,'glassdoor.com'))continue;
 $found++;$name=mb_substr(strip_tags((string)($r['title']??$host)),0,255);$snippet=mb_substr(strip_tags((string)($r['description']??'')),0,2000);
 $s=db()->prepare("INSERT IGNORE INTO companies(name,domain,country,industry,source_url,hiring_signal,score) VALUES(?,?,?,?,?,?,?)");$s->execute([$name,$host,$country,$industry,$url,$snippet,40]);$added+=$s->rowCount();
 }
 $s=db()->prepare('INSERT INTO discovery_runs(market,industry,found_count,added_count) VALUES(?,?,?,?)');$s->execute([$country,$industry,$found,$added]);audit('discovery',"$country / $industry: $added added");return [$found,$added];
}
function proposal_text(array $company,array $contact):array{
 $subject='Recruitment Partnership Proposal — Gulf Job Works / '.$company['name'];
 $salutation=$contact['full_name']!==''?'Dear '.$contact['full_name'].',':'Dear Hiring Team,';
 $body=$salutation."\n\nWe are contacting you from Gulf Job Works to explore whether your organization welcomes recruitment-service proposals.\n\nWe support employer-led recruitment coordination for suitable roles in ".$company['industry'].". If this is relevant to your current hiring plans, we would be pleased to discuss your requirements and share our company profile.\n\nPlease reply if you would like further information. If you prefer not to receive further messages, reply 'No further contact'.\n\nRegards,\nGulf Job Works\nYOUR VERIFIED BUSINESS CONTACT DETAILS";
 return [$subject,$body];
}
function ai_proposal_text(array $company,array $contact):array{
 global $c;
 if(empty($c['openai_api_key']))return proposal_text($company,$contact);
 $instructions='Draft a concise professional recruitment-services introduction for Gulf Job Works. Do not claim any unverified vacancies, relationships, certifications, candidate availability or outcomes. Do not invent addresses, names or phone numbers. Ask whether the company welcomes recruitment proposals. Include a simple opt-out sentence. Return JSON with subject and body strings only. Use English.';
 $input=['company_name'=>(string)$company['name'],'industry'=>(string)$company['industry'],'contact_name'=>(string)$contact['full_name'],'contact_role'=>(string)$contact['role_title']];
 $response=api_json('https://api.openai.com/v1/chat/completions',['Authorization: Bearer '.$c['openai_api_key'],'Content-Type: application/json'],['model'=>$c['openai_model']??'gpt-4o-mini','temperature'=>0.3,'response_format'=>['type'=>'json_object'],'messages'=>[['role'=>'system','content'=>$instructions],['role'=>'user','content'=>json_encode($input,JSON_THROW_ON_ERROR)]]]);
 $raw=(string)($response['choices'][0]['message']['content']??'');$data=json_decode($raw,true);
 if(!is_array($data)||empty($data['subject'])||empty($data['body']))throw new RuntimeException('AI returned an invalid draft');
 return [mb_substr(trim((string)$data['subject']),0,255),mb_substr(trim((string)$data['body']),0,10000)];
}
function eligible(array $p):bool{return $p['permission_status']==='confirmed'&&!empty($p['permission_basis'])&&$p['permission_evidence']!==null&&trim((string)$p['permission_evidence'])!==''&&$p['validation_status']==='valid'&&$p['status']==='approved';}
function send_approved(int $limit):array{
 global $c;if(empty($c['brevo_api_key'])||empty($c['sender_email']))throw new RuntimeException('Configure Brevo API key and verified sender');
 $lock=db()->query("SELECT GET_LOCK('gjw_send_batch', 0)")->fetchColumn();if((int)$lock!==1)throw new RuntimeException('Another sending batch is already running');try{
 $today=(int)db()->query("SELECT COUNT(*) FROM mail_events WHERE event_type='accepted' AND DATE(created_at)=CURDATE()")->fetchColumn();
 $limit=max(0,min($limit,(int)$c['daily_send_limit']-$today,20));$sent=0;$failed=0;
 $sql="SELECT p.*,ct.email,ct.full_name,ct.permission_status,ct.permission_evidence,ct.validation_status,co.name company_name FROM proposals p JOIN contacts ct ON ct.id=p.contact_id JOIN companies co ON co.id=p.company_id WHERE p.status='approved' AND ct.permission_status='confirmed' AND ct.permission_basis<>'' AND ct.permission_evidence IS NOT NULL AND ct.permission_evidence<>'' AND ct.validation_status='valid' AND NOT EXISTS(SELECT 1 FROM suppression s WHERE s.email=ct.email) ORDER BY p.id LIMIT ".(int)$limit;
 foreach(db()->query($sql) as $p){if(!eligible($p))continue;
 // Claim before network call. On uncertain response, mark failed for human review rather than retry automatically.
 $s=db()->prepare("UPDATE proposals SET status='failed' WHERE id=? AND status='approved'");$s->execute([$p['id']]);if(!$s->rowCount())continue;
 try{$response=api_json('https://api.brevo.com/v3/smtp/email',['api-key: '.$c['brevo_api_key'],'Content-Type: application/json','Accept: application/json'],['sender'=>['name'=>$c['sender_name'],'email'=>$c['sender_email']],'to'=>[['email'=>$p['email'],'name'=>$p['full_name']?:$p['email']]],'subject'=>$p['subject'],'textContent'=>$p['body']]);
 db()->prepare("UPDATE proposals SET status='sent' WHERE id=?")->execute([$p['id']]);db()->prepare('INSERT INTO mail_events(proposal_id,event_type,detail) VALUES(?,?,?)')->execute([$p['id'],'accepted',json_encode($response)]);$sent++;
 }catch(Throwable $e){db()->prepare('INSERT INTO mail_events(proposal_id,event_type,detail) VALUES(?,?,?)')->execute([$p['id'],'send_error',mb_substr($e->getMessage(),0,500)]);$failed++;}
 }
 audit('send_batch',"accepted=$sent errors=$failed");return [$sent,$failed];}finally{db()->query("SELECT RELEASE_LOCK('gjw_send_batch')");}
}
