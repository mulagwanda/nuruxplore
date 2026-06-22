<?php

namespace App\Services;

use App\Models\NuruxploreProject;

class ExportService
{
    public function exportToPdf(NuruxploreProject $project): string
    {
        $content = $this->stripDocumentTitleFromContent((string) ($project->content ?? ''), (string) $project->title);

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= '<title>' . e($project->title) . '</title>';
        $html .= '<style>
            body { font-family: "Times New Roman", serif; line-height: 1.6; max-width: 850px; margin: 0 auto; padding: 40px; }
            h1 { font-size: 20px; text-align: center; margin-bottom: 30px; }
            h2 { font-size: 16px; margin-top: 24px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
            h3 { font-size: 14px; margin-top: 16px; }
            h4 { font-size: 13px; margin-top: 14px; }
            p { text-align: justify; margin: 8px 0; }
            table { border-collapse: collapse; width: 100%; margin: 12px 0; }
            th, td { border: 1px solid #ddd; padding: 6px; font-size: 12px; vertical-align: top; }
            th { background: #f5f5f5; }
            ul, ol { margin: 8px 0 8px 28px; padding: 0; }
            li { margin: 6px 0; text-align: justify; }
            .document-title { text-transform: uppercase; }
            .references li { margin-bottom: 10px; }
            .placeholder-warning { color:#7c5cff; }
        </style></head><body>';

        $html .= '<h1 class="document-title">' . e($project->title) . '</h1>';
        $html .= $this->markdownToHtml($content);
        $html .= '</body></html>';

        $filename = 'exports/' . $project->id . '_' . time() . '.html';
        $path = storage_path('app/public/' . $filename);

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $html);

        return $filename;
    }

    public function exportToWord(NuruxploreProject $project): string
    {
        $content = $this->stripDocumentTitleFromContent((string) ($project->content ?? ''), (string) $project->title);

        $text = strtoupper((string) $project->title) . "\n\n";
        $text .= $this->markdownToPlainText($content);
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        $filename = 'exports/' . $project->id . '_' . time() . '.txt';
        $path = storage_path('app/public/' . $filename);

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, trim($text) . "\n");

        return $filename;
    }

    protected function markdownToHtml(string $markdown): string
    {
        $markdown = trim(str_replace(["\r\n", "\r"], "\n", $markdown));
        if ($markdown === '') {
            return '';
        }

        $blocks = preg_split("/\n{2,}/", $markdown) ?: [];
        $html = '';

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            if (preg_match('/^###\s+(.+)$/u', $block, $match)) {
                $html .= '<h3>' . $this->inlineMarkdown(e($match[1])) . '</h3>';
                continue;
            }

            if (preg_match('/^##\s+(.+)$/u', $block, $match)) {
                $html .= '<h2>' . $this->inlineMarkdown(e($match[1])) . '</h2>';
                continue;
            }

            if (preg_match('/^#\s+(.+)$/u', $block, $match)) {
                $html .= '<h1>' . $this->inlineMarkdown(e($match[1])) . '</h1>';
                continue;
            }

            if ($this->isMarkdownTable($block)) {
                $html .= $this->tableMarkdownToHtml($block);
                continue;
            }

            if ($this->isOrderedList($block)) {
                $class = stripos($block, 'World Health Organization') !== false || stripos($block, 'Avert') !== false || stripos($block, 'Ministry of Health') !== false
                    ? ' class="references"'
                    : '';
                $html .= '<ol' . $class . '>' . $this->listMarkdownToHtml($block, true) . '</ol>';
                continue;
            }

            if ($this->isUnorderedList($block)) {
                $html .= '<ul>' . $this->listMarkdownToHtml($block, false) . '</ul>';
                continue;
            }

            $paragraphs = preg_split('/\n+/', $block) ?: [$block];
            foreach ($paragraphs as $paragraph) {
                $paragraph = trim($paragraph);
                if ($paragraph !== '') {
                    $html .= '<p>' . $this->inlineMarkdown(e($paragraph)) . '</p>';
                }
            }
        }

        return $html;
    }

    protected function isMarkdownTable(string $block): bool
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\n/', $block) ?: [])));
        return count($lines) >= 2 && str_contains($lines[0], '|') && preg_match('/^\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?$/', $lines[1]);
    }

