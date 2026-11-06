<?php // AI assistant endpoint (JSON)
require "config.php";
header("Content-Type: application/json");
$msg = trim(json_decode(file_get_contents("php://input"), true)["msg"] ?? "");
$msg = substr($msg, 0, 300);
if (AI_KEY && !me()) {
    echo json_encode(["reply" => "Please log in to use the AI assistant."]);
    exit();
}
$_SESSION["cc"] = array_filter($_SESSION["cc"] ?? [], function ($t) {
    return $t > time() - 60;
}); // max 8 messages per minute
if (count($_SESSION["cc"]) >= 8) {
    echo json_encode(["reply" => "Too many messages. Please wait a minute."]);
    exit();
}
$_SESSION["cc"][] = time();
$sys =
    "You are the helpful assistant of Workonnect, a website connecting customers with skilled workers (electricians, plumbers, carpenters, painters, cleaners, drivers, mechanics, AC technicians). Customers post jobs, workers send offers, customer accepts one, worker updates progress, customer pays (UPI/Card/Net Banking/Cash), admin verifies workers. Answer briefly in simple English.";
if (($_SESSION["lang"] ?? "en") == "kn") {
    $sys .= " Reply in Kannada (ಕನ್ನಡ).";
}
if (AI_KEY) {
    // Real AI reply through the Anthropic API
    $ch = curl_init("https://api.anthropic.com/v1/messages");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_POST => 1,
        CURLOPT_HTTPHEADER => [
            "content-type: application/json",
            "x-api-key: " . AI_KEY,
            "anthropic-version: 2023-06-01",
        ],
        CURLOPT_POSTFIELDS => json_encode([
            "model" => "claude-haiku-5-5",
            "max_tokens" => 300,
            "system" => $sys,
            "messages" => [["role" => "user", "content" => $msg]],
        ]),
    ]);
    $r = json_decode(curl_exec($ch), true);
    echo json_encode([
        "reply" =>
            $r["content"][0]["text"] ?? "Sorry, AI is not available now.",
    ]);
    exit();
}
// Fallback: simple keyword answers (works offline, no key needed)
$faq = [
    "post" => "Login as customer > Post Job > fill details and photo > submit.",
    "offer" =>
        'Workers open a job and use "Send Offer" with price and message.',
    "pay" =>
        "After a worker is accepted, open the job and click Pay Now (UPI, Card, Net Banking or Cash).",
    "verif" => "Admin verifies workers and a green Verified badge appears.",
    "progress" =>
        "The job page shows Open > Assigned > In progress > Completed.",
    "notif" =>
        'Click the <span class="material-icons mi">notifications</span> in the menu to see updates.',
    "regist" => "Click Register, choose Customer or Worker.",
];
$a =
    "I can help with posting jobs, offers, payments, verification and progress tracking. Please ask about one of these.";
foreach ($faq as $k => $v) {
    if (stripos($msg, $k) !== false) {
        $a = $v;
        break;
    }
}
echo json_encode(["reply" => $a]);
