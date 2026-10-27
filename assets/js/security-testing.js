document.addEventListener('DOMContentLoaded', () => {
  const pills = [...document.querySelectorAll('.cia-pill')];
  const searchInput = document.getElementById('ciaSearchInput');
  const rows = [...document.querySelectorAll('.test-row')];
  let currentFilter = 'all';

  function toggleDetail(row) {
    const button = row.querySelector('button[aria-controls]');
    const box = button && document.getElementById(button.getAttribute('aria-controls'));
    if (!box) return;
    const isOpen = box.classList.toggle('is-open');
    row.classList.toggle('is-expanded', isOpen);
    button.setAttribute('aria-expanded', String(isOpen));
  }

  function applyFilters() {
    const query = (searchInput?.value || '').toLowerCase().trim();
    rows.forEach((row) => {
      const status = row.dataset.status || '';
      const matchesPillar = currentFilter === 'all'
        || (currentFilter === 'ATTENTION' && ['WARNING', 'FAIL', 'NOT_TESTABLE'].includes(status))
        || row.dataset.cia === currentFilter;
      const matchesQuery = !query || row.textContent.toLowerCase().includes(query);
      const visible = matchesPillar && matchesQuery;
      row.style.display = visible ? '' : 'none';
      const detailRow = row.nextElementSibling;
      if (detailRow?.classList.contains('test-detail-row')) detailRow.style.display = visible ? '' : 'none';
    });
  }

  rows.forEach((row) => {
    row.addEventListener('click', (event) => {
      if (event.target.closest('button, a, input, select')) return;
      toggleDetail(row);
    });
    row.querySelector('button[aria-controls]')?.addEventListener('click', () => toggleDetail(row));
  });

  pills.forEach((pill) => pill.addEventListener('click', () => {
    pills.forEach((item) => {
      const active = item === pill;
      item.classList.toggle('is-active', active);
      item.setAttribute('aria-selected', String(active));
    });
    currentFilter = pill.dataset.filter || 'all';
    applyFilters();
  }));

  searchInput?.addEventListener('input', applyFilters);
  document.querySelector('[data-security-print]')?.addEventListener('click', () => window.print());
});
