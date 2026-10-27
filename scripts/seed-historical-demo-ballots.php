<?php
/**
 * Seed 200 unmistakably fictional ballots across the 14 empty, closed sample
 * elections (IDs 3–16). Run from the app root: php scripts/seed-historical-demo-ballots.php
 * Add --apply to write; add --rollback to remove only this batch.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/config/database.php';

$mode = $argv[1] ?? '--dry-run';
if (!in_array($mode, ['--dry-run', '--apply', '--rollback'], true) || count($argv) > 2) {
    fwrite(STDERR, "Usage: php scripts/seed-historical-demo-ballots.php [--dry-run|--apply|--rollback]\n");
    exit(2);
}

$pdo = db();
$batch = 'historical-demo-200-20261027';
$usernamePrefix = 'demo_hist_20261027_';
$referencePrefix = 'SV-DEMO-20261027-';
$marker = ' [Demo results] Simulated ballots for testing; not an official election outcome.';
$ids = range(3, 16);
$lockName = 'securevote-historical-demo-200-20261027';
$lock = $pdo->prepare('SELECT GET_LOCK(?, 10)');
$lock->execute([$lockName]);
if ((int) $lock->fetchColumn() !== 1) {
    throw new RuntimeException('Another historical demo ballot operation is running.');
}

try {
    $existing = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username LIKE ?');
    $existing->execute([$usernamePrefix . '%']);
    $existingUsers = (int) $existing->fetchColumn();
    $existingBallots = $pdo->prepare('SELECT COUNT(*) FROM ballots WHERE ballot_reference LIKE ?');
    $existingBallots->execute([$referencePrefix . '%']);
    $existingBallotCount = (int) $existingBallots->fetchColumn();

    if ($mode === '--rollback') {
        if ($existingUsers !== 200 || $existingBallotCount !== 200) {
            throw new RuntimeException('Batch counts changed; refusing automatic rollback.');
        }
        $pdo->beginTransaction();
        $pdo->prepare('DELETE v FROM votes v JOIN ballots b ON b.id = v.ballot_id WHERE b.ballot_reference LIKE ?')
            ->execute([$referencePrefix . '%']);
        $pdo->prepare('DELETE ve FROM voter_eligibility ve JOIN users u ON u.id = ve.user_id WHERE u.username LIKE ?')
            ->execute([$usernamePrefix . '%']);
        $pdo->prepare('DELETE FROM ballots WHERE ballot_reference LIKE ?')->execute([$referencePrefix . '%']);
        $pdo->prepare('DELETE FROM users WHERE username LIKE ?')->execute([$usernamePrefix . '%']);
        $update = $pdo->prepare('UPDATE elections SET description = REPLACE(description, ?, "") WHERE id = ? AND description LIKE ?');
        foreach ($ids as $id) {
            $update->execute([$marker, $id, '%[Demo results]%']);
        }
        $pdo->prepare('INSERT INTO audit_logs (action,description) VALUES (?,?)')
            ->execute(['demo_ballots_removed', $batch . ': removed 200 simulated ballots and voter accounts.']);
        $pdo->commit();
        echo "Removed 200 demo ballots and 200 demo voter accounts.\n";
        exit;
    }

    if ($existingUsers !== 0 || $existingBallotCount !== 0) {
        throw new RuntimeException('This batch already exists or is incomplete; refusing to add duplicates.');
    }

    $elections = $pdo->query(
        'SELECT id,title,description,start_datetime,end_datetime,status FROM elections '
        . 'WHERE id BETWEEN 3 AND 16 ORDER BY id'
    )->fetchAll();
    if (count($elections) !== 14) {
        throw new RuntimeException('Expected exactly 14 historical sample elections.');
    }

    $ballotCount = $pdo->prepare('SELECT COUNT(*) FROM ballots WHERE election_id = ?');
    $positions = $pdo->prepare('SELECT id FROM positions WHERE election_id = ? ORDER BY display_order,id');
    $candidates = $pdo->prepare("SELECT id FROM candidates WHERE election_id = ? AND position_id = ? AND status = 'active' ORDER BY id");
    $choices = [];
    $allocation = [];
    foreach ($elections as $index => $election) {
        $id = (int) $election['id'];
        if ($id !== $ids[$index] || !in_array($election['status'], ['closed', 'archived'], true)
            || strtotime($election['end_datetime']) >= time()
            || str_contains((string) $election['description'], '[Demo results]')) {
            throw new RuntimeException("Election #$id is not an unmarked, completed sample election.");
        }
        $ballotCount->execute([$id]);
        if ((int) $ballotCount->fetchColumn() !== 0) {
            throw new RuntimeException("Election #$id already has ballots; refusing to mix demo and existing results.");
        }
        $positions->execute([$id]);
        $positionIds = $positions->fetchAll(PDO::FETCH_COLUMN);
        if (count($positionIds) !== 5) {
            throw new RuntimeException("Election #$id does not have the expected five positions.");
        }
        foreach ($positionIds as $positionId) {
            $candidates->execute([$id, $positionId]);
            $candidateIds = array_map('intval', $candidates->fetchAll(PDO::FETCH_COLUMN));
            if (count($candidateIds) < 2) {
                throw new RuntimeException("Position #$positionId has too few active candidates.");
            }
            $choices[$id][(int) $positionId] = $candidateIds;
        }
        $allocation[$id] = 14 + ($index < 4 ? 1 : 0);
    }

    if ($mode === '--dry-run') {
        echo json_encode(['batch' => $batch, 'fictional_voters' => 200, 'ballots' => 200,
            'candidate_selections' => 1000, 'ballots_per_election' => $allocation], JSON_PRETTY_PRINT) . "\n";
        exit;
    }

    $pdo->beginTransaction();
    $insertUser = $pdo->prepare(
        "INSERT INTO users (username,email,voter_code,password_hash,first_name,last_name,role,status) "
        . "VALUES (?,?,?,NULL,'Demo',?,'voter','inactive')"
    );
    $insertBallot = $pdo->prepare(
        "INSERT INTO ballots (election_id,voter_id,ballot_reference,submitted_at,status) VALUES (?,?,?,?,'submitted')"
    );
    $insertVote = $pdo->prepare(
        'INSERT INTO votes (ballot_id,position_id,candidate_id,created_at) VALUES (?,?,?,?)'
    );
    $insertEligibility = $pdo->prepare(
        'INSERT INTO voter_eligibility (election_id,user_id,has_voted,voted_at,ballot_id) VALUES (?,?,1,?,?)'
    );
    $markElection = $pdo->prepare('UPDATE elections SET description = CONCAT(description, ?) WHERE id = ?');
    $serial = 0;
    foreach ($elections as $election) {
        $id = (int) $election['id'];
        $start = strtotime($election['start_datetime']);
        $end = strtotime($election['end_datetime']);
        foreach (range(1, $allocation[$id]) as $ordinal) {
            ++$serial;
            $suffix = sprintf('%04d', $serial);
            $insertUser->execute([
                $usernamePrefix . $suffix,
                $usernamePrefix . $suffix . '@example.test',
                'VC-DEMO-20261027-' . $suffix,
                'Voter ' . $suffix,
            ]);
            $userId = (int) $pdo->lastInsertId();
            // Demo timestamps sit within each election's recorded voting window.
            $fraction = $ordinal / ($allocation[$id] + 1);
            $submittedAt = date('Y-m-d H:i:s', $start + (int) (($end - $start) * $fraction));
            $insertBallot->execute([$id, $userId, $referencePrefix . $suffix, $submittedAt]);
            $ballotId = (int) $pdo->lastInsertId();
            foreach ($choices[$id] as $positionId => $candidateIds) {
                $hash = hash('sha256', "$batch:$id:$serial:$positionId");
                $candidateId = $candidateIds[hexdec(substr($hash, 0, 7)) % count($candidateIds)];
                $insertVote->execute([$ballotId, $positionId, $candidateId, $submittedAt]);
            }
            $insertEligibility->execute([$id, $userId, $submittedAt, $ballotId]);
        }
        $markElection->execute([$marker, $id]);
    }
    if ($serial !== 200) {
        throw new RuntimeException("Unexpected ballot count: $serial");
    }
    $pdo->prepare('INSERT INTO audit_logs (action,description) VALUES (?,?)')
        ->execute(['demo_ballots_seeded', $batch . ': 200 fictional inactive voters, 200 ballots, 1000 selections across elections 3-16.']);
    $pdo->commit();
    echo "Added 200 demo voters, 200 ballots, and 1000 candidate selections across 14 past elections.\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
} finally {
    $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
    $release->execute([$lockName]);
}
