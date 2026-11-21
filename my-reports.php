<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();

// The month comes from the address bar, so check its shape before using it.
$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    $month = date('Y-m');
}

$reports     = get_my_reports((int) $user['user_id'], $month);
$month_label = (new DateTime($month . '-01'))->format('F Y');

$page_title = 'My reports';
require __DIR__ . '/includes/header.php';
?>
<h1>My reports</h1>

<form method="get">
  <label for="month">Month</label>
  <input id="month" name="month" type="month" value="<?= e($month) ?>">
  <button type="submit">Show</button>
</form>

<p><?= count($reports) ?> report(s) in <?= e($month_label) ?></p>

<?php if ($reports): ?>
  <table>
    <thead>
      <tr>
        <th>Date and time</th>
        <th>Type</th>
        <th>Address</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($reports as $r): ?>
        <tr>
          <td><?= e((new DateTime($r['incident_datetime']))->format('M j, g:i A')) ?></td>
          <td><?= e($r['type_name']) ?></td>
          <td><?= e($r['address']) ?></td>
          <td><?= e($r['status'] === 'ongoing' ? 'ONGOING' : 'COMPLETED') ?></td>
          <td><a href="<?= e(url('report.php?id=' . (int) $r['report_id'])) ?>">View</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>