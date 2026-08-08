<?php

use App\Filament\Team\Resources\EmployeeProgramResource;
use App\Filament\Team\Resources\EmployeeProgramResource\Pages\ListEmployeePrograms;
use App\Models\Employee;
use App\Models\EmployeeProgram;
use App\Models\EmployeeProgramItem;
use App\Models\Position;
use App\Models\StandardOperatingProcedure;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function programDocumentContent(string $text): array
{
    return [
        'type' => 'doc',
        'content' => [[
            'type' => 'paragraph',
            'content' => [[
                'type' => 'text',
                'text' => $text,
            ]],
        ]],
    ];
}

function employeeProgramUser(string $role, ?string $plant = null, array $positions = []): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));

    if ($plant !== null) {
        $employee = Employee::create([
            'user_id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => true,
            'christy_location' => $plant,
        ]);

        $positionIds = collect($positions)
            ->map(fn (string $position): int => Position::query()->firstOrCreate(
                ['name' => $position],
                ['display_name' => str($position)->headline()],
            )->getKey());

        $employee->positions()->sync($positionIds);
    }

    return $user;
}

function allowProgramViewing(User $user): User
{
    $user->givePermissionTo(User::VIEW_PROGRAMS_PERMISSION);

    return $user;
}

function testEmployeeProgram(User $manager, array $overrides = []): EmployeeProgram
{
    static $sequence = 0;
    $sequence++;

    return EmployeeProgram::create([
        'title' => "Test Program {$sequence}",
        'category' => 'safety',
        'summary' => 'A test employee resource program.',
        'introduction' => programDocumentContent("Program introduction {$sequence}"),
        'audience' => EmployeeProgram::AUDIENCE_ALL_EMPLOYEES,
        'owner_user_id' => $manager->getKey(),
        ...$overrides,
    ]);
}

function publishedTestProcedure(User $manager, string $title, array $overrides = []): StandardOperatingProcedure
{
    static $sequence = 0;
    $sequence++;

    $procedure = StandardOperatingProcedure::create([
        'code' => 'SOP-PRG-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
        'title' => $title,
        'category' => 'safety',
        'summary' => 'Program-linked procedure.',
        'audience' => StandardOperatingProcedure::AUDIENCE_ALL_EMPLOYEES,
        'owner_user_id' => $manager->getKey(),
        'draft_content' => programDocumentContent("Instructions for {$title}"),
        'draft_effective_date' => today(),
        ...$overrides,
    ]);
    $procedure->publishDraft($manager);

    return $procedure;
}

beforeEach(function (): void {
    Filament::setCurrentPanel('team');
});

it('keeps program authoring in Team and restricts it to managers', function (): void {
    $manager = employeeProgramUser('manager');

    $this->actingAs($manager)
        ->get('/team/programs')
        ->assertOk()
        ->assertSee('Programs');
    $this->get('/team/programs/create')
        ->assertOk()
        ->assertSee('Add section')
        ->assertSee('height: 22rem; min-height: 16rem; resize: vertical; overflow: auto;', false);

    expect(EmployeeProgramResource::canCreate())->toBeTrue();

    $program = testEmployeeProgram($manager);
    $program->sections()->create(['title' => 'First section']);

    $this->get("/team/programs/{$program->getKey()}/edit")
        ->assertOk()
        ->assertSee('Add program item');

    $employee = employeeProgramUser('employee', 'colma');

    $this->actingAs($employee);
    $this->get('/team/programs')->assertForbidden();
    $this->get('/team/programs/create')->assertForbidden();

    allowProgramViewing($employee);

    $this->get('/team/programs')
        ->assertOk()
        ->assertDontSee('New program')
        ->assertDontSee('Create program');
    $this->get('/team/programs/create')->assertForbidden();

    expect(EmployeeProgramResource::canViewAny())->toBeTrue()
        ->and(EmployeeProgramResource::canCreate())->toBeFalse();
});

