<?php

declare(strict_types=1);

use App\Support\TidoBrandCopy;

test('login heading html underlines ti and do', function () {
    expect((string) TidoBrandCopy::loginHeadingHtml())
        ->toBe('Keep it <span class="underline">ti</span>dy. Get it <span class="underline">do</span>ne.');
});

test('login subheading html underlines ti in tidy done and whole tido', function () {
    expect((string) TidoBrandCopy::loginSubheadingHtml())
        ->toBe('Where <span class="underline">ti</span>dy preparation meets <span class="underline">do</span>ne work, then "<span class="underline">tido</span>" (sleep).');
});

test('dashboard action phrase html underlines ti and do', function () {
    expect((string) TidoBrandCopy::dashboardActionPhraseHtml())
        ->toBe('Start by <span class="underline">ti</span>dying up your files, then get it <span class="underline">do</span>ne.');
});
