<?php

use App\Filament\Team\Resources\TrainingAssignmentResource;
use App\Models\DocumentAcknowledgement;
use App\Models\Employee;
use App\Models\EmployeeProgram;
use App\Models\EmployeeProgramItem;
use App\Models\StandardOperatingProcedure;
use App\Models\TrainingAssignment;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function trainingUser(string $role, bool $withEmployee = false): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));

    if ($withEmployee) {
        Employee::create([
            'user_id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => true,
            'christy_location' => 'colma',
            'preferred_locale' => 'en',
        ]);
    }

    return $user;
}

function trainingRichContent(string $text): array
{
    return [
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => $text]],
        ]],
    ];
}

function publishedTrainingPolicy(User $manager): StandardOperatingProcedure
{
    $policy = StandardOperatingProcedure::create([
        'document_type' => StandardOperatingProcedure::TYPE_POLICY,
        'code' => 'POL-TEST-001',
        'title' => 'Test Cell Phone Policy',
        'category' => 'human_resources',
        'summary' => 'Rules for personal cell phones.',
        'audience' => StandardOperatingProcedure::AUDIENCE_ALL_EMPLOYEES,
        'owner_user_id' => $manager->getKey(),
        'acknowledgement_required' => true,
        'draft_content' => trainingRichContent('Personal cell phones must remain stored during active work.'),
        'draft_acknowledgement_text' => 'I acknowledge that I received and reviewed the Test Cell Phone Policy.',
        'draft_effective_date' => today(),
        'default_locale' => 'en',
    ]);
    $policy->publishDraft($manager);

    return $policy->fresh();
}

function publishedTrainingProgram(User $manager, StandardOperatingProcedure $policy): EmployeeProgram
{
    $program = EmployeeProgram::create([
        'title' => 'Test Workplace Safety Training',
        'category' => 'safety',
        'summary' => 'A test training program.',
        'introduction' => trainingRichContent('Review every required item.'),
        'audience' => EmployeeProgram::AUDIENCE_ALL_EMPLOYEES,
        'owner_user_id' => $manager->getKey(),
        'status' => EmployeeProgram::STATUS_DRAFT,
        'training_enabled' => true,
        'passing_score' => 80,
        'estimated_minutes' => 15,
        'default_locale' => 'en',
    ]);
    $section = $program->sections()->create(['title' => 'Required material']);
    $section->items()->create([
        'type' => EmployeeProgramItem::TYPE_PROCEDURE,
        'standard_operating_procedure_id' => $policy->getKey(),
        'required_for_completion' => true,
    ]);
    $program->trainingQuestions()->create([
        'prompt' => 'When may a personal cell phone be used during active work?',
        'options' => [
            ['label' => 'Whenever convenient', 'correct' => false],
            ['label' => 'Only as allowed by the policy', 'correct' => true],
        ],
        'explanation' => 'Follow the published policy.',
        'is_active' => true,
    ]);
    $program->publish();

    return $program->fresh();
}

beforeEach(function (): void {
    Filament::setCurrentPanel('team');
});

it('publishes policies with immutable acknowledgement language and records employee evidence', function (): void {
    $manager = trainingUser('manager');
    $employeeUser = trainingUser('employee', true);
    $employeeUser->givePermissionTo(User::VIEW_PROCEDURES_PERMISSION);
    $policy = publishedTrainingPolicy($manager);
    $revision = $policy->currentRevision;

    $this->actingAs($employeeUser);

    $acknowledgement = $revision->acknowledge(
        $employeeUser->employee,
        $employeeUser,
        $employeeUser->employee->name,
    );

    expect($acknowledgement->method)->toBe(DocumentAcknowledgement::METHOD_AUTHENTICATED)
        ->and($acknowledgement->standard_operating_procedure_revision_id)->toBe($revision->getKey())
        ->and($acknowledgement->acknowledgement_text)->toBe($revision->acknowledgement_text)
        ->and($acknowledgement->locale)->toBe('en')
        ->and($acknowledgement->evidence_hash)->toHaveLength(64);

    $policy->update([
        'draft_content' => trainingRichContent('Updated cell phone requirements.'),
        'draft_acknowledgement_text' => 'I acknowledge the updated Test Cell Phone Policy.',
        'draft_change_summary' => 'Updated the requirements.',
    ]);
    $secondRevision = $policy->fresh()->publishDraft($manager);

    expect($secondRevision->acknowledgementFor($employeeUser->employee))->toBeNull()
        ->and($revision->fresh()->acknowledgementFor($employeeUser->employee)?->is($acknowledgement))->toBeTrue();
});

it('allows managers to preserve a signed paper acknowledgement without impersonating the employee', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('policies/acknowledgements/signed-policy.pdf', '%PDF-1.4 signed policy');
    $manager = trainingUser('manager');
    $employeeUser = trainingUser('employee', true);
    $policy = publishedTrainingPolicy($manager);

    $acknowledgement = $policy->currentRevision->recordPaperAcknowledgement(
        $employeeUser->employee,
        $manager,
        $employeeUser->employee->name,
        Carbon::parse('2026-08-01 17:00:00'),
        'policies/acknowledgements/signed-policy.pdf',
    );

    expect($acknowledgement->method)->toBe(DocumentAcknowledgement::METHOD_PAPER_IMPORT)
        ->and($acknowledgement->recorded_by_user_id)->toBe($manager->getKey())
        ->and($acknowledgement->user_id)->toBe($employeeUser->getKey())
        ->and($acknowledgement->evidence_file_path)->toBe('policies/acknowledgements/signed-policy.pdf');

    $this->actingAs($manager)
        ->get(route('policy-acknowledgements.evidence.show', $acknowledgement))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->actingAs($employeeUser)
        ->get(route('policy-acknowledgements.evidence.show', $acknowledgement))
        ->assertForbidden();
});

