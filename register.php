<?php // Create account (customer or worker) - card design with confirm password, strength meter and terms
require 'config.php';$err='';$v=function($k){return e($_POST[$k]??'');};$role=$_POST['role']??'customer';$kn=($_SESSION['lang']??'en')=='kn';
if($_SERVER['REQUEST_METHOD']=='POST'){
 $pw=$_POST['password']??'';
 if($role=='worker'&&($_POST['skill']??'')==''){$err='Workers must choose a skill.';}
 elseif(!preg_match('/^[0-9]{10}$/',trim($_POST['phone']??''))){$err='Enter a valid 10-digit phone number.';}
 elseif(!filter_var(trim($_POST['email']??''),FILTER_VALIDATE_EMAIL)){$err='Enter a valid email address.';}
 elseif(strlen($pw)<6){$err='Password must be at least 6 characters.';}
 elseif($pw!==($_POST['password2']??'')){$err='Passwords do not match.';}
 elseif(empty($_POST['terms'])){$err='Please accept the Terms and Privacy Policy.';}
 else{try{$rb=$pdo->prepare("SELECT id FROM users WHERE ref_code=?");$rb->execute([strtoupper(trim($_POST['ref']??''))]);$rb=$rb->fetchColumn()?:null;
   $pdo->prepare("INSERT INTO users(name,email,phone,password,role,skill,referred_by)VALUES(?,?,?,?,?,?,?)")->execute([trim($_POST['name']),trim($_POST['email']),trim($_POST['phone']),password_hash($pw,PASSWORD_DEFAULT),$role=='worker'?'worker':'customer',$role=='worker'?$_POST['skill']:'',$rb]);
  }catch(Exception $x){$err='Email already registered.';}
  if(!$err){send_otp($pdo,trim($_POST['email']));header('Location: verify_email.php?e='.urlencode(trim($_POST['email'])));exit;}}}
head('Register');auth_open('Join Workonnect','Create your account.','Connect with Workonnect and get started.');?>
<p class="err"><?=e($err)?></p>
<?=fld('Full name','◉','<input name="name" value="'.$v('name').'" placeholder="Your full name" autocomplete="name" required>')?>
<?=fld('Email address','@','<input type="email" name="email" value="'.$v('email').'" placeholder="you@example.com" autocomplete="email" required>')?>
<?=fld('Phone number','<span class="material-icons mi">phone</span>','<input name="phone" value="'.$v('phone').'" placeholder="10-digit mobile number" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" autocomplete="tel" required>')?>
<?=pwf('Password','password','Create a password',true,'new-password')?>
<?=pwf('Confirm password','password2','Type it again',false,'new-password')?>
<?=fld('Joining as','◆','<select name="role" id="role"><option value="customer"'.($role=='customer'?' selected':'').'>I need work done (Customer)</option><option value="worker"'.($role=='worker'?' selected':'').'>I am a worker</option></select>')?>
<div id="skillbox"<?=$role=='worker'?'':' hidden'?>><?php $o='<option value="">Skill (workers only)</option>';foreach($CATS as $c)$o.='<option'.(($_POST['skill']??'')==$c?' selected':'').'>'.e($c).'</option>';echo fld('Your skill','<span class="material-icons mi">hardware</span>','<select name="skill">'.$o.'</select>');?></div>
<?=fld('Referral code (optional)','#','<input name="ref" value="'.$v('ref').'" placeholder="Have a code? Enter it" autocomplete="off">')?>
<label class="chk"><input type="checkbox" name="terms" value="1" required><span><?=$kn?'ನಾನು Workonnect <a href="terms.php" target="_blank">ನಿಯಮಗಳು</a> ಮತ್ತು <a href="privacy.php" target="_blank">ಗೌಪ್ಯತಾ ನೀತಿ</a>ಯನ್ನು ಒಪ್ಪುತ್ತೇನೆ.':'I agree to the Workonnect <a href="terms.php" target="_blank">Terms</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>.'?></span></label>
<button class="cta" data-busy="<?=e(t('Creating…'))?>"><span>Create account</span><b class="go">→</b></button>
<script>role.onchange=function(){skillbox.hidden=this.value!="worker"}</script>
<?php auth_close('Already have an account? <a href="login.php">Login</a>');foot();
