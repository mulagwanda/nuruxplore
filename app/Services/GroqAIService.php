<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqAIService
{
    protected string $apiKey;
    protected string $apiUrl;
    protected string $model;
    protected string $fastModel;
    protected string $writingModel;

    public function __construct()
    {
        $this->apiKey = (string) config('services.groq.api_key');
        $this->apiUrl = rtrim((string) config('services.groq.api_url', 'https://api.groq.com/openai/v1'), '/');
        $this->model = (string) config('services.groq.model', 'llama-3.3-70b-versatile');
        $this->fastModel = (string) config('services.groq.fast_model', 'llama-3.1-8b-instant');
        $this->writingModel = (string) config('services.groq.writing_model', $this->model);
    }

    public function fastModel(): string
    {
        return $this->fastModel;
    }

    public function writingModel(): string
    {
        return $this->writingModel;
    }

    public function callGroqAPI(
        string $systemPrompt,
        string $userPrompt,
        int $maxTokens = 2000,
        ?array $messages = null,
        ?string $model = null,
        float $temperature = 0.3
    ): array {
        try {
            if (blank($this->apiKey)) {
                return ['success' => false, 'error' => 'Groq API key is not configured.'];
            }

            $payloadMessages = $messages ?: [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ];

            $response = Http::timeout(120)
                ->retry(2, 700)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->apiUrl . '/chat/completions', [
                    'model' => $model ?: $this->model,
                    'messages' => $payloadMessages,
                    'max_completion_tokens' => $maxTokens,
                    'temperature' => $temperature,
                    'top_p' => 1,
                ]);

            if (!$response->successful()) {
                Log::error('Groq API Error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => 'AI service temporarily unavailable. Please try again.',
                    'status' => $response->status(),
                ];
            }

            $data = $response->json();

            return [
                'success' => true,
                'content' => Arr::get($data, 'choices.0.message.content', ''),
                'tokens_used' => Arr::get($data, 'usage.total_tokens', 0),
                'input_tokens' => Arr::get($data, 'usage.prompt_tokens', 0),
                'output_tokens' => Arr::get($data, 'usage.completion_tokens', 0),
                'model' => $model ?: $this->model,
                'raw' => $data,
            ];
        } catch (\Throwable $e) {
            Log::error('Groq API Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Unable to connect to AI service.',
            ];
        }
    }

    public function jsonCall(string $systemPrompt, string $userPrompt, int $maxTokens = 2000, ?string $model = null): array
    {
        $result = $this->callGroqAPI($systemPrompt, $userPrompt, $maxTokens, null, $model, 0.1);
        if (!$result['success']) {
            return $result;
        }

        $json = $this->extractJson($result['content'] ?? '');
        if ($json === null) {
            return [
                'success' => false,
                'error' => 'AI returned invalid JSON.',
                'content' => $result['content'] ?? '',
                'tokens_used' => $result['tokens_used'] ?? 0,
                'model' => $result['model'] ?? $model,
            ];
        }

        return [
            'success' => true,
            'json' => $json,
            'content' => $result['content'] ?? '',
            'tokens_used' => $result['tokens_used'] ?? 0,
            'model' => $result['model'] ?? $model,
        ];
    }

    public function extractJson(string $content): ?array
    {
        $clean = trim($content);
        $clean = preg_replace('/^```(?:json)?\s*/i', '', $clean);
        $clean = preg_replace('/\s*```$/', '', $clean);

        $decoded = json_decode($clean, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        if (preg_match('/\[.*\]/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    public function generateThesisOutline(string $topic, string $type = 'thesis'): array
    {
        $systemPrompt = 'You are an academic writing expert. Return ONLY valid JSON with key chapters. No markdown.';
        $userPrompt = <<<EOT
Generate a detailed academic {$type} outline for: {$topic}

Return exactly this JSON shape:
{
  "chapters": [
    {"title": "Chapter Title", "subsections": ["Subsection 1", "Subsection 2"]}
  ]
}

Use standard sections: Abstract, Introduction, Literature Review, Methodology, Results, Discussion, Conclusion.
Each chapter should have 3-5 subsections.
EOT;

        return $this->jsonCall($systemPrompt, $userPrompt, 1800, $this->fastModel);
    }

    public function generateSection(string $sectionTitle, string $context, string $citationStyle = 'APA7'): array
    {
        $systemPrompt = $this->getSystemPrompt();
        $userPrompt = <<<EOT
Write the '{$sectionTitle}' section for the following academic work.

CONTEXT:
{$context}

REQUIREMENTS:
- Write 500-1000 words unless context gives another target
- Use {$citationStyle} citation style
- Use formal academic tone
- Use Markdown formatting
- Do not invent project facts that conflict with context
EOT;

        return $this->callGroqAPI($systemPrompt, $userPrompt, 2500, null, $this->writingModel, 0.35);
    }

    public function reviseContent(string $content, string $instruction, string $context = ''): array
    {
        $systemPrompt = 'You are an academic editor. Revise only the provided section based on the instruction. Return the revised section only.';
        $userPrompt = <<<EOT
PROJECT CONTEXT:
{$context}

ORIGINAL SECTION:
{$content}

REVISION INSTRUCTION:
{$instruction}

Return only the revised section in Markdown. Do not include explanations.
EOT;

        return $this->callGroqAPI($systemPrompt, $userPrompt, 2500, null, $this->writingModel, 0.25);
    }

    public function chatResponse(array $messages, string $projectContext = ''): array
    {
        $systemPrompt = $this->getSystemPrompt() . "\n\nProject Context:\n" . $projectContext;
        $formattedMessages = array_map(fn($msg) => [
            'role' => $msg['role'],
            'content' => $msg['content'],
        ], $messages);

        array_unshift($formattedMessages, ['role' => 'system', 'content' => $systemPrompt]);

        return $this->callGroqAPI($systemPrompt, end($formattedMessages)['content'] ?? '', 1800, $formattedMessages, $this->fastModel, 0.4);
    }

    protected function getSystemPrompt(): string
    {
        return <<<EOT
You are NuruXplore AI, an expert academic writing assistant.

Rules:
1. Maintain formal academic tone.
2. Follow the approved project context exactly.
3. Never change objectives, study area, sample size, methodology, or research questions unless explicitly instructed.
4. Use Markdown headings where helpful.
5. Use citation placeholders only when exact sources are not provided.
6. Help the researcher draft and improve; do not claim final academic authority.
EOT;
    }
}
