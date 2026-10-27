<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/community.php';
$user = current_user(); $pdo = sv_community_db(); $errors = [];
$form = ['email' => $user['email'] ?? '', 'category' => 'other', 'subject' => '', 'message' => ''];
$categories = ['eligibility' => 'Eligibility', 'login' => 'Sign-in problem', 'ballot' => 'Ballot problem', 'other' => 'Other'];
if (is_post()) {
    require_csrf();
    foreach ($form as $key => $value) $form[$key] = input($key, '');
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($form['email']) > 150) $errors[] = sv_t('Enter a valid contact email.');
    if (!isset($categories[$form['category']])) $errors[] = sv_t('Choose a valid issue category.');
    if (mb_strlen($form['subject']) < 3 || mb_strlen($form['subject']) > 150) $errors[] = sv_t('Use a subject of 3–150 characters.');
    if (mb_strlen($form['message']) < 10 || mb_strlen($form['message']) > 3000) $errors[] = sv_t('Describe the issue in 10–3,000 characters.');
    if (!$errors && !sv_feature_allow('support', 5, 3600)) { http_response_code(429); $errors[] = sv_t('Too many reports from this connection. Try again in one hour.'); }
    if (!$errors) {
        $reference = 'SV-ISS-' . strtoupper(bin2hex(random_bytes(8)));
        $stmt = $pdo->prepare('INSERT INTO support_tickets (reference, user_id, email, category, subject, message) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$reference, $user['id'] ?? null, $form['email'], $form['category'], $form['subject'], $form['message']]);
        audit_log('support_created', 'Support ticket #' . (int) $pdo->lastInsertId() . ' created.');
        flash_success(sprintf(sv_t('Report received. Your reference is %s. %s'), $reference, $user ? sv_t('Track updates under Your reports below.') : sv_t('Keep this reference for follow-up with your election administrator.')));
        redirect('help.php');
    }
}
$tickets = [];
if ($user) { $stmt = $pdo->prepare('SELECT reference, subject, status, response, updated_at FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC LIMIT 50'); $stmt->execute([$user['id']]); $tickets = $stmt->fetchAll(); }
$pageTitle = 'Help and support'; $activeNav = 'help.php';
require __DIR__ . '/includes/header.php';
?>
<div class="feature-page stack"><div class="feature-intro"><h1 class="visually-hidden"><?= e(sv_t("A little help with voting")) ?></h1><p><?= e(sv_t("Find answers or report a problem to your election team.")) ?></p></div>
<section class="faq-list" aria-label="<?= e(sv_t("Common questions")) ?>">
<?php foreach ([
'How do I know whether I can vote?' => 'Sign in and open Elections. Your account must be active and registered for the election. If voting is refused, report an eligibility issue below so the administrator can check your registration.',
'Can I skip a position?' => 'Yes. Leave all candidates for that position unselected to abstain. Review your choices before submitting.',
'Can I change my vote after submission?' => 'No. You can change your selections during review, but a submitted real ballot is final. Each voter may submit one ballot per election.',
'What if the deadline passes while I am voting?' => 'The server checks the deadline again when you submit. A ballot submitted after the election closes cannot be accepted. Dates and times use Asia/Manila.',
'I cannot sign in. What should I do?' => 'Check your username, email, or voter code. If your account is locked, wait for the lockout period to pass or contact the administrator. Report sign-in problems below without including passwords or voter codes.',
'What does receipt verification prove?' => 'It confirms a submitted ballot with that code is in the database. It does not show choices or independently prove the tally is correct.',
'When can I see results?' => 'Availability depends on the election’s results policy. Open the Results page for the election’s current notice.',
] as $question => $answer): ?><details><summary><?= e(sv_t($question)) ?></summary><p><?= e(sv_t($answer)) ?></p></details><?php endforeach; ?></section>
<p><a class="link" href="<?= e(url('practice.php')) ?>"><?= e(sv_t("Try a practice ballot")) ?></a> · <a class="link" href="<?= e(url('verify.php')) ?>"><?= e(sv_t("Check a receipt")) ?></a></p>
<section aria-labelledby="report-heading"><h2 id="report-heading"><?= e(sv_t("Report an issue")) ?></h2><p><?= e(sv_t("Do not include your selections, password, voter code, or receipt code. Reports are stored for the election team; this form does not send email.")) ?> <?= $user ? e(sv_t('You can read replies below.')) : e(sv_t('Sign in before reporting if you want to track replies here. For sign-in issues, keep your report reference and follow up with your election administrator.')) ?></p>
<?php sv_feature_errors($errors); ?>
<form class="feature-form stack" method="post" action="<?= e(post_url('help.php')) ?>"><?= csrf_field() ?><label for="email"><?= e(sv_t("Contact email")) ?></label><input class="input" id="email" name="email" type="email" maxlength="150" required value="<?= e($form['email']) ?>"><label for="category"><?= e(sv_t("Issue category")) ?></label><select class="input" name="category" id="category"><?php foreach ($categories as $key => $label): ?><option value="<?= $key ?>" <?= $form['category'] === $key ? 'selected' : '' ?>><?= e(sv_t($label)) ?></option><?php endforeach; ?></select><label for="subject"><?= e(sv_t("Subject")) ?></label><input class="input" id="subject" name="subject" required minlength="3" maxlength="150" value="<?= e($form['subject']) ?>"><label for="message"><?= e(sv_t("What happened?")) ?></label><textarea class="input" id="message" name="message" rows="6" required minlength="10" maxlength="3000"><?= e($form['message']) ?></textarea><button class="btn btn--primary"><?= e(sv_t("Send issue report")) ?></button></form></section>
<?php if ($user): ?><section><h2><?= e(sv_t("Your reports")) ?></h2><?php if (!$tickets): ?><p><?= e(sv_t("You have no reports yet.")) ?></p><?php endif; ?><?php foreach ($tickets as $ticket): ?><article class="feature-story"><span class="badge badge--neutral"><?= e(str_replace('_', ' ', ucfirst($ticket['status']))) ?></span><h3><?= e($ticket['subject']) ?></h3><p class="text-muted text-sm"><?= e($ticket['reference']) ?> · <?= sprintf(sv_t('Updated %s'), e(fmt_datetime($ticket['updated_at']))) ?></p><?php if ($ticket['response']): ?><h4><?= e(sv_t("Election team reply")) ?></h4><p class="feature-prose"><?= nl2br(e($ticket['response'])) ?></p><?php else: ?><p><?= e(sv_t("Waiting for an election team reply.")) ?></p><?php endif; ?></article><?php endforeach; ?></section><?php endif; ?></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
