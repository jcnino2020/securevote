<?php
/**
 * SecureVote - Secure Online Voting System
 * =====================================================================
 * includes/footer.php
 *
 * Closes the application shell and prints the footer navigation.
 */

$user      = current_user();
?>
<?php if (($layout ?? 'app') !== 'auth'): ?>
  </main>

  <?php if (($layout ?? '') === 'app' && ($user['role'] ?? '') === 'voter'): ?>
    <nav class="voter-dock" aria-label="<?= e(sv_t('Voter quick navigation')) ?>">
      <?php foreach ([
          ['dashboard.php', 'Home', 'dashboard'],
          ['elections.php', 'Elections', 'vote'],
          ['results.php', 'Results', 'results'],
          ['profile.php', 'Profile', 'user'],
      ] as [$file, $label, $icon]): ?>
        <a href="<?= e(url('voter/' . $file)) ?>" <?= ($activeNav ?? '') === $file ? 'aria-current="page"' : '' ?>>
          <?= sv_icon($icon, 19) ?><span><?= e(sv_t($label)) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <footer class="app-footer" role="contentinfo">
    <div class="app-footer__row">
      <div class="app-footer__brand">
        <?= sv_logo_mark(22, 'brand__mark brand__mark--sm') ?>
        <span class="app-footer__title"><?= e(APP_NAME) ?></span>
        <span class="app-footer__ver">v<?= e(APP_VERSION) ?></span>
      </div>

      <div class="app-footer__legal">
        <span>&copy; <?= e(date('Y')) ?> <?= e(APP_NAME) ?> &middot; <?= e(sv_t("Academic Civic Voting Platform")) ?></span>
        <div class="app-footer__links">
          <a href="<?= e(url('guide.php')) ?>" class="link"><?= e(sv_t("Election guide")) ?></a>
          <a href="<?= e(url('help.php')) ?>" class="link"><?= e(sv_t("Help")) ?></a>
          <a href="<?= e(url('privacy.php')) ?>" class="link" target="_blank" rel="noopener noreferrer"><?= e(sv_t("Privacy Policy")) ?></a>
          <span aria-hidden="true">&middot;</span>
          <a href="<?= e(url('terms.php')) ?>" class="link" target="_blank" rel="noopener noreferrer"><?= e(sv_t("Terms of Service")) ?></a>
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
