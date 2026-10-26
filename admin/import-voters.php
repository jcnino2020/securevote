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
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) $errors[] = 'Choose a CSV file to upload (maximum 1 MB).';
        elseif (($file['size'] ?? 0) > 1048576 || filesize($file['tmp_name']) > 1048576) $errors[] = 'Keep the CSV file under 1 MB.';
        $stmt = $pdo->prepare("SELECT id FROM elections WHERE id = ? AND status IN ('draft','scheduled','open') AND end_datetime > NOW()"); $stmt->execute([$electionId]);
        if (!$stmt->fetchColumn()) $errors[] = 'Choose an upcoming or currently open election.';
        if (!$errors) {
            $stream = fopen($file['tmp_name'], 'rb'); $parsed = sv_parse_voter_csv($stream); fclose($stream);
            $checked = sv_check_import_rows($pdo, $parsed['rows']); $errors = array_merge($parsed['errors'], $checked['errors']);
            if (!$errors) $_SESSION['_voter_import'] = ['rows' => $checked['rows'], 'election_id' => $electionId, 'expires' => time() + 900, 'nonce' => bin2hex(random_bytes(16))];
        }
    } elseif ($action === 'confirm') {
        $preview = $_SESSION['_voter_import'] ?? null;
        if (!$preview || $preview['expires'] < time() || !hash_equals($preview['nonce'], input('nonce', ''))) {
            unset($_SESSION['_voter_import']); $errors[] = 'The preview expired or was replaced. Upload the CSV again.';
        } else {
            try {
                $counts = sv_commit_voter_import($pdo, $preview['rows'], (int) $preview['election_id']);
                unset($_SESSION['_voter_import']);
                audit_log('voters_imported', 'CSV import: ' . $counts['created'] . ' new, ' . $counts['existing'] . ' existing voters assigned to election #' . $preview['election_id'] . '.');
                flash_success($counts['created'] . ' voters created; ' . $counts['existing'] . ' existing voters retained. All assigned to the selected election. Distribute new voter codes privately from the registry.');
                redirect('admin/voters.php');
            } catch (RuntimeException $e) {
                if ($e instanceof PDOException) { error_log('CSV import failed: ' . $e->getMessage()); $errors[] = 'An account changed or the import could not be saved. No rows were imported. Preview the file again.'; }
                else $errors[] = $e->getMessage();
                unset($_SESSION['_voter_import']);
            }
        }
    }
}
$preview = $_SESSION['_voter_import'] ?? null;
if ($preview && $preview['expires'] < time()) { unset($_SESSION['_voter_import']); $preview = null; }
$elections = $pdo->query("SELECT id, title FROM elections WHERE status IN ('draft','scheduled','open') AND end_datetime > NOW() ORDER BY start_datetime DESC")->fetchAll();
$pageTitle = 'Import voters'; $activeNav = 'import-voters.php'; require __DIR__ . '/../includes/header.php';
?>
<div class="feature-page stack"><div class="feature-intro"><h1>Bring your student roster</h1><p>Upload, review, then confirm. Up to 500 voters per UTF-8 CSV, maximum 1 MB. No changes are saved during preview.</p></div><?php sv_feature_errors($errors); ?>
<?php if ($preview): ?>
<h2>Review <?= count($preview['rows']) ?> voters before importing</h2><p>New accounts receive generated voter codes. Existing active voters are matched by both username and email and keep their profile, credentials, and voting history. All rows will be assigned to election #<?= (int) $preview['election_id'] ?>. This preview expires after 15 minutes.</p>
<div class="comparison-scroll" role="region" tabindex="0" aria-label="Roster preview"><table class="comparison-table"><caption>Voter import preview</caption><thead><tr><th>Row</th><th>Username</th><th>Email</th><th>Name from CSV</th><th>Action</th></tr></thead><tbody><?php foreach ($preview['rows'] as $row): ?><tr><td><?= (int) $row['line'] ?></td><td><?= e($row['username']) ?></td><td><?= e($row['email']) ?></td><td><?= e($row['first_name'] . ' ' . $row['last_name']) ?></td><td><?= $row['existing_id'] ? 'Keep existing account; assign eligibility' : 'Create account; assign eligibility' ?></td></tr><?php endforeach; ?></tbody></table></div>
<form method="post" class="row" action="<?= e(post_url('admin/import-voters.php')) ?>"><?= csrf_field() ?><input type="hidden" name="nonce" value="<?= e($preview['nonce']) ?>"><button class="btn btn--primary" name="action" value="confirm">Confirm voter import</button><button class="btn btn--secondary" name="action" value="cancel">Discard preview</button></form>
<?php else: ?><form method="post" enctype="multipart/form-data" class="feature-form stack" action="<?= e(post_url('admin/import-voters.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="preview"><label for="election">Assign to election</label><select class="input" name="election_id" id="election" required><option value="">Choose an election</option><?php foreach ($elections as $election): ?><option value="<?= (int) $election['id'] ?>" <?= $electionId === (int) $election['id'] ? 'selected' : '' ?>><?= e($election['title']) ?></option><?php endforeach; ?></select><label for="roster">Student roster CSV</label><input class="input" type="file" id="roster" name="roster" accept=".csv,text/csv" required><p class="text-muted text-sm">Exactly four columns: username, email, first_name, last_name. Account conflicts or duplicate rows block the entire import. Password columns are not accepted.</p><div class="row"><button class="btn btn--primary" <?= !$elections ? 'disabled' : '' ?>>Preview voters</button><a class="link" href="<?= e(url('admin/import-voters.php?template=csv')) ?>">Download CSV template</a></div></form><?php if (!$elections): ?><p>Create an upcoming election before importing voters.</p><?php endif; ?><?php endif; ?></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
