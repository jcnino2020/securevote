# Private configuration

Credentials previously committed to this repository must be revoked or rotated at their providers. Removing them from current files does not remove them from Git history.

SecureVote reads `SV_DB_HOST`, `SV_DB_PORT`, `SV_DB_NAME`, `SV_DB_USER`, `SV_DB_PASS`, `SV_DB_SOCKET`, `GOOGLE_CLIENT_ID`, and `GOOGLE_CLIENT_SECRET` from environment variables. Alternatively set `SV_CONFIG_FILE` to an absolute PHP file outside the web root that returns an array with those keys. The Hostinger default is `securevote.private.php` in the domain directory, outside `public_html`.

Environment values take precedence, including deliberately empty values. Give private configuration files permission 0600 and never commit or upload them into a public directory. Missing credentials disable OAuth/SMTP without enabling demo authentication.
