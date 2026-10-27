<?php
declare(strict_types=1);

/** Strict, bounded CSV parser. No passwords accepted or returned. */
function sv_parse_voter_csv($stream): array
{
    $headers = fgetcsv($stream, 0, ',', '"', '');
    if (!$headers) return ['rows' => [], 'errors' => [sv_t('The CSV is empty.')]];
    $headers = array_map(fn($v) => strtolower(trim((string) $v)), $headers);
    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
    $required = ['username','email','first_name','last_name'];
    if (count($headers) !== 4 || array_diff($required, $headers)) return ['rows' => [], 'errors' => [sv_t('The header must contain exactly username,email,first_name,last_name (in any order).')]];
    $rows = []; $errors = []; $seenUsers = []; $seenEmails = []; $line = 1;
    while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
        $line++;
        if ($values === [null]) continue;
        if (count($rows) >= 500) { $errors[] = sv_t('Import at most 500 voters per file.'); break; }
        if (count($values) !== 4) { $errors[] = sprintf(sv_t('Row %d: expected four columns.'), $line); continue; }
        $row = array_combine($headers, array_map(fn($v) => trim((string) $v), $values));
        if (!mb_check_encoding(implode('', $row), 'UTF-8')) { $errors[] = sprintf(sv_t('Row %d: save the file as UTF-8 CSV.'), $line); continue; }
        $row['username'] = strtolower($row['username']); $row['email'] = strtolower($row['email']);
        $row['line'] = $line;
        if (!preg_match('/^[a-z0-9._-]{3,50}$/D', $row['username'])) $errors[] = sprintf(sv_t('Row %d: username must use 3–50 letters, numbers, dots, underscores, or hyphens.'), $line);
        if (!filter_var($row['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($row['email']) > 150) $errors[] = sprintf(sv_t('Row %d: invalid email.'), $line);
        foreach (['first_name','last_name'] as $key) if (mb_strlen($row[$key]) < 1 || mb_strlen($row[$key]) > 80 || preg_match('/[\x00-\x1F\x7F]/', $row[$key])) $errors[] = sprintf(sv_t('Row %d: %s must contain 1–80 printable characters.'), $line, $key);
        if (isset($seenUsers[$row['username']]) || isset($seenEmails[$row['email']])) $errors[] = sprintf(sv_t('Row %d: duplicate username or email in the file.'), $line);
        $seenUsers[$row['username']] = true; $seenEmails[$row['email']] = true;
        $rows[] = $row;
    }
    if (!$rows && !$errors) $errors[] = sv_t('Add at least one voter after the header.');
    return ['rows' => $rows, 'errors' => array_slice($errors, 0, 50)];
}

/** Repeated at preview and under transaction at confirmation. Never modifies existing accounts. */
function sv_check_import_rows(PDO $pdo, array $rows, bool $lock = false): array
{
    $errors = []; $checked = [];
    $stmt = $pdo->prepare('SELECT id, username, email, role, status FROM users WHERE username = ? OR email = ?' . ($lock ? ' FOR UPDATE' : ''));
    foreach ($rows as $row) {
        $stmt->execute([$row['username'], $row['email']]); $matches = $stmt->fetchAll();
        $row['existing_id'] = 0;
        if ($matches) {
            $match = $matches[0];
            if (count($matches) !== 1 || strtolower($match['username']) !== $row['username'] || strtolower($match['email']) !== $row['email'] || $match['role'] !== 'voter' || $match['status'] !== 'active') {
                $errors[] = sprintf(sv_t('Row %d: username/email conflicts with an existing account, or the account is not an active voter.'), $row['line']);
            } else $row['existing_id'] = (int) $match['id'];
        }
        $checked[] = $row;
    }
    return ['rows' => $checked, 'errors' => array_slice($errors, 0, 50)];
}

function sv_commit_voter_import(PDO $pdo, array $rows, int $electionId): array
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT id FROM elections WHERE id = ? AND status IN ('draft','scheduled','open') AND end_datetime > NOW() FOR UPDATE");
        $stmt->execute([$electionId]);
        if (!$stmt->fetchColumn()) throw new RuntimeException(sv_t('Choose an election that is draft, scheduled, or open and has not ended.'));
        $checked = sv_check_import_rows($pdo, $rows, true);
        if ($checked['errors']) throw new RuntimeException(implode(' ', $checked['errors']));
        $insert = $pdo->prepare("INSERT INTO users (username,email,first_name,last_name,voter_code,password_hash,role,status) VALUES (?,?,?,?,?,?,'voter','active')");
        $enrol = $pdo->prepare('INSERT INTO voter_eligibility (election_id,user_id,has_voted) VALUES (?,?,0) ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)');
        $created = 0; $existing = 0;
        foreach ($checked['rows'] as $row) {
            $id = $row['existing_id'];
            if (!$id) {
                // Code-only account; administrators distribute codes privately via the registry.
                $hash = null;
                $insert->execute([$row['username'], $row['email'], $row['first_name'], $row['last_name'], sv_generate_voter_code(), $hash]);
                $id = (int) $pdo->lastInsertId(); $created++;
            } else $existing++;
            $enrol->execute([$electionId, $id]);
        }
        $pdo->commit();
        return ['created' => $created, 'existing' => $existing];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
