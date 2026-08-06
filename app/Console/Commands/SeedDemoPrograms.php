<?php

namespace App\Console\Commands;

use App\Models\EmployeeProgram;
use App\Models\EmployeeProgramItem;
use App\Models\Position;
use App\Models\StandardOperatingProcedure;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SeedDemoPrograms extends Command
{
    protected $signature = 'programs:seed-demo {--refresh : Rebuild existing demo programs}';

    protected $description = 'Create clearly labeled sample Team programs in a local or testing environment';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Demo programs may only be created in a local or testing environment.');

            return self::FAILURE;
        }

        $owner = User::query()
            ->get()
            ->first(fn (User $user): bool => $user->canManagePrograms());

        if (! $owner) {
            $this->error('No admin, super-admin, or manager account is available to own the demo programs.');

            return self::FAILURE;
        }

        Storage::disk('local')->put(
            'programs/demo/driver-reference-note.txt',
            "DEMO CONTENT ONLY\n\nReplace this file with an approved company reference before using the program.\n",
        );

        $created = 0;
        $refreshed = 0;
        $skipped = 0;

        foreach ($this->programs() as $definition) {
            $sections = $definition['sections'];
            $positions = $definition['positions'] ?? [];
            unset($definition['sections'], $definition['positions']);

            $program = EmployeeProgram::query()->firstOrNew(['title' => $definition['title']]);
            $isNew = ! $program->exists;

            if (! $isNew && ! $this->option('refresh')) {
                $skipped++;

                continue;
            }

            $program->fill([
                ...$definition,
                'owner_user_id' => $owner->getKey(),
                'status' => EmployeeProgram::STATUS_DRAFT,
                'published_at' => null,
                'archived_at' => null,
            ]);
            $program->save();

            $positionIds = collect($positions)
                ->map(fn (array $position): int => Position::query()->firstOrCreate(
                    ['name' => $position['name']],
                    ['display_name' => $position['display_name']],
                )->getKey());
            $program->positions()->sync($positionIds);
            $program->sections()->delete();

            foreach ($sections as $sectionIndex => $sectionDefinition) {
                $items = $sectionDefinition['items'];
                unset($sectionDefinition['items']);

                $section = $program->sections()->create([
                    ...$sectionDefinition,
                    'sort_order' => ($sectionIndex + 1) * 10,
                ]);

                foreach ($items as $itemIndex => $itemDefinition) {
                    if (filled($itemDefinition['procedure_code'] ?? null)) {
                        $procedure = StandardOperatingProcedure::query()
                            ->where('code', $itemDefinition['procedure_code'])
                            ->whereNotNull('current_revision_id')
                            ->first();
                        unset($itemDefinition['procedure_code']);

                        if (! $procedure) {
                            continue;
                        }

                        $itemDefinition['standard_operating_procedure_id'] = $procedure->getKey();
                    }

                    $section->items()->create([
                        ...$itemDefinition,
                        'sort_order' => ($itemIndex + 1) * 10,
                    ]);
                }
            }

            $program->publish();
            $isNew ? $created++ : $refreshed++;
        }

        $this->info("Demo programs ready: {$created} created, {$refreshed} refreshed, {$skipped} already present.");
        $this->comment('Every sample is prefixed [DEMO] and is an informational example, not assigned training.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function programs(): array
    {
        $driverPositions = [
            ['name' => 'driver', 'display_name' => 'Driver'],
            ['name' => 'tulare-driver', 'display_name' => 'Tulare Driver'],
        ];

        return [
            [
                'title' => '[DEMO] Driver Reference Program',
                'category' => 'delivery',
                'summary' => 'Example of a browsable driver collection containing procedures, a private file, and an external reference.',
                'introduction' => $this->document('This demonstration program shows how related driver resources can be organized without assignments, quizzes, or completion tracking.'),
                'audience' => EmployeeProgram::AUDIENCE_SELECTED_POSITIONS,
                'positions' => $driverPositions,
                'plant_locations' => [],
                'sections' => [
                    [
                        'title' => 'Before leaving the plant',
                        'description' => 'Start with the vehicle and load checks that apply before departure.',
                        'items' => [
                            [
                                'type' => EmployeeProgramItem::TYPE_PROCEDURE,
                                'procedure_code' => 'DEMO-SOP-DRV-001',
                                'description' => 'Review the pre-trip inspection sequence.',
                            ],
                            [
                                'type' => EmployeeProgramItem::TYPE_PROCEDURE,
                                'procedure_code' => 'DEMO-SOP-DRV-002',
                                'description' => 'Review securement and the final walkaround.',
                            ],
                            [
                                'type' => EmployeeProgramItem::TYPE_FILE,
                                'file_path' => 'programs/demo/driver-reference-note.txt',
                                'original_name' => 'driver-reference-note.txt',
                                'title' => '[DEMO] Driver reference handout',
                                'description' => 'Example of a private downloadable file owned by the program.',
                            ],
                        ],
                    ],
                    [
                        'title' => 'At the customer site',
                        'items' => [
                            [
                                'type' => EmployeeProgramItem::TYPE_PROCEDURE,
                                'procedure_code' => 'DEMO-SOP-DRV-003',
                                'description' => 'Review arrival, positioning, and delivery-area controls.',
                            ],
                            [
                                'type' => EmployeeProgramItem::TYPE_LINK,
                                'title' => 'OSHA worker rights and resources',
                                'description' => 'Example of an external supporting resource.',
                                'external_url' => 'https://www.osha.gov/workers',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => '[DEMO] Workplace Safety Resources',
                'category' => 'safety',
                'summary' => 'Example company-wide collection for emergency and incident-reporting references.',
                'introduction' => $this->document('Use this collection as an example of a small, company-wide safety reference program.'),
                'audience' => EmployeeProgram::AUDIENCE_ALL_EMPLOYEES,
                'plant_locations' => [],
                'sections' => [
                    [
                        'title' => 'Emergency and incident response',
                        'items' => [
                            [
                                'type' => EmployeeProgramItem::TYPE_PROCEDURE,
                                'procedure_code' => 'DEMO-SOP-EMR-001',
                            ],
                            [
                                'type' => EmployeeProgramItem::TYPE_PROCEDURE,
                                'procedure_code' => 'DEMO-SOP-SAF-002',
                            ],
                            [
                                'type' => EmployeeProgramItem::TYPE_LINK,
                                'title' => 'OSHA emergency preparedness resources',
                                'external_url' => 'https://www.osha.gov/emergency-preparedness',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function document(string $text): array
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
}
