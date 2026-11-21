<?php
require_once __DIR__ . '/includes/v2.php';
$user = require_login();

$report = get_report((int) ($_GET['id'] ?? 0));
if (!$report) {
    http_response_code(404);
    exit('Report not found.');
}
$report_id = (int) $report['report_id'];
// Responders can access only reports they filed.
if (!v2_can_report($user, $report_id)) {
    http_response_code(403);
    exit('Access denied.');
}

// An admin deletes a duplicate (the button is near the top of the page).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    require_admin();
    if (!csrf_ok()) {
        http_response_code(400);
        exit('Your session expired. Please go back and try again.');
    }
    delete_report($report_id);
    redirect('admin/reports.php?deleted=1');
}

// Who may add an update? Only on an ongoing fire report, by its author or an admin.
$can_follow_up = (int) $report['needs_follow_up'] === 1
    && $report['status'] === 'ongoing'
    && (v2_can_report($user, $report_id));

$vehicles = get_vehicles();
$people   = get_active_users();
$crew     = get_report_crew($report_id);
$images = get_report_images($report_id);
$last_type = get_last_follow_up_type($report_id);
$allowed   = next_follow_up_types($last_type);


$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$can_follow_up) {
        http_response_code(403);
        exit('You cannot add an update to this report.');
    }
    if (!csrf_ok()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $type    = $_POST['update_type'] ?? '';
        $when    = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['update_datetime'] ?? '');
        $details = trim($_POST['details'] ?? '');
        $closing = in_array($type, ['fire out', 'negative'], true);

        $vehicle_id = ($_POST['vehicle_id'] ?? '') === '' ? null : (int) $_POST['vehicle_id'];
        $driver_id  = ($_POST['driver_id'] ?? '')  === '' ? null : (int) $_POST['driver_id'];
        $crew_ids   = array_unique(array_map('intval', (array) ($_POST['crew'] ?? [])));
        $narrative  = trim($_POST['narrative'] ?? '');
        $alarm      = trim($_POST['alarm_level'] ?? '');
        $structures = filter_var($_POST['affected_structures'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        $valid_vehicles = array_map('intval', array_column($vehicles, 'vehicle_id'));
        $valid_people   = array_map('intval', array_column($people, 'user_id'));

        if (!in_array($type, $allowed, true)) {
            $errors[] = 'That update is not available at this stage.';
        }
        if (!$when) {
            $errors[] = 'Please enter a valid date and time.';
        }

        // Closing the report: nothing may stay blank.
        if ($closing) {
            if ($vehicle_id === null || !in_array($vehicle_id, $valid_vehicles, true)) {
                $errors[] = 'Please choose the vehicle.';
            }
            if ($driver_id === null || !in_array($driver_id, $valid_people, true)) {
                $errors[] = 'Please choose the driver.';
            }
            if (!$crew_ids || array_diff($crew_ids, $valid_people)) {
                $errors[] = 'Please tick at least one crew member.';
            }
            if ($narrative === '') {
                $errors[] = 'Please write the narrative.';
            }
            if ($type === 'fire out') {
                if ($structures === false) {
                    $errors[] = 'Please enter the number of affected houses (0 if none).';
                }
                if ($alarm === '') {
                    $errors[] = 'Please enter the alarm level.';
                }
            }
        }

        if (!$errors) {
            $when_sql = $when->format('Y-m-d H:i:s');
            if ($closing) {
                close_fire_report($report_id, (int) $user['user_id'], $type, $when_sql, $details ?: null, [
                    'vehicle_id'          => $vehicle_id,
                    'driver_id'           => $driver_id,
                    'narrative'           => $narrative,
                    'crew'                => $crew_ids,
                    'affected_structures' => $structures === false ? null : $structures,
                    'alarm_level'         => $alarm ?: null,
                ]);
            } else {
                add_follow_up($report_id, (int) $user['user_id'], $type, $when_sql, $details ?: null);
            }
            redirect('report.php?id=' . $report_id);
        }
    }
}

// Values to show in the form: what was just typed, or what the report already has.
$f_type    = $_POST['update_type'] ?? '';
$f_vehicle = $_POST['vehicle_id'] ?? $report['vehicle_id'];
$f_driver  = $_POST['driver_id'] ?? $report['driver_id'];
$f_narr    = $_POST['narrative'] ?? $report['narrative'];
$f_struct  = $_POST['affected_structures'] ?? $report['affected_structures'];
$f_alarm   = $_POST['alarm_level'] ?? $report['alarm_level'];
$f_crew    = $_POST
    ? array_map('intval', (array) ($_POST['crew'] ?? []))
    : array_map('intval', array_column($crew, 'user_id'));

$follow_ups = get_follow_ups($report_id);

$page_title = 'Report details';
require __DIR__ . '/includes/header.php';
?>
<p><a href="<?= e(url('today.php')) ?>">&larr; Back to Today</a></p>