it('seeds program permissions only onto management roles', function (): void {
    $manager = employeeProgramUser('manager');
    $employee = employeeProgramUser('employee', 'colma');
    $driver = employeeProgramUser('driver', 'colma', ['driver']);

    expect($manager->canViewPrograms())->toBeTrue()
        ->and($manager->canManagePrograms())->toBeTrue()
        ->and($employee->canViewPrograms())->toBeFalse()
        ->and($employee->canManagePrograms())->toBeFalse()
        ->and($driver->canViewPrograms())->toBeFalse()
        ->and($driver->canManagePrograms())->toBeFalse();
});

it('shows employees only published programs for their plant and position', function (): void {
    $manager = employeeProgramUser('manager');
    $driver = allowProgramViewing(employeeProgramUser('driver', 'colma', ['driver']));

    $everyone = testEmployeeProgram($manager, ['title' => 'Everyone Program']);
    $everyone->sections()->create(['title' => 'Start here'])->items()->create([
        'type' => EmployeeProgramItem::TYPE_LINK,
        'title' => 'Company resource',
        'external_url' => 'https://example.com/resource',
    ]);
    $everyone->publish();

    $driverOnly = testEmployeeProgram($manager, [
        'title' => 'Driver Program',
        'audience' => EmployeeProgram::AUDIENCE_SELECTED_POSITIONS,
    ]);
    $driverOnly->positions()->attach(Position::query()->where('name', 'driver')->firstOrFail());
    $driverOnly->sections()->create(['title' => 'Driver resources'])->items()->create([
        'type' => EmployeeProgramItem::TYPE_LINK,
        'title' => 'Driver resource',
        'external_url' => 'https://example.com/driver',
    ]);
    $driverOnly->publish();

    $productionOnly = testEmployeeProgram($manager, [
        'title' => 'Production Program',
        'audience' => EmployeeProgram::AUDIENCE_SELECTED_POSITIONS,
    ]);
    $productionOnly->positions()->attach(Position::query()->firstOrCreate(
        ['name' => 'production'],
        ['display_name' => 'Production'],
    ));
    $productionOnly->sections()->create(['title' => 'Production'])->items()->create([
        'type' => EmployeeProgramItem::TYPE_LINK,
        'title' => 'Production resource',
        'external_url' => 'https://example.com/production',
    ]);
    $productionOnly->publish();

    $tulareOnly = testEmployeeProgram($manager, [
        'title' => 'Tulare Program',
        'plant_locations' => ['tulare'],
    ]);
    $tulareOnly->sections()->create(['title' => 'Tulare'])->items()->create([
        'type' => EmployeeProgramItem::TYPE_LINK,
        'title' => 'Tulare resource',
        'external_url' => 'https://example.com/tulare',
    ]);
    $tulareOnly->publish();

    $draft = testEmployeeProgram($manager, ['title' => 'Draft Program']);

    $visibleIds = EmployeeProgram::query()->visibleTo($driver)->pluck('id')->all();

    expect($visibleIds)->toContain($everyone->getKey(), $driverOnly->getKey())
        ->and($visibleIds)->not->toContain(
            $productionOnly->getKey(),
            $tulareOnly->getKey(),
            $draft->getKey(),
        );

    $this->actingAs($driver)
        ->get('/team/programs')
        ->assertOk()
        ->assertSee('Everyone Program')
        ->assertSee('Driver Program')
        ->assertDontSee('Production Program')
        ->assertDontSee('Tulare Program')
        ->assertDontSee('Draft Program');
});

