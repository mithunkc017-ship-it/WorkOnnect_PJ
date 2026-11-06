<?php
require "config.php";
head("How it works");
?>
<section aria-labelledby="how-title">
    <h1 id="how-title">A clearer way to get local work done</h1>
    <p>Post what you need, compare responses, and keep the job details in one place.</p>

    <div class="grid">
        <article class="card">
            <span class="material-icons mi-lg" aria-hidden="true">post_add</span>
            <h2>1. Describe the job</h2>
            <p>Sign in as a customer and share the service, location, preferred time, and details workers need to respond.</p>
        </article>
        <article class="card">
            <span class="material-icons mi-lg" aria-hidden="true">forum</span>
            <h2>2. Review worker offers</h2>
            <p>Workers can respond to your request. Review their profile, experience, rating, and offer before deciding.</p>
        </article>
        <article class="card">
            <span class="material-icons mi-lg" aria-hidden="true">handshake</span>
            <h2>3. Choose the right fit</h2>
            <p>Compare the available offers and accept the one that best matches your needs and schedule.</p>
        </article>
        <article class="card">
            <span class="material-icons mi-lg" aria-hidden="true">task_alt</span>
            <h2>4. Follow the job through</h2>
            <p>Use the job page to follow updates, communicate, and review payment and completion details.</p>
        </article>
    </div>

    <p class="card"><b>Are you a worker?</b> Create a worker account, complete your profile, and browse open jobs to find requests that match your skills.</p>
    <p><a class="btn" href="post_job.php">Post a Job</a> <a class="btn" href="register.php">Join as Worker</a></p>
</section>
<?php foot(); ?>
