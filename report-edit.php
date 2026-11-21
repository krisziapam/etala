<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();

$report = get_report((int) ($_GET['id'] ?? 0));
if (!$report) {
    http_response_code(404);
    exit('Report not found.');
}
$report_id = (int) $report['report_id'];

// Only the person who filed the report, or an admin, may edit it.
if ((int) $user['user_id'] !== (int) $report['reported_by'] && $user['role'] !== 'admin') {
    http_response_code(403);
    exit('You can only edit your own reports.');
}

$is_fire      = (int) $report['needs_follow_up'] === 1;
$is_ambulance = $report['category'] === 'Ambulance';
// A completed report (or one completed when saved) cannot have blank fields.
$strict       = !$is_fire || $report['status'] === 'completed';
$closed_by_fire_out = $is_fire && $report['status'] === 'completed'
    && get_last_follow_up_type($report_id) === 'fire out';

$vehicles = get_vehicles();
$people   = get_active_users();
$crew     = get_report_crew($report_id);

// Keep people and vehicles that were deactivated after this report was filed.
$known = array_map('intval', array_column($people, 'user_id'));
foreach ($crew as $c) {
    if (!in_array((int) $c['user_id'], $known, true)) {
        $people[] = $c;
        $known[]  = (int) $c['user_id'];
    }
}
if ($report['driver_id'] && !in_array((int) $report['driver_id'], $known, true)) {
    $people[] = ['user_id' => $report['driver_id'],
                 'first_name' => $report['driver_first'], 'last_name' => $report['driver_last']];
}
$known_v = array_map('intval', array_column($vehicles, 'vehicle_id'));
if ($report['vehicle_id'] && !in_array((int) $report['vehicle_id'], $known_v, true)) {
    $vehicles[] = ['vehicle_id' => $report['vehicle_id'], 'unit_name' => $report['unit_name']];
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        // 1. Read and clean what was typed
        $address  = trim($_POST['address'] ?? '');
        $landmark = trim($_POST['landmark'] ?? '');
        $number   = trim($_POST['incident_number'] ?? '');
        $narr     = trim($_POST['narrative'] ?? '');
        $dt       = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['incident_datetime'] ?? '');
        $lat = ($_POST['latitude'] ?? '')  === '' ? null : filter_var($_POST['latitude'], FILTER_VALIDATE_FLOAT);
        $lng = ($_POST['longitude'] ?? '') === '' ? null : filter_var($_POST['longitude'], FILTER_VALIDATE_FLOAT);

        $vehicle_id = ($_POST['vehicle_id'] ?? '') === '' ? null : (int) $_POST['vehicle_id'];
        $driver_id  = ($_POST['driver_id'] ?? '')  === '' ? null : (int) $_POST['driver_id'];
        $crew_ids   = array_unique(array_map('intval', (array) ($_POST['crew'] ?? [])));

        $structures  = filter_var($_POST['affected_structures'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $alarm       = trim($_POST['alarm_level'] ?? '');
        $destination = trim($_POST['destination'] ?? '');
        $condition   = trim($_POST['patient_condition'] ?? '');

        $valid_vehicles = array_map('intval', array_column($vehicles, 'vehicle_id'));
        $valid_people   = array_map('intval', array_column($people, 'user_id'));

        // 2. Check it
        if ($address === '' || mb_strlen($address) > 255) {
            $errors[] = 'The address is required (up to 255 characters).';
        }
        if (!$dt) {
            $errors[] = 'Please enter a valid date and time.';
        }
        if ($vehicle_id !== null && !in_array($vehicle_id, $valid_vehicles, true)) {
            $errors[] = 'That vehicle is not valid.';
        }
        if ($driver_id !== null && !in_array($driver_id, $valid_people, true)) {
            $errors[] = 'That driver is not valid.';
        }
        if (array_diff($crew_ids, $valid_people)) {
            $errors[] = 'A crew member is not valid.';
        }
        if ($lat === false || $lng === false || ($lat === null) !== ($lng === null)
            || ($lat !== null && ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180))) {
            $errors[] = 'The map pin is not valid. Please pin it again or clear it.';
        }
        if ($strict) {
            if ($vehicle_id === null) { $errors[] = 'Please choose the vehicle.'; }
            if ($driver_id === null)  { $errors[] = 'Please choose the driver.'; }
            if (!$crew_ids)           { $errors[] = 'Please tick at least one crew member.'; }
            if ($narr === '')         { $errors[] = 'Please write the narrative.'; }
            if ($is_ambulance && $destination === '') { $errors[] = 'Please enter the destination.'; }
            if ($is_ambulance && $condition === '')   { $errors[] = 'Please enter the patient condition.'; }
        }
        if ($closed_by_fire_out) {
            if ($structures === false) { $errors[] = 'Please enter the number of affected houses (0 if none).'; }
            if ($alarm === '')         { $errors[] = 'Please enter the alarm level.'; }
        }

        // 3. Save it
        if (!$errors) {
            update_report($report_id, [
                'is_fire'             => $is_fire,
                'is_ambulance'        => $is_ambulance,
                'incident_number'     => $number ?: null,
                'incident_datetime'   => $dt->format('Y-m-d H:i:s'),
                'address'             => $address,
                'landmark'            => $landmark ?: null,
                'latitude'            => $lat,
                'longitude'           => $lng,
                'vehicle_id'          => $vehicle_id,
                'driver_id'           => $driver_id,
                'narrative'           => $narr ?: null,
                'affected_structures' => $structures === false ? null : $structures,
                'alarm_level'         => $alarm ?: null,
                'destination'         => $destination ?: null,
                'patient_condition'   => $condition ?: null,
                'crew'                => $crew_ids,
            ]);
            redirect('report.php?id=' . $report_id . '&edited=1');
        }
    }
}

