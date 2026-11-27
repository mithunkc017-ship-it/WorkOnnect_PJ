<?php
/* WorkOnnect AI - extra brain. Included by ai_chat.php (global scope).
   Defines: xt, x_top_lines, x_autocorrect, x_typo, x_name_search, x_profile_view, x_faq, x_fallback
   Optional: put your Anthropic key in AI_KEY (config.php) to answer ANY question online. Empty = fully offline. */

/* ---------- texts (English / ಕನ್ನಡ / हिन्दी). Missing kn/hi falls back to English ---------- */
function xt($k, $a = []) {
    global $L;
    static $S = null;
    if ($S === null) $S = [
        "typo" => ["en" => "Did you mean **%s**? Pick an option 👇", "kn" => "ನೀವು **%s** ಎಂದು ಹೇಳಿದ್ದೀರಾ? ಒಂದನ್ನು ಆರಿಸಿ 👇", "hi" => "क्या आपका मतलब **%s** था? एक विकल्प चुनें 👇"],
        "found_tip" => ["en" => "💡 Tap **Book** on a card, or type *book worker #1*. Add a time to filter availability (e.g. *electrician tomorrow 5 pm*).",
                        "kn" => "💡 ಯಾವುದೇ ಕೆಲಸಗಾರರ **Book** ಒತ್ತಿ, ಅಥವಾ *book worker #1* ಎಂದು ಬರೆಯಿರಿ. ಲಭ್ಯತೆ ನೋಡಲು ಸಮಯ ಸೇರಿಸಿ, ಉದಾ: *electrician tomorrow 5 pm*.",
                        "hi" => "💡 किसी वर्कर पर **Book** दबाएँ या *book worker #1* लिखें। उपलब्धता देखने के लिए समय जोड़ें, जैसे *electrician tomorrow 5 pm*।"],
        "dym" => ["en" => "I'm not completely sure what you meant 🤔 Did you mean one of these?", "kn" => "ನೀವು ಏನು ಹೇಳಿದ್ದೀರಿ ಎಂದು ಸಂಪೂರ್ಣ ಖಚಿತವಿಲ್ಲ 🤔 ಇವುಗಳಲ್ಲಿ ಒಂದೇ?", "hi" => "मुझे पूरी तरह समझ नहीं आया 🤔 क्या आपका मतलब इनमें से कोई था?"],
        "fallback" => ["en" => "I didn't quite catch “%s” 🤔\n\n**Here is what I can help with right away:**\n• 🔍 Find & book verified workers (*%s*)\n• 💡 Instant price estimates & booking status\n• 🧮 Quick math (*20%% of 850*) & platform FAQs\n\nPick a suggestion below or rephrase your question 👇",
                       "kn" => "“%s” ಅನ್ನು ಅರ್ಥಮಾಡಿಕೊಳ್ಳಲಾಗಲಿಲ್ಲ 🤔 ನಾನು ಕೆಲಸಗಾರರನ್ನು ಹುಡುಕಲು, ಬೆಲೆ ಅಂದಾಜಿಸಲು ಮತ್ತು ಬುಕಿಂಗ್ ನಿರ್ವಹಿಸಲು ಸಹಾಯ ಮಾಡಬಲ್ಲೆ. ಕೆಳಗಿನ ಬಟನ್ ಒತ್ತಿ ಅಥವಾ *%s* ಎಂದು ಟೈಪ್ ಮಾಡಿ.",
                       "hi" => "मैं “%s” को समझ नहीं पाया 🤔 मैं वर्कर ढूँढने, कीमत का अनुमान लगाने और बुकिंग संभालने में मदद कर सकता हूँ। नीचे कोई बटन दबाएँ या *%s* जैसा लिखें।"],
        "name_found" => ["en" => "I found **%d** worker(s) matching your search 👇", "kn" => "ನಿಮ್ಮ ಹುಡುಕಾಟಕ್ಕೆ ಹೊಂದುವ **%d** ಕೆಲಸಗಾರರು ಸಿಕ್ಕಿದ್ದಾರೆ 👇", "hi" => "आपकी खोज से मेल खाते **%d** वर्कर मिले 👇"],
        "who" => ["en" => "I'm **WorkOnnect Assistant** 🤖\n• Find & book verified local workers\n• Compare ratings, prices & availability\n• Manage bookings, earnings & platform questions in **English, ಕನ್ನಡ & हिन्दी**.",
                  "kn" => "ನಾನು **WorkOnnect Assistant** 🤖 ವಿಶ್ವಾಸಾರ್ಹ ಕೆಲಸಗಾರರನ್ನು ಹುಡುಕಲು, ಬೆಲೆ ಅಂದಾಜಿಸಲು, ಬುಕ್ ಮಾಡಲು ಸಹಾಯ ಮಾಡುತ್ತೇನೆ.",
                  "hi" => "मैं **WorkOnnect Assistant** हूँ 🤖 मैं भरोसेमंद वर्कर ढूँढने, कीमत का अंदाज़ा लगाने और बुकिंग संभालने में मदद करता हूँ।"],
        "howru" => ["en" => "I'm doing great, thanks for asking! 😊 How can I help you today?", "kn" => "ನಾನು ಚೆನ್ನಾಗಿದ್ದೇನೆ, ಕೇಳಿದ್ದಕ್ಕೆ ಧನ್ಯವಾದ! 😊 ಇಂದು ಹೇಗೆ ಸಹಾಯ ಮಾಡಲಿ?", "hi" => "मैं बढ़िया हूँ, पूछने के लिए धन्यवाद! 😊 आज मैं कैसे मदद करूँ?"],
        "services" => ["en" => "🛠️ **Available Services on WorkOnnect:**\n%s\n\nTap any service below to view top-rated workers 👇",
                       "kn" => "🛠️ **WorkOnnect ಸೇವೆಗಳು:** %s. ಹುಡುಕಲು ಒಂದನ್ನು ಒತ್ತಿ 👇",
                       "hi" => "🛠️ **WorkOnnect की सेवाएँ:** %s। खोजने के लिए एक चुनें 👇"],
        "support" => ["en" => "🙋 **Need Help or Support?**\n• **Platform issues:** Open the **Help** page from the top menu.\n• **Active booking:** Message your worker directly on the booking's **Job Page**.\n• **Security:** WorkOnnect never asks for OTPs, passwords, or card details."],
        "post_c" => ["en" => "📝 **How to Post a Job:**\n1. Open **Post Job** in the top menu.\n2. Describe the task, select a category, and pick a preferred time.\n3. Compare offers from workers and accept the best fit.\n\n💡 Or book directly here by typing *find an electrician*."],
        "offer_c" => ["en" => "💬 **How Worker Offers Work:**\nWhen you post an open job, nearby workers send **offers** with their price quote. Compare their rating, completed jobs, and price on the job page before accepting."],
        "offer_w" => ["en" => "💬 **How to Win More Jobs:**\n1. Open **Open Jobs** in the top menu.\n2. Pick a job matching your skill and send a fair price quote with a clear note.\n3. Keep your profile 100% complete and reply quickly."],
        "review" => ["en" => "⭐ **Rating & Reviews:**\nOnce a job is marked **Completed**, open it from **My Bookings** to submit a 1–5 star rating and review."],
        "coupon" => ["en" => "🎟️ **Coupons & Discounts:**\nApply your promo code at checkout on the **Job Page** before paying. Referral bonuses (₹50) are credited straight to your **Wallet**."],
        "dispute" => ["en" => "⚖️ **Disputes & Complaints:**\nOpen the booking's **Job Page** and click **Raise Dispute / Report**, or contact us via the **Help** page. Keep chat messages and photos as proof for faster resolution."],
        "lang" => ["en" => "🌐 **Language Switch:**\nUse the **EN / ಕನ್ನಡ / हिं** toggle at the top of this chat window to switch languages anytime."],
        "voice" => ["en" => "🎙️ **Voice Input & Read-Aloud:**\nTap the **mic icon** to speak your request, and the **speaker icon** to hear responses read aloud (supported on Chrome & Edge with mic permission)."],
        "areas" => ["en" => "📍 **Service Areas:**\nInclude your neighborhood or city in your search—for example, *electrician in Indiranagar* or *plumber near Whitefield*—to filter local workers."],
        "whoami" => ["en" => "👤 You are logged in as **%s** (Role: **%s**)."],
        "account_c" => ["en" => "⚙️ **Account Settings:**\nOpen **Settings** in the top menu to update your name, phone number, email, or password."],
        "account_w" => ["en" => "⚙️ **Worker Profile & Settings:**\n• **My Profile:** Update your skill category, rate, experience, photo & working hours.\n• **Settings:** Update your phone number, email, or password."],
        "urgent" => ["en" => "⚡ **Urgent / Same-Day Help:**\nSearch with **today** and a near time—for example, *plumber today 8 pm*—to see workers currently open and clash-free."],
        "resched" => ["en" => "🗓️ **Rescheduling a Booking:**\nMessage your worker on the **Job Page** to agree on a new time, or cancel before acceptance (free) and place a fresh booking for the new slot."],
        "tip" => ["en" => "💝 **Tipping Your Worker:**\nYou can add an optional tip on the **Job Page** during payment. **100%% of your tip** goes directly to the worker."],
        "worker_onboard" => ["en" => "🚀 **Getting Started as a Worker:**\n1. Complete your **My Profile** (category, area, rate, photo & working hours).\n2. Submit ID verification to earn the **Verified ✔** badge.\n3. Check **Open Jobs** or respond fast to direct booking requests."],
        "worker_payout" => ["en" => "🏦 **Worker Payouts & Commission:**\nCustomer payments (minus platform commission, plus 100%% of tips) are tracked in your **Earnings** and settled to your registered payout method."],
        "admin_help" => ["en" => "🛠️ **Admin Quick Actions:**\n• Type **Platform overview** for live user, job, dispute & revenue counts.\n• Use the admin dashboard to verify worker IDs, manage categories, and resolve open disputes."],
        "bye2" => ["en" => "Goodbye! 👋 Come back anytime you need help."],
        "okay" => ["en" => "👍 Got it! What would you like to do next?"],
        "joke" => ["en" => "😄 %s"],
        "nocalc" => ["en" => "🧮 I can't divide by zero 😅"],
    ];
    $s = $S[$k][$L] ?? $S[$k]["en"] ?? $k;
    return $a ? vsprintf($s, $a) : $s;
}

