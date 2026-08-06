<?php

namespace App\Console\Commands;

use App\Models\Position;
use App\Models\StandardOperatingProcedure;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SeedDemoProcedures extends Command
{
    protected $signature = 'procedures:seed-demo {--refresh : Refresh and republish existing demo procedures}';

    protected $description = 'Create clearly labeled sample Team procedures in a local or testing environment';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Demo procedures may only be created in a local or testing environment.');

            return self::FAILURE;
        }

        $publisher = User::query()
            ->get()
            ->first(fn (User $user): bool => $user->canManageProcedures());

        if (! $publisher) {
            $this->error('No admin, super-admin, or manager account is available to publish the demo procedures.');

            return self::FAILURE;
        }

        $created = 0;
        $refreshed = 0;
        $skipped = 0;

        $this->seedDemoAttachmentFiles();

        foreach ($this->procedures() as $definition) {
            $positions = $definition['positions'] ?? [];
            unset($definition['positions']);

            $procedure = StandardOperatingProcedure::query()->firstOrNew([
                'code' => $definition['code'],
            ]);
            $isNew = ! $procedure->exists;

            if (! $isNew && ! $this->option('refresh')) {
                $skipped++;

                continue;
            }

            $procedure->fill([
                ...$definition,
                'owner_user_id' => $publisher->getKey(),
                'draft_change_summary' => $isNew ? null : 'Refreshed local demonstration content.',
                'draft_effective_date' => today(),
                'draft_review_due_date' => today()->addMonths(6),
            ]);
            $procedure->save();

            $positionIds = collect($positions)
                ->map(fn (array $position): int => Position::query()->firstOrCreate(
                    ['name' => $position['name']],
                    ['display_name' => $position['display_name']],
                )->getKey());
            $procedure->positions()->sync($positionIds);

            if ($procedure->hasUnpublishedChanges()) {
                $procedure->publishDraft($publisher);
            }

            $isNew ? $created++ : $refreshed++;
        }

        $this->info("Demo procedures ready: {$created} created, {$refreshed} refreshed, {$skipped} already present.");
        $this->comment('Every sample is prefixed [DEMO] and must not be treated as an approved operating procedure.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function procedures(): array
    {
        $driverPositions = [
            ['name' => 'driver', 'display_name' => 'Driver'],
            ['name' => 'tulare-driver', 'display_name' => 'Tulare Driver'],
        ];

        return [
            [
                'code' => 'DEMO-SOP-EMR-001',
                'title' => '[DEMO] Emergency Evacuation and Accountability',
                'category' => 'emergency',
                'summary' => 'Sample steps for responding to an alarm, reaching the assembly area, and accounting for employees.',
                'audience' => StandardOperatingProcedure::AUDIENCE_ALL_EMPLOYEES,
                'plant_locations' => [],
                'public_qr_enabled' => true,
                'draft_attachments' => [[
                    'path' => 'procedures/demo/emergency-assembly-map.svg',
                    'original_name' => 'emergency-assembly-map.svg',
                    'title' => '[DEMO] Emergency assembly map',
                    'description' => 'Example of an image displayed directly with a published procedure.',
                    'public_qr_enabled' => true,
                ]],
                'draft_content' => $this->document([
                    $this->notice(),
                    $this->section('When an alarm sounds', [
                        'Stop work and place equipment in a safe condition only when it is immediately safe to do so.',
                        'Leave by the nearest safe exit. Do not stop to collect personal belongings.',
                        'Report to the posted assembly area and remain with your work group.',
                    ]),
                    $this->section('Employee accountability', [
                        'Supervisors account for their assigned employees and immediately report anyone missing.',
                        'Do not re-enter the building until the person in charge gives an all-clear.',
                    ]),
                ]),
            ],
            [
                'code' => 'DEMO-SOP-SAF-002',
                'title' => '[DEMO] Reporting Injuries, Hazards, and Near Misses',
                'category' => 'safety',
                'summary' => 'Sample reporting workflow for injuries, unsafe conditions, damaged equipment, and incidents that almost occurred.',
                'audience' => StandardOperatingProcedure::AUDIENCE_ALL_EMPLOYEES,
                'plant_locations' => [],
                'public_qr_enabled' => true,
                'draft_content' => $this->document([
                    $this->notice(),
                    $this->section('Get immediate help', [
                        'For an emergency, call for emergency assistance and notify the nearest supervisor.',
                        'Do not move an injured person unless remaining in place creates a greater danger.',
                    ]),
                    $this->section('Report and preserve information', [
                        'Report every injury, hazard, equipment defect, spill, and near miss as soon as possible.',
                        'Identify the location, time, people involved, and what was happening immediately beforehand.',
                        'Do not disturb the area unless doing so is necessary to prevent another injury.',
                    ]),
                ]),
            ],
            [
                'code' => 'DEMO-SOP-DRV-001',
                'title' => '[DEMO] Driver Pre-Trip Vehicle Inspection',
                'category' => 'delivery',
                'summary' => 'Sample pre-trip sequence covering vehicle condition, required documents, load condition, and defect reporting.',
                'audience' => StandardOperatingProcedure::AUDIENCE_SELECTED_POSITIONS,
                'positions' => $driverPositions,
                'plant_locations' => [],
                'public_qr_enabled' => true,
                'draft_attachments' => [[
                    'path' => 'procedures/demo/driver-pre-trip-checklist.txt',
                    'original_name' => 'driver-pre-trip-checklist.txt',
                    'title' => '[DEMO] Printable pre-trip checklist',
                    'description' => 'Example of a downloadable document that remains internal to signed-in employees.',
                    'public_qr_enabled' => false,
                ]],
                'draft_content' => $this->document([
                    $this->notice(),
                    $this->section('Before starting the engine', [
                        'Walk around the vehicle and look underneath for leaks, damage, or obstructions.',
                        'Inspect tires, wheels, lights, mirrors, glass, steps, and safety equipment.',
                        'Confirm required registration, insurance, permits, and inspection documents are present.',
                    ]),
                    $this->section('Before leaving the yard', [
                        'Check gauges, warning indicators, brakes, steering, horn, lights, and mirrors.',
                        'Confirm the load matches the trip paperwork and is secured for transport.',
                        'Report defects before departure and do not operate equipment that is unsafe.',
                    ]),
                ]),
            ],
            [
                'code' => 'DEMO-SOP-DRV-002',
                'title' => '[DEMO] Load Securement and Final Walkaround',
                'category' => 'delivery',
                'summary' => 'Sample final checks before a loaded truck leaves the plant and after securement is adjusted.',
                'audience' => StandardOperatingProcedure::AUDIENCE_SELECTED_POSITIONS,
                'positions' => $driverPositions,
                'plant_locations' => [],
                'public_qr_enabled' => true,
                'draft_content' => $this->document([
                    $this->notice(),
                    $this->section('Secure the load', [
                        'Use only approved securement points and serviceable straps, chains, binders, and blocking.',
                        'Protect securement from sharp edges and confirm no product can shift during braking or turning.',
                        'Keep required access points, lights, and identification visible.',
                    ]),
                    $this->section('Complete the final walkaround', [
                        'Walk completely around the vehicle after securement is finished.',
                        'Verify doors, racks, tools, outriggers, attachments, and loose items are secured.',
                        'Recheck securement after the load settles and whenever conditions require it.',
                    ]),
                ]),
            ],
            [
                'code' => 'DEMO-SOP-DRV-003',
                'title' => '[DEMO] Customer Site Arrival and Delivery Setup',
                'category' => 'delivery',
                'summary' => 'Sample arrival process for checking in, evaluating the delivery area, and positioning the vehicle.',
                'audience' => StandardOperatingProcedure::AUDIENCE_SELECTED_POSITIONS,
                'positions' => $driverPositions,
                'plant_locations' => [],
                'public_qr_enabled' => false,
                'draft_content' => $this->document([
                    $this->notice(),
                    $this->section('Check in before positioning', [
                        'Confirm the delivery contact and the intended unloading location.',
                        'Discuss site traffic, overhead hazards, soft ground, slopes, and other work in the area.',
                        'Stop and contact the office when the requested setup cannot be completed safely.',
                    ]),
                    $this->section('Control the work area', [
                        'Position the vehicle to minimize backing and exposure to moving traffic.',
                        'Set the parking brake and use required wheel chocks, cones, and warning devices.',
                        'Keep customers and bystanders outside the operating area.',
                    ]),
                ]),
            ],
            [
                'code' => 'DEMO-SOP-EQP-001',
                'title' => '[DEMO] Boom Truck Setup and Outrigger Safety',
                'category' => 'equipment',
                'summary' => 'Sample setup checks for ground conditions, clearances, outriggers, and the controlled lifting area.',
                'audience' => StandardOperatingProcedure::AUDIENCE_SELECTED_POSITIONS,
                'positions' => [
                    ...$driverPositions,
                    ['name' => 'foreman', 'display_name' => 'Foreman'],
                ],
                'plant_locations' => [],
                'public_qr_enabled' => true,
                'draft_content' => $this->document([
                    $this->notice(),
                    $this->section('Evaluate the setup area', [
                        'Identify overhead electrical lines, underground concerns, slopes, soft ground, and obstructions.',
                        'Use suitable pads and fully establish the equipment on stable support.',
                        'Do not begin lifting when the setup does not meet the equipment manufacturer requirements.',
                    ]),
                    $this->section('Before operating', [
                        'Confirm outriggers and stabilizers are correctly deployed and the truck is level.',
                        'Establish a controlled area and confirm communication signals with the designated signal person.',
                        'Complete the manufacturer-required inspection and functional checks.',
                    ]),
                ]),
            ],
            [
                'code' => 'DEMO-SOP-QLT-001',
                'title' => '[DEMO] Finished Product Inspection and Release',
                'category' => 'quality',
                'summary' => 'Sample final inspection covering identification, appearance, dimensions, accessories, and release status.',
                'audience' => StandardOperatingProcedure::AUDIENCE_SELECTED_POSITIONS,
                'positions' => [
                    ['name' => 'production', 'display_name' => 'Production'],
                    ['name' => 'foreman', 'display_name' => 'Foreman'],
                ],
                'plant_locations' => ['colma', 'tulare'],
                'public_qr_enabled' => false,
                'draft_content' => $this->document([
                    $this->notice(),
                    $this->section('Verify the product', [
                        'Confirm the product number, description, quantity, and required accessories.',
                        'Inspect visible surfaces and critical dimensions against the approved requirements.',
                        'Clearly identify and isolate anything that does not meet the requirements.',
                    ]),
                    $this->section('Release for delivery or inventory', [
                        'Record the inspection result and the person completing the inspection.',
                        'Apply the correct identification and protect the product from damage during staging.',
                        'Only released product may be loaded or placed into available inventory.',
                    ]),
                ]),
            ],
            [
                'code' => 'DEMO-SOP-OPS-001',
                'title' => '[DEMO] Delivery Schedule and Tag Preparation',
                'category' => 'operations',
                'summary' => 'Sample office workflow for confirming the next delivery day, printing tags, and identifying schedule problems.',
                'audience' => StandardOperatingProcedure::AUDIENCE_MANAGEMENT,
                'plant_locations' => [],
                'public_qr_enabled' => false,
                'draft_content' => $this->document([
                    $this->notice(),
                    $this->section('Review the next delivery day', [
                        'Confirm every trip has the correct orders, stops, plant, vehicle, and assigned driver.',
                        'Resolve missing products, special instructions, time windows, and customer contact questions.',
                        'Confirm the load plan is practical before releasing paperwork to the plant or driver.',
                    ]),
                    $this->section('Prepare and verify paperwork', [
                        'Print delivery tags where plant workflow requires printed tags.',
                        'Confirm order numbers and product quantities against the scheduled trip.',
                        'Record or communicate exceptions before loading begins.',
                    ]),
                ]),
            ],
        ];
    }

    private function seedDemoAttachmentFiles(): void
    {
        $disk = Storage::disk('local');

        $disk->put('procedures/demo/emergency-assembly-map.svg', <<<'SVG'
            <svg xmlns="http://www.w3.org/2000/svg" width="1200" height="675" viewBox="0 0 1200 675">
                <rect width="1200" height="675" fill="#f8fafc"/>
                <rect x="90" y="100" width="670" height="430" rx="24" fill="#dbeafe" stroke="#1c3366" stroke-width="8"/>
                <text x="425" y="300" text-anchor="middle" font-family="sans-serif" font-size="48" font-weight="700" fill="#1c3366">DEMO WORK AREA</text>
                <path d="M760 315 H980" stroke="#dc2626" stroke-width="18" stroke-linecap="round"/>
                <path d="M945 270 L1000 315 L945 360" fill="none" stroke="#dc2626" stroke-width="18" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="1050" cy="315" r="82" fill="#dcfce7" stroke="#15803d" stroke-width="8"/>
                <text x="1050" y="305" text-anchor="middle" font-family="sans-serif" font-size="24" font-weight="700" fill="#166534">ASSEMBLY</text>
                <text x="1050" y="338" text-anchor="middle" font-family="sans-serif" font-size="24" font-weight="700" fill="#166534">AREA</text>
                <text x="600" y="615" text-anchor="middle" font-family="sans-serif" font-size="30" fill="#475569">DEMO ONLY — replace with the approved site map</text>
            </svg>
            SVG);

        $disk->put('procedures/demo/driver-pre-trip-checklist.txt', <<<'TEXT'
            DEMO DRIVER PRE-TRIP CHECKLIST

            This is sample content only. Replace it with an approved checklist before use.

            [ ] Walkaround completed
            [ ] Tires, wheels, lights, mirrors, and glass checked
            [ ] Required documents present
            [ ] Brakes, steering, horn, gauges, and warnings checked
            [ ] Load and securement verified
            [ ] Defects reported before departure
            TEXT);
    }

    /**
     * @param  array<int, array<string, mixed>>  $content
     * @return array<string, mixed>
     */
    private function document(array $content): array
    {
        return [
            'type' => 'doc',
            'content' => collect($content)
                ->flatMap(fn (array $node): array => array_is_list($node) ? $node : [$node])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function notice(): array
    {
        return [
            'type' => 'blockquote',
            'content' => [[
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => 'DEMO CONTENT — This is sample material for evaluating the procedure library. It has not been reviewed or approved for operational use.',
                ]],
            ]],
        ];
    }

    /**
     * @param  array<int, string>  $steps
     * @return array<int, array<string, mixed>>
     */
    private function section(string $heading, array $steps): array
    {
        return [
            [
                'type' => 'heading',
                'attrs' => ['level' => 2],
                'content' => [[
                    'type' => 'text',
                    'text' => $heading,
                ]],
            ],
            [
                'type' => 'bulletList',
                'content' => collect($steps)
                    ->map(fn (string $step): array => [
                        'type' => 'listItem',
                        'content' => [[
                            'type' => 'paragraph',
                            'content' => [[
                                'type' => 'text',
                                'text' => $step,
                            ]],
                        ]],
                    ])
                    ->all(),
            ],
        ];
    }
}
