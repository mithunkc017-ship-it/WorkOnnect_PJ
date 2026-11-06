<?php // Offer comparison table for the customer (included by job.php): price, rating, experience, distance, "Best value" tags
$o=$pdo->prepare("SELECT o.*,u.name,u.verified,u.lat,u.lng,(SELECT ROUND(AVG(stars),1) FROM reviews WHERE worker_id=u.id) r,(SELECT COUNT(*) FROM reviews WHERE worker_id=u.id) rc,(SELECT COUNT(*) FROM jobs WHERE worker_id=u.id AND status='completed') done FROM offers o JOIN users u ON u.id=o.worker_id WHERE o.job_id=? AND o.status='pending'");$o->execute([$id]);$of=$o->fetchAll();
if(!$of){echo '<p>No offers yet.</p>';return;}
$jl=preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/',$j['location'],$mm)?[(float)$mm[1],(float)$mm[2]]:null; // distance only when the job location is a GPS pin
$pr=array_column($of,'price');$lo=min($pr);$hi=max($pr);
foreach($of as &$x){$x['d']=($jl&&$x['lat']!==null)?6371*acos(min(1,cos(deg2rad($jl[0]))*cos(deg2rad($x['lat']))*cos(deg2rad($x['lng'])-deg2rad($jl[1]))+sin(deg2rad($jl[0]))*sin(deg2rad($x['lat'])))):null;
 $x['sc']=($x['r']?$x['r']:3.5)/5*40+($x['verified']?10:0)+min($x['done'],20)/20*20+($x['d']!==null?max(0,1-$x['d']/25)*10:5)+($hi>$lo?20*($hi-$x['price'])/($hi-$lo):20);}unset($x);
usort($of,function($a,$b){return $b['sc']<=>$a['sc'];});
$best=$of[0]['id'];$cheap=array_search($lo,array_column($of,'price'));$topr=0;foreach($of as $i=>$x)if($x['r']>($of[$topr]['r']??0))$topr=$i;
echo '<div style="overflow-x:auto"><table><tr><th>Worker</th><th>Price</th><th>Rating</th><th>Jobs done</th><th>Distance</th><th>Message</th><th></th></tr>';
foreach($of as $i=>$x){$tags=($x['id']==$best?'<span class="ok">⭐ Best value</span> ':'').($i===$cheap&&count($of)>1?'<span class="ok">💰 Lowest price</span> ':'').($i===$topr&&$x['r']>0?'<span class="ok">🏆 Highest rated</span>':'');
 echo '<tr><td><b><a href="worker.php?id='.$x['worker_id'].'">'.e($x['name']).'</a></b> '.badge($x['verified']).'<br>'.$tags.'</td><td><b>₹'.$x['price'].'</b></td><td>'.($x['r']?$x['r'].' ⭐ ('.$x['rc'].')':'New').'</td><td>'.$x['done'].'</td><td>'.($x['d']!==null?round($x['d'],1).' km':'–').'</td><td>'.e($x['message']).'</td><td>';
 if($j['status']=='open'&&$j['customer_id']==$u['id'])echo '<form method="post"><input type="hidden" name="a" value="accept"><input type="hidden" name="w" value="'.$x['worker_id'].'"><button>Accept</button></form><form method="post"><input type="hidden" name="a" value="reject"><input type="hidden" name="w" value="'.$x['worker_id'].'"><button style="background:#c00">Reject</button></form>';
 echo '</td></tr>';}
echo '</table></div><p><small>"Best value" mixes rating, experience, verification, price and distance.</small></p>';
