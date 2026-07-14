<style>
[x-cloak] { display: none !important; }
</style>

@if (\App\Models\User::query()->doesntExist())
<div
    x-data="{
        show: false,
        submitting: false,
        showToken: false,
        token: '',
        fileName: '',
        maxBytes: {{ (int) config('backup.backup.restore.max_upload_kilobytes', 51200) * 1024 }},
        maxMegabytes: {{ (int) ceil(((int) config('backup.backup.restore.max_upload_kilobytes', 51200)) / 1024) }},
        csrf: @js(csrf_token()),
        endpoint: @js(route('restore-backup')),
        loginUrl: @js(url('/admin/login')),

        resetForm() {
            this.submitting = false;
            this.showToken = false;
            this.token = '';
            this.fileName = '';
            this.$refs.backupInput.value = '';
        },

        open() {
            this.resetForm();
            this.show = true;
        },

        close() {
            this.show = false;
            this.resetForm();
        },

        onFileChange(event) {
            const file = event.target.files?.[0] ?? null;

            if (! file) {
                this.fileName = '';
                return;
            }

            const extension = file.name.split('.').pop()?.toLowerCase();

            if (extension !== 'zip') {
                this.notify('danger', 'Only .zip backup files are allowed.');
                event.target.value = '';
                this.fileName = '';
                return;
            }

            if (file.size > this.maxBytes) {
                this.notify('danger', `The backup file may not be greater than ${this.maxMegabytes} MB.`);
                event.target.value = '';
                this.fileName = '';
                return;
            }

            this.fileName = file.name;
        },

        notify(status, message) {
            window.dispatchEvent(new CustomEvent('auth-toast', {
                detail: { status, message },
            }));
        },

        async submit() {
            if (this.submitting) {
                return;
            }

            const file = this.$refs.backupInput.files?.[0] ?? null;

            if (! file) {
                this.notify('danger', 'Choose a backup zip file to restore.');
                return;
            }

            if (! this.token.trim()) {
                this.notify('danger', 'Enter the restore token from your backup kit.');
                return;
            }

            this.submitting = true;

            const formData = new FormData();
            formData.append('backup', file);
            formData.append('token', this.token.trim());
            formData.append('_token', this.csrf);

            try {
                const response = await fetch(this.endpoint, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const data = await response.json().catch(() => ({}));

                if (! response.ok || ! data.success) {
                    let message = data.message ?? 'Restore failed. Try again.';

                    if (response.status === 403) {
                        message = data.message ?? 'Restore is unavailable.';
                    } else if (response.status === 422 && data.errors) {
                        const firstError = Object.values(data.errors).flat()[0];
                        message = firstError ?? message;
                    }

                    this.notify('danger', message);
                    this.submitting = false;
                    return;
                }

                this.notify('success', data.message ?? 'Backup restored. Please sign in.');
                this.close();

                setTimeout(() => {
                    window.location.href = data.redirect ?? this.loginUrl;
                }, 900);
            } catch (error) {
                this.notify('danger', 'Restore failed. Try again.');
                this.submitting = false;
            }
        },
    }"
    x-on:open-restore-backup-modal.window="open()"
    x-on:keydown.escape.window="if (show) { close() }"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-[99999] flex items-center justify-center p-4"
    style="z-index: 99999 !important;"
