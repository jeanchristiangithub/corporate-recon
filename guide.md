# AutoRecon Developer Guide

This guide explains how the project works end-to-end for a new developer.

> Scope note: this document focuses on **first-party application code** in `src/` and `recontool/`. Third-party dependencies in `vendor/` are treated as libraries.

---

## 1) What this project is

AutoRecon is a PHP web app (served in XAMPP) with:

- A login/index page and authenticated home page.
- Upload/extract/insert pipelines for MBTC **Web Data** and **Partner Data** Excel files.
- A reconciliation/test module that compares two uploaded files and shows matched vs unmatched rows.
- A calendar-style MBTC recon status board based on database data.
- Debug/fetch-test modals for troubleshooting extraction and comparison.
- A separate `recontool/` Node utility for local Office-file decryption tests.

---

## 2) Project structure and responsibilities

## 2.1 Entry/bootstrap

- `/.htaccess`
  - Routes root `/` to `src/pages/index/index.php`.
  - Adds `/home` fallback to `src/pages/home/home.php`.
- `src/config/session.php`
  - `bootSecureSession()` configures secure session cookie flags and starts session.
- `src/config/csrf.php`
  - `csrfToken()`, `csrfField()`, `verifyCsrfOrFail()` for form/API CSRF handling.
- `src/config/auth.php`
  - `isAuthenticated()`, `currentUser()` session helpers.
- `src/config/middleware.php`
  - `requireAuth()`: redirects to index if no session user.
  - `requirePublicRoleOrShowConstruction()`: sets a role-based “under construction” modal message for non-public roles.
- `src/config/env.php`
  - Lightweight `.env` reader via `env(key, default)`.

## 2.2 Authentication and password lifecycle

- `src/config/login-handler.php`
  - Handles login POST.
  - Loads user via `UserController::findByUsername()`.
  - Verifies password hash and sets `$_SESSION['user']`.
  - Checks latest user log via `latestUserLogByIdNumber()` to decide forced password reset.
  - Redirect rules:
    - Force reset → index page (reset modal opens).
    - Non-public role → index page (construction modal opens).
    - Public role + valid login → home page.
- `src/config/change-pass-handler.php`
  - POST endpoint for password update (`action=change_password`).
  - Uses `ChangePassController::changePassword()` then redirects to home on success.
- `src/config/logout-handler.php`
  - Destroys session and redirects to index.
- `src/controllers/usercontroller.php`
  - DB access for user lookup, latest user log, password/log updates.
- `src/controllers/change-pass-controller.php`
  - Enforces min 8 chars and writes password hash + log state update.

## 2.3 Home/page composition

- `src/pages/index/index.php`
  - Public landing page + login modal + forced-reset modal.
- `src/pages/home/home.php`
  - Auth-protected shell with sidebar navigation and sections:
    - `home-section.php`
    - `webdata-section.php`
    - `partnerdata-section.php`
    - `recon-section.php`

## 2.4 MBTC ingest/recon backends

- `src/controllers/excelcontrol/mbtc/mbtc-webdata.php`
  - Extracts MBTC Web Data workbook rows (required columns, header detection, date parsing).
- `src/controllers/excelcontrol/mbtc/mbtc-partnerdata.php`
  - Extracts MBTC Partner Data rows + cover period date normalization.
- `src/controllers/excelcontrol/mbtc/mbtc-helper.php`
  - Shared normalization utilities (amount, date parsing, duplicate-pair building).
- `src/controllers/excelcontrol/mbtc/mbtc-insert-lib.php`
  - `MbtcInsert` class; transactional insert into:
    - `mbtc_web_data`
    - `mbtc_partner_data`
- `src/controllers/excelcontrol/mbtc/mbtc-insert.php`
  - JSON action router for duplicate check/delete/insert for both web and partner pipelines.
- `src/controllers/excelcontrol/mbtc/mbtc-normalize-check.php`
  - Normalizes incoming pairs/payloads and returns duplicate hits.
