<?php
require_once __DIR__ . '/includes/v2.php';
require_once __DIR__ . '/includes/upload.php';
$user = require_login();

$types    = get_incident_types();
$vehicles = get_vehicles();
$people   = get_active_users();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errors[] = 'The upload was too big. Try fewer or smaller photos.';
    } elseif (!csrf_ok()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        // 1. Read and clean what was typed
        $type     = find_incident_type((int) ($_POST['type_id'] ?? 0));
        $address  = trim($_POST['address'] ?? '');
        $landmark = trim($_POST['landmark'] ?? '');
        $number   = trim($_POST['incident_number'] ?? '');
        $narr     = trim($_POST['narrative'] ?? '');
        $dt       = DateTime::createFromFormat('Y-m-d\TH:i', $_POST['incident_datetime'] ?? '');
        $lat = ($_POST['latitude'] ?? '')  === '' ? null : filter_var($_POST['latitude'], FILTER_VALIDATE_FLOAT);
        $lng = ($_POST['longitude'] ?? '') === '' ? null : filter_var($_POST['longitude'], FILTER_VALIDATE_FLOAT);        

        $vehicle_id = ($_POST['vehicle_id'] ?? '') === '' ? null : (int) $_POST['vehicle_id'];
        $driver_id  = ($_POST['driver_id'] ?? '')  === '' ? null : (int) $_POST['driver_id'];
        $crew       = array_unique(array_map('intval', (array) ($_POST['crew'] ?? [])));

        $valid_vehicles = array_map('intval', array_column($vehicles, 'vehicle_id'));
        $valid_people   = array_map('intval', array_column($people, 'user_id'));

        // 2. Check it. Never trust the browser: someone can send anything.
        if (!$type) {
            $errors[] = 'Please choose an incident type.';
        }
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
        if (array_diff($crew, $valid_people)) {
            $errors[] = 'A crew member is not valid.';
        }
        // A report that is completed when saved cannot have blank fields.
        if ($type && (int) $type['needs_follow_up'] === 0) {
            if ($vehicle_id === null) {
                $errors[] = 'Please choose the vehicle.';
            }
            if ($driver_id === null) {
                $errors[] = 'Please choose the driver.';
            }
            if (!$crew) {
                $errors[] = 'Please tick at least one crew member.';
            }
            if ($narr === '') {
                $errors[] = 'Please write the narrative.';
            }
            if ($type['category'] === 'Ambulance') {
                if (trim($_POST['destination'] ?? '') === '') {
                    $errors[] = 'Please enter the destination.';
                }
                if (trim($_POST['patient_condition'] ?? '') === '') {
                    $errors[] = 'Please enter the patient condition.';
                }
            }
        }
        if ($lat === false || $lng === false || ($lat === null) !== ($lng === null)
            || ($lat !== null && ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180))) {
            $errors[] = 'The map pin is not valid. Please pin it again or clear it.';
        }
        [$photo_files, $photo_errors] = check_photos($_FILES['photos'] ?? null);
        $errors = array_merge($errors, $photo_errors);                        

        // 3. Save it
        if (!$errors) {
            $structures = filter_var($_POST['affected_structures'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            $id = create_report([
                'type_id'             => $type['type_id'],
                'category'            => $type['category'],
                'needs_follow_up'     => (int) $type['needs_follow_up'] === 1,
                'incident_number'     => $number ?: null,
                'incident_datetime'   => $dt->format('Y-m-d H:i:s'),
                'address'             => $address,
                'landmark'            => $landmark ?: null,
                'vehicle_id'          => $vehicle_id,
                'driver_id'           => $driver_id,
                'narrative'           => $narr ?: null,
                'affected_structures' => $structures === false ? null : $structures,
                'alarm_level'         => trim($_POST['alarm_level'] ?? '') ?: null,
                'destination'         => trim($_POST['destination'] ?? '') ?: null,
                'patient_condition'   => trim($_POST['patient_condition'] ?? '') ?: null,
                'crew'                => $crew,
                'latitude'            => $lat,
                'longitude'           => $lng,               
            ], (int) $user['user_id']);
            store_photos($id, (int) $user['user_id'], $photo_files);            
            v2_event((int)$user['user_id'], 'incident_created', 'New incident recorded', $address, (int)$id);
            redirect('report-new.php?saved=' . $id);
        }
    }
}

$page_title = 'New report';
require __DIR__ . '/includes/header.php';
?>
<?php if (!empty($_GET['saved'])): ?>
  <p role="status">Report #<?= (int) $_GET['saved'] ?> saved.</p>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
  <p role="alert"><?= e($err) ?></p>
<?php endforeach; ?>


<h1>New report</h1>
<p>Only the incident type and the address are required.</p>

