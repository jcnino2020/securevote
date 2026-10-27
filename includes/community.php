<?php
declare(strict_types=1);

/** Additive setup on first use, following the existing traffic-table lifecycle. */
function sv_community_db(): PDO
{
    $pdo = db();
    static $ready = false;
    if (!$ready) {
        try {
            foreach (['announcements', 'support_tickets', 'feature_rate_limits'] as $table) {
                $pdo->query("SELECT 1 FROM $table LIMIT 1");
            }
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? 0) !== 1146) throw $e;
            try {
                $sql = file_get_contents(__DIR__ . '/../database/migration_community_features.sql');
                foreach (explode(';', $sql) as $statement) {
                    if (trim($statement) !== '') $pdo->exec($statement);
                }
            } catch (PDOException $setupError) {
                error_log('Community schema setup failed: ' . $setupError->getMessage());
                sv_error_page(503, 'Feature setup needed', 'Ask the administrator to apply the community features database migration, then try again.', []);
            }
        }
        $ready = true;
    }
    return $pdo;
}

/** Shared database limits survive session/cookie changes. Never store submitted codes. */
function sv_feature_allow(string $action, int $limit, int $seconds): bool
{
    $pdo = sv_community_db();
    $time = time();
    $window = intdiv($time, $seconds) * $seconds;
    $bucket = hash('sha256', $action . '|' . client_ip());
    $stmt = $pdo->prepare('INSERT INTO feature_rate_limits (bucket, window_start, attempts) VALUES (?, ?, 1)
        ON DUPLICATE KEY UPDATE attempts = IF(window_start = VALUES(window_start), attempts + 1, 1), window_start = VALUES(window_start)');
    $stmt->execute([$bucket, $window]);
    $stmt = $pdo->prepare('SELECT attempts FROM feature_rate_limits WHERE bucket = ?');
    $stmt->execute([$bucket]);
    // Bounded cleanup of expired counters.
    $cleanup = $pdo->prepare('DELETE FROM feature_rate_limits WHERE window_start < ? LIMIT 100');
    $cleanup->execute([$time - 86400]);
    return (int) $stmt->fetchColumn() <= $limit;
}

function sv_public_elections(PDO $pdo): array
{
    return $pdo->query("SELECT * FROM elections WHERE status IN ('scheduled','open','closed','archived') ORDER BY start_datetime DESC")->fetchAll();
}

/** Read-only election preparation checks, including the app's auto-enrolment policy. */
function sv_election_readiness(PDO $pdo, array $election): array
{
    $id = (int) $election['id'];
    $stmt = $pdo->prepare("SELECT p.id, p.name, p.max_selections, COUNT(c.id) AS candidates
        FROM positions p LEFT JOIN candidates c ON c.position_id = p.id AND c.election_id = p.election_id AND c.status = 'active'
        WHERE p.election_id = ? GROUP BY p.id, p.name, p.max_selections ORDER BY p.display_order, p.name");
    $stmt->execute([$id]);
    $positions = $stmt->fetchAll();
    $empty = array_column(array_filter($positions, fn($p) => (int) $p['candidates'] === 0), 'name');
    $short = array_column(array_filter($positions, fn($p) => (int) $p['candidates'] > 0 && (int) $p['candidates'] < (int) $p['max_selections']), 'name');
    $active = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'voter' AND status = 'active'")->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM voter_eligibility ve JOIN users u ON u.id = ve.user_id WHERE ve.election_id = ? AND u.role = 'voter' AND u.status = 'active'");
    $stmt->execute([$id]);
    $assigned = (int) $stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM candidates WHERE election_id = ? AND status = 'active' AND (COALESCE(TRIM(platform),'') = '' OR COALESCE(TRIM(biography),'') = '')");
    $stmt->execute([$id]);
    $missing = (int) $stmt->fetchColumn();
    $start = to_datetime($election['start_datetime']);
    $end = to_datetime($election['end_datetime']);
    $schedule = $start && $end && $end > $start && $end->getTimestamp() > time();
    return [
        ['ok' => $schedule, 'label' => sv_t('Voting schedule'), 'detail' => $schedule ? fmt_range($election['start_datetime'], $election['end_datetime']) : sv_t('Set a valid voting window with an end time in the future.'), 'href' => 'admin/elections.php?action=edit&id=' . $id],
        ['ok' => $positions !== [] && $empty === [], 'label' => sv_t('Positions and candidates'), 'detail' => $positions === [] ? sv_t('Add at least one position.') : ($empty ? sprintf(sv_t('No active candidates: %s'), implode(', ', $empty)) : sprintf(sv_t('%d positions each have active candidates.'), count($positions))), 'href' => 'admin/positions.php?election_id=' . $id],
        ['ok' => $active > 0 && $assigned === $active, 'label' => sv_t('Voter assignment'), 'detail' => sprintf(sv_t('%d of %d active voters assigned. Opening through Open now automatically enrols all active voters; scheduled elections need assignments in advance.'), $assigned, $active), 'href' => 'admin/voters.php'],
        ['ok' => $missing === 0, 'label' => sv_t('Candidate information'), 'detail' => $missing ? sprintf(sv_t('%d active candidates need a biography or platform.'), $missing) : sv_t('All active candidates have a biography and platform.'), 'href' => 'admin/candidates.php?election_id=' . $id],
        ['ok' => $short === [], 'label' => sv_t('Selection limits'), 'detail' => $short ? sprintf(sv_t('Fewer candidates than allowed selections: %s. Review whether this is intentional.'), implode(', ', $short)) : sv_t('Selection limits fit the active candidate counts.'), 'href' => 'admin/positions.php?election_id=' . $id],
    ];
}

function sv_practice_ballot(): array
{
    return [
        ['position' => ['id' => 1, 'name' => 'Practice president', 'max_selections' => 1], 'candidates' => [
            ['id' => 101, 'first_name' => 'Alex', 'last_name' => 'Rivera'],
            ['id' => 102, 'first_name' => 'Jamie', 'last_name' => 'Santos'],
        ]],
        ['position' => ['id' => 2, 'name' => 'Practice representatives', 'max_selections' => 2], 'candidates' => [
            ['id' => 201, 'first_name' => 'Sam', 'last_name' => 'Cruz'],
            ['id' => 202, 'first_name' => 'Taylor', 'last_name' => 'Reyes'],
            ['id' => 203, 'first_name' => 'Morgan', 'last_name' => 'Lee'],
        ]],
    ];
}

function sv_feature_errors(array $errors): void
{
    if ($errors === []) return;
    echo '<div class="alert alert--danger" role="alert"><div><strong>' . e(sv_t('Please check the following:')) . '</strong><ul>';
    foreach ($errors as $error) echo '<li>' . e(sv_t($error)) . '</li>';
    echo '</ul></div></div>';
}
