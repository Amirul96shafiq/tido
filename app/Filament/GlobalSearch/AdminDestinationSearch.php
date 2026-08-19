<?php

declare(strict_types=1);

namespace App\Filament\GlobalSearch;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\EvolutionApiPage;
use App\Filament\Pages\OllamaPage;
use App\Filament\Pages\ReceiptUploadPage;
use App\Filament\Pages\ServiceStatusPage;
use App\Filament\Resources\Backups\BackupResource;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\FamilyMembers\FamilyMemberResource;
use App\Filament\Resources\Labels\LabelResource;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
use App\Filament\Resources\Recurrings\RecurringResource;
use App\Filament\Widgets\CurrentCurrency;
use App\Filament\Widgets\MonthlySpendingOverview;
use App\Filament\Widgets\RecurringMonthSnapshot;
use App\Support\HouseholdAccess;
use CharrafiMed\GlobalSearchModal\GlobalSearchResult;
use CharrafiMed\GlobalSearchModal\GlobalSearchResults;
use Illuminate\Support\Str;

final class AdminDestinationSearch
{
    /**
     * @return list<array{title: string, keywords: list<string>, group: 'Pages'|'Sections', url: string, details?: array<string, string>}>
     */
    public static function destinations(): array
    {
        $profileUrl = EditProfile::getUrl();
        $dashboardUrl = Dashboard::getUrl();
        $evolutionUrl = EvolutionApiPage::getUrl();
        $ollamaUrl = OllamaPage::getUrl();
        $serviceStatusUrl = ServiceStatusPage::getUrl();
        $uploadUrl = ReceiptUploadPage::getUrl();

        $destinations = [
            [
                'title' => 'Dashboard',
                'keywords' => ['dashboard', 'home', 'analytics', 'finances'],
                'group' => 'Pages',
                'url' => $dashboardUrl,
            ],
            [
                'title' => 'Total Spent',
                'keywords' => ['total', 'spent', 'spending', 'finance', 'dashboard', 'stats', 'overview'],
                'group' => 'Sections',
                'url' => $dashboardUrl.'#'.MonthlySpendingOverview::SECTION_TOTAL_SPENT,
                'details' => ['Page' => 'Dashboard'],
            ],
            [
                'title' => 'Spending Forecast',
                'keywords' => ['forecast', 'projected', 'spending', 'finance', 'dashboard', 'budget'],
                'group' => 'Sections',
                'url' => $dashboardUrl.'#'.MonthlySpendingOverview::SECTION_SPENDING_FORECAST,
                'details' => ['Page' => 'Dashboard'],
            ],
            [
                'title' => 'Daily Average',
                'keywords' => ['daily', 'average', 'spending', 'finance', 'dashboard'],
                'group' => 'Sections',
                'url' => $dashboardUrl.'#'.MonthlySpendingOverview::SECTION_SPENDING_FORECAST,
                'details' => ['Page' => 'Dashboard'],
            ],
            [
                'title' => 'SST Tax Paid',
                'keywords' => ['sst', 'tax', 'taxation', 'finance', 'dashboard', 'stats'],
                'group' => 'Sections',
                'url' => $dashboardUrl.'#'.MonthlySpendingOverview::SECTION_SST_TAX_PAID,
                'details' => ['Page' => 'Dashboard'],
            ],
            [
                'title' => 'Receipts Processed',
                'keywords' => ['receipts', 'processed', 'parsing', 'pending', 'finance', 'dashboard', 'stats'],
                'group' => 'Sections',
                'url' => $dashboardUrl.'#'.MonthlySpendingOverview::SECTION_RECEIPTS_PROCESSED,
                'details' => ['Page' => 'Dashboard'],
            ],
            [
                'title' => 'USD to MYR',
                'keywords' => ['usd', 'myr', 'currency', 'exchange', 'rate', 'finance', 'dashboard'],
                'group' => 'Sections',
                'url' => $dashboardUrl.'#'.CurrentCurrency::SECTION_CURRENCY_RATE,
                'details' => ['Page' => 'Dashboard'],
            ],
            [
                'title' => 'Due Recurrings',
                'keywords' => ['recurring', 'recurrings', 'due', 'bills', 'subscriptions', 'reminders', 'dashboard'],
                'group' => 'Sections',
                'url' => $dashboardUrl.'#due-recurrings',
                'details' => ['Page' => 'Dashboard'],
            ],
            [
                'title' => RecurringMonthSnapshot::headingLabel(),
                'keywords' => ['recurring', 'recurrings', 'bills', 'snapshot', 'progress', 'paid', 'remaining', 'dashboard', 'this month'],
                'group' => 'Sections',
                'url' => $dashboardUrl.'#'.RecurringMonthSnapshot::dashboardSectionId(),
                'details' => ['Page' => 'Dashboard'],
            ],
            [
                'title' => 'Monthly Spending Trend',
                'keywords' => ['trend', 'monthly', 'chart', 'dashboard', 'spending'],
                'group' => 'Sections',
                'url' => $dashboardUrl.'#monthly-trend',
                'details' => ['Page' => 'Dashboard'],
            ],
            [
                'title' => 'Spending by Label',
                'keywords' => ['label', 'labels', 'spending', 'dashboard', 'chart'],
                'group' => 'Sections',
                'url' => $dashboardUrl.'#spending-by-label',
                'details' => ['Page' => 'Dashboard'],
            ],
            [
                'title' => 'Budget Performance',
                'keywords' => ['budget', 'budgets', 'performance', 'dashboard'],
                'group' => 'Sections',
                'url' => $dashboardUrl.'#budget-status',
                'details' => ['Page' => 'Dashboard'],
            ],
            [
                'title' => 'Top Merchants',
                'keywords' => ['merchant', 'merchants', 'top', 'dashboard', 'chart'],
                'group' => 'Sections',
                'url' => $dashboardUrl.'#top-merchants',
                'details' => ['Page' => 'Dashboard'],
            ],
            [
                'title' => 'Spending by Payment Method',
                'keywords' => ['payment', 'method', 'methods', 'dashboard', 'spending'],
                'group' => 'Sections',
                'url' => $dashboardUrl.'#spending-by-payment-method',
                'details' => ['Page' => 'Dashboard'],
            ],
            [
                'title' => 'Receipts by Upload Source',
                'keywords' => ['receipts', 'source', 'upload', 'dashboard', 'whatsapp'],
                'group' => 'Sections',
                'url' => $dashboardUrl.'#receipts-by-source',
                'details' => ['Page' => 'Dashboard'],
            ],
            [
                'title' => 'Recent Receipts',
                'keywords' => ['recent', 'receipts', 'dashboard', 'invoices'],
                'group' => 'Sections',
                'url' => $dashboardUrl.'#recent-receipts',
                'details' => ['Page' => 'Dashboard'],
            ],
            [
                'title' => 'Upload Receipts',
                'keywords' => ['upload', 'receipt', 'receipts', 'finances', 'ingest'],
                'group' => 'Pages',
                'url' => $uploadUrl,
            ],
            [
                'title' => 'Expenses',
                'keywords' => ['invoice', 'invoices', 'receipt', 'receipts', 'finances', 'expenses'],
                'group' => 'Pages',
                'url' => ExpenseResource::getUrl('index'),
            ],
            [
                'title' => 'Budgets',
                'keywords' => ['budget', 'budgets', 'finances', 'spending', 'limits'],
                'group' => 'Pages',
                'url' => BudgetResource::getUrl('index'),
            ],
            [
                'title' => 'Recurrings',
                'keywords' => ['recurring', 'recurrings', 'bills', 'subscriptions', 'finances', 'tabung'],
                'group' => 'Pages',
                'url' => RecurringResource::getUrl('index'),
            ],
            [
                'title' => 'Labels',
                'keywords' => ['label', 'labels', 'category', 'categories', 'settings'],
                'group' => 'Pages',
                'url' => LabelResource::getUrl('index'),
            ],
            [
                'title' => 'Payment Methods',
                'keywords' => ['payment', 'methods', 'card', 'cash', 'settings'],
                'group' => 'Pages',
                'url' => PaymentMethodResource::getUrl('index'),
            ],
            [
                'title' => 'Family Members',
                'keywords' => ['family', 'members', 'whatsapp', 'allowlist', 'settings'],
                'group' => 'Pages',
                'url' => FamilyMemberResource::getUrl('index'),
            ],
            [
                'title' => 'Backups',
                'keywords' => ['backup', 'backups', 'restore', 'export', 'tools'],
                'group' => 'Pages',
                'url' => BackupResource::getUrl('index'),
            ],
            [
                'title' => 'Ollama',
                'keywords' => ['ollama', 'ai', 'model', 'ocr', 'vision', 'integration', 'llm'],
                'group' => 'Pages',
                'url' => $ollamaUrl,
            ],
            [
                'title' => 'Evolution API',
                'keywords' => ['evolution', 'evolutionapi', 'whatsapp', 'integration', 'qr', 'webhook'],
                'group' => 'Pages',
                'url' => $evolutionUrl,
            ],
            [
                'title' => 'Service Status',
                'keywords' => ['service', 'status', 'health', 'tools', 'monitoring'],
                'group' => 'Pages',
                'url' => $serviceStatusUrl,
            ],
            [
                'title' => 'Profile',
                'keywords' => ['profile', 'account', 'user', 'settings', 'preferences'],
                'group' => 'Pages',
                'url' => $profileUrl,
            ],
            [
                'title' => 'Personalize',
                'keywords' => ['personalize', 'theme', 'sidebar', 'background', 'appearance'],
                'group' => 'Sections',
                'url' => $profileUrl.'#personalize',
                'details' => ['Page' => 'Profile'],
            ],
            [
                'title' => 'Account & Security',
                'keywords' => ['account', 'security', 'email', 'password', 'login'],
                'group' => 'Sections',
                'url' => $profileUrl.'#account-security',
                'details' => ['Page' => 'Profile'],
            ],
            [
                'title' => 'Active Sessions',
                'keywords' => ['active', 'sessions', 'devices', 'revoke', 'logout', 'browser'],
                'group' => 'Sections',
                'url' => $profileUrl.'#active-sessions',
                'details' => ['Page' => 'Profile'],
            ],
            [
                'title' => 'Regional Preferences',
                'keywords' => ['regional', 'preferences', 'locale', 'language', 'timezone', 'date'],
                'group' => 'Sections',
                'url' => $profileUrl.'#regional-preferences',
                'details' => ['Page' => 'Profile'],
            ],
            [
                'title' => 'Notifications',
                'keywords' => ['notifications', 'alerts', 'budget', 'digest', 'evolution', 'recurring', 'reminder', 'receipt', 'backup', 'service status'],
                'group' => 'Sections',
                'url' => $profileUrl.'#notifications',
                'details' => ['Page' => 'Profile'],
            ],
            [
                'title' => 'Danger Zone',
                'keywords' => ['danger', 'zone', 'reset', 'delete', 'account', 'backup'],
                'group' => 'Sections',
                'url' => $profileUrl.'#danger-zone',
                'details' => ['Page' => 'Profile'],
            ],
            [
                'title' => 'Profile Photo',
                'keywords' => ['profile', 'photo', 'avatar', 'picture', 'image'],
                'group' => 'Sections',
                'url' => $profileUrl.'#profile-photo',
                'details' => ['Page' => 'Profile'],
            ],
            [
                'title' => 'Personal Details',
                'keywords' => ['personal', 'details', 'name', 'whatsapp', 'phone', 'birthday'],
                'group' => 'Sections',
                'url' => $profileUrl.'#personal-details',
                'details' => ['Page' => 'Profile'],
            ],
            [
                'title' => 'Connection',
                'keywords' => ['connection', 'instance', 'webhook', 'status', 'evolution'],
                'group' => 'Sections',
                'url' => $evolutionUrl.'#evolution-connection',
                'details' => ['Page' => 'Evolution API'],
            ],
            [
                'title' => 'Link device',
                'keywords' => ['link', 'device', 'qr', 'pairing', 'code', 'whatsapp'],
                'group' => 'Sections',
                'url' => $evolutionUrl.'#evolution-link-device',
                'details' => ['Page' => 'Evolution API'],
            ],
            [
                'title' => 'WhatsApp LID',
                'keywords' => ['lid', 'whatsapp', 'allowlist', 'link', 'identity', 'evolution'],
                'group' => 'Sections',
                'url' => $evolutionUrl.'#evolution-whatsapp-lid',
                'details' => ['Page' => 'Evolution API'],
            ],
            [
                'title' => 'Connection history',
                'keywords' => ['connection', 'history', 'log', 'events', 'evolution'],
                'group' => 'Sections',
                'url' => $evolutionUrl.'#evolution-connection-history',
                'details' => ['Page' => 'Evolution API'],
            ],
            [
                'title' => 'Ollama Status',
                'keywords' => ['ollama', 'status', 'connection', 'latency', 'ai', 'model', 'models', 'installed', 'vision', 'llm', 'integration', 'configure', 'defaults'],
                'group' => 'Sections',
                'url' => $ollamaUrl.'#ollama-status',
                'details' => ['Page' => 'Ollama'],
            ],
            [
                'title' => 'Ollama Pipeline Readiness',
                'keywords' => ['ollama', 'pipeline', 'readiness', 'receipt', 'pdf', 'json', 'integration'],
                'group' => 'Sections',
                'url' => $ollamaUrl.'#ollama-pipeline',
                'details' => ['Page' => 'Ollama'],
            ],
            [
                'title' => 'Ollama Receipt & Parsing Activity',
                'keywords' => ['ollama', 'activity', 'receipts', 'parsed', 'reviewed', 'manual review', 'pdf'],
                'group' => 'Sections',
                'url' => $ollamaUrl.'#ollama-activity',
                'details' => ['Page' => 'Ollama'],
            ],
            [
                'title' => 'Summary report',
                'keywords' => ['summary', 'report', 'health', 'status', 'service'],
                'group' => 'Sections',
                'url' => $serviceStatusUrl.'#service-summary-report',
                'details' => ['Page' => 'Service Status'],
            ],
            [
                'title' => 'System status',
                'keywords' => ['system', 'status', 'services', 'health', 'monitoring'],
                'group' => 'Sections',
                'url' => $serviceStatusUrl.'#service-system-status',
                'details' => ['Page' => 'Service Status'],
            ],
            [
                'title' => 'Upload Receipts',
                'keywords' => ['upload', 'receipt', 'receipts', 'file', 'image'],
                'group' => 'Sections',
                'url' => $uploadUrl.'#upload-receipts',
                'details' => ['Page' => 'Upload Receipts'],
            ],
            [
                'title' => 'Recent Uploads & Processing Status',
                'keywords' => ['recent', 'uploads', 'processing', 'status', 'queue', 'pending'],
                'group' => 'Sections',
                'url' => $uploadUrl.'#recent-uploads',
                'details' => ['Page' => 'Upload Receipts'],
            ],
        ];

        if (! HouseholdAccess::isPrimary()) {
            $blockedTitles = [
                'Family Members',
                'Labels',
                'Payment Methods',
                'Backups',
                'Ollama',
                'Ollama Setup',
                'Ollama Status',
                'Ollama Pipeline Readiness',
                'Ollama Receipt & Parsing Activity',
                'Evolution API',
                'Service Status',
                'Danger Zone',
                'Account & Security',
                'Budget Performance',
            ];

            return array_values(array_filter(
                $destinations,
                fn (array $destination): bool => ! in_array($destination['title'], $blockedTitles, true),
            ));
        }

        return $destinations;
    }

