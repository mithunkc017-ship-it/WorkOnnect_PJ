<?php
// Dark mode icon pair: moon (light mode) + sun (dark mode). CSS below shows the right one based on body.dark - no JS needed.
$wkSun =
    '<svg class="wk-i wk-sun" viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>';
$wkDm = $wkSvg("moon", "wk-i wk-moon") . $wkSun;
?>
<style>
.wk-iconbtn .wk-sun{display:none!important}
body.dark .wk-iconbtn .wk-sun{display:inline-block!important}
body.dark .wk-iconbtn .wk-moon{display:none!important}
</style>
<header class="wk-header" id="wkHeader">
<a class="wk-skip" href="#top">Skip to content</a>
<div class="wk-bar" role="navigation" aria-label="Main navigation">
<a class="wk-brand" href="index.php"><img src="icons/icon-192.png" width="36" height="36" alt=""><span class="wk-brand-t">Work<b>onnect</b></span></a>
<ul class="wk-links">
<?php foreach ($wkLinks as $l) { ?>
<li><a class="wk-link<?= $l[2] ?>" href="<?= $l[0] ?>"<?= $l[3] ?>><span><?= $l[1] ?></span></a></li>
<?php } ?>
</ul>
<div class="wk-right">
<div class="wk-util wk-desk">
<?php if ($wkHelp) { ?><a class="wk-quiet" href="help.php"><span>Help</span></a><?php } ?>
<button type="button" class="wk-iconbtn" aria-label="Toggle dark mode" onclick="document.body.classList.toggle('dark');localStorage.d=document.body.classList.contains('dark')?1:'';return false"><?= $wkDm ?></button>
</div>
<?php if ($u) { ?>
<a class="wk-btn wk-btn-ghost wk-btn-sm wk-desk" href="dash.php"><span>Dashboard</span></a>
<?= $wkBell ?>
<div class="wk-acctw wk-desk">
<button type="button" class="wk-acct" id="wkAcct" aria-haspopup="true" aria-expanded="false" aria-controls="wkMenu"><span class="wk-av wk-av-sm"><?= e($wkIni($u["name"])) ?></span><span class="wk-acct-n"><?= e($u["name"]) ?></span><?= $wkSvg("chev") ?></button>
<div class="wk-menu" id="wkMenu">
<?php foreach ($wkMenu as $m) { ?>
<a href="<?= $m[0] ?>"><?= $wkSvg($m[2]) ?><span><?= $m[1] ?></span></a>
<?php } ?>
<a class="wk-out" href="<?= $wkLogout[0] ?>"><?= $wkSvg("arrow") ?><span><?= $wkLogout[1] ?></span></a>
</div>
</div>
<?php } else { ?>
<a class="wk-quiet wk-desk" href="login.php"><span>Login</span></a>
<a class="wk-btn wk-btn-primary wk-btn-sm wk-desk" href="register.php"><span>Register</span></a>
<?php } ?>
<button type="button" class="wk-burger" id="wkBurger" aria-expanded="false" aria-controls="wkPanel" aria-label="Menu"><?= $wkSvg("menu", "wk-i wk-i-open") ?><?= $wkSvg("close", "wk-i wk-i-shut") ?></button>
</div>
</div>
<div class="wk-panel" id="wkPanel">
<ul class="wk-plist">
<?php foreach ($wkLinks as $l) { ?>
<li><a class="<?= $l[2] ?>" href="<?= $l[0] ?>"<?= $l[3] ?>><span><?= $l[1] ?></span></a></li>
<?php } ?>
<?php if ($wkHelp) { ?><li><a href="help.php"><span>Help</span></a></li><?php } ?>
</ul>
<?php if ($u) { ?>
<ul class="wk-plist wk-plist-2">
<li><a href="dash.php"><?= $wkSvg("activity") ?><span>Dashboard</span></a></li>
<?php foreach ($wkMenu as $m) { ?>
<li><a href="<?= $m[0] ?>"><?= $wkSvg($m[2]) ?><span><?= $m[1] ?></span></a></li>
<?php } ?>
<li><a href="<?= $wkLogout[0] ?>"><?= $wkSvg("arrow") ?><span><?= $wkLogout[1] ?></span></a></li>
</ul>
<?php } else { ?>
<div class="wk-pbtns"><a class="wk-btn wk-btn-ghost" href="login.php"><span>Login</span></a><a class="wk-btn wk-btn-primary" href="register.php"><span>Register</span></a></div>
<?php } ?>
<div class="wk-putil">
<button type="button" class="wk-iconbtn" aria-label="Toggle dark mode" onclick="document.body.classList.toggle('dark');localStorage.d=document.body.classList.contains('dark')?1:'';return false"><?= $wkDm ?></button>
</div>
</div>
</header>