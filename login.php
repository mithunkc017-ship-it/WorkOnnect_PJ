<?php // Login page: checks password, limits failed attempts per IP address (5 per 5 minutes)
require 'config.php';$err='';$ip=$_SERVER['REMOTE_ADDR'];
$n=$pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip=? AND ts>DATE_SUB(NOW(),INTERVAL 5 MINUTE)");$n->execute([$ip]);
if($n->fetchColumn()>=5){$err='Too many failed attempts. Try again in 5 minutes.';}
elseif($_SERVER['REQUEST_METHOD']=='POST'){
 $s=$pdo->prepare("SELECT * FROM users WHERE email=?");$s->execute([trim($_POST['email'])]);$u=$s->fetch();
 if($u&&$u['banned']&&password_verify($_POST['password'],$u['password'])){$err='Your account is suspended. Reason: '.$u['ban_reason'];}elseif($u&&password_verify($_POST['password'],$u['password'])){
  if(!$u['email_verified']){send_otp($pdo,$u['email']);header('Location: verify_email.php?e='.urlencode($u['email']));exit;}if($u['role']=='admin'&&ADMIN_2FA){send_otp($pdo,$u['email']);$_SESSION['pre2fa']=['id'=>$u['id'],'t'=>time()];lh($pdo,$u['id'],$u['email'],1,'password ok, 2-step pending');header('Location: admin_2fa.php');exit;}session_regenerate_id(true);$_SESSION['u']=$u;logit('Logged in',$u['id']);lh($pdo,$u['id'],$u['email'],1,'login');
  $pdo->prepare("DELETE FROM login_attempts WHERE ip=?")->execute([$ip]);header('Location: dash.php');exit;}
 $pdo->prepare("INSERT INTO login_attempts(ip)VALUES(?)")->execute([$ip]);$err=$err?:'Wrong email or password.';lh($pdo,$u['id']??null,trim($_POST['email']),0,($u&&$u['banned'])?'suspended account':($u?'wrong password':'unknown email'));}
head('Login');auth_open('Welcome back','Log in to your account.','Log in to Workonnect to continue.');?>
<p class="err"><?=e($err)?></p><?=isset($_GET['ok'])?'<p class="ok">Email verified! Please login.</p>':''?>
<?=fld('Email address','@','<input type="email" name="email" value="'.e($_POST['email']??'').'" placeholder="you@example.com" autocomplete="email" required>')?>
<?=pwf('Password','password','Your password',false,'current-password')?>
<button class="cta" data-busy="<?=e(t('Logging in…'))?>"><span>Log in</span><b class="go">→</b></button>
<p class="alt" style="margin-top:14px"><a href="forgot.php">Forgot password?</a></p>
<?php auth_close('New to Workonnect? <a href="register.php">Create an account</a>');foot();
