<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Livewire\DatabaseNotifications;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Filament\Pages\Auth\ResetPassword;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Budgets\Pages\CreateBudget;
use App\Filament\Resources\Budgets\Pages\EditBudget;
use App\Filament\Resources\FamilyMembers\Pages\CreateFamilyMember;
use App\Filament\Resources\FamilyMembers\Pages\EditFamilyMember;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Labels\Pages\CreateLabel;
use App\Filament\Resources\Labels\Pages\EditLabel;
use App\Filament\Resources\PaymentMethods\Pages\CreatePaymentMethod;
use App\Filament\Resources\PaymentMethods\Pages\EditPaymentMethod;
use App\Http\Middleware\SetUserPreferences;
use CharrafiMed\GlobalSearchModal\GlobalSearchModalPlugin;
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
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Middleware\ShareErrorsFromSession;

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
            ->font('Outfit', url: 'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap')
            ->brandLogo(asset('images/tido_dark_logo.png'))
            ->darkModeBrandLogo(asset('images/tido_light_logo.png'))
            ->brandLogoHeight('3rem')
            ->favicon(asset('images/favicon.png'))
            ->sidebarWidth('16rem')
            ->sidebarCollapsibleOnDesktop()
            ->assets([
                Js::make(
                    'chart-js-plugins',
                    Vite::asset('resources/js/filament-chart-js-plugins.js'),
                )->module(),
                Js::make(
                    'disable-mobile-tippy',
                    Vite::asset('resources/js/disable-mobile-tippy.js'),
                )->module(),
                Js::make(
                    'drag-drop-upload',
                    Vite::asset('resources/js/drag-drop-upload.js'),
                )->module(),
                Js::make(
                    'receipt-upload-handler',
                    Vite::asset('resources/js/receipt-upload-handler.js'),
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
                    'receipt-image-preview',
                    Vite::asset('resources/js/receipt-image-preview.js'),
                )->module(),
            ])
            ->renderHook(
                PanelsRenderHook::SIMPLE_LAYOUT_START,
                fn (): string => Blade::render('<x-auth-menu />'),
                scopes: [
                    Login::class,
                    RequestPasswordReset::class,
                    ResetPassword::class,
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
                PanelsRenderHook::HEAD_END,
                function (): string {
                    $currentUser = auth()->user();
                    $backgroundEnabled = $currentUser === null
                        || (bool) $currentUser->getAttribute('stylized_background_enabled');
                    $light = asset('images/bg-l.png');
                    $dark = asset('images/bg-d.png');
                    $authLightMobile = asset('images/auth-bg-l.png');
                    $authDarkMobile = asset('images/auth-bg-d.png');
                    $authLight = asset('images/auth-bg-l-v2.png');
                    $authDark = asset('images/auth-bg-d-v2.png');
                    $lightBackground = $backgroundEnabled ? "url('{$light}')" : 'none';
                    $darkBackground = $backgroundEnabled ? "url('{$dark}')" : 'none';

                    return <<<HTML
                        <style>
                            :root {
                                --tido-bg-light: {$lightBackground};
                                --tido-bg-dark: {$darkBackground};
                                --tido-bg-color-light: #FFFFFF;
                                --tido-bg-color-dark: #1D293D;
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
                                });
                            } catch (e) {}
                        })();
                    </script>
                    HTML,
            )
            ->databaseNotifications(livewireComponent: DatabaseNotifications::class)
            ->spa()
            ->globalSearchResourceOptIn()
            ->globalSearchKeyBindings(['alt+k'])
            ->globalSearchFieldKeyBindingSuffix()
            ->plugins([
                GlobalSearchModalPlugin::make()
                    ->modal(
                        width: Width::TwoExtraLarge,
                        hasCloseButton: false,
                    ),
            ])
            ->userMenuItems([
                // sort >= 0 places items after the theme switcher
                // (theme → profile → changelogs → notifications → logout)
                'profile' => fn (Action $action): Action => $action
                    ->icon('heroicon-o-user')
                    ->sort(0)
                    ->extraAttributes(fn (): array => request()->routeIs(EditProfile::getRouteName())
                        ? ['class' => 'fi-user-menu-profile-active']
                        : []),
                Action::make('changelogs')
                    ->label('Changelogs')
                    ->icon('heroicon-o-code-bracket')
                    ->url('javascript:void(0)')
                    ->sort(10),
                Action::make('notifications')
                    ->label('Notifications')
                    ->icon('heroicon-o-bell')
                    ->alpineClickHandler("\$dispatch('open-modal', { id: 'database-notifications' })")
                    ->sort(20),
                'logout' => fn (Action $action): Action => $action
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->color('danger')
                    ->sort(30),
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
                                        x-cloak
                                        x-show="$store.sidebar.isOpen"
                                        x-transition:enter="fi-transition-enter"
                                        x-transition:enter-start="fi-transition-enter-start"
                                        x-transition:enter-end="fi-transition-enter-end"
                                        x-on:click="
                                            $el.blur();
                                            document.querySelectorAll('[data-tippy-root]').forEach((node) => node.remove());
                                            $store.sidebar.close();
                                        "
                                        class="fi-sidebar-close-collapse-sidebar-btn"
                                    >
                                        {{ __('filament-panels::layout.actions.sidebar.collapse.label') }}
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
                                        x-cloak
                                        x-show="! $store.sidebar.isOpen"
                                        x-transition:enter="fi-transition-enter"
                                        x-transition:enter-start="fi-transition-enter-start"
                                        x-transition:enter-end="fi-transition-enter-end"
                                        x-on:click="
                                            $el.blur();
                                            document.querySelectorAll('[data-tippy-root]').forEach((node) => node.remove());
                                            $store.sidebar.open();
                                        "
                                        class="fi-sidebar-open-collapse-sidebar-btn"
                                    >
                                        {{ __('filament-panels::layout.actions.sidebar.expand.label') }}
                                    </x-filament::button>
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
                PanelsRenderHook::BODY_END,
                fn (): string => Blade::render('<x-changelog-modal /><x-restore-backup-modal /><x-drag-drop-config /><x-go-to-top /><x-go-to-bottom /><x-global-search-shortcut />'),
            )
            ->renderHook(
                PanelsRenderHook::PAGE_END,
                fn (): View => view('filament.hooks.content-draft-poller'),
                scopes: [
                    CreateInvoice::class,
                    EditInvoice::class,
                    CreateLabel::class,
                    EditLabel::class,
                    CreatePaymentMethod::class,
                    EditPaymentMethod::class,
                    CreateFamilyMember::class,
                    EditFamilyMember::class,
                    CreateBudget::class,
                    EditBudget::class,
                ],
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->navigationGroups([
                NavigationGroup::make('Finances'),
                NavigationGroup::make('Settings'),
                NavigationGroup::make('Integrations'),
                NavigationGroup::make('Tools'),
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
