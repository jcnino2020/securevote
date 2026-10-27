<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/community.php';
require_once __DIR__ . '/../includes/voter-import.php';
require_admin(); $pdo = db(); $errors = []; $electionId = int_input('election_id');
header('Cache-Control: no-store');
if (input('template') === 'csv' && !is_post()) {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="voter-import-template.csv"');
    echo "username,email,first_name,last_name\n"; exit;
}
if (is_post()) {
    require_csrf(); $action = input('action', 'preview');
    if ($action === 'cancel') { unset($_SESSION['_voter_import']); redirect('admin/import-voters.php'); }
    if ($action === 'preview') {
        unset($_SESSION['_voter_import']);
        $file = $_FILES['roster'] ?? [];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) $errors[] = sv_t('Choose a CSV file to upload (maximum 1 MB).');
        elseif (($file['size'] ?? 0) > 1048576 || filesize($file['tmp_name']) > 1048576) $errors[] = sv_t('Keep the CSV file under 1 MB.');
        $stmt = $pdo->prepare("SELECT id FROM elections WHERE id = ? AND status IN ('draft','scheduled','open') AND end_datetime > NOW()"); $stmt->execute([$electionId]);
        if (!$stmt->fetchColumn()) $errors[] = sv_t('Choose an upcoming or currently open election.');
        if (!$errors) {
            $stream = fopen($file['tmp_name'], 'rb'); $parsed = sv_parse_voter_csv($stream); fclose($stream);
            $checked = sv_check_import_rows($pdo, $parsed['rows']); $errors = array_merge($parsed['errors'], $checked['errors']);
            if (!$errors) $_SESSION['_voter_import'] = ['rows' => $checked['rows'], 'election_id' => $electionId, 'expires' => time() + 900, 'nonce' => bin2hex(random_bytes(16))];
        }
    } elseif ($action === 'confirm') {
        $preview = $_SESSION['_voter_import'] ?? null;
        if (!$preview || $preview['expires'] < time() || !hash_equals($preview['nonce'], input('nonce', ''))) {
            unset($_SESSION['_voter_import']); $errors[] = sv_t('The preview expired or was replaced. Upload the CSV again.');
        } else {
            try {
                $counts = sv_commit_voter_import($pdo, $preview['rows'], (int) $preview['election_id']);
                unset($_SESSION['_voter_import']);
                audit_log('voters_imported', 'CSV import: ' . $counts['created'] . ' new, ' . $counts['existing'] . ' existing voters assigned to election #' . $preview['election_id'] . '.');
                flash_success(sprintf(sv_t('%d voters created; %d existing voters retained. All assigned to the selected election. Distribute new voter codes privately from the registry.'), $counts['created'], $counts['existing']));
                redirect('admin/voters.php');
            } catch (RuntimeException $e) {
                if ($e instanceof PDOException) { error_log('CSV import failed: ' . $e->getMessage()); $errors[] = sv_t('An account changed or the import could not be saved. No rows were imported. Preview the file again.'); }
                else $errors[] = $e->getMessage();
                unset($_SESSION['_voter_import']);
            }
        }
    }
}
$preview = $_SESSION['_voter_import'] ?? null;
if ($preview && $preview['expires'] < time()) { unset($_SESSION['_voter_import']); $preview = null; }
$elections = $pdo->query("SELECT id, title FROM elections WHERE status IN ('draft','scheduled','open') AND end_datetime > NOW() ORDER BY start_datetime DESC")->fetchAll();
$pageTitle = sv_t('Import voters'); $activeNav = 'import-voters.php'; require __DIR__ . '/../includes/header.php';
?>
<div class="feature-page stack"><div class="feature-intro"><h1><?= e(sv_t("Bring your student roster")) ?></h1><p><?= e(sv_t("Upload, review, then confirm. Up to 500 voters per UTF-8 CSV, maximum 1 MB. No changes are saved during preview.")) ?></p></div><?php sv_feature_errors($errors); ?>
<?php if ($preview): ?>
<h2><?= e(sprintf(sv_t('Review %d voters before importing'), count($preview['rows']))) ?></h2><p><?= e(sprintf(sv_t('New accounts receive generated voter codes. Existing active voters are matched by both username and email and keep their profile, credentials, and voting history. All rows will be assigned to election #%d. This preview expires after 15 minutes.'), (int) $preview['election_id'])) ?></p>
<div class="comparison-scroll" role="region" tabindex="0" aria-label="<?= e(sv_t('Roster preview')) ?>"><table class="comparison-table"><caption><?= e(sv_t("Voter import preview")) ?></caption><thead><tr><th><?= e(sv_t("Row")) ?></th><th><?= e(sv_t("Username")) ?></th><th><?= e(sv_t("Email")) ?></th><th><?= e(sv_t("Name from CSV")) ?></th><th><?= e(sv_t("Action")) ?></th></tr></thead><tbody><?php foreach ($preview['rows'] as $row): ?><tr><td><?= (int) $row['line'] ?></td><td><?= e($row['username']) ?></td><td><?= e($row['email']) ?></td><td><?= e($row['first_name'] . ' ' . $row['last_name']) ?></td><td><?= $row['existing_id'] ? e(sv_t('Keep existing account; assign eligibility')) : e(sv_t('Create account; assign eligibility')) ?></td></tr><?php endforeach; ?></tbody></table></div>
<form method="post" class="row" action="<?= e(post_url('admin/import-voters.php')) ?>"><?= csrf_field() ?><input type="hidden" name="nonce" value="<?= e($preview['nonce']) ?>"><button class="btn btn--primary" name="action" value="confirm"><?= e(sv_t("Confirm voter import")) ?></button><button class="btn btn--secondary" name="action" value="cancel"><?= e(sv_t("Discard preview")) ?></button></form>
<?php else: ?><form method="post" enctype="multipart/form-data" class="feature-form stack" action="<?= e(post_url('admin/import-voters.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="preview"><label for="election"><?= e(sv_t("Assign to election")) ?></label><select class="input" name="election_id" id="election" required><option value=""><?= e(sv_t("Choose an election")) ?></option><?php foreach ($elections as $election): ?><option value="<?= (int) $election['id'] ?>" <?= $electionId === (int) $election['id'] ? 'selected' : '' ?>><?= e($election['title']) ?></option><?php endforeach; ?></select><label for="roster"><?= e(sv_t("Student roster CSV")) ?></label><input class="input" type="file" id="roster" name="roster" accept=".csv,text/csv" required><p class="text-muted text-sm"><?= e(sv_t("Exactly four columns: username, email, first_name, last_name. Account conflicts or duplicate rows block the entire import. Password columns are not accepted.")) ?></p><div class="row"><button class="btn btn--primary" <?= !$elections ? 'disabled' : '' ?>><?= e(sv_t("Preview voters")) ?></button><a class="link" href="<?= e(url('admin/import-voters.php?template=csv')) ?>"><?= e(sv_t("Download CSV template")) ?></a></div></form><?php if (!$elections): ?><p><?= e(sv_t("Create an upcoming election before importing voters.")) ?></p><?php endif; ?><?php endif; ?></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
