<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * includes/voting.php
 *
 * The voting engine. This is the file that decides whether a ballot is
 * accepted, so it is written defensively and it re-checks everything the
 * browser could have tampered with:
 *
 *   - the ballot shown to the voter (only active candidates, only this
 *     election, in the administrator's order)
 *   - the selections coming back from the form (must be candidate ids
 *     that exist in this position, in this election, and are active)
 *   - the number of selections per position
 *   - the election window and status, checked again at submission time
 *   - the voter's registration and whether they already voted
 *
 * The final write happens inside a single transaction with row locks, and
 * the unique constraints on ballots and voter_eligibility are the last
 * line of defence if two requests somehow arrive at the same moment.
 *
 * The client is never trusted: not the hidden fields, not the JavaScript,
 * not the URL parameters, not a cookie.
 */

require_once __DIR__ . '/functions.php';

/* =====================================================================
 | 1. Building the ballot
 * =================================================================== */

/**
 * The ballot structure for an election.
 *
 * Only positions of that election and only *active* candidates are
 * returned, so a disqualified candidate disappears from the ballot the
 * moment an administrator changes the status.
 *
 * @return array<int, array{position: array, candidates: array<int, array>}>
 */
function sv_build_ballot(int $electionId): array
{
    $pdo = db();

    $positionStmt = $pdo->prepare(
        'SELECT id, election_id, name, description, max_selections, display_order
           FROM positions
          WHERE election_id = :election_id
          ORDER BY display_order ASC, name ASC'
    );
    $positionStmt->execute([':election_id' => $electionId]);
    $positions = $positionStmt->fetchAll();

    if ($positions === []) {
        return [];
    }

    $candidateStmt = $pdo->prepare(
        "SELECT id, position_id, first_name, last_name, party, biography, platform, photo
           FROM candidates
          WHERE election_id = :election_id
            AND status = 'active'
          ORDER BY last_name ASC, first_name ASC"
    );
    $candidateStmt->execute([':election_id' => $electionId]);

    $byPosition = [];
    foreach ($candidateStmt->fetchAll() as $candidate) {
        $byPosition[(int) $candidate['position_id']][] = $candidate;
    }

    $ballot = [];
    foreach ($positions as $position) {
        $ballot[] = [
            'position'   => $position,
            'candidates' => $byPosition[(int) $position['id']] ?? [],
        ];
    }

    return $ballot;
}

/* =====================================================================
 | 2. Validating what came back from the form
 * =================================================================== */

/**
 * Turn raw form input into the plan the server is willing to store.
 *
 * Anything that is not a candidate of that position in that election is
 * rejected outright rather than ignored: a tampered submission should be
 * reported, not quietly trimmed.
 *
 * @param array $ballot    result of sv_build_ballot()
 * @param mixed $submitted raw $_POST['votes']
 * @return array{ok: bool, plan: array<int, array<int,int>>, errors: array<int,string>}
 */
function sv_validate_submission(array $ballot, mixed $submitted): array
{
    if (!is_array($submitted)) {
        $submitted = [];
    }

    $plan   = [];
    $errors = [];

    foreach ($ballot as $entry) {
        $position = $entry['position'];
        $positionId = (int) $position['id'];
        $max        = max(1, (int) $position['max_selections']);

        // The candidates this position is actually allowed to offer.
        $allowed = [];
        foreach ($entry['candidates'] as $candidate) {
            $allowed[(int) $candidate['id']] = true;
        }

        $raw = $submitted[$positionId] ?? [];
        if (!is_array($raw)) {
            $raw = [$raw];
        }

        $chosen = [];
        foreach ($raw as $value) {
            if (!is_scalar($value)) {
                $errors[] = 'A selection sent for ' . $position['name'] . ' was not a valid value.';
                continue;
            }

            $candidateId = (int) $value;
            if ($candidateId <= 0) {
                continue;
            }

            if (!isset($allowed[$candidateId])) {
                $errors[] = 'A selected candidate for ' . $position['name']
                    . ' is not standing in this election, or is no longer active.';
                continue;
            }

            $chosen[$candidateId] = true;
        }

        if (count($chosen) > $max) {
            $errors[] = 'You selected ' . count($chosen) . ' candidates for ' . $position['name']
                . ', but only ' . $max . ' ' . ($max === 1 ? 'selection is' : 'selections are') . ' allowed.';
        }

        $plan[$positionId] = array_keys($chosen);
    }

    return [
        'ok'     => $errors === [],
        'plan'   => $plan,
        'errors' => $errors,
    ];
}

/**
 * Describe a stored plan for the review page: names, parties and photos,
 * read back from the database rather than from the submitted form.
 *
 * @param array<int, array<int,int>> $plan position id => candidate ids
 * @return array<int, array{position: array, candidates: array<int, array>}>
 */
function sv_describe_plan(int $electionId, array $plan): array
{
    $pdo = db();

    $positionStmt = $pdo->prepare(
        'SELECT id, name, description, max_selections, display_order
           FROM positions
          WHERE election_id = :election_id
          ORDER BY display_order ASC, name ASC'
    );
    $positionStmt->execute([':election_id' => $electionId]);
    $positions = $positionStmt->fetchAll();

    $describe = [];

    foreach ($positions as $position) {
        $positionId = (int) $position['id'];
        $ids        = $plan[$positionId] ?? [];
        $candidates = [];

        if ($ids !== []) {
            // Placeholders are generated from the validated integer ids.
            $ids         = array_values(array_map('intval', $ids));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $stmt = $pdo->prepare(
                'SELECT id, first_name, last_name, party, biography, photo
                   FROM candidates
                  WHERE id IN (' . $placeholders . ')
                    AND position_id = ?
                    AND election_id = ?'
            );
            $stmt->execute([...$ids, $positionId, $electionId]);
            $candidates = $stmt->fetchAll();
        }

        $describe[] = [
            'position'   => $position,
            'candidates' => $candidates,
        ];
    }

    return $describe;
}

/* =====================================================================
 | 3. Submitting the ballot
 * =================================================================== */

/** A public receipt code such as SV-2026-8F3K2Q. */
function sv_generate_reference(): string
{
    return 'SV-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Write a ballot to the database.
 *
 * Everything is re-checked here, inside one transaction:
 *   - the election is still open and inside its window
 *   - the voter is registered and has not already voted
 *   - every chosen candidate belongs to its position *and* to this
 *     election *and* is still active
 *   - no position exceeds its allowed number of selections
 *
 * If any step fails the whole transaction is rolled back, so a partially
 * recorded ballot can never exist.
 *
 * @return array{ok: bool, reference?: string, error?: string, code?: string}
 */
function sv_submit_ballot(int $electionId, array $plan, int $voterId): array
{
    $pdo = db();

    try {
        $pdo->beginTransaction();

        /* --- 1. Lock the election and confirm it is open right now ------- */
        $electionStmt = $pdo->prepare('SELECT * FROM elections WHERE id = ? LIMIT 1 FOR UPDATE');
        $electionStmt->execute([$electionId]);
        $election = $electionStmt->fetch();

        if (!$election) {
            $pdo->rollBack();

            return ['ok' => false, 'error' => 'That election no longer exists.', 'code' => 'missing'];
        }
        if (election_effective_status($election) !== 'open') {
            $pdo->rollBack();

            return [
                'ok'    => false,
                'error' => 'This election is not open for voting. No ballot was recorded.',
                'code'  => 'not_open',
            ];
        }

        /* --- 2. Lock the voter's registration row ------------------------ */
        $eligibilityStmt = $pdo->prepare(
            'SELECT id, has_voted FROM voter_eligibility
              WHERE election_id = ? AND user_id = ? LIMIT 1 FOR UPDATE'
        );
        $eligibilityStmt->execute([$electionId, $voterId]);
        $eligibility = $eligibilityStmt->fetch();

        if (!$eligibility) {
            $pdo->rollBack();

            return [
                'ok'    => false,
                'error' => 'Your account is not registered to vote in this election.',
                'code'  => 'not_eligible',
            ];
        }
        if ((int) $eligibility['has_voted'] === 1) {
            $pdo->rollBack();

            return [
                'ok'    => false,
                'error' => 'Our records show you have already voted in this election. '
                    . 'Only one ballot can be recorded per voter.',
                'code'  => 'already_voted',
            ];
        }

        /* --- 3. Re-validate every selection against the database --------- */
        $positionStmt = $pdo->prepare(
            'SELECT id, name, max_selections FROM positions WHERE election_id = ?'
        );
        $positionStmt->execute([$electionId]);
        $positions = [];
        foreach ($positionStmt->fetchAll() as $position) {
            $positions[(int) $position['id']] = $position;
        }

        if ($positions === []) {
            $pdo->rollBack();

            return ['ok' => false, 'error' => 'This election has no positions, so nothing could be recorded.', 'code' => 'empty'];
        }

        $validated = [];

        foreach ($positions as $positionId => $position) {
            $ids = $plan[$positionId] ?? [];
            $ids = is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
            $ids = array_values(array_filter($ids, static fn(int $id): bool => $id > 0));

            if ($ids === []) {
                // A blank position is allowed: the voter abstained.
                $validated[$positionId] = [];
                continue;
            }

            $max = max(1, (int) $position['max_selections']);
            if (count($ids) > $max) {
                $pdo->rollBack();

                return [
                    'ok'    => false,
                    'error' => 'Too many candidates were selected for ' . $position['name'] . '.',
                    'code'  => 'too_many',
                ];
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            // The candidate must belong to this position, to this election,
            // and must still be active. Nothing else is accepted.
            $checkStmt = $pdo->prepare(
                "SELECT id FROM candidates
                  WHERE id IN (" . $placeholders . ")
                    AND position_id = ?
                    AND election_id = ?
                    AND status = 'active'"
            );
            $checkStmt->execute([...$ids, $positionId, $electionId]);
            $found = array_map('intval', $checkStmt->fetchAll(PDO::FETCH_COLUMN));

            sort($found);
            $expected = $ids;
            sort($expected);

            if ($found !== $expected) {
                $pdo->rollBack();

                return [
                    'ok'    => false,
                    'error' => 'A candidate selected for ' . $position['name']
                        . ' is not valid for this election. No ballot was recorded.',
                    'code'  => 'invalid_candidate',
                ];
            }

            $validated[$positionId] = $ids;
        }

        /* --- 4. Insert the ballot into the ballots table ---------------- */
        $reference = '';
        $ballotId  = 0;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $reference = sv_generate_reference();
            try {
                $insertBallot = $pdo->prepare(
                    "INSERT INTO ballots (election_id, voter_id, ballot_reference, submitted_at, status)
                     VALUES (?, ?, ?, NOW(), 'submitted')"
                );
                $insertBallot->execute([$electionId, $voterId, $reference]);
                $ballotId = (int) $pdo->lastInsertId();
                break;
            } catch (PDOException $duplicate) {
                if (str_contains($duplicate->getMessage(), 'uq_ballots_election_voter')) {
                    $pdo->rollBack();

                    return [
                        'ok'    => false,
                        'error' => 'A ballot has already been submitted for your account in this election.',
                        'code'  => 'already_voted',
                    ];
                }
                if ($attempt === 4) {
                    $pdo->rollBack();

                    return [
                        'ok'    => false,
                        'error' => 'The ballot could not be recorded because a receipt code could not be allocated. Please try again.',
                        'code'  => 'reference_conflict',
                    ];
                }
            }
        }

        if ($ballotId === 0) {
            $pdo->rollBack();

            return ['ok' => false, 'error' => 'The ballot could not be recorded. Please try again.', 'code' => 'insert_failed'];
        }

        /* --- 5. Insert the selections ----------------------------------- */
        $insertVote = $pdo->prepare(
            'INSERT INTO votes (ballot_id, position_id, candidate_id, created_at) VALUES (?, ?, ?, NOW())'
        );

        foreach ($validated as $positionId => $ids) {
            foreach ($ids as $candidateId) {
                $insertVote->execute([$ballotId, $positionId, $candidateId]);
            }
        }

        /* --- 6. Mark the voter as having voted -------------------------- */
        $markStmt = $pdo->prepare(
            'UPDATE voter_eligibility
                SET has_voted = 1, voted_at = NOW(), ballot_id = ?
              WHERE election_id = ? AND user_id = ?'
        );
        $markStmt->execute([$ballotId, $electionId, $voterId]);

        $pdo->commit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Ballot submission failed: ' . $e->getMessage());

        return [
            'ok'    => false,
            'error' => 'The ballot could not be recorded because of a database problem. Nothing was saved.',
            'code'  => 'db_error',
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Ballot submission failed: ' . $e->getMessage());

        return [
            'ok'    => false,
            'error' => 'The ballot could not be recorded. Nothing was saved.',
            'code'  => 'error',
        ];
    }

    // Logged only after the transaction has committed successfully.
    audit_log('ballot_submitted', 'Ballot ' . $reference . ' submitted for election #'
        . $electionId . ' (' . count($validated) . ' position(s) presented).', $voterId);

    return ['ok' => true, 'reference' => $reference];
}

/**
 * Submit a direct secret ballot using a Student ID / Voter Passcode (No login required).
 *
 * Enforces:
 *   1. 1-person-1-vote per Student ID / Passcode in voter_participation table
 *   2. Decoupled anonymous ballot stored in ballots & votes (no voter ID attached)
 *   3. Transactional safety with row locks
 *
 * @return array{ok: bool, reference?: string, error?: string, code?: string}
 */
function sv_submit_direct_ballot(int $electionId, array $plan, string $voterIdentifier, ?int $userId = null): array
{
    $voterIdentifier = trim($voterIdentifier);
    if ($voterIdentifier === '') {
        return ['ok' => false, 'error' => 'Student ID / Voter Passcode is required.', 'code' => 'missing_identifier'];
    }

    if (mb_strlen($voterIdentifier) < 2 || mb_strlen($voterIdentifier) > 60) {
        return ['ok' => false, 'error' => 'Student ID / Voter Passcode must be between 2 and 60 characters.', 'code' => 'invalid_identifier'];
    }

    $pdo = db();

    try {
        $pdo->beginTransaction();

        /* --- 1. Lock the election and confirm it is open right now ------- */
        $electionStmt = $pdo->prepare('SELECT * FROM elections WHERE id = ? LIMIT 1 FOR UPDATE');
        $electionStmt->execute([$electionId]);
        $election = $electionStmt->fetch();

        if (!$election) {
            $pdo->rollBack();

            return ['ok' => false, 'error' => 'That election no longer exists.', 'code' => 'missing'];
        }
        if (election_effective_status($election) !== 'open') {
            $pdo->rollBack();

            return [
                'ok'    => false,
                'error' => 'This election is not open for voting. No ballot was recorded.',
                'code'  => 'not_open',
            ];
        }

        /* --- 2. Check 1-Person-1-Vote by Student ID / Voter Passcode ----- */
        $partStmt = $pdo->prepare(
            'SELECT id FROM voter_participation WHERE election_id = ? AND voter_identifier = ? LIMIT 1 FOR UPDATE'
        );
        $partStmt->execute([$electionId, $voterIdentifier]);
        if ($partStmt->fetch()) {
            $pdo->rollBack();

            return [
                'ok'    => false,
                'error' => 'Student ID / Passcode "' . $voterIdentifier . '" has already cast a ballot in this election. Only one ballot is permitted per voter.',
                'code'  => 'already_voted',
            ];
        }

        /* --- 3. Re-validate every selection against the database --------- */
        $positionStmt = $pdo->prepare(
            'SELECT id, name, max_selections FROM positions WHERE election_id = ?'
        );
        $positionStmt->execute([$electionId]);
        $positions = [];
        foreach ($positionStmt->fetchAll() as $position) {
            $positions[(int) $position['id']] = $position;
        }

        if ($positions === []) {
            $pdo->rollBack();

            return ['ok' => false, 'error' => 'This election has no positions, so nothing could be recorded.', 'code' => 'empty'];
        }

        $validated = [];

        foreach ($positions as $positionId => $position) {
            $ids = $plan[$positionId] ?? [];
            $ids = is_array($ids) ? array_values(array_unique(array_map('intval', $ids))) : [];
            $ids = array_values(array_filter($ids, static fn(int $id): bool => $id > 0));

            if ($ids === []) {
                $validated[$positionId] = [];
                continue;
            }

            $max = max(1, (int) $position['max_selections']);
            if (count($ids) > $max) {
                $pdo->rollBack();

                return [
                    'ok'    => false,
                    'error' => 'Too many candidates were selected for ' . $position['name'] . '.',
                    'code'  => 'too_many',
                ];
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $checkStmt = $pdo->prepare(
                "SELECT id FROM candidates
                  WHERE id IN (" . $placeholders . ")
                    AND position_id = ?
                    AND election_id = ?
                    AND status = 'active'"
            );
            $checkStmt->execute([...$ids, $positionId, $electionId]);
            $found = array_map('intval', $checkStmt->fetchAll(PDO::FETCH_COLUMN));

            sort($found);
            $expected = $ids;
            sort($expected);

            if ($found !== $expected) {
                $pdo->rollBack();

                return [
                    'ok'    => false,
                    'error' => 'A candidate selected for ' . $position['name']
                        . ' is not valid for this election. No ballot was recorded.',
                    'code'  => 'invalid_candidate',
                ];
            }

            $validated[$positionId] = $ids;
        }

        /* --- 4. Insert the anonymous ballot into the ballot box ---------- */
        $reference = '';
        $ballotId  = 0;

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $reference = sv_generate_reference();
            try {
                $insertBallot = $pdo->prepare(
                    "INSERT INTO ballots (election_id, ballot_reference, submitted_at, status)
                     VALUES (?, ?, NOW(), 'submitted')"
                );
                $insertBallot->execute([$electionId, $reference]);
                $ballotId = (int) $pdo->lastInsertId();
                break;
            } catch (PDOException $duplicate) {
                if ($attempt === 4) {
                    $pdo->rollBack();

                    return [
                        'ok'    => false,
                        'error' => 'The ballot could not be recorded because a receipt code could not be allocated. Please try again.',
                        'code'  => 'reference_conflict',
                    ];
                }
            }
        }

        if ($ballotId === 0) {
            $pdo->rollBack();

            return ['ok' => false, 'error' => 'The ballot could not be recorded. Please try again.', 'code' => 'insert_failed'];
        }

        /* --- 5. Insert candidate choices --------------------------------- */
        $insertVote = $pdo->prepare(
            'INSERT INTO votes (ballot_id, position_id, candidate_id, created_at) VALUES (?, ?, ?, NOW())'
        );

        foreach ($validated as $positionId => $ids) {
            foreach ($ids as $candidateId) {
                $insertVote->execute([$ballotId, $positionId, $candidateId]);
            }
        }

        /* --- 6. Record participation for Student ID ---------------------- */
        $ipHash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
        $partInsert = $pdo->prepare(
            'INSERT INTO voter_participation (election_id, voter_identifier, voted_at, ballot_reference, ip_hash)
             VALUES (?, ?, NOW(), ?, ?)'
        );
        $partInsert->execute([$electionId, $voterIdentifier, $reference, $ipHash]);

        // If a logged-in user account was tied, mark voter_eligibility as well if present
        if ($userId !== null && $userId > 0) {
            $markStmt = $pdo->prepare(
                'UPDATE voter_eligibility
                    SET has_voted = 1, voted_at = NOW(), ballot_reference = ?
                  WHERE election_id = ? AND user_id = ? AND has_voted = 0'
            );
            $markStmt->execute([$reference, $electionId, $userId]);
        }

        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Direct ballot submission failed: ' . $e->getMessage());

        if (str_contains($e->getMessage(), 'uq_voter_participation')) {
            return [
                'ok'    => false,
                'error' => 'Student ID / Passcode "' . $voterIdentifier . '" has already cast a ballot in this election.',
                'code'  => 'already_voted',
            ];
        }

        return [
            'ok'    => false,
            'error' => 'The ballot could not be recorded because of a database issue. Nothing was saved.',
            'code'  => 'db_error',
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Direct ballot submission failed: ' . $e->getMessage());

        return [
            'ok'    => false,
            'error' => 'The ballot could not be recorded. Nothing was saved.',
            'code'  => 'error',
        ];
    }

    audit_log(
        'direct_ballot_submitted',
        'Direct ballot ' . $reference . ' submitted for election #' . $electionId . ' by ID: ' . sv_mask_identifier($voterIdentifier),
        $userId
    );

    return ['ok' => true, 'reference' => $reference];
}

/* =====================================================================
 | 4. Session helpers for the two-step voting flow
 |
 | The review page deliberately does not read the selections from the
 | form. They are kept server-side in the session, so the review screen
 | shows exactly what the server is prepared to store.
 * =================================================================== */

/** Remember a validated plan for the review step. */
function sv_store_pending_ballot(int $electionId, array $plan): void
{
    $_SESSION['pending_ballot'][$electionId] = [
        'plan'       => $plan,
        'created_at' => time(),
    ];
}

/** Remember a validated direct plan and voter identifier for the review step. */
function sv_store_pending_direct_ballot(int $electionId, array $plan, string $identifier): void
{
    $_SESSION['pending_direct_ballot'][$electionId] = [
        'plan'       => $plan,
        'identifier' => trim($identifier),
        'created_at' => time(),
    ];
}

/** Read the pending direct plan and identifier, or null. */
function sv_pending_direct_ballot(int $electionId): ?array
{
    $entry = $_SESSION['pending_direct_ballot'][$electionId] ?? null;

    if (!is_array($entry) || !isset($entry['plan']) || !is_array($entry['plan']) || empty($entry['identifier'])) {
        return null;
    }

    if (isset($entry['created_at']) && (time() - (int) $entry['created_at']) > 3600) {
        sv_clear_pending_direct_ballot($electionId);

        return null;
    }

    return $entry;
}

function sv_clear_pending_direct_ballot(int $electionId): void
{
    unset($_SESSION['pending_direct_ballot'][$electionId]);
}

/** Read the pending plan, or null when there is none (or it has gone stale). */
function sv_pending_ballot(int $electionId): ?array
{
    $entry = $_SESSION['pending_ballot'][$electionId] ?? null;

    if (!is_array($entry) || !isset($entry['plan']) || !is_array($entry['plan'])) {
        return null;
    }

    // A review screen left open for hours must not submit a stale ballot.
    if (isset($entry['created_at']) && (time() - (int) $entry['created_at']) > 3600) {
        sv_clear_pending_ballot($electionId);

        return null;
    }

    return $entry['plan'];
}

function sv_clear_pending_ballot(int $electionId): void
{
    unset($_SESSION['pending_ballot'][$electionId]);
}


/* =====================================================================
 | 5. Counting an election
 |
 | One implementation, shared by the administrator's results page and the
 | voter's results page, so the two can never disagree. Counting is
 | aggregate only: a candidate's total is the number of votes rows that
 | point at them from a ballot that is still "submitted". Nothing here
 | joins a ballot to the voter's name, which is what keeps the ballot
 | secret while still producing a tally.
 * =================================================================== */

/**
 * @return array<int, array{position: array, rows: array, total_selections: int,
 *                          ballots_with_choice: int, leading: array<int,bool>}>
 */
function sv_tally_positions(int $electionId): array
{
    $pdo = db();

    $positionStmt = $pdo->prepare(
        'SELECT id, name, description, max_selections, display_order
           FROM positions
          WHERE election_id = ?
          ORDER BY display_order ASC, name ASC'
    );
    $positionStmt->execute([$electionId]);

    $tally = [];

    foreach ($positionStmt->fetchAll() as $position) {
        $positionId = (int) $position['id'];

        // The candidate is matched through position_id as well as id, so a
        // malformed row could never inflate somebody's total.
        $tallyStmt = $pdo->prepare(
            "SELECT c.id, c.first_name, c.last_name, c.party, c.status, c.photo,
                    (SELECT COUNT(*)
                       FROM votes v
                       JOIN ballots b ON b.id = v.ballot_id
                      WHERE v.candidate_id = c.id
                        AND v.position_id = c.position_id
                        AND b.status = 'submitted') AS vote_count
               FROM candidates c
              WHERE c.position_id = :position_id
                AND c.election_id = :election_id
              ORDER BY vote_count DESC, c.last_name ASC, c.first_name ASC"
        );
        $tallyStmt->execute([':position_id' => $positionId, ':election_id' => $electionId]);
        $rows = $tallyStmt->fetchAll();

        // How many ballots actually carried a choice for this position?
        $castStmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT v.ballot_id)
               FROM votes v
               JOIN ballots b ON b.id = v.ballot_id
              WHERE v.position_id = ? AND b.status = 'submitted'"
        );
        $castStmt->execute([$positionId]);
        $ballotsWithChoice = (int) $castStmt->fetchColumn();

        $totalSelections = 0;
        foreach ($rows as $row) {
            $totalSelections += (int) $row['vote_count'];
        }

        // Leading: the top max_selections candidates. A tie on the cut-off
        // count is reported as a tie rather than decided silently.
        $seats       = max(1, (int) $position['max_selections']);
        $leadingIds  = [];
        $rank        = 0;
        $cutOffCount = null;

        foreach ($rows as $row) {
            $count = (int) $row['vote_count'];
            if ($count <= 0) {
                break;
            }

            $rank++;
            if ($rank <= $seats) {
                $leadingIds[(int) $row['id']] = true;
                $cutOffCount = $count;
                continue;
            }

            if ($cutOffCount !== null && $count === $cutOffCount) {
                $leadingIds[(int) $row['id']] = true;
            }
            break;
        }

        $tally[] = [
            'position'            => $position,
            'rows'                => $rows,
            'total_selections'    => $totalSelections,
            'ballots_with_choice' => $ballotsWithChoice,
            'leading'             => $leadingIds,
        ];
    }

    return $tally;
}