// Values to show: what was just typed, or what the report already has.
$f_dt      = $_POST['incident_datetime'] ?? (new DateTime($report['incident_datetime']))->format('Y-m-d\TH:i');
$f_number  = $_POST['incident_number'] ?? $report['incident_number'];
$f_address = $_POST['address'] ?? $report['address'];
$f_land    = $_POST['landmark'] ?? $report['landmark'];
$f_lat     = $_POST['latitude'] ?? $report['latitude'];
$f_lng     = $_POST['longitude'] ?? $report['longitude'];
$f_vehicle = $_POST['vehicle_id'] ?? $report['vehicle_id'];
$f_driver  = $_POST['driver_id'] ?? $report['driver_id'];
$f_narr    = $_POST['narrative'] ?? $report['narrative'];
$f_struct  = $_POST['affected_structures'] ?? $report['affected_structures'];
$f_alarm   = $_POST['alarm_level'] ?? $report['alarm_level'];
$f_dest    = $_POST['destination'] ?? $report['destination'];
$f_cond    = $_POST['patient_condition'] ?? $report['patient_condition'];
$f_crew    = $_POST
    ? array_map('intval', (array) ($_POST['crew'] ?? []))
    : array_map('intval', array_column($crew, 'user_id'));

$page_title = 'Edit report';
require __DIR__ . '/includes/header.php';
?>
<p><a href="<?= e(url('report.php?id=' . $report_id)) ?>">&larr; Back to the report</a></p>
<h1>Edit report</h1>
<p><strong><?= e($report['type_name']) ?></strong> (the type cannot be changed)</p>

<?php foreach ($errors as $err): ?>
  <p role="alert"><?= e($err) ?></p>
<?php endforeach; ?>