<h1><?= e($report['type_name']) ?></h1>

<?php if (!empty($_GET['edited'])): ?>
  <p role="status">Changes saved.</p>
<?php endif; ?>
<?php if ((int) $user['user_id'] === (int) $report['reported_by'] || $user['role'] === 'admin'): ?>
  <p><a href="<?= e(url('report-edit.php?id=' . $report_id)) ?>">Edit report</a></p>
<?php endif; ?>
<?php if ($user['role'] === 'admin'): ?>
  <form method="post" onsubmit="return confirm('Delete this report as a duplicate?');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <button type="submit">Delete duplicate</button>
  </form>
<?php endif; ?>

<p>
  <strong><?= e($report['status'] === 'ongoing' ? 'ONGOING' : 'COMPLETED') ?></strong>
  &middot; <?= e($report['category']) ?>
</p>

<dl>
  <dt>Report number</dt>
  <dd><?= e(report_number($report)) ?></dd>
  <dt>Date and time</dt>
  <dd><?= e((new DateTime($report['incident_datetime']))->format('M j, Y g:i A')) ?></dd>

  <?php if ($report['incident_number']): ?>
    <dt>Reference code</dt>
    <dd><?= e($report['incident_number']) ?></dd>
  <?php endif; ?>

  <dt>Address</dt>
  <dd><?= e($report['address']) ?></dd>
  <?php if ($report['latitude'] !== null): ?>
    <dt>Map</dt>
    <dd>
      <div id="view-map" style="height: 250px"
           data-lat="<?= e((string) $report['latitude']) ?>"
           data-lng="<?= e((string) $report['longitude']) ?>"></div>
      <a href="https://www.openstreetmap.org/?mlat=<?= e((string) $report['latitude']) ?>&amp;mlon=<?= e((string) $report['longitude']) ?>#map=18/<?= e((string) $report['latitude']) ?>/<?= e((string) $report['longitude']) ?>"
         target="_blank" rel="noopener">Open in OpenStreetMap</a>
    </dd>
  <?php endif; ?>  

  <?php if ($report['landmark']): ?>
    <dt>Landmark</dt>
    <dd><?= e($report['landmark']) ?></dd>
  <?php endif; ?>

    <?php if ((int) $report['needs_follow_up'] === 1): ?>
    <dt>Affected houses or structures</dt>
    <dd><?= $report['affected_structures'] !== null ? (int) $report['affected_structures'] : '-' ?></dd>
    <dt>Alarm level</dt>
    <dd><?= e($report['alarm_level'] ?: '-') ?></dd>
  <?php elseif ($report['category'] === 'Ambulance'):?>
    <dt>Destination</dt>
    <dd><?= e($report['destination'] ?: '-') ?></dd>
    <dt>Patient condition</dt>
    <dd><?= nl2br(e($report['patient_condition'] ?: '-')) ?></dd>
  <?php endif; ?>

  <dt>Vehicle</dt>
  <dd><?= e($report['unit_name'] ?: '-') ?></dd>

  <dt>Driver</dt>
  <dd><?= $report['driver_first'] ? e($report['driver_first'] . ' ' . $report['driver_last']) : '-' ?></dd>

  <dt>Crew</dt>
  <dd>
    <?php if ($crew): ?>
      <?php foreach ($crew as $c): ?>
        <?= e($c['first_name'] . ' ' . $c['last_name']) ?><br>
      <?php endforeach; ?>
    <?php else: ?>
      -
    <?php endif; ?>
  </dd>

  <dt>Narrative</dt>
  <dd><?= nl2br(e($report['narrative'] ?: '-')) ?></dd>

  <?php if ($images): ?>
    <dt>Photos</dt>
    <dd>
      <?php foreach ($images as $img): ?>
        <a href="<?= e(url('photo.php?id=' . (int) $img['image_id'])) ?>" target="_blank" rel="noopener">
          <img src="<?= e(url('photo.php?id=' . (int) $img['image_id'])) ?>"
               alt="Photo of the incident" width="160">
        </a>
      <?php endforeach; ?>
    </dd>
  <?php endif; ?>  

  <dt>Filed by</dt>
  <dd><?= e($report['filer_first'] . ' ' . $report['filer_last']) ?></dd>
</dl>
<h2>Updates</h2>

<?php if (!$follow_ups): ?>
  <p>No updates yet.</p>
<?php endif; ?>

<?php foreach ($follow_ups as $f): ?>
  <p>
    <strong><?= e(follow_up_label($f['update_type'])) ?></strong>
    &middot; <?= e((new DateTime($f['update_datetime']))->format('M j, g:i A')) ?>
    &middot; <?= e($f['first_name'] . ' ' . $f['last_name']) ?>
    <?php if ($f['details']): ?>
      <br><?= nl2br(e($f['details'])) ?>
    <?php endif; ?>
  </p>
<?php endforeach; ?>