it('searches program sections and linked procedures in the card library', function (): void {
    $manager = employeeProgramUser('manager');
    $procedure = publishedTestProcedure($manager, 'Hydraulic Isolation Procedure');

    $matching = testEmployeeProgram($manager, ['title' => 'Matching Program']);
    $matchingSection = $matching->sections()->create([
        'title' => 'Before servicing equipment',
        'description' => 'Resources for hydraulic isolation work.',
    ]);
    $matchingSection->items()->create([
        'type' => EmployeeProgramItem::TYPE_PROCEDURE,
        'standard_operating_procedure_id' => $procedure->getKey(),
    ]);
    $matching->publish();

    $other = testEmployeeProgram($manager, ['title' => 'Unrelated Program']);
    $other->sections()->create(['title' => 'General resources'])->items()->create([
        'type' => EmployeeProgramItem::TYPE_LINK,
        'title' => 'Office information',
        'external_url' => 'https://example.com/office',
    ]);
    $other->publish();

    $this->actingAs($manager);

    Livewire::test(ListEmployeePrograms::class)
        ->set('tableSearch', 'hydraulic isolation')
        ->assertSee('Matching Program')
        ->assertDontSee('Unrelated Program');
});

it('searches program sections and linked procedures from the team menu without widening access', function (): void {
    $manager = employeeProgramUser('manager');
    $driver = allowProgramViewing(employeeProgramUser('driver', 'colma', ['driver']));
    $driver->givePermissionTo(User::VIEW_PROCEDURES_PERMISSION);
    $procedure = publishedTestProcedure($manager, 'Compressed Air Moisture Procedure');

    $visible = testEmployeeProgram($manager, ['title' => 'Colma Vehicle Care']);
    $visibleSection = $visible->sections()->create([
        'title' => 'Air system upkeep',
        'description' => 'Downtime checks for moisture prevention and reservoir care.',
    ]);
    $visibleSection->items()->create([
        'type' => EmployeeProgramItem::TYPE_PROCEDURE,
        'standard_operating_procedure_id' => $procedure->getKey(),
    ]);
    $visible->publish();

    $hidden = testEmployeeProgram($manager, [
        'title' => 'Tulare Vehicle Care',
        'plant_locations' => ['tulare'],
    ]);
    $hidden->sections()->create([
        'title' => 'Air system upkeep',
        'description' => 'Downtime checks for moisture prevention and reservoir care.',
    ])->items()->create([
        'type' => EmployeeProgramItem::TYPE_LINK,
        'title' => 'Moisture reference',
        'external_url' => 'https://example.com/moisture',
    ]);
    $hidden->publish();

    $this->actingAs($driver);

    $sectionResults = EmployeeProgramResource::getGlobalSearchResults('moisture reservoir');
    $procedureResults = EmployeeProgramResource::getGlobalSearchResults('Compressed Air Moisture');

    expect($sectionResults->pluck('title')->all())->toBe(['Colma Vehicle Care'])
        ->and($procedureResults->pluck('title')->all())->toBe(['Colma Vehicle Care'])
        ->and($sectionResults->first()?->details)->toBe(['Topic' => 'Safety']);
});

it('keeps linked procedure permissions authoritative inside programs', function (): void {
    $manager = employeeProgramUser('manager');
    $employee = allowProgramViewing(employeeProgramUser('employee', 'colma'));
    $employee->givePermissionTo(User::VIEW_PROCEDURES_PERMISSION);

    $general = publishedTestProcedure($manager, 'General Safety Procedure');
    $management = publishedTestProcedure($manager, 'Management Confidential Procedure', [
        'audience' => StandardOperatingProcedure::AUDIENCE_MANAGEMENT,
    ]);

    $program = testEmployeeProgram($manager, ['title' => 'Safety Reference Program']);
    $section = $program->sections()->create(['title' => 'Core procedures']);
    $section->items()->create([
        'type' => EmployeeProgramItem::TYPE_PROCEDURE,
        'standard_operating_procedure_id' => $general->getKey(),
    ]);
    $section->items()->create([
        'type' => EmployeeProgramItem::TYPE_PROCEDURE,
        'standard_operating_procedure_id' => $management->getKey(),
    ]);
    $program->publish();

    $this->actingAs($employee)
        ->get("/team/programs/{$program->getKey()}")
        ->assertOk()
        ->assertSee('General Safety Procedure')
        ->assertDontSee('Management Confidential Procedure');
});

