<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Request as ApiRequest;
use App\Models\RequestHistory;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Stats and chart series are cached because they run several aggregate
     * queries that rarely change between requests. The cache is busted from
     * the user/workspace controllers whenever the underlying data mutates.
     */
    public const CACHE_KEY = 'admin:dashboard';

    public function __invoke(): Response
    {
        $data = Cache::remember(self::CACHE_KEY, now()->addMinutes(10), function (): array {
            $weekAgo = now()->subDays(7);

            return [
                'stats' => [
                    'users' => [
                        'total' => User::count(),
                        'delta' => User::where('created_at', '>=', $weekAgo)->count(),
                    ],
                    'admins' => [
                        'total' => User::where('role', UserRole::Admin)->count(),
                        'delta' => null,
                    ],
                    'workspaces' => [
                        'total' => Workspace::count(),
                        'delta' => Workspace::where('created_at', '>=', $weekAgo)->count(),
                    ],
                    'collections' => [
                        'total' => Collection::count(),
                        'delta' => Collection::where('created_at', '>=', $weekAgo)->count(),
                    ],
                    'requests' => [
                        'total' => ApiRequest::count(),
                        'delta' => RequestHistory::where('executed_at', '>=', $weekAgo)->count(),
                    ],
                ],
                'userGrowth' => $this->cumulativeUserGrowth(30),
                'requestActivity' => $this->dailyRequestActivity(14),
                'recentUsers' => User::orderByDesc('created_at')
                    ->limit(6)
                    ->get(['id', 'name', 'email', 'role', 'created_at']),
            ];
        });

        return Inertia::render('admin/Dashboard', $data);
    }

    /**
     * Cumulative user count for each of the last N days, so the chart reads as
     * a total-users-over-time line rather than a spiky per-day bar.
     *
     * @return list<array{date: string, label: string, value: int}>
     */
    private function cumulativeUserGrowth(int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $running = User::where('created_at', '<', $start)->count();

        $perDay = User::where('created_at', '>=', $start)
            ->get(['created_at'])
            ->groupBy(fn (User $user): string => $user->created_at->toDateString())
            ->map->count();

        return $this->buildSeries($start, $days, function (CarbonInterface $day) use (&$running, $perDay): int {
            $running += $perDay->get($day->toDateString(), 0);

            return $running;
        });
    }

    /**
     * Requests executed per day over the last N days.
     *
     * @return list<array{date: string, label: string, value: int}>
     */
    private function dailyRequestActivity(int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $perDay = RequestHistory::where('executed_at', '>=', $start)
            ->get(['executed_at'])
            ->groupBy(fn (RequestHistory $history): string => $history->executed_at->toDateString())
            ->map->count();

        return $this->buildSeries($start, $days, fn (CarbonInterface $day): int => $perDay->get($day->toDateString(), 0));
    }

    /**
     * Walk each day in the window and resolve its value, filling gaps so the
     * series always has one point per day (no missing dates on the chart).
     *
     * @param  callable(CarbonInterface): int  $resolve
     * @return list<array{date: string, label: string, value: int}>
     */
    private function buildSeries(CarbonInterface $start, int $days, callable $resolve): array
    {
        $series = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);

            $series[] = [
                'date' => $day->toDateString(),
                'label' => $day->format('M j'),
                'value' => $resolve($day),
            ];
        }

        return $series;
    }
}
