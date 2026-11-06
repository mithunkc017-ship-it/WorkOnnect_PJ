<?php // Forgot password: makes a 30-minute token and "emails" the link.
// On localhost there is no mail server, so the email text is also saved in mail_log.txt (not accessible from the browser).
require 'config.php';$sent=false;
if($_SERVER['REQUEST_METHOD']=='POST'){
 $em=trim($_POST['email']);$s=$pdo->prepare("SELECT id FROM users WHERE email=?");$s->execute([$em]);
 if($s->fetch()){$t=bin2hex(random_bytes(16));
  $pdo->prepare("INSERT INTO password_resets(email,token,expires)VALUES(?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE))")->execute([$em,$t]);
  $link='http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['SCRIPT_NAME']).'/reset.php?t='.$t;
  send_mail($em,'Workonnect password reset','Reset your password (valid 30 minutes): '.$link);
  }
 $sent=true;} // same message whether or not the email exists (prevents guessing registered emails)
head('Forgot Password');auth_open('Reset password','Forgot your password?','Enter your email and we will send you a reset link.');
if($sent)echo '<p class="ok">If this email is registered, a reset link has been sent. (On localhost, open mail_log.txt in the project folder to read it.)</p>';?>
<?=fld('Email address','@','<input type="email" name="email" placeholder="you@example.com" autocomplete="email" required>')?>
<button class="cta" data-busy="<?=e(t('Sending…'))?>"><span>Send Reset Link</span><b class="go">→</b></button>
<?php auth_close('Remembered it? <a href="login.php">Login</a>');foot();
