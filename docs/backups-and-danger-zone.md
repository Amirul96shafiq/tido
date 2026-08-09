# Backups & Danger Zone

Cataloged ZIP backups, restore tokens, guest restore, and profile account deletion. Complements Spatie’s scheduled backup package with a Filament-managed catalog.

## Source of truth

| Layer | Path |
|-------|------|
| Model | `app/Models/Backup.php` (`backups` table; includes `restore_token_hash` and `edited_by`) |
| Service | `app/Services/BackupService.php` |
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

## Safe manual verification

Reset Data and Delete Account remove invoice records and their stored receipt files, so do not perform the zero-user guest-restore test against a local database that contains valuable data. Use a disposable local sandbox with its own SQLite database and `storage/app` directories.

The recommended browser flow is:

1. Create one synthetic invoice and receipt image in the sandbox.
2. Create and download a complete backup from **Backups**.
3. Use the sandbox Danger Zone to delete the sandbox account.
4. Open the guest **Restore Backup** modal from the sandbox login page.
5. Upload the backup, enter its token, and exercise a path-like client filename such as `..\\..\\CON.zip` when the browser permits it.
6. Confirm that the restore succeeds, the expense returns, and the receipt file opens.

A copied `database.sqlite` file alone is not a complete rollback because it restores invoice rows and their `image_path` values but not the receipt bytes. Preserve the complete backup archive and its token outside the sandbox. Never place a real restore token, real receipt content, or session data in documentation or logs.

## Agent rules

1. Do not log or commit plain restore tokens.
2. Validate guest restore with `GuestRestoreBackupRequest`; process via `BackupService` (no ad-hoc unzip in controllers).
3. Keep restore-backup modal tooltips Tippy-based with high `zIndex` — see [ui-tooltips.md](ui-tooltips.md).
4. Cover new backup/restore paths with Pest; fake storage / avoid real Spatie runs in unit tests where possible.
5. Nav: Backups live under Tools (bottom nav group), not Finances, Settings, or Integrations.
6. Keep backup recency on `updated_at`; `created_at` describes when the catalog row was first created and is not the table’s Edited At value.

## Related

- Spatie schedule / disks: `config/backup.php`, `docs/system-architecture.md` §7.4
- Modal blur: [ui-modal-overlay.md](ui-modal-overlay.md)
- Impersonal copy: [ui-copy-style.md](ui-copy-style.md)
