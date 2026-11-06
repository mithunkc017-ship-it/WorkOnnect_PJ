<?php
require "config.php";
head("Why Workonnect");
?>
<section aria-labelledby="why-title">
    <h1 id="why-title">Local services, with the details in one place</h1>
    <p>Workonnect helps customers find local professionals and gives workers a place to share their services and respond to jobs.</p>

    <div class="grid">
        <article class="card">
            <span class="material-icons mi-lg" aria-hidden="true">manage_search</span>
            <h2>Find a relevant service</h2>
            <p>Browse skills and worker profiles, then narrow your search by service or area.</p>
        </article>
        <article class="card">
            <span class="material-icons mi-lg" aria-hidden="true">compare_arrows</span>
            <h2>Compare before choosing</h2>
            <p>Review worker profiles and the offers you receive before accepting a job offer.</p>
        </article>
        <article class="card">
            <span class="material-icons mi-lg" aria-hidden="true">verified_user</span>
            <h2>See verification status</h2>
            <p>Profiles display a verification badge when the worker's submitted ID has been approved.</p>
        </article>
        <article class="card">
            <span class="material-icons mi-lg" aria-hidden="true">timeline</span>
            <h2>Keep job updates together</h2>
            <p>Use the job page to follow progress, communicate, and review next steps.</p>
        </article>
        <article class="card">
            <span class="material-icons mi-lg" aria-hidden="true">support_agent</span>
            <h2>Know where to get help</h2>
            <p>Visit Help for common questions or contact the team if you need assistance.</p>
        </article>
    </div>

    <p><a class="btn" href="workers.php">Find Workers</a> <a href="help.php">Visit Help</a></p>
</section>
<?php foot(); ?>
