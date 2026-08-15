# Backups & Danger Zone

Cataloged ZIP backups, restore tokens, guest restore, and profile account deletion. Complements Spatie’s scheduled backup package with a Filament-managed catalog.

## Source of truth

| Layer | Path |
|-------|------|
| Model | `app/Models/Backup.php` (`backups` table; includes `restore_token_hash`, `content_sha256`, `manifest_hmac`, and `edited_by`) |
| Service | `app/Services/BackupService.php` |
| Manifest MAC | `app/Support/BackupManifest.php` |
| Notifications | `app/Services/BackupNotificationService.php` |
| Account wipe + final backup | `app/Services/AccountDangerZoneService.php` |
| Filament resource | `app/Filament/Resources/Backups/` |
| Profile Danger Zone | `app/Filament/Pages/Auth/EditProfile.php` |
| Guest restore UI | `resources/views/components/restore-backup-modal.blade.php` |
| Guest restore API | `app/Http/Controllers/GuestRestoreBackupController.php` |
| Authenticated download | `app/Http/Controllers/BackupDownloadController.php` |
| Scheduled catalog hook | `app/Listeners/RegisterScheduledBackupCatalog.php` (`BackupWasSuccessful`) |

## Concepts

- **Catalog row:** Each successful backup (manual, scheduled, or pre-delete) is recorded in `backups` with disk path metadata.
- **Edit audit:** Backup catalog rows record the latest authenticated editor in `edited_by`. The Filament table displays **Edited By** and **Edited At** from `updated_at`, with newest catalog changes first; system-generated backup updates show `System` when no user is authenticated.
- **Restore token:** Plain token is shown once (email / UI); only `restore_token_hash` is stored. Required for restore / guest restore.
- **Guest restore:** When no users exist (post Danger Zone wipe), auth menu exposes Restore Backup → Alpine modal → `GuestRestoreBackupRequest` validation → `BackupService` restore.
- **Danger Zone (Edit Profile):** Creates a final backup, returns the restore token to the user, then deletes account data. Single-tenant — wiping the only user leaves the app in guest-restore mode.

## Guest restore upload boundary

Guest restore accepts the uploaded ZIP only after the zero-user authorization gate, ZIP validation, and restore-token lookup succeed. `GuestRestoreBackupController` then creates a fresh per-request directory under `storage/app/backup-restore`, moves the upload to the server-controlled basename `backup.zip`, resolves the resulting path, verifies that it remains inside that directory, and passes the resolved path to `BackupService`.

The client-supplied filename is not a filesystem location. Path-like, reserved, or overwrite-oriented names must never be passed to `move()`, `Storage`, `File`, or the restore service. The original filename may be used for upload metadata and validation only.

## Database payload entries

`BackupService` restores the database only from allowlisted ZIP entries: exact `database.sqlite` (native SQLite backups) or a single `db-dumps/{safe}.sql` Spatie dump. Extraction never uses the archive entry name as a destination path; bytes are written to a server-controlled `database.sqlite` or `database.sql` under the restore temp directory, then path-checked before import.

## Restore ZIP resource limits

`BackupService::restoreFromZipPath` inspects the ZIP central directory (`ZipArchive::statIndex`) before any database payload extraction, database import, or application-file write. Guest compressed upload size remains `backup.backup.restore.max_upload_kilobytes` on `GuestRestoreBackupRequest`. Uncompressed limits apply to every restore path (guest upload and catalog restore):

| Config key (`backup.backup.restore`) | Default | Env |
| --- | --- | --- |
| `max_upload_kilobytes` | 51200 (50 MiB) | `BACKUP_RESTORE_MAX_UPLOAD_KILOBYTES` |
| `max_entries` | 5000 | `BACKUP_RESTORE_MAX_ENTRIES` |
| `max_uncompressed_bytes` | 209715200 (200 MiB) | `BACKUP_RESTORE_MAX_UNCOMPRESSED_BYTES` |
| `max_entry_bytes` | 52428800 (50 MiB) | `BACKUP_RESTORE_MAX_ENTRY_BYTES` |
| `max_compression_ratio` | 100 | `BACKUP_RESTORE_MAX_COMPRESSION_RATIO` |
| `max_duration_seconds` | 60 | `BACKUP_RESTORE_MAX_DURATION_SECONDS` |