/* ---------- worker lines for the "found" message ---------- */
function x_top_lines($ws) {
    $o = [];
    foreach ($ws as $w) {
        $b = [];
        $b[] = $w["rating"] === null ? "New" : "⭐ " . $w["rating"];
        $b[] = (int) $w["jobs_done"] . " jobs";
        if (!empty($w["rate"])) $b[] = "₹" . $w["rate"];
        if (!empty($w["location"])) $b[] = "📍 " . $w["location"];
        $b[] = !empty($w["available"]) ? "🟢 available" : "🔴 busy";
        $o[] = $w["index"] . ". **" . str_replace("*", "", (string) $w["name"]) . "**" . (!empty($w["verified"]) ? " ✔" : "") . " — " . str_replace("*", "", implode(" · ", $b));
    }
    return implode("\n", $o);
}

/* ---------- typo help ---------- */
function x_tokens($m) { return preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string) $m), -1, PREG_SPLIT_NO_EMPTY); }

function x_best_word($tok, $dict) {
    // nearest dictionary word: same first letter, small edit distance, unique winner. Returns null if unsure.
    $n = strlen($tok);
    if ($n < 5 || !preg_match('/^[a-z]+$/', $tok)) return null;
    $max = $n >= 7 ? 2 : 1;
    $best = null; $bd = 99; $tie = false;
    foreach ($dict as $w) {
        if ($w[0] !== $tok[0] || abs(strlen($w) - $n) > $max) continue;
        $d = levenshtein($tok, $w);
        if ($d > $max) continue;
        if ($d < $bd) { $bd = $d; $best = $w; $tie = false; }
        elseif ($d == $bd && $w !== $best) $tie = true;
    }
    return ($best !== null && !$tie) ? $best : null;
}

