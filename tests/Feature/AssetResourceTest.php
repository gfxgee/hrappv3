<?php

use App\Enum\AssetCategory;
use App\Enum\AssetStatus;
use App\Enum\AssignmentType;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Assets\Pages\CreateAsset;
use App\Filament\Resources\Assets\Pages\EditAsset;
use App\Filament\Resources\Assets\Pages\ListAssets;
use App\Models\Asset;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');

    Role::findOrCreate('hr');
    $this->manager = User::factory()->create();
    $this->manager->assignRole('hr');
    $this->actingAs($this->manager);
});

it('renders the list page', function () {
    Livewire::test(ListAssets::class)->assertSuccessful();
});

it('grants asset access to managers but not to plain employees', function () {
    expect(AssetResource::canAccess())->toBeTrue();

    $this->actingAs(User::factory()->create());
    expect(AssetResource::canAccess())->toBeFalse();
});

it('creates an asset and auto-generates a sequential asset tag', function () {
    Livewire::test(CreateAsset::class)
        ->fillForm([
            'category' => AssetCategory::RAM->value,
            'name' => 'Corsair Vengeance 8GB',
            'brand' => 'Corsair',
            'status' => AssetStatus::AVAILABLE->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $asset = Asset::firstOrFail();

    expect($asset->name)->toBe('Corsair Vengeance 8GB')
        ->and($asset->category)->toBe(AssetCategory::RAM)
        ->and($asset->status)->toBe(AssetStatus::AVAILABLE)
        ->and($asset->asset_tag)->toBe('AST-'.str_pad((string) $asset->id, 5, '0', STR_PAD_LEFT));
});

it('keeps a manually entered asset tag', function () {
    Livewire::test(CreateAsset::class)
        ->fillForm([
            'asset_tag' => 'CUSTOM-1',
            'category' => AssetCategory::MONITOR->value,
            'name' => 'Dell P2422H',
            'status' => AssetStatus::AVAILABLE->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Asset::firstOrFail()->asset_tag)->toBe('CUSTOM-1');
});

it('logs edits to an asset', function () {
    $asset = Asset::factory()->create(['status' => AssetStatus::AVAILABLE]);

    Livewire::test(EditAsset::class, ['record' => $asset->getRouteKey()])
        ->fillForm(['status' => AssetStatus::MAINTENANCE->value])
        ->call('save')
        ->assertHasNoFormErrors();

    $activity = Activity::query()
        ->where('subject_type', Asset::class)
        ->where('subject_id', $asset->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($asset->refresh()->status)->toBe(AssetStatus::MAINTENANCE)
        ->and($activity)->not->toBeNull()
        ->and(data_get($activity->properties->toArray(), 'attributes.status'))
        ->toBe(AssetStatus::MAINTENANCE->value);
});

it('soft-deletes an asset', function () {
    $asset = Asset::factory()->create();

    $asset->delete();

    expect(Asset::find($asset->id))->toBeNull()
        ->and(Asset::withTrashed()->find($asset->id)?->trashed())->toBeTrue();
});

it('filters assets by status', function () {
    $available = Asset::factory()->create(['status' => AssetStatus::AVAILABLE]);
    $retired = Asset::factory()->create(['status' => AssetStatus::RETIRED]);

    Livewire::test(ListAssets::class)
        ->filterTable('status', AssetStatus::AVAILABLE->value)
        ->assertCanSeeTableRecords([$available])
        ->assertCanNotSeeTableRecords([$retired]);
});

it('assigns an asset to an employee from the table action', function () {
    $asset = Asset::factory()->create(['status' => AssetStatus::AVAILABLE]);
    $employee = User::factory()->create();

    Livewire::test(ListAssets::class)
        ->callTableAction('assign', $asset, data: [
            'user_id' => $employee->id,
            'type' => AssignmentType::PERMANENT->value,
        ])
        ->assertHasNoTableActionErrors();

    $asset->refresh();

    expect($asset->status)->toBe(AssetStatus::ASSIGNED)
        ->and($asset->assigned_to)->toBe($employee->id)
        ->and($asset->assignments()->open()->count())->toBe(1);
});

it('returns an assigned asset from the table action', function () {
    $employee = User::factory()->create();
    $asset = Asset::factory()->assignedTo($employee)->create();
    $asset->assignments()->create([
        'user_id' => $employee->id,
        'type' => AssignmentType::PERMANENT,
        'assigned_at' => now(),
    ]);

    Livewire::test(ListAssets::class)
        ->callTableAction('return', $asset)
        ->assertHasNoTableActionErrors();

    $asset->refresh();

    expect($asset->status)->toBe(AssetStatus::AVAILABLE)
        ->and($asset->assigned_to)->toBeNull()
        ->and($asset->assignments()->open()->count())->toBe(0);
});

it('hides assign on a held asset and return on an available one', function () {
    $available = Asset::factory()->create(['status' => AssetStatus::AVAILABLE]);
    $held = Asset::factory()->assignedTo(User::factory()->create())->create();

    Livewire::test(ListAssets::class)
        ->assertTableActionVisible('assign', $available)
        ->assertTableActionHidden('return', $available)
        ->assertTableActionVisible('return', $held)
        ->assertTableActionHidden('assign', $held);
});
