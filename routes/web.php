<?php

use App\Helpers\ChangelogHelper;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/changelog', function () {
    // #region agent log
    $agentLogStart = microtime(true);
    file_put_contents(base_path('debug-8f1b08.log'), json_encode(['sessionId' => '8f1b08', 'runId' => 'run2', 'hypothesisId' => 'H3', 'location' => 'web.php:changelog-route', 'message' => 'route hit', 'data' => ['page' => (int) request('page', 1)], 'timestamp' => (int) (microtime(true) * 1000)]).PHP_EOL, FILE_APPEND);
    // #endregion
    try {
        $changelog = ChangelogHelper::getPaginatedChangelog(10, (int) request('page', 1));
        // #region agent log
        file_put_contents(base_path('debug-8f1b08.log'), json_encode(['sessionId' => '8f1b08', 'runId' => 'run2', 'hypothesisId' => 'H3', 'location' => 'web.php:changelog-route', 'message' => 'changelog fetched', 'data' => ['elapsedMs' => (int) round((microtime(true) - $agentLogStart) * 1000), 'total' => $changelog->total(), 'count' => $changelog->count()], 'timestamp' => (int) (microtime(true) * 1000)]).PHP_EOL, FILE_APPEND);
        // #endregion

        // Format commits for JSON response
        $commits = $changelog->map(function ($commit) {
            // Get relative date and shorten time units
            $dateRelative = $commit['date']->diffForHumans();
            $dateRelative = str_replace([' seconds', ' minutes', ' hours'], [' secs', ' mins', ' hrs'], $dateRelative);
            // Also handle singular forms
            $dateRelative = str_replace([' second', ' minute', ' hour'], [' sec', ' min', ' hr'], $dateRelative);
            // Handle other time units that might exist
            $dateRelative = str_replace([' days', ' weeks', ' months', ' years'], [' days', ' wks', ' mths', ' yrs'], $dateRelative);
            $dateRelative = str_replace([' day', ' week', ' month', ' year'], [' day', ' wk', ' mth', ' yr'], $dateRelative);

            $user = request()->user();
            $dateFormatted = $user instanceof User
                ? $user->formatDateTime($commit['date'])
                : $commit['date']->format(config('app.datetime_format', 'd/m/Y H:i'));

            return [
                'short_hash' => $commit['short_hash'],
                'full_hash' => $commit['full_hash'],
                'date' => $commit['date']->toISOString(),
                'date_formatted' => $dateFormatted,
                'date_relative' => $dateRelative,
                'author_name' => $commit['author_name'],
                'author_email' => $commit['author_email'],
                'author_avatar' => $commit['author_avatar'],
                'message' => $commit['message'],
                'description' => $commit['description'] ?? '',
                'tags' => $commit['tags'] ?? [],
            ];
        })->values(); // Convert to indexed array

        return response()->json([
            'success' => true,
            'commits' => $commits,
            'total' => $changelog->total(),
            'pagination' => [
                'current_page' => $changelog->currentPage(),
                'last_page' => $changelog->lastPage(),
                'per_page' => $changelog->perPage(),
                'total' => $changelog->total(),
                'from' => $changelog->firstItem(),
                'to' => $changelog->lastItem(),
                'has_more_pages' => $changelog->hasMorePages(),
            ],
        ]);
    } catch (Exception $e) {
        // #region agent log
        file_put_contents(base_path('debug-8f1b08.log'), json_encode(['sessionId' => '8f1b08', 'runId' => 'run2', 'hypothesisId' => 'H3', 'location' => 'web.php:changelog-route', 'message' => 'route exception', 'data' => ['error' => $e->getMessage()], 'timestamp' => (int) (microtime(true) * 1000)]).PHP_EOL, FILE_APPEND);

        // #endregion
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'commits' => [],
            'total' => 0,
            'pagination' => null,
        ], 500);
    }
})->name('changelog');
