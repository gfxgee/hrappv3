<?php

use App\Enum\AssignmentType;
use App\Filament\Widgets\Employee\MyEquipmentWidget;
use App\Models\Asset;
use App\Models\User;
use App\Services\AssetAssignmentService;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('lists the equipment currently assigned to the signed-in employee', function () {
    $user = User::factory()->create(['status' => 'active']);
    $asset = Asset::factory()->create(['name' => 'Corsair Vengeance 8GB']);
    app(AssetAssignmentService::class)->assign($asset, $user, AssignmentType::BORROW, now()->addWeek());

    $this->actingAs($user);

    $rows = (new MyEquipmentWidget)->equipment();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['name'])->toBe('Corsair Vengeance 8GB')
        ->and($rows[0]['type'])->toBe(AssignmentType::BORROW->label())
        ->and($rows[0]['due'])->not->toBeNull();
});

it('does not show another employee\'s equipment', function () {
    $owner = User::factory()->create(['status' => 'active']);
    $other = User::factory()->create(['status' => 'active']);
    app(AssetAssignmentService::class)->assign(Asset::factory()->create(), $other);

    $this->actingAs($owner);

    expect((new MyEquipmentWidget)->equipment())->toHaveCount(0);
});

it('renders the widget for the holder', function () {
    $user = User::factory()->create(['status' => 'active']);
    $asset = Asset::factory()->create(['name' => 'Dell P2422H']);
    app(AssetAssignmentService::class)->assign($asset, $user);

    $this->actingAs($user);

    Livewire::test(MyEquipmentWidget::class)
        ->assertSuccessful()
        ->assertSee('Dell P2422H');
});
