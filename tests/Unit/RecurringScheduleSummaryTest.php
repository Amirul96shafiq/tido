<?php

declare(strict_types=1);

use App\Support\RecurringScheduleSummary;

test('cadence labels cover presets', function () {
    expect(RecurringScheduleSummary::cadenceLabel('monthly'))->toBe('Monthly')
        ->and(RecurringScheduleSummary::cadenceLabel('quarterly'))->toBe('Quarterly')
        ->and(RecurringScheduleSummary::cadenceLabel('semiannual'))->toBe('Every 6 months')
        ->and(RecurringScheduleSummary::cadenceLabel('yearly'))->toBe('Yearly')
        ->and(RecurringScheduleSummary::cadenceLabel('custom', 5))->toBe('Every 5 months')
        ->and(RecurringScheduleSummary::cadenceLabel('once'))->toBe('Once');
});

test('create summary status is creating', function () {
    expect(RecurringScheduleSummary::statusLabel('create'))->toBe('Creating')
        ->and(RecurringScheduleSummary::statusLabel('create', false))->toBe('Creating');
});

test('edit and view summary status follow is_active', function () {
    expect(RecurringScheduleSummary::statusLabel('edit', true))->toBe('Active')
        ->and(RecurringScheduleSummary::statusLabel('edit', 1))->toBe('Active')
        ->and(RecurringScheduleSummary::statusLabel('edit', false))->toBe('Inactive')
        ->and(RecurringScheduleSummary::statusLabel('view', true))->toBe('Active')
        ->and(RecurringScheduleSummary::statusLabel('view', false))->toBe('Inactive');
});