    protected function tableMarkdownToHtml(string $block): string
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\n/', $block) ?: [])));
        if (count($lines) < 2) {
            return '<p>' . nl2br(e($block)) . '</p>';
        }

        $headers = $this->splitTableRow($lines[0]);
        $html = '<table><thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>' . $this->inlineMarkdown(e($header)) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach (array_slice($lines, 2) as $line) {
            if (!str_contains($line, '|')) {
                continue;
            }
            $html .= '<tr>';
            foreach ($this->splitTableRow($line) as $cell) {
                $html .= '<td>' . $this->inlineMarkdown(e($cell)) . '</td>';
            }
            $html .= '</tr>';
        }

        return $html . '</tbody></table>';
    }

    protected function splitTableRow(string $line): array
    {
        $line = trim($line);
        $line = trim($line, '|');
        return array_map(fn ($cell) => trim($cell), explode('|', $line));
    }

    protected function isOrderedList(string $block): bool
    {
        return (bool) preg_match('/^\s*\d+[\.)]\s+/m', $block);
    }

    protected function isUnorderedList(string $block): bool
    {
        return (bool) preg_match('/^\s*[-*•]\s+/m', $block);
    }

    protected function listMarkdownToHtml(string $block, bool $ordered): string
    {
        $items = [];
        $buffer = '';
        $lines = preg_split('/\n/', $block) ?: [];
        $pattern = $ordered ? '/^\s*\d+[\.)]\s+(.+)$/u' : '/^\s*[-*•]\s+(.+)$/u';

        foreach ($lines as $line) {
            if (preg_match($pattern, trim($line), $m)) {
                if ($buffer !== '') {
                    $items[] = trim($buffer);
                }
                $buffer = $m[1];
            } else {
                $buffer .= ' ' . trim($line);
            }
        }

        if ($buffer !== '') {
            $items[] = trim($buffer);
        }

        $html = '';
        foreach ($items as $item) {
            $html .= '<li>' . $this->inlineMarkdown(e($item)) . '</li>';
        }
        return $html;
    }

    protected function inlineMarkdown(string $html): string
    {
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html) ?? $html;
        $html = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $html) ?? $html;

        $html = preg_replace(
            '/\[(Author, Year|Year|number|value|insert results|study design|sampling method|statistical software|data analysis plan|truncated)\]/i',
            '<span class="placeholder-warning">[$1]</span>',
            $html
        ) ?? $html;

        return $html;
    }

    protected function markdownToPlainText(string $markdown): string
    {
        $markdown = preg_replace('/^#{1,6}\s+/m', '', $markdown) ?? $markdown;
        $markdown = preg_replace('/\*\*(.+?)\*\*/s', '$1', $markdown) ?? $markdown;
        $markdown = preg_replace('/\*(.+?)\*/s', '$1', $markdown) ?? $markdown;
        return trim($markdown);
    }

    protected function stripDocumentTitleFromContent(string $content, string $title): string
    {
        $content = trim($content);
        $title = trim($title);

        if ($content === '' || $title === '') {
            return $content;
        }

        $lines = preg_split('/\R/u', $content) ?: [];
        $cleanLines = [];
        $removed = false;
        $nonEmptySeen = 0;

        foreach ($lines as $line) {
            $rawLine = trim((string) $line);

            if ($rawLine !== '') {
                $nonEmptySeen++;
            }

            if (!$removed && $nonEmptySeen <= 2 && $rawLine !== '') {
                $candidate = $this->normalizeDocumentTitleLine($rawLine);
                $expected = $this->normalizeDocumentTitleLine($title);

                if ($candidate === $expected) {
                    $removed = true;
                    continue;
                }
            }

            $cleanLines[] = $line;
        }

        return trim(implode("\n", $cleanLines));
    }

    protected function normalizeDocumentTitleLine(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/^\s*#{1,6}\s+/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        $value = preg_replace('/[^\p{L}\p{N}\s]/u', '', $value) ?? $value;
        return mb_strtolower(trim($value));
    }
}
