<?php // Leaderboard: top workers of this month (completed jobs, then rating). #1 is "Worker of the Month"
require 'config.php';head('Leaderboard');
$s=$pdo->query("SELECT u.id,u.name,u.skill,u.verified,(SELECT COUNT(*) FROM jobs j WHERE j.worker_id=u.id AND j.status='completed' AND MONTH(COALESCE(j.done_at,j.created))=MONTH(NOW()) AND YEAR(COALESCE(j.done_at,j.created))=YEAR(NOW())) m,(SELECT ROUND(AVG(stars),1) FROM reviews WHERE worker_id=u.id) r FROM users u WHERE u.role='worker' HAVING m>0 ORDER BY m DESC,r DESC LIMIT 10");
echo '<h2>🏆 Top Workers This Month</h2><div class="grid">';$i=0;
foreach($s as $w){echo '<div class="card"><h2>'.($i<3?['🥇','🥈','🥉'][$i]:'#'.($i+1)).' <a href="worker.php?id='.$w['id'].'">'.e($w['name']).'</a></h2>'.($i==0?'<span class="ok">🏆 Worker of the Month</span><br>':'').e($w['skill']).' '.badge($w['verified']).'<br>'.$w['m'].' jobs completed · '.($w['r']?$w['r'].' ⭐':'no rating').'</div>';$i++;}
if(!$i)echo '<p>No completed jobs this month yet.</p>';echo '</div>';foot();
