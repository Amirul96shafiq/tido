<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\ServiceHealthStatus;
use App\Filament\Concerns\HasSectionNav;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Services\Health\ServiceHealthAggregator;
use App\Services\Health\ServiceHealthRecorder;
use App\Support\HouseholdAccess;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ServiceStatusPage extends Page
{
    use HasSectionNav;
    use PrependsHomeBreadcrumb;

    protected static ?string $slug = 'service-status';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $navigationLabel = 'Service Status';

    protected static string|\UnitEnum|null $navigationGroup = 'Tools';

    protected static ?string $title = 'Service Status';

    protected static ?int $navigationSort = 2;

    /**
     * @var array<string, mixed>
     */
    public array $report = [];

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            'fi-service-status-page',
        ];
    }

    /**
     * @return list<array{label: string, id: string}>
     */
    public static function sectionNavItems(): array
    {
        return [
            ['label' => 'Summary Report', 'id' => 'service-summary-report'],
            ['label' => 'Status', 'id' => 'service-status'],
        ];
    }

    public function sectionNavAriaLabel(): string
    {
        return 'Service status sections';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->wrapInSectionNavScope([
                    SchemaView::make('filament.pages.partials.service-status-content'),
                ]),
            ]);
    }

    public function mount(ServiceHealthAggregator $aggregator): void
    {
        $this->loadReport($aggregator);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runCheck')
                ->label('Run check now')
                ->icon(Heroicon::OutlinedArrowPath)
                ->authorize(fn (): bool => HouseholdAccess::canManageHouseholdSettings())
                ->authorizationTooltip()
                ->authorizationMessage(fn (): string => HouseholdAccess::createDeniedMessage())
                ->extraAttributes(fn (): array => HouseholdAccess::isFamilyMember()
                    ? ['class' => 'tido-primary-only-action']
                    : [])
                ->action(function (ServiceHealthRecorder $recorder, ServiceHealthAggregator $aggregator): void {
                    $recorder->recordAll();
                    $this->loadReport($aggregator);

                    Notification::make()
                        ->title('Health check completed')
                        ->body('Latest service samples have been recorded.')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function loadReport(ServiceHealthAggregator $aggregator): void
    {
        $timezone = auth()->user()?->preferredTimezone();

        $this->report = $aggregator->report($timezone);
    }

    public function summaryStatus(): ServiceHealthStatus
    {
        return $this->report['summary']['status'] ?? ServiceHealthStatus::Unknown;
    }

    public function summaryTitle(): string
    {
        return (string) ($this->report['summary']['title'] ?? 'Status unavailable');
    }

    public function summaryMessage(): string
    {
        return (string) ($this->report['summary']['message'] ?? '');
    }

    public function periodDateRange(): string
    {
        return (string) ($this->report['periodDateRange'] ?? '');
    }

    public function summaryReportHeading(): string
    {
        return 'Summary Report ('.$this->periodDateRange().')';
    }

    public function systemStatusHeading(): string
    {
        return 'Status ('.$this->periodDateRange().')';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function services(): array
    {
        return $this->report['services'] ?? [];
    }
}
