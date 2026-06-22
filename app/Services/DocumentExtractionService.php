<?php

namespace App\Services;

use App\Models\NuruxploreSource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DocumentExtractionService
{
    public function extractAndSave(NuruxploreSource $source): NuruxploreSource
    {
        if (!$source->file_path || !Storage::disk('public')->exists($source->file_path)) {
            $source->markExtraction('failed', 'File not found.');
            return $source->fresh();
        }

        $path = Storage::disk('public')->path($source->file_path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        try {
            $text = match ($extension) {
                'pdf' => $this->extractPdf($path, $source),
                'docx' => $this->extractDocx($path),
                'txt', 'md' => file_get_contents($path) ?: '',
                'csv' => $this->extractCsv($path),
                'xlsx' => $this->extractXlsx($path),
                default => '',
            };

            $text = $this->cleanText($text);

            if ($text === '') {
                $source->markExtraction('failed', 'No readable text extracted. Use PDF, DOCX, TXT, CSV, or XLSX.');
                return $source->fresh();
            }

            $source->update([
                'extracted_text' => $text,
                'metadata' => array_merge($source->metadata ?? [], [
                    'file_extension' => $extension,
                    'extraction_status' => 'completed',
                    'extraction_message' => 'Text extracted successfully.',
                    'word_count' => str_word_count($text),
                    'character_count' => strlen($text),
                    'extracted_at' => now()->toISOString(),
                ]),
            ]);

            return $source->fresh();
        } catch (\Throwable $e) {
            Log::error('Document extraction failed', [
                'source_id' => $source->id,
                'message' => $e->getMessage(),
            ]);

            $source->markExtraction('failed', $e->getMessage());
            return $source->fresh();
        }
    }

    protected function extractPdf(string $path, NuruxploreSource $source): string
    {
        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            $parser = new \Smalot\PdfParser\Parser();
            return $parser->parseFile($path)->getText();
        }

        $pdftotext = trim((string) shell_exec('command -v pdftotext 2>/dev/null'));
        if ($pdftotext !== '') {
            $output = tempnam(sys_get_temp_dir(), 'nuru_pdf_');
            @shell_exec(escapeshellcmd($pdftotext) . ' -layout ' . escapeshellarg($path) . ' ' . escapeshellarg($output) . ' 2>/dev/null');
            $text = is_file($output) ? (file_get_contents($output) ?: '') : '';
            @unlink($output);
            return $text;
        }

        if (class_exists(\App\Services\PDFExtractionService::class)) {
            app(\App\Services\PDFExtractionService::class)->extractAndSave($source);
            return (string) $source->fresh()->extracted_text;
        }

        return '';
    }

    protected function extractDocx(string $path): string
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml') ?: '';
        $zip->close();

        $xml = str_replace(['</w:p>', '</w:tr>'], "\n", $xml);
        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    protected function extractCsv(string $path): string
    {
        $handle = fopen($path, 'r');
        if (!$handle) {
            return '';
        }

        $rows = [];
        $count = 0;
        while (($data = fgetcsv($handle)) !== false && $count < 250) {
            $rows[] = implode(' | ', array_map(fn($v) => trim((string) $v), $data));
            $count++;
        }
        fclose($handle);

        return implode("\n", $rows);
    }

    protected function extractXlsx(string $path): string
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml) {
            preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $sharedXml, $matches);
            foreach ($matches[1] ?? [] as $value) {
                $shared[] = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }

        $text = '';
        for ($i = 1; $i <= 5; $i++) {
            $sheetXml = $zip->getFromName("xl/worksheets/sheet{$i}.xml");
            if (!$sheetXml) {
                continue;
            }

            preg_match_all('/<c[^>]*(?:t="s")?[^>]*>.*?<v>(.*?)<\/v>.*?<\/c>/s', $sheetXml, $cells);
            $row = [];
            foreach (($cells[1] ?? []) as $idx => $cell) {
                $value = is_numeric($cell) && isset($shared[(int) $cell]) ? $shared[(int) $cell] : $cell;
                $row[] = $value;
                if (count($row) >= 12) {
                    $text .= implode(' | ', $row) . "\n";
                    $row = [];
                }
                if ($idx > 1200) {
                    break;
                }
            }
            if ($row) {
                $text .= implode(' | ', $row) . "\n";
            }
        }

        $zip->close();
        return $text;
    }

    protected function cleanText(string $text): string
    {
        $text = preg_replace('/\x{00A0}/u', ' ', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\R{3,}/', "\n\n", $text);
        return trim((string) $text);
    }
}