function x_dict() {
    global $CATS;
    static $D = null;
    if ($D !== null) return $D;
    $D = ["electrician", "electricians", "electrical", "plumber", "plumbers", "plumbing", "painter", "painters", "painting", "carpenter", "carpenters", "carpentry",
        "cleaner", "cleaners", "cleaning", "mechanic", "mechanics", "gardener", "gardeners", "driver", "drivers", "technician", "technicians", "furniture", "bathroom",
        "booking", "bookings", "cancel", "cancellation", "status", "track", "estimate", "estimated", "price", "prices", "payment", "payments", "refund", "wallet", "referral",
        "earnings", "earning", "availability", "available", "verified", "verification", "profile", "performance", "performing", "rating", "ratings",
        "tomorrow", "today", "tonight", "evening", "morning", "afternoon", "monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday",
        "search", "worker", "workers", "nearby", "policy", "complaint", "dispute", "support", "services", "service", "thanks", "hello", "charges", "customer",
        "calculate", "developer", "developers", "location", "offers", "review", "reviews", "coupon", "discount", "language", "kannada", "weekdays", "weekday",
        "reschedule", "urgent", "emergency", "settlement", "payout", "overview", "dashboard"];
    foreach ((array) ($CATS ?? []) as $c) foreach (preg_split('/\s+/', mb_strtolower((string) $c)) as $p) if (strlen($p) >= 5 && preg_match('/^[a-z]+$/', $p)) $D[] = $p;
    return $D = array_values(array_unique($D));
}

