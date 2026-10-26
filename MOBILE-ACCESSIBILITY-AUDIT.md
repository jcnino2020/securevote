# SecureVote responsive and accessibility improvement report

Date: October 26, 2026 (Asia/Manila)

## Scope and design constraints

This is a refinement of the existing PHP application, not a rebuild. The shared public, authentication, voter, and administrator layouts were reviewed together with page templates, inputs, tables, charts, preferences, navigation, and error pages. Existing routes, database schema, authentication rules, voting logic, API contracts, translations, and content were preserved.

No BRAND-KIT.md was present. DESIGN.md and PRODUCT.md supplied the brand context. The available design-taste-frontend and Impeccable guidance was applied within the task's preservation and accessibility constraints. No frontend framework or runtime dependency was added.

## Priorities and resolutions

| Priority | Finding | Resolution |
| --- | --- | --- |
| High | Public header positioning and repeated overrides could overlap navigation and account controls. | Centralized the final rules in responsive.css. Navigation occupies a real grid column on wide screens and uses the existing menu below 1300px. |
| High | CSS used a different public-menu breakpoint from JavaScript. | Both now use 1300px; open menus close when the desktop navigation becomes available. |
| High | Privacy and Terms retained an inline two-column span after collapsing to one column. | Reset the content column at narrow widths; fixed verified 320px horizontal overflow. |
| High | Some account templates had no primary heading after visible titles were replaced with breadcrumbs. | Added a screen-reader primary page title for legacy templates, avoiding templates that own their heading. |
| High | Wide tables were scrollable with a pointer but not consistently accessible to keyboard users. | Overflowing wrappers receive keyboard focus, a named region, and a visible focus indicator. Existing table relationships remain intact; column headers receive scope=col. |
| Medium | Field error associations were inconsistent across older forms. | Existing field errors are associated with their controls using aria-describedby and aria-invalid; existing descriptions are retained. |
| Medium | Mobile drawer background was still interactive. | The shell is inert while the drawer is open. Escape closes it, focus returns to the opener, and background scrolling is locked. Focus containment includes all interactive controls. |
| Medium | Duplicate preferences markup and incremental styles caused drift. | Extracted one shared preferences component; consolidated shared header, preferences, and responsive rules. |
| Medium | Browser font zoom, long content, and safe areas could stress layout. | Added minimum-width corrections, long-content wrapping, 16px mobile inputs, 200% text preference, safe-area spacing, and wrapping account headers. Sticky-header offsets now track the rendered height. Election countdown/status rows wrap at enlarged text sizes. |
| Medium | Low-emphasis light-theme text was below 4.5:1 against the page background. | Darkened that token while preserving the palette. Key text-token pairs were checked mathematically in both themes; this is not a full rendered contrast audit. |
| Medium | External font CSS and the full interaction script were on the initial render path. | Self-hosted the existing variable fonts, preloaded the primary Latin text font, and split the 464-byte prepaint theme decision from the deferred interaction script. |
| Low | Candidate photos lacked explicit size hints. | Added width/height and asynchronous decoding. Below-fold images are progressively lazy-loaded without changing above-fold loading. |

## Files changed in this task

- Shared templates: includes/header.php, includes/display-settings.php (new), includes/error-page.php.
- Styles: assets/css/responsive.css (new), assets/css/fonts.css (new), assets/css/next-features.css (shared overrides removed).
- Scripts: assets/js/theme.js (new), assets/js/app.js, assets/js/accessibility.js, assets/js/navigation.js.
- Candidate image markup: admin/candidates.php, admin/results.php.
- Font resources: four WOFF2 subsets in assets/fonts/ and their SIL Open Font License files.
- Verification artifacts: tests/ui-shell-fixture.php, tests/ui-layout-results.json, tests/ui-live-results.json, tests/UI-AUDIT.md.

The checkout already contained many unrelated local changes before this task. They were not discarded. This file list describes this task's edits, not the entire working-tree diff.

## Verification completed

- PHP syntax: 70 application PHP files passed.
- JavaScript syntax: all 6 scripts passed node --check.
- Impeccable mechanical detector: no findings on the changed files.
- Local browser matrix: 153 cases passed page-overflow, one-main, and one-h1 checks (15 public HTML snapshots plus synthetic voter/admin shells, each at 320, 360, 375, 390, 414, 430, 768, 1024, and 1440 CSS pixels).
- The public snapshots use the application's real markup and updated assets. The account fixtures render the real shared header/sidebar/footer with fictional stress content; they do not replace authenticated end-to-end testing of every screen.
- Live smoke matrix: 36 cases passed on Home, Login, Privacy, and Election Guide at the same nine widths. Results record actual viewport widths, overflow, main landmarks, headings, and absence of external Google font links.
- Tested mobile account navigation opening, Escape closing, restored opener focus, and scroll lock on the synthetic admin shell.
- Tested public navigation opening and closing across the desktop breakpoint on the deployed site.
- Tested 200% text preference on guide/account surfaces; reset works. A final live Home check at 320px and 200% text passed with only 1px rounding tolerance after wrapping header controls and election countdown labels.
- Tested light/dark switching and persistence across navigation. Captured live mobile login and desktop home in the two themes.
- Tested field-error associations and focusable horizontal table scrolling in the stress fixture.
- Confirmed the existing reduced-motion rules remain supported and added a shared forced-colors fallback; physical assistive-device verification remains below.

Lighthouse and axe-core were unavailable. Browser DOM, geometry, accessible-name/heading/landmark checks, syntax checks, and targeted keyboard interactions were used instead. These checks do not establish full WCAG conformance.

## Performance impact and measurement limits

The brand fonts now load from the same host, removing font-service DNS/connection and stylesheet dependencies. A small synchronous theme script preserves appearance before paint; the approximately 23KB interaction script is deferred. Variable WOFF2 fonts replace external weight requests, with Latin extensions available on demand. Candidate image dimensions reduce layout-shift risk, and below-fold image decoding/loading is deferred where applicable.

No LCP, INP, CLS, or Lighthouse score is claimed. Real-world thresholds require representative field data at the 75th percentile, plus mobile lab measurements under realistic network and CPU conditions. See [Google's Web Vitals guidance](https://web.dev/articles/vitals).

## Remaining manual verification

- Run full authenticated voter/admin journeys with representative large datasets, long translations, and permission states. No real votes or production account changes were made during this audit.
- Test VoiceOver and NVDA announcements, reading order, table navigation, and dynamic voting feedback.
- Test actual iOS Safari and Android Chrome with their on-screen keyboards, plus Safari, Firefox, Edge, and Chrome desktop. The automated browser session is not a substitute for these browser/device combinations.
- Test native browser zoom at 200% and 400%, portrait/landscape rotation, and operating-system text scaling. The 320px tests address the equivalent reflow width; the 200% preference is text enlargement, not a claim that native zoom was tested. [W3C reflow guidance](https://www.w3.org/WAI/WCAG22/Understanding/reflow.html).
- Audit rendered contrast for every status, chart, hover, disabled, and error state; token-pair checks cover only the named base text tokens.
- Review target-size spacing exceptions for dense table links and inline legal links. Header/action controls aim for 44px; WCAG's AA minimum and exceptions differ. [W3C target-size guidance](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html).
- Measure production Core Web Vitals and backend response time with field telemetry or a dedicated performance service. No telemetry changes were introduced.

## Deployment

The changed application templates, scripts, styles, and font resources were deployed to the existing Hostinger SecureVote directory. Test fixtures and audit artifacts were kept local. Public pages and real account workflows retain their existing URLs and integrations.
