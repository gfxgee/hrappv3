<?php

use App\Enum\AttendanceStatus;
use App\Models\Department;
use App\Models\OverTimeRequest;
use App\Models\User;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Spatie\Permission\Models\Role;

it('requires authentication to download a DTR pdf', function () {
    $employee = User::factory()->create();

    $this->get(route('dtr.pdf', ['employee' => $employee->id]))
        ->assertRedirect(); // guest → login
});

it('lets an employee download their own DTR pdf', function () {
    Pdf::fake();

    $employee = User::factory()->create(['status' => 'active']);
    $this->actingAs($employee);

    $this->get(route('dtr.pdf', [
        'employee' => $employee->id,
        'from' => now()->startOfMonth()->toDateString(),
        'until' => now()->endOfMonth()->toDateString(),
    ]))->assertOk();

    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf): bool => $pdf->viewName === 'pdf.dtr'
        && ($pdf->viewData['employee']->id ?? null) === $employee->id);
});

it('forbids an employee from downloading another employee\'s DTR pdf', function () {
    Pdf::fake();

    $me = User::factory()->create(['status' => 'active']);
    $other = User::factory()->create(['status' => 'active']);
    $this->actingAs($me);

    $this->get(route('dtr.pdf', ['employee' => $other->id]))
        ->assertForbidden();
});

it('lets a manager download any employee\'s DTR pdf', function () {
    Pdf::fake();

    Role::findOrCreate('hr');
    $manager = User::factory()->create(['status' => 'active']);
    $manager->assignRole('hr');
    $employee = User::factory()->create(['status' => 'active']);
    $this->actingAs($manager);

    $this->get(route('dtr.pdf', ['employee' => $employee->id]))->assertOk();

    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf): bool => ($pdf->viewData['employee']->id ?? null) === $employee->id);
});

it('lets a team leader download a department member\'s DTR pdf but not an outsider\'s', function () {
    Pdf::fake();

    $department = Department::factory()->create();
    $leader = User::factory()->create(['status' => 'active']);
    $leader->ledDepartments()->attach($department);
    $member = User::factory()->create(['status' => 'active', 'department_id' => $department->id]);
    $outsider = User::factory()->create(['status' => 'active']);

    $this->actingAs($leader);

    $this->get(route('dtr.pdf', ['employee' => $member->id]))->assertOk();
    $this->get(route('dtr.pdf', ['employee' => $outsider->id]))->assertForbidden();
});

it('renders the DTR pdf for a period containing overtime', function () {
    Pdf::fake();

    $employee = User::factory()->create(['status' => 'active']);
    OverTimeRequest::factory()->for($employee)->create([
        'status' => AttendanceStatus::APPROVED,
        'request_date' => now()->startOfMonth()->addDays(2),
        'hours' => 3.0,
    ]);
    $this->actingAs($employee);

    $this->get(route('dtr.pdf', [
        'employee' => $employee->id,
        'from' => now()->startOfMonth()->toDateString(),
        'until' => now()->endOfMonth()->toDateString(),
    ]))->assertOk();

    Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf): bool => $pdf->viewName === 'pdf.dtr');
});