    public static function search(string $query, GlobalSearchResults $builder): GlobalSearchResults
    {
        $query = trim($query);

        if ($query === '') {
            return $builder;
        }

        $terms = self::searchTerms($query);

        if ($terms === []) {
            return $builder;
        }

        $pageResults = [];
        $sectionResults = [];

        foreach (self::destinations() as $destination) {
            if (! self::matches($destination, $terms)) {
                continue;
            }

            $result = new GlobalSearchResult(
                title: $destination['title'],
                url: $destination['url'],
                details: $destination['details'] ?? [],
            );

            if ($destination['group'] === 'Pages') {
                $pageResults[] = $result;
            } else {
                $sectionResults[] = $result;
            }
        }

        if ($pageResults !== []) {
            $builder->category('Pages', $pageResults);
        }

        if ($sectionResults !== []) {
            $builder->category('Sections', $sectionResults);
        }

        return $builder;
    }

    /**
     * @return list<string>
     */
    protected static function searchTerms(string $query): array
    {
        return array_values(array_filter(
            preg_split('/\s+/', Str::lower($query)) ?: [],
            static fn (string $term): bool => $term !== '',
        ));
    }

    /**
     * @param  array{title: string, keywords: list<string>, group: 'Pages'|'Sections', url: string, details?: array<string, string>}  $destination
     * @param  list<string>  $terms
     */
    protected static function matches(array $destination, array $terms): bool
    {
        $haystack = Str::lower($destination['title'].' '.implode(' ', $destination['keywords']));

        foreach ($terms as $term) {
            if (! str_contains($haystack, $term)) {
                return false;
            }
        }

        return true;
    }
}
