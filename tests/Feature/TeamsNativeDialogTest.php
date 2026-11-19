<?php

use App\Filament\Pages\PraiseWall;
use App\Filament\Widgets\ClockInOutWidget;
use App\Models\AttendanceLog;
use App\Models\Praise;
use App\Models\PraiseComment;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

/**
 * Microsoft Teams renders the app in a sandboxed iframe, where the browser
 * ignores window.confirm() and reports it as cancelled. Anything gated behind
 * `wire:confirm` therefore does nothing at all in the Teams tab — the click
 * lands, the confirm never shows, and the action is silently dropped.
 *
 * Confirmations must be Filament modals, which are ordinary DOM.
 */
beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('has no wire:confirm anywhere in the views', function () {
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('views')),
    );

    foreach ($files as $file) {
        // The attribute itself, so prose mentioning it in a comment is fine.
        if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')
            && str_contains((string) file_get_contents($file->getPathname()), 'wire:confirm=')) {
            $offenders[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file->getPathname());
        }
    }

    expect($offenders)->toBe([]);
});

it('clocks out through a confirmation modal', function () {
    $user = User::factory()->create(['status' => 'active']);
    $this->actingAs($user);

    AttendanceLog::create(['user_id' => $user->id, 'type' => 'clockin', 'device' => 'web']);

    Livewire::test(ClockInOutWidget::class)->callAction('confirmClockOut');

    expect(AttendanceLog::where('user_id', $user->id)->where('type', 'clockout')->count())->toBe(1);
});

it('does not clock out when the confirmation is never completed', function () {
    $user = User::factory()->create(['status' => 'active']);
    $this->actingAs($user);

    AttendanceLog::create(['user_id' => $user->id, 'type' => 'clockin', 'device' => 'web']);

    // Opening the modal alone must not close the shift.
    Livewire::test(ClockInOutWidget::class)->mountAction('confirmClockOut');

    expect(AttendanceLog::where('user_id', $user->id)->where('type', 'clockout')->count())->toBe(0);
});

it('deletes a comment through a confirmation modal', function () {
    $user = User::factory()->create(['status' => 'active']);
    $this->actingAs($user);

    $praise = Praise::factory()->create();
    $comment = PraiseComment::create([
        'praise_id' => $praise->id,
        'user_id' => $user->id,
        'body' => 'Nice work',
    ]);

    Livewire::test(PraiseWall::class)
        ->callAction('confirmDeleteComment', arguments: ['comment' => $comment->id]);

    expect(PraiseComment::find($comment->id))->toBeNull();
});

it('still refuses to delete a comment belonging to someone else', function () {
    $owner = User::factory()->create(['status' => 'active']);
    $intruder = User::factory()->create(['status' => 'active']);

    $praise = Praise::factory()->create();
    $comment = PraiseComment::create([
        'praise_id' => $praise->id,
        'user_id' => $owner->id,
        'body' => 'Nice work',
    ]);

    $this->actingAs($intruder);

    Livewire::test(PraiseWall::class)
        ->callAction('confirmDeleteComment', arguments: ['comment' => $comment->id]);

    expect(PraiseComment::find($comment->id))->not->toBeNull();
});
