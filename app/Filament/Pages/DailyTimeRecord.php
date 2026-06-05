<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\DtrService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DailyTimeRecord extends Page
{
    protected string $view = 'filament.pages.daily-time-record';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Daily Time Record';

    protected static ?string $title = 'Daily Time Record';

    /** Roles that may view any employee's record. */
    protected const MANAGER_ROLES = ['superadmin', 'super_admin', 'hr'];

    public ?string $employeeId = null;

    public string $from = '';

    public string $until = '';

    /** Per-request memo of the computed DTR. */
    protected ?array $computed = null;

    public function mount(): void
    {
        $this->employeeId = (string) auth()->id();
        $this->from = now()->startOfMonth()->toDateString();
        $this->until = now()->endOfMonth()->toDateString();
    }

    /*
    |--------------------------------------------------------------------------
    | Access & employee scope
    |--------------------------------------------------------------------------
    */

    protected function isManager(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(static::MANAGER_ROLES);
    }

    /**
     * Employee IDs this user may view. Null means everyone (managers).
     *
     * @return list<int>|null
     */
    protected function accessibleEmployeeIds(): ?array
    {
        $user = auth()->user();

        if ($user === null) {
            return [];
        }

        if ($this->isManager()) {
            return null;
        }

        if ($user->isTeamLeader()) {
            $departmentIds = $user->ledDepartments()->pluck('departments.id');

            return User::query()
                ->whereIn('department_id', $departmentIds)
                ->pluck('id')
                ->push($user->id)
                ->unique()
                ->values()
                ->all();
        }

        return [(int) $user->id];
    }

    /**
     * @return array<int, string>
     */
    public function employeeOptions(): array
    {
        $query = User::query()->active()->orderBy('name');

        $ids = $this->accessibleEmployeeIds();

        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->pluck('name', 'id')->all();
    }

    public function canSelectEmployee(): bool
    {
        return count($this->employeeOptions()) > 1;
    }

    public function resolveEmployee(): User
    {
        $ids = $this->accessibleEmployeeIds();
        $id = (int) $this->employeeId;

        if ($id !== 0 && ($ids === null || in_array($id, $ids, true))) {
            $user = User::find($id);

            if ($user !== null) {
                return $user;
            }
        }

        return auth()->user();
    }

    /*
    |--------------------------------------------------------------------------
    | Period helpers
    |--------------------------------------------------------------------------
    */

    public function thisMonth(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->until = now()->endOfMonth()->toDateString();
    }

    public function lastMonth(): void
    {
        $this->from = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $this->until = now()->subMonthNoOverflow()->endOfMonth()->toDateString();
    }

    public function periodLabel(): string
    {
        return Carbon::parse($this->from)->toFormattedDateString().' — '.Carbon::parse($this->until)->toFormattedDateString();
    }

    /*
    |--------------------------------------------------------------------------
    | Record
    |--------------------------------------------------------------------------
    */

    /**
     * @return array{rows: list<array<string, mixed>>, totals: array<string, mixed>}
     */
    public function dtr(): array
    {
        return $this->computed ??= app(DtrService::class)->build(
            $this->resolveEmployee(),
            Carbon::parse($this->from),
            Carbon::parse($this->until),
        );
    }

    public function exportCsv(): StreamedResponse
    {
        $user = $this->resolveEmployee();
        $data = app(DtrService::class)->build($user, Carbon::parse($this->from), Carbon::parse($this->until));

        $filename = 'dtr-'.str($user->name)->slug().'-'.$this->from.'_'.$this->until.'.csv';

        return response()->streamDownload(function () use ($user, $data): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Name', $user->name]);
            fputcsv($handle, ['Email', $user->email]);
            fputcsv($handle, ['Period', $this->periodLabel()]);
            fputcsv($handle, []);
            fputcsv($handle, ['Date', 'Day', 'Time In', 'Time Out', 'Hours', 'Late (min)', 'Undertime (min)', 'OT (hrs)', 'Status']);

            foreach ($data['rows'] as $row) {
                fputcsv($handle, [
                    $row['date']->toDateString(),
                    $row['day'],
                    $row['time_in'],
                    $row['time_out'],
                    $row['hours'],
                    $row['late'],
                    $row['undertime'],
                    $row['overtime'],
                    $row['status'],
                ]);
            }

            $totals = $data['totals'];
            fputcsv($handle, []);
            fputcsv($handle, [
                'Totals', '', '', '',
                $totals['hours'], $totals['late'], $totals['undertime'], $totals['overtime'],
                "Present: {$totals['present']} · Absent: {$totals['absent']} · Leave: {$totals['leave']}",
            ]);

            fclose($handle);
        }, $filename);
    }
}
