<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToImage\Pdf;
use thiagoalessio\TesseractOCR\TesseractOCR;
use setasign\Fpdi\Fpdi;
use Exception;
use App\Enums\OrderStatus;

class BulkDeliveryTagService
{
    /**
     * Process a bulk PDF file and split it into individual delivery tags
     * 
     * @param UploadedFile $file
     * @param bool $dryRun
     * @return array
     */
    public function processBulkPdf(UploadedFile $file, bool $dryRun = false): array
    {
        $results = [
            'total_pages' => 0,
            'processed' => 0,
            'matched' => 0,
            'matched_orders' => [],
            'unmatched' => [],
            'errors' => []
        ];

        try {
            // Store the uploaded file temporarily
            $tempPath = $file->storeAs('temp', 'bulk_delivery_tags_' . time() . '.pdf', 'local');
            $fullPath = Storage::disk('local')->path($tempPath);

            // Create PDF instance
            $pdf = new Pdf($fullPath);
            $pageCount = $pdf->getNumberOfPages();
            $results['total_pages'] = $pageCount;

            // Process each page individually to avoid timeouts
            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                // Add a small delay between pages to prevent overwhelming the system
                if ($pageNumber > 1) {
                    usleep(500000); // 0.5 second delay
                }
                
                try {
                    \Log::info("Processing page {$pageNumber} of {$pageCount}");
                    
                    $pageResult = $this->processPage($pdf, $pageNumber, $fullPath, $dryRun);
                    
                    if ($pageResult['success']) {
                        $results['processed']++;
                        
                        if ($pageResult['matched']) {
                            $results['matched']++;
                            $results['matched_orders'][] = [
                                'page' => $pageNumber,
                                'order_number' => $pageResult['order_number'],
                                'order_id' => $pageResult['order_id'],
                                'file_path' => $pageResult['file_path'],
                                'order_details' => $pageResult['order_details'] ?? null
                            ];
                        } else {
                            $results['unmatched'][] = [
                                'page' => $pageNumber,
                                'order_number' => $pageResult['order_number'],
                                'file_path' => $pageResult['file_path']
                            ];
                        }
                    } else {
                        $results['errors'][] = [
                            'page' => $pageNumber,
                            'error' => $pageResult['error']
                        ];
                    }
                    
                    \Log::info("Completed page {$pageNumber}: " . 
                              ($pageResult['success'] ? 'success' : 'failed'));
                              
                } catch (Exception $e) {
                    \Log::error("Error processing page {$pageNumber}: " . $e->getMessage());
                    $results['errors'][] = [
                        'page' => $pageNumber,
                        'error' => $e->getMessage()
                    ];
                }
            }

            // Clean up temporary file
            Storage::disk('local')->delete($tempPath);

        } catch (Exception $e) {
            \Log::error('Bulk PDF processing failed: ' . $e->getMessage());
            $results['errors'][] = [
                'page' => 'general',
                'error' => $e->getMessage()
            ];
        }

