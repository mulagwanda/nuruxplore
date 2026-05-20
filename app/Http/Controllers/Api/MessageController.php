<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NuruxploreProject;
use App\Services\GroqAIService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MessageController extends Controller
{
    protected GroqAIService $aiService;

    public function __construct(GroqAIService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function index($projectId): JsonResponse
    {
        $project = NuruxploreProject::findOrFail($projectId);
        
        if ($project->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $messages = $project->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn($msg) => [
                'id' => $msg->id,
                'role' => $msg->role,
                'content' => $msg->content,
                'action_type' => $msg->action_type,
                'credits_used' => $msg->credits_used,
                'created_at' => $msg->created_at->format('H:i'),
                'created_at_human' => $msg->created_at->diffForHumans(),
            ]);

        return response()->json(['messages' => $messages]);
    }

    public function store(Request $request, $projectId): JsonResponse
    {
        $project = NuruxploreProject::findOrFail($projectId);
        
        if ($project->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'action_type' => 'nullable|in:chat,generate,revise,cite,check',
        ]);

        $user = $request->user();
        $creditCost = $this->getCreditCost($validated['action_type'] ?? 'chat');

        if ($user->credits_balance < $creditCost) {
            return response()->json([
                'message' => "Insufficient credits. Need {$creditCost} credits.",
                'credits_balance' => $user->credits_balance,
            ], 402);
        }

        // Save user message
        $userMessage = $project->messages()->create([
            'user_id' => $user->id,
            'role' => 'user',
            'content' => $validated['message'],
            'action_type' => $validated['action_type'] ?? 'chat',
        ]);

        // Get recent conversation context
        $recentMessages = $project->messages()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->reverse()
            ->map(fn($msg) => [
                'role' => $msg->role,
                'content' => $msg->content,
            ])
            ->toArray();

        // Build project context
        $context = "Project: {$project->title}\nType: {$project->type}\n";
        
        $sections = $project->sections()->whereNull('parent_id')->get();
        if ($sections->isNotEmpty()) {
            $context .= "Structure:\n";
            foreach ($sections as $section) {
                $context .= "- {$section->title}" . ($section->content ? " (has content)" : "") . "\n";
            }
        }

        // Get AI response
        $result = $this->aiService->chatResponse($recentMessages, $context);

        if (!$result['success']) {
            return response()->json(['message' => $result['error']], 500);
        }

        // Deduct credits
        $user->deductCredits($creditCost, 'AI Chat', $project->id);

        // Save AI response
        $aiMessage = $project->messages()->create([
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => $result['content'],
            'action_type' => $validated['action_type'] ?? 'chat',
            'credits_used' => $creditCost,
            'metadata' => [
                'model' => $result['model'] ?? 'llama-3.3-70b',
                'tokens_used' => $result['tokens_used'] ?? 0,
            ],
        ]);

        $project->update(['last_edited_at' => now()]);

        return response()->json([
            'user_message' => [
                'id' => $userMessage->id,
                'role' => 'user',
                'content' => $userMessage->content,
                'created_at' => $userMessage->created_at->format('H:i'),
            ],
            'ai_message' => [
                'id' => $aiMessage->id,
                'role' => 'assistant',
                'content' => $aiMessage->content,
                'created_at' => $aiMessage->created_at->format('H:i'),
            ],
            'credits_remaining' => $user->fresh()->credits_balance,
        ]);
    }

    protected function getCreditCost(string $actionType): int
    {
        return match($actionType) {
            'generate' => 5,
            'revise' => 2,
            'cite' => 1,
            'check' => 5,
            default => 1, // chat
        };
    }
}