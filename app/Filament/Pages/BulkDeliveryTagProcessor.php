<?php

namespace App\Filament\Pages;

use App\Services\BulkDeliveryTagService;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\HtmlString;

class BulkDeliveryTagProcessor extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static string $view = 'filament.pages.bulk-delivery-tag-processor';

    protected static ?string $title = 'Bulk Delivery Tag Processor';

    protected static ?string $navigationLabel = 'Bulk Tag Processor';

    protected static ?string $navigationGroup = 'Delivery Management';

    protected static ?int $navigationSort = 10;

    public ?array $data = [];
    public ?array $processingResults = null;
    public bool $showResults = false;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Upload Bulk Delivery Tags')
                    ->description('Upload a multi-page PDF containing delivery tags. The system will automatically split them into individual pages, extract order numbers using OCR, and attach them to the corresponding orders.')
                    ->schema([
                        Forms\Components\Placeholder::make('instructions')
                            ->content(new HtmlString('
                                <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                                    <h4 class="font-semibold text-blue-900 dark:text-blue-100 mb-2">How it works:</h4>
                                    <ol class="list-decimal list-inside text-sm text-blue-800 dark:text-blue-200 space-y-1">
                                        <li>Upload a multi-page PDF containing scanned delivery tags</li>
                                        <li>Each page will be extracted as a separate delivery tag</li>
                                        <li>OCR will scan for order numbers in format "ORD-123456"</li>
                                        <li>Tags will be automatically matched to existing orders</li>
                                        <li>Unmatched tags will be listed for manual review</li>
                                    </ol>
                                </div>
                            ')),

                        FileUpload::make('bulk_pdf')
                            ->label('Bulk Delivery Tags PDF')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(50 * 1024) // 50MB limit
                            ->required()
                            ->disk('local')
                            ->directory('temp')
                            ->visibility('private')
                            ->helperText('Upload a PDF file containing multiple delivery tag pages. Maximum size: 50MB.')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                Section::make('Processing Options')
                    ->schema([
                        Forms\Components\Toggle::make('dry_run')
                            ->label('Dry Run Mode')
                            ->helperText('Test the processing without actually attaching tags to orders')
                            ->default(false)
                            ->inline(false),

                        Forms\Components\Toggle::make('high_quality_ocr')
                            ->label('High Quality OCR')
                            ->helperText('Use higher resolution for better OCR accuracy (slower processing)')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function process(): void
    {
        try {
            $data = $this->form->getState();
            
            if (!isset($data['bulk_pdf']) || empty($data['bulk_pdf'])) {
                throw new Halt('Please upload a PDF file first.');
            }

            // Get the uploaded file
            $filePath = $data['bulk_pdf'];
            
            // Debug: Log what we're getting
            \Log::info('Bulk PDF Upload Debug', [
                'raw_data' => $data['bulk_pdf'],
                'is_array' => is_array($filePath),
                'file_path' => $filePath
            ]);
            
            // Handle both string path and array of paths
            if (is_array($filePath)) {
                $filePath = $filePath[0];
            }
            
            // Filament stores files in private directory by default
            $fullPath = storage_path('app/private/' . $filePath);
            
            \Log::info('File Path Debug', [
                'relative_path' => $filePath,
                'full_path' => $fullPath,
                'file_exists' => file_exists($fullPath)
            ]);
            
            // Check if file exists
            if (!file_exists($fullPath)) {
                throw new Halt("The uploaded file could not be found at: {$fullPath}. Please try uploading again.");
            }
            
            $file = new \Illuminate\Http\UploadedFile(
                $fullPath,
                'bulk_delivery_tags.pdf',
                'application/pdf',
                null,
                true
            );

            // Process the bulk PDF
            $service = new BulkDeliveryTagService();
            $dryRun = $data['dry_run'] ?? false;
            $this->processingResults = $service->processBulkPdf($file, $dryRun);
            $this->showResults = true;

            // Show notification
            $matched = $this->processingResults['matched'];
            $total = $this->processingResults['processed'];
            $dryRunText = $dryRun ? ' (DRY RUN - No changes made)' : '';
            
            if ($matched === $total) {
                Notification::make()
                    ->title('Processing Complete!' . $dryRunText)
                    ->body("Successfully processed all {$total} delivery tags.")
                    ->success()
                    ->send();
            } else {
                $unmatched = $total - $matched;
                Notification::make()
                    ->title('Processing Complete with Issues' . $dryRunText)
                    ->body("Processed {$total} tags: {$matched} matched, {$unmatched} unmatched.")
                    ->warning()
                    ->send();
            }

        } catch (Halt $exception) {
            throw $exception;
        } catch (\Exception $e) {
            Notification::make()
                ->title('Processing Failed')
                ->body('Error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function resetForm(): void
    {
        $this->form->fill();
        $this->processingResults = null;
        $this->showResults = false;
    }

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('process')
                ->label('Process Delivery Tags')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('primary')
                ->size('lg')
                ->action('process')
                ->requiresConfirmation()
                ->modalHeading('Process Bulk Delivery Tags')
                ->modalDescription('This will split the PDF, extract order numbers, and attach tags to orders. Are you sure you want to continue?')
                ->modalSubmitActionLabel('Yes, Process Tags'),

            Actions\Action::make('reset')
                ->label('Reset Form')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action('resetForm')
                ->visible(fn() => $this->showResults),
        ];
    }

    public function getProcessingResults(): ?array
    {
        return $this->processingResults;
    }

    public function getShowResults(): bool
    {
        return $this->showResults;
    }
}