        return $results;
    }

    /**
     * Process a single page of the PDF
     * 
     * @param Pdf $pdf
     * @param int $pageNumber
     * @param string $fullPath
     * @param bool $dryRun
     * @return array
     */
    private function processPage(Pdf $pdf, int $pageNumber, string $fullPath, bool $dryRun): array
    {
        try {
            // Extract page as image for OCR
            $imagePath = $this->extractPageAsImage($pdf, $pageNumber);
            
            // Perform OCR to extract order number
            $orderNumber = $this->extractOrderNumber($imagePath);
            
            if (!$orderNumber) {
                // Clean up image
                unlink($imagePath);
                return [
                    'success' => false,
                    'error' => 'Could not extract order number from page'
                ];
            }

            // Create individual PDF for this page (or simulate in dry run)
            $individualPdfPath = null;
            if (!$dryRun) {
                $individualPdfPath = $this->createIndividualPdf($fullPath, $pageNumber, $orderNumber);
            } else {
                // Simulate file path for dry run using same format
                $order = $this->findOrderByNumber($orderNumber);
                $date = now()->format('Y-m-d');
                
                if ($order && $order->location) {
                    $locationName = preg_replace('/[^A-Za-z0-9\-_]/', '_', $order->location->name);
                    $city = preg_replace('/[^A-Za-z0-9\-_]/', '_', $order->location->city);
                    $individualPdfPath = "delivery-tags/{$date}_{$locationName}_{$city}_{$order->order_number}_delivery_tag.pdf";
                } else {
                    $individualPdfPath = "delivery-tags/{$date}_{$orderNumber}_delivery_tag.pdf";
                }
            }
            
            // Find matching order
            $order = $this->findOrderByNumber($orderNumber);
            
            $matched = false;
            if ($order && !$dryRun) {
                // Attach to order (only if not dry run)
                $order->update(['delivery_tag_url' => $individualPdfPath]);
                $matched = true;
            } elseif ($order && $dryRun) {
                // In dry run, just mark as matched without updating
                $matched = true;
            }

            // Clean up image
            unlink($imagePath);

            return [
                'success' => true,
                'matched' => $matched,
                'order_number' => $orderNumber,
                'file_path' => $individualPdfPath,
                'order_id' => $order?->id,
                'order_details' => $order ? [
                    'order_number' => $order->order_number,
                    'location_name' => $order->location?->name,
                    'location_city' => $order->location?->city,
                    'requested_delivery_date' => $order->requested_delivery_date?->format('M j, Y'),
                    'status' => $this->getOrderStatusLabel($order->status)
                ] : null
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract a page as an image for OCR processing
     * 
     * @param Pdf $pdf
     * @param int $pageNumber
     * @return string
     */
    private function extractPageAsImage(Pdf $pdf, int $pageNumber): string
    {
        $tempImagePath = storage_path('app/temp/page_' . $pageNumber . '_' . time() . '.png');
        
        // Ensure temp directory exists
        if (!file_exists(dirname($tempImagePath))) {
            mkdir(dirname($tempImagePath), 0755, true);
        }

        $pdf->setPage($pageNumber)
            ->setResolution(300) // Higher resolution for better OCR
            ->saveImage($tempImagePath);

        return $tempImagePath;
    }

    /**
     * Extract order number from image using OCR
     * 
     * @param string $imagePath
     * @return string|null
     */
    private function extractOrderNumber(string $imagePath): ?string
    {
        try {
            // First attempt: Look specifically for ORD- pattern
            $ocr = new TesseractOCR($imagePath);
            $ocr->psm(6); // Uniform block of text
            $ocr->oem(3); // Default OCR Engine Mode
            // Allow letters O,R,D and digits and dash for "ORD-00591" pattern
            $ocr->allowlist(range('0', '9'), ['-', 'O', 'R', 'D']);
            
            $text = $ocr->run();
            
            \Log::info('OCR Text (ORD focused)', [
                'image' => basename($imagePath),
                'text' => $text
            ]);

            // Look for "ORD-XXXXX" pattern first (primary target)
            if (preg_match('/ORD[-\s]*(\d{4,6})/i', $text, $matches)) {
                \Log::info('Found ORD pattern', ['match' => $matches[1]]);
                return $matches[1];
            }

            // Alternative patterns for OCR errors on ORD
            if (preg_match('/0RD[-\s]*(\d{4,6})/i', $text, $matches)) {
                \Log::info('Found 0RD pattern', ['match' => $matches[1]]);
                return $matches[1];
            }

            if (preg_match('/[O0]R[D0][-\s]*(\d{4,6})/i', $text, $matches)) {
                \Log::info('Found OCR variant pattern', ['match' => $matches[1]]);
                return $matches[1];
            }

            // Second attempt: Broader OCR for more complete text
            $ocrBroad = new TesseractOCR($imagePath);
            $ocrBroad->psm(6);
            $ocrBroad->oem(3);
            // No character restrictions for broader scan
            
            $broadText = $ocrBroad->run();
            \Log::info('OCR Text (broad)', [
                'image' => basename($imagePath),
                'text' => $broadText
            ]);

            // Look for ORD pattern in broader text
            if (preg_match('/ORD[-\s]*(\d{4,6})/i', $broadText, $matches)) {
                \Log::info('Found ORD in broad text', ['match' => $matches[1]]);
                return $matches[1];
            }

            // Look for variations in broad text
            if (preg_match('/[O0]RD[-\s]*(\d{4,6})/i', $broadText, $matches)) {
                \Log::info('Found ORD variant in broad text', ['match' => $matches[1]]);
                return $matches[1];
            }

            \Log::warning('No ORD pattern found in OCR text', [
                'image' => basename($imagePath),
                'focused_text' => $text,
                'broad_text' => $broadText
            ]);

            return null;
        } catch (Exception $e) {
            // Log the error for debugging
            \Log::warning('OCR extraction failed', [
                'image' => $imagePath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Create an individual PDF file for a single page
     * 
     * @param string $sourcePdfPath
     * @param int $pageNumber
     * @param string $orderNumber
     * @return string
     */
    private function createIndividualPdf(string $sourcePdfPath, int $pageNumber, string $orderNumber): string
    {
        // Find the order to get location details for filename
        $order = $this->findOrderByNumber($orderNumber);
        
        // Generate filename using same format as HasOrderForm
        $date = now()->format('Y-m-d');
        
        if ($order && $order->location) {
            // Clean the location name and city for filename (same as HasOrderForm)
            $locationName = preg_replace('/[^A-Za-z0-9\-_]/', '_', $order->location->name);
            $city = preg_replace('/[^A-Za-z0-9\-_]/', '_', $order->location->city);
            
            // Include order number to prevent collisions
            $filename = "{$date}_{$locationName}_{$city}_{$order->order_number}_delivery_tag.pdf";
        } else {
            // Fallback if no location is found - still include extracted order number
            $filename = "{$date}_{$orderNumber}_delivery_tag.pdf";
        }
        
        $relativePath = 'delivery-tags/' . $filename;
        
        // Create new PDF with single page using FPDI
        $tempOutputPath = storage_path('app/temp/' . $filename);
        
        // Ensure temp directory exists
        if (!file_exists(dirname($tempOutputPath))) {
            mkdir(dirname($tempOutputPath), 0755, true);
        }
        
        $pdf = new Fpdi();
        $pdf->AddPage();
        
        // Set source file
        $pdf->setSourceFile($sourcePdfPath);
        
        // Import the specific page
        $templateId = $pdf->importPage($pageNumber);
        
        // Use the imported page and adjust the size to fit the page
        $pdf->useTemplate($templateId, 0, 0, 210); // A4 width in mm
        
        // Output to temporary file
        $pdf->Output('F', $tempOutputPath);
        
        // Upload to R2
        $pdfContent = file_get_contents($tempOutputPath);
        Storage::disk('r2')->put($relativePath, $pdfContent);
        
        // Clean up temporary file
        unlink($tempOutputPath);
        
        return $relativePath;
    }

    /**
     * Find order by order number
     * 
     * @param string $extractedNumber
     * @return Order|null
     */
    private function findOrderByNumber(string $extractedNumber): ?Order
    {
        // Try different format patterns to match against order_number field
        $patterns = [
            'ORD-' . str_pad($extractedNumber, 5, '0', STR_PAD_LEFT), // ORD-00921
            'ORD-' . str_pad($extractedNumber, 4, '0', STR_PAD_LEFT), // ORD-0921  
            'ORD-' . str_pad($extractedNumber, 3, '0', STR_PAD_LEFT), // ORD-921
            'ORD-' . $extractedNumber, // ORD-921 (no padding)
        ];

        \Log::info('Order matching attempt', [
            'extracted_number' => $extractedNumber,
            'trying_patterns' => $patterns
        ]);

        foreach ($patterns as $pattern) {
            $order = Order::with('location')->where('order_number', $pattern)->first();
            if ($order) {
                \Log::info('Order match found', [
                    'pattern' => $pattern,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'location' => $order->location?->name
                ]);
                return $order;
            }
        }

        \Log::info('No order match found', [
            'extracted_number' => $extractedNumber,
            'tried_patterns' => $patterns
        ]);

        return null;
    }

    /**
     * Get the label for an order status
     * 
     * @param string $status
     * @return string
     */
    private function getOrderStatusLabel(string $status): string
    {
        try {
            $orderStatus = OrderStatus::from($status);
            return $orderStatus->label();
        } catch (\ValueError $e) {
            // If the status value doesn't match any enum case, return the raw value
            return ucfirst(str_replace('_', ' ', $status));
        }
    }
}
