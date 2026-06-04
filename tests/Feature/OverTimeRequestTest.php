<?php

use App\Enum\AttendanceStatus;
use App\Filament\Pages\FileOverTimeRequest;
use App\Filament\Resources\OverTimeRequests\OverTimeRequestResource;
use App\Filament\Resources\OverTimeRequests\Pages\EditOverTimeRequest;
use App\Filament\Resources\OverTimeRequests\Pages\ListOverTimeRequests;
use App\Models\Department;
use App\Models\OverTimeRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

function overtimeManager(string $role): User
{
    Role::findOrCreate($role);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('allows manager roles to access the overtime resource', function (string $role) {
    $this->actingAs(overtimeManager($role));

    expect(OverTimeRequestResource::canAccess())->toBeTrue();
})->with(['superadmin', 'super_admin', 'hr']);

it('allows a department leader to access the overtime resource without a manager role', function () {
    $leader = User::factory()->create();
    $leader->ledDepartments()->attach(Department::factory()->create());
    $this->actingAs($leader);

    expect(OverTimeRequestResource::canAccess())->toBeTrue();
});

it('denies regular users access to the overtime resource', function () {
    $this->actingAs(User::factory()->create());

    expect(OverTimeRequestResource::canAccess())->toBeFalse();
});

it('badges the count of pending overtime requests', function () {
    $this->actingAs(overtimeManager('hr'));

    expect(OverTimeRequestResource::getNavigationBadge())->toBeNull();

    OverTimeRequest::factory()->count(3)->create(['status' => AttendanceStatus::FOR_APPROVAL]);
    OverTimeRequest::factory()->approved()->create();

    expect(OverTimeRequestResource::getNavigationBadge())->toBe('3');
});

it('renders the overtime list for a manager', function () {
    $this->actingAs(overtimeManager('hr'));

    Livewire::test(ListOverTimeRequests::class)->assertSuccessful();
});

it('renders the overtime edit page', function () {
    $this->actingAs(overtimeManager('hr'));
    $ot = OverTimeRequest::factory()->create();

    Livewire::test(EditOverTimeRequest::class, ['record' => $ot->getRouteKey()])
        ->assertSuccessful();

    expect(OverTimeRequestResource::getRecordTitle($ot))->toBeString();
});

it('bulk-approves selected pending overtime requests and stamps the date', function () {
    $this->actingAs(overtimeManager('hr'));

    $pending = OverTimeRequest::factory()->count(2)->create(['status' => AttendanceStatus::FOR_APPROVAL]);

    Livewire::test(ListOverTimeRequests::class)
        ->callTableBulkAction('approveSelected', $pending);

    $pending->each(function ($ot) {
        $ot->refresh();
        expect($ot->status)->toBe(AttendanceStatus::APPROVED)
            ->and($ot->approved_date)->not->toBeNull();
    });
});

it('bulk-rejects selected pending overtime requests', function () {
    $this->actingAs(overtimeManager('hr'));

    $pending = OverTimeRequest::factory()->count(2)->create(['status' => AttendanceStatus::FOR_APPROVAL]);

    Livewire::test(ListOverTimeRequests::class)
        ->callTableBulkAction('rejectSelected', $pending);

    $pending->each(fn ($ot) => expect($ot->refresh()->status)->toBe(AttendanceStatus::REJECTED));
});

it('approves an overtime request and stamps the approved date', function () {
    $this->actingAs(overtimeManager('hr'));
    $ot = OverTimeRequest::factory()->create([
        'status' => AttendanceStatus::FOR_APPROVAL,
        'approved_date' => null,
    ]);

    Livewire::test(ListOverTimeRequests::class)
        ->callTableAction('approve', $ot);

    $ot->refresh();

    expect($ot->status)->toBe(AttendanceStatus::APPROVED)
        ->and($ot->approved_date)->not->toBeNull();
});

it('rejects an overtime request', function () {
    $this->actingAs(overtimeManager('hr'));
    $ot = OverTimeRequest::factory()->create(['status' => AttendanceStatus::FOR_APPROVAL]);

    Livewire::test(ListOverTimeRequests::class)
        ->callTableAction('reject', $ot);

    expect($ot->refresh()->status)->toBe(AttendanceStatus::REJECTED);
});

it('renders the file overtime page', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(FileOverTimeRequest::class)->assertSuccessful();
});

it('files an overtime request for the current user as for-approval', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(FileOverTimeRequest::class)
        ->fillForm([
            'request_date' => '2026-07-10',
            'hours' => 2.5,
            'reason' => 'Production deployment after hours',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $ot = OverTimeRequest::where('user_id', $user->id)->firstOrFail();

    expect((float) $ot->hours)->toBe(2.5)
        ->and($ot->status)->toBe(AttendanceStatus::FOR_APPROVAL);
});

it('only lists the current user\'s overtime on the page', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $mine = OverTimeRequest::factory()->for($user)->create();
    $theirs = OverTimeRequest::factory()->for($other)->create();

    $this->actingAs($user);

    Livewire::test(FileOverTimeRequest::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('lets an employee edit their own pending overtime request', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $ot = OverTimeRequest::factory()->for($user)->create([
        'status' => AttendanceStatus::FOR_APPROVAL,
        'hours' => 1.0,
        'reason' => 'old reason',
    ]);

    Livewire::test(FileOverTimeRequest::class)
        ->callTableAction('edit', $ot, data: [
            'request_date' => '2026-08-01',
            'hours' => 3.0,
            'reason' => 'updated reason',
        ])
        ->assertHasNoTableActionErrors();

    $ot->refresh();
    expect((float) $ot->hours)->toBe(3.0)
        ->and($ot->reason)->toBe('updated reason');
});

it('hides edit and cancel actions once an overtime request is rejected', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $ot = OverTimeRequest::factory()->for($user)->create([
        'status' => AttendanceStatus::REJECTED,
    ]);

    Livewire::test(FileOverTimeRequest::class)
        ->assertTableActionHidden('edit', $ot)
        ->assertTableActionHidden('cancel', $ot);
});

it('lets an employee cancel an approved overtime request', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $ot = OverTimeRequest::factory()->for($user)->create([
        'status' => AttendanceStatus::APPROVED,
    ]);

    Livewire::test(FileOverTimeRequest::class)
        ->callTableAction('cancel', $ot);

    expect($ot->refresh()->status)->toBe(AttendanceStatus::CANCELLED);
});

it('computes approved hours this month and pending hours', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    OverTimeRequest::factory()->for($user)->create([
        'status' => AttendanceStatus::APPROVED,
        'hours' => 4.0,
        'request_date' => now()->startOfMonth()->addDays(2),
        'approved_date' => now(),
    ]);
    OverTimeRequest::factory()->for($user)->create([
        'status' => AttendanceStatus::APPROVED,
        'hours' => 2.5,
        'request_date' => now()->startOfMonth()->addDays(5),
        'approved_date' => now(),
    ]);
    // Approved but in a previous month — excluded from the month total.
    OverTimeRequest::factory()->for($user)->create([
        'status' => AttendanceStatus::APPROVED,
        'hours' => 999,
        'request_date' => now()->subMonth(),
        'approved_date' => now()->subMonth(),
    ]);
    OverTimeRequest::factory()->for($user)->create([
        'status' => AttendanceStatus::FOR_APPROVAL,
        'hours' => 1.5,
    ]);

    $page = new FileOverTimeRequest;

    expect($page->getApprovedHoursThisMonth())->toBe(6.5)
        ->and($page->getPendingHours())->toBe(1.5);
});
