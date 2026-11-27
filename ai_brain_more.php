<?php
/* WorkOnnect AI - extra knowledge + per-role features. Loaded by ai_brain_extra.php (global scope).
   Adds: x_more() (DB features + knowledge base), x_general_q(), x_llm2(). Nothing existing is changed. */

/* ---------- small texts ---------- */
function xm($k, $a = []) {
    global $L;
    static $S = [
        "general_q" => ["en" => "🤔 That one is outside what I can answer right now. I'm best at WorkOnnect topics: finding workers, prices, bookings, payments, cancellations and home-repair tips. Try one of these 👇",
                        "kn" => "🤔 ಇದಕ್ಕೆ ಈಗ ಉತ್ತರಿಸಲು ನನ್ನಿಂದ ಆಗುತ್ತಿಲ್ಲ. ನಾನು WorkOnnect ವಿಷಯಗಳಲ್ಲಿ ಉತ್ತಮ: ಕೆಲಸಗಾರರನ್ನು ಹುಡುಕುವುದು, ಬೆಲೆ, ಬುಕಿಂಗ್, ಪಾವತಿ ಮತ್ತು ಮನೆ ದುರಸ್ತಿ ಸಲಹೆಗಳು. ಇವುಗಳಲ್ಲಿ ಒಂದನ್ನು ಪ್ರಯತ್ನಿಸಿ 👇",
                        "hi" => "🤔 यह सवाल अभी मेरे दायरे से बाहर है। मैं WorkOnnect के विषयों में बेहतर हूँ: वर्कर ढूँढना, कीमत, बुकिंग, भुगतान और घर की मरम्मत के सुझाव। इनमें से कोई आज़माएँ 👇"],
        "yn" => ["en" => "👍 Got it. What would you like to do next?", "kn" => "👍 ಸರಿ. ಮುಂದೆ ಏನು ಮಾಡೋಣ?", "hi" => "👍 ठीक है। आगे क्या करना चाहेंगे?"],
    ];
    $s = $S[$k][$L] ?? $S[$k]["en"] ?? $k;
    return $a ? vsprintf($s, $a) : $s;
}

/* ---------- knowledge-base entry builder ---------- */
function x_e($roles, $keys, $en, $o = []) {
    return $o + ["r" => $roles, "k" => $keys, "t" => ["en" => $en, "kn" => $o["kn"] ?? null, "hi" => $o["hi"] ?? null]];
}