Every central-directory entry counts toward those limits, including extra Spatie source paths that are not restored. Application-file writes remain `files/public/` → disk `public` and `files/private/` → disk `local`, and only `jpg`, `jpeg`, `png`, `gif`, `webp`, and `pdf` extensions are written. Archives that exceed a limit fail closed with a generic error before payload directories or storage writes.

## Restore integrity and one-time use

Guest and catalog restore bind the archive to the catalog row before any database or application-file write. After a ZIP is fully assembled, `BackupService` hashes restoreable entries (`database.sqlite` or `db-dumps/{safe}.sql`, plus `files/public|private` with the restore extensions), excluding `RESTORE_TOKEN.txt`, `MANIFEST.json`, and `MANIFEST.hmac`. It embeds `MANIFEST.json` and `MANIFEST.hmac` (HMAC-SHA256 over the canonical JSON using `APP_KEY`) and stores `content_sha256` and `manifest_hmac` on the catalog row.

Restore then:

1. Acquires an exclusive file-cache lock (`backup-restore`) so only one restore runs at a time. The file store is used so the lock survives a SQLite file replace.
2. Re-checks the restore token (guest path) and verifies the ZIP content hash against the catalog. When `manifest_hmac` is present, the embedded MAC must match.
3. Legacy rows with a null hash are backfilled from the on-disk catalog file when it exists; otherwise restore fails closed.
4. Snapshots the live SQLite file (when file-backed) and any application files the ZIP would overwrite.
5. Imports and restores files. On success, the guest token is consumed. On failure, the snapshot is restored and the token is left in place.

`issueRestoreToken` re-embeds the token and re-seals the manifest; content identity does not change because the token file is excluded from the hash.

## Safe manual verification

Reset Data and Delete Account remove expense records and their stored receipt files, so do not perform the zero-user guest-restore test against a local database that contains valuable data. Use a disposable local sandbox with its own SQLite database and `storage/app` directories.

The recommended browser flow is:

1. Create one synthetic expense and receipt image in the sandbox.
2. Create and download a complete backup from **Backups**.
3. Use the sandbox Danger Zone to delete the sandbox account.
4. Open the guest **Restore Backup** modal from the sandbox login page.
5. Upload the backup, enter its token, and exercise a path-like client filename such as `..\\..\\CON.zip` when the browser permits it.
6. Confirm that the restore succeeds, the expense returns, and the receipt file opens.

A copied `database.sqlite` file alone is not a complete rollback because it restores expense rows and their `image_path` values but not the receipt bytes. Preserve the complete backup archive and its token outside the sandbox. Never place a real restore token, real receipt content, or session data in documentation or logs.

## Agent rules

1. Do not log or commit plain restore tokens.
2. Validate guest restore with `GuestRestoreBackupRequest`; process via `BackupService` (no ad-hoc unzip in controllers).
3. Keep restore-backup modal tooltips Tippy-based with high `zIndex` — see [ui-tooltips.md](ui-tooltips.md).
4. Cover new backup/restore paths with Pest; fake storage / avoid real Spatie runs in unit tests where possible.
5. Nav: Backups live under Tools (bottom nav group), not Finances, Settings, or Integrations.
6. Keep backup recency on `updated_at`; `created_at` describes when the catalog row was first created and is not the table’s Edited At value.
7. Do not broaden database ZIP payload selection beyond the allowlisted entry names above; never pass archive entry path components into filesystem destinations.
8. Inspect restore ZIP central-directory limits before any database or application-file write; do not extract first and cap afterwards.
9. Verify catalog content hash and manifest MAC before restore writes; hold the exclusive restore lock; snapshot and roll back on failure; consume the guest token only after a successful import.

## Related

- Spatie schedule / disks: `config/backup.php`, `docs/system-architecture.md` §7.4
- Modal blur: [ui-modal-overlay.md](ui-modal-overlay.md)
- Impersonal copy: [ui-copy-style.md](ui-copy-style.md)
