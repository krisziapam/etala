<?php require_once __DIR__.'/includes/v2.php';$user=require_login();$page_title="Today's Live Operations";require __DIR__.'/includes/header.php';?>
<h1>Today's Live Operations</h1><p>Rolling operational updates for <?=e(date('F j, Y'))?> (Philippine time). Updates automatically every 15 seconds while this page is open.</p>
<div class="v2-stats"><div class="v2-card">Ongoing incidents <strong id="v2-ongoing">—</strong></div><div class="v2-card">Pending backup <strong id="v2-pending">—</strong></div><div class="v2-card">Today's updates <strong id="v2-total">—</strong></div></div>
<div class="v2-card"><div class="v2-between"><h2>Live Activity Feed</h2><a href="<?=e(url('backup.php'))?>">View Backup Requests</a></div>
<p><label>Filter <select id="v2-filter"><option value="">All updates</option><option value="backup">Backup</option><option value="transfer">Transfers</option><option value="incident">Incidents</option><option value="inventory">Inventory</option></select></label></p>
<p class="v2-muted" id="v2-connection">Connecting…</p><div id="v2-feed" aria-live="polite">Loading…</div></div>
<script>window.ETALA_OPERATIONS_URL=<?=json_encode(url('api-operations.php'))?>;</script><script src="<?=e(url('assets/js/operations.js'))?>"></script>
<?php require __DIR__.'/includes/footer.php';?>
