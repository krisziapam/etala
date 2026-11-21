<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();

$reports = get_reports_today();
$updates = get_follow_ups_for(array_column($reports, 'report_id'));
$images  = get_images_for(array_column($reports, 'report_id'));

$page_title = 'Today';
require __DIR__ . '/includes/header.php';
?>
<h1>Today</h1>

<?php if (!$reports): ?>
  <p>No reports yet today.</p>
<?php endif; ?>

<?php foreach ($reports as $r):
    $when  = new DateTime($r['incident_datetime']);
    $is_today = $when->format('Y-m-d') === date('Y-m-d');
    $rows  = $updates[(int) $r['report_id']] ?? [];
    $pics  = $images[(int) $r['report_id']] ?? [];
?>
  <article>
    <h2><a href="<?= e(url('report.php?id=' . (int) $r['report_id'])) ?>"><?= e($r['type_name']) ?></a></h2>
    <p>
      <strong><?= e($r['status'] === 'ongoing' ? 'ONGOING' : 'COMPLETED') ?></strong>
      &middot; <?= e($when->format($is_today ? 'g:i A' : 'M j, g:i A')) ?>
    </p>
    <p><?= e($r['address']) ?></p>
    <p>Filed by <?= e($r['first_name'] . ' ' . $r['last_name']) ?></p>

    <?php if ($pics): ?>
      <p>
        <?php foreach ($pics as $img): ?>
          <a href="<?= e(url('photo.php?id=' . (int) $img['image_id'])) ?>" target="_blank" rel="noopener">
            <img src="<?= e(url('photo.php?id=' . (int) $img['image_id'])) ?>"
                 alt="Photo of the incident" width="120" loading="lazy">
          </a>
        <?php endforeach; ?>
      </p>
    <?php endif; ?>

    <?php foreach ($rows as $f): ?>
      <p>
        &#8627; <strong><?= e(ucfirst($f['update_type'])) ?></strong>
        &middot; <?= e((new DateTime($f['update_datetime']))->format('g:i A')) ?>
        &middot; <?= e($f['first_name'] . ' ' . $f['last_name']) ?>
        <?php if ($f['details']): ?>
          <br><?= nl2br(e($f['details'])) ?>
        <?php endif; ?>
      </p>
    <?php endforeach; ?>
  </article>
<?php endforeach; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>