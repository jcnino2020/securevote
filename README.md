# SecureVote — Secure Online Voting System

An academic endterm project: a web-based voting platform built with **PHP + MySQL/MariaDB**,
managed with **phpMyAdmin**, that demonstrates the three core information security principles
in working code rather than in a slide deck.

| Principle | Where you can see it working |
| --- | --- |
| **Authentication** | bcrypt password hashing, `password_verify()`, account status checks, per-account brute-force braking, session id regeneration, idle and absolute session timeouts, browser fingerprint binding |
| **Integrity** | server-side re-validation of every selection, one ballot per voter enforced by database unique constraints, the whole ballot written inside a single transaction, relational integrity through InnoDB foreign keys |
| **Confidentiality** | prepared statements everywhere, `htmlspecialchars()` on all output, CSP and security headers, HttpOnly session cookies, audit logging that never records a choice, results that are only ever produced from aggregate queries |

---

## 1. What the system does

**Administrator**

* Dashboard with turnout, alerts and the newest audit entries
* Election management: create, edit, open, close, archive, delete
* Position management with per-position selection limits
* Candidate management, including an optional profile photo
* Voter account management, including election eligibility
* Results with per-position tallies, leading candidates, ties and CSV export
* Audit log with filters and CSV export
* Settings: own profile, own password, security posture, database snapshot, audit retention

**Voter**

* Personal dashboard: what is open, what has been voted, how voting works
* Election list with instructions and the reason a ballot cannot be opened
* Ballot with per-position selection limits
* Review screen before anything is recorded
* Final submission with a reference receipt
* Results, but only when the election's results policy allows it
* Own profile, own password and own participation history

---

## 2. Required software

| Requirement | Version used | Notes |
| --- | --- | --- |
| PHP | 8.1 or newer (developed and tested on **8.5**) | Uses `never`, `mixed`, `match` and `str_contains`. Needs `pdo_mysql`, `mbstring`, `session`, `random` (all standard) |
| MySQL or MariaDB | MySQL 5.7+ / MariaDB 10.2+ (tested on **MariaDB 12.3**) | InnoDB is required for transactions and foreign keys |
| phpMyAdmin | any recent version | Used to import the SQL and inspect the data |
| Web browser | any modern browser | Chromium or Firefox recommended |

The easiest way to get all of this at once on Windows or macOS is **XAMPP** (or Laragon).
The application uses **no Composer packages and no build step**: it is plain PHP, one CSS file
and one JavaScript file.

---

## 3. Installation

### 3.1 Put the project in the web root

Copy the project folder into your server's document root, for example:

```text
XAMPP (macOS)   /Applications/XAMPP/xamppfiles/htdocs/secure-voting-system/
XAMPP (Windows) C:\xampp\htdocs\secure-voting-system\
```

The application detects whether it is served from a domain root or from a sub-folder, so both
`http://localhost/secure-voting-system/` and `http://localhost:8000/` work without editing code.

### 3.2 Start MySQL/MariaDB

**XAMPP:** start **Apache** and **MySQL** from the control panel.

Verify from a terminal:

```bash
mysql -u root -p -e "SELECT VERSION();"
```

### 3.3 Create the database by importing the SQL

1. Open phpMyAdmin: `http://localhost/phpmyadmin/`
2. Click the **Import** tab at the top
3. **Choose file** → `database/secure_voting.sql`
4. Leave every option at its default and click **Import**

The file creates the `secure_voting` database, all eight tables and the demonstration data in
one step. If your hosting already gave you an empty database, open the SQL file, delete the
`CREATE DATABASE` and `USE` lines, then import into that database instead.

Check that it worked: you should see `users` (13 rows), `elections` (3), `positions` (8),
`candidates` (22) and `voter_eligibility` (30 rows).

### 3.4 Database configuration

Credentials live in `config/config.php` (section 3) and default to a stock XAMPP install:

