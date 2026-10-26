# MailPilot

**English** | [Français](README.fr.md)

A **local** web application (127.0.0.1, single user, no login) to:

1. import a company database from any country (CSV / XLSX, up to 100,000 rows);
2. clean and verify email addresses (syntax, MX records, disposable domains, role addresses);
3. send a service offer **in controlled waves** (quotas, sending windows, random delays, circuit breakers);
4. automatically handle bounces, unsubscribes and replies received over IMAP.

The interface is available in **English and French**, emails are written in English or French depending on the
template, and the time zone is detected on first launch. Rules specific to Algeria (wilayas, phone numbers) are
available as options.

> **Laravel version**: the project uses **Laravel 12**. Every 11.x release is blocked by Composer because of unpatched
> security advisories (including a CRLF injection in the `email` validation rule).

---

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Starting the app](#starting-the-app)
- [First-time setup](#first-time-setup)
- [Languages and regional preferences](#languages-and-regional-preferences)
- [How it works](#how-it-works)
- [DNS setup for your sending domain (SPF, DKIM, DMARC)](#dns-setup-for-your-sending-domain-spf-dkim-dmarc)
- [Recommended warm-up](#recommended-warm-up)
- [Backups](#backups)
- [Using MySQL instead of SQLite](#using-mysql-instead-of-sqlite)
- [Tests](#tests)
- [Troubleshooting](#troubleshooting)
- [Code layout](#code-layout)
- [License](#license)

---

## Requirements

| Tool | Version |
|---|---|
| PHP | 8.3 (extensions: `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `zip`, `xml`, `gd`, `fileinfo`; `intl` recommended) |
| Composer | 2.x |
| Node.js / npm | Node 18 or later (only to build the interface) |

The PHP `imap` extension is **not required**: mailbox checks use `webklex/php-imap`, which talks to the server
directly.

## Installation

```bash
# 1. PHP dependencies
composer install

# 2. Environment file and encryption key
cp .env.example .env            # Windows: copy .env.example .env
php artisan key:generate
php artisan mailpilot:configure-env   # sets APP_TIMEZONE to your computer's time zone

# 3. SQLite database, tables and example templates (English and French)
touch database/database.sqlite  # Windows: type nul > database\database.sqlite
php artisan migrate --seed

# 4. Interface (Tailwind via Vite)
npm install
npm run build
```

`start.sh` / `start.bat` run steps 2 to 4 automatically when they detect a first installation. Only
`composer install` has to be run by hand. You can also set `APP_TIMEZONE` yourself in `.env` (e.g. `Europe/London`,
`America/New_York`, `Africa/Algiers`).

## Starting the app

| System | Command |
|---|---|
| macOS | double-click `MailPilot.command` in the Finder (or run `./start.sh` in Terminal); Ctrl+C or closing the window stops everything |
| Linux | `./start.sh` (Ctrl+C stops everything) |
| Windows | double-click `start.bat` (three windows open; close them to stop) |

The scripts run three processes side by side:

- `php artisan serve --host=127.0.0.1`: the interface, on <http://127.0.0.1:8000>, or on the next free port if 8000 is
  taken (set the starting port with the `MAILPILOT_PORT` environment variable);
- `php artisan queue:work --queue=sends,default`: background jobs (imports, verification, sending, IMAP). It is
  restarted automatically if it stops;
- `php artisan schedule:work`: the scheduler (sending every minute, mailbox checks every 15 minutes).

They also load `php/mailpilot.ini` (64 MB uploads, 512 MB memory) through `PHP_INI_SCAN_DIR`, without changing your PHP
installation.

> **All three processes are required.** Without the worker, imports stay "Pending"; without the scheduler, no
> campaign sends anything.

The application rejects any request that does not come from the local machine (`EnsureLocalRequest` middleware).

## First-time setup

1. **Settings**: enter the sender's company name, address and phone number. They appear in the legal footer added
   automatically to every email, next to the unsubscribe link. Check the **Regional preferences** too (see below).
2. **SMTP accounts**: add a sending account, then click "Test SMTP connection". Also fill in the **IMAP** mailbox and
   test it: it receives bounces, unsubscribes and replies, and its address is used for the unsubscribe link
   (`mailto`). Passwords are encrypted in the database with the `APP_KEY` key.
3. **Templates**: adapt one of the example templates (one in English, one in French). Variables work in both
   languages: `{{company}}` or `{{entreprise}}`, `{{sector}}` or `{{secteur}}`, `{{region}}` (or `{{wilaya}}`),
   `{{email}}`. The preview uses a real contact, and "Send a test" sends the result to the address of your choice.
4. **Imports**: choose a file, check the column mapping (guessed from the headers), then start the import. Email
   verification starts automatically when the import finishes.
5. **Campaigns**: the 3-step wizard sets the segment (with a live count of eligible contacts), the template and
   account, then the schedule and quotas.

## Languages and regional preferences

- **Interface**: **FR / EN** buttons at the bottom of the sidebar (English by default on a new installation). The
  choice is saved and also applies to messages produced in the background (verification reasons, campaign stops).
  Translations live in `lang/en.json`, with the French text as the key; a test fails if any text is missing.
- **Emails**: each template has its own **language** (English or French). It sets the language of the legal footer,
  the unsubscribe link and the default values of variables ("your company" / "votre entreprise").
- **Settings → Regional preferences**:
  - sending days suggested for a new campaign (Monday–Friday by default; Sunday–Thursday in Algeria, etc.);
  - **Algerian wilaya normalisation** on import (off by default): recognises the 58 wilayas, their codes
    ("16" → Alger) and their spelling variants. When off, the region is imported as is;
  - **restoring the leading 0** that Excel drops from 9-digit phone numbers (off by default; useful for Algeria,
    Morocco, Tunisia, France…).
- **Time zone**: `APP_TIMEZONE` in `.env`, detected on first launch. It is used for sending windows and for displaying
  dates. Preferably change it before your first sends: dates already stored are not converted.

## How it works

### Import and cleanup

- Files are read in chunks of 5,000 rows in the background (`WithChunkReading` + `ShouldQueue`). For an Excel
  workbook, only the first sheet is imported.
- The CSV delimiter (`;` `,` tab `|`) and encoding (UTF-8 or Windows-1252) are detected automatically.
- Emails are lowercased and trimmed. A cell containing several addresses (separated by `;` `,` `/`, a space or a line
  break) produces several contacts sharing the same company details.
- Obvious typos are fixed: `gmail,com`, `@gmail`, `gmial.com`, `hotmial.fr`…
- Duplicates are removed by email, both within the file and against the database. An email already in the database is
  not created again, but its empty fields (company name, sector, region…) are filled in.
- The region is imported as is. Optionally (Regional preferences), the 58 Algerian wilayas are normalised: "ALGER",
  "16", "16 - Alger", "Wilaya de Tizi-Ouzou", "Bejaia", "Bougie"… all become the official name.
- Optionally, the leading 0 that Excel drops from 9-digit phone numbers is restored.
- An address already on the blocklist is imported with the matching status (unsubscribed, bounced, excluded).
- The report shows rows read, contacts imported, rejections (missing or invalid email) and duplicates.

### Email verification

Contacts are processed in batches of 200:

| Check | Result |
|---|---|
| Syntax (`filter_var` + regular expression) | invalid |
| Disposable domain (`resources/data/disposable_domains.txt`, subdomains included) | invalid |
| Domain without an MX record | invalid |
| Role address (`noreply`, `no-reply`, `postmaster`, `abuse`, `mailer-daemon`) | risky |
| Otherwise | valid |

The DNS result for each domain is stored in `domain_cache`: **no new DNS lookup is made for a domain checked less than
30 days ago**.

### Eligible contacts

A campaign only targets contacts that are all of the following:

- verified as **valid**;
- **active**;
- **not** on the blocklist;
- **not contacted** in the last 60 days.

Sends are created when the campaign starts. Eligibility is checked again just before each send: a contact who
unsubscribed in the meantime is skipped.

### Sending engine

Every minute, the `mailpilot:dispatch` command processes each running campaign:

1. it checks the sending day and time, in the application's time zone (`APP_TIMEZONE`);
2. it works out how many sends are allowed: the smallest of the hourly and daily quotas of the campaign and of its
   SMTP account. The hourly quota is a rolling 60-minute window, and the account quota covers all of its campaigns
   together;
3. it queues the jobs, spacing them by a **random delay** between `min_delay` and `max_delay` seconds.

Each job sends **one email to a single recipient**:

- variables are rendered and the legal footer is added;
- `List-Unsubscribe` (mailto to the IMAP mailbox) and a dedicated `Message-ID` headers are set, the latter being kept
  to recognise replies;
- the result is logged in `campaign_sends`;
- `last_contacted_at` is updated.

A permanent rejection of the recipient (`550 User unknown`) is treated as a bounce: the contact becomes "Bounced" and
the address is added to the blocklist. A temporary error (4xx, connection) is retried, up to 3 attempts.

### No duplicate sends, safe restart

- **Lock**: when scheduled, each send gets a unique token. A job can only send if it succeeds in an atomic
  `scheduled → sending` update with that token. Running the same job twice, or a stale job, therefore sends nothing.
- **Restart**: jobs are stored in the database (`jobs`). After the app has been closed, jobs that run more than
  5 minutes late do not go out in a burst: they put their send back in the queue, where it is rescheduled with normal
  spacing. A lost job (no news for more than 15 minutes) is rescheduled with a new token, and the old one becomes
  ineffective.
- **Interrupted send**: a send cut off in the middle of an SMTP session is **never resent**, since there is no way to
  know whether the server accepted it. It is marked as failed with an explanation.

### Circuit breakers

A campaign is automatically "Stopped", with the reason displayed, when:

- the **bounce rate exceeds 5%**, after 50 sends;
- **10 SMTP failures happen in a row** (account blocked, password changed, server unavailable…).

After fixing the cause, the "Resume" button restarts the campaign. If the bounce rate is still above the threshold, it
is stopped again.

### Mailbox checks (every 15 minutes)

Messages are read **without being marked as read**: MailPilot remembers the last processed UID, so you still find your
prospects' replies in your usual email client. The first check covers the last 14 days.

| Message detected | Action |
|---|---|
| Bounce (DSN, `mailer-daemon`, `postmaster`, `X-Failed-Recipients`…) | contact "Bounced", address blocklisted, send marked as bounced, circuit breaker re-evaluated |
| Unsubscribe ("unsubscribe", "désinscription" or "STOP" in the subject or in the text written by the sender) | contact "Unsubscribed", address blocklisted |
| Reply to a campaign (`In-Reply-To` / `References`, or a sender already contacted) | `replied_at` set on the contact and the send |

Quoted text from the original email (`>` lines, "On … wrote:", "Le … a écrit :") is ignored. Without this, a simple
reply quoting the footer (which contains "STOP" and "Unsubscribe") would be taken for an unsubscribe request.

## DNS setup for your sending domain (SPF, DKIM, DMARC)

Without these three records, most of your emails will land in spam or be rejected. Set them up in the DNS zone of the
domain used in the sender address (for example `your-domain.com`).

### SPF: servers allowed to send for your domain

A single TXT record at the root of the domain:

```
your-domain.com.   TXT   "v=spf1 include:_spf.your-email-provider.com ~all"
```

- Replace the `include:` with the value given by your email provider (or `ip4:1.2.3.4` for a dedicated server).
- **Only one** SPF record per domain: merge the `include` entries if you use several services.
- Start with `~all` (soft fail), then switch to `-all` once everything is validated.

### DKIM: cryptographic signature of messages

Signing is done by **your SMTP server**, not by MailPilot. Enable DKIM in your provider's control panel, which gives you
a record to publish, for example:

```
mail._domainkey.your-domain.com.   TXT   "v=DKIM1; k=rsa; p=MIIBIjANBgkqh..."
```

### DMARC: policy when SPF/DKIM fail, and reports

```
_dmarc.your-domain.com.   TXT   "v=DMARC1; p=none; rua=mailto:dmarc@your-domain.com; adkim=r; aspf=r"
```

Start with `p=none` (monitoring) for 2 to 4 weeks, analyse the reports, then move to `p=quarantine`, and finally to
`p=reject`.

### Checks

- Send a test (the template's "Send a test" button) to <https://www.mail-tester.com> and aim for at least 9/10.
- In Gmail, "Show original" should read `SPF: PASS`, `DKIM: PASS` and `DMARC: PASS`.
- Make sure the SMTP server's IP is not blocklisted (<https://mxtoolbox.com/blacklists.aspx>) and has a consistent
  reverse DNS (PTR) record.
- Tip: use a **subdomain or a separate domain** for prospecting (e.g. `contact-your-domain.com`) to protect the
  reputation of your main domain.

## Recommended warm-up

A domain or address that suddenly sends a lot of email is quickly flagged as a spammer. Increase quotas **per SMTP
account** gradually, and only if the bounce rate stays below 2% and complaints are close to zero:

| Period | Daily quota per account | Suggested hourly quota |
|---|---|---|
| Week 1 | **30 / day** | 5 / h |
| Week 2 | 50 / day | 8 / h |
| Week 3 | 80 / day | 12 / h |
| Week 4 | 120 / day | 18 / h |
| Week 5 | 160 / day | 22 / h |
| Week 6 onwards | **200 / day maximum** | 25 / h |

Other good practices:

- keep 60 to 180 seconds between two emails;
- send during your recipients' office hours and working days;
- verify your database before each campaign;
- to go beyond 200/day, add accounts and domains rather than pushing a single account.

## Backups

All data lives in **`database/database.sqlite`**. The database runs in WAL mode, so `database.sqlite-wal` and
`database.sqlite-shm` files may sit next to it.

**Simple method**: stop MailPilot, then copy `database/database.sqlite` (plus the `-wal` and `-shm` files if they exist)
to your backup location.

**While running**, with the `sqlite3` tool:

```bash
sqlite3 database/database.sqlite ".backup 'backups/mailpilot-$(date +%Y-%m-%d).sqlite'"
```

**Back up the `.env` file too.** It contains `APP_KEY`, which encrypts the SMTP and IMAP passwords: without this key, a
database backup is still readable, but account passwords will have to be entered again.

To restore: stop the app, replace `database/database.sqlite` (delete any `-wal`/`-shm` files), then start it again.
The `php artisan migrate` run by the start script updates the schema if needed.

## Using MySQL instead of SQLite

In `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mailpilot
DB_USERNAME=root
DB_PASSWORD=
```

Then run `php artisan migrate --seed`. MySQL 8 or later is required (the tag list uses `JSON_TABLE`).

## Tests

```bash
php artisan test
```

The Pest tests (`tests/Feature`) cover in particular:

- **Hourly quotas** (`CampaignEngineTest`): campaign quota, account quota shared across campaigns, rolling window,
  daily quota, random spacing, sending windows.
- **Exclusion of removed contacts** (`CampaignEngineTest`): at activation (blocklist, not valid, inactive, contacted
  less than 60 days ago) and right before sending.
- **No duplicate sends after a restart** (`CampaignEngineTest`): jobs run several times, app closed for two hours with
  a send cut off mid-SMTP session, late jobs.
- **MX cache** (`EmailVerificationTest`): a single DNS lookup per domain, none for 30 days, refreshed afterwards.

They also cover imports (CSV, XLSX, multi-email cells, regions, optional wilayas), both languages, circuit breakers,
IMAP classification, templates, SMTP accounts, campaigns, the dashboard and settings.

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| Import stuck on "Pending" or "In progress" | The worker (`queue:work`) is not running. `start.sh` / `start.bat` restart it automatically if it stops; if you start it by hand, add `--memory=448` (otherwise it stops after 128 MB, as soon as a large Excel file is read). |
| Campaign "In progress" but nothing is sent | The scheduler (`schedule:work`) is not running, you are outside the sending days/hours, or today's quota is used up (see the campaign page). |
| "The file must not exceed…" / upload error | Start the app with `start.sh` / `start.bat`, which load `php/mailpilot.ini`. |
| Every email "Invalid – Domain has no MX record" | The machine has no DNS access (offline, firewall). |
| Sending hours are off by a few hours | `APP_TIMEZONE` in `.env` does not match your time zone. |
| SMTP passwords unreadable after a restore | The `.env` file (`APP_KEY`) was not restored with the database. |
| Slow imports on PHP 8.5 | `phpoffice/phpspreadsheet` 1.x emits one deprecation warning per cell on PHP 8.5. The target version is PHP 8.3, where this cost does not exist. |

Logs are in `storage/logs/laravel.log`.

## Code layout

```
app/
├── Console/Commands/DispatchCampaignSends.php   command scheduled every minute
├── Console/Commands/ConfigureEnvironment.php    time zone detected on first launch
├── Contracts/                                   DnsResolver, MailTransportFactory (swappable in tests)
├── Http/Middleware/                             EnsureLocalRequest (127.0.0.1 only), SetLocale
├── Imports/ContactsImport.php                   chunked maatwebsite/excel import
├── Jobs/                                        VerifyContactsJob, SendCampaignEmailJob, ProcessMailboxesJob
├── Livewire/                                    screens (dashboard, contacts, imports, templates, SMTP, campaigns, settings)
├── Models/                                      Contact, Import, Template, SmtpAccount, Campaign, CampaignSend, Suppression, DomainCache, Wilaya, Setting
├── Services/
│   ├── Campaigns/                               audience, quotas, sending windows, scheduling, circuit breakers, statistics
│   ├── Inbox/                                   mailbox checks and classification of incoming messages
│   ├── Mail/                                    template rendering, Symfony Mailer sending, IMAP client
│   ├── ContactImporter.php                      cleanup and deduplication on import
│   └── EmailVerifier.php                        verification + MX cache
└── Support/                                     EmailParser, WilayaNormalizer, ContactFilter, Locale
config/mailpilot.php                             business settings (60 days, 30 days, 5%, 10 failures…)
lang/en.json                                     English translations (key = French text)
resources/data/disposable_domains.txt            disposable domains (editable)
```

## License

MailPilot is free software released under the [MIT](LICENSE) license: you may use, modify and redistribute it freely,
including for commercial purposes, as long as you keep the copyright notice and the license text in copies of the
code.

Copyright (c) 2026 Habib Belayachi.

Use it in compliance with the laws of your country and of your recipients on email prospecting and personal data
protection (for example the GDPR in the European Union, CAN-SPAM in the United States, law 18-07 in Algeria): sender
identity, unsubscribe requests honoured, business addresses only. Rules differ between countries, especially on prior
consent: check them before sending.