/* fixes clear typos in the message ("electrition" -> "electrician", "tommorow" -> "tomorrow") so every later rule works */
function x_autocorrect($m) {
    $dict = x_dict();
    $set = array_flip($dict);
    return preg_replace_callback('/\b[a-z]{5,}\b/', function ($x) use ($dict, $set) {
        $w = $x[0];
        if (isset($set[$w])) return $w;
        return x_best_word($w, $dict) ?: $w;
    }, (string) $m);
}

/* maps a word to the site's category name */
function x_canon_cat($name) {
    global $CATS;
    foreach ((array) ($CATS ?? []) as $c) if (mb_stripos((string) $c, $name) !== false || mb_stripos($name, (string) $c) !== false) return (string) $c;
    return $name;
}

/* "plumbar" -> "Plumber"; returns null when it is not a near-miss */
function x_typo($m) {
    $K = ["electrician" => "Electrician", "electrical" => "Electrician", "plumber" => "Plumber", "plumbing" => "Plumber", "painter" => "Painter", "painting" => "Painter",
        "carpenter" => "Carpenter", "carpentry" => "Carpenter", "cleaner" => "Cleaner", "cleaning" => "Cleaner", "mechanic" => "Mechanic", "gardener" => "Gardener",
        "driver" => "Driver", "technician" => "AC Technician"];
    foreach (x_tokens($m) as $t) {
        if (!preg_match('/^[a-z]{4,}$/', $t) || isset($K[$t])) continue;
        $best = null; $bd = 99;
        foreach (array_keys($K) as $w) {
            $d = levenshtein($t, $w);
            if ($d < $bd) { $bd = $d; $best = $w; }
        }
        if ($best !== null && $bd <= (strlen($t) <= 5 ? 1 : 2)) return x_canon_cat($K[$best]);
    }
    return null;
}

/* ---------- find a worker by NAME ("ravi", "find Ravi", "book ravi") ---------- */
function x_name_search() {
    global $pdo, $m, $role, $ctx;
    if ($role != "customer") return;
    static $STOP = ["find", "show", "book", "hire", "worker", "workers", "named", "called", "profile", "the", "for", "with", "near", "please", "want", "need", "and", "who", "what", "how", "can", "you", "get", "any", "this", "that", "has", "have", "details", "info", "about"];
    $toks = [];
    foreach (x_tokens($m) as $t) if (mb_strlen($t) >= 3 && !in_array($t, $STOP, true) && !ctype_digit($t)) $toks[] = $t;
    $toks = array_values(array_unique($toks));
    if (!$toks || count($toks) > 5) return;
    try { $rows = $pdo->query("SELECT * FROM users WHERE role='worker'")->fetchAll(); } catch (Throwable $e) { return; }
    $hit = [];
    foreach ($rows as $r) {
        if (!empty($r["banned"])) continue;
        $n = count(array_intersect($toks, x_tokens((string) $r["name"])));
        if ($n) $hit[] = [$n, $r];
    }
    if (!$hit) return;
    usort($hit, function ($a, $b) { return $b[0] <=> $a[0]; });
    $top = $hit[0][0];
    $hit = array_slice(array_values(array_filter($hit, function ($h) use ($top) { return $h[0] == $top; })), 0, 5);
    $items = []; $ids = [];
    foreach ($hit as $i => $h) { $items[] = wcard($h[1], $i + 1, wstats($h[1]["id"])); $ids[] = (int) $h[1]["id"]; }
    $_SESSION["ai_last"] = $ids;
    // "book ravi" with one match: start the booking flow straight away
    if (count($hit) == 1 && has($m, ["book", "hire", "ಬುಕ್", "बुक"])) {
        $w = $hit[0][1];
        $_SESSION["ai_flow"] = ["step" => "slot", "wid" => (int) $w["id"], "category" => (string) (col($w, ["category", "skill", "service"]) ?? ($ctx["category"] ?? ""))];
        reply(aitx("ask_slot", [$w["name"]]), [], slot_chips());
    }
    $chips = [];
    foreach ($items as $it) if (count($chips) < 3) $chips[] = "Book worker #" . $it["index"] . " (" . explode(" ", (string) $it["name"])[0] . ")";
    reply(xt("name_found", [count($items)]), [["type" => "workers", "items" => $items]], $chips);
}

