<?php // Admin: activity log (who did what and when)
require 'config.php';need('admin');head('Activity Log');?>
<h2>📜 Activity Log</h2><table><tr><th>Time</th><th>User</th><th>Action</th></tr>
<?php foreach($pdo->query("SELECT a.*,u.name FROM activity_log a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 100") as $x)echo '<tr><td>'.$x['created'].'</td><td>'.e($x['name']??'—').'</td><td>'.e($x['action']).'</td></tr>';?></table>
<?php foot();
