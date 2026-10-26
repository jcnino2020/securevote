# Responsive UI verification

`ui-shell-fixture.php` is CLI-only and database-free. It renders the real voter/admin shell with fictional long content, a multi-column table, and an invalid form field. Do not deploy this fixture.

Generate fixtures from the repository root:

```sh
php tests/ui-shell-fixture.php voter > /tmp/securevote-ui-audit/voter.html
php tests/ui-shell-fixture.php admin > /tmp/securevote-ui-audit/admin.html
```

Serve that temporary directory on localhost with an `assets` symlink to this repository's assets directory. Use the browser viewport capability to test 320, 360, 375, 390, 414, 430, 768, 1024, and 1440px. Verify the actual viewport width, not just the requested override. Let the page finish rendering before measuring geometry.

For each surface check:

- `document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1`.
- Exactly one main landmark and primary heading.
- Controls have accessible names; form errors refer to existing feedback elements.
- Overflowing table wrappers can receive keyboard focus and scroll independently.
- Mobile drawer traps focus, locks background scrolling, closes on Escape, and restores focus.
- Preferences work in both themes, at 200% text, and with keyboard-only operation.
- Public navigation switches at 1300px without overlapping account actions.

`ui-layout-results.json` records the 153 local layout cases; `ui-live-results.json` records the 36 live smoke cases from October 26, 2026. These are test evidence, not an automatic WCAG certificate. The parent audit report lists manual and real-device checks still required.