```php
define('DB_HOST',   '127.0.0.1');
define('DB_PORT',   '3306');
define('DB_NAME',   'secure_voting');
define('DB_USER',   'root');
define('DB_PASS',   '');
```

Every value can be overridden with an environment variable instead of editing the file, which
is how the project was tested without touching the source:

```bash
SV_DB_HOST=127.0.0.1 SV_DB_PORT=3306 SV_DB_NAME=secure_voting \
SV_DB_USER=root SV_DB_PASS=secret php -S localhost:8000 -t .
```

If your MySQL listens on a socket rather than a TCP port, set `SV_DB_SOCKET` to the socket path
(for example `/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock`).

### 3.5 Run the application

Either use Apache (XAMPP) and open:

```text
http://localhost/secure-voting-system/
```

or use PHP's built-in server from inside the project folder:

```bash
php -S localhost:8000 -t .
```

then open `http://localhost:8000/`. Both routes land on the sign-in page.

---

## 4. Demonstration accounts

Seeded by `database/secure_voting.sql`. Every name in the dataset is fictional.

| Role | Username | Password | What it is for |
| --- | --- | --- | --- |
| Administrator | `admin` | `Admin@12345` | Full access to the admin module |
| Voter, still able to vote | `voter009` | `Voter@12345` | The account to use for a live vote during the presentation |
| Voter, already voted | `voter001` | `Voter@12345` | Demonstrates that the server refuses a second ballot |
| Voter, blocked | `voter020` | `Voter@12345` | Demonstrates that a blocked account cannot sign in |

`voter001` through `voter020` all use `Voter@12345`. Voters `voter001` to `voter008` have
already voted in the open election; `voter009` to `voter020` have not, so there is always an
account available to submit a ballot.

> **These passwords exist only for the presentation.** Change them before the system is used
> for anything real, and delete the demonstration hint block in `auth/login.php`.

### 4.1 What the sample dataset contains

Nothing in the database is left empty, so every screen has something to show.

| Table | Rows | What it demonstrates |
| --- | ---: | --- |
| `users` | 21 | 1 administrator and 20 voters, one of them blocked |
| `elections` | 3 | One open, one scheduled, one closed |
| `positions` | 11 | 7 in the open election; Representative allows 2 selections |
| `candidates` | 33 | 3 per position in the open election (4 for Representative), 2 to 3 for the other two, across three fictional organizations |
| `voter_eligibility` | 60 | All 20 voters registered for all three elections |
| `ballots` | 11 | 8 in the open election, 3 in the closed one |
| `votes` | 67 | Every selection, each one consistent with its ballot and position |
| `audit_logs` | 57 | One continuous history, from account creation to this morning |

The three elections are deliberately in three different states, each with a different results
policy, so the whole state machine can be demonstrated without editing anything:

| Election | State | Results policy | Demonstrates |
| --- | --- | --- | --- |
| Student Council Election 2026 | Open | `after_vote` | Voting works normally, and a voter sees the tally once they have voted |
| College Department Election 2026 | Scheduled | `hidden` | The server refuses a ballot before the start date |
| Student Organization Election 2026 | Closed | `after_close` | The server refuses a ballot after the end date, and results are published |

Expected figures on the administrator dashboard straight after a fresh import: **3 elections
(1 open, 1 scheduled, 1 closed)**, **20 registered voters**, **8 votes cast of 20 eligible
(40% turnout)**, **33 candidates**, **11 ballots submitted** and **3 failed sign-ins in the
last 24 hours**. Every one of those numbers is counted from the tables on each request, so
they move as soon as the data moves.

Because every timestamp is written relative to the moment of import, the open election always
has two days behind it and five ahead, and the audit trail always looks recent. The
demonstration never goes stale.

---

## 5. Creating an election from scratch

The seeded data already contains a running election, so this section is only needed if you
want to build one live during the presentation.

