<?php

namespace App\Filament\Pages;

use App\Models\Department;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * A company-wide org chart built from each employee's manager (reports-to).
 * The root is the employee flagged as the company head; anyone not reachable
 * from a head (no manager set yet) is listed separately as "Unassigned".
 */
class OrgChart extends Page
{
    protected string $view = 'filament.pages.org-chart';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    protected static string|\UnitEnum|null $navigationGroup = 'My Workspace';

    protected static ?string $navigationLabel = 'Org Chart';

    protected static ?int $navigationSort = 8;

    /**
     * Chart nodes (flat, for d3-org-chart) plus the unassigned bucket.
     *
     * @return array{nodes: list<array<string, mixed>>, unassigned: list<array<string, mixed>>}
     */
    public function chartData(): array
    {
        $users = User::query()
            ->active()
            ->get(['id', 'name', 'job_title', 'department_id', 'manager_id', 'is_org_head', 'photo'])
            ->keyBy('id');

        $departments = Department::query()->pluck('name', 'id');
        $childrenByManager = $users->groupBy('manager_id');
        $heads = $users->filter(fn (User $u): bool => $u->is_org_head);

        // Everyone reachable from a head, walking down the reports-to chain.
        $seen = [];
        $reachable = [];
        $queue = $heads->values()->all();

        while ($queue !== []) {
            $user = array_shift($queue);

            if (isset($seen[$user->id])) {
                continue;
            }

            $seen[$user->id] = true;
            $reachable[] = $user;

            foreach ($childrenByManager->get($user->id, collect()) as $child) {
                if (! isset($seen[$child->id])) {
                    $queue[] = $child;
                }
            }
        }

        $multipleHeads = $heads->count() > 1;
        $rootId = '__root__';

        $nodes = array_map(fn (User $user): array => [
            'id' => (string) $user->id,
            'parentId' => $user->is_org_head
                ? ($multipleHeads ? $rootId : '')
                : (string) $user->manager_id,
            ...$this->card($user, $departments),
        ], $reachable);

        // With more than one head, give them a single shared company root.
        if ($multipleHeads && $nodes !== []) {
            array_unshift($nodes, [
                'id' => $rootId,
                'parentId' => '',
                'name' => config('app.name', 'Company'),
                'title' => 'Organization',
                'department' => null,
                'imageUrl' => null,
                'initials' => '🏢',
            ]);
        }

        $unassigned = $users
            ->reject(fn (User $user): bool => isset($seen[$user->id]))
            ->sortBy('name')
            ->map(fn (User $user): array => $this->card($user, $departments))
            ->values()
            ->all();

        return ['nodes' => $nodes, 'unassigned' => $unassigned];
    }

    /**
     * The display fields shown on a node / unassigned chip.
     *
     * @param  Collection<int, string>  $departments
     * @return array{name: string, title: ?string, department: ?string, imageUrl: ?string, initials: string}
     */
    protected function card(User $user, $departments): array
    {
        return [
            'name' => $user->name ?? '',
            'title' => $user->job_title,
            'department' => $departments[$user->department_id] ?? null,
            'imageUrl' => blank($user->photo) ? null : Storage::disk('public')->url($user->photo),
            'initials' => $this->initials($user->name),
        ];
    }

    protected function initials(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return '?';
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';

        return mb_strtoupper($first.$last);
    }
}