it('keeps questionnaire evidence and policy acknowledgement separate while rolling them into training completion', function (): void {
    $manager = trainingUser('manager');
    $employeeUser = trainingUser('employee', true);
    $employeeUser->givePermissionTo([
        User::VIEW_PROCEDURES_PERMISSION,
        User::VIEW_PROGRAMS_PERMISSION,
        User::VIEW_TRAINING_PERMISSION,
    ]);
    $policy = publishedTrainingPolicy($manager);
    $program = publishedTrainingProgram($manager, $policy);
    $assignment = TrainingAssignment::create([
        'employee_program_id' => $program->getKey(),
        'employee_id' => $employeeUser->employee->getKey(),
        'assigned_by_user_id' => $manager->getKey(),
        'due_date' => today()->addWeek(),
    ]);

    $originalPrompt = data_get($assignment->content_snapshot, 'questions.0.prompt');
    $program->trainingQuestions()->firstOrFail()->update(['prompt' => 'A later edited question']);

    $this->actingAs($employeeUser);
    $attempt = $assignment->submitQuestionnaire($employeeUser, ['0' => '1']);

    expect($attempt->passed)->toBeTrue()
        ->and($attempt->score)->toBe(100)
        ->and($assignment->fresh()->canComplete())->toBeFalse()
        ->and(data_get($assignment->fresh()->content_snapshot, 'questions.0.prompt'))->toBe($originalPrompt);

    $policy->currentRevision->acknowledge(
        $employeeUser->employee,
        $employeeUser,
        $employeeUser->employee->name,
    );

    $assignment->refresh();
    expect($assignment->canComplete())->toBeTrue();

    $assignment->complete($employeeUser);

    expect($assignment->fresh()->status)->toBe(TrainingAssignment::STATUS_COMPLETED)
        ->and($assignment->fresh()->completed_at)->not->toBeNull()
        ->and($assignment->fresh()->completion_certification)->toBe(TrainingAssignment::COMPLETION_CERTIFICATION);
});

it('keeps training hidden until roles receive permission and scopes employees to their own assignments', function (): void {
    $manager = trainingUser('manager');
    $first = trainingUser('employee', true);
    $second = trainingUser('employee', true);
    $policy = publishedTrainingPolicy($manager);
    $program = publishedTrainingProgram($manager, $policy);
    $firstAssignment = TrainingAssignment::create([
        'employee_program_id' => $program->getKey(),
        'employee_id' => $first->employee->getKey(),
        'assigned_by_user_id' => $manager->getKey(),
    ]);
    $secondAssignment = TrainingAssignment::create([
        'employee_program_id' => $program->getKey(),
        'employee_id' => $second->employee->getKey(),
        'assigned_by_user_id' => $manager->getKey(),
    ]);

    $this->actingAs($first);
    expect(TrainingAssignmentResource::canViewAny())->toBeFalse();
    $this->get('/team/training')->assertForbidden();

    $first->givePermissionTo(User::VIEW_TRAINING_PERMISSION);

    expect(TrainingAssignment::query()->visibleTo($first)->pluck('id')->all())
        ->toBe([$firstAssignment->getKey()]);

    $this->get('/team/training')->assertOk()->assertSee($program->title);
    $this->get("/team/training/{$secondAssignment->getKey()}")->assertNotFound();
});

it('seeds training permissions only for management roles', function (): void {
    $manager = trainingUser('manager');
    $employee = trainingUser('employee', true);

    expect($manager->canViewTraining())->toBeTrue()
        ->and($manager->canManageTraining())->toBeTrue()
        ->and($employee->canViewTraining())->toBeFalse()
        ->and($employee->canManageTraining())->toBeFalse();
});

it('renders policy authoring and manager training reporting in the team panel', function (): void {
    $manager = trainingUser('manager');
    $employee = trainingUser('employee', true);
    $policy = publishedTrainingPolicy($manager);
    $program = publishedTrainingProgram($manager, $policy);
    $assignment = TrainingAssignment::create([
        'employee_program_id' => $program->getKey(),
        'employee_id' => $employee->employee->getKey(),
        'assigned_by_user_id' => $manager->getKey(),
    ]);

    $this->actingAs($manager)
        ->get("/team/procedures/{$policy->getKey()}/edit")
        ->assertOk()
        ->assertSee('Document type')
        ->assertSee('Employee acknowledgment');

    $this->get('/team/training')
        ->assertOk()
        ->assertSee('Assign training')
        ->assertSee($program->title)
        ->assertSee($employee->name);

    $this->get('/team')
        ->assertOk()
        ->assertSee('Training assignments')
        ->assertSee($program->title);

    $this->get("/team/training/{$assignment->getKey()}")
        ->assertOk()
        ->assertSee('Required policies')
        ->assertSee('Questionnaire');
});
