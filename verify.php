<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/community.php';
header('Cache-Control: no-store'); header('Referrer-Policy: no-referrer');
$code = ''; $result = null; $errors = [];
if (is_post()) {
    require_csrf();
    $code = strtoupper((string) input('receipt', ''));
    if (!sv_feature_allow('receipt', 20, 900)) {
        http_response_code(429); $errors[] = sv_t('Too many checks from this connection. Try again in 15 minutes.');
    } elseif (!preg_match('/^SV-[0-9]{4}-[A-Z0-9]{6,16}$/D', $code)) {
        $errors[] = sv_t('Enter the full receipt code, such as SV-2026-ABC123.');
    } else {
        // Deliberately never join users or votes, or return identity, time, or choices.
        $stmt = db()->prepare("SELECT e.title FROM ballots b JOIN elections e ON e.id = b.election_id WHERE b.ballot_reference = ? AND b.status = 'submitted' LIMIT 1");
        $stmt->execute([$code]); $title = $stmt->fetchColumn();
        $result = $title === false ? ['found' => false] : ['found' => true, 'title' => $title];
    }
}
$pageTitle = 'Verify a receipt'; $activeNav = 'verify.php';
require __DIR__ . '/includes/header.php';
?>
<div class="feature-page feature-page--narrow stack"><div class="feature-intro"><h1 class="visually-hidden"><?= e(sv_t("Check your ballot receipt")) ?></h1><p><?= e(sv_t("Confirm that a submitted ballot with your receipt code is recorded. This check does not reveal who voted or their selections, and does not prove tally correctness.")) ?></p></div>
<?php sv_feature_errors($errors); ?>
<form method="post" action="<?= e(post_url('verify.php')) ?>" class="feature-form stack" autocomplete="off"><?= csrf_field() ?><label for="receipt"><?= e(sv_t("Receipt code")) ?></label><input class="input" id="receipt" name="receipt" maxlength="30" value="<?= e($code) ?>" placeholder="SV-2026-ABC123" required spellcheck="false" autocapitalize="characters"><button class="btn btn--primary"><?= e(sv_t("Check receipt")) ?></button></form>
<?php if ($result !== null): ?><section class="feature-empty" role="status"><h2><?= $result['found'] ? e(sv_t('Recorded ballot found')) : e(sv_t('No submitted ballot found')) ?></h2><p><?= $result['found'] ? sprintf(sv_t('A submitted ballot is recorded for %s.'), e($result['title'])) : e(sv_t('Check your code and try again. If the problem continues, contact the election team.')) ?></p><a class="link" href="<?= e(url('help.php')) ?>"><?= e(sv_t("Get help")) ?></a></section><?php endif; ?>
<p class="text-muted text-sm"><?= e(sv_t("Keep your receipt private. Never share your voter code or password when requesting help.")) ?></p></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