- `src/controllers/recon/mbtc-recon.php`
  - Produces per-day month summary (status colors, mismatches, duplicates, optional detail rows).
- `src/controllers/excelcontrol/mbtc/mbtc-viewer.php`
  - Returns HTML table view for payload rows (used in modal viewers).

## 2.5 Generic reconciliation test backend

- `src/controllers/excelcontrol/test-controller.php`
  - Main compare endpoint for “Recon Tool” card flow and fetch-test modals.
  - Handles:
    - Upload validation.
    - CSV/XLSX parsing, legacy format support via PhpSpreadsheet.
    - Required-header extraction.
    - Single-file fetch-test mode or full two-file compare mode.
    - Duplicate-submission guard using session hash.
    - Session persistence of recent payloads for restore after refresh.
- `src/controllers/excelcontrol/clearsection-controller.php`
  - Clears compare session payloads (JSON API with CSRF check).
- `src/controllers/excelcontrol/clear-recent.php`
  - Clears recent compare hashes/payload markers; JSON-safe error handling.

## 2.6 UI modals/components with behavior

- Login/reset:
  - `src/modals/login-modal/login-modal.php`
  - `src/modals/password-modal/newlogin-reset-pass.php`
- Compare result/debug:
  - `src/modals/comparisonresult/view-result-modal.php`
  - `src/modals/debug/error-debug-modal.php`
- Shared data modal fragments:
  - `src/modals/data-modals/fetch-modal.php`
  - `src/modals/data-modals/check-insert-modal.php`
- Fetch-test modals:
  - `src/modals/fetch-test/partnerfetch.php`
  - `src/modals/fetch-test/webfetch.php`
- MBTC day details modal:
  - `src/modals/mbtc-view/mbtc-recon-view-modal.php`

## 2.7 `recontool/` utility

- `recontool/decrypt-server.js`
  - Local Express server: `POST /decrypt`, `GET /health`.
  - Uses `msoffcrypto-tool` CLI fallback chain.
- `recontool/sample.html` + `sample.js`
  - Browser test page for loading/decrypting spreadsheet content.

---

## 3) End-to-end user flow (click-by-click)

## Flow A: User login and landing routing

1. User opens root URL.
   - Server routes via `.htaccess` to `src/pages/index/index.php`.
2. User clicks `Login` or `Get Started`.
   - `src/pages/index/index.js` opens `#loginModal`.
3. User submits username/password form.
   - Form posts to `src/config/login-handler.php` with CSRF token.
4. Server validates credentials.
   - `UserController::findByUsername()` + `password_verify()`.
5. Server decides next destination:
   - Needs reset → `$_SESSION['force_password_reset']=true` and redirect index.
   - Non-public role → set `construction_modal`, redirect index.
   - Public role → redirect home.
6. If forced reset:
   - `newlogin-reset-pass.php` modal opens on index.
   - Submit posts to `src/config/change-pass-handler.php`.
   - `ChangePassController::changePassword()` updates password+log.
   - Redirect to home.

## Flow B: Home shell + section switching

1. `src/pages/home/home.php` runs:
   - `bootSecureSession()`, `requireAuth()`, `requirePublicRoleOrShowConstruction()`.
2. Sidebar links (`Home`, `ML Web Data`, `Partner Data`, `Recon Tool`) toggle section visibility.
3. Header logout link opens confirmation modal in `header.php` script.
4. Confirm logout navigates to `src/config/logout-handler.php`.

## Flow C: MBTC Web Data upload/insert

1. User opens `ML Web Data` section (`webdata-section.php`).
2. Selects company MBTC and drops one/more Excel files.
3. For each file, client posts file to:
   - `src/controllers/excelcontrol/mbtc/mbtc-webdata.php`.