1. **Create the election** — *Elections → New election*.
   Set a title, a start time in the past, an end time in the future, status **Draft**, and a
   results policy. Saving takes you straight to the edit screen.
2. **Add positions** — *Manage positions* on that screen, or *Positions → New position*.
   Choose the election, give the position a name (for example `President`), set
   **Selections allowed** to `1`, and set the display order.
3. **Add candidates** — *Candidates → New candidate*.
   Pick the election, then the position. The server verifies that the position really belongs
   to that election. Two candidates per position is enough for a demonstration.
4. **Register voters** — *Voters*. Either create a new account (*New voter*), or open an
   existing one and press **Elections** to tick the elections they may vote in. The
   **Register everyone** button on that page adds every active voter at once.
5. **Open the election** — *Elections → Open*.
   The system refuses to open an election that has no positions, or whose position has no
   active candidate. It also moves the start time to the current moment if the start time is
   still in the future, because an election can never be open before it starts.
6. **Vote** — sign in as a registered voter, press **Vote now**, fill the ballot, press
   **Review my ballot**, then **Submit final ballot**.
7. **See the result** as the administrator, or as a voter once the election's results policy
   allows it. Each of the three seeded elections uses a different policy (`after_vote`,
   `hidden`, `after_close`), so all three behaviours can be shown without editing anything.
   Note the trade-off: `after_vote` on a running election lets voters see the live tally,
   which is convenient for a demonstration but can influence later voters. Switch the open
   election to `after_close` for the more conservative rule.

---

## 6. Testing the security controls

The point of the project is that each of these can be *demonstrated*, not just described.
Every test below can be run against the sample data with no preparation at all.

### 6.1 Authentication

| Test | How | Expected result |
| --- | --- | --- |
| Valid sign-in | `admin` / `Admin@12345` | Redirected to the admin dashboard, entry in the audit log |
| Wrong password | `admin` / `wrongpass` | "Incorrect username/email or password", `login_failure` in the audit log |
| Unknown account | `nobody` / `whatever` | Same generic message, so the form never reveals whether an account exists |
| Blocked account | `voter020` / `Voter@12345` | "This account has been blocked", `login_blocked` in the audit log. This case is seeded. |
| Brute-force brake | Five wrong passwords in a row | The sixth attempt is refused for about 15 minutes |
| Session protection | Sign in, then delete the session cookie and reload | Sent back to the sign-in page |
| Session regeneration | Sign in and watch the session cookie | The id changes at the moment of sign-in |
| Sign-out | *Log out* in the sidebar | Session cleared, `logout` in the audit log. `auth/logout.php` opened directly with GET answers 405 |

### 6.2 Authorization

| Test | How | Expected result |
| --- | --- | --- |
| Voter opens an admin page | Sign in as `voter009`, then visit `admin/dashboard.php` | 403 "Not authorized", `unauthorized_access` in the audit log |
| Admin opens a voter page | Sign in as `admin`, visit `voter/ballot.php?election_id=1` | 403, logged |
| Ballot before the start date | Sign in as `voter009` and open the scheduled election (`election_id=2`) | 403 "This election has not started yet", `ballot_closed_attempt` in the audit log. This case is seeded. |
| Ballot after the end date | Sign in as `voter009` and open the closed election (`election_id=3`) | 403 "This election is closed", logged. This case is seeded. |
| Voter not on the voter list | As the administrator, untick that voter on *Voters → Elections*, then try to open the ballot | 403 "Not registered for this election", `unauthorized_election_access` in the audit log |

### 6.3 Integrity

