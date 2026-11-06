<?php // Demand heatmap: which areas and services have the most job requests (last 60 days). For workers and admin.
require 'config.php';need();$u=me();if($u['role']=='customer'){header('Location: dashboard.php');exit;}
$m=[];$tot=[];$cat=[];
foreach($pdo->query("SELECT location,category FROM jobs WHERE created>DATE_SUB(NOW(),INTERVAL 60 DAY)")->fetchAll() as $r){
 $l=strtolower(trim($r['location']));if(preg_match('/^-?\d+(\.\d+)?\s*,\s*-?\d+/',$l))$l='gps pin';$l=substr($l,0,25);
 $m[$l][$r['category']]=($m[$l][$r['category']]??0)+1;$tot[$l]=($tot[$l]??0)+1;$cat[$r['category']]=($cat[$r['category']]??0)+1;}
arsort($tot);$tot=array_slice($tot,0,10,true);arsort($cat);$mx=1;foreach($tot as $l=>$n)foreach($m[$l] as $v)$mx=max($mx,$v);
head('Demand map');?><h2>🔥 Demand Map <small>(last 60 days)</small></h2>
<?php if(!$tot)echo '<p>No jobs yet.</p>';else{echo '<p>Busiest area: <b>'.e(key($tot)).'</b> · Most requested service: <b>'.e(key($cat)).'</b></p><div style="overflow-x:auto"><table><tr><th>Area</th>';
foreach($CATS as $c)echo '<th>'.e($c).'</th>';echo '<th>Total</th></tr>';
foreach($tot as $l=>$n){echo '<tr><td><b>'.e($l).'</b></td>';foreach($CATS as $c){$v=$m[$l][$c]??0;echo '<td style="text-align:center;background:rgba(255,120,0,'.($v?round(.15+.75*$v/$mx,2):0).')">'.($v?:'').'</td>';}echo '<td>'.$n.'</td></tr>';}echo '</table></div><p><small>Darker = more requests. Workers: go where demand is high. Admin: find areas that need more workers.</small></p>';}
foot();
