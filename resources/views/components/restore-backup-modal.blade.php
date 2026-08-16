@if (\App\Models\User::query()->doesntExist())
    @php
        $maxUploadKilobytes = (int) config('backup.backup.restore.max_upload_kilobytes', 51200);
        $maxBytes = $maxUploadKilobytes * 1024;
        $maxMegabytes = (int) ceil($maxUploadKilobytes / 1024);
    @endphp

    <div
        class="fi-restore-backup"
        x-data="{
            submitting: false,
            showToken: false,
            token: '',
            fileName: '',
            feedbackStatus: 'danger',
            feedbackMessage: '',
            maxBytes: {{ $maxBytes }},
            maxMegabytes: {{ $maxMegabytes }},
            csrf: @js(csrf_token()),
            endpoint: @js(route('restore-backup')),
            loginUrl: @js(url('/admin/login')),

            fileInput() {
                return document.getElementById('restore-backup-file');
            },

            resetForm() {
                this.submitting = false;
                this.showToken = false;
                this.token = '';
                this.fileName = '';
                this.feedbackStatus = 'danger';
                this.feedbackMessage = '';

                const input = this.fileInput();

                if (input) {
                    input.value = '';
                }
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
                this.feedbackMessage = '';
            },

            notify(status, message) {
                this.feedbackStatus = status;
                this.feedbackMessage = message;
            },

            async submit() {
                if (this.submitting) {
                    return;
                }

                const file = this.fileInput()?.files?.[0] ?? null;

                if (! file) {
                    this.notify('danger', 'Choose a backup zip file to restore.');
                    return;
                }

                if (! this.token.trim()) {
                    this.notify('danger', 'Enter the recovery token from the backup kit.');
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
                        } else if (response.status === 429) {
                            message = data.message ?? 'Too many restore attempts. Try again later.';
                        } else if (response.status === 422 && data.errors) {
                            const firstError = Object.values(data.errors).flat()[0];
                            message = firstError ?? message;
                        }

                        this.notify('danger', message);
                        this.submitting = false;
                        return;
                    }

                    this.notify('success', data.message ?? 'Backup restored. Please sign in.');

                    setTimeout(() => {
                        window.location.href = data.redirect ?? this.loginUrl;
                    }, 900);
                } catch (error) {
                    this.notify('danger', 'Restore failed. Try again.');
                    this.submitting = false;
                }
            },
        }"
        x-on:open-modal.window="if ($event.detail.id === 'restore-backup') resetForm()"
        x-on:close-modal.window="if ($event.detail.id === 'restore-backup') resetForm()"
    >
        <x-filament::modal
            id="restore-backup"
            heading="Restore Backup"
            icon="heroicon-o-arrow-path"
            icon-color="danger"
            width="md"
            close-button
            sticky-header
            sticky-footer
            teleport="body"
            footer-actions-alignment="end"
            class="fi-restore-backup"
        >
            <form
                id="restore-backup-form"
                class="grid gap-6"
                x-on:submit.prevent="submit()"
            >
                <div data-field-wrapper class="fi-fo-field">
                    <div class="fi-fo-field-label-col">
                        <div class="fi-fo-field-label-ctn">
                            <label for="restore-backup-file" class="fi-fo-field-label">
                                <span class="fi-fo-field-label-content">
                                    Backup file<sup class="fi-fo-field-label-required-mark">*</sup>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="fi-fo-field-content-col">
                        <input
                            id="restore-backup-file"
                            class="hidden"
                            type="file"
                            accept=".zip,application/zip"
                            x-on:change="onFileChange($event)"
                        />

                        <x-filament::input.wrapper
                            inline-prefix
                            inline-suffix
                            prefix-icon="heroicon-o-paper-clip"
                            class="cursor-pointer"
                            x-on:click="fileInput()?.click()"
                        >
                            <x-filament::input
                                type="text"
                                readonly
                                tabindex="0"
                                inline-prefix
                                inline-suffix
                                placeholder="Choose a .zip backup"
                                class="cursor-pointer"
                                x-bind:value="fileName"
                                x-on:keydown.enter.prevent="fileInput()?.click()"
                            />

                            <x-slot name="suffix">
                                <x-filament::icon-button
                                    type="button"
                                    color="gray"
                                    size="sm"
                                    icon="heroicon-o-folder-open"
                                    label="Choose file"
                                    tooltip="Choose file"
                                    x-on:click.stop="fileInput()?.click()"
                                />
                            </x-slot>
                        </x-filament::input.wrapper>

                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            .zip only · max {{ $maxMegabytes }} MB · includes database and uploaded files
                        </p>
                    </div>
                </div>

                <div data-field-wrapper class="fi-fo-field">
                    <div class="fi-fo-field-label-col">
                        <div class="fi-fo-field-label-ctn">
                            <label for="restore-backup-token" class="fi-fo-field-label">
                                <span class="fi-fo-field-label-content">
                                    Recovery token<sup class="fi-fo-field-label-required-mark">*</sup>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="fi-fo-field-content-col">
                        <x-filament::input.wrapper
                            inline-prefix
                            inline-suffix
                            prefix-icon="heroicon-o-key"
                        >
                            <x-filament::input
                                id="restore-backup-token"
                                inline-prefix
                                inline-suffix
                                x-model="token"
                                x-bind:type="showToken ? 'text' : 'password'"
                                x-bind:aria-invalid="Boolean(feedbackMessage) && feedbackStatus !== 'success'"
                                aria-describedby="restore-backup-feedback"
                                autocomplete="off"
                                placeholder="Paste the one-time recovery token"
                                x-on:input="if (feedbackStatus !== 'success') feedbackMessage = ''"
                            />

                            <x-slot name="suffix">
                                <x-filament::icon-button
                                    x-show="! showToken"
                                    type="button"
                                    color="gray"
                                    size="sm"
                                    icon="heroicon-o-eye"
                                    label="Show recovery token"
                                    tooltip="Show recovery token"
                                    x-on:click="showToken = true"
                                />
                                <x-filament::icon-button
                                    x-show="showToken"
                                    x-cloak
                                    type="button"
                                    color="gray"
                                    size="sm"
                                    icon="heroicon-o-eye-slash"
                                    label="Hide recovery token"
                                    tooltip="Hide recovery token"
                                    x-on:click="showToken = false"
                                />
                            </x-slot>
                        </x-filament::input.wrapper>

                        <p
                            x-show="feedbackMessage"
                            x-cloak
                            id="restore-backup-feedback"
                            class="mt-2 text-sm"
                            :class="feedbackStatus === 'success'
                                ? 'text-success-600 dark:text-success-400'
                                : 'fi-fo-field-wrp-error-message'"
                            role="status"
                            aria-live="polite"
                            x-text="feedbackMessage"
                        ></p>
                    </div>
                </div>
            </form>

            <x-slot name="footer">
                <div class="fi-modal-footer-actions">
                    <x-filament::button
                        color="gray"
                        type="button"
                        x-on:click="$dispatch('close-modal', { id: 'restore-backup' })"
                        x-bind:disabled="submitting"
                    >
                        Cancel
                    </x-filament::button>

                    <x-filament::button
                        color="danger"
                        type="submit"
                        form-id="restore-backup-form"
                        x-bind:disabled="submitting"
                        x-bind:class="{ 'fi-disabled': submitting }"
                    >
                        <span x-show="! submitting">Restore backup</span>
                        <span x-show="submitting" x-cloak>Restoring…</span>
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>
    </div>

    <script>
        window.showRestoreBackupModal = function () {
            window.dispatchEvent(new CustomEvent('open-modal', { detail: { id: 'restore-backup' } }));
        };
    </script>
@endif