| Test | How | Expected result |
| --- | --- | --- |
| Successful vote | Sign in as `voter009` and vote once | Confirmation page with a reference such as `SV-2026-9A31F4` |
| Duplicate vote | Sign in as `voter001`, who has already voted, and open the same ballot | Blocked before the ballot renders, with `ballot_duplicate_attempt` in the audit log. This case is seeded. |
| Duplicate vote at the database level | See the demonstration queries at the bottom of `database/secure_voting.sql` | The database itself rejects the insert |
| Invalid candidate | With the ballot form open, use the browser inspector to add a `votes[…]` value for a candidate from another position and submit | "A selected candidate for … is not standing in this election" and nothing is written |
| Too many selections | On the Representative position, select three candidates where only two are allowed | Rejected on the server with a clear message |
| Voting outside the window | Close the open election, then submit a prepared ballot | 403 before the ballot renders; if a stale review screen is submitted, the transaction refuses it |
| Blank position | Leave a position unselected and submit | Allowed and recorded as an abstention; the review screen warns first |
| Transaction rollback | Stop MySQL mid-submission | Nothing is recorded: the ballot, its votes and the eligibility flag are rolled back together |

### 6.4 Confidentiality

| Test | How | Expected result |
| --- | --- | --- |
| Password hashing | phpMyAdmin → `users` → `password_hash` | A `$2y$12$…` bcrypt hash, never readable text |
| SQL injection | Sign in with the username `' OR 1=1 -- ` | Treated as a literal username; the query is a prepared statement |
| SQL injection in a search box | Enter `' OR 1=1 -- ` in the voter search | No rows, no error, no data leak |
| XSS | Add a candidate named `<script>alert(1)</script>` | The name is displayed as text; the script never runs |
| Stored XSS in a biography | Put an `<img src=x onerror=alert(1)>` in a biography field | Escaped on the ballot and on the review screen |
| CSRF | Remove the `csrf_token` input with the inspector and submit a form | 403 "Request blocked", `csrf_failure` in the audit log |
| Logout CSRF | `<img src="…/auth/logout.php">` on another page | Nothing happens: logout requires a POST with a valid token |
| Session cookie | Browser developer tools → Application → Cookies | `HttpOnly` set, `SameSite=Lax`, no token in the URL |
| Information disclosure | Open `config/config.php` in a browser | It is PHP; nothing is printed, and errors are logged rather than displayed |

---

## 7. Project structure

```text
secure-voting-system/
├── index.php                     entry point: routes to the dashboard or the sign-in page
├── README.md
├── config/
│   ├── config.php                constants, security headers, hardened session bootstrap
│   └── database.php              the single PDO connection (prepared statements, exceptions)
├── auth/
│   ├── login.php                 sign-in form
│   ├── authenticate.php          POST handler: CSRF, bcrypt verification, account status
│   └── logout.php                POST-only, CSRF-protected sign-out
├── admin/
│   ├── dashboard.php             turnout, alerts, recent activity
│   ├── elections.php             election CRUD, open/close/archive/delete
│   ├── positions.php             position CRUD with selection limits
│   ├── candidates.php            candidate CRUD with a hardened photo upload
│   ├── voters.php                account CRUD, status changes, election eligibility
│   ├── results.php               per-position tally, leading candidates, CSV export
│   ├── audit-logs.php            filtered, read-only audit trail
│   └── settings.php              own profile and password, security posture, maintenance
├── voter/
│   ├── dashboard.php             what is open, what has been voted, how to vote
│   ├── elections.php             election list and per-election instructions
│   ├── ballot.php                the ballot (nothing is written here)
│   ├── review.php                the review screen and the only submit button
│   ├── confirmation.php          the receipt with the ballot reference
│   ├── results.php               results, gated by the election's results policy
│   └── profile.php               own details, own password, own participation history
├── includes/
│   ├── bootstrap.php             loads everything, installs the error handlers
│   ├── auth.php                  identity, session lifecycle, role guards
│   ├── csrf.php                  token issue, rotation and verification
│   ├── flash.php                 one-shot messages across a redirect
│   ├── functions.php             escaping, URLs, dates, status vocabulary, audit logging
│   ├── voting.php                the voting engine: build, validate, submit, count
│   ├── icons.php                 the inline SVG icon set and the brand mark
│   ├── header.php                the application shell
│   ├── footer.php                shared footer and the security strip
│   └── error-page.php            the branded 403 / 404 / 500 page
├── assets/
│   ├── css/style.css             the entire brand kit, light and dark
│   ├── js/app.js                 theme, drawer, ballot limits, confirms, filters, modals
│   └── images/                   favicon.svg, logo.svg, candidates/ (uploaded photos)
├── database/
│   └── secure_voting.sql         schema, constraints, seed data, demonstration queries
└── storage/logs/                 php-error.log (created on the first error)
```

