<?php // Public read-only job status page for the "share with a trusted person" link
require 'config.php';$t=$_GET['t']??'';
$s=$pdo->prepare("SELECT j.*,w.name wn,w.phone wp,c.name cn FROM jobs j JOIN users c ON c.id=j.customer_id LEFT JOIN users w ON w.id=j.worker_id WHERE j.share_token=?");$s->execute([$t]);$j=$s->fetch();
if(!$t||!$j)die('Link not valid.');
head('Job status');?>
<div class="card"><h2>🛡 Job status</h2><p><b><?=e($j['title'])?></b> (<?=e($j['category'])?>)</p>
<p>Customer: <?=e($j['cn'])?><br>Worker: <?=e($j['wn'])?> · 📞 <?=e($j['wp'])?></p><p>Status: <b><?=e($j['status'])?></b> · 📍 <?=e($j['location'])?></p>
<iframe title="map" width="100%" height="220" style="border:0;border-radius:8px" src="https://www.google.com/maps?q=<?=urlencode($j['location'])?>&output=embed"></iframe></div><?php foot();
