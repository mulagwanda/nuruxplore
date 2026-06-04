<?php

namespace App\Services;

use App\Models\NuruxploreProject;
use App\Models\NuruxploreVersion;
use Illuminate\Support\Facades\Log;

class NuruAIService
{
    protected GroqAIService $groq;

    public function __construct(GroqAIService $groq)
    {
        $this->groq = $groq;
    }

    /**
     * Generate complete thesis OR proposal with progress steps
     * @param string $type 'thesis' or 'proposal'
     */
    public function generateCompleteThesis(NuruxploreProject $project, string $userTopic, string $type = 'thesis'): array
    {
        $steps = [];
        $documentType = $type === 'proposal' ? 'Research Proposal' : 'Thesis';
        
        // Step 1: Smart Title
        $steps[] = ['step' => 'title', 'status' => 'processing', 'message' => '🎯 Crafting title...'];
        $titleResult = $this->groq->callGroqAPI(
            "Create a short academic title. Return ONLY the title.",
            "Create " . ($type === 'proposal' ? 'research proposal' : 'thesis') . " title for: {$userTopic}",
            200
        );
        $smartTitle = trim(str_replace(['"', "'", '**', 'Title:'], '', $titleResult['content'] ?? $userTopic));
        $project->update(['title' => $smartTitle]);
        $steps[0]['status'] = 'completed';
        $steps[0]['message'] = '✅ ' . $smartTitle;

        // Step 2: Extract uploaded file content if exists
        $uploadedContent = $this->extractUploadedContent($project);
        if ($uploadedContent) {
            $steps[] = ['step' => 'extract', 'status' => 'completed', 'message' => '📄 Document extracted (' . str_word_count(strip_tags($uploadedContent)) . ' words)'];
        }

        // Step 3: Generate document based on type
        $steps[] = ['step' => 'document', 'status' => 'processing', 'message' => '📝 Writing ' . $documentType . '...'];
        
        if ($type === 'proposal') {
            $document = $this->generateProposal($smartTitle, $project, $uploadedContent);
        } else {
            $document = $this->generateThesis($smartTitle, $project, $uploadedContent);
        }
        
        if (!empty($document)) {
            \App\Models\NuruxploreProject::where('id', $project->id)->update([
                'content' => $document,
                'word_count' => str_word_count(strip_tags($document)),
                'last_edited_at' => now(),
                'status' => 'draft',
            ]);
            
            $v = NuruxploreVersion::where('project_id', $project->id)->max('version_number') ?? 0;
            NuruxploreVersion::create([
                'project_id' => $project->id,
                'user_id' => $project->user_id,
                'version_number' => $v + 1,
                'snapshot' => ['content' => $document, 'word_count' => str_word_count(strip_tags($document))],
                'changes_description' => 'Initial ' . $documentType . ' generation',
                'change_type' => 'ai_generation',
            ]);
        }
        
        $words = str_word_count(strip_tags($document));
        $lastStep = count($steps) - 1;
        $steps[$lastStep]['status'] = 'completed';
        $steps[$lastStep]['message'] = "✅ {$documentType} ready ({$words} words)";

        return $steps;
    }

    /**
     * Generate Research Proposal (1,500-2,500 words)
     */
    protected function generateProposal(string $title, NuruxploreProject $project, ?string $uploadedContent): string
    {
        $referenceText = $uploadedContent ? "\n\nREFERENCE DOCUMENT (use for context and data):\n{$uploadedContent}\n" : '';
        
        $systemPrompt = <<<EOT
You are an academic writer. Generate a COMPLETE research proposal in Markdown format.

REQUIRED SECTIONS:
## Title
The research proposal title.

## Abstract
200-300 words summarizing the proposed research.

## Introduction
300-500 words with background, problem statement, research gap.

## Research Objectives
Clear, numbered objectives (3-5 objectives).

## Research Questions
Specific research questions (3-5 questions).

## Literature Review
400-600 words with theoretical framework and key studies.

## Methodology
300-500 words with research design, population, sampling, data collection, analysis.

## Expected Outcomes
200-300 words on anticipated findings and contributions.

## Timeline
Brief timeline or work plan.

## References
List all cited works in {$project->citation_style} format.

TOTAL: 1500-2500 words. Use [Author, Year] citations.
EOT;

        $userPrompt = "Write a research proposal titled: {$title}{$referenceText}\n\nTarget 1500-2500 words. Write ALL sections completely.";
        
        $result = $this->groq->callGroqAPI($systemPrompt, $userPrompt, 8000);
        return $result['content'] ?? '';
    }

