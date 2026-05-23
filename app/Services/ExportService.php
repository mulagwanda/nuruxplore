<?php

namespace App\Services;

use App\Models\NuruxploreProject;

class ExportService
{
    public function exportToPdf(NuruxploreProject $project): string
    {
        // Build HTML from project content
        $content = $project->content ?? '';
        
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= '<title>' . e($project->title) . '</title>';
        $html .= '<style>
            body { font-family: "Times New Roman", serif; line-height: 1.6; max-width: 800px; margin: 0 auto; padding: 40px; }
            h1 { font-size: 20px; text-align: center; margin-bottom: 30px; }
            h2 { font-size: 16px; margin-top: 24px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
            h3 { font-size: 14px; margin-top: 16px; }
            p { text-align: justify; margin: 8px 0; }
            table { border-collapse: collapse; width: 100%; margin: 12px 0; }
            th, td { border: 1px solid #ddd; padding: 6px; font-size: 12px; }
            th { background: #f5f5f5; }
        </style></head><body>';
        
        // Add title
        $html .= '<h1>' . e($project->title) . '</h1>';
        
        // Convert Markdown content to HTML
        $htmlContent = $this->markdownToHtml($content);
        $html .= $htmlContent;
        
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
        $content = $project->content ?? '';
        
        $text = strtoupper($project->title) . "\n\n";
        $text .= strip_tags($this->markdownToHtml($content));
        
        $filename = 'exports/' . $project->id . '_' . time() . '.txt';
        $path = storage_path('app/public/' . $filename);
        
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        file_put_contents($path, $text);
        
        return $filename;
    }

    protected function markdownToHtml(string $markdown): string
    {
        // Basic Markdown to HTML conversion
        $html = $markdown;
        
        // Headers
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);
        
        // Bold and italic
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);
        
        // Inline citations
        $html = preg_replace('/\[([^\]]+)\]/', '<span style="color:#7c5cff;">[$1]</span>', $html);
        
        // Paragraphs
        $html = '<p>' . preg_replace('/\n\n+/', '</p><p>', $html) . '</p>';
        $html = str_replace('<p></p>', '', $html);
        
        return $html;
    }
}