/* ---------- "show profile #2" ---------- */
function x_profile_view() {
    global $m, $role;
    if ($role != "customer") return;
    if (!has($m, ["profile", "details", "detail", "info about", "ಪ್ರೊಫೈಲ್", "प्रोफ़ाइल", "प्रोफाइल"])) return;
    if (!preg_match('/#\s*(\d)\b|\b(?:worker|profile|number|no\.?|of)\s*#?\s*(\d)\b/i', $m, $x)) return;
    $idx = (int) ($x[1] !== "" ? $x[1] : $x[2]);
    $id = (int) ($_SESSION["ai_last"][$idx - 1] ?? 0);
    $w = $id ? getworker($id) : null;
    if (!$w) reply(aitx("pick_first"), [], cat_chips());
    $c = wcard($w, $idx, wstats($id));
    reply("👤 Profile of **" . str_replace("*", "", (string) $w["name"]) . "**:", [["type" => "workers", "items" => [$c]]], ["Book worker #" . $idx . " (" . explode(" ", (string) $w["name"])[0] . ")", "Show my bookings"]);
}

/* ---------- tiny safe calculator (no eval) ---------- */
class XCalc {
    private $t = []; private $i = 0;
    public function run($s) {
        preg_match_all('/\d+(?:\.\d+)?|[-+*\/^()]/', $s, $mm);
        $this->t = $mm[0]; $this->i = 0;
        $v = $this->expr();
        if ($this->i < count($this->t)) throw new Exception("syntax");
        return $v;
    }
    private function peek() { return isset($this->t[$this->i]) ? $this->t[$this->i] : null; }
    private function expr() { $v = $this->term(); while (($p = $this->peek()) === "+" || $p === "-") { $this->i++; $r = $this->term(); $v = $p === "+" ? $v + $r : $v - $r; } return $v; }
    private function term() {
        $v = $this->power();
        while (($p = $this->peek()) === "*" || $p === "/") {
            $this->i++; $r = $this->power();
            if ($p === "/") { if ($r == 0) throw new Exception("div0"); $v /= $r; } else $v *= $r;
        }
        return $v;
    }
    private function power() { $b = $this->unary(); if ($this->peek() === "^") { $this->i++; $e = $this->power(); if (abs($e) > 100) throw new Exception("big"); return pow($b, $e); } return $b; }
    private function unary() { $p = $this->peek(); if ($p === "-") { $this->i++; return -$this->unary(); } if ($p === "+") { $this->i++; return $this->unary(); } return $this->atom(); }
    private function atom() {
        $p = $this->peek();
        if ($p === "(") { $this->i++; $v = $this->expr(); if ($this->peek() !== ")") throw new Exception("syntax"); $this->i++; return $v; }
        if ($p !== null && is_numeric($p)) { $this->i++; return (float) $p; }
        throw new Exception("syntax");
    }
}
function x_num($v) {
    if (abs($v - round($v)) < 1e-9) return number_format(round($v), 0, ".", ",");
    return rtrim(rtrim(number_format($v, 6, ".", ","), "0"), ".");
}