4. Extracted payloads are shown as cards (with viewer/delete actions).
5. User clicks `Upload`.
6. Client calls `mbtc-insert.php` actions in sequence:
   - `check` (duplicate detection by `ccref_no + date_claimed`)
   - optional `delete` (if user confirms overwrite)
   - `insert_web` (transactional insert via `MbtcInsert::insertWebData()`)
7. On success, page reloads to reflect new persisted state.

## Flow D: MBTC Partner Data upload/insert

1. User opens `Partner Data` section (`partnerdata-section.php`).
2. Selects company MBTC and uploads files.
3. For each file, client posts to:
   - `src/controllers/excelcontrol/mbtc/mbtc-partnerdata.php`.
4. On upload action, client calls `mbtc-insert.php` actions:
   - `check_partner` (duplicate check by `reference_no + date`)
   - optional `delete_partner`
   - `insert_partner` via `MbtcInsert::insertPartnerData()`.
5. Success → UI card update + reload.

## Flow E: Calendar recon board (Home section)

1. User selects `Company=MBTC`, month, year in `home-section.php`.
2. Client fetches:
   - `src/controllers/recon/mbtc-recon.php?month=..&year=..`
3. Server computes day status (`white`, `green`, `red`, `yellow`) from db aggregates.
4. UI renders day cards + summary totals.
5. User clicks a day:
   - client optionally requests detail with `detail=1&day=...`.
   - opens `mbtc-recon-view-modal` and renders row-level partner/web data, filter/search.
6. Right-click day card opens context menu:
   - `View Partner Data` or `View Web Data`.
   - fetches `mbtc-viewer.php` HTML viewer for selected side.

## Flow F: Recon Tool two-file compare card

1. User navigates to `Recon Tool` section (`recon-section.php`).
2. A blank card is auto-created (date label from month selector).
3. User drops Partner file and Web file.
4. When both exist, client calls:
   - `src/controllers/excelcontrol/test-controller.php` with CSRF + mode + both files.
5. Backend parses and compares:
   - required header mapping for both datasets.
   - row match criteria:
     - `referenceNo` ↔ `ccrefNo` (case-insensitive)
     - partner `php` == web `amount`
     - partner `inPhp` == web `ctp`
6. Result:
   - card state becomes success/fail.
   - click card header opens comparison modal (`view-result-modal.php`).
7. If failure/error:
   - debug modal shows raw payload/json.
   - “Compare Again” clears recent hash (`clear-recent.php`) then retries.

## Flow G: Fetch-test modals (single-file extraction)

1. User clicks fetch icons in Recon section header.
2. Opens `partnerfetch` or `webfetch` modal.
3. Uploads one file; request goes to `test-controller.php` with only one file.
4. Backend returns extracted rows in a one-sided row model.
5. Modal renders rows + search, useful for troubleshooting headers/parse.

---

## 4) Core backend algorithms and outputs

## 4.1 `test-controller.php` pipeline

Major helper functions:

- `normalizeHeader()`
  - Uppercases, strips BOM/NBSP, normalizes spaces/control chars.
- `parseCsvRows()` / `parseXlsxRows()` / legacy parse branch via PhpSpreadsheet.
- `extractByRequiredHeaders(rows, requiredHeaders)`
  - Finds header row by searching for all required labels.
  - Returns:
    - `ok`
    - `records`
    - `headerRow`
    - `normalizedHeaderRow`
    - `map`
    - `headerIndex`
- `toNumber()`
  - Comma/space cleanup to float conversion.

Compare output JSON shape (two-file mode):

- `success`
- `allMatched`
- `matchedCount` / `unmatchedCount`
- `partners_count` / `web_count`
- `rows[]` where each row has:
  - `partners` subset (`referenceNo`, `php`, `usd`, `inPhp`)
  - `web` subset (`ccrefNo`, `amount`, `ctp`)
  - `match` flags (`referenceNo`, `php`, `inPhp`)
  - `all`
- `parsedHeaders`
- optional `debug`

## 4.2 MBTC ingest normalization

