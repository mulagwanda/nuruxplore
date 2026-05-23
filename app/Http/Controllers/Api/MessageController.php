<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NuruxploreProject;
use App\Services\GroqAIService;
use App\Services\NuruAIService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MessageController extends Controller
{
    protected GroqAIService $aiService;
    protected NuruAIService $nuruAI;

    public function __construct(GroqAIService $aiService, NuruAIService $nuruAI)
    {
        $this->aiService = $aiService;
        $this->nuruAI = $nuruAI;
    }

    public function index($projectUuid): JsonResponse
    {
        $project = NuruxploreProject::where('uuid', $projectUuid)->firstOrFail();
        
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
                'created_at' => $msg->created_at->format('H:i'),
            ]);

        return response()->json(['messages' => $messages]);
    }

    public function store(Request $request, $projectUuid): JsonResponse
    {
        $project = NuruxploreProject::where('uuid', $projectUuid)->firstOrFail();
        
        if ($project->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user = $request->user();
        $creditCost = 2;

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
            'content' => $request->input('message'),
        ]);

        // Process with AI
        $result = $this->nuruAI->smartChat($project, $request->input('message'));
        
        $user->deductCredits($creditCost, 'AI Chat', $project->id);

        // Save AI response
        $project->messages()->create([
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => $result['message'] ?? 'Done!',
            'credits_used' => $creditCost,
        ]);

        return response()->json([
            'user_message' => ['id' => $userMessage->id, 'role' => 'user', 'content' => $userMessage->content],
            'action' => $result['action'] ?? 'chat',
            'message' => $result['message'] ?? 'Done!',
            'credits_remaining' => $user->fresh()->credits_balance,
        ]);
    }
}