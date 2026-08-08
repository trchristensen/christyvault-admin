<?php

use App\Filament\Team\Resources\StandardOperatingProcedureResource;
use App\Filament\Team\Resources\StandardOperatingProcedureResource\Pages\ListStandardOperatingProcedures;
use App\Models\Employee;
use App\Models\Position;
use App\Models\StandardOperatingProcedure;
use App\Models\StandardOperatingProcedureRevision;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(DatabaseTransactions::class);

function sopContent(string $text): array
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

function sopUser(string $role, ?string $plant = null, array $positions = []): User
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

function sopProcedure(User $manager, array $overrides = []): StandardOperatingProcedure
{
    static $sequence = 0;
    $sequence++;

    return StandardOperatingProcedure::create([
        'code' => 'SOP-TST-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
        'title' => "Test Procedure {$sequence}",
        'category' => 'safety',
        'summary' => 'A test procedure.',
        'audience' => StandardOperatingProcedure::AUDIENCE_ALL_EMPLOYEES,
        'owner_user_id' => $manager->getKey(),
        'draft_content' => sopContent("Procedure content {$sequence}"),
        'draft_effective_date' => today(),
        ...$overrides,
    ]);
}

function allowProcedureViewing(User $user): User
{
    $user->givePermissionTo(User::VIEW_PROCEDURES_PERMISSION);

    return $user;
}

beforeEach(function (): void {
    Filament::setCurrentPanel('team');
});

it('hides procedures and denies direct access before an employee role is granted permission', function (): void {
    $employee = sopUser('employee', 'colma');

    $this->actingAs($employee);

    expect(StandardOperatingProcedureResource::canViewAny())->toBeFalse();

    $this->get('/team')
        ->assertOk()
        ->assertDontSee('href="'.StandardOperatingProcedureResource::getUrl('index').'"', false);
    $this->get('/team/procedures')->assertForbidden();
    $this->get('/team/procedures/create')->assertForbidden();
});

it('keeps procedure authoring in Team and restricts it to managers', function (): void {
    $manager = sopUser('manager');

    $this->actingAs($manager)
        ->get('/team/procedures')
        ->assertOk()
        ->assertSee('Procedures');
    $this->get('/team/procedures/create')->assertOk();

    expect(StandardOperatingProcedureResource::canCreate())->toBeTrue();

    $procedure = sopProcedure($manager);
    $this->get("/team/procedures/{$procedure->getKey()}/edit")->assertOk();

    $employee = sopUser('employee', 'colma');

    $this->actingAs($employee);
    $this->get('/team/procedures')->assertForbidden();
    $this->get('/team/procedures/create')->assertForbidden();

    expect(StandardOperatingProcedureResource::canViewAny())->toBeFalse()
        ->and(StandardOperatingProcedureResource::canCreate())->toBeFalse();

    allowProcedureViewing($employee);

    $this->get('/team/procedures')
        ->assertOk()
        ->assertDontSee('New procedure')
        ->assertDontSee('Create procedure');
    $this->get('/team/procedures/create')->assertForbidden();

    expect(StandardOperatingProcedureResource::canViewAny())->toBeTrue()
        ->and(StandardOperatingProcedureResource::canCreate())->toBeFalse();
});

it('seeds procedure permissions only onto management roles', function (): void {
    $manager = sopUser('manager');
    $employee = sopUser('employee', 'colma');
    $driver = sopUser('driver', 'colma', ['driver']);

    expect($manager->canViewProcedures())->toBeTrue()
        ->and($manager->canManageProcedures())->toBeTrue()
        ->and($employee->canViewProcedures())->toBeFalse()
        ->and($employee->canManageProcedures())->toBeFalse()
        ->and($driver->canViewProcedures())->toBeFalse()
        ->and($driver->canManageProcedures())->toBeFalse();
});

it('provides managers a large vertically resizable procedure editor', function (): void {
    $manager = sopUser('manager');

    $this->actingAs($manager)
        ->get('/team/procedures/create')
        ->assertOk()
        ->assertSee('height: 36rem; min-height: 28rem; resize: vertical; overflow: auto;', false)
        ->assertSee('Drag the bottom-right corner');
});

