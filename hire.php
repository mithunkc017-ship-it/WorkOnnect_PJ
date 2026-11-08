<?php // Customer sends a job request directly to one chosen worker (worker accepts or declines on the job page)
require "config.php";
need("customer");
$u = me();
$w = (int) ($_GET["w"] ?? 0);
$q = $pdo->prepare("SELECT * FROM users WHERE id=? AND role='worker'");
$q->execute([$w]);
$wk = $q->fetch();
if (!$wk) {
    die("Worker not found");
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (
        !preg_match('/^[0-9]{10}$/', $_POST["phone"]) ||
        (int) $_POST["price"] <= 0
    ) {
        die(
            "Enter a valid 10-digit phone number and a price. Go back and try again."
        );
    }
    if (!works($wk, $_POST["slot"])) {
        die(
            "This worker is not available at that day/time. Check their working hours on their profile and go back."
        );
    }
    if ($_POST["slot"] && clash($pdo, $w, $_POST["slot"])) {
        die("That time was just taken. Go back and pick another slot.");
    }
    $pdo->prepare(
        "INSERT INTO jobs(customer_id,title,category,details,amount,location,phone,price,slot,status,worker_id)VALUES(?,?,?,?,?,?,?,?,?,'requested',?)",
    )->execute([
        $u["id"],
        $_POST["title"],
        $wk["skill"],
        $_POST["details"],
        $_POST["amount"],
        $_POST["location"],
        $_POST["phone"],
        (int) $_POST["price"],
        $_POST["slot"] ?: null,
        $w,
    ]);
    $id = $pdo->lastInsertId();
    notify(
        $w,
        $u["name"] . ' sent you a job request: "' . $_POST["title"] . '"',
        "job.php?id=$id",
    );
    header("Location: job.php?id=$id");
    exit();
}
head("Hire Worker");
?>
<style>
/* Simple hire form styling (scoped to .hw) */
.hw{--h:#4f46e5;max-width:600px;margin:0 auto;border-radius:16px!important;box-shadow:0 8px 24px rgba(0,0,0,.08);animation:hIn .4s ease both}
.hw>h2{margin:0 0 16px;padding-bottom:12px;border-bottom:3px solid var(--h);display:flex;align-items:center;gap:8px}
.hw>h2 .mi{color:var(--h)}
.hw input:not([type=hidden]),.hw textarea{width:100%;display:block;margin:0 0 10px;padding:11px 13px;border:1.5px solid #dcdfe8;border-radius:10px;
  background:#fff;font-size:15px;transition:border-color .2s,box-shadow .2s}
.hw input:not([type=hidden]):focus,.hw textarea:focus{outline:none;border-color:var(--h);box-shadow:0 0 0 3px rgba(79,70,229,.15)}
.hw label{display:block;margin:6px 0;font-weight:600}
.hw .slots{padding:12px;border-radius:12px;background:#f6f7fb;margin-bottom:12px}
.hw .slot{margin:4px 6px 0 0;padding:7px 14px;border:1.5px solid #cfd3e6;border-radius:999px;background:#fff;color:var(--h);cursor:pointer;
  transition:background .2s,color .2s,border-color .2s}
.hw .slot:hover:not(:disabled){border-color:var(--h);background:#eeedff}
.hw .slot.sel{background:var(--h);color:#fff;border-color:var(--h)}
.hw .slot:disabled{background:#eceef3;color:#a3a7b8;border-color:#eceef3;cursor:not-allowed}
.hw>button{width:100%;margin-top:4px;padding:13px;border:0;border-radius:10px;background:var(--h);color:#fff;font-size:16px;font-weight:600;cursor:pointer;
  transition:background .2s,transform .15s}
.hw>button:hover{background:#4338ca;transform:translateY(-2px)}
.hw>button:active{transform:scale(.98)}
@keyframes hIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}
@media (prefers-reduced-motion:reduce){.hw,.hw *{animation:none!important;transition:none!important}}
</style>
<form class="card hw" method="post"><h2><span class="material-icons mi">handshake</span> Hire <?= e(
    $wk["name"],
) ?> (<?= e($wk["skill"]) ?>)</h2>
<input name="title" placeholder="Job title" required><input name="amount" placeholder="Amount of work (e.g. 3 fans)" required>
<textarea name="details" rows="3" placeholder="How should the work be done?" required></textarea>
<input name="location" placeholder="Location" required><input name="phone" pattern="[0-9]{10}" placeholder="Phone (10 digits)" value="<?= e(
    $u["phone"],
) ?>" required>
<input type="number" name="price" min="1" placeholder="Price you offer (₹)" required>
<label>Book a time slot (optional)</label><input type="date" id="sd" min="<?= date(
    "Y-m-d",
) ?>" max="<?= date("Y-m-d", strtotime("+30 days")) ?>">
<div id="sl" class="slots"><small>Pick a date to see the free times.</small></div><input type="hidden" name="slot" id="slot">
<script>sd.onchange=async function(){let box=document.getElementById("sl");box.textContent="Loading…";slot.value="";let d=await (await fetch("slots.php?w=<?= $w ?>&d="+sd.value)).json();box.innerHTML="";
if(!d.slots.length){let m=document.createElement("small");m.textContent=d.note;box.appendChild(m);return}
let m=document.createElement("small");m.textContent=d.note+" · grey = already booked";box.appendChild(m);box.appendChild(document.createElement("br"));
d.slots.forEach(function(s){let b=document.createElement("button");b.type="button";b.className="slot";b.textContent=s.t;b.disabled=!s.free;b.onclick=function(){document.querySelectorAll(".slot").forEach(function(x){x.classList.remove("sel")});b.classList.add("sel");slot.value=sd.value+" "+s.t};box.appendChild(b)})}</script>
<button>Send Request</button></form><?php foot();