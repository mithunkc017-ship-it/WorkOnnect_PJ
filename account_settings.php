<?php // Settings page (all roles): edit profile (name; UPI ID for workers) + display / notification preferences.
// Profile changes use the same users table the rest of the site uses; the preferences are stored only in this browser.
require "config.php";
need();
$u = me();
$role = $u["role"];
$err = "";
$msg = $_SESSION["st_msg"] ?? "";
unset($_SESSION["st_msg"]);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // (the CSRF token is checked for every POST in config.php)
    $name = trim(preg_replace("/\s+/", " ", (string) ($_POST["name"] ?? "")));
    $upi = trim((string) ($_POST["upi_id"] ?? ""));
    if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
        $err = "Name must be 2 to 60 characters.";
    } elseif ($role == "worker" && $upi !== "" && !validUpi($upi)) {
        $err = "That UPI ID does not look right. Example: yourname@upi";
    } else {
        $pdo->prepare("UPDATE users SET name=? WHERE id=?")->execute([$name, $u["id"]]);
        if ($role == "worker") {
            $pdo->prepare("UPDATE users SET upi_id=? WHERE id=?")->execute([$upi, $u["id"]]);
        }
        logit("Updated profile (settings)", $u["id"]);
        $_SESSION["st_msg"] = "Profile saved.";
        header("Location: account_settings.php");
        exit();
    }
}

