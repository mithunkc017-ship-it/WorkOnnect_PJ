<?php // Homepage v13: hero search, live stats, services, features, testimonials, FAQ
require 'config.php';head('Home');
$c=fn($q)=>(float)$pdo->query($q)->fetchColumn();
$nW=(int)$c("SELECT COUNT(*) FROM users WHERE role='worker'");$nD=(int)$c("SELECT COUNT(*) FROM jobs WHERE status='completed'");
$nV=(int)$c("SELECT COUNT(*) FROM users WHERE role='worker' AND verified=1");$avg=round($c("SELECT AVG(stars) FROM reviews"),1);
$w=$pdo->query("SELECT u.name,u.skill,(SELECT ROUND(AVG(stars),1) FROM reviews WHERE worker_id=u.id) r,(SELECT COUNT(*) FROM reviews WHERE worker_id=u.id) rc FROM users u WHERE role='worker' AND verified=1 ORDER BY r DESC LIMIT 6")->fetchAll();
$q=$pdo->query("SELECT r.stars,r.comment,u.name FROM reviews r JOIN users u ON u.id=r.customer_id WHERE r.stars>=4 AND r.comment<>'' ORDER BY r.id DESC LIMIT 3")->fetchAll();
$ico=['Electrician'=>'⚡','Plumber'=>'🚿','Carpenter'=>'🪚','Painter'=>'🎨','Cleaner'=>'🧹','Driver'=>'🚗','Mechanic'=>'🔧','AC Technician'=>'❄️'];?>
<div class="hero"><h1><?=t('Find Skilled Workers Near You')?></h1><p><?=t('Post a job, get offers, track progress – all in one place.')?></p>
<form class="hs" action="workers.php" method="get"><select name="cat"><option value="">All services</option><?php foreach($CATS as $x)echo '<option>'.e($x).'</option>';?></select><input name="ar" placeholder="Your area (e.g. Sagar)"><button>🔍 Search</button></form>
<a class="btn" href="post_job.php"><?=t('Post a Job')?></a> <a class="btn" href="register.php"><?=t('Join as Worker')?></a>
<div class="trust"><span>✔ ID-verified workers</span><span>🚨 Emergency booking</span><span>💬 Live chat</span><span>🗣 ಕನ್ನಡ + English</span></div></div>
<div class="stats"><div class="stat"><b data-n="<?=$nW?>">0</b><span>Workers</span></div><div class="stat"><b data-n="<?=$nV?>">0</b><span>Verified</span></div><div class="stat"><b data-n="<?=$nD?>">0</b><span>Jobs completed</span></div><div class="stat"><b><?=$avg?:'–'?> ⭐</b><span>Average rating</span></div></div>
<h2><?=t('Services')?></h2><div class="grid"><?php foreach($CATS as $x)echo '<a class="card svc" href="workers.php?cat='.urlencode($x).'"><i>'.($ico[$x]??'🔧').'</i>'.e($x).'</a>';?></div>
<h2><?=t('How it works')?></h2><div class="grid"><div class="card"><?=t('1. Customer posts a job')?></div><div class="card"><?=t('2. Workers send offers')?></div><div class="card"><?=t('3. Accept the best offer')?></div><div class="card"><?=t('4. Track job progress')?></div></div>
<h2>Why Workonnect?</h2><div class="grid">
<div class="card feat"><i>🛡️</i><b>Verified &amp; trusted</b><p>Workers upload ID proof; Top Rated, Gold, Silver and Bronze levels show real performance.</p></div>
<div class="card feat"><i>🚨</i><b>Emergency jobs</b><p>Mark a job urgent and it appears first for workers.</p></div>
<div class="card feat"><i>📍</i><b>Nearby workers</b><p>Use "Near me" to find the closest available worker.</p></div>
<div class="card feat"><i>💸</i><b>Fair prices</b><p>Compare offers, apply coupons, pay from wallet. Only a 10% platform fee.</p></div>
<div class="card feat"><i>💬</i><b>Chat &amp; tracking</b><p>Talk to your worker and follow every step of the job.</p></div>
<div class="card feat"><i>🔔</i><b>Instant alerts</b><p>Live notification bell for offers, payments and updates.</p></div></div>
<h2><?=t('Verified Workers')?></h2><div class="grid"><?php foreach($w as $x)echo '<div class="card"><b>'.e($x['name']).'</b><br>'.e($x['skill']).'<br>'.($x['r']?$x['r'].' ⭐ ('.$x['rc'].')<br>':'').badge(1).'</div>';if(!$w)echo '<p>No verified workers yet.</p>';?></div>
<?php if($q){echo '<h2>What customers say</h2><div class="grid">';foreach($q as $x)echo '<div class="card quote">'.str_repeat('⭐',(int)$x['stars']).'<q>'.e($x['comment']).'</q><small>– '.e($x['name']).'</small></div>';echo '</div>';}?>
<h2>Quick answers</h2>
<details class="card"><summary>Is payment safe?</summary><p>Pay by UPI, card, net banking, cash or wallet. We never store your card or UPI details.</p></details>
<details class="card"><summary>How do I become a verified worker?</summary><p>Register as a worker, open "Get Verified" and upload your ID. Admin approves it and your badge appears.</p></details>
<details class="card"><summary>What if the job goes wrong?</summary><p>You can cancel, request a refund, or file a complaint from the job page.</p></details>
<button id="up" onclick="scrollTo({top:0,behavior:'smooth'})" title="Back to top" hidden>↑</button>
<script>document.querySelectorAll('.stat b[data-n]').forEach(function(b){var n=+b.dataset.n,i=0,s=Math.max(1,Math.ceil(n/40));var t=setInterval(function(){i=Math.min(n,i+s);b.textContent=i;if(i>=n)clearInterval(t)},30)});
addEventListener('scroll',function(){document.getElementById('up').hidden=scrollY<400});</script>
<?php foot();
