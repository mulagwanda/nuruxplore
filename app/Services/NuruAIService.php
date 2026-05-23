<?php

namespace App\Services;

use App\Models\NuruxploreProject;
use App\Models\NuruxploreVersion;

class NuruAIService
{
    protected GroqAIService $groq;

    public function __construct(GroqAIService $groq)
    {
        $this->groq = $groq;
    }

    /**
     * Generate complete thesis with progress steps
     */
    public function generateCompleteThesis(NuruxploreProject $project, string $userTopic): array
    {
        $steps = [];
        
        // Step 1: Smart Title
        $steps[] = ['step' => 'title', 'status' => 'processing', 'message' => '🎯 Crafting title...'];
        $titleResult = $this->groq->callGroqAPI(
            "Create a short academic thesis title. Return ONLY the title.",
            "Create thesis title for: {$userTopic}",
            200
        );
        $smartTitle = trim(str_replace(['"', "'", '**', 'Title:'], '', $titleResult['content'] ?? $userTopic));
        $project->update(['title' => $smartTitle]);
        $steps[0]['status'] = 'completed';
        $steps[0]['message'] = '✅ ' . $smartTitle;

        // Step 2: Generate complete document
        $steps[] = ['step' => 'document', 'status' => 'processing', 'message' => '📝 Writing thesis...'];
        
        $systemPrompt = "Generate a complete academic thesis in Markdown. ";
        $systemPrompt .= "Use ## for section headings. ";
        $systemPrompt .= "Sections: Abstract, Introduction, Literature Review, Methodology, Results, Discussion, Conclusion, References. ";
        $systemPrompt .= "Include inline citations [Author, Year]. ";
        $systemPrompt .= "Write 2000-3500 words. Citation style: {$project->citation_style}.";
        
        $result = $this->groq->callGroqAPI(
            $systemPrompt,
            "Write a complete {$project->type} titled: {$smartTitle}",
            4000
        );
        
        $document = $result['content'] ?? '';
        
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
                'changes_description' => 'Initial thesis generation',
                'change_type' => 'ai_generation',
            ]);
        }
        
        $words = str_word_count(strip_tags($document));
        $steps[1]['status'] = 'completed';
        $steps[1]['message'] = "✅ Thesis ready ({$words} words)";

        return $steps;
    }

    /**
     * Smart chat with intent detection
     */
    public function smartChat(NuruxploreProject $project, string $userMessage): array
    {
        $currentDocument = $project->content ?? '';
        $msg = strtolower(trim($userMessage));
        
        // ==========================================
        // LEVEL 1: ULTRA-STRONG CHAT SIGNALS
        // ==========================================
        
        $isChat = false;
        $isEdit = false;
        
        // Message starts with question word = ALWAYS chat
        $questionStarters = [
            'what ', 'how ', 'why ', 'when ', 'where ', 'who ',
            'which ', 'whose ', 'can you ', 'could you ', 'would you ',
            'should i ', 'is it ', 'are there ', 'do you ', 'does the ',
            'tell me ', 'explain ', 'define ',
        ];
        
        foreach ($questionStarters as $starter) {
            if (str_starts_with($msg, $starter)) {
                $isChat = true;
                $isEdit = false;
                break;
            }
        }
        
        // Question mark = always chat
        if (str_contains($msg, '?')) {
            $isChat = true;
            $isEdit = false;
        }
        
        // ==========================================
        // LEVEL 2: STRONG CHAT PATTERNS
        // ==========================================
        
        if (!$isEdit) {
            $strongChatPatterns = [
                'what is', 'what are', 'what does', 'what do',
                'how to', 'how do', 'how does', 'how can', 'how should',
                'how would', 'how is', 'how are', 'how much', 'how many',
                'why is', 'why are', 'why do', 'why does',
                'explain the', 'explain how', 'explain what', 'explain why',
                'explain difference', 'can you explain', 'could you explain',
                'help me understand', 'tell me about', 'tell me more',
                'tell me how', 'tell me why',
                'difference between', 'what is the difference',
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
            
            foreach ($strongChatPatterns as $pattern) {
                if (str_contains($msg, $pattern)) {
                    $isChat = true;
                    break;
                }
            }
        }
        
        // ==========================================
        // LEVEL 3: SECTION + ACTION = EDIT
        // ==========================================
        
        $sectionNames = [
            'abstract', 'introduction', 'literature review', 'lit review',
            'methodology', 'methods', 'results', 'findings',
            'discussion', 'conclusion', 'references', 'chapter',
            'section', 'background',
        ];
        
        $actionWords = [
            'add', 'remove', 'delete', 'modify', 'change', 'update',
            'rewrite', 'revise', 'expand', 'shorten', 'replace',
            'insert', 'fix', 'correct', 'edit', 'append', 'include',
            'incorporate', 'enhance', 'improve', 'rephrase', 'reword',
            'restructure', 'extend', 'reduce', 'trim', 'elaborate',
            'clarify', 'simplify', 'strengthen', 'adjust', 'refine',
            'polish', 'tighten', 'broaden', 'narrow', 'deepen',
            'draft', 'write', 'generate', 'create', 'compose',
            'make the', 'make this', 'make it',
        ];
        
        $mentionsSection = false;
        foreach ($sectionNames as $section) {
            if (str_contains($msg, $section)) {
                $mentionsSection = true;
                break;
            }
        }
        
        $hasAction = false;
        foreach ($actionWords as $action) {
            if (str_contains($msg, $action)) {
                $hasAction = true;
                break;
            }
        }
        
        // Section + action + no chat signal = edit
        if ($mentionsSection && $hasAction && !$isChat) {
            $isEdit = true;
        }
        
        // ==========================================
        // LEVEL 4: STANDALONE EDIT SIGNALS
        // ==========================================
        
        if (!$isChat && !$isEdit) {
            $editPatterns = [
                'add a table', 'add table', 'add a paragraph', 'add paragraph',
                'add more detail', 'add more information', 'add content',
                'add a section', 'add section', 'new section', 'new chapter',
                'remove the section', 'delete the section',
                'proofread', 'copy edit', 'copyedit',
                'tone down', 'tone up', 'more academic', 'more formal',
                'more concise', 'more detailed', 'more specific',
                'less wordy', 'more professional', 'better flow',
                'better structure', 'clearer', 'more clear',
                'reformat', 'move the', 'reorder',
            ];
            
            foreach ($editPatterns as $pattern) {
                if (str_contains($msg, $pattern)) {
                    $isEdit = true;
                    break;
                }
            }
        }
        
        // ==========================================
        // LEVEL 5: EMPTY DOCUMENT = GENERATE
        // ==========================================
        
        if (empty($currentDocument)) {
            $isEdit = true;
            $isChat = false;
        }
        
        // ==========================================
        // LEVEL 6: AI FALLBACK
        // ==========================================
        
        if (!$isChat && !$isEdit) {
            $intentResult = $this->groq->callGroqAPI(
                "Message: \"{$userMessage}\"\nDoes this MODIFY/EDIT a thesis, or is it a general QUESTION? Reply ONE word: 'edit' or 'chat'.",
                "",
                20
            );
            $aiIntent = strtolower(trim($intentResult['content'] ?? 'chat'));
            $isEdit = str_contains($aiIntent, 'edit');
            $isChat = !$isEdit;
        }
        
        // ==========================================
        // EXECUTE
        // ==========================================
        
        if ($isChat && !$isEdit) {
            return $this->handleChat($project, $userMessage);
        }
        
        return $this->handleEdit($project, $userMessage, $currentDocument);
    }

    /**
     * Handle chat/question response
     */
    protected function handleChat(NuruxploreProject $project, string $userMessage): array
    {
        $systemPrompt = "You are NuruXplore AI, an expert academic advisor. ";
        $systemPrompt .= "The user is working on a thesis titled '{$project->title}'. ";
        $systemPrompt .= "Provide a helpful, concise response. Use **bold** for emphasis. ";
        $systemPrompt .= "Use numbered lists for steps. Keep paragraphs short. Be encouraging.";
        
        $answer = $this->groq->callGroqAPI(
            $systemPrompt,
            "User question: {$userMessage}\n\nProvide a helpful response.",
            600
        );
        
        return [
            'action' => 'chat',
            'message' => $answer['content'] ?? 'Let me help with that.',
        ];
    }

    /**
     * Handle document edit
     */
    protected function handleEdit(NuruxploreProject $project, string $userMessage, string $currentDocument): array
    {
        // Get recent chat history for context
        $recentMessages = $project->messages()
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get()
            ->reverse()
            ->map(fn($m) => "{$m->role}: {$m->content}")
            ->implode("\n");
        
        $systemPrompt = "You are an academic editor. Revise the COMPLETE thesis based on the user's instruction. ";
        $systemPrompt .= "Return the ENTIRE revised thesis in Markdown with ## section headings. ";
        $systemPrompt .= "CRITICAL: Keep ALL unchanged sections EXACTLY as they are. Only modify what the user asked for. ";
        $systemPrompt .= "Sections: Abstract, Introduction, Literature Review, Methodology, Results, Discussion, Conclusion, References. ";
        $systemPrompt .= "Use inline citations [Author, Year]. Maintain {$project->citation_style} format. ";
        $systemPrompt .= "DO NOT add a References section to every chapter - only at the end.";
        
        $userPrompt = "CONVERSATION HISTORY:\n{$recentMessages}\n\n";
        $userPrompt .= "CURRENT THESIS:\n\n{$currentDocument}\n\n";
        $userPrompt .= "USER INSTRUCTION: {$userMessage}\n\n";
        $userPrompt .= "Return the COMPLETE revised thesis. Keep unchanged sections exactly as-is.";
        
        $result = $this->groq->callGroqAPI($systemPrompt, $userPrompt, 4000);
        
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
            'message' => 'Thesis updated (' . str_word_count(strip_tags($newDocument)) . ' words)',
        ];
    }
}