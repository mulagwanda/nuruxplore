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
            $this->markExtraction($source, 'failed', 'File not found.');
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
                $this->markExtraction($source, 'failed', 'No readable text extracted. Use PDF, DOCX, TXT, CSV, or XLSX.');
                return $source->fresh();
            }

            $metadata = array_merge($source->metadata ?? [], [
                'file_extension' => $extension,
                'extraction_status' => 'completed',
                'extraction_message' => 'Text extracted successfully.',
                'word_count' => str_word_count($text),
                'character_count' => strlen($text),
                'extracted_at' => now()->toISOString(),
            ]);

            if (in_array($extension, ['csv', 'xlsx'], true) || in_array(($metadata['document_role'] ?? ''), ['dataset', 'collected_data'], true)) {
                $metadata = array_merge($metadata, $this->datasetMetadataFromExtractedText($text));
                $metadata['is_dataset'] = true;
            }

            $source->update([
                'extracted_text' => $text,
                'metadata' => $metadata,
            ]);

            return $source->fresh();
        } catch (\Throwable $e) {
            Log::error('Document extraction failed', [
                'source_id' => $source->id,
                'message' => $e->getMessage(),
            ]);

            $this->markExtraction($source, 'failed', $e->getMessage());
            return $source->fresh();
        }
    }

    protected function extractPdf(string $path, NuruxploreSource $source): string
    {
        // Preferred on Ubuntu servers: poppler-utils. This avoids fragile Spatie object
        // initialization and works well for proposal PDFs.
        $pdftotext = trim((string) shell_exec('command -v pdftotext 2>/dev/null'));
        if ($pdftotext !== '') {
            $output = tempnam(sys_get_temp_dir(), 'nuru_pdf_');
            @shell_exec(escapeshellcmd($pdftotext) . ' -layout -enc UTF-8 ' . escapeshellarg($path) . ' ' . escapeshellarg($output) . ' 2>/dev/null');
            $text = is_file($output) ? (file_get_contents($output) ?: '') : '';
            @unlink($output);
            if (trim($text) !== '') {
                return $text;
            }
        }

        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $text = $parser->parseFile($path)->getText();
                if (trim((string) $text) !== '') {
                    return (string) $text;
                }
            } catch (\Throwable $e) {
                Log::warning('Smalot PDF extraction failed; trying fallback', [
                    'source_id' => $source->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        // Optional fallback only. Use Spatie correctly so `$pdf` is initialized before text().
        if (class_exists(\Spatie\PdfToText\Pdf::class)) {
            try {
                if (method_exists(\Spatie\PdfToText\Pdf::class, 'getText')) {
                    $text = \Spatie\PdfToText\Pdf::getText($path);
                    if (trim((string) $text) !== '') {
                        return (string) $text;
                    }
                }

                if ($pdftotext !== '') {
                    $pdf = new \Spatie\PdfToText\Pdf($pdftotext);
                    if (method_exists($pdf, 'setPdf')) {
                        $text = $pdf->setPdf($path)->text();
                        if (trim((string) $text) !== '') {
                            return (string) $text;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Spatie PDF extraction failed; no readable PDF text returned', [
                    'source_id' => $source->id,
                    'message' => $e->getMessage(),
                ]);
            }
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
        while (($data = fgetcsv($handle)) !== false && $count < 1000) {
            $rows[] = array_map(fn ($v) => trim((string) $v), $data);
            $count++;
        }
        fclose($handle);

        return $this->rowsToPipeText($rows);
    }

    protected function extractXlsx(string $path): string
    {
        $rows = $this->readXlsxRows($path, 1000);
        return $this->rowsToPipeText($rows);
    }

    protected function readXlsxRows(string $path, int $maxRows = 1000): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $shared = $this->readSharedStrings($zip);
        $rows = [];

        // Prefer first worksheet. Most uploaded field-data files use the first sheet as the dataset.
        for ($sheet = 1; $sheet <= 3 && count($rows) < $maxRows; $sheet++) {
            $sheetXml = $zip->getFromName("xl/worksheets/sheet{$sheet}.xml");
            if (!$sheetXml) {
                continue;
            }

            preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $sheetXml, $rowMatches);
            foreach ($rowMatches[1] ?? [] as $rowXml) {
                if (count($rows) >= $maxRows) {
                    break 2;
                }

                $row = [];
                preg_match_all('/<c\s+([^>]*)>(.*?)<\/c>/s', $rowXml, $cellMatches, PREG_SET_ORDER);
                foreach ($cellMatches as $cellMatch) {
                    $attrs = $cellMatch[1] ?? '';
                    $cellXml = $cellMatch[2] ?? '';
                    $colIndex = $this->xlsxColumnIndexFromAttributes($attrs);
                    $value = $this->xlsxCellValue($attrs, $cellXml, $shared);
                    if ($colIndex === null) {
                        $colIndex = count($row);
                    }
                    $row[$colIndex] = $value;
                }

                if (!empty($row)) {
                    ksort($row);
                    $max = max(array_keys($row));
                    $normalized = [];
                    for ($i = 0; $i <= $max; $i++) {
                        $normalized[] = trim((string) ($row[$i] ?? ''));
                    }
                    if (trim(implode('', $normalized)) !== '') {
                        $rows[] = $normalized;
                    }
                }
            }
        }

        $zip->close();
        return $rows;
    }

    protected function readSharedStrings(ZipArchive $zip): array
    {
        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if (!$sharedXml) {
            return $shared;
        }

        preg_match_all('/<si[^>]*>(.*?)<\/si>/s', $sharedXml, $items);
        foreach ($items[1] ?? [] as $itemXml) {
            preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $itemXml, $texts);
            $value = implode('', $texts[1] ?? []);
            $shared[] = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        return $shared;
    }

    protected function xlsxColumnIndexFromAttributes(string $attrs): ?int
    {
        if (!preg_match('/\br="([A-Z]+)\d+"/i', $attrs, $m)) {
            return null;
        }
        $letters = strtoupper($m[1]);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }
        return $index - 1;
    }

    protected function xlsxCellValue(string $attrs, string $cellXml, array $shared): string
    {
        if (preg_match('/<is[^>]*>.*?<t[^>]*>(.*?)<\/t>.*?<\/is>/s', $cellXml, $m)) {
            return html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        if (!preg_match('/<v[^>]*>(.*?)<\/v>/s', $cellXml, $m)) {
            return '';
        }
        $raw = html_entity_decode(trim(strip_tags($m[1])), ENT_QUOTES | ENT_XML1, 'UTF-8');
        if (preg_match('/\bt="s"/i', $attrs) && is_numeric($raw) && isset($shared[(int) $raw])) {
            return (string) $shared[(int) $raw];
        }
        return $raw;
    }

    protected function rowsToPipeText(array $rows): string
    {
        $lines = [];
        foreach ($rows as $row) {
            $lines[] = implode(' | ', array_map(fn ($v) => trim((string) $v), $row));
        }
        return implode("\n", $lines);
    }

    protected function datasetMetadataFromExtractedText(string $text): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $text) ?: [])));
        if (empty($lines)) {
            return ['dataset_rows_extracted' => 0, 'dataset_columns' => []];
        }

        $delimiter = str_contains($lines[0], '|') ? '|' : (str_contains($lines[0], ',') ? ',' : null);
        if (!$delimiter) {
            return [
                'dataset_rows_extracted' => max(0, count($lines) - 1),
                'dataset_columns' => [],
                'dataset_sample_rows' => array_slice($lines, 1, 8),
                'dataset_summary' => 'Dataset text extracted, but columns could not be detected reliably.',
            ];
        }

        $headers = array_values(array_filter(array_map(fn ($v) => $this->normalizeDatasetHeader($v), explode($delimiter, $lines[0]))));
        $rows = [];
        foreach (array_slice($lines, 1) as $line) {
            $values = array_map(fn ($v) => trim((string) $v), explode($delimiter, $line));
            if (count($values) < 2) {
                continue;
            }
            $row = [];
            foreach ($headers as $i => $header) {
                $row[$header] = $values[$i] ?? '';
            }
            if (trim(implode('', $row)) !== '') {
                $rows[] = $row;
            }
        }

        $numeric = [];
        $frequencies = [];
        foreach ($headers as $header) {
            $values = array_values(array_filter(array_map(fn ($row) => trim((string) ($row[$header] ?? '')), $rows), fn ($v) => $v !== ''));
            if (empty($values)) {
                continue;
            }

            $numericValues = [];
            foreach ($values as $value) {
                $clean = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', $value));
                if ($clean !== '' && is_numeric($clean)) {
                    $numericValues[] = (float) $clean;
                }
            }

            $numericRatio = count($numericValues) / max(1, count($values));
            if ($numericRatio >= 0.75 && count($numericValues) >= 3) {
                $numeric[$header] = [
                    'count' => count($numericValues),
                    'mean' => round(array_sum($numericValues) / max(1, count($numericValues)), 2),
                    'min' => round(min($numericValues), 2),
                    'max' => round(max($numericValues), 2),
                    'sum' => round(array_sum($numericValues), 2),
                ];
            } else {
                $counts = [];
                foreach ($values as $value) {
                    $key = $value === '' ? 'Missing' : $value;
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                }
                arsort($counts);
                $items = [];
                foreach (array_slice($counts, 0, 12, true) as $label => $count) {
                    $items[] = [
                        'value' => $label,
                        'frequency' => $count,
                        'percentage' => round(($count / max(1, count($rows))) * 100, 1),
                    ];
                }
                $frequencies[$header] = $items;
            }
        }

        $indicators = $this->computedDatasetIndicators($headers, $rows, $numeric, $frequencies);
        $analysisMarkdown = $this->datasetAnalysisMarkdown($headers, $rows, $numeric, $frequencies, $indicators);

        return [
            'dataset_rows_extracted' => count($rows),
            'dataset_columns' => array_slice($headers, 0, 80),
            'dataset_sample_rows' => array_slice($lines, 1, 8),
            'dataset_frequency_tables' => $frequencies,
            'dataset_numeric_summaries' => $numeric,
            'dataset_computed_indicators' => $indicators,
            'dataset_analysis_markdown' => $analysisMarkdown,
            'dataset_summary' => 'Dataset extracted with ' . count($rows) . ' data rows and ' . count($headers) . ' columns. Use the computed summaries, tables, and indicators as real Chapter 4/5 evidence.',
        ];
    }

    protected function normalizeDatasetHeader(string $header): string
    {
        $header = trim($header);
        $header = preg_replace('/\s+/', '_', strtolower($header));
        $header = preg_replace('/[^a-z0-9_]/', '', $header);
        return trim((string) $header, '_') ?: 'column';
    }

    protected function computedDatasetIndicators(array $headers, array $rows, array $numeric, array $frequencies): array
    {
        $indicators = [];
        $rowCount = count($rows);
        $indicators['respondents'] = $rowCount;

        $before = $this->findHeader($headers, ['revenue_before', 'monthly_revenue_before', 'before_mobile_money']);
        $after = $this->findHeader($headers, ['revenue_after', 'monthly_revenue_after', 'after_mobile_money']);
        if ($before && $after && isset($numeric[$before], $numeric[$after])) {
            $beforeMean = (float) $numeric[$before]['mean'];
            $afterMean = (float) $numeric[$after]['mean'];
            $change = $afterMean - $beforeMean;
            $percent = $beforeMean != 0.0 ? ($change / $beforeMean) * 100 : null;
            $indicators['average_revenue_before'] = round($beforeMean, 2);
            $indicators['average_revenue_after'] = round($afterMean, 2);
            $indicators['average_revenue_change'] = round($change, 2);
            $indicators['average_revenue_change_percent'] = $percent === null ? null : round($percent, 1);
        }

        foreach ([
            'uses_mobile_money' => ['uses_mobile_money', 'mobile_money_use', 'use_mobile_money'],
            'customer_reach_improved' => ['customer_reach_improved', 'customer_reach', 'reach_improved'],
            'record_keeping_improved' => ['record_keeping_improved', 'record_keeping'],
            'access_to_credit_improved' => ['access_to_credit_improved', 'credit_improved', 'access_to_credit'],
        ] as $name => $needles) {
            $header = $this->findHeader($headers, $needles);
            if ($header) {
                $yes = 0;
                foreach ($rows as $row) {
                    if (preg_match('/^(yes|y|true|1|improved)$/i', trim((string) ($row[$header] ?? '')))) {
                        $yes++;
                    }
                }
                $indicators[$name . '_yes_count'] = $yes;
                $indicators[$name . '_yes_percent'] = round(($yes / max(1, $rowCount)) * 100, 1);
            }
        }

        $growth = $this->findHeader($headers, ['growth_rating', 'business_growth_rating', 'growth_rating_1_5']);
        if ($growth && isset($numeric[$growth])) {
            $indicators['average_growth_rating'] = $numeric[$growth]['mean'];
        }

        return $indicators;
    }

    protected function findHeader(array $headers, array $needles): ?string
    {
        foreach ($headers as $header) {
            foreach ($needles as $needle) {
                if (str_contains($header, $needle)) {
                    return $header;
                }
            }
        }
        return null;
    }

    protected function datasetAnalysisMarkdown(array $headers, array $rows, array $numeric, array $frequencies, array $indicators): string
    {
        $lines = [];
        $lines[] = 'Dataset analysis summary: ' . count($rows) . ' respondents and ' . count($headers) . ' variables were extracted.';

        if (!empty($indicators)) {
            $lines[] = '';
            $lines[] = 'Key computed indicators:';
            foreach ($indicators as $key => $value) {
                if ($value !== null && !is_array($value)) {
                    $lines[] = '- ' . str_replace('_', ' ', $key) . ': ' . $value;
                }
            }
        }

        foreach (array_slice($frequencies, 0, 8, true) as $column => $items) {
            $lines[] = '';
            $lines[] = 'Frequency table for ' . str_replace('_', ' ', $column) . ':';
            foreach (array_slice($items, 0, 8) as $item) {
                $lines[] = '- ' . $item['value'] . ': ' . $item['frequency'] . ' (' . $item['percentage'] . '%)';
            }
        }

        foreach (array_slice($numeric, 0, 8, true) as $column => $summary) {
            $lines[] = '';
            $lines[] = 'Numeric summary for ' . str_replace('_', ' ', $column) . ': mean ' . $summary['mean'] . ', min ' . $summary['min'] . ', max ' . $summary['max'] . ', count ' . $summary['count'] . '.';
        }

        return implode("\n", $lines);
    }

    protected function markExtraction(NuruxploreSource $source, string $status, ?string $message = null, array $extra = []): void
    {
        if (method_exists($source, 'markExtraction')) {
            $source->markExtraction($status, $message, $extra);
            return;
        }

        $metadata = $source->metadata ?? [];
        $metadata['extraction_status'] = $status;
        if ($message !== null) {
            $metadata['extraction_message'] = $message;
        }
        $metadata = array_merge($metadata, $extra);
        $source->forceFill(['metadata' => $metadata])->save();
    }

    protected function cleanText(string $text): string
    {
        $text = preg_replace('/\x{00A0}/u', ' ', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\R{3,}/', "\n\n", $text);
        return trim((string) $text);
    }
}