From `mbtc-helper.php`:

- `mbtc_normalize_amount()` / `mbtc_partner_normalize_currency()`
  - Handles commas, decimal separators, parentheses-negatives.
- `mbtc_parse_date_claimed()`
  - Supports Excel serial dates and string dates.
- Pair builders (`mbtc_build_pairs_from_payloads`, partner equivalent)
  - Generate duplicate-check keys from extracted rows.

From `mbtc-insert-lib.php`:

- `insertWebData()` inserts rows into `mbtc_web_data` with normalized date/amount.
- `insertPartnerData()` inserts rows into `mbtc_partner_data` with normalized date/time/currency.
- Both run inside DB transactions and return `{success, inserted}`.

## 4.3 MBTC month recon status generation

`mbtc-recon.php` per-day logic:

1. Query partner aggregates grouped by reference_no.
2. Query web aggregates grouped by ccref_no.
3. Normalize keys uppercase/trim.
4. Detect duplicates (`cnt > 1`) → `yellow` precedence.
5. Compare overlaps for amount/commission mismatch and missing refs.
6. Status decision:
   - `green`: matched/reconciled
   - `red`: mismatch or missing refs
   - `yellow`: duplicates
   - `white`: no usable data for day
7. Optional detail mode returns row-level joined dataset and counts.

Output: `{ success:true, days:[ ...dayPayload ] }`.

---

## 5) Frontend-to-backend action map

- Login submit
  - UI: `src/modals/login-modal/login-modal.php`
  - Endpoint: `src/config/login-handler.php`
  - Data source: `UserController`

- Change password submit
  - UI: `src/modals/password-modal/newlogin-reset-pass.php`
  - Endpoint: `src/config/change-pass-handler.php`
  - Data source: `ChangePassController` → `UserController`

- Recon compare (two-file)
  - UI: `src/pages/home/components/recon-section.php`
  - Endpoint: `src/controllers/excelcontrol/test-controller.php`
  - Result UI: `src/modals/comparisonresult/view-result-modal.php`

- Recon clear sessions
  - UI button in `recon-section.php`
  - Endpoint: `src/controllers/excelcontrol/clearsection-controller.php`

- Debug compare-again
  - UI: `src/modals/debug/error-debug-modal.php`
  - Endpoint: `src/controllers/excelcontrol/clear-recent.php`
  - Callback: `window.recon.retryComparison(batchId)`

- Fetch-test partner/web modals
  - UI: `src/modals/fetch-test/partnerfetch.php`, `webfetch.php`
  - Endpoint: `src/controllers/excelcontrol/test-controller.php` (single-file mode)

- Web data extraction
  - UI: `src/pages/home/components/webdata-section.php`
  - Endpoint: `src/controllers/excelcontrol/mbtc/mbtc-webdata.php`

- Partner data extraction
  - UI: `src/pages/home/components/partnerdata-section.php`
  - Endpoint: `src/controllers/excelcontrol/mbtc/mbtc-partnerdata.php`

- Duplicate check/delete/insert
  - UI: web/partner section scripts
  - Endpoint: `src/controllers/excelcontrol/mbtc/mbtc-insert.php`
  - Insert implementation: `mbtc-insert-lib.php`

- Home MBTC recon board
  - UI: `src/pages/home/components/home-section.php`
  - Endpoint: `src/controllers/recon/mbtc-recon.php`
  - Row viewer endpoint: `src/controllers/excelcontrol/mbtc/mbtc-viewer.php`

---

## 6) Session model and state dependencies

Key session keys used:

- `$_SESSION['user']` → authenticated user identity.
- `$_SESSION['csrf_token']` → CSRF protection.
- `$_SESSION['login_error']` → shown in login modal.
- `$_SESSION['force_password_reset']` / `$_SESSION['password_error']`.
- `$_SESSION['construction_modal']` → non-public role notice.
- `$_SESSION['excel_compare_recent']` → duplicate submission hashes.
- `$_SESSION['excel_compare_recent_payloads']` → restore recon cards after refresh.
- `$_SESSION['excel_compare_recent_cleared_at']` → grace period after clear.