it('searches procedure content inside the grouped card library', function (): void {
    $manager = sopUser('manager');
    $matching = sopProcedure($manager, [
        'title' => 'Matching Procedure',
        'category' => 'equipment',
        'draft_content' => sopContent('Inspect the hydraulic isolation valve before operating.'),
    ]);
    $matching->publishDraft($manager);

    $other = sopProcedure($manager, [
        'title' => 'Unrelated Procedure',
        'category' => 'quality',
        'draft_content' => sopContent('Confirm the finished surface is acceptable.'),
    ]);
    $other->publishDraft($manager);

    $this->actingAs($manager);

    Livewire::test(ListStandardOperatingProcedures::class)
        ->set('tableSearch', 'isolation hydraulic')
        ->assertSee('Matching Procedure')
        ->assertDontSee('Unrelated Procedure');
});

it('searches published procedure details and content from the team menu without widening access', function (): void {
    $manager = sopUser('manager');
    $driver = allowProcedureViewing(sopUser('driver', 'colma', ['driver']));
    $visible = sopProcedure($manager, [
        'code' => 'SOP-AIR-DRY',
        'title' => 'Colma Air System Care',
        'summary' => 'Moisture prevention for vehicle air systems.',
        'category' => 'equipment',
        'draft_content' => sopContent('Drain each applicable manual reservoir and report excessive compressor oil.'),
    ]);
    $visible->publishDraft($manager);

    $hidden = sopProcedure($manager, [
        'title' => 'Tulare Air System Care',
        'plant_locations' => ['tulare'],
        'draft_content' => sopContent('Drain each applicable manual reservoir and report excessive compressor oil.'),
    ]);
    $hidden->publishDraft($manager);

    $this->actingAs($driver);

    $contentResults = StandardOperatingProcedureResource::getGlobalSearchResults('manual reservoir compressor');
    $codeResults = StandardOperatingProcedureResource::getGlobalSearchResults('SOP-AIR-DRY');

    expect($contentResults->pluck('title')->all())->toBe(['Colma Air System Care'])
        ->and($codeResults->pluck('title')->all())->toBe(['Colma Air System Care'])
        ->and($contentResults->first()?->details)->toMatchArray([
            'Procedure' => 'SOP-AIR-DRY',
            'Topic' => 'Equipment',
        ]);
});

it('shows employees only published procedures for their plant and position', function (): void {
    $manager = sopUser('manager');
    $driver = allowProcedureViewing(sopUser('driver', 'colma', ['driver']));

    $everyone = sopProcedure($manager, ['title' => 'Everyone Procedure']);
    $everyone->publishDraft($manager);

    $driverOnly = sopProcedure($manager, [
        'title' => 'Driver Procedure',
        'audience' => StandardOperatingProcedure::AUDIENCE_SELECTED_POSITIONS,
    ]);
    $driverOnly->positions()->attach(Position::query()->where('name', 'driver')->firstOrFail());
    $driverOnly->publishDraft($manager);

    $productionOnly = sopProcedure($manager, [
        'title' => 'Production Procedure',
        'audience' => StandardOperatingProcedure::AUDIENCE_SELECTED_POSITIONS,
    ]);
    $productionOnly->positions()->attach(Position::query()->firstOrCreate(
        ['name' => 'production'],
        ['display_name' => 'Production'],
    ));
    $productionOnly->publishDraft($manager);

    $tulareOnly = sopProcedure($manager, [
        'title' => 'Tulare Procedure',
        'plant_locations' => ['tulare'],
    ]);
    $tulareOnly->publishDraft($manager);

    $managementOnly = sopProcedure($manager, [
        'title' => 'Management Procedure',
        'audience' => StandardOperatingProcedure::AUDIENCE_MANAGEMENT,
    ]);
    $managementOnly->publishDraft($manager);

    $draft = sopProcedure($manager, ['title' => 'Draft Procedure']);

    $visibleIds = StandardOperatingProcedure::query()
        ->visibleTo($driver)
        ->pluck('id')
        ->all();

    expect($visibleIds)->toContain($everyone->getKey(), $driverOnly->getKey())
        ->and($visibleIds)->not->toContain(
            $productionOnly->getKey(),
            $tulareOnly->getKey(),
            $managementOnly->getKey(),
            $draft->getKey(),
        );

    $this->actingAs($driver)
        ->get('/team/procedures')
        ->assertOk()
        ->assertSee('fi-ta-content-grid', false)
        ->assertSee('Safe work practices, hazard prevention, and incident response.')
        ->assertSee('Everyone Procedure')
        ->assertSee('Driver Procedure')
        ->assertDontSee('Production Procedure')
        ->assertDontSee('Tulare Procedure')
        ->assertDontSee('Management Procedure')
        ->assertDontSee('Draft Procedure');

    $this->get("/team/procedures/{$everyone->getKey()}")
        ->assertOk()
        ->assertSee('Procedure content')
        ->assertDontSee('Print QR sign');
});

