<?php

namespace App\Services;

use App\Models\NuruxploreProject;

class ExportService
{
    public function exportToPdf(NuruxploreProject $project): string
    {
        $html = $this->buildDocumentHTML($project);
        
        // Basic PDF generation using built-in PHP
        $filename = 'exports/' . $project->id . '_' . time() . '.pdf';
        $path = storage_path('app/public/' . $filename);
        
        // Ensure directory exists
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        // Save as HTML for now (DomPDF integration requires composer package)
        file_put_contents($path . '.html', $html);
        
        // For MVP: return HTML file path
        return $filename . '.html';
    }

    public function exportToWord(NuruxploreProject $project): string
    {
        $content = $this->buildDocumentText($project);
        
        $filename = 'exports/' . $project->id . '_' . time() . '.txt';
        $path = storage_path('app/public/' . $filename);
        
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        
        file_put_contents($path, $content);
        
        return $filename;
    }

    protected function buildDocumentHTML(NuruxploreProject $project): string
    {
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= '<title>' . e($project->title) . '</title>';
        $html .= '<style>body{font-family:serif;line-height:1.6;max-width:800px;margin:0 auto;padding:40px}h1{font-size:24px;text-align:center}h2{font-size:18px;margin-top:30px}h3{font-size:15px;margin-top:20px}p{text-align:justify}</style>';
        $html .= '</head><body>';
        
        $html .= '<h1>' . e($project->title) . '</h1>';
        
        foreach ($project->sections()->whereNull('parent_id')->orderBy('order')->get() as $chapter) {
            $html .= '<h2>' . e($chapter->title) . '</h2>';
            if ($chapter->content) {
                $html .= $chapter->content;
            }
            
            foreach ($chapter->children as $subsection) {
                $html .= '<h3>' . e($subsection->title) . '</h3>';
                if ($subsection->content) {
                    $html .= $subsection->content;
                }
            }
        }
        
        $sources = $project->sources;
        if ($sources->isNotEmpty()) {
            $html .= '<h2>References</h2><ol>';
            foreach ($sources as $source) {
                $html .= '<li>' . e($source->author) . ' (' . e($source->year) . '). ' . e($source->title) . '.</li>';
            }
            $html .= '</ol>';
        }
        
        $html .= '</body></html>';
        
        return $html;
    }

    protected function buildDocumentText(NuruxploreProject $project): string
    {
        $text = strtoupper($project->title) . "\n\n";
        
        foreach ($project->sections()->whereNull('parent_id')->orderBy('order')->get() as $chapter) {
            $text .= strtoupper($chapter->title) . "\n\n";
            if ($chapter->content) {
                $text .= strip_tags($chapter->content) . "\n\n";
            }
            
            foreach ($chapter->children as $subsection) {
                $text .= $subsection->title . "\n\n";
                if ($subsection->content) {
                    $text .= strip_tags($subsection->content) . "\n\n";
                }
            }
        }
        
        return $text;
    }
}