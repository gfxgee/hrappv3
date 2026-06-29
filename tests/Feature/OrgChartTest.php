<?php

use App\Filament\Pages\OrgChart;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('builds a tree from the company head down through managers', function () {
    $ceo = User::factory()->create(['status' => 'active', 'is_org_head' => true, 'name' => 'Boss']);
    $manager = User::factory()->create(['status' => 'active', 'manager_id' => $ceo->id, 'name' => 'Manager']);
    $staff = User::factory()->create(['status' => 'active', 'manager_id' => $manager->id, 'name' => 'Staff']);
    $lonely = User::factory()->create(['status' => 'active', 'name' => 'Lonely']);

    $this->actingAs($ceo);
    $data = (new OrgChart)->chartData();
    $byId = collect($data['nodes'])->keyBy('id');

    expect($byId)->toHaveCount(3)
        ->and($byId[(string) $ceo->id]['parentId'])->toBe('')
        ->and($byId[(string) $manager->id]['parentId'])->toBe((string) $ceo->id)
        ->and($byId[(string) $staff->id]['parentId'])->toBe((string) $manager->id)
        ->and($byId->has((string) $lonely->id))->toBeFalse()
        ->and(collect($data['unassigned'])->pluck('name'))->toContain('Lonely');
});

it('adds a synthetic company root when there is more than one head', function () {
    $a = User::factory()->create(['status' => 'active', 'is_org_head' => true]);
    $b = User::factory()->create(['status' => 'active', 'is_org_head' => true]);
    $this->actingAs($a);

    $nodes = collect((new OrgChart)->chartData()['nodes']);

    expect($nodes->firstWhere('id', '__root__'))->not->toBeNull()
        ->and($nodes->firstWhere('id', (string) $a->id)['parentId'])->toBe('__root__')
        ->and($nodes->firstWhere('id', (string) $b->id)['parentId'])->toBe('__root__');
});

it('shows the empty state when no company head is set', function () {
    $this->actingAs(User::factory()->create(['status' => 'active']));

    Livewire::test(OrgChart::class)
        ->assertSuccessful()
        ->assertSee('No org chart yet');
});

it('renders the chart once a head and a report exist', function () {
    $ceo = User::factory()->create(['status' => 'active', 'is_org_head' => true]);
    User::factory()->create(['status' => 'active', 'manager_id' => $ceo->id]);

    $this->actingAs($ceo);

    Livewire::test(OrgChart::class)->assertSuccessful();
});

it('computes descendant ids to prevent reporting loops', function () {
    $ceo = User::factory()->create();
    $manager = User::factory()->create(['manager_id' => $ceo->id]);
    $staff = User::factory()->create(['manager_id' => $manager->id]);

    expect($ceo->descendantIds())->toContain($manager->id, $staff->id)
        ->and($staff->descendantIds())->toBe([]);
});