    /**
     * Generate Full Thesis — Progressive 3-part generation with context
     */
    protected function generateThesis(string $title, NuruxploreProject $project, ?string $uploadedContent): string
    {
        $referenceText = $uploadedContent ? "\n\nREFERENCE DOCUMENT (use for context and data):\n{$uploadedContent}\n" : '';
        
        // Part 1: Abstract + Introduction
        Log::info('Thesis Part 1 - Starting');
        $part1 = $this->groq->callGroqAPI(
            "You are an academic thesis writer. Write the first part of a thesis.\n\nREQUIRED SECTIONS:\n## Abstract (300-500 words with background, aim, methods, findings, conclusions)\n## Introduction (800-1200 words with background, problem statement, objectives, research questions, significance)\n\nUse [Author, Year] citations. Citation style: {$project->citation_style}. Be thorough and detailed.",
            "Write the Abstract and Introduction for a thesis titled: {$title}{$referenceText}\n\nWrite thorough, detailed content. Target 1500+ words for these two sections combined.",
            8000
        );
        
        $content = ($part1['content'] ?? '');
        Log::info('Thesis Part 1 - Done', ['words' => str_word_count(strip_tags($content))]);
        
        // Part 2: Literature Review + Methodology (with context from Part 1)
        Log::info('Thesis Part 2 - Starting');
        $part2 = $this->groq->callGroqAPI(
            "You are an academic thesis writer. Continue writing a thesis. The previous sections are:\n\n{$content}\n\nNow write:\n## Literature Review (1000-1500 words with theoretical framework, empirical review, research gaps, conceptual framework)\n## Methodology (600-900 words with design, population, sampling, data collection, analysis, ethics)\n\nUse [Author, Year] citations. Citation style: {$project->citation_style}. Be thorough and detailed.",
            "Continue the thesis titled: {$title}{$referenceText}\n\nWrite thorough, detailed content for Literature Review and Methodology. Target 2000+ words combined.",
            8000
        );
        
        $content .= "\n\n" . ($part2['content'] ?? '');
        Log::info('Thesis Part 2 - Done', ['words' => str_word_count(strip_tags($content))]);
        
        // Part 3: Results + Discussion + Conclusion + References (with context from Parts 1 & 2)
        Log::info('Thesis Part 3 - Starting');
        $part3 = $this->groq->callGroqAPI(
            "You are an academic thesis writer. Complete the final sections of a thesis. The previous sections are:\n\n{$content}\n\nNow write:\n## Results (500-800 words with findings and analysis)\n## Discussion (600-900 words with interpretation, comparison, implications, limitations)\n## Conclusion (400-600 words with summary, recommendations, future research)\n## References (list ALL cited works in {$project->citation_style} format)\n\nUse [Author, Year] citations. Be thorough and detailed.",
            "Complete the thesis titled: {$title}{$referenceText}\n\nWrite thorough, detailed content for the final sections. Target 2500+ words combined.",
            8000
        );
        
        $content .= "\n\n" . ($part3['content'] ?? '');
        $totalWords = str_word_count(strip_tags($content));
        Log::info('Thesis Part 3 - Done', ['total_words' => $totalWords]);
        
        return $content;
    }

    /**
     * Extract text from uploaded files
     */
    protected function extractUploadedContent(NuruxploreProject $project): ?string
    {
        $sources = $project->sources()
            ->whereNotNull('file_path')
            ->whereNotNull('extracted_text')
            ->get();
        
        if ($sources->isEmpty()) {
            return null;
        }
        
        $content = '';
        foreach ($sources as $source) {
            if (!empty($source->extracted_text)) {
                $text = $source->extracted_text;
                // Limit to ~3000 words to leave room for AI output
                $words = explode(' ', $text);
                if (count($words) > 3000) {
                    $text = implode(' ', array_slice($words, 0, 3000)) . '... [truncated]';
                }
                $content .= "--- Document: {$source->title} ---\n\n";
                $content .= $text . "\n\n";
            }
        }
        
        return $content ?: null;
    }