/* ---------- offline quick answers for common general questions ---------- */
function x_general_qa($m, $chips) {
    $QA = [
        [['/\b(capital of india|india\'?s capital)\b/i'], "🇮🇳 The capital of India is **New Delhi**."],
        [['/\b(capital of karnataka|karnataka\'?s capital)\b/i'], "📍 The capital of Karnataka is **Bengaluru** (Bangalore)."],
        [['/\b(pm of india|prime minister of india)\b/i'], "🇮🇳 The Prime Minister of India is **Narendra Modi**."],
        [['/\b(currency of india|indian currency)\b/i'], "💱 India's official currency is the **Indian Rupee (₹ / INR)**."],
        [['/\b(emergency number|police number|ambulance number|helpline india)\b/i'], "🚨 **India Emergency Helplines:**\n• **112** — All-in-one National Emergency\n• **100** — Police · **101** — Fire · **108** — Ambulance"],
        [['/\b(gst|goods and services tax)\b/i'], "🧾 **GST (Goods and Services Tax)** is India's unified indirect tax on goods and services, with standard slabs of 5%, 12%, 18%, and 28%."],
        [['/\b(what is upi|full form of upi)\b/i'], "📲 **UPI (Unified Payments Interface)** is India's instant real-time bank-to-bank mobile payment system developed by NPCI."],
        [['/\b(water leak|pipe burst|stop leak)\b/i'], "🚰 **Quick Water-Leak Tip:**\n1. Shut off the nearest angle valve or main water valve immediately.\n2. Keep electrical switches dry.\n3. Book a verified plumber right here!"],
        [['/\b(power cut|short circuit|trip|mcb|spark)\b/i'], "⚡ **Electrical Safety Tip:**\n1. Switch off the affected appliance and check the main **MCB** board.\n2. Never touch exposed wires or wet switches.\n3. Book a verified electrician for inspection."],
    ];
    foreach ($QA as [$rxs, $ans]) {
        foreach ($rxs as $rx) if (preg_match($rx, $m)) reply($ans, [], $chips);
    }
}

/* ---------- FAQ, calculator, small talk (all roles) ---------- */
function x_faq() {
    global $m, $role, $u, $CATS, $L;
    $chips = menu_chips($role);

    // calculator: "2+2", "500 x 3", "20% of 500", "what is 1500/4"
    $e = trim(preg_replace('/\b(what\'?s|what is|whats|calculate|calc|compute|solve|how much is|equals?)\b|[=?]/iu', " ", (string) $m));
    $e2 = str_replace(",", "", $e);
    if (preg_match('/^(\d+(?:\.\d+)?)\s*(?:%|percent)\s*of\s*(\d+(?:\.\d+)?)$/i', $e2, $x)) {
        reply("🧮 **" . $x[1] . "% of " . $x[2] . " = " . x_num((float) $x[1] * (float) $x[2] / 100) . "**", [], $chips);
    }
    $e3 = str_replace("÷", "/", preg_replace('/(?<=\d)\s*[x×]\s*(?=[\d(])/iu', "*", $e2));
    if (strlen($e3) <= 80 && !preg_match('/\d{4}-\d{2}-\d{2}/', $e3) && preg_match('/^[\d\s+\-*\/().^]+$/', $e3) && preg_match('/\d\s*[+\-*\/^]\s*[\d(]/', $e3)) {
        try {
            $v = (new XCalc())->run($e3);
            if (is_finite($v)) reply("🧮 **" . str_replace("*", "×", trim($e3)) . " = " . x_num($v) . "**", [], $chips);
        } catch (Exception $ex) {
            if ($ex->getMessage() == "div0") reply(xt("nocalc"), [], $chips);
        }
    }
    x_more();   // new: per-role features + knowledge base

    if (mb_strlen($m) < 12 && preg_match('/^(ok|okay|cool|great|nice|awesome|fine|hmm|k)\b/i', $m)) reply(xt("okay"), [], $chips);
    if (has($m, ["bye", "goodbye", "see you", "good night"])) reply(xt("bye2"), [], $chips);
    if (has($m, ["joke", "funny", "make me laugh"])) {
        $J = ["Why did the electrician go to school? To improve his current knowledge!", "Why don't plumbers ever get lost? They always follow the pipeline.",
            "A painter's favourite exercise? Wall sits... with a coat of paint.", "Why was the carpenter calm? He knew how to nail every deadline."];
        reply(xt("joke", [$J[random_int(0, count($J) - 1)]]), [], $chips);
    }
    if (has($m, ["who are you", "your name", "are you a bot", "are you ai", "are you human", "are you real", "what are you"])) reply(xt("who"), [], $chips);
    if (has($m, ["how are you", "how r u", "how do you do", "what's up", "whats up"])) reply(xt("howru"), [], $chips);
    if (has($m, ["who am i", "my name", "my role"])) reply(xt("whoami", [(string) ($u["name"] ?? ""), $role]), [], $chips);
    if (has($m, ["what services", "which services", "list of services", "service list", "categories", "types of workers", "what can i book", "what workers"])) {
        $list = array_slice(array_values((array) ($CATS ?? [])), 0, 12);
        reply(xt("services", [implode(", ", $list)]), [], array_slice($list, 0, 8));
    }
    if (has($m, ["change password", "reset password", "forgot password", "edit profile", "account settings", "update my profile", "change my phone", "change email", "change my email"])) reply(xt($role == "worker" ? "account_w" : "account_c"), [], $chips);
    if (!detect_cat($m) && has($m, ["contact", "customer care", "customer support", "support team", "helpline", "phone number", "talk to human", "speak to human", "email us"])) reply(xt("support"), [], $chips);
    if (has($m, ["coupon", "promo", "discount", "voucher", "offer code"])) reply(xt("coupon"), [], $chips);
    if (has($m, ["dispute", "complaint", "complain", "report worker", "no show", "not satisfied", "bad service", "worker didn't come", "worker did not come"])) reply(xt("dispute"), [], $chips);
    if (has($m, ["post a job", "post job", "how to post", "how do i post", "create a job", "new job"]) && $role != "worker") reply(xt("post_c"), [], $chips);
    if (!detect_cat($m) && has($m, ["offer", "offers", "quotation", "quote", "bid"])) reply(xt($role == "worker" ? "offer_w" : "offer_c"), [], $chips);
    if (has($m, ["leave a review", "give review", "write a review", "rate worker", "rate a worker", "how to rate", "give rating"])) reply(xt("review"), [], $chips);
    if (!detect_cat($m) && has($m, ["language", "kannada", "hindi", "ಕನ್ನಡದಲ್ಲಿ", "हिंदी में"])) reply(xt("lang"), [], $chips);
    if (!detect_cat($m) && has($m, ["voice", "microphone", "speak to you", "talk to you"])) reply(xt("voice"), [], $chips);
    if (has($m, ["which areas", "which cities", "where available", "service area", "areas covered", "which city", "coverage"])) reply(xt("areas"), [], $chips);
    if (has($m, ["urgent", "emergency", "right now", "asap", "immediately", "same day"])) reply(xt("urgent"), [], $chips);
    if (has($m, ["reschedule", "change time", "change date", "postpone", "move booking"])) reply(xt("resched"), [], $chips);
    if (has($m, ["tip", "tips", "tipping", "bonus for worker"])) reply(xt("tip"), [], $chips);
    if ($role == "worker" && has($m, ["how to get jobs", "more bookings", "get verified", "blue badge", "onboarding", "start working"])) reply(xt("worker_onboard"), [], $chips);
    if ($role == "worker" && has($m, ["when do i get paid", "bank transfer", "withdraw", "settlement work", "how payout"])) reply(xt("worker_payout"), [], $chips);
    if ($role == "admin" && has($m, ["admin", "manage", "verify worker", "moderate"])) reply(xt("admin_help"), [], $chips);

    x_general_qa($m, $chips);
}

