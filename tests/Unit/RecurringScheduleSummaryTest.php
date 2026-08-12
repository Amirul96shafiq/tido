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
