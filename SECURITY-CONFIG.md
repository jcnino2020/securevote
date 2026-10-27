# Private configuration

Credentials previously committed to this repository must be revoked or rotated at their providers. Removing them from current files does not remove them from Git history.

SecureVote reads `SV_DB_HOST`, `SV_DB_PORT`, `SV_DB_NAME`, `SV_DB_USER`, `SV_DB_PASS`, `SV_DB_SOCKET`, `GOOGLE_CLIENT_ID`, and `GOOGLE_CLIENT_SECRET` from environment variables. Alternatively set `SV_CONFIG_FILE` to an absolute PHP file outside the web root that returns an array with those keys. The Hostinger default is `securevote.private.php` in the domain directory, outside `public_html`.

Environment values take precedence, including deliberately empty values. Give private configuration files permission 0600 and never commit or upload them into a public directory. Missing credentials disable OAuth/SMTP without enabling demo authentication.

## Registration email verification

Password-based voter registration can require a six-digit email code. It is off until `SV_REGISTRATION_OTP_ENABLED` is set to `1` in private configuration. Mail keys can live in `securevote.mail.php` in the domain directory outside `public_html` (or the file named by `SV_MAIL_CONFIG_FILE`). Configure `SV_SMTP_HOST`, `SV_SMTP_PORT`, `SV_SMTP_USER`, `SV_SMTP_PASS`, `SV_SMTP_SECURE` (`ssl` or `tls`), and `SV_MAIL_FROM`. The sender name is **SecureVote** even when an existing mailbox on another domain supplies the SMTP account. The sender address should be an address the SMTP account is authorized to use.

With verification enabled, the account is created only after the code is accepted. Codes expire after 10 minutes, allow five attempts, and can be resent after one minute. Google registration continues to use Google's verified email assertion.