    /**
     * Smart chat with intent detection
     * For chat-type projects, ALWAYS treat as general chat (no document editing)
     */
    public function smartChat(NuruxploreProject $project, string $userMessage): array
    {
        $currentDocument = $project->content ?? '';
        $msg = strtolower(trim($userMessage));
        
        // If this is a chat-type project, ALWAYS use general chat
        if ($project->type === 'chat') {
            return $this->handleGeneralChat($project, $userMessage);
        }
        
        // For thesis/proposal projects, use intent detection
        $isChat = false;
        $isEdit = false;
        
        // LEVEL 1: Question starters = ALWAYS chat
        $questionStarters = [
            'what ', 'how ', 'why ', 'when ', 'where ', 'who ',
            'which ', 'whose ', 'can you ', 'could you ', 'would you ',
            'should i ', 'is it ', 'are there ', 'do you ', 'does the ',
            'tell me ', 'explain ', 'define ',
        ];
        
        foreach ($questionStarters as $starter) {
            if (str_starts_with($msg, $starter)) { $isChat = true; $isEdit = false; break; }
        }
        
        if (str_contains($msg, '?')) { $isChat = true; $isEdit = false; }
        
        // LEVEL 2: Strong chat patterns
        if (!$isEdit) {
            $strongChatPatterns = [
                'what is', 'what are', 'what does', 'what do',
                'how to', 'how do', 'how does', 'how can', 'how should',
                'how would', 'how is', 'how are', 'how much', 'how many',
                'why is', 'why are', 'why do', 'why does',
                'explain the', 'explain how', 'explain what', 'explain why',
                'explain difference', 'can you explain', 'could you explain',
                'help me understand', 'tell me about', 'tell me more',
                'tell me how', 'tell me why', 'difference between',
                'best practice', 'best way', 'best method', 'best approach',
                'recommend', 'suggestion', 'advice', 'should i',
                'what would you', 'what should i', 'how should i',
                'what do you think', 'your opinion',
                'define', 'definition of', 'meaning of',
                'compare', 'contrast', 'advantages of', 'disadvantages of',
                'examples of', 'types of', 'kinds of',
                'can you tell', 'can you help', 'can you clarify',
                'could you tell', 'could you help', 'could you clarify',
                'i want to know', 'i need to know',
                'i want to understand', 'i need to understand',
                'what theory', 'which theory', 'what methodology',
                'what method', 'which method', 'what approach',
                'sample size', 'sampling method',
                'apa format', 'citation style', 'how to cite',
                'how to write a', 'how to structure a',
            ];
            foreach ($strongChatPatterns as $p) { if (str_contains($msg, $p)) { $isChat = true; break; } }
        }
        
        // LEVEL 3: Section + Action = Edit
        $sectionNames = ['abstract', 'introduction', 'literature review', 'lit review', 'methodology', 'methods', 'results', 'findings', 'discussion', 'conclusion', 'references', 'chapter', 'section', 'background'];
        $actionWords = ['add', 'remove', 'delete', 'modify', 'change', 'update', 'rewrite', 'revise', 'expand', 'shorten', 'replace', 'insert', 'fix', 'correct', 'edit', 'append', 'include', 'incorporate', 'enhance', 'improve', 'rephrase', 'reword', 'restructure', 'extend', 'reduce', 'trim', 'elaborate', 'clarify', 'simplify', 'strengthen', 'adjust', 'refine', 'polish', 'tighten', 'broaden', 'narrow', 'deepen', 'draft', 'write', 'generate', 'create', 'compose', 'make the', 'make this', 'make it'];
        
        $mentionsSection = false; foreach ($sectionNames as $s) { if (str_contains($msg, $s)) { $mentionsSection = true; break; } }
        $hasAction = false; foreach ($actionWords as $a) { if (str_contains($msg, $a)) { $hasAction = true; break; } }
        
        if ($mentionsSection && $hasAction && !$isChat) { $isEdit = true; }
        
        // LEVEL 4: Standalone edit patterns
        if (!$isChat && !$isEdit) {
            $editPatterns = ['add a table', 'add table', 'add a paragraph', 'add paragraph', 'add more detail', 'add more information', 'add content', 'add a section', 'add section', 'new section', 'new chapter', 'remove the section', 'delete the section', 'proofread', 'copy edit', 'copyedit', 'tone down', 'tone up', 'more academic', 'more formal', 'more concise', 'more detailed', 'more specific', 'less wordy', 'more professional', 'better flow', 'better structure', 'clearer', 'more clear', 'reformat', 'move the', 'reorder'];
            foreach ($editPatterns as $p) { if (str_contains($msg, $p)) { $isEdit = true; break; } }
        }
        
        // LEVEL 5: Empty document = generate
        if (empty($currentDocument)) { $isEdit = true; $isChat = false; }
        
        // LEVEL 6: AI fallback
        if (!$isChat && !$isEdit) {
            $intentResult = $this->groq->callGroqAPI(
                "Message: \"{$userMessage}\"\nDoes this MODIFY/EDIT a thesis, or is it a general QUESTION? Reply ONE word: 'edit' or 'chat'.", "", 20
            );
            $aiIntent = strtolower(trim($intentResult['content'] ?? 'chat'));
            $isEdit = str_contains($aiIntent, 'edit');
            $isChat = !$isEdit;
        }
        
        // EXECUTE
        if ($isChat && !$isEdit) {
            return $this->handleChat($project, $userMessage);
        }
        
        return $this->handleEdit($project, $userMessage, $currentDocument);
    }

