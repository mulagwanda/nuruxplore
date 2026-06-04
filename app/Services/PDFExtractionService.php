<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\PdfToText\Pdf;

class PDFExtractionService
{
    /**
     * Extract text from a PDF file
     */
    public function extract(string $filePath): ?string
    {
        try {
            $fullPath = Storage::disk('public')->path($filePath);
            
            if (!file_exists($fullPath)) {
                Log::warning('PDF file not found: ' . $fullPath);
                return null;
            }

            // For Linux servers, you may need the pdftotext binary path
            // On macOS (ServBay), it's usually in /usr/local/bin or /opt/homebrew/bin
            $pdf = new Pdf($fullPath);
            
            $text = $pdf->text();
            
            if (empty(trim($text))) {
                Log::warning('PDF extraction returned empty text: ' . $filePath);
                return null;
            }

            return $text;
        } catch (\Exception $e) {
            Log::error('PDF extraction failed: ' . $e->getMessage(), [
                'file' => $filePath,
            ]);
            return null;
        }
    }

    /**
     * Extract text from uploaded file and save to source
     */
    public function extractAndSave(\App\Models\NuruxploreSource $source): bool
    {
        if (!$source->file_path) {
            return false;
        }

        $text = $this->extract($source->file_path);
        
        if ($text) {
            $source->update([
                'extracted_text' => $text,
            ]);
            return true;
        }

        return false;
    }
}