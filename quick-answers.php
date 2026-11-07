<?php
require "config.php";
ob_start();
head("Quick answers");
$pageHead = ob_get_clean();
$pageHead = preg_replace(
    '#<nav>.*?</nav>#s',
    '<nav aria-label="Page header"><a class="logo" href="index.php">Work<span>onnect</span></a></nav>',
    $pageHead,
    1,
    $navReplaced,
);
if ($navReplaced !== 1) {
    throw new RuntimeException("Could not simplify the Quick Answers page header.");
}
echo $pageHead;
?>
<section aria-labelledby="answers-title">
    <h1 id="answers-title">Quick answers</h1>
    <p>Helpful details about finding a worker, arranging a job, and getting support.</p>

    <details class="card">
        <summary>How do I post a job?</summary>
        <p>Register or sign in as a customer, choose Post Job, and enter the service and job details. Workers can then respond with offers.</p>
    </details>
    <details class="card">
        <summary>Can I choose a specific worker?</summary>
        <p>Yes. Browse worker profiles and use the Hire option on a profile to send a request to that worker.</p>
    </details>
    <details class="card">
        <summary>How do I become a verified worker?</summary>
        <p>Create a worker account, open Get Verified, and submit your ID for admin review. The verified badge appears after approval.</p>
    </details>
    <details class="card">
        <summary>How do payments work?</summary>
        <p>Review the payment options and amount shown on the job's payment page. Payment methods can vary by job and configuration.</p>
    </details>
    <details class="card">
        <summary>How can I report a problem with a job?</summary>
        <p>Open the job page and use Report a problem, or contact the team from the Help page.</p>
    </details>

    <p><a class="btn" href="help.php">More help and contact options</a></p>
</section>
<?php foot(); ?>