it('renders ordered sections, procedures, files, and links', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('programs/materials/reference.pdf', '%PDF-1.4 demo');

    $manager = employeeProgramUser('manager');
    $procedure = publishedTestProcedure($manager, 'Vehicle Inspection Procedure');
    $program = testEmployeeProgram($manager, ['title' => 'Driver Reference Program']);

    $second = $program->sections()->create(['title' => 'Second Section', 'sort_order' => 20]);
    $first = $program->sections()->create(['title' => 'First Section', 'sort_order' => 10]);
    $first->items()->create([
        'type' => EmployeeProgramItem::TYPE_PROCEDURE,
        'standard_operating_procedure_id' => $procedure->getKey(),
        'sort_order' => 10,
    ]);
    $first->items()->create([
        'type' => EmployeeProgramItem::TYPE_FILE,
        'file_path' => 'programs/materials/reference.pdf',
        'original_name' => 'reference.pdf',
        'title' => 'Printable Reference',
        'sort_order' => 20,
    ]);
    $second->items()->create([
        'type' => EmployeeProgramItem::TYPE_LINK,
        'title' => 'External Safety Resource',
        'external_url' => 'https://example.com/safety',
    ]);
    $program->publish();

    $this->actingAs($manager)
        ->get("/team/programs/{$program->getKey()}")
        ->assertOk()
        ->assertSeeInOrder(['First Section', 'Vehicle Inspection Procedure', 'Printable Reference', 'Second Section', 'External Safety Resource'])
        ->assertSee('Open procedure')
        ->assertSee('Download');

    $fileItem = $first->items()->where('type', EmployeeProgramItem::TYPE_FILE)->firstOrFail();

    expect($fileItem->mime_type)->toBe('application/pdf')
        ->and($fileItem->media_type)->toBe('document');
});

it('protects private program files with the same program audience rules', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('programs/materials/private.txt', 'Private program material');

    $manager = employeeProgramUser('manager');
    $program = testEmployeeProgram($manager, [
        'title' => 'Colma Program',
        'plant_locations' => ['colma'],
    ]);
    $item = $program->sections()->create(['title' => 'Files'])->items()->create([
        'type' => EmployeeProgramItem::TYPE_FILE,
        'file_path' => 'programs/materials/private.txt',
        'original_name' => 'private.txt',
        'title' => 'Private file',
    ]);
    $program->publish();

    $colmaEmployee = allowProgramViewing(employeeProgramUser('employee', 'colma'));
    $tulareEmployee = allowProgramViewing(employeeProgramUser('employee', 'tulare'));

    $this->actingAs($colmaEmployee)
        ->get(route('programs.materials.show', $item))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->actingAs($tulareEmployee)
        ->get(route('programs.materials.show', $item))
        ->assertForbidden();
});

it('does not publish an empty program', function (): void {
    $manager = employeeProgramUser('manager');
    $program = testEmployeeProgram($manager);

    expect(fn () => $program->publish())
        ->toThrow(ValidationException::class);
});

it('creates clearly labeled demo programs through a local-only Artisan command', function (): void {
    employeeProgramUser('manager');

    $this->artisan('programs:seed-demo')
        ->expectsOutputToContain('Demo programs ready:')
        ->assertSuccessful();

    $programs = EmployeeProgram::query()
        ->where('title', 'like', '[DEMO]%')
        ->with('sections.items')
        ->get();

    expect($programs)->toHaveCount(2)
        ->and($programs->every(fn (EmployeeProgram $program): bool => $program->status === EmployeeProgram::STATUS_PUBLISHED))->toBeTrue()
        ->and($programs->flatMap->sections->flatMap->items->pluck('type')->unique()->all())
        ->toContain(EmployeeProgramItem::TYPE_FILE, EmployeeProgramItem::TYPE_LINK);
});
