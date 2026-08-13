<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\GlobalSearch\AdminDestinationSearch;
use App\Filament\Livewire\DatabaseNotifications;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\EmailChangeLinkExpired;
use App\Filament\Pages\Auth\Forbidden;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\NotFound;
use App\Filament\Pages\Auth\PasswordResetLinkExpired;
use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Filament\Pages\Auth\ResetPassword;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ReceiptUploadPage;
use App\Filament\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Resources\Expenses\Pages\EditExpense;
use App\Http\Middleware\SetUserPreferences;
use App\Support\FilamentAuthLogout;
use CharrafiMed\GlobalSearchModal\GlobalSearchModalPlugin;
use CharrafiMed\GlobalSearchModal\GlobalSearchResults;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Js;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Facades\FilamentIcon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Component;
use Livewire\Livewire;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        FilamentIcon::register([
            'panels::sidebar.collapse-button' => 'heroicon-o-bars-3-bottom-left',
            'panels::sidebar.expand-button' => 'heroicon-o-bars-3',
        ]);
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->passwordReset(RequestPasswordReset::class, ResetPassword::class)
            ->profile(EditProfile::class, isSimple: false)
            ->emailChangeVerification()
            ->colors([
                'primary' => Color::hex('#FFD07D'),
                'success' => Color::hex('#FFA524'),
                'info' => Color::hex('#FFE2A3'),
                'gray' => array_replace(Color::Slate, [
                    900 => Color::Slate[800],
                    950 => Color::Slate[800],
                ]),
                'danger' => Color::Red,
                'warning' => Color::Amber,
            ])
            ->font('Outfit')
            ->brandLogo(asset('images/tido_dark_logo.png'))
            ->darkModeBrandLogo(asset('images/tido_light_logo.png'))
            ->brandLogoHeight('3rem')
            ->favicon(asset('images/favicon.png'))
            ->sidebarWidth('16rem')
            ->sidebarCollapsibleOnDesktop()
            ->assets([
                Js::make(
                    'disable-mobile-tippy',
                    Vite::asset('resources/js/disable-mobile-tippy.js'),
                )->module(),
                Js::make(
                    'sticky-blur-veil',
                    Vite::asset('resources/js/sticky-blur-veil.js'),
                )->module(),
                Js::make(
                    'select-value-marquee',
                    Vite::asset('resources/js/select-value-marquee.js'),
                )->module(),
                Js::make(
                    'notification-swipe-dismiss',
                    Vite::asset('resources/js/notification-swipe-dismiss.js'),
                )->module(),
                Js::make(
                    'file-upload-editor-overlay',
                    Vite::asset('resources/js/file-upload-editor-overlay.js'),
                )->module(),
                Js::make(
                    'unsupported-record-checkbox',
                    Vite::asset('resources/js/unsupported-record-checkbox.js'),
                )->module(),
                Js::make(
                    'date-picker-month-select',
                    Vite::asset('resources/js/date-picker-month-select.js'),
                )->module(),
            ])
            ->renderHook(
                PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE,
                function (): View {
                    $livewire = Livewire::current();
                    $activeView = $livewire instanceof Dashboard
                        ? $livewire->getDashboardView()
                        : Dashboard::VIEW_FINANCES;

                    return view('filament.pages.partials.dashboard-view-tabs', [
                        'activeView' => $activeView,
                    ]);
                },
                scopes: [
                    Dashboard::class,
                ],
            )
            ->renderHook(
                PanelsRenderHook::SIMPLE_LAYOUT_START,
                fn (): string => Blade::render('<x-auth-menu />'),
                scopes: [
                    Login::class,
                    RequestPasswordReset::class,
                    ResetPassword::class,
                    PasswordResetLinkExpired::class,
                    EmailChangeLinkExpired::class,
                    NotFound::class,
                    Forbidden::class,
                ],
            )
            ->renderHook(
                PanelsRenderHook::SIMPLE_PAGE_START,
                fn (): string => Blade::render('<x-auth-login-toast />'),
                scopes: [
                    Login::class,
                ],
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render('@vite(\'resources/css/app.css\')'),
            )
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => Blade::render('@vite([\'resources/js/filament-chart-js-plugins.js\'])'),
                scopes: [
                    Dashboard::class,
                ],
            )
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => Blade::render('@vite([\'resources/js/drag-drop-upload.js\', \'resources/js/receipt-upload-handler.js\'])'),
                scopes: [
                    Dashboard::class,
                    ReceiptUploadPage::class,
                ],
            )
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => Blade::render('@vite([\'resources/js/receipt-image-preview.js\'])'),
                scopes: [
                    ReceiptUploadPage::class,
                    CreateExpense::class,
                    EditExpense::class,
                ],
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                function (): string {
                    $currentUser = auth()->user();
                    $backgroundEnabled = $currentUser === null
                        || (bool) $currentUser->getAttribute('stylized_background_enabled');
                    $light = asset('images/bg-l-v8.webp');
                    $dark = asset('images/bg-d-v8.webp');
                    $authLightMobile = asset('images/auth-bg-l.webp');
                    $authDarkMobile = asset('images/auth-bg-d.webp');
                    $authLight = asset('images/auth-bg-l-v5.webp');
                    $authDark = asset('images/auth-bg-d-v5.webp');
                    // Chrome-matched tint; art lives on .tido-stylized-bg with soft masks.
                    $lightTint = 'var(--color-white)';
                    $darkTint = 'var(--color-slate-800)';
                    $lightArt = $backgroundEnabled ? "url('{$light}')" : 'none';
                    $darkArt = $backgroundEnabled ? "url('{$dark}')" : 'none';
                    $stylizedDisplay = $backgroundEnabled ? 'block' : 'none';

                    return <<<HTML
                        <style>
                            :root {
                                --tido-bg-art-light: {$lightArt};
                                --tido-bg-art-dark: {$darkArt};
                                --tido-bg-tint-light: {$lightTint};
                                --tido-bg-tint-dark: {$darkTint};
                                --tido-bg-color-light: var(--color-white);
                                --tido-bg-color-dark: var(--color-slate-800);
                                --tido-stylized-bg-display: {$stylizedDisplay};
                                --tido-auth-bg-light-mobile: url('{$authLightMobile}');
                                --tido-auth-bg-dark-mobile: url('{$authDarkMobile}');
                                --tido-auth-bg-light: url('{$authLight}');
                                --tido-auth-bg-dark: url('{$authDark}');
                            }
                        </style>
                        HTML;
                },
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => <<<'HTML'
                    <script>
                        (function () {
                            try {
                                var desktopBreakpoint = 1024;
                                var isDesktop = window.innerWidth >= desktopBreakpoint;
                                var isOpenDesktop = JSON.parse(localStorage.getItem('isOpenDesktop') ?? 'true');
                                var isOpen = JSON.parse(localStorage.getItem('isOpen') ?? 'true');
                                var isCollapsed = isDesktop ? ! isOpenDesktop : ! isOpen;

                                document.documentElement.classList.add('fi-sidebar-preload');

                                if (isCollapsed) {
                                    document.documentElement.classList.add('fi-sidebar-is-collapsed');
                                }

                                document.addEventListener('alpine:initialized', function () {
                                    requestAnimationFrame(function () {
                                        document.documentElement.classList.remove(
                                            'fi-sidebar-preload',
                                            'fi-sidebar-is-collapsed',
                                        );
                                    });

                                    var sidebar = document.querySelector('.fi-main-sidebar');
                                    if (! sidebar) {
                                        return;
                                    }

                                    var lastOpen = sidebar.classList.contains('fi-sidebar-open');
                                    var animTimer = null;

                                    new MutationObserver(function () {
                                        var open = sidebar.classList.contains('fi-sidebar-open');
                                        if (open === lastOpen) {
                                            return;
                                        }
                                        lastOpen = open;
                                        if (! sidebar.classList.contains('fi-sidebar-animating')) {
                                            sidebar.classList.add('fi-sidebar-animating');
                                        }
                                        if (animTimer) {
                                            clearTimeout(animTimer);
                                        }
                                        var styles = getComputedStyle(document.documentElement);
                                        var duration = parseFloat(styles.getPropertyValue('--tido-sidebar-duration')) || 520;
                                        var delay = parseFloat(styles.getPropertyValue('--tido-sidebar-content-delay')) || 340;
                                        animTimer = setTimeout(function () {
                                            sidebar.classList.remove('fi-sidebar-animating');
                                            animTimer = null;
                                        }, duration + delay + 40);
                                    }).observe(sidebar, { attributes: true, attributeFilter: ['class'] });
                                });
                            } catch (e) {}
                        })();
                    </script>
                    HTML,
            )
            ->databaseNotifications(livewireComponent: DatabaseNotifications::class)
            ->databaseNotificationsPolling('60s')
            ->spa()
            ->globalSearchResourceOptIn()
            ->globalSearchKeyBindings(['alt+k'])
            ->globalSearchFieldKeyBindingSuffix()
            ->plugins([
                GlobalSearchModalPlugin::make()
                    ->modal(
                        width: Width::TwoExtraLarge,
                        hasCloseButton: false,
                    )
                    ->searchUsing(
                        fn (string $query, GlobalSearchResults $builder): GlobalSearchResults => AdminDestinationSearch::search($query, $builder),
                        mergeWithCore: true,
                    ),
            ])
            ->userMenuItems([
                // sort >= 0 places items after the theme switcher
                // (theme → profile → changelogs 🡥 → notifications 🡥 → logout)
                'profile' => fn (Action $action): Action => $action
                    ->icon('heroicon-o-user')
                    ->sort(0)
                    ->extraAttributes(['wire:current' => 'fi-user-menu-profile-active']),
                Action::make('changelogs')
                    ->label('Changelogs 🡥')
                    ->icon('heroicon-o-code-bracket')
                    ->alpineClickHandler("\$dispatch('open-modal', { id: 'changelog' })")
                    ->sort(10),
                Action::make('notifications')
                    ->label('Notifications 🡥')
                    ->icon('heroicon-o-bell')
                    ->alpineClickHandler("\$dispatch('open-modal', { id: 'database-notifications' })")
                    ->sort(20),
                'logout' => fn (Action $action): Action => $action
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->color('danger')
                    ->sort(30)
                    ->url(null)
                    ->postToUrl(false)
                    ->requiresConfirmation()
                    ->modalHeading('Sign out')
                    ->modalDescription('Are you sure you want to sign out of your account?')
                    ->modalSubmitActionLabel('Sign out')
                    ->action(function (Component $livewire): void {
                        FilamentAuthLogout::logoutToLogin($livewire);
                    }),
            ])
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                function (): string {
                    $isRtl = __('filament-panels::layout.direction') === 'rtl';
                    $isSidebarCollapsibleOnDesktop = filament()->isSidebarCollapsibleOnDesktop();
                    $isSidebarFullyCollapsibleOnDesktop = filament()->isSidebarFullyCollapsibleOnDesktop();

                    return Blade::render(<<<'BLADE'
                        <div
                            x-data="{}"
                            class="fi-sidebar-collapse-footer"
                        >
                            @if ($isSidebarCollapsibleOnDesktop || $isSidebarFullyCollapsibleOnDesktop)
                                <div class="fi-sidebar-collapse-buttons flex h-full w-full items-center px-4">
                                    <div class="fi-sidebar-collapse-morph">
                                        <x-filament::button
                                            color="primary"
                                            size="sm"
                                            :icon="$isRtl ? \Filament\Support\Icons\Heroicon::OutlinedChevronRight : \Filament\Support\Icons\Heroicon::OutlinedChevronLeft"
                                            :icon-alias="
                                                $isRtl
                                                ? [
                                                    \Filament\View\PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON_RTL,
                                                    \Filament\View\PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON,
                                                ]
                                                : \Filament\View\PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON
                                            "
                                            icon-size="md"
                                            x-on:click="
                                                const sidebar = $el.closest('.fi-sidebar');
                                                if (sidebar) {
                                                    sidebar.classList.add('fi-sidebar-animating');
                                                }
                                                $el.blur();
                                                document.querySelectorAll('[data-tippy-root]').forEach((node) => node.remove());
                                                $store.sidebar.close();
                                            "
                                            class="fi-sidebar-close-collapse-sidebar-btn"
                                        >
                                            <span class="fi-sidebar-collapse-toggle-label">
                                                {{ __('filament-panels::layout.actions.sidebar.collapse.label') }}
                                            </span>
                                        </x-filament::button>

                                        <x-filament::button
                                            color="primary"
                                            size="sm"
                                            :icon="$isRtl ? \Filament\Support\Icons\Heroicon::OutlinedChevronLeft : \Filament\Support\Icons\Heroicon::OutlinedChevronRight"
                                            :icon-alias="
                                                $isRtl
                                                ? [
                                                    \Filament\View\PanelsIconAlias::SIDEBAR_EXPAND_BUTTON_RTL,
                                                    \Filament\View\PanelsIconAlias::SIDEBAR_EXPAND_BUTTON,
                                                ]
                                                : \Filament\View\PanelsIconAlias::SIDEBAR_EXPAND_BUTTON
                                            "
                                            icon-size="md"
                                            label-sr-only
                                            :tooltip="__('filament-panels::layout.actions.sidebar.expand.label')"
                                            x-on:click="
                                                const sidebar = $el.closest('.fi-sidebar');
                                                if (sidebar) {
                                                    sidebar.classList.add('fi-sidebar-animating');
                                                }
                                                $el.blur();
                                                document.querySelectorAll('[data-tippy-root]').forEach((node) => node.remove());
                                                $store.sidebar.open();
                                            "
                                            class="fi-sidebar-open-collapse-sidebar-btn"
                                        >
                                            {{ __('filament-panels::layout.actions.sidebar.expand.label') }}
                                        </x-filament::button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    BLADE, [
                        'isRtl' => $isRtl,
                        'isSidebarCollapsibleOnDesktop' => $isSidebarCollapsibleOnDesktop,
                        'isSidebarFullyCollapsibleOnDesktop' => $isSidebarFullyCollapsibleOnDesktop,
                    ]);
                }
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => '<div class="tido-stylized-bg" aria-hidden="true"></div>',
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => Blade::render('<x-changelog-modal /><x-restore-backup-modal /><x-drag-drop-config /><x-go-to-top /><x-go-to-bottom /><x-global-search-shortcut /><x-hash-scroll />'),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->navigationGroups([
                NavigationGroup::make('Finances'),
                NavigationGroup::make('Settings'),
                NavigationGroup::make('Integrations'),
                NavigationGroup::make('Tools'),
            ])
            ->routes(function (): void {
                Route::name('auth.')->group(function (): void {
                    Route::get('/password-reset/expired', PasswordResetLinkExpired::class)
                        ->name('password-reset.expired');
                    Route::get('/email-change-verification/expired', EmailChangeLinkExpired::class)
                        ->name('email-change-verification.expired');
                    Route::get('/not-found', NotFound::class)
                        ->name('not-found');
                    Route::get('/forbidden', Forbidden::class)
                        ->name('forbidden');
                });
            })
            ->livewireComponents([
                PasswordResetLinkExpired::class,
                EmailChangeLinkExpired::class,
                NotFound::class,
                Forbidden::class,
            ])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetUserPreferences::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
