<?php
declare(strict_types=1);
require_once __DIR__ . '/community.php';
function sv_engagement_db(): PDO {
    $pdo = db(); static $ready = false;
    if (!$ready) {
        try {
            foreach (['election_milestones','candidate_answers','reminder_dismissals'] as $table) $pdo->query("SELECT 1 FROM $table LIMIT 1");
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1146) throw $e;
            foreach (explode(';', file_get_contents(__DIR__ . '/../database/migration_engagement.sql')) as $sql) if (trim($sql)) $pdo->exec($sql);
        }
        $ready = true;
    }
    return $pdo;
}
function sv_reminder_key(array $e, string $kind): string {
    return hash('sha256', $e['id'].'|'.$e['start_datetime'].'|'.$e['end_datetime'].'|'.$kind);
}
function sv_reminders(PDO $pdo, int $userId, ?int $now = null): array {
    $now ??= time();
    $stmt = $pdo->prepare("SELECT e.* FROM elections e JOIN voter_eligibility ve ON ve.election_id=e.id AND ve.user_id=?
        WHERE e.status IN ('scheduled','open') AND NOT EXISTS (SELECT 1 FROM ballots b WHERE b.election_id=e.id AND b.voter_id=? AND b.status='submitted') ORDER BY e.end_datetime");
    $stmt->execute([$userId,$userId]);
    $hidden = $pdo->prepare('SELECT reminder_key FROM reminder_dismissals WHERE user_id=?'); $hidden->execute([$userId]);
    $hidden = array_flip($hidden->fetchAll(PDO::FETCH_COLUMN)); $items=[];
    foreach ($stmt->fetchAll() as $e) {
        $start=to_datetime($e['start_datetime']); $end=to_datetime($e['end_datetime']);
        if (!$start || !$end || $end->getTimestamp() <= $now) continue;
        if ($start->getTimestamp() > $now) {
            if ($start->getTimestamp()-$now > 86400) continue;
            $kind='soon'; $message=sv_t('Voting opens within 24 hours.');
        } else {
            $kind=$end->getTimestamp()-$now <= 86400 ? 'closing' : 'open';
            $message=sv_t($kind==='closing' ? 'Voting closes within 24 hours. Submit your ballot before the deadline.' : 'Voting is open. Your ballot is waiting.');
        }
        $key=sv_reminder_key($e,$kind);
        if (!isset($hidden[$key])) $items[]=['key'=>$key,'election'=>$e,'message'=>$message];
    }
    return $items;
}
function sv_timeline(array $e, array $m): array {
    $rows=[];
    foreach (['registration_opens'=>'Registration opens','registration_closes'=>'Registration closes','campaign_opens'=>'Campaign starts','campaign_closes'=>'Campaign ends'] as $key=>$label) $rows[] = [$label,$m[$key] ?? null];
    $rows[]=['Voting opens',$e['start_datetime']]; $rows[]=['Voting closes',$e['end_datetime']];
    $rows[]=['Results announcement',$m['results_at'] ?? null];
    return $rows;
}
