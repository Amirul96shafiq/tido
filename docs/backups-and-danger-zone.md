# Backups & Danger Zone

Cataloged ZIP backups, restore tokens, guest restore, and profile account deletion. Complements Spatie’s scheduled backup package with a Filament-managed catalog.

## Source of truth

| Layer | Path |
|-------|------|
| Model | `app/Models/Backup.php` (`backups` table; includes `restore_token_hash`) |
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
- **Restore token:** Plain token is shown once (email / UI); only `restore_token_hash` is stored. Required for restore / guest restore.
- **Guest restore:** When no users exist (post Danger Zone wipe), auth menu exposes Restore Backup → Alpine modal → `GuestRestoreBackupRequest` validation → `BackupService` restore.
- **Danger Zone (Edit Profile):** Creates a final backup, returns the restore token to the user, then deletes account data. Single-tenant — wiping the only user leaves the app in guest-restore mode.

## Agent rules

1. Do not log or commit plain restore tokens.
2. Validate guest restore with `GuestRestoreBackupRequest`; process via `BackupService` (no ad-hoc unzip in controllers).
3. Keep restore-backup modal tooltips Tippy-based with high `zIndex` — see [ui-tooltips.md](ui-tooltips.md).
4. Cover new backup/restore paths with Pest; fake storage / avoid real Spatie runs in unit tests where possible.
5. Nav: Backups live under Tools (bottom nav group), not Finances, Settings, or Integrations.

## Related

- Spatie schedule / disks: `config/backup.php`, `docs/system-architecture.md` §6.4
- Modal blur: [ui-modal-overlay.md](ui-modal-overlay.md)
- Impersonal copy: [ui-copy-style.md](ui-copy-style.md)