function x_kb_table() {
    static $T = null;
    if ($T !== null) return $T;
    $C = ["customer"]; $W = ["worker"]; $A = ["admin"]; $ALL = ["customer", "worker", "admin"];

    $T = [
    /* ===== CUSTOMER ===== */
    x_e($C, ["reschedule", "change the time", "change time", "change date", "postpone", "different time", "ಸಮಯ ಬದಲಾ", "समय बदल"],
        "🕒 To change the time, message the worker from the booking's job page and agree on a new slot. If the worker hasn't accepted yet, you can cancel for free and book a new time. Say *show my bookings* to open it.",
        ["kn" => "🕒 ಸಮಯ ಬದಲಾಯಿಸಲು ಬುಕಿಂಗ್‌ನ ಜಾಬ್ ಪುಟದಲ್ಲಿ ಕೆಲಸಗಾರರಿಗೆ ಸಂದೇಶ ಕಳುಹಿಸಿ ಹೊಸ ಸಮಯ ಒಪ್ಪಿಕೊಳ್ಳಿ. ಅವರು ಇನ್ನೂ ಒಪ್ಪಿಲ್ಲದಿದ್ದರೆ ಉಚಿತವಾಗಿ ರದ್ದು ಮಾಡಿ ಹೊಸ ಸಮಯಕ್ಕೆ ಬುಕ್ ಮಾಡಬಹುದು.",
         "hi" => "🕒 समय बदलने के लिए बुकिंग के जॉब पेज पर वर्कर को संदेश भेजकर नया समय तय करें। अगर वर्कर ने अभी स्वीकार नहीं किया है तो आप मुफ़्त में रद्द करके नए समय पर बुक कर सकते हैं।",
         "c" => ["Show my bookings", "Cancellation policy"]]),
    x_e($C, ["urgent", "emergency", "asap", "right now", "immediately", "ಅರ್ಜೆಂಟ್", "जरूरी", "जल्दी", "तुरंत"],
        "🚨 For urgent work: pick a worker marked 🟢 available and choose the nearest slot (e.g. *today 6 pm*), and mention the urgency in the job title. If there is fire, a gas leak or an electric shock, call **112** first and keep everyone away from the danger.",
        ["nocat" => 1, "kn" => "🚨 ತುರ್ತು ಕೆಲಸಕ್ಕೆ 🟢 ಲಭ್ಯ ಇರುವ ಕೆಲಸಗಾರರನ್ನು ಆರಿಸಿ, ಹತ್ತಿರದ ಸಮಯ ಆಯ್ಕೆಮಾಡಿ (ಉದಾ: *today 6 pm*). ಬೆಂಕಿ, ಗ್ಯಾಸ್ ಸೋರಿಕೆ ಅಥವಾ ವಿದ್ಯುತ್ ಆಘಾತವಿದ್ದರೆ ಮೊದಲು **112** ಗೆ ಕರೆ ಮಾಡಿ.",
         "hi" => "🚨 ज़रूरी काम के लिए 🟢 उपलब्ध वर्कर चुनें और सबसे नज़दीकी समय रखें (जैसे *today 6 pm*)। आग, गैस लीक या करंट लगने पर पहले **112** पर कॉल करें।",
         "c" => ["Find an electrician near me", "Find a verified painter", "Show my bookings"]]),
    x_e($C, ["how to choose", "which worker", "best worker", "how do i select", "how to select", "how can i pick", "who should i hire", "how to hire", "how do i choose"],
        "🧭 Choosing a worker: 1) prefer the ✔ verified badge, 2) compare rating **and** jobs done (a 4.8 from 40 jobs beats a 5.0 from 1), 3) check 🟢 availability for your slot, 4) agree on the price before work starts. Add a time like *electrician tomorrow 5 pm* to see only workers free then.",
        ["kn" => "🧭 ಕೆಲಸಗಾರರನ್ನು ಆರಿಸುವಾಗ: 1) ✔ ಪರಿಶೀಲಿತ ಬ್ಯಾಡ್ಜ್, 2) ರೇಟಿಂಗ್ ಮತ್ತು ಪೂರ್ಣಗೊಂಡ ಕೆಲಸಗಳು, 3) ನಿಮ್ಮ ಸಮಯಕ್ಕೆ 🟢 ಲಭ್ಯತೆ, 4) ಕೆಲಸ ಶುರುವಾಗುವ ಮೊದಲು ಬೆಲೆ ಒಪ್ಪಿಕೊಳ್ಳಿ.",
         "hi" => "🧭 वर्कर चुनते समय: 1) ✔ वेरिफाइड बैज, 2) रेटिंग और पूरे किए गए काम, 3) आपके समय पर 🟢 उपलब्धता, 4) काम शुरू होने से पहले कीमत तय करें।",
         "c" => ["Find a verified painter", "Find an electrician near me"]]),
    x_e($C, ["is late", "not arrived", "hasn't arrived", "not reached", "still waiting", "has not come", "not yet come"],
        "⏱️ If your worker is late: message them from the booking's job page first. If there is no reply or they don't show up, raise a complaint on the same page (or use **Help**) and keep screenshots as proof. You can then book another worker.",
        ["c" => ["Show my bookings", "Find an electrician near me"]]),
    x_e($C, ["invoice", "receipt", "bill copy", "download bill", "payment proof"],
        "🧾 Open the booking from **Show my bookings**. Payment details for that job are on its job page. If you need a document for a completed job and don't see one, use the **Help** page.",
        ["c" => ["Show my bookings", "How do payments work?"]]),
    x_e($C, ["tip the worker", "give tip", "add a tip", "tipping", "tip worker"],
        "🙏 Tipping is optional. After a job is done you can add a tip on its job page, and the worker sees it in their earnings.",
        ["c" => ["Show my bookings"]]),
    x_e($C, ["warranty", "guarantee", "redo", "rework", "not fixed", "problem again", "work not good", "poor work", "bad work", "damaged"],
        "🛠️ Not happy with the work? Open the booking and raise a complaint or dispute so the admin team can review it. Take clear photos and keep your chat messages. You can also leave an honest review.",
        ["c" => ["Show my bookings", "Cancellation policy"]]),
    x_e($C, ["extra charge", "extra charges", "hidden charge", "material cost", "spare part", "parts cost", "negotiate", "bargain", "overcharg"],
        "💰 Agree on the price with the worker **before** work starts, and ask clearly whether spare parts or materials cost extra. Say *estimate price for plumber* to see typical rates from real jobs, so you can spot an unfair quote.",
        ["c" => ["Estimate a plumbing job", "Find an electrician near me"]]),
    x_e($C, ["recurring", "monthly service", "every week", "weekly cleaning", "regular cleaning", "multiple workers", "more than one worker", "bulk booking"],
        "🔁 Each booking is for one worker and one time slot. For regular visits, book the same worker again for each date, or agree on a routine in chat. For several workers, book each one separately.",
        ["c" => ["Find an electrician near me", "Show my bookings"]]),
    x_e($C, ["safety tips", "stay safe", "is it safe to", "safe to let", "id proof", "background check"],
        "🛡️ Stay safe: choose ✔ verified workers, read ratings, keep chat and payment on the platform, never share OTPs or card details, and don't leave valuables unattended during a visit.",
        ["c" => ["Find a verified painter", "Is it safe?"]]),
    x_e($C, ["not accepting", "not responding", "no response", "didn't accept", "not accepted", "has not accepted", "no reply"],
        "⏳ If a worker hasn't accepted yet, you can cancel **for free** and pick another available worker. Say *show my bookings* to cancel, then search again.",
        ["c" => ["Show my bookings", "Find an electrician near me"]]),
    x_e($C, ["upload photo", "attach photo", "add photo", "share photo", "send picture"],
        "📷 Photos of the problem help workers quote accurately. If the job page offers an upload option, add them there; otherwise describe the issue clearly in your job title and chat.",
        ["c" => ["Show my bookings"]]),
    x_e($C, ["refund status", "refund time", "money back", "when will i get my refund", "refund not received"],
        "💸 Refunds for cancelled or disputed jobs are processed to your original payment method or wallet. Check the booking's job page for its status; if nothing changes, raise a complaint on that page or use **Help**.",
        ["c" => ["Show my bookings", "Cancellation policy"]]),
    x_e($C, ["on sunday", "on holiday", "on weekend", "weekend", "festival", "late night", "night service", "24x7", "24 hours"],
        "📆 Availability depends on each worker's own working days and hours. Add a time to your search, e.g. *plumber sunday 10 am*, and I'll show only workers who are free then.",
        ["nocat" => 1, "c" => ["Find an electrician near me", "Show my bookings"]]),
    x_e($C, ["pay cash", "cash payment", "pay directly", "pay outside", "pay in cash"],
        "💳 Please pay through the booking's job page so your payment, refunds and any dispute stay protected. Avoid paying outside the platform.",
        ["c" => ["How do payments work?", "Show my bookings"]]),

    /* ===== WORKER ===== */
    x_e($W, ["get verified", "how to verify", "verified badge", "blue badge", "verification process", "verification documents"],
        "✅ To get the verified badge: complete **My Profile** (clear photo, category, area, experience) and submit the ID/verification details requested there. The admin team reviews them. Verified workers get more trust and more bookings.",
        ["kn" => "✅ ಪರಿಶೀಲಿತ ಬ್ಯಾಡ್ಜ್ ಪಡೆಯಲು **My Profile** ಪೂರ್ಣಗೊಳಿಸಿ (ಸ್ಪಷ್ಟ ಫೋಟೋ, ವಿಭಾಗ, ಪ್ರದೇಶ, ಅನುಭವ) ಮತ್ತು ಕೇಳಲಾದ ಗುರುತಿನ ವಿವರ ಸಲ್ಲಿಸಿ. ಅಡ್ಮಿನ್ ತಂಡ ಪರಿಶೀಲಿಸುತ್ತದೆ.",
         "hi" => "✅ वेरिफाइड बैज के लिए **My Profile** पूरा करें (साफ़ फ़ोटो, श्रेणी, क्षेत्र, अनुभव) और माँगे गए ID/सत्यापन विवरण जमा करें। एडमिन टीम उनकी जाँच करती है।",
         "c" => ["Improve my profile", "How am I performing?"]]),
    x_e($W, ["earn more", "increase earning", "increase income", "more bookings", "get more jobs", "more customers", "get more work", "more requests", "grow my business"],
        "🚀 Ways to get more jobs: 1) complete your profile and verification, 2) keep your working hours updated, 3) reply to requests within minutes, 4) be punctual and polite for 5⭐ reviews, 5) set a fair rate (ask *what do others charge for plumber*).",
        ["kn" => "🚀 ಹೆಚ್ಚು ಕೆಲಸ ಪಡೆಯಲು: 1) ಪ್ರೊಫೈಲ್ ಮತ್ತು ಪರಿಶೀಲನೆ ಪೂರ್ಣಗೊಳಿಸಿ, 2) ಕೆಲಸದ ಸಮಯ ಅಪ್‌ಡೇಟ್ ಮಾಡಿ, 3) ವಿನಂತಿಗಳಿಗೆ ಬೇಗ ಉತ್ತರಿಸಿ, 4) ಸಮಯಪಾಲನೆ ಮಾಡಿ ಉತ್ತಮ ರೇಟಿಂಗ್ ಪಡೆಯಿರಿ, 5) ನ್ಯಾಯಯುತ ದರ ನಿಗದಿಪಡಿಸಿ.",
         "hi" => "🚀 ज़्यादा काम पाने के लिए: 1) प्रोफ़ाइल और सत्यापन पूरा करें, 2) काम के घंटे अपडेट रखें, 3) अनुरोधों का जल्दी जवाब दें, 4) समय के पाबंद रहें और 5⭐ रिव्यू पाएँ, 5) उचित दर रखें।",
         "c" => ["Improve my profile", "Set availability Mon-Sat 9am to 6pm", "Show my new job requests"]]),
    x_e($W, ["no jobs", "no bookings", "no requests", "not getting jobs", "not getting any", "not getting bookings"],
        "📭 Few requests? Check these: your availability hours are set, your profile has a photo, category, area and rate, you are verified, and you answer quickly. Say *improve my profile* and I'll list what's missing.",
        ["c" => ["Improve my profile", "Set availability Mon-Sat 9am to 6pm"]]),
    x_e($W, ["commission", "platform fee", "service fee", "deduction", "deducted"],
        "🧮 A platform commission is deducted from each paid job. What you see under earnings is your **net** amount (price minus commission). Say *how much did I earn this month* for the exact numbers.",
        ["c" => ["How much did I earn this month?", "Earnings all time"]]),
    x_e($W, ["payout", "settlement", "when will i get paid", "when do i get paid", "withdraw", "bank transfer"],
        "🏦 Paid jobs show under **earnings**, and the amount already transferred to you is shown as *Already settled*. Say *pending payout* to see how much is still to be settled. For payout timing questions, use the **Help** page.",
        ["c" => ["How much did I earn this month?", "Earnings all time"]]),
    x_e($W, ["accept job", "reject job", "decline job", "accept request", "how to accept", "how to respond", "how to reply to"],
        "📥 New requests arrive as notifications. Say *show my new job requests*, tap a job to open it, and accept or decline there. Quick replies win more work.",
        ["c" => ["Show my new job requests", "How am I performing?"]]),
    x_e($W, ["on the way", "mark complete", "complete job", "finish job", "start job", "mark done", "how to complete", "update status"],
        "🔄 Update the job's status from its job page as you progress (accepted → on the way → in progress → completed). The customer then pays on the same page. Always update it so the customer stays informed.",
        ["c" => ["Show my new job requests", "Completed jobs"]]),
    x_e($W, ["i want to cancel", "cancel job", "cancel a job", "cancel the job", "cancel my job"],
        "⚠️ Cancelling an accepted job can hurt your rating and customer trust. Message the customer from the job page first and try to agree on a new time. If you must cancel, do it as early as possible.",
        ["c" => ["Show my new job requests", "How am I performing?"]]),
    x_e($W, ["bad review", "low rating", "negative review", "unfair review", "reply to review", "wrong review"],
        "⭐ A low review stings, but your **average** matters most. Stay polite, fix the cause (time, quality or communication) and keep doing good work. If a review is abusive or false, report it through the **Help** page.",
        ["c" => ["How am I performing?", "Improve my profile"]]),
    x_e($W, ["contact customer", "message customer", "call customer", "customer phone", "customer address", "customer number"],
        "📞 Customer details and chat are on the booking's **job page**. Open it from *show my new job requests* and message the customer there.",
        ["c" => ["Show my new job requests"]]),
    x_e($W, ["customer not paying", "not paid", "payment not received", "customer refused", "customer didn't pay"],
        "💳 Payment is made on the job page after work is done. If a customer hasn't paid, open that job and remind them there. If it stays unresolved, raise a complaint on the job page or use **Help**, and keep your messages and photos.",
        ["c" => ["Show my new job requests", "Earnings all time"]]),
    x_e($W, ["holiday", "day off", "vacation", "going out of town", "stop receiving", "take a break", "on leave"],
        "🌴 To take time off, update your working days and hours so you aren't booked then, e.g. *Mon-Fri 9am to 6pm* (leave out the days you're away). You can change it again anytime.",
        ["c" => ["Set availability Mon-Sat 9am to 6pm", "Weekdays 10am to 7pm"]]),
    x_e($W, ["portfolio", "work photos", "upload work", "change my photo"],
        "📸 A clear, friendly profile photo builds trust. Update it in **My Profile**. Keep your service details and area complete too.",
        ["c" => ["Improve my profile"]]),
    x_e($W, ["tips received", "get tips", "customer tip", "how do tips"],
        "🙏 Customers can add an optional tip after a completed job. Tips are shown in your earnings summary. Say *earnings all time* to see them.",
        ["c" => ["Earnings all time"]]),
    x_e($W, ["code of conduct", "account suspended", "account blocked", "got banned", "am i banned", "why blocked", "rules for workers"],
        "📜 Accounts can be restricted for fake details, repeated no-shows, abusive behaviour or taking payments outside the platform. If you think a restriction is a mistake, contact support through the **Help** page.",
        ["c" => ["Improve my profile", "How am I performing?"]]),

    /* ===== ADMIN ===== */
    x_e($A, ["verify worker", "approve worker", "verification request", "approve verification", "verify a worker"],
        "✅ Review a worker's profile and ID in your admin dashboard (Workers list) and mark them verified. Say *unverified workers* and I'll list the ones still waiting.",
        ["c" => ["Unverified workers", "Platform overview"]]),
    x_e($A, ["ban user", "block user", "suspend user", "unban", "ban a", "ban worker"],
        "🚫 Ban or unban from the admin Users/Workers list. Banned workers disappear from search and can't be booked. I can't change accounts from chat. Say *platform overview* to see how many are banned.",
        ["c" => ["Platform overview"]]),
    x_e($A, ["handle dispute", "resolve dispute", "review complaint", "refund customer", "dispute resolution", "manage dispute", "dispute", "complaint"],
        "⚖️ Open the disputed booking's job page, review the chat, photos and timeline, then decide: refund, partial refund or release payment. Document your decision. I can list recent bookings with *recent bookings*.",
        ["c" => ["Recent bookings", "Platform overview"]]),
    x_e($A, ["commission rate", "change commission", "set commission", "commission percent"],
        "🧮 Commission is configured in your admin/site settings, not from chat. A change applies to jobs from then on. Say *platform overview* for the commission earned so far.",
        ["c" => ["Platform overview"]]),
    x_e($A, ["create coupon", "add coupon", "manage coupon", "new coupon", "coupon"],
        "🎟️ Create and manage coupons from your admin dashboard. Customers enter the code at the payment step on the job page. I can't create coupons from chat.",
        ["c" => ["Platform overview"]]),
    x_e($A, ["export", "download data", "csv", "excel", "backup"],
        "📦 I can't export data from chat. Use your admin tools or database backup for that. I can show live counts with *platform overview*.",
        ["c" => ["Platform overview"]]),
    x_e($A, ["security", "audit", "logs", "activity log", "suspicious", "fraud"],
        "🔐 Key actions (like bookings made via the assistant) are written to the activity log. For suspicious accounts, review their jobs and reviews, then ban or contact them. Always keep admin passwords private.",
        ["c" => ["Recent bookings", "Platform overview"]]),

    /* ===== EVERYONE: what can you do ===== */
    x_e($C, ["what can you do", "what do you do", "your features", "how can you help", "capabilities", "what all can"],
        "🤖 For customers I can: find workers by service, area, time or name • show profiles • start a booking (you confirm) • estimate prices • show bookings, upcoming visits and spending • cancel with fee preview • explain payments, refunds, coupons, wallet and safety • give quick home-repair tips • do quick maths.",
        ["c" => ["Find an electrician near me", "Show my bookings", "Upcoming bookings", "How much did I spend?"]]),
    x_e($W, ["what can you do", "what do you do", "your features", "how can you help", "capabilities", "what all can"],
        "🤖 For workers I can: show new requests, today's and tomorrow's jobs, next job • earnings, pending payout and performance • update availability • profile tips • price benchmarks • explain commission, payouts, verification and reviews • quick maths.",
        ["c" => ["Today's jobs", "Pending payout", "What do others charge for plumber?", "Improve my profile"]]),
    x_e($A, ["what can you do", "what do you do", "your features", "how can you help", "capabilities", "what all can"],
        "🤖 For admins I can: show platform overview (users, bookings, revenue, commission) • list unverified workers • list recent bookings • explain verification, bans, disputes, coupons and security • quick maths.",
        ["c" => ["Platform overview", "Unverified workers", "Recent bookings"]]),

    /* ===== EVERYONE: general ===== */
    x_e($ALL, ["delete account", "close account", "deactivate", "remove my account"],
        "🗑️ Open **Settings** to look for account options. If you can't find a delete option, contact support from the **Help** page and they will assist you."),
    x_e($ALL, ["notification", "notifications", "reminder", "reminders", "alert me"],
        "🔔 You're notified when a booking is requested, accepted or updated, and reminders go out before a visit. Check notifications in the top menu."),
    x_e($ALL, ["mobile app", "download app", "android app", "ios app", "play store", "app store"],
        "📱 WorkOnnect works in your phone's browser, so there's nothing to install. Open the site and log in. You can also add it to your home screen from the browser menu."),
    x_e($ALL, ["privacy", "my data", "data safe", "personal information", "personal data"],
        "🔒 Share only what a booking needs. I never ask for OTPs, passwords or card details, and nobody from WorkOnnect should either. Manage your details in **Settings**."),
    x_e($ALL, ["weather", "will it rain", "temperature outside"],
        "🌦️ I can't check live weather, but you can confirm the plan with your worker if rain may affect outdoor work."),
    x_e($ALL, ["you are good", "you're good", "good bot", "well done", "great job", "you are smart", "nice bot", "love you", "you are great", "you are awesome", "you're awesome", "you are helpful"],
        "😊 Thank you, that makes my day! Anything else I can do for you?"),
    x_e($ALL, ["stupid", "useless", "dumb", "idiot", "worst bot", "hate you", "you are bad"],
        "😔 Sorry I let you down. Please tell me in a few words what you need (for example *find a plumber* or *show my bookings*) and I'll do my best. For anything else, the **Help** page has the team."),
    x_e($ALL, ["sorry", "my bad", "excuse me"],
        "😊 No problem at all! How can I help?"),
    x_e($ALL, ["are you there", "anyone there", "hello?", "you there"],
        "👋 Yes, I'm here! What do you need?"),
    x_e($ALL, ["how old are you", "your age", "where do you live", "where are you from", "are you married", "your favourite", "your favorite"],
        "😄 I'm an AI assistant, so no age or address, but I'm always online for WorkOnnect questions!"),
    x_e($ALL, ["about us", "about workonnect", "what is this app", "what is this website", "tell me about workonnect"],
        "ℹ️ **WorkOnnect** is a local-services marketplace in India: customers find and book verified electricians, plumbers, painters, carpenters, cleaners, drivers, mechanics and AC technicians, then track and pay on the job page. Workers get jobs, manage availability and earnings."),

    /* ===== DIY / safety tips (only when asked as a how/why question) ===== */
    x_e($ALL, ["mcb", "tripping", "trips", "short circuit", "sparks", "spark", "shock", "fuse", "switch hot", "wiring", "flickering", "flicker"],
        "⚡ **Electrical safety**: switch off the main MCB, don't touch wires or switches with wet hands, and don't open the board yourself. Repeated tripping usually means overload or a faulty appliance: unplug everything, switch the MCB on, then plug devices in one by one to find the culprit. Sparks, burning smell or shocks need a licensed electrician. For fire, call **112**.",
        ["ask" => 1, "cc" => ["Find an electrician near me", "Estimate price for Electrician"]]),
    x_e($ALL, ["leak", "leaking", "drip", "dripping", "clog", "clogged", "blocked drain", "low pressure", "water pressure", "flush", "overflow", "pipe burst"],
        "🚰 **Plumbing first aid**: close the nearest isolation valve or main tap, put a bucket under the leak and wipe the area to prevent slips and damp. Avoid harsh chemical drain cleaners; a plunger is safer. A burst pipe or a leak that keeps coming back needs a plumber.",
        ["ask" => 1, "cc" => ["Find Plumber", "Estimate price for Plumber"]]),
    x_e($ALL, ["not cooling", "cooling issue", "ac smell", "ac noise", "ac water", "ac service", "ac filter", "ac gas"],
        "❄️ **AC care**: first check the remote batteries, set temperature to cool mode, and clean the filters (every 2–4 weeks in summer). Ice on the unit or weak cooling after cleaning usually means low gas or a dirty coil, which needs a technician. Service once or twice a year.",
        ["ask" => 1, "cc" => ["Find AC Technician", "Estimate price for AC Technician"]]),
    x_e($ALL, ["damp", "dampness", "seepage", "peeling", "wall crack", "which paint", "paint quality", "how much paint", "paint for"],
        "🎨 **Walls and paint**: fix the water source (leaking pipe, roof or outer wall) *before* repainting, or the paint will peel again. Use primer, then 2 coats. As a rough guide, 1 litre of emulsion covers about 100–140 sq ft per coat. Ask your painter to confirm for your surface.",
        ["ask" => 1, "cc" => ["Find Painter", "Estimate price for Painter"]]),
    x_e($ALL, ["door stuck", "door not closing", "hinge", "squeak", "termite", "wood polish", "furniture repair", "drawer"],
        "🪚 **Wood and doors**: humidity swells wood, which makes doors stick. Lubricate hinges with a little wax or oil to stop squeaks, and tighten loose screws. Fine powder or mud tubes mean **termites**, which need pest control, not just a carpenter.",
        ["ask" => 1, "cc" => ["Find Carpenter", "Estimate price for Carpenter"]]),
    x_e($ALL, ["deep clean", "cleaning tips", "stain", "mold", "mould", "bad smell"],
        "🧹 **Cleaning tips**: ventilate first, test any cleaner on a hidden spot, and never mix bleach with other cleaners. For mould, fix the damp source, otherwise it returns. For a thorough job, a professional deep clean saves time.",
        ["ask" => 1, "cc" => ["Find Cleaner", "Estimate price for Cleaner"]]),
    x_e($ALL, ["fan slow", "fan noise", "fan wobble", "inverter beeping", "light not working", "bulb"],
        "🌀 **Fans and lights**: a slow fan is often a weak capacitor or dust. A wobbling or noisy fan needs the blades and screws checked. Switch off at the wall before touching anything, and use a ladder safely. Get an electrician for any wiring work.",
        ["ask" => 1, "cc" => ["Find an electrician near me", "Estimate price for Electrician"]]),
    ];
    return $T;
}