>
    <div
        class="absolute inset-0 bg-gray-950/50 dark:bg-gray-950/75 backdrop-blur-md transition-opacity"
        x-on:click="close()"
        aria-hidden="true"
    ></div>

    <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="restore-backup-heading"
        class="relative w-full max-w-lg mx-auto cursor-default flex flex-col rounded-xl bg-white dark:bg-gray-900 shadow-xl ring-1 ring-gray-950/5 dark:ring-white/10 pointer-events-auto overflow-hidden"
    >
        <div class="flex items-center justify-between px-6 pt-6 pb-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-full bg-primary-100 text-primary-600 dark:bg-primary-500/20 dark:text-primary-400">
                    <x-heroicon-o-arrow-path class="h-5 w-5" />
                </div>
                <div>
                    <h2 id="restore-backup-heading" class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Restore Backup
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Upload your recovery zip and enter its restore token.
                    </p>
                </div>
            </div>

            <button
                type="button"
                class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5 dark:hover:text-gray-200"
                aria-label="{{ __('filament::components/modal.actions.close.label') }}"
                x-tooltip="{
                    content: @js(__('filament::components/modal.actions.close.label')),
                    theme: $store.theme,
                }"
                x-on:click="close()"
            >
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </button>
        </div>

        <form class="px-6 py-5 space-y-5" @submit.prevent="submit()">
            <div class="space-y-2">
                <label for="restore-backup-file" class="text-sm font-medium text-gray-950 dark:text-white">
                    Backup file
                </label>
                <input
                    id="restore-backup-file"
                    x-ref="backupInput"
                    type="file"
                    accept=".zip,application/zip"
                    class="block w-full text-sm text-gray-700 dark:text-gray-200 file:me-4 file:rounded-lg file:border-0 file:bg-primary-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-primary-500"
                    x-on:change="onFileChange($event)"
                >
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    .zip only · max {{ (int) ceil(((int) config('backup.backup.restore.max_upload_kilobytes', 51200)) / 1024) }} MB · includes database and uploaded files
                </p>
                <p x-show="fileName" x-text="fileName" class="text-sm text-gray-700 dark:text-gray-300"></p>
            </div>

            <div class="space-y-2">
                <label for="restore-backup-token" class="text-sm font-medium text-gray-950 dark:text-white">
                    Restore token
                </label>
                <div class="relative">
                    <input
                        id="restore-backup-token"
                        x-model="token"
                        :type="showToken ? 'text' : 'password'"
                        autocomplete="off"
                        class="w-full rounded-lg border-none bg-white px-3 py-2 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:placeholder:text-gray-500 dark:focus:ring-primary-500"
                        placeholder="Paste token from RESTORE_TOKEN.txt"
                    >
                    <button
                        type="button"
                        class="absolute inset-y-0 end-0 flex items-center px-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                        x-on:click="showToken = ! showToken"
                    >
                        <x-heroicon-o-eye x-show="! showToken" class="h-5 w-5" />
                        <x-heroicon-o-eye-slash x-show="showToken" class="h-5 w-5" />
                    </button>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Open the zip and copy the contents of RESTORE_TOKEN.txt
                </p>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <button
                    type="button"
                    class="fi-btn fi-color-gray relative inline-grid grid-flow-col items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold outline-none transition duration-75 bg-white text-gray-700 shadow-sm ring-1 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/20 dark:hover:bg-white/10"
                    x-on:click="close()"
                    :disabled="submitting"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    class="fi-btn fi-color-danger relative inline-grid grid-flow-col items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold outline-none transition duration-75 bg-danger-600 text-white hover:bg-danger-500 focus-visible:ring-2 focus-visible:ring-danger-600/50 disabled:opacity-70"
                    :disabled="submitting"
                >
                    <span x-show="! submitting">Restore backup</span>
                    <span x-show="submitting">Restoring…</span>
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Filament-style top-right toast host for guest auth pages --}}
<div
    x-data="{
        toasts: [],
        push(detail) {
            const id = Date.now() + Math.random();
            this.toasts.push({
                id,
                status: detail.status ?? 'danger',
                message: detail.message ?? '',
            });
            setTimeout(() => {
                this.toasts = this.toasts.filter((toast) => toast.id !== id);
            }, 4500);
        },
    }"
    x-on:auth-toast.window="push($event.detail)"
    class="pointer-events-none fixed inset-x-0 top-0 z-[100000] flex flex-col items-end gap-3 p-4 sm:p-6"
    style="z-index: 100000 !important;"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            class="pointer-events-auto w-full max-w-sm overflow-hidden rounded-xl bg-white shadow-lg ring-1 dark:bg-gray-900"
            :class="toast.status === 'success'
                ? 'ring-success-600/20 dark:ring-success-400/30'
                : 'ring-danger-600/20 dark:ring-danger-400/30'"
        >
            <div class="flex gap-3 p-4">
                <div
                    class="mt-0.5"
                    :class="toast.status === 'success' ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400'"
                >
                    <x-heroicon-o-check-circle x-show="toast.status === 'success'" class="h-5 w-5" />
                    <x-heroicon-o-exclamation-circle x-show="toast.status !== 'success'" class="h-5 w-5" />
                </div>
                <div class="fi-no-notification-text grid flex-1 gap-y-1">
                    <h3
                        class="fi-no-notification-title text-sm font-medium text-gray-950 dark:text-white"
                        x-text="toast.status === 'success' ? 'Success' : 'Error'"
                    ></h3>
                    <div class="fi-no-notification-body overflow-hidden break-words text-sm text-gray-500 dark:text-gray-400" x-text="toast.message"></div>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
    window.showRestoreBackupModal = function () {
        window.dispatchEvent(new CustomEvent('open-restore-backup-modal'));
    };
</script>
@endif
