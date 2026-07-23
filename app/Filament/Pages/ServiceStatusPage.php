<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\ServiceHealthStatus;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Services\Health\ServiceHealthAggregator;
use App\Services\Health\ServiceHealthRecorder;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class ServiceStatusPage extends Page
{
    use PrependsHomeBreadcrumb;

    protected static ?string $slug = 'service-status';

    protected string $view = 'filament.pages.service-status';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $navigationLabel = 'Service Status';

    protected static string|\UnitEnum|null $navigationGroup = 'Tools';

    protected static ?string $title = 'Service Status';

    protected static ?int $navigationSort = 2;

    /**
     * @var array<string, mixed>
     */
    public array $report = [];

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

    public function periodLabel(): string
    {
        return (string) ($this->report['periodLabel'] ?? '');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function services(): array
    {
        return $this->report['services'] ?? [];
    }
}
