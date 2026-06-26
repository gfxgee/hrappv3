<?php

use App\Enum\AssetStatus;
use App\Enum\AssignmentType;
use App\Models\Asset;
use App\Models\User;
use App\Services\AssetAssignmentService;

beforeEach(function () {
    $this->service = app(AssetAssignmentService::class);
});

it('assigns an asset to an employee and opens a ledger entry', function () {
    $asset = Asset::factory()->create(['status' => AssetStatus::AVAILABLE]);
    $user = User::factory()->create();
    $admin = User::factory()->create();

    $assignment = $this->service->assign($asset, $user, AssignmentType::PERMANENT, null, 'Onboarding kit', $admin);

    $asset->refresh();

    expect($asset->status)->toBe(AssetStatus::ASSIGNED)
        ->and($asset->assigned_to)->toBe($user->id)
        ->and($assignment->type)->toBe(AssignmentType::PERMANENT)
        ->and($assignment->assigned_by)->toBe($admin->id)
        ->and($assignment->returned_at)->toBeNull()
        ->and($asset->currentAssignment->is($assignment))->toBeTrue();
});

it('marks a borrow with a due date and the borrowed status', function () {
    $asset = Asset::factory()->create(['status' => AssetStatus::AVAILABLE]);
    $user = User::factory()->create();

    $assignment = $this->service->assign($asset, $user, AssignmentType::BORROW, now()->addWeek());

    expect($asset->refresh()->status)->toBe(AssetStatus::BORROWED)
        ->and($assignment->type)->toBe(AssignmentType::BORROW)
        ->and($assignment->due_at)->not->toBeNull();
});

it('refuses to assign an asset that is already held', function () {
    $asset = Asset::factory()->create(['status' => AssetStatus::AVAILABLE]);
    $this->service->assign($asset, User::factory()->create());

    $this->service->assign($asset->refresh(), User::factory()->create());
})->throws(RuntimeException::class);

it('returns an asset, closing the assignment and freeing the unit', function () {
    $asset = Asset::factory()->create(['status' => AssetStatus::AVAILABLE]);
    $user = User::factory()->create();
    $admin = User::factory()->create();

    $assignment = $this->service->assign($asset, $user);
    $closed = $this->service->return($asset->refresh(), 'Returned in good condition', $admin);

    $asset->refresh();

    expect($asset->status)->toBe(AssetStatus::AVAILABLE)
        ->and($asset->assigned_to)->toBeNull()
        ->and($closed->id)->toBe($assignment->id)
        ->and($closed->returned_at)->not->toBeNull()
        ->and($closed->received_by)->toBe($admin->id)
        ->and($asset->currentAssignment)->toBeNull();
});

it('is a no-op when returning an asset that is not assigned', function () {
    $asset = Asset::factory()->create(['status' => AssetStatus::AVAILABLE]);

    expect($this->service->return($asset))->toBeNull()
        ->and($asset->refresh()->status)->toBe(AssetStatus::AVAILABLE);
});

it('notifies the employee when an asset is assigned to them', function () {
    $asset = Asset::factory()->create(['status' => AssetStatus::AVAILABLE]);
    $user = User::factory()->create();

    $this->service->assign($asset, $user);

    expect($user->refresh()->notifications()->count())->toBe(1);
});
