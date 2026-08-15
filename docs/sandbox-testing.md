# Local sandbox (port 2001)

Disposable Laravel instance for **backup, Danger Zone wipe, and guest restore** browser tests. It has its own SQLite database and `storage` tree so those flows never touch the live-like local app.

Pest still uses `phpunit.xml` (in-memory / testing disks). This sandbox is for **manual** wipe/restore only.

## Do not use the live-like instance

| | Sandbox | Live-like local |
|---|---|---|
| URL | `http://127.0.0.1:2001` | `http://tido.local` / `:2000` |
| Env file | `.env.sandbox` (`APP_ENV=sandbox`) | `.env` |
| Database | `database/sandbox.sqlite` | live SQLite |
| Storage | `storage/sandbox/` | `storage/` |
| Public files | `public/sandbox-storage` → `storage/sandbox/app/public` | `public/storage` |
| Queue | `sync` | `npm run dev:all` worker |
| Broadcast | `log` | Reverb |

Never open Profile → Danger Zone, Reset Data, or guest restore on port **2000** / `tido.local` when the goal is a destructive test.

Never run `php artisan migrate:fresh` without `--env=sandbox`. Never run `php artisan storage:link --env=sandbox` (that would retarget `public/storage` at the sandbox disk and break live public files).

## Isolation keys

`.env.sandbox` is gitignored. Copy `.env` to `.env.sandbox`, then set **only** these (keep a distinct `APP_KEY` for the life of this sandbox; restore MAC uses it):

```env
APP_NAME=tido-sandbox
APP_ENV=sandbox
APP_URL=http://127.0.0.1:2001
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/tido/database/sandbox.sqlite
QUEUE_CONNECTION=sync
BROADCAST_CONNECTION=log
CACHE_STORE=file
APP_STORAGE_PATH=storage/sandbox
FILESYSTEM_PUBLIC_URL=/sandbox-storage
```

Adjust `DB_DATABASE` to the absolute path of `database/sandbox.sqlite` on the machine.

`APP_NAME=tido-sandbox` also isolates the session cookie (`tido-sandbox-session`). `APP_STORAGE_PATH` is applied in `bootstrap/app.php` via `Application::afterLoadingEnvironment()` **after** `.env.sandbox` loads. Applying it earlier leaves `env('APP_STORAGE_PATH')` empty, disks stay on live `storage/app`, and a sandbox backup can embed live receipts and prior backup ZIPs.

## One-time setup

From the repo root:

```bash
# 1. Env (see isolation keys above)
cp .env .env.sandbox

# 2. Empty SQLite file
touch database/sandbox.sqlite

# 3. Storage tree
mkdir -p storage/sandbox/app/public storage/sandbox/app/private \
  storage/sandbox/framework/cache/data storage/sandbox/framework/sessions \
  storage/sandbox/framework/views storage/sandbox/logs

# 4. Public URL junction (Windows). Do not use artisan storage:link.
cmd //c "mklink /J public\\sandbox-storage storage\\sandbox\\app\\public"

# 5. Schema + seed (sandbox DB only)
php artisan migrate:fresh --seed --env=sandbox --no-interaction
```

If `public/sandbox-storage` already exists as a real directory, remove it before creating the junction.

Confirm isolation **before** creating a backup:

```bash
php artisan tinker --env=sandbox --execute="echo storage_path();"
php artisan tinker --execute="echo storage_path();"
```

Sandbox must print `.../storage/sandbox`. Live must print `.../storage` with no `sandbox` segment.

Seeded login: `admin@tido.local` / `password`.

## Boot

Leave `npm run dev:all` on port 2000 for the live-like app. In a **separate** terminal:

```bash
php artisan serve --env=sandbox --host=127.0.0.1 --port=2001
```

Health check: `http://127.0.0.1:2001/up` then `http://127.0.0.1:2001/admin/login`.

Panel CSS/JS come from the shared Vite build (`public/build`) or the live Vite server. The sandbox process does not need its own Vite or queue worker (`QUEUE_CONNECTION=sync`).

## Backup / wipe / guest restore flow

1. Sign in on **:2001** only.
2. Create one synthetic expense and a tiny receipt image (for example merchant **Sandbox Mart**, RM 12.80). Confirm the receipt opens from `/sandbox-storage/...`, not `/storage/...`.
3. Tools → Backups → Create backup. Download the ZIP (signed GET; Filament SPA mode excludes this URL). The archive should be small. If it is tens of megabytes, isolation failed — stop and re-check `storage_path()`.
4. Peek the ZIP: `database.sqlite`, `files/public/receipts/...`, `MANIFEST.json`, `MANIFEST.hmac`, `RESTORE_TOKEN.txt`. Copy the token somewhere private. Never paste a real token into docs, tests, chat, or logs.
5. Profile → Danger Zone → Delete account. Phrase: `CONFIRM DELETE ACCOUNT`, then the sandbox password.
6. On the sandbox login page, open **Restore Backup**. Upload the ZIP from step 3 and enter its token. Optional SEC-003 check: set a path-like client filename such as `..\..\CON.zip` when the browser permits it.
7. Sign in again. Confirm the synthetic expense returns and the receipt file opens.

Guest restore rejects compressed uploads over 50 MiB (`BACKUP_RESTORE_MAX_UPLOAD_KILOBYTES`). Uncompressed / entry / ratio limits are in [backups-and-danger-zone.md](backups-and-danger-zone.md).

A copied `database.sqlite` file is not a complete rollback: expense rows return without receipt bytes. Keep the ZIP and token outside the sandbox tree.

## Reset the sandbox

```bash
php artisan migrate:fresh --seed --env=sandbox --no-interaction
```

That wipes **only** `database/sandbox.sqlite`. Receipt files under `storage/sandbox/` may remain; delete that tree if a clean disk is required, then recreate the directories from setup step 3.

## Agent rules

1. Destructive backup/wipe/restore browser tests run on `:2001` with `--env=sandbox`. Not on `:2000` / `tido.local`.
2. Every Artisan command that mutates data or storage must include `--env=sandbox`.
3. Do not log or commit restore tokens, sandbox session cookies, or live receipt bytes.
4. If a sandbox backup contains `files/private/tido/` live backup ZIPs or live receipts, isolation is broken — delete that sandbox ZIP (`BackupService::delete` under `--env=sandbox` only) and fix `APP_STORAGE_PATH` before continuing.
5. Product behaviour remains [backups-and-danger-zone.md](backups-and-danger-zone.md). This file is only the isolated runtime.

## Related

- Backup catalog, tokens, guest restore, Danger Zone: [backups-and-danger-zone.md](backups-and-danger-zone.md)
- Security register: [security-audit.md](security-audit.md) (`SEC-003`–`SEC-006`)
- Gitignored paths: `.env.sandbox`, `database/sandbox.sqlite*`, `storage/sandbox/`, `public/sandbox-storage`
