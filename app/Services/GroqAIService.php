<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqAIService
{
    protected string $apiKey;
    protected string $apiUrl;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.groq.api_key');
        $this->apiUrl = config('services.groq.api_url');
        $this->model = config('services.groq.model');
    }

    public function generateThesisOutline(string $topic, string $type = 'thesis'): array
    {
        $systemPrompt = $this->getSystemPrompt();
        
        $userPrompt = "Generate a detailed academic {$type} outline for the topic: {$topic}. ";
        $userPrompt .= "Include all major chapters (Abstract, Introduction, Literature Review, Methodology, Results, Discussion, Conclusion). ";
        $userPrompt .= "For each chapter, provide 3-5 subsections. Format as JSON with chapter titles and subsections array.";

        return $this->callGroqAPI($systemPrompt, $userPrompt);
    }

    public function generateSection(string $sectionTitle, string $context, string $citationStyle = 'APA7'): array
    {
        $systemPrompt = $this->getSystemPrompt();
        
        $userPrompt = "Write the '{$sectionTitle}' section for the following academic work.\n\n";
        $userPrompt .= "Context: {$context}\n\n";
        $userPrompt .= "Requirements:\n";
        $userPrompt .= "- Write 500-1000 words\n";
        $userPrompt .= "- Use {$citationStyle} citation style\n";
        $userPrompt .= "- Include relevant academic references\n";
        $userPrompt .= "- Maintain formal academic tone\n";
        $userPrompt .= "- Use Markdown formatting";

        return $this->callGroqAPI($systemPrompt, $userPrompt, 2000);
    }

    public function reviseContent(string $content, string $instruction): array
    {
        $systemPrompt = "You are an academic writing expert. Revise academic content based on instructions while maintaining scholarly tone and accuracy.";

        $userPrompt = "Original Content:\n{$content}\n\n";
        $userPrompt .= "Revision Instructions: {$instruction}\n\n";
        $userPrompt .= "Please provide the revised version with changes highlighted or explained.";

        return $this->callGroqAPI($systemPrompt, $userPrompt, 2000);
    }

    public function chatResponse(array $messages, string $projectContext = ''): array
    {
        $systemPrompt = $this->getSystemPrompt() . "\n\nProject Context: {$projectContext}";
        
        $formattedMessages = array_map(function ($msg) {
            return [
                'role' => $msg['role'],
                'content' => $msg['content']
            ];
        }, $messages);

        // Add system message at the beginning
        array_unshift($formattedMessages, [
            'role' => 'system',
            'content' => $systemPrompt
        ]);

        return $this->callGroqAPI($systemPrompt, end($formattedMessages)['content'], 2000, $formattedMessages);
    }

    protected function callGroqAPI(string $systemPrompt, string $userPrompt, int $maxTokens = 1500, array $messages = null): array
    {
        try {
            if (!$messages) {
                $messages = [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt]
                ];
            }

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->apiUrl . '/chat/completions', [
                    'model' => $this->model,
                    'messages' => $messages,
                    'max_tokens' => $maxTokens,
                    'temperature' => 0.7,
                    'top_p' => 1,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'content' => $data['choices'][0]['message']['content'],
                    'tokens_used' => $data['usage']['total_tokens'] ?? 0,
                    'model' => $this->model,
                ];
            }

            Log::error('Groq API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'AI service temporarily unavailable. Please try again.',
            ];
        } catch (\Exception $e) {
            Log::error('Groq API Exception', ['message' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => 'Unable to connect to AI service.',
            ];
        }
    }

    protected function getSystemPrompt(): string
    {
        return <<<EOT
You are NuruXplore AI, an expert academic writing assistant. Your role is to help students and researchers write high-quality academic papers, theses, and dissertations.

Guidelines:
1. Always maintain formal academic tone and style
2. Use proper citation formats as specified
3. Structure content logically with clear transitions
4. Include relevant examples and evidence
5. Acknowledge limitations and counterarguments
6. Follow standard academic conventions
7. Use Markdown formatting for structure
8. Be thorough but concise

When generating content:
- Start with clear topic sentences
- Support claims with evidence
- Use discipline-appropriate terminology
- Include proper in-text citations
- End sections with transitional statements

Remember: You're assisting in writing, not replacing the researcher's critical thinking.
EOT;
    }
}