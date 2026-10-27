document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('resolveAlertModal');
  const activeTab = document.getElementById('alert-tab-active-btn');
  const resolvedTab = document.getElementById('alert-tab-resolved-btn');
  const activePanel = document.getElementById('alert-panel-active');
  const resolvedPanel = document.getElementById('alert-panel-resolved');

  function switchAlertTab(name) {
    const resolved = name === 'resolved';
    activeTab?.classList.toggle('is-active', !resolved);
    activeTab?.setAttribute('aria-selected', String(!resolved));
    resolvedTab?.classList.toggle('is-active', resolved);
    resolvedTab?.setAttribute('aria-selected', String(resolved));
    if (activePanel) activePanel.style.display = resolved ? 'none' : 'block';
    if (resolvedPanel) resolvedPanel.style.display = resolved ? 'block' : 'none';
  }

  document.querySelectorAll('[data-alert-tab]').forEach((button) => {
    button.addEventListener('click', () => switchAlertTab(button.dataset.alertTab));
  });

  document.querySelectorAll('[data-open-resolve]').forEach((button) => {
    button.addEventListener('click', () => {
      if (!modal) return;
      document.getElementById('resolveModalKey').value = button.dataset.alertKey || '';
      document.getElementById('resolveModalTitle').value = button.dataset.alertTitle || '';
      document.getElementById('resolveModalSeverity').value = button.dataset.alertSeverity || 'Medium';
      document.getElementById('resolveModalDisplayTitle').textContent = button.dataset.alertTitle || '';
      document.getElementById('resolveModalDisplayText').textContent = button.dataset.alertText || '';
      document.getElementById('resolveModalReason').value = '';
      modal.showModal();
    });
  });

  document.querySelectorAll('[data-close-resolve]').forEach((button) => {
    button.addEventListener('click', () => modal?.close());
  });

  document.querySelectorAll('[data-confirm-submit]').forEach((button) => {
    button.closest('form')?.addEventListener('submit', (event) => {
      if (!window.confirm(button.dataset.confirmSubmit || 'Continue?')) event.preventDefault();
    });
  });
});