/* ---------- optional: real AI for any other question (needs AI_KEY in config.php) ---------- */
function x_llm($msg) {
    global $role, $L;
    if (!defined("AI_KEY") || AI_KEY === "" || !function_exists("curl_init")) return null;
    $lang = ["en" => "English", "kn" => "Kannada", "hi" => "Hindi"][$L] ?? "English";
    $sys = "You are WorkOnnect Assistant inside a local-services marketplace in India where customers book verified electricians, plumbers, painters, carpenters, cleaners, drivers, mechanics and AC technicians. The user is a $role. Reply in $lang using clean, concise formatting (short bullet points or 2-4 crisp sentences). You may answer general questions helpfully, but you cannot search, book, cancel or change anything yourself: for that, tell the user to type commands such as 'Find an electrician', 'Show my bookings' or 'Estimate a plumbing job'. Never invent worker names, prices, booking details or policies. Never ask for OTPs, passwords or card details.";
    $ch = curl_init("https://api.anthropic.com/v1/messages");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => ["content-type: application/json", "x-api-key: " . AI_KEY, "anthropic-version: 2023-06-01"],
        CURLOPT_POSTFIELDS => json_encode(["model" => "claude-sonnet-5-5", "max_tokens" => 350, "system" => $sys,
            "messages" => [["role" => "user", "content" => mb_substr((string) $msg, 0, 600)]]], JSON_UNESCAPED_UNICODE)]);
    $res = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if (!$res || $code != 200) return null;
    $j = json_decode($res, true); $t = "";
    foreach (($j["content"] ?? []) as $b) if (($b["type"] ?? "") == "text") $t .= $b["text"];
    $t = trim($t);
    return $t !== "" ? mb_substr($t, 0, 800) : null;
}