head("Settings");
echo d_top("account_settings.php");
$shownName = $err ? (string) ($_POST["name"] ?? $u["name"]) : (string) $u["name"];
$shownUpi = $err ? (string) ($_POST["upi_id"] ?? "") : (string) ($u["upi_id"] ?? "");
?>
<div class="pd">
  <div class="pd-head">
    <div><h1>Settings</h1><p>Edit your profile and choose how Workonnect looks and behaves for you.</p></div>
    <div class="pd-btns"><a class="pd-b" href="dash.php"><span class="material-icons mi">arrow_back</span> Back to dashboard</a></div>
  </div>

  <?= $err ? '<div class="pd-card st-alert bad"><span class="material-icons mi-xs">error</span> ' . e($err) . "</div>" : "" ?>
  <?= $msg ? '<div class="pd-card st-alert ok"><span class="material-icons mi-xs">check_circle</span> ' . e($msg) . "</div>" : "" ?>

  <div class="pd-g2">
    <div class="pd-card">
      <div class="pd-ct">Edit profile</div>
      <div class="st-me">
        <?= d_av($u["name"], 56) ?>
        <div>
          <b><?= e($u["name"]) ?></b>
          <span><?= e($u["email"] ?? "") ?></span><br>
          <span class="st-tag"><?= e(ucfirst($role)) ?></span>
          <?= $role == "worker" ? badge($u["verified"] ?? 0) : "" ?>
        </div>
      </div>
      <form method="post" class="st-form" autocomplete="off">
        <label for="st-name">Full name</label>
        <input id="st-name" name="name" value="<?= e($shownName) ?>" minlength="2" maxlength="60" required>

        <label for="st-mail">E-mail <small>(used for your login code – cannot be changed here)</small></label>
        <input id="st-mail" value="<?= e($u["email"] ?? "") ?>" disabled>

        <?php if ($role == "worker") { ?>
        <label for="st-upi">UPI ID <small>(customers and the admin pay you here)</small></label>
        <input id="st-upi" name="upi_id" value="<?= e($shownUpi) ?>" placeholder="yourname@upi" maxlength="80">
        <?php } ?>

        <div class="st-actions">
          <button class="st-save" type="submit"><span class="material-icons mi">save</span> Save changes</button>
          <?= $role == "worker" ? '<a class="st-btn" href="profile.php"><span class="material-icons mi">person</span> Skills &amp; work details</a>' : "" ?>
        </div>
      </form>
    </div>

    <div class="pd-card">
      <div class="pd-ct">Display</div>
      <div class="st-row">
        <div>Dark mode<small>Easier on the eyes at night</small></div>
        <button type="button" class="st-sw" data-pref="dark" role="switch" aria-checked="false" aria-label="Dark mode"></button>
      </div>
      <div class="st-row">
        <div>Compact layout<small>Tighter cards, more on screen</small></div>
        <button type="button" class="st-sw" data-pref="compact" role="switch" aria-checked="false" aria-label="Compact layout"></button>
      </div>
      <div class="st-row">
        <div>Larger text<small>Bigger, easier-to-read letters</small></div>
        <button type="button" class="st-sw" data-pref="big" role="switch" aria-checked="false" aria-label="Larger text"></button>
      </div>
      <div class="st-row">
        <div>Reduce animations<small>Turn off moving effects</small></div>
        <button type="button" class="st-sw" data-pref="calm" role="switch" aria-checked="false" aria-label="Reduce animations"></button>
      </div>
    </div>
  </div>

  <div class="pd-g2">
    <div class="pd-card">
      <div class="pd-ct">Notifications</div>
      <div class="st-row">
        <div>Browser alerts<small id="st-nstate">Checking…</small></div>
        <button type="button" class="st-btn" id="st-nbtn"><span class="material-icons mi">notifications_active</span> Enable</button>
      </div>
      <div class="st-row">
        <div>Alert sound<small>Short beep when a new alert arrives (this browser)</small></div>
        <button type="button" class="st-sw" data-pref="sound" role="switch" aria-checked="false" aria-label="Alert sound"></button>
      </div>
      <div class="st-row">
        <div>Test alert<small>See what a notification looks like</small></div>
        <button type="button" class="st-btn" id="st-test"><span class="material-icons mi">send</span> Send test</button>
      </div>
    </div>

    <div class="pd-card">
      <div class="pd-ct">Account &amp; data</div>
      <div class="st-row">
        <div>Log out<small>Sign out of this device</small></div>
        <a class="st-btn red" href="logout.php"><span class="material-icons mi">logout</span> Logout</a>
      </div>
      <div class="st-row">
        <div>Reset display settings<small>Clears the choices above on this browser only</small></div>
        <button type="button" class="st-btn" id="st-reset"><span class="material-icons mi">restart_alt</span> Reset</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  // Preference → localStorage key and the class it puts on <body>. config.php's foot() re-applies them on every page.
  var P = { dark: ["d", "dark"], compact: ["cp", "compact"], big: ["bt", "bigtext"], calm: ["rm", "calm"], sound: ["snd", ""] };
  function get(k) { try { return !!localStorage.getItem(k); } catch (e) { return false; } }
  function set(k, on) { try { if (on) localStorage.setItem(k, "1"); else localStorage.removeItem(k); } catch (e) {} }

  var sws = document.querySelectorAll(".st-sw");
  function paint() {
    sws.forEach(function (b) {
      var d = P[b.dataset.pref], on = d[1] ? document.body.classList.contains(d[1]) : get(d[0]);
      b.classList.toggle("on", on);
      b.setAttribute("aria-checked", on ? "true" : "false");
    });
  }
  sws.forEach(function (b) {
    b.addEventListener("click", function () {
      var d = P[b.dataset.pref], on;
      if (d[1]) { on = document.body.classList.toggle(d[1]); } else { on = !get(d[0]); }
      set(d[0], on);
      paint();
      if (b.dataset.pref === "sound" && on) beep();
    });
  });
  paint();

  document.getElementById("st-reset").addEventListener("click", function () {
    Object.keys(P).forEach(function (k) { set(P[k][0], false); if (P[k][1]) document.body.classList.remove(P[k][1]); });
    paint();
  });

  // notifications
  var st = document.getElementById("st-nstate"), nb = document.getElementById("st-nbtn"), tb = document.getElementById("st-test");
  function note() {
    if (!window.Notification) { st.textContent = "Not supported in this browser"; nb.style.display = "none"; tb.disabled = true; return; }
    var p = Notification.permission;
    st.textContent = p === "granted" ? "On – you will get alerts for new activity"
                  : p === "denied" ? "Blocked – allow it in your browser's site settings"
                  : "Off – turn on to get alerts";
    nb.style.display = p === "default" ? "" : "none";
    tb.disabled = p !== "granted";
  }
  nb.addEventListener("click", function () { Notification.requestPermission().then(note); });
  tb.addEventListener("click", function () {
    try { new Notification("Workonnect", { body: "This is how your alerts will look.", icon: "icons/icon-192.png" }); } catch (e) {}
    if (get("snd")) beep();
  });
  note();

  function beep() {
    try {
      var C = window.AudioContext || window.webkitAudioContext, c = new C(), o = c.createOscillator(), g = c.createGain();
      o.type = "sine"; o.frequency.value = 880; g.gain.value = .08;
      o.connect(g); g.connect(c.destination); o.start(); o.stop(c.currentTime + .18);
    } catch (e) {}
  }
})();
</script>
<?php foot();