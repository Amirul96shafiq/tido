<?php

declare(strict_types=1);

namespace App\Filament\GlobalSearch;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\EvolutionApiPage;
use App\Filament\Pages\ReceiptUploadPage;
use App\Filament\Pages\ServiceStatusPage;
use App\Filament\Resources\Backups\BackupResource;
use App\Filament\Resources\Budgets\BudgetResource;
use App\Filament\Resources\FamilyMembers\FamilyMemberResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\Labels\LabelResource;
use App\Filament\Resources\PaymentMethods\PaymentMethodResource;
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
        $evolutionUrl = EvolutionApiPage::getUrl();
        $serviceStatusUrl = ServiceStatusPage::getUrl();
        $uploadUrl = ReceiptUploadPage::getUrl();

        return [
            [
                'title' => 'Dashboard',
                'keywords' => ['dashboard', 'home', 'analytics', 'finances'],
                'group' => 'Pages',
                'url' => Dashboard::getUrl(),
            ],
            [
                'title' => 'Upload Receipts',
                'keywords' => ['upload', 'receipt', 'receipts', 'finances', 'ingest'],
                'group' => 'Pages',
                'url' => $uploadUrl,
            ],
            [
                'title' => 'Invoices',
                'keywords' => ['invoice', 'invoices', 'receipt', 'receipts', 'finances', 'expenses'],
                'group' => 'Pages',
                'url' => InvoiceResource::getUrl('index'),
            ],
            [
                'title' => 'Budgets',
                'keywords' => ['budget', 'budgets', 'finances', 'spending', 'limits'],
                'group' => 'Pages',
                'url' => BudgetResource::getUrl('index'),
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
                'title' => 'Regional Preferences',
                'keywords' => ['regional', 'preferences', 'locale', 'language', 'timezone', 'date'],
                'group' => 'Sections',
                'url' => $profileUrl.'#regional-preferences',
                'details' => ['Page' => 'Profile'],
            ],
            [
                'title' => 'Notifications',
                'keywords' => ['notifications', 'alerts', 'budget', 'digest', 'evolution'],
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
                'title' => 'Connection history',
                'keywords' => ['connection', 'history', 'log', 'events', 'evolution'],
                'group' => 'Sections',
                'url' => $evolutionUrl.'#evolution-connection-history',
                'details' => ['Page' => 'Evolution API'],
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
