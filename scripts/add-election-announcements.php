<?php
/** Run from the app root via CLI. Preview by default; explicitly enable writes. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require getcwd() . '/includes/bootstrap.php';
require_once getcwd() . '/includes/community.php';
$pdo = sv_community_db();
$batch = 'election-announcements-30-20261016';
$lock = $pdo->query("SELECT GET_LOCK('securevote-announcements-30-20261016', 10)")->fetchColumn();
if ((int) $lock !== 1) throw new RuntimeException('Another announcement batch is running.');
try {
    $exists = $pdo->prepare("SELECT description FROM audit_logs WHERE action = 'announcements_seeded' AND description LIKE ? LIMIT 1");
    $exists->execute([$batch . '%']);
    if ($exists->fetchColumn()) { echo "This 30-announcement batch has already been published. No duplicates added.\n"; exit; }
    $elections = $pdo->query("SELECT * FROM elections WHERE status IN ('scheduled','open','closed','archived') ORDER BY start_datetime, id")->fetchAll();
    if (count($elections) < 30) throw new RuntimeException('At least 30 published elections are required for this batch.');
    $tips = [
        ['Voting dates and preparation', 'Before voting, review the positions and candidate information on the Elections page. You can compare active candidates by position after signing in.'],
        ['Get to know the candidates', 'Read the candidate biographies and platforms, then use Compare candidates to review their priorities side by side. Comparing candidates does not select anyone on your ballot.'],
        ['Check your voter access', 'Your account must be active and registered for this election before a real ballot can be submitted. If access is refused, report an eligibility issue through Help so the election administrator can check your registration.'],
        ['Practice before casting your ballot', 'The Practice ballot page lets you try selecting candidates, reviewing choices, and abstaining with fictional candidates. Practice does not record a real vote or change your eligibility.'],
        ['Review every choice before submitting', 'Follow the selection limit shown for each position. You may leave a position unselected to abstain. Review your ballot carefully: a submitted real ballot is final and cannot be changed.'],
        ['Receipts, results, and support', 'After a successful submission, save your receipt privately. Verify receipt checks whether a submitted ballot with that code is recorded; it does not reveal selections or independently prove the tally. Results availability follows this election’s configured policy. Use Help for problems without sharing credentials or ballot choices.'],
    ];
    $items = [];
    for ($i = 0; $i < 30; $i++) {
        $election = $elections[(int) round($i * (count($elections) - 1) / 29)];
        [$heading, $tip] = $tips[$i % count($tips)];
        $status = election_effective_status($election);
        $state = match ($status) {
            'open' => 'The voting window is currently open. Eligible registered voters can submit a ballot before the closing time.',
            'scheduled' => 'Voting is scheduled for the window below. Review the election details before voting begins.',
            default => 'This election is no longer accepting ballots. You can review its information and check the Results page for availability.',
        };
        $items[] = [
            'election_id' => (int) $election['id'],
            'title' => mb_substr($heading . ' — ' . $election['title'], 0, 150),
            'body' => $election['title'] . "\n\n" . $state . "\n\nRecorded voting window (Asia/Manila):\n" . fmt_datetime($election['start_datetime']) . ' to ' . fmt_datetime($election['end_datetime']) . ".\n\n" . $tip . "\n\nAlways check the election page for the current schedule and status.",
        ];
    }
    if (getenv('SV_SEED_ANNOUNCEMENTS') !== '1') {
        echo json_encode(['count' => count($items), 'distinct_elections' => count(array_unique(array_column($items, 'election_id'))), 'preview' => $items], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        exit;
    }
    $pdo->beginTransaction();
    $author = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id LIMIT 1")->fetchColumn();
    $stmt = $pdo->prepare("INSERT INTO announcements (election_id,title,body,status,created_by) VALUES (?,?,?,'published',?)");
    $ids = [];
    foreach ($items as $item) {
        $stmt->execute([$item['election_id'], $item['title'], $item['body'], $author ?: null]);
        $ids[] = (int) $pdo->lastInsertId();
    }
    $audit = $pdo->prepare("INSERT INTO audit_logs (user_id,action,description,created_at) VALUES (?,'announcements_seeded',?,NOW())");
    $audit->execute([$author ?: null, $batch . ': published 30 announcements across 30 elections; IDs ' . min($ids) . '-' . max($ids) . '.']);
    $pdo->commit();
    echo json_encode(['published' => count($ids), 'distinct_elections' => count(array_unique(array_column($items, 'election_id'))), 'announcement_ids' => $ids]) . "\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $e->getMessage() . "\n"); exit(1);
} finally {
    $pdo->query("SELECT RELEASE_LOCK('securevote-announcements-30-20261016')");
}