/* ---------- smart fallback: never a dead end ---------- */
function x_intents($m, $role) {
    $I = [
        "customer" => [
            ["Show my bookings", ["booking", "bookings", "order", "orders", "status", "track", "tracking", "ಬುಕಿಂಗ್", "बुकिंग"]],
            ["Estimate a plumbing job", ["price", "prices", "cost", "estimate", "rate", "rates", "charge", "charges", "cheap", "ಬೆಲೆ", "कीमत"]],
            ["Find an electrician near me", ["find", "search", "worker", "workers", "hire", "electrician", "nearby", "looking", "~near"]],
            ["Find a verified painter", ["verified", "trusted", "painter", "~best", "~top"]],
            ["My wallet & referrals", ["wallet", "referral", "refer", "coupon", "bonus", "cashback", "balance"]],
            ["Cancellation policy", ["cancel", "cancellation", "refund", "policy", "fee"]],
            ["How do payments work?", ["pay", "payment", "payments", "upi", "money", "bill", "invoice", "ಪಾವತಿ", "भुगतान"]],
            ["Is it safe?", ["safe", "safety", "secure", "trust", "scam"]],
            ["How does WorkOnnect work?", ["~how", "~work", "~works", "~use", "~start", "guide", "tutorial"]],
        ],
        "worker" => [
            ["Show my new job requests", ["job", "jobs", "request", "requests", "booking", "bookings", "order", "orders"]],
            ["Open jobs near me", ["open", "marketplace", "bid", "offer", "offers", "available"]],
            ["How much did I earn this month?", ["earn", "earning", "earnings", "income", "money", "salary", "payout", "paid", "settlement"]],
            ["How am I performing?", ["perform", "performance", "rating", "ratings", "review", "reviews", "stats"]],
            ["Improve my profile", ["profile", "improve", "verify", "verification", "photo", "badge"]],
            ["Set availability Mon-Sat 9am to 6pm", ["availability", "available", "schedule", "hours", "timing", "timings", "~days"]],
        ],
        "admin" => [
            ["Platform overview", ["overview", "stats", "dashboard", "users", "revenue", "dispute", "platform", "report"]],
            ["How does WorkOnnect work?", ["~how", "~work", "~works", "guide", "platform"]],
            ["How do payments work?", ["pay", "payment", "payments", "commission", "refund", "money"]],
            ["Is it safe?", ["safe", "safety", "secure", "trust", "verified"]],
        ],
    ];
    $toks = x_tokens($m);
    $out = [];
    foreach ($I[$role] ?? [] as $it) {
        $score = 0.0;
        foreach ($it[1] as $kw) {
            $w = 1.0;
            if ($kw[0] === "~") { $w = 0.5; $kw = substr($kw, 1); }
            foreach ($toks as $t) {
                $hit = $t === $kw
                    || (mb_strlen($t) >= 4 && mb_strpos($kw, $t) === 0)
                    || (preg_match('/^[a-z]+$/', $t) && strlen($t) >= 5 && strlen($kw) >= 5 && levenshtein($t, $kw) <= (strlen($t) >= 7 ? 2 : 1));
                if ($hit) { $score += $w; break; }
            }
        }
        if ($score >= 1.0) $out[] = [$score, $it[0]];
    }
    usort($out, function ($a, $b) { return $b[0] <=> $a[0]; });
    return array_slice(array_column($out, 1), 0, 3);
}

function x_fallback() {
    global $m, $msg, $role;
    // 1) a worker's name?  2) a misspelt service?
    if ($role == "customer") x_name_search();
    if ($role == "customer" && ($s = x_typo($m))) reply(xt("typo", [$s]), [], ["Find " . $s, "Estimate price for " . $s]);

    $hits = x_intents($m, $role);

    // 3) real AI for open questions, when AI_KEY is set
    if (($ai = x_llm2($msg)) !== null) reply($ai, [], $hits ?: menu_chips($role));

    // 4) offline: closest known topics, otherwise a helpful menu
    if ($hits) reply(xt("dym"), [], $hits);
        x_general_q($m, $role);
    $tip = $role == "worker" ? "Set availability Mon-Sat 9am to 6pm" : ($role == "customer" ? "plumber in Indiranagar tomorrow 5 pm" : "Platform overview");
    $shown = trim(str_replace("*", "", mb_substr((string) $msg, 0, 40)));
    reply(xt("fallback", [$shown, $tip]), [], menu_chips($role));
}

require __DIR__ . "/ai_brain_more.php";