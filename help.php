<?php // Help / FAQ page and Contact Us form (the admin also sees the received messages here)
require 'config.php';$u=me();$sent=false;
if($_SERVER['REQUEST_METHOD']=='POST'&&trim($_POST['msg']??'')!==''){
 $pdo->prepare("INSERT INTO contacts(name,email,msg)VALUES(?,?,?)")->execute([substr($_POST['name'],0,80),substr($_POST['email'],0,100),substr($_POST['msg'],0,500)]);
 foreach($pdo->query("SELECT id FROM users WHERE role='admin'") as $a)notify($a['id'],'New contact message from '.$_POST['name'],'help.php');$sent=true;}
head('Help');?>
<h2>❓ Help &amp; FAQ</h2>
<details class="card"><summary>How do I post a job?</summary>Register as a customer, click Post Job, fill the details and submit. Workers will send you offers.</details>
<details class="card"><summary>Can I hire one particular worker?</summary>Yes. Open the worker's profile and click Hire this worker. The worker can accept or decline.</details>
<details class="card"><summary>When do I pay?</summary>After the worker marks the job completed and you confirm it. Use a coupon code on the payment page for a discount.</details>
<details class="card"><summary>How do workers get the Verified badge?</summary>Workers upload an ID proof under Get Verified. The admin reviews it.</details>
<details class="card"><summary>Something went wrong with a job. What now?</summary>Open the job and click Report a problem. The admin will look into it.</details>
<h2>✉ Contact Us</h2>
<form class="card" method="post"><?php if($sent)echo '<p class="ok">Thanks! Your message was sent to the admin.</p>';?>
<input name="name" placeholder="Your name" value="<?=e($u['name']??'')?>" required><input type="email" name="email" placeholder="Your email" value="<?=e($u['email']??'')?>" required>
<textarea name="msg" rows="3" maxlength="500" placeholder="Your message" required></textarea><button>Send</button></form>
<?php if($u&&$u['role']=='admin'){echo '<h2>📥 Messages</h2><table><tr><th>From</th><th>Email</th><th>Message</th><th>Date</th></tr>';
foreach($pdo->query("SELECT * FROM contacts ORDER BY id DESC LIMIT 50") as $x)echo '<tr><td>'.e($x['name']).'</td><td>'.e($x['email']).'</td><td>'.e($x['msg']).'</td><td>'.$x['created'].'</td></tr>';echo '</table>';}
foot();
