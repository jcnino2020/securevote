<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * includes/footer.php
 *
 * Closes the application shell and prints the modern 2026 security strip
 * and footer navigation.
 */

$user      = current_user();
$isHttps   = sv_is_https();
$transport = $isHttps
    ? ['icon' => 'lock',          'label' => 'Encrypted TLS/HTTPS Active']
    : ['icon' => 'alert-triangle','label' => 'Local Environment (HTTP) — Deploy with HTTPS for Production'];
?>
<?php if (($layout ?? 'app') !== 'auth'): ?>
  </main>

  <footer class="app-footer" role="contentinfo">
    <div class="app-footer__row">
      <div class="app-footer__brand">
        <?= sv_logo_mark(22, 'brand__mark brand__mark--sm') ?>
        <span class="app-footer__title"><?= e(APP_NAME) ?></span>
        <span class="app-footer__ver">v<?= e(APP_VERSION) ?></span>
      </div>

      <ul class="security-strip" aria-label="Security controls active on this system">
        <li class="security-strip__item security-strip__item--<?= $isHttps ? 'ok' : 'warn' ?>">
          <?= sv_icon($transport['icon'], 14) ?>
          <span><?= e($transport['label']) ?></span>
        </li>
        <li class="security-strip__item">
          <?= sv_icon('key', 14) ?>
          <span>Bcrypt Password Hashing</span>
        </li>
        <li class="security-strip__item">
          <?= sv_icon('database', 14) ?>
          <span>Parameterized PDO Statements</span>
        </li>
        <li class="security-strip__item">
          <?= sv_icon('shield-check', 14) ?>
          <span>Secret Ballot Transaction Integrity</span>
        </li>
      </ul>

      <div class="app-footer__legal">
        <span>&copy; <?= e(date('Y')) ?> <?= e(APP_NAME) ?> &middot; Academic Civic Voting Platform</span>
        <div class="app-footer__links">
          <a href="<?= e(url('privacy.php')) ?>" class="link" target="_blank" rel="noopener noreferrer">Privacy Policy</a>
          <span aria-hidden="true">&middot;</span>
          <a href="<?= e(url('terms.php')) ?>" class="link" target="_blank" rel="noopener noreferrer">Terms of Service</a>
        </div>
      </div>
    </div>
  </footer>
</div>
<?php else: ?>
</main>
<?php endif; ?>

</body>
</html>