<form method="post">
  <?= csrf_field() ?>

  <p>
    <label for="incident_datetime">Date and time</label><br>
    <input id="incident_datetime" name="incident_datetime" type="datetime-local" required
           value="<?= e($f_dt) ?>">
  </p>

  <p>
    <label for="incident_number">Reference code (if any)</label><br>
    <input id="incident_number" name="incident_number" type="text" maxlength="30"
           value="<?= e((string) $f_number) ?>">
  </p>

  <p>
    <label for="address">Address *</label><br>
    <input id="address" name="address" type="text" maxlength="255" required
           value="<?= e($f_address) ?>">
  </p>

  <p>
    <label for="landmark">Landmark</label><br>
    <input id="landmark" name="landmark" type="text" maxlength="150"
           value="<?= e((string) $f_land) ?>">
  </p>

  <div>
    <p>Pin the location on the map (optional)</p>
    <div id="map" style="height: 300px"
         data-lat="<?= e((string) (config('map_lat') ?? 14.5786)) ?>"
         data-lng="<?= e((string) (config('map_lng') ?? 121.1222)) ?>"
         <?php if ($f_lat !== null && $f_lat !== ''): ?>
         data-pin-lat="<?= e((string) $f_lat) ?>" data-pin-lng="<?= e((string) $f_lng) ?>"
         <?php endif; ?>></div>
    <p>
      <button type="button" id="use-location">Use my location</button>
      <button type="button" id="clear-pin">Clear pin</button>
    </p>
    <p id="pin-status" role="status">No pin yet. Tap the map, or use "Use my location".</p>
    <input type="hidden" id="latitude" name="latitude" value="<?= e((string) $f_lat) ?>">
    <input type="hidden" id="longitude" name="longitude" value="<?= e((string) $f_lng) ?>">
  </div>

  <?php if ($is_fire): ?>
    <fieldset>
      <legend>Fire details</legend>
      <p>
        <label for="affected_structures">Affected houses or structures</label><br>
        <input id="affected_structures" name="affected_structures" type="number" min="0"
               <?= $closed_by_fire_out ? 'required' : '' ?>
               value="<?= e((string) $f_struct) ?>">
      </p>
      <p>
        <label for="alarm_level">Alarm level</label><br>
        <input id="alarm_level" name="alarm_level" type="text" maxlength="20"
               <?= $closed_by_fire_out ? 'required' : '' ?>
               value="<?= e((string) $f_alarm) ?>">
      </p>
    </fieldset>
  <?php elseif ($is_ambulance): ?>
    <fieldset>
      <legend>Ambulance details</legend>
      <p>
        <label for="destination">Destination (hospital or home address) *</label><br>
        <input id="destination" name="destination" type="text" maxlength="255" required
               value="<?= e((string) $f_dest) ?>">
      </p>
      <p>
        <label for="patient_condition">Patient condition *</label><br>
        <textarea id="patient_condition" name="patient_condition" rows="3" required><?= e((string) $f_cond) ?></textarea>
      </p>
    </fieldset>
  <?php endif; ?>

  <p>
    <label for="vehicle_id"><?= $strict ? 'Vehicle *' : 'Vehicle' ?></label><br>
    <select id="vehicle_id" name="vehicle_id" <?= $strict ? 'required' : '' ?>>
      <option value="">None</option>
      <?php foreach ($vehicles as $v): ?>
        <option value="<?= (int) $v['vehicle_id'] ?>"
          <?= (int) $f_vehicle === (int) $v['vehicle_id'] ? 'selected' : '' ?>>
          <?= e($v['unit_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </p>

  <p>
    <label for="driver_id"><?= $strict ? 'Driver *' : 'Driver' ?></label><br>
    <select id="driver_id" name="driver_id" <?= $strict ? 'required' : '' ?>>
      <option value="">None</option>
      <?php foreach ($people as $p): ?>
        <option value="<?= (int) $p['user_id'] ?>"
          <?= (int) $f_driver === (int) $p['user_id'] ? 'selected' : '' ?>>
          <?= e($p['first_name'] . ' ' . $p['last_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </p>

  <fieldset>
    <legend>Crew<?= $strict ? ' * (tick at least one)' : '' ?></legend>
    <?php foreach ($people as $p): ?>
      <label>
        <input type="checkbox" name="crew[]" value="<?= (int) $p['user_id'] ?>"
          <?= in_array((int) $p['user_id'], $f_crew, true) ? 'checked' : '' ?>>
        <?= e($p['first_name'] . ' ' . $p['last_name']) ?>
      </label><br>
    <?php endforeach; ?>
  </fieldset>

  <p>
    <label for="narrative"><?= $strict ? 'Narrative *' : 'Narrative' ?></label><br>
    <textarea id="narrative" name="narrative" rows="4" <?= $strict ? 'required' : '' ?>><?= e((string) $f_narr) ?></textarea>
  </p>

  <button type="submit">Save changes</button>
</form>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="<?= e(url('assets/js/report-map.js')) ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>