function x_kb() {
    global $m, $role, $L;
    $ask = has($m, ["how ", "how to", "why ", "what to do", "what should", "tips", "tip for", "tricks", "advice", "reason", "can i fix", "can i repair", "diy", "safe to", "is it ok", "is it okay", "what causes", "what is the cause"]);
    foreach (x_kb_table() as $e) {
        if (!in_array($role, $e["r"], true)) continue;
        if (!empty($e["ask"]) && !$ask) continue;
        if (!empty($e["nocat"]) && detect_cat($m)) continue;
        if (!has($m, $e["k"])) continue;
        $text = $e["t"][$L] ?? null;
        if (!$text) $text = $e["t"]["en"];
        $chips = $e["c"] ?? ($role == "customer" ? ($e["cc"] ?? null) : null);
        reply($text, [], $chips ?: menu_chips($role));
    }
}

/* ---------- DB-backed features per role (read-only) ---------- */
function x_q($sql, $args = []) { global $pdo; $q = $pdo->prepare($sql); $q->execute($args); return $q->fetchAll(); }
function x_stats($title, $rows) { return ["type" => "stats", "title" => $title, "rows" => $rows]; }
function x_bk_card($rows, $title = "Your bookings") { return ["type" => "bookings", "title" => $title, "items" => array_map("jobcard", $rows)]; }