### How the database fits together

```text
users ─┬─< elections (created_by)
       ├─< voter_eligibility >─ elections        one row per voter per election (UNIQUE)
       ├─< ballots >──────────  elections        one ballot per voter per election (UNIQUE)
       └─< audit_logs

elections ─< positions ─< candidates
ballots   ─< votes     ─> positions, candidates
```

The two unique keys that make duplicate voting impossible:

```text
voter_eligibility : uq_voter_eligibility_election_user (election_id, user_id)
ballots           : uq_ballots_election_voter          (election_id, voter_id)
                    uq_ballots_reference               (ballot_reference)
votes             : uq_votes_ballot_position_candidate (ballot_id, position_id, candidate_id)
```

---

## 8. How the voting flow protects itself

1. `voter/ballot.php` builds the ballot **from the database**: only positions of that election,
   and only candidates whose status is `active`.
2. On submit, `sv_validate_submission()` rejects any value that is not a real candidate of that
   position in that election, and any position with more selections than allowed. It reports the
   problem instead of silently trimming it.
3. The validated plan is kept **in the session**, not in a hidden form field. The review screen
   therefore shows exactly what the server is prepared to store.
4. `voter/review.php` is the only page that submits. It calls `sv_submit_ballot()`, which opens a
   transaction and re-checks the election window, the voter's registration, whether they have
   already voted, and every single candidate against the database.
5. The ballot, its votes and the `has_voted` flag are written together and committed once. If any
   step fails, everything rolls back and the voter is told nothing was recorded.
6. Counting happens in `sv_tally_positions()`, an aggregate query that never joins a ballot to a
   voter's name, so a tally can be produced without a ballot ever being de-anonymised.

---

## 9. Security controls that are implemented

Everything listed here is really in the code, which is why the interface is allowed to say so.

* **Password storage** — `password_hash()` with bcrypt, verified with `password_verify()`, and
  transparently re-hashed when the cost changes.
* **SQL injection** — PDO prepared statements with `ATTR_EMULATE_PREPARES = false`, so the SQL
  text and the data are sent to MySQL separately.
* **Cross-site scripting** — every dynamic value is printed through `e()`
  (`htmlspecialchars()` with `ENT_QUOTES`), and the Content-Security-Policy forbids inline and
  remote scripts.
* **Cross-site request forgery** — a per-session token on every state-changing form, compared
  with `hash_equals()`, rotated on sign-in, sign-out and password change.
* **Session security** — `HttpOnly` and `SameSite=Lax` cookie, `session.use_strict_mode`,
  `session_regenerate_id(true)` on privilege change, a 30-minute idle timeout, a 2-hour absolute
  lifetime and a browser fingerprint check.
* **Authorization** — `require_admin()` / `require_voter()` run before any page body, deny by
  default, and log every refusal.
* **Vote integrity** — server-side re-validation, row locks (`FOR UPDATE`), one transaction per
  ballot, and unique constraints as the final defence.
* **Upload safety** — image type detected from the file contents with `getimagesize()`, only
  JPEG/PNG/WebP accepted, a 2 MB cap, a random stored filename and a genuine-upload check.
* **Privacy of results** — results are produced from aggregate queries only; no page can show
  which voter chose which candidate.
