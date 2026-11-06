<?php // Admin: login history (successful and failed logins with IP address and browser)
require 'config.php';need('admin');$bad=isset($_GET['failed']);
$s=$pdo->query("SELECT h.*,u.name,u.role FROM login_history h LEFT JOIN users u ON u.id=h.user_id".($bad?" WHERE h.ok=0":"")." ORDER BY h.id DESC LIMIT 200");
head('Login history');?><h2>🔐 Login History</h2>
<p><a class="btn" href="login_history.php">All</a> <a class="btn" href="?failed=1">Failed only</a></p>
<div style="overflow-x:auto"><table><tr><th>Time</th><th>User</th><th>Result</th><th>Note</th><th>IP</th><th>Browser</th></tr>
<?php foreach($s as $x)echo '<tr><td>'.$x['created'].'</td><td>'.e($x['name']?:$x['email']).($x['role']?' <small>('.$x['role'].')</small>':'').'</td><td>'.($x['ok']?'✅':'<b class="err">❌ failed</b>').'</td><td>'.e($x['note']).'</td><td>'.e($x['ip']).'</td><td><small>'.e(substr($x['ua'],0,60)).'</small></td></tr>';?></table></div><?php foot();
