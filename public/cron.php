<?php
declare(strict_types=1);
require dirname(__DIR__).'/private/bootstrap.php';
// Use CLI only; never expose the cron token via a public URL.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$token=$argv[1]??'';if(!hash_equals((string)$c['cron_token'],$token)){fwrite(STDERR,"Unauthorized\n");exit(1);}
$mode=$argv[2]??'discovery';
try{
 if($mode==='discovery'){$markets=['Saudi Arabia','UAE','Qatar','Kuwait','Oman','Bahrain','Worldwide'];$industries=['construction','healthcare','hospitality','logistics','manufacturing'];$today=(int)db()->query('SELECT COALESCE(SUM(added_count),0) FROM discovery_runs WHERE DATE(created_at)=CURDATE()')->fetchColumn();if($today>=(int)$c['daily_discovery_target']){echo "Target reached\n";exit;}$index=(int)date('z');[$found,$added]=discover($markets[$index%count($markets)],$industries[$index%count($industries)]);echo "Found $found, added $added\n";}
 elseif($mode==='send') {[$sent,$failed]=send_approved(10);echo "Accepted $sent; review $failed\n";}
 else{throw new RuntimeException('Unknown job');}
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
