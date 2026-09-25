<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * includes/footer.php
 *
 * Closes the shell opened by header.php and prints the shared footer.
 *
 * The security strip states what the system actually does. It never
 * claims a protection that has not been implemented: on plain HTTP it
 * says so instead of pretending the connection is encrypted.
 */

$user         = current_user();
$isHttps      = sv_is_https();
$transport    = $isHttps
    ? ['icon' => 'lock',          'label' => 'Encrypted connection (HTTPS)']
    : ['icon' => 'alert-triangle','label' => 'Local development mode (HTTP) — enable HTTPS before real use'];
?>
<?php if (($layout ?? 'app') !== 'auth'): ?>
  </main>

  <footer class="app-footer">
    <div class="app-footer__row">
      <span class="app-footer__brand">
        <?= sv_logo_mark(20, 'brand__mark brand__mark--sm') ?>
        <span><?= e(APP_NAME) ?></span>
        <span class="text-muted">&middot; <?= e(APP_VERSION) ?></span>
      </span>

      <ul class="security-strip" aria-label="Security controls in effect">
        <li class="security-strip__item security-strip__item--<?= $isHttps ? 'ok' : 'warn' ?>">
          <?= sv_icon($transport['icon'], 15) ?>
          <span><?= e($transport['label']) ?></span>
        </li>
        <li class="security-strip__item">
          <?= sv_icon('key', 15) ?>
          <span>Passwords stored as bcrypt hashes</span>
        </li>
        <li class="security-strip__item">
          <?= sv_icon('database', 15) ?>
          <span>All queries use prepared statements</span>
        </li>
        <li class="security-strip__item">
          <?= sv_icon('shield-check', 15) ?>
          <span>Ballots are recorded in a database transaction</span>
        </li>
      </ul>

      <span class="app-footer__note text-muted text-sm">
        &copy; <?= e(date('Y')) ?> SecureVote &middot; 
        <a href="<?= e(url('privacy.php')) ?>" class="link" style="color:inherit; text-decoration:underline;">Privacy Policy</a> &middot; 
        <a href="<?= e(url('terms.php')) ?>" class="link" style="color:inherit; text-decoration:underline;">Terms of Service</a> &middot; 
        Academic civic project.
      </span>
    </div>
  </footer>
</div>
<?php else: ?>
</main>
<?php endif; ?>

</body>
</html>
