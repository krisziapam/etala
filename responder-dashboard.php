<?php require_once __DIR__.'/includes/v2.php';$user=require_login();$page_title='My Dashboard';require __DIR__.'/includes/header.php';?>
<div class="v2-page-heading"><div><span class="v2-eyebrow">RESPONDER / FIELD OPERATIONS</span><h1>My Dashboard</h1><p>Welcome, <?=e($user['first_name'])?>. Your operational tools are ready.</p></div><a class="v2-btn" href="<?=e(url('report-new.php'))?>">+ Record Incident</a></div>
<section class="v2-card"><div class="v2-section-head"><div><h2>Quick Actions</h2><p>Frequently used responder tools</p></div></div><div class="v2-quick-grid"><a href="<?=e(url('report-new.php'))?>"><span>＋</span><strong>Record Incident</strong><small>Submit a new report</small></a><a href="<?=e(url('backup.php#request'))?>"><span>✚</span><strong>Request Backup</strong><small>Get operational assistance</small></a><a href="<?=e(url('transfers.php'))?>"><span>⇄</span><strong>Incident Handover</strong><small>Transfer an assignment</small></a><a href="<?=e(url('inventory.php'))?>"><span>▣</span><strong>Equipment Requests</strong><small>Request supplies or gear</small></a></div></section>
<section class="v2-card v2-operations"><div class="v2-section-head"><div><h2>Today's Live Operations</h2><p>Incident updates, backup assistance, and handovers.</p></div><a class="v2-text-link" href="<?=e(url('operations.php'))?>">Open live feed →</a></div><div class="v2-action-links"><a href="<?=e(url('my-reports.php'))?>">My Reports</a><a href="<?=e(url('backup.php#requests'))?>">View Backup Requests</a><a href="<?=e(url('directory.php'))?>">Emergency Directory</a></div></section>
<div class="v2-dashboard-grid"><section class="v2-card"><div class="v2-section-head"><div><h2>My Incident Calendar</h2><p>Review authorized incident activity by date</p></div></div><label class="v2-inline-label">Month <input type="month" id="v2-month"></label><div id="v2-calendar">Loading calendar…</div></section><section class="v2-card" id="etala-weather-v3">
  <div class="ew-heading"><div><h2>Weather Forecast</h2><p class="ew-subtitle">Local conditions for field operations</p></div>
  <form aria-label="Search weather by city"><input type="search" value="Cainta" aria-label="City or municipality" placeholder="Search a city" required><button type="submit">Search</button></form></div>
  <div data-weather-current aria-live="polite"></div>
  <h3 class="ew-forecast-title">7-day forecast</h3>
  <div class="ew-days" data-weather-days></div>
  <p class="ew-status" data-weather-status role="status">Loading forecast…</p>
</section></div>
<script defer src="<?=e(url('assets/js/weather-polished.js'))?>?v=20261121b"></script>
<script>window.ETALA_CAL_URL=<?=json_encode(url('api-calendar.php'))?>;window.ETALA_REPORT_URL=<?=json_encode(url('report.php'))?>;</script><script defer src="<?=e(url('assets/js/dashboard-widgets.js'))?>"></script>
<?php require __DIR__.'/includes/footer.php';?>