* **Auditability** — sign-ins, failures, election changes, eligibility changes, ballot submissions
  and configuration changes are appended to `audit_logs`, which the interface cannot edit.
* **Information disclosure** — errors are logged, never printed; a friendly branded page is shown
  instead; security headers include `X-Content-Type-Options`, `X-Frame-Options`,
  `Referrer-Policy` and a restrictive CSP.

## 10. Limitations and non-goals

Stated openly, because a security report that claims everything is a security report nobody
should trust.

* **HTTPS is not configured by the application.** Use HTTPS at the web server in any real
  deployment. The footer says so when the page is served over plain HTTP.
* **No two-factor authentication** and **no self-service password reset** (there is no email
  delivery). Administrators reset passwords from the Voters page.
* **No public registration.** Accounts are created by an administrator, which is the correct
  posture for a controlled election.
* **The database user is not least-privileged by default.** Section 11 shows how to create a
  dedicated user limited to SELECT, INSERT, UPDATE and DELETE on this schema.
* **Votes are not cryptographically anonymised.** The tally is aggregate and no page exposes a
  link between voter and choice, but the `votes` rows are reachable by anyone who can read the
  database. End-to-end verifiable voting is a much larger problem than an endterm project.
* **No rate limiting at the web-server level.** The brute-force brake is per account and lives in
  the application.
* **Being honest about the scope**: this is a college-level implementation, not a certified
  election platform.

---

## 11. Optional hardening for a real deployment

```sql
-- 1. A database user that can only do what the application needs.
CREATE USER 'securevote_app'@'localhost' IDENTIFIED BY 'a-strong-password-here';
GRANT SELECT, INSERT, UPDATE, DELETE ON `secure_voting`.* TO 'securevote_app'@'localhost';
FLUSH PRIVILEGES;
```

Then set `DB_USER` / `DB_PASS` accordingly (or the `SV_DB_*` environment variables).

```php
// 2. In config/config.php: never print errors to the browser.
define('APP_DEBUG', false);
```

```text
3. Serve the application over HTTPS and add the HSTS header that config.php already sends when
   it detects HTTPS.
4. Remove the demonstration hint block from auth/login.php and change every seeded password.
5. Keep storage/ outside the web root, or make sure your web server refuses to serve it.
```

---

## 12. Troubleshooting

| Symptom | Cause and fix |
| --- | --- |
| "Service temporarily unavailable" | PHP cannot reach the database. Check that MySQL is running and that the credentials in `config/config.php` are right. |
| The page loads with no styling | `assets/css/style.css` is not being found. Confirm the folder is spelled `assets` and that the web server is serving the project folder. |
| Everything redirects to the sign-in page | Sessions are not persisting. Check `session.save_path` and that the browser is not blocking cookies. |
| "Your session token was missing or had expired" | The form was open too long, or cookies were cleared. Reload the page and try again. |
| Sign-in always fails with the seeded accounts | The seed data may not have been imported. Check that `users` has 13 rows in phpMyAdmin. |
| Photos do not upload | `assets/images/candidates/` must be writable by the web server user. |
| A blank white page | Look at `storage/logs/php-error.log`. Nothing is ever printed to the browser on purpose. |

---

## 13. Further reading

* OWASP Top 10 — <https://owasp.org/www-project-top-ten/>
* OWASP Cross-Site Request Forgery Prevention Cheat Sheet — <https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html>
* OWASP File Upload Cheat Sheet — <https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html>
* PHP manual, `password_hash()` — <https://www.php.net/manual/en/function.password-hash.php>
* PHP manual, PDO prepared statements — <https://www.php.net/manual/en/pdo.prepared-statements.php>
* MySQL manual, InnoDB transactions — <https://dev.mysql.com/doc/refman/8.0/en/innodb-transaction-model.html>

---

**SecureVote** is an academic endterm project. It is not affiliated with, endorsed by, or
connected to any government body or electoral commission.