<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>

  <p>
    <label for="type_id">Incident type *</label><br>
    <select id="type_id" name="type_id" required>
      <option value="<?= (int) $t['type_id'] ?>" data-category="<?= e($t['category']) ?>"
                data-followup="<?= (int) $t['needs_follow_up'] ?>">
      <?php $lastCat = null; foreach ($types as $t):
        if ($t['category'] !== $lastCat) {
            if ($lastCat !== null) echo '</optgroup>';
            echo '<optgroup label="' . e($t['category']) . '">';
            $lastCat = $t['category'];
        } ?>
        <option value="<?= (int) $t['type_id'] ?>" data-category="<?= e($t['category']) ?>">
          <?= e($t['name']) ?>
        </option>
      <?php endforeach; if ($lastCat !== null) echo '</optgroup>'; ?>
    </select>
  </p>

  <p>
    <label for="incident_datetime">Date and time</label><br>
    <input id="incident_datetime" name="incident_datetime" type="datetime-local"
           value="<?= e(date('Y-m-d\TH:i')) ?>">
  </p>

  <p>
    <label for="incident_number">Incident number (if any)</label><br>
    <input id="incident_number" name="incident_number" type="text" maxlength="30">
  </p>

  <p>
    <label for="address">Address *</label><br>
    <input id="address" name="address" type="text" maxlength="255" required>
  </p>

  <p>
    <label for="landmark">Landmark</label><br>
    <input id="landmark" name="landmark" type="text" maxlength="150">
  </p>

  <div>
    <p>Pin the location on the map (optional)</p>
    <div id="map" style="height: 300px"
         data-lat="<?= e((string) (config('map_lat') ?? 14.5786)) ?>"
         data-lng="<?= e((string) (config('map_lng') ?? 121.1222)) ?>"></div>
    <p>
      <button type="button" id="use-location">Use my location</button>
      <button type="button" id="clear-pin">Clear pin</button>
    </p>
    <p id="pin-status" role="status">No pin yet. Tap the map, or use "Use my location".</p>
    <input type="hidden" id="latitude" name="latitude">
    <input type="hidden" id="longitude" name="longitude">
  </div>

  <fieldset id="fire-fields" hidden>
    <legend>Fire details</legend>
    <p>
      <label for="affected_structures">Affected houses or structures</label><br>
      <input id="affected_structures" name="affected_structures" type="number" min="0">
    </p>
    <p>
      <label for="alarm_level">Alarm level</label><br>
      <input id="alarm_level" name="alarm_level" type="text" maxlength="20" placeholder="e.g. 1st alarm">
    </p>
  </fieldset>

  <fieldset id="ambulance-fields" hidden>
    <legend>Ambulance details (required, together with vehicle, driver, crew and narrative)</legend>
    <p>
      <label for="destination">Destination (hospital or home address)</label><br>
      <input id="destination" name="destination" type="text" maxlength="255">
    </p>
    <p>
      <label for="patient_condition">Patient condition</label><br>
      <textarea id="patient_condition" name="patient_condition" rows="3"></textarea>
    </p>
  </fieldset>

  <p>
    <label for="vehicle_id">Vehicle</label><br>
    <select id="vehicle_id" name="vehicle_id">
      <option value="">None</option>
      <?php foreach ($vehicles as $v): ?>
        <option value="<?= (int) $v['vehicle_id'] ?>"><?= e($v['unit_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </p>

  <p>
    <label for="driver_id">Driver</label><br>
    <select id="driver_id" name="driver_id">
      <option value="">None</option>
      <?php foreach ($people as $p): ?>
        <option value="<?= (int) $p['user_id'] ?>">
          <?= e($p['first_name'] . ' ' . $p['last_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </p>

  <fieldset>
    <legend>Crew</legend>
    <?php foreach ($people as $p): ?>
      <label>
        <input type="checkbox" name="crew[]" value="<?= (int) $p['user_id'] ?>">
        <?= e($p['first_name'] . ' ' . $p['last_name']) ?>
      </label><br>
    <?php endforeach; ?>
  </fieldset>

  <p>
    <label for="narrative">Narrative</label><br>
    <textarea id="narrative" name="narrative" rows="4"></textarea>
  </p>

  <p>
    <label for="photos">Photos (up to 4: JPG, PNG or WebP)</label><br>
    <input id="photos" name="photos[]" type="file"
           accept="image/jpeg,image/png,image/webp" multiple>
  </p>
  <p id="photo-info" role="status"></p>

  <button type="submit">Save report</button>
</form>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="<?= e(url('assets/js/report-map.js')) ?>"></script>
<script src="<?= e(url('assets/js/report-form.js')) ?>"></script>
<script src="<?= e(url('assets/js/report-photos.js')) ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>