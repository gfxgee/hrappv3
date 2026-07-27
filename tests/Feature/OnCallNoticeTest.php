<?php

use App\Enum\AttendanceStatus;
use App\Enum\LeaveType;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\Employee\OnCallWidget;
use App\Models\LeaveRequest;
use App\Models\OnCallMember;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('shows the on-call banner to this week\'s owner only', function () {
    $onCall = User::factory()->create(['name' => 'On Call', 'status' => 'active']);
    $other = User::factory()->create(['name' => 'Someone Else', 'status' => 'active']);
    OnCallMember::create(['user_id' => $onCall->id, 'position' => 1]);
    OnCallMember::create(['user_id' => $other->id, 'position' => 2]);

    $this->actingAs($onCall);
    expect((new Dashboard)->myOnCallNotice())
        ->not->toBeNull()
        ->and((new Dashboard)->myOnCallNotice()['type'])->toBe('owner');

    $this->actingAs($other);
    expect((new Dashboard)->myOnCallNotice())->toBeNull();
});

it('shows a stand-in banner to whoever covers when the owner is on leave today', function () {
    $owner = User::factory()->create(['name' => 'Owner', 'status' => 'active']);
    $standIn = User::factory()->create(['name' => 'Stand In', 'status' => 'active']);
    OnCallMember::create(['user_id' => $owner->id, 'position' => 1]);
    OnCallMember::create(['user_id' => $standIn->id, 'position' => 2]);

    LeaveRequest::factory()->create([
        'user_id' => $owner->id,
        'request_type' => LeaveType::VACATION,
        'status' => AttendanceStatus::APPROVED,
        'start_date' => today(),
        'end_date' => today(),
    ]);

    $this->actingAs($standIn);
    $notice = (new Dashboard)->myOnCallNotice();
    expect($notice['type'])->toBe('substitute')
        ->and($notice['covering_for'])->toBe('Owner');

    // The owner is out today, so they're not the effective on-call → no banner.
    $this->actingAs($owner);
    expect((new Dashboard)->myOnCallNotice())->toBeNull();
});

it('renders the team on-call widget with the current developer', function () {
    $dev = User::factory()->create(['name' => 'Weekly Dev', 'status' => 'active']);
    OnCallMember::create(['user_id' => $dev->id, 'position' => 1]);

    $this->actingAs(User::factory()->create(['status' => 'active']));

    Livewire::test(OnCallWidget::class)
        ->assertSuccessful()
        ->assertSee('Weekly Dev')
        ->assertSee('On-call this week');
});

it('hides the team on-call widget when the roster is empty', function () {
    $this->actingAs(User::factory()->create(['status' => 'active']));

    Livewire::test(OnCallWidget::class)
        ->assertSuccessful()
        ->assertDontSee('On-call this week');
});
