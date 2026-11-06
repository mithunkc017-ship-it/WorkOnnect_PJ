<?php // Customer wallet: demo top-up, balance, history and referral code
require 'config.php';need('customer');$u=me();
if(!$u['ref_code']){$rc='WK'.strtoupper(substr(bin2hex(random_bytes(3)),0,5));$pdo->prepare("UPDATE users SET ref_code=? WHERE id=?")->execute([$rc,$u['id']]);$u['ref_code']=$rc;}
if($_SERVER['REQUEST_METHOD']=='POST'){$a=(int)$_POST['amt'];if($a>=10&&$a<=10000){wallet($pdo,$u['id'],$a,'Wallet top-up (demo)');logit('Wallet top-up ₹'.$a);}header('Location: wallet.php');exit;}
head('Wallet');echo d_top('wallet.php');?>
<h2><span class="material-icons mi">account_balance_wallet</span> My Wallet</h2><div class="card"><h1>₹<?=(int)$u['wallet']?></h1>
<form method="post" style="display:flex;gap:8px"><input type="number" name="amt" min="10" max="10000" placeholder="Amount (₹10 – ₹10,000)" required><button>Add money (demo)</button></form><small>Demo top-up: no real money is used.</small></div>
<div class="card" style="margin:12px 0"><span class="material-icons mi">card_giftcard</span> Your referral code: <b><?=e($u['ref_code'])?></b><br><small>When a friend registers with your code and pays for their first job, you both get ₹50 in your wallet.</small></div>
<h3>History</h3><table><tr><th>Date</th><th>Note</th><th>Amount</th></tr>
<?php $s=$pdo->prepare("SELECT * FROM wallet_tx WHERE user_id=? ORDER BY id DESC LIMIT 50");$s->execute([$u['id']]);
foreach($s as $x)echo '<tr><td>'.$x['created'].'</td><td>'.e($x['note']).'</td><td style="color:'.($x['amount']<0?'#c00':'#0a7a3a').'">'.($x['amount']<0?'-':'+').'₹'.abs($x['amount']).'</td></tr>';?></table>
<?php foot();
