# COMIUM SIS Full Audit — 20 July 2026

## Result

The active PHP application was audited across authentication, configuration, Admin, Supervisor, Agent, notifications, reporting, stock, visitor, upload, routing, JavaScript, and packaging areas. All 92 active PHP files pass parser-level syntax validation and the shared JavaScript passes Node syntax validation.

## Critical fixes completed

- Record-51 root-cause fix: `csrfField()` previously created a separate duplicate-submit token for every form. Each transaction row has four action forms, so around 51 records the 200-token cleanup removed the earlier Add Sale token. CSRF fields now contain only the CSRF token, while the shared footer supplies one page-level duplicate token to all POST forms. The number of displayed sales can no longer expire the Add Sale form.
- Final Agent duplicate fix: the shared one-time POST guard now records tokens that were genuinely consumed. It blocks only confirmed replays, while allowing an unknown/expired token to continue through normal CSRF validation. This prevents legitimate Agent sales from being rejected after a session refresh, deployment, token pruning, or a long-open page.
- Final 21 July correction: removed every obsolete `customer_sale` request-token field from both Agent and Supervisor sale pages. The live workflow now uses one CSRF token plus the central one-time POST guard, eliminating the remaining false "duplicate or expired" path.
- Standardized Sales Report, its summary tabs, and all Daily Services Report tables with striped rows, consistent borders and padding, sticky headers, themed hover states, responsive horizontal scrolling, and accessible captions.
- Fixed the Add Customer / Sale false rejection: the sale workflow was consuming a redundant second one-time token after the global duplicate guard had already consumed its token. Sale mutations now retain CSRF, global one-time submission, POST/redirect/GET, and browser double-click protection without rejecting valid current-page submissions.
- Added centralized CSRF enforcement to every authenticated Admin, Supervisor, and Agent POST page. The shared layout safely supplies tokens to legacy forms that previously lacked explicit fields.
- Replaced the broken `supervisor/export_report.php`, which contained Markdown instead of PHP, with a working outlet-scoped CSV exporter.
- Added the missing `admin/get_visitor_details.php` JSON endpoint required by the Visitor Log detail modal.
- Changed Service archive, restore, paid/free toggle, and permanent-delete actions from unsafe GET links to POST forms protected by CSRF tokens.
- Added CSRF protection to login, notification updates, announcement updates, Supervisor dashboard actions, and Admin Visitor Log deletion.
- Added strict session mode, HTTP-only cookies, SameSite cookies, and HTTPS-aware Secure cookies.
- Disabled browser display of PHP/database errors by default. Set `SIS_DEBUG=1` only for local debugging.
- Removed the hard-coded database password fallback and added `.env.example` with safe placeholders.
- Replaced raw database connection errors with a generic 503 response while retaining server-side logging.
- Added response security headers, disabled directory indexes, and blocked direct web access to SQL, environment, log, backup, example, and Markdown files.
- Removed unused `config4.php`, the obsolete theme backup, and the Apache default testing page.
- Removed published default-password instructions from the primary README.

## Validation completed

- Parsed 92 active PHP files after the final sale-token correction: 0 syntax errors.
- Checked `assets/js/sis-theme.js`: 0 JavaScript syntax errors.
- Checked PHP open/close tag structure: no invalid extra closing tags.
- Checked removed duplicate/test assets for active references: none found.
- Confirmed the new visitor endpoint uses authenticated Admin access, validated numeric IDs, prepared statements, bounded results, and JSON headers.
- Confirmed the Supervisor CSV exporter is restricted to the logged-in Supervisor's outlet and validates its date range.

## Deployment requirements

1. Configure `SIS_DB_HOST`, `SIS_DB_USER`, `SIS_DB_PASS`, and `SIS_DB_NAME` in the server environment.
2. Keep `SIS_DEBUG=0` in production.
3. Import `comium_gm.sql`, sign in through a trusted local/admin network, reset all imported user passwords immediately, and remove unused sample accounts.
4. Run **Admin > Management & System > Run Upgrade** once.
5. Deploy behind HTTPS so session cookies receive the Secure attribute.
6. Protect database backups separately; the included `.htaccess` blocks direct HTTP access but does not replace server-level access controls.

## Environment limitation

The audit workspace did not include a PHP runtime or MySQL server, so live database integration and browser workflow testing could not be executed here. Parser-level PHP validation was performed with an independent PHP parser. Before production, perform a staging smoke test against a copy of the real database.