function x_features() {
    global $pdo, $m, $role, $u;
    $uid = (int) $u["id"];
    try {
        /* ----- CUSTOMER ----- */
        if ($role == "customer") {
            if (has($m, ["how much did i spend", "how much have i spent", "total spent", "my spending", "my expenses", "spent so far", "money spent"])) {
                $rows = x_q("SELECT price,tip FROM jobs WHERE customer_id=? AND paid=1", [$uid]);
                $n = count($rows); $sum = 0; $tips = 0;
                foreach ($rows as $r) { $sum += (int) $r["price"]; $tips += (int) $r["tip"]; }
                if (!$n) reply("You haven't paid for any job yet 🙂 Your spending will show here after your first paid job.", [], menu_chips($role));
                reply("💳 Here's what you've spent on WorkOnnect:", [x_stats("Your spending", [["Paid jobs", $n], ["Total spent", "₹" . $sum], ["Tips given", "₹" . $tips], ["Average per job", "₹" . round($sum / $n)]])], ["Show my bookings", "Find an electrician near me"]);
            }
            $base = "SELECT j.id,j.title,j.status,j.slot,j.price,w.name person FROM jobs j LEFT JOIN users w ON w.id=j.worker_id WHERE j.customer_id=? ";
            if (has($m, ["upcoming booking", "upcoming job", "next booking", "my next visit", "what is scheduled", "scheduled booking", "when is my"])) {
                $rows = x_q($base . "AND j.status IN ('requested','assigned','in_progress') ORDER BY j.slot ASC LIMIT 5", [$uid]);
                if (!$rows) reply("You have no upcoming bookings. Want to find someone? 🔍", [], cat_chips());
                reply("📅 Your upcoming bookings, soonest first:", [x_bk_card($rows)], ["Show my bookings", "Find an electrician near me"]);
            }
            if (has($m, ["pending booking", "pending request", "awaiting", "waiting for worker"])) {
                $rows = x_q($base . "AND j.status='requested' ORDER BY j.id DESC LIMIT 5", [$uid]);
                if (!$rows) reply("✅ Nothing is waiting for a worker's reply right now.", [], menu_chips($role));
                reply("⏳ These requests are waiting for the worker to accept. If it takes long, you can cancel for free and pick another worker:", [x_bk_card($rows)], ["Show my bookings", "Find an electrician near me"]);
            }
            if (has($m, ["last booking", "latest booking", "recent booking", "previous booking", "last job"])) {
                $rows = x_q($base . "ORDER BY j.id DESC LIMIT 1", [$uid]);
                if (!$rows) reply("You have no bookings yet. Let's find someone! 🔍", [], cat_chips());
                reply("🕘 Your most recent booking:", [x_bk_card($rows)], ["Show my bookings", "Cancel booking #" . (int) $rows[0]["id"]]);
            }
            if (has($m, ["booking history", "past booking", "completed booking", "previous bookings", "old booking"])) {
                $rows = x_q($base . "AND j.status='completed' ORDER BY j.id DESC LIMIT 6", [$uid]);
                if (!$rows) reply("No completed bookings yet. Your finished jobs will appear here.", [], menu_chips($role));
                reply("📚 Your completed bookings:", [x_bk_card($rows)], ["Show my bookings", "Find an electrician near me"]);
            }
        }

        /* ----- WORKER ----- */
        if ($role == "worker") {
            $base = "SELECT j.id,j.title,j.status,j.slot,j.price,c.name person FROM jobs j JOIN users c ON c.id=j.customer_id WHERE j.worker_id=? AND j.status IN ('requested','assigned','in_progress') ";
            if (has($m, ["today's job", "todays job", "jobs today", "today's schedule", "schedule today", "work today"])) {
                $rows = x_q($base . "AND j.slot LIKE ? ORDER BY j.slot ASC LIMIT 8", [$uid, date("Y-m-d") . "%"]);
                if (!$rows) reply("📭 You have no jobs scheduled for today. Keep your availability on to receive requests.", [], menu_chips($role));
                reply("📋 Your jobs for **today**:", [x_bk_card($rows, "Your jobs")], ["Show my new job requests", "How am I performing?"]);
            }
            if (has($m, ["tomorrow's job", "tomorrows job", "jobs tomorrow", "tomorrow's schedule", "work tomorrow"])) {
                $rows = x_q($base . "AND j.slot LIKE ? ORDER BY j.slot ASC LIMIT 8", [$uid, date("Y-m-d", strtotime("+1 day")) . "%"]);
                if (!$rows) reply("📭 No jobs scheduled for tomorrow yet.", [], menu_chips($role));
                reply("📋 Your jobs for **tomorrow**:", [x_bk_card($rows, "Your jobs")], ["Today's jobs", "Show my new job requests"]);
            }
            if (has($m, ["next job", "my next booking", "next customer", "what is next"])) {
                $rows = x_q($base . "AND j.slot >= ? ORDER BY j.slot ASC LIMIT 1", [$uid, date("Y-m-d\TH:i")]);
                if (!$rows) reply("📭 You have no upcoming job right now.", [], menu_chips($role));
                reply("⏭️ Your next job:", [x_bk_card($rows, "Your jobs")], ["Today's jobs", "Show my new job requests"]);
            }
            if (has($m, ["pending payout", "unsettled", "how much is pending", "pending amount", "yet to receive", "to be settled"])) {
                $rows = x_q("SELECT price,commission,settled FROM jobs WHERE worker_id=? AND paid=1", [$uid]);
                $un = 0; $nun = 0;
                foreach ($rows as $r) if (empty($r["settled"])) { $un += (int) $r["price"] - (int) $r["commission"]; $nun++; }
                reply($nun ? "🏦 Money still to be settled to you:" : "✅ Everything is settled. Nothing is pending right now.", $nun ? [x_stats("Pending payout", [["Jobs awaiting settlement", $nun], ["Net amount pending", "₹" . $un]])] : [], ["How much did I earn this month?", "Earnings all time"]);
            }
            if (has($m, ["how many requests", "how many jobs", "how many pending", "job count", "jobs summary", "my job summary"])) {
                $cnt = ["requested" => 0, "assigned" => 0, "in_progress" => 0, "completed" => 0, "cancelled" => 0];
                foreach (x_q("SELECT status,COUNT(*) c FROM jobs WHERE worker_id=? GROUP BY status", [$uid]) as $r) if (isset($cnt[$r["status"]])) $cnt[$r["status"]] = (int) $r["c"];
                reply("📊 Your jobs by status:", [x_stats("Your jobs", [["New requests", $cnt["requested"]], ["Upcoming", $cnt["assigned"]], ["In progress", $cnt["in_progress"]], ["Completed", $cnt["completed"]], ["Cancelled", $cnt["cancelled"]]])], ["Show my new job requests", "Today's jobs"]);
            }
            if (has($m, ["what do others charge", "market rate", "typical price", "price range", "how much should i charge", "what to charge", "set my rate", "set price"])) {
                $cat = detect_cat($m) ?: col($u, ["category", "skill", "service"]);
                if ($cat) {
                    [$r, $c] = run("estimate_service_price", ["category" => (string) $cat]);
                    if ($c) reply("💡 Typical prices customers paid for **$cat** on WorkOnnect. Use it as a guide for your own rate (set it in **My Profile**):", [$c], menu_chips($role));
                    reply("I don't have enough paid **$cat** jobs yet for a reliable range 🙏 Set a fair rate in **My Profile** and adjust as you get reviews.", [], menu_chips($role));
                }
                reply("Tell me the service, e.g. *what do others charge for plumber*, and I'll show typical prices from real jobs 💡", [], ["What do others charge for Plumber?", "What do others charge for Electrician?"]);
            }
        }

        /* ----- ADMIN ----- */
        if ($role == "admin") {
            if (has($m, ["platform overview", "overview", "statistics", "stats", "how many users", "how many workers", "how many customers", "total users", "total workers", "revenue", "commission earned", "analytics", "total bookings", "how many bookings", "platform report", "dashboard"])) {
                $cnt = []; foreach (x_q("SELECT role,COUNT(*) c FROM users GROUP BY role") as $r) $cnt[$r["role"]] = (int) $r["c"];
                $st = []; foreach (x_q("SELECT status,COUNT(*) c FROM jobs GROUP BY status") as $r) $st[$r["status"]] = (int) $r["c"];
                $pd = x_q("SELECT COUNT(*) n, COALESCE(SUM(price),0) p, COALESCE(SUM(commission),0) c FROM jobs WHERE paid=1")[0];
                $ban = 0; try { $ban = (int) x_q("SELECT COUNT(*) c FROM users WHERE banned=1")[0]["c"]; } catch (Throwable $e) {}
                reply("📊 **Platform overview** (live from your database):", [
                    x_stats("Users", [["Customers", $cnt["customer"] ?? 0], ["Workers", $cnt["worker"] ?? 0], ["Banned users", $ban]]),
                    x_stats("Bookings", [["Total bookings", array_sum($st)], ["Requested", $st["requested"] ?? 0], ["Upcoming", $st["assigned"] ?? 0], ["In progress", $st["in_progress"] ?? 0], ["Completed", $st["completed"] ?? 0], ["Cancelled", $st["cancelled"] ?? 0]]),
                    x_stats("Revenue", [["Paid jobs", (int) $pd["n"]], ["Gross paid", "₹" . (int) $pd["p"]], ["Commission earned", "₹" . (int) $pd["c"]]]),
                ], ["Unverified workers", "Recent bookings"]);
            }
            if (has($m, ["unverified", "pending verification", "not verified", "verification pending", "need verification"])) {
                $rows = x_q("SELECT * FROM users WHERE role='worker' AND (verified IS NULL OR verified=0 OR verified='') AND (banned IS NULL OR banned=0) ORDER BY id DESC LIMIT 5");
                if (!$rows) reply("✅ All workers are verified.", [], ["Platform overview"]);
                $items = []; foreach ($rows as $i => $r) $items[] = wcard($r, $i + 1, wstats($r["id"]));
                reply("🕵️ Workers still waiting for verification (latest " . count($items) . "):", [["type" => "workers", "items" => $items]], ["Platform overview", "Recent bookings"]);
            }
            if (has($m, ["recent booking", "latest jobs", "recent jobs", "latest bookings", "new bookings"])) {
                $rows = x_q("SELECT j.id,j.title,j.status,j.slot,j.price,CONCAT(c.name,' → ',COALESCE(w.name,'?')) person FROM jobs j JOIN users c ON c.id=j.customer_id LEFT JOIN users w ON w.id=j.worker_id ORDER BY j.id DESC LIMIT 8");
                reply($rows ? "🕘 Latest bookings (customer → worker):" : "No bookings yet.", $rows ? [x_bk_card($rows, "Your bookings")] : [], ["Platform overview", "Unverified workers"]);
            }
        }
    } catch (Throwable $e) {
        error_log("x_features: " . $e->getMessage());
        reply("Sorry, I couldn't read that data just now. Please try again in a moment.", [], menu_chips($role));
    }
}

