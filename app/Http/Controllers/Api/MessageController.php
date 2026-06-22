<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NuruxploreProject;
use App\Services\NuruAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(protected NuruAIService $nuruAI)
    {
    }

    public function index(NuruxploreProject $project): JsonResponse
    {
        if ($project->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $messages = $project->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn ($message) => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'metadata' => $message->metadata,
                'action_type' => $message->action_type,
                'credits_used' => $message->credits_used,
                'created_at' => $message->created_at?->toISOString(),
            ]);

        return response()->json(['messages' => $messages]);
    }

    public function store(Request $request, NuruxploreProject $project): JsonResponse
    {
        if ($project->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:12000',
            'action_type' => 'nullable|string|max:50',
        ]);

        $user = $request->user();
        $cost = $project->type === 'chat' ? 1 : 2;

        if ($user->credits_balance < $cost) {
            return response()->json([
                'message' => "Insufficient credits. You need {$cost} credits.",
                'credits_balance' => $user->credits_balance,
            ], 402);
        }

        $userMessage = $project->messages()->create([
            'user_id' => $user->id,
            'role' => 'user',
            'content' => $validated['message'],
            'action_type' => $validated['action_type'] ?? 'chat',
            'credits_used' => 0,
        ]);

        // Sends the latest 10-20 messages to AI, and for workspace projects can apply targeted document edits.
        $result = $this->nuruAI->smartChat($project->fresh(), $validated['message'], 20);

        $assistantMessage = $project->messages()->create([
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => $result['message'] ?? 'Done.',
            'action_type' => $result['action'] ?? 'chat',
            'credits_used' => $cost,
            'metadata' => [
                'model' => $result['model'] ?? null,
                'tokens_used' => $result['tokens_used'] ?? 0,
                'history_messages_sent' => 20,
                'document_updated' => $result['document_updated'] ?? false,
                'target_section' => $result['target_section'] ?? null,
                'edit_type' => $result['edit_type'] ?? null,
            ],
        ]);

        $user->deductCredits($cost, 'AI workspace: ' . ($result['action'] ?? 'chat'), $project->id);
        $project->update(['last_edited_at' => now()]);

        $freshProject = $project->fresh();

        return response()->json([
            'success' => true,
            'action' => $result['action'] ?? 'chat',
            'message' => $result['message'] ?? 'Done.',
            'document_updated' => $result['document_updated'] ?? false,
            'target_section' => $result['target_section'] ?? null,
            'edit_type' => $result['edit_type'] ?? null,
            'project' => [
                'id' => $freshProject->id,
                'uuid' => $freshProject->uuid,
                'title' => $freshProject->title,
                'type' => $freshProject->type,
                'status' => $freshProject->status,
                'word_count' => $freshProject->word_count,
                'content' => $freshProject->content,
                'last_edited_at' => $freshProject->last_edited_at?->diffForHumans(),
            ],
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
            'credits_remaining' => $user->fresh()->credits_balance,
        ]);
    }
}
