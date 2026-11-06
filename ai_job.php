<?php // AI job assistant: problem text (+ optional photo) -> suggested title, category, details, amount, price range and checklist (JSON)
require "config.php";
header("Content-Type: application/json");
if (!me()) {
    echo json_encode(["error" => "Please log in."]);
    exit();
}
$_SESSION["aic"] = array_filter($_SESSION["aic"] ?? [], function ($t) {
    return $t > time() - 3600;
}); // max 10 requests per hour
if (count($_SESSION["aic"]) >= 10) {
    echo json_encode([
        "error" => "Limit reached (10 per hour). Please try again later.",
    ]);
    exit();
}
$_SESSION["aic"][] = time();
$text = trim(substr($_POST["text"] ?? "", 0, 500));
if ($text === "") {
    echo json_encode(["error" => "Please describe the problem."]);
    exit();
}
$KW = [
    "Electrician" =>
        "electric|fan|light|bulb|switch|wiring|socket|fuse|inverter",
    "Plumber" => "leak|tap|pipe|water|toilet|drain|tank|flush",
    "Carpenter" => "wood|door|table|chair|furniture|cupboard|window|bed",
    "Painter" => "paint|wall|ceiling colour|whitewash",
    "Cleaner" => "clean|dust|wash|sweep|mop|sanitize",
    "AC Technician" => "\bac\b|air condition|cooling|gas refill",
    "Mechanic" => "bike|car|engine|tyre|tire|brake|scooter|puncture",
    "Driver" => "drive|drop|pickup|airport|trip",
];
$R = [
    "Electrician" => [200, 600],
    "Plumber" => [150, 500],
    "Carpenter" => [300, 1000],
    "Painter" => [1500, 5000],
    "Cleaner" => [500, 2000],
    "Driver" => [300, 1500],
    "Mechanic" => [300, 1200],
    "AC Technician" => [400, 1500],
];
$guess = function ($t) use ($KW, $CATS) {
    foreach ($KW as $c => $re) {
        if (in_array($c, $CATS) && preg_match("/" . $re . "/i", $t)) {
            return $c;
        }
    }
    return $CATS[0] ?? "Electrician";
};
$r = null;
if (AI_KEY) {
    $parts = [];
    if (
        !empty($_FILES["photo"]["tmp_name"]) &&
        ($i = @getimagesize($_FILES["photo"]["tmp_name"])) &&
        $_FILES["photo"]["size"] < 3000000 &&
        in_array($i[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG])
    ) {
        $parts[] = [
            "type" => "image",
            "source" => [
                "type" => "base64",
                "media_type" => $i["mime"],
                "data" => base64_encode(
                    file_get_contents($_FILES["photo"]["tmp_name"]),
                ),
            ],
        ];
    }
    $parts[] = ["type" => "text", "text" => $text];
    $sys =
        'You help customers of a home-services marketplace in India write a job post. Reply with ONLY a JSON object: {"title":"max 60 chars","category":"one of: ' .
        implode(", ", $CATS) .
        '","details":"2-3 clear sentences for the worker","amount":"e.g. 2 fans","price_low":number in INR,"price_high":number in INR,"checklist":["3 to 5 short things the worker should check or do"]}';
    $ch = curl_init("https://api.anthropic.com/v1/messages");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_POST => 1,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => [
            "content-type: application/json",
            "x-api-key: " . AI_KEY,
            "anthropic-version: 2023-06-01",
        ],
        CURLOPT_POSTFIELDS => json_encode([
            "model" => "claude-haiku-5-5",
            "max_tokens" => 500,
            "system" => $sys,
            "messages" => [["role" => "user", "content" => $parts]],
        ]),
    ]);
    $o = json_decode((string) curl_exec($ch), true);
    $tx = $o["content"][0]["text"] ?? "";
    $tx = trim(preg_replace('/^```(json)?|```$/m', "", $tx));
    $r = json_decode($tx, true);
    if (!is_array($r)) {
        $r = null;
    }
}
$ai = (bool) $r;
if (!$r) {
    $c = $guess($text);
    $r = [
        "title" => mb_substr($text, 0, 60),
        "category" => $c,
        "details" => $text,
        "amount" => "1 job",
        "price_low" => $R[$c][0] ?? 200,
        "price_high" => $R[$c][1] ?? 1000,
        "checklist" => [
            "Check the problem first and tell the customer the cost before starting",
            "Use good-quality parts and keep the work area clean",
            "Test everything with the customer before leaving",
        ],
    ];
}
if (!in_array($r["category"] ?? "", $CATS)) {
    $r["category"] = $guess($text);
}
$g = $pdo->prepare(
    "SELECT ROUND(AVG(price)) a,MIN(price) mn,MAX(price) mx,COUNT(*) n FROM jobs WHERE paid=1 AND price>0 AND category=?",
);
$g->execute([$r["category"]]);
$g = $g->fetch();
echo json_encode([
    "ai" => $ai,
    "title" => mb_substr((string) ($r["title"] ?? ""), 0, 60),
    "category" => $r["category"],
    "details" => (string) ($r["details"] ?? $text),
    "amount" => (string) ($r["amount"] ?? ""),
    "low" => (int) ($r["price_low"] ?? 0),
    "high" => (int) ($r["price_high"] ?? 0),
    "checklist" => array_slice(
        array_map("strval", (array) ($r["checklist"] ?? [])),
        0,
        5,
    ),
    "db" => $g && $g["n"] ? $g : null,
]);