it('shows procedure management actions only to management', function (): void {
    $manager = sopUser('manager');
    $procedure = sopProcedure($manager, [
        'title' => 'Public QR Procedure',
        'public_qr_enabled' => true,
    ]);
    $procedure->publishDraft($manager);

    $this->actingAs($manager)
        ->get("/team/procedures/{$procedure->getKey()}")
        ->assertOk()
        ->assertSee('Print QR sign')
        ->assertSee('Edit');

    $employee = allowProcedureViewing(sopUser('employee', 'colma'));

    $this->actingAs($employee)
        ->get("/team/procedures/{$procedure->getKey()}")
        ->assertOk()
        ->assertDontSee('Print QR sign')
        ->assertDontSee('Edit')
        ->assertDontSee('Retire')
        ->assertDontSee('Restore');
});

it('creates clearly labeled demo procedures through a local-only Artisan command', function (): void {
    sopUser('manager');

    $this->artisan('procedures:seed-demo')
        ->expectsOutputToContain('Demo procedures ready:')
        ->assertSuccessful();

    $demoProcedures = StandardOperatingProcedure::query()
        ->where('code', 'like', 'DEMO-SOP-%')
        ->with('currentRevision')
        ->get();

    expect($demoProcedures)->toHaveCount(8)
        ->and($demoProcedures->every(fn (StandardOperatingProcedure $procedure): bool => str_starts_with($procedure->title, '[DEMO]')))->toBeTrue()
        ->and($demoProcedures->every(fn (StandardOperatingProcedure $procedure): bool => $procedure->currentRevision !== null))->toBeTrue()
        ->and($demoProcedures->firstWhere('audience', StandardOperatingProcedure::AUDIENCE_MANAGEMENT)?->public_qr_enabled)->toBeFalse();

    $emergencyDemo = $demoProcedures->firstWhere('code', 'DEMO-SOP-EMR-001');
    $driverDemo = $demoProcedures->firstWhere('code', 'DEMO-SOP-DRV-001');

    expect($emergencyDemo->currentRevision->attachmentItems())->toHaveCount(1)
        ->and($emergencyDemo->currentRevision->attachmentItems()->first()['public_qr_enabled'])->toBeTrue()
        ->and($driverDemo->currentRevision->attachmentItems())->toHaveCount(1)
        ->and($driverDemo->currentRevision->attachmentItems()->first()['public_qr_enabled'])->toBeFalse();

    $this->get(route('procedures.public.show', $emergencyDemo->qr_token))
        ->assertOk()
        ->assertSee('DEMO CONTENT')
        ->assertSee('When an alarm sounds')
        ->assertSee('[DEMO] Emergency assembly map');
});

it('publishes immutable revisions while edits remain drafts', function (): void {
    $manager = sopUser('manager');
    $procedure = sopProcedure($manager, [
        'title' => 'Original Procedure',
        'draft_content' => sopContent('Original instructions'),
    ]);

    $first = $procedure->publishDraft($manager);

    $procedure->update([
        'title' => 'Updated Procedure',
        'draft_content' => sopContent('Updated instructions'),
        'draft_change_summary' => 'Clarified the instructions.',
    ]);

    expect($procedure->fresh()->currentRevision->getKey())->toBe($first->getKey())
        ->and($first->fresh()->title)->toBe('Original Procedure')
        ->and($first->fresh()->content)->toBe(sopContent('Original instructions'));

    $second = $procedure->fresh()->publishDraft($manager);

    expect($second->version)->toBe(2)
        ->and($second->title)->toBe('Updated Procedure')
        ->and($second->content)->toBe(sopContent('Updated instructions'))
        ->and($procedure->fresh()->current_revision_id)->toBe($second->getKey())
        ->and($first->fresh()->status)->toBe(StandardOperatingProcedureRevision::STATUS_SUPERSEDED);
});