---

## 7) Data and database dependencies

DBs from env:

- User/auth DB (`USERDB_NAME`, typically `userdb`):
  - `users`
  - `userlogs`
- File recon DB (`FILERECONDB_NAME`, typically `filerecondb`):
  - `mbtc_web_data`
  - `mbtc_partner_data`

The app assumes these schemas and columns exist exactly as queried in controllers.

---

## 8) Error paths and edge cases (important)

- Missing/invalid CSRF:
  - `verifyCsrfOrFail()` returns HTTP 419 with message or JSON variant in clear endpoint.
- Wrong HTTP method:
  - many endpoints return 405.
- Wrong file extension:
  - explicit rejection in upload endpoints.
- Missing required headers:
  - extraction fails with “Missing required header” diagnostics.
- Legacy Excel without PhpSpreadsheet support:
  - `test-controller.php` returns explicit message for `.xls/.xlsm/.xlsb/.ods/.xlx` parse inability.
- Duplicate submission in compare flow:
  - blocked via hash in `excel_compare_recent`, with one auto clear+retry attempt.
- Duplicate MBTC insert candidates:
  - UI asks overwrite, then calls delete action before insert.
- Non-public user role:
  - user remains on index and sees construction modal.
- Forced password reset:
  - user cannot proceed to home until password successfully updated.

---

## 9) How modules depend on each other

- Pages depend on config bootstrap (`session`, `csrf`, `auth`, `middleware`).
- Form POST handlers depend on controllers (`UserController`, `ChangePassController`).
- Home section scripts depend on specific backend JSON schemas.
- `mbtc-insert.php` depends on helper normalization + insert library transactions.
- MBTC recon UI depends on `mbtc-recon.php` status payload shape.
- Compare result/debug modals depend on `test-controller.php` response format.
- Restore-on-refresh in recon section depends on session payload persistence by `test-controller.php`.

---

## 10) `recontool/` subsystem (separate from main app)

Purpose:

- Local browser utility to test reading/decrypting office files.
- Uses `xlsx` in browser; when needed, calls local Node decrypt service.

Flow:

1. Start server with `start-decrypt-server.bat` or `.ps1`.
2. `decrypt-server.js` listens on `http://localhost:3000`.
3. `sample.js` can call `POST /decrypt` with file+password.
4. Server tries CLI commands (`msoffcrypto-tool`, `python -m msoffcrypto.cli`, `py -m ...`) and returns base64 decrypted workbook bytes.

---

## 11) Practical navigation tips for new developers

Start reading in this order:

1. `src/pages/index/index.php` + login/reset modals.
2. `src/config/login-handler.php`, `usercontroller.php`, `change-pass-handler.php`.
3. `src/pages/home/home.php` to see all included sections.
4. `src/pages/home/components/recon-section.php` + `test-controller.php`.
5. `src/pages/home/components/webdata-section.php` + MBTC web extract/insert files.
6. `src/pages/home/components/partnerdata-section.php` + MBTC partner extract/insert files.
7. `src/pages/home/components/home-section.php` + `recon/mbtc-recon.php`.

---

## 12) Quick checklist when adding a new partner/company

1. Add company option to selectors in home/web/partner sections.
2. Implement extractor endpoint(s) mirroring MBTC style:
   - `<company>/<company>-webdata.php`
   - `<company>/<company>-partnerdata.php`
3. Implement insert/check/delete action handling (or new action router).
4. Ensure viewer endpoint can render your column set.
5. Update recon day endpoint for your company, or fallback rules.
6. Keep JSON response shapes stable for existing frontend scripts.

---

If you want, I can generate a second document (`api-reference.md`) containing a strict endpoint contract table (method, input fields, response schema, error codes) extracted from this codebase.