<?php foreach ($errors as $err): ?>
  <p role="alert"><?= e($err) ?></p>
<?php endforeach; ?>

<?php if ($can_follow_up): ?>
  <h3>Add an update</h3>
  <form method="post">
    <?= csrf_field() ?>
    <p>
      <label for="update_type">Update *</label><br>
      <select id="update_type" name="update_type" required>
        <option value="">Choose an update</option>
         <?php foreach ($allowed as $t): ?>
          <option value="<?= e($t) ?>" <?= $f_type === $t ? 'selected' : '' ?>><?= e(follow_up_label($t)) ?></option>
        <?php endforeach; ?>
      </select>
    </p>
    <p>
      <label for="update_datetime">Date and time</label><br>
      <input id="update_datetime" name="update_datetime" type="datetime-local"
             value="<?= e($_POST['update_datetime'] ?? date('Y-m-d\TH:i')) ?>">
    </p>
    <p>
      <label for="details">Details (optional)</label><br>
      <textarea id="details" name="details" rows="3"><?= e($_POST['details'] ?? '') ?></textarea>
    </p>

    <fieldset id="close-fields" hidden>
      <legend>To close this report, every field must be filled</legend>

      <fieldset id="fire-out-fields" hidden>
        <legend>Fire details</legend>
        <p>
          <label for="affected_structures">Affected houses or structures *</label><br>
          <input id="affected_structures" name="affected_structures" type="number" min="0" required
                 value="<?= e((string) $f_struct) ?>">
        </p>
        <p>
          <label for="alarm_level">Alarm level *</label><br>
          <input id="alarm_level" name="alarm_level" type="text" maxlength="20" required
                 value="<?= e((string) $f_alarm) ?>">
        </p>
      </fieldset>

      <p>
        <label for="vehicle_id">Vehicle *</label><br>
        <select id="vehicle_id" name="vehicle_id" required>
          <option value="">Choose a vehicle</option>
          <?php foreach ($vehicles as $v): ?>
            <option value="<?= (int) $v['vehicle_id'] ?>"
              <?= (int) $f_vehicle === (int) $v['vehicle_id'] ? 'selected' : '' ?>>
              <?= e($v['unit_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </p>

      <p>
        <label for="driver_id">Driver *</label><br>
        <select id="driver_id" name="driver_id" required>
          <option value="">Choose a driver</option>
          <?php foreach ($people as $p): ?>
            <option value="<?= (int) $p['user_id'] ?>"
              <?= (int) $f_driver === (int) $p['user_id'] ? 'selected' : '' ?>>
              <?= e($p['first_name'] . ' ' . $p['last_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </p>

      <fieldset>
        <legend>Crew * (tick at least one)</legend>
        <?php foreach ($people as $p): ?>
          <label>
            <input type="checkbox" name="crew[]" value="<?= (int) $p['user_id'] ?>"
              <?= in_array((int) $p['user_id'], $f_crew, true) ? 'checked' : '' ?>>
            <?= e($p['first_name'] . ' ' . $p['last_name']) ?>
          </label><br>
        <?php endforeach; ?>
      </fieldset>

      <p>
        <label for="narrative">Narrative *</label><br>
        <textarea id="narrative" name="narrative" rows="4" required><?= e((string) $f_narr) ?></textarea>
      </p>
    </fieldset>

    <button type="submit">Post update</button>
  </form>
  <script src="<?= e(url('assets/js/followup-form.js')) ?>"></script>
<?php elseif ((int) $report['needs_follow_up'] === 1 && $report['status'] === 'completed'): ?>
  <p>This report is completed, so no more updates can be added.</p>
<?php endif; ?>
<?php if ($report['latitude'] !== null): ?>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
          integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <script src="<?= e(url('assets/js/report-view-map.js')) ?>"></script>
<?php endif; ?>
<section class="v2-card"><h2>Incident Operations</h2>
<p><a href="<?=e(url('backup.php#request'))?>">Request Backup</a> · <a href="<?=e(url('transfers.php?report_id='.$report_id))?>">Transfer Incident</a></p>
<h3>Activity Timeline</h3>
<?php $q=db()->prepare('SELECT ev.*,u.first_name,u.last_name FROM operational_event ev JOIN user u ON u.user_id=ev.actor_id WHERE ev.report_id=? ORDER BY ev.event_id DESC LIMIT 50');$q->execute([$report_id]);$v2timeline=$q->fetchAll();?>
<?php if(!$v2timeline):?><p>No Version 2 operational events yet.</p><?php endif;?>
<?php foreach($v2timeline as $ev):?><article class="v2-entry"><strong><?=e($ev['title'])?></strong><p><?=e($ev['details'])?></p><small><?=e($ev['created_at'])?> · <?=e($ev['first_name'].' '.$ev['last_name'])?></small></article><?php endforeach;?></section>
<?php require __DIR__ . '/includes/footer.php'; ?>