it('publishes immutable attachment snapshots with each revision', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('procedures/attachments/first.pdf', '%PDF-1.4 first');
    Storage::disk('local')->put('procedures/attachments/second.pdf', '%PDF-1.4 second');

    $manager = sopUser('manager');
    $procedure = sopProcedure($manager, [
        'draft_attachments' => [[
            'path' => 'procedures/attachments/first.pdf',
            'original_name' => 'first.pdf',
            'title' => 'First revision document',
            'description' => 'The original attachment.',
            'public_qr_enabled' => false,
        ]],
    ]);

    $first = $procedure->publishDraft($manager);

    $procedure->update([
        'draft_attachments' => [[
            'path' => 'procedures/attachments/second.pdf',
            'original_name' => 'second.pdf',
            'title' => 'Second revision document',
            'public_qr_enabled' => true,
        ]],
        'draft_change_summary' => 'Replaced the supporting document.',
    ]);

    $second = $procedure->fresh()->publishDraft($manager);

    expect($first->fresh()->attachments)->toHaveCount(1)
        ->and($first->fresh()->attachments[0]['title'])->toBe('First revision document')
        ->and($first->fresh()->attachments[0]['public_qr_enabled'])->toBeFalse()
        ->and($second->attachments)->toHaveCount(1)
        ->and($second->attachments[0]['title'])->toBe('Second revision document')
        ->and($second->attachments[0]['public_qr_enabled'])->toBeTrue()
        ->and($first->fresh()->attachments[0]['path'])->toBe('procedures/attachments/first.pdf');
});

it('protects procedure attachments and exposes only approved files through QR access', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('procedures/attachments/public.pdf', '%PDF-1.4 public');
    Storage::disk('local')->put('procedures/attachments/internal.txt', 'Internal instructions');

    $manager = sopUser('manager');
    $procedure = sopProcedure($manager, [
        'plant_locations' => ['colma'],
        'public_qr_enabled' => true,
        'draft_attachments' => [
            [
                'path' => 'procedures/attachments/public.pdf',
                'original_name' => 'public.pdf',
                'title' => 'Public reference',
                'public_qr_enabled' => true,
            ],
            [
                'path' => 'procedures/attachments/internal.txt',
                'original_name' => 'internal.txt',
                'title' => 'Internal reference',
                'public_qr_enabled' => false,
            ],
        ],
    ]);
    $revision = $procedure->publishDraft($manager);
    $publicAttachment = $revision->attachmentItems()->firstWhere('title', 'Public reference');
    $internalAttachment = $revision->attachmentItems()->firstWhere('title', 'Internal reference');

    $colmaEmployee = allowProcedureViewing(sopUser('employee', 'colma'));

    $this->actingAs($colmaEmployee)
        ->get(route('procedures.attachments.show', [$procedure, $internalAttachment['token']]))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->get(route('procedures.public.attachments.show', [$procedure->qr_token, $publicAttachment['token']]))
        ->assertOk();
    $this->get(route('procedures.public.attachments.show', [$procedure->qr_token, $internalAttachment['token']]))
        ->assertNotFound();

    $this->get(route('procedures.public.show', $procedure->qr_token))
        ->assertOk()
        ->assertSee('Public reference')
        ->assertDontSee('Internal reference');

    $tulareEmployee = allowProcedureViewing(sopUser('employee', 'tulare'));

    $this->actingAs($tulareEmployee)
        ->get(route('procedures.attachments.show', [$procedure, $internalAttachment['token']]))
        ->assertForbidden();
});

it('serves only explicitly approved published procedures through a stable QR page', function (): void {
    $manager = sopUser('manager');
    $procedure = sopProcedure($manager, [
        'title' => 'QR Safety Procedure',
        'draft_content' => sopContent('Use the approved safety steps.'),
    ]);
    $procedure->publishDraft($manager);

    $this->get(route('procedures.public.show', $procedure->qr_token))->assertNotFound();

    $procedure->update(['public_qr_enabled' => true]);

    $this->get(route('procedures.public.show', $procedure->qr_token))
        ->assertOk()
        ->assertSee('QR Safety Procedure')
        ->assertSee('Use the approved safety steps.')
        ->assertSee('Current published procedure');

    $this->get(route('procedures.public.label', $procedure->qr_token))
        ->assertOk()
        ->assertSee('Scan to view the current procedure');

    $this->get(route('procedures.public.qr', $procedure->qr_token))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml');

    $managementProcedure = sopProcedure($manager, [
        'audience' => StandardOperatingProcedure::AUDIENCE_MANAGEMENT,
        'public_qr_enabled' => true,
    ]);
    $managementProcedure->publishDraft($manager);

    expect($managementProcedure->fresh()->public_qr_enabled)->toBeFalse();
    $this->get(route('procedures.public.show', $managementProcedure->qr_token))->assertNotFound();

    $procedure->archive();

    expect($procedure->fresh()->archived_at)->not->toBeNull()
        ->and($procedure->fresh()->public_qr_enabled)->toBeFalse();
    $this->get(route('procedures.public.show', $procedure->qr_token))->assertNotFound();
});
