<?php

declare(strict_types=1);

namespace App\Support\Calendar;

use Carbon\CarbonInterface;

final readonly class CalendarEvent
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public CalendarModule $module,
        public string $type,
        public CarbonInterface $date,
        public string $title,
        public ?string $subtitle,
        public ?string $status,
        public string $colorKey,
        public ?string $url,
        public array $meta = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toPopoverArray(): array
    {
        return [
            'module' => $this->module->value,
            'moduleLabel' => $this->module->label(),
            'type' => $this->type,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'status' => $this->status,
            'colorKey' => $this->colorKey,
            'url' => $this->url,
            'projected' => (bool) ($this->meta['projected'] ?? false),
            'completed' => (bool) ($this->meta['completed'] ?? false),
            'isCurrentViewer' => (bool) ($this->meta['is_current_viewer'] ?? false),
        ];
    }
}