/* ---------- entry point called from x_faq() ---------- */
function x_more() {
    global $m;
    if (preg_match('/^(yes|yeah|yep|yup|no|nope|nah|sure)[.!\s]*$/i', $m)) reply(xm("yn"), [], menu_chips($GLOBALS["role"]));
    x_features();
    x_kb();
}

/* ---------- looks like a general question? ---------- */
function x_general_q($m, $role) {
    if (preg_match('/\?|^(what|why|how|who|when|where|which|can|could|is|are|do|does|will|should|tell me|explain|define|meaning)\b/iu', trim((string) $m)))
        reply(xm("general_q"), [], menu_chips($role));
}

/* ---------- better online AI: remembers the last turns, knows platform facts ---------- */
function x_llm2($msg) {
    global $role, $L, $u;
    if (!defined("AI_KEY") || AI_KEY === "" || !function_exists("curl_init")) return null;
    $lang = ["en" => "English", "kn" => "Kannada", "hi" => "Hindi"][$L] ?? "English";
    $sys = "You are WorkOnnect Assistant inside a local-services marketplace in India (customers book verified electricians, plumbers, painters, carpenters, cleaners, drivers, mechanics, AC technicians). "
        . "The user is a $role named " . explode(" ", trim((string) ($u["name"] ?? "")))[0] . ". Reply in $lang, friendly, in at most 5 short sentences. "
        . "You may answer any general question (home repair, safety, maths, language, general knowledge) briefly and honestly; say when you are unsure. "
        . "Platform facts: cancelling is free until a worker accepts, then 5% (min 20 rupees) or 20% (min 50 rupees) within 24h or when the worker is on the way; payment is on the job page after work; referral bonus is 50 rupees each; commission is deducted from workers' paid jobs. "
        . "You cannot search, book, cancel or change anything yourself: tell the user to type commands like 'Find an electrician', 'Show my bookings' or 'Estimate a plumbing job'. "
        . "Never invent worker names, prices, booking details or policies beyond the facts above. For electrical, gas or structural danger advise a professional and 112. Never ask for OTPs, passwords or card details.";
    $hist = array_slice((array) ($_SESSION["ai_h"] ?? []), -6);
    $hist[] = ["role" => "user", "content" => mb_substr((string) $msg, 0, 600)];
    $ch = curl_init("https://api.anthropic.com/v1/messages");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => ["content-type: application/json", "x-api-key: " . AI_KEY, "anthropic-version: 2023-06-01"],
        CURLOPT_POSTFIELDS => json_encode(["model" => "claude-sonnet-5-5", "max_tokens" => 450, "system" => $sys, "messages" => $hist], JSON_UNESCAPED_UNICODE)]);
    $res = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if (!$res || $code != 200) return null;
    $j = json_decode($res, true); $t = "";
    foreach (($j["content"] ?? []) as $b) if (($b["type"] ?? "") == "text") $t .= $b["text"];
    $t = trim($t);
    if ($t === "") return null;
    $t = mb_substr($t, 0, 900);
    $h = (array) ($_SESSION["ai_h"] ?? []);
    $h[] = ["role" => "user", "content" => mb_substr((string) $msg, 0, 600)];
    $h[] = ["role" => "assistant", "content" => $t];
    $_SESSION["ai_h"] = array_slice($h, -8);
    return $t;
}