    /**
     * Handle GENERAL CHAT (for chat-type projects)
     */
    protected function handleGeneralChat(NuruxploreProject $project, string $userMessage): array
    {
        $systemPrompt = "You are NuruXplore AI, a helpful academic assistant. ";
        $systemPrompt .= "You can discuss ANY academic topic — research, writing, studying, concepts, theories. ";
        $systemPrompt .= "Be helpful, informative, and encouraging. ";
        $systemPrompt .= "Use **bold** for emphasis. Use numbered lists when helpful. ";
        $systemPrompt .= "You are NOT editing a document — you're having a conversation.";
        
        $answer = $this->groq->callGroqAPI(
            $systemPrompt,
            "User: {$userMessage}\n\nProvide a helpful, thorough response.",
            1000
        );
        
        return ['action' => 'chat', 'message' => $answer['content'] ?? 'Let me help with that.'];
    }

    /**
     * Handle chat/question response (for thesis/proposal projects)
     */
    protected function handleChat(NuruxploreProject $project, string $userMessage): array
    {
        $systemPrompt = "You are NuruXplore AI, an expert academic advisor. ";
        $systemPrompt .= "The user is working on a document titled '{$project->title}'. ";
        $systemPrompt .= "Provide a helpful, concise response. Use **bold** for emphasis. ";
        $systemPrompt .= "Use numbered lists for steps. Keep paragraphs short. Be encouraging.";
        
        $answer = $this->groq->callGroqAPI(
            $systemPrompt,
            "User question: {$userMessage}\n\nProvide a helpful response.",
            600
        );
        
        return ['action' => 'chat', 'message' => $answer['content'] ?? 'Let me help with that.'];
    }

    /**
     * Handle document edit
     */
    protected function handleEdit(NuruxploreProject $project, string $userMessage, string $currentDocument): array
    {
        $recentMessages = $project->messages()
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get()
            ->reverse()
            ->map(fn($m) => "{$m->role}: {$m->content}")
            ->implode("\n");
        
        $systemPrompt = "You are an academic editor. Revise the COMPLETE document based on the user's instruction. ";
        $systemPrompt .= "Return the ENTIRE revised document in Markdown with ## section headings.\n\n";
        $systemPrompt .= "CRITICAL RULES:\n";
        $systemPrompt .= "1. Keep ALL unchanged sections EXACTLY as they are — do NOT summarize or shorten them\n";
        $systemPrompt .= "2. Only modify what the user explicitly asked for\n";
        $systemPrompt .= "3. Maintain the SAME level of detail and word count for unchanged sections\n";
        $systemPrompt .= "4. When adding content, be THOROUGH and SUBSTANTIVE\n";
        $systemPrompt .= "5. Use inline citations [Author, Year]\n";
        $systemPrompt .= "6. Maintain {$project->citation_style} format\n";
        $systemPrompt .= "7. Do NOT add a References section to every chapter — only at the end";
        
        $userPrompt = "CONVERSATION HISTORY:\n{$recentMessages}\n\n";
        $userPrompt .= "CURRENT DOCUMENT:\n\n{$currentDocument}\n\n";
        $userPrompt .= "USER INSTRUCTION: {$userMessage}\n\n";
        $userPrompt .= "Return the COMPLETE revised document. Keep unchanged sections exactly as-is with their full detail.";
        
        $result = $this->groq->callGroqAPI($systemPrompt, $userPrompt, 12000);
        
        $newDocument = $result['content'] ?? $currentDocument;
        
        if (!empty($newDocument) && trim($newDocument) !== trim($currentDocument)) {
            \App\Models\NuruxploreProject::where('id', $project->id)->update([
                'content' => $newDocument,
                'word_count' => str_word_count(strip_tags($newDocument)),
                'last_edited_at' => now(),
            ]);
            
            $v = NuruxploreVersion::where('project_id', $project->id)->max('version_number') ?? 0;
            NuruxploreVersion::create([
                'project_id' => $project->id,
                'user_id' => $project->user_id,
                'version_number' => $v + 1,
                'snapshot' => ['content' => $newDocument, 'word_count' => str_word_count(strip_tags($newDocument))],
                'changes_description' => $userMessage,
                'change_type' => 'ai_generation',
            ]);
        }
        
        return [
            'action' => 'edit',
            'message' => 'Document updated (' . str_word_count(strip_tags($newDocument)) . ' words)',
        ];
    }
}