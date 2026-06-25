<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateResearchDocumentJob;
use App\Models\NuruxploreProject;
use App\Models\NuruxploreVersion;
use App\Services\NuruAIService;
use App\Services\NuruCreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    public function __construct(
        protected NuruAIService $nuruAI,
        protected NuruCreditService $credits
    ) {
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
        $messageText = $validated['message'];

        $userMessage = $project->messages()->create([
            'user_id' => $user->id,
            'role' => 'user',
            'content' => $messageText,
            'action_type' => $validated['action_type'] ?? 'chat',
            'credits_used' => 0,
        ]);

        // General chat can now create structured proposal/thesis projects instead of dumping long documents into chat.
        if ($project->type === 'chat') {
            $researchRequest = $this->nuruAI->detectResearchProjectRequest($messageText);
            if ($researchRequest['should_create'] ?? false) {
                return $this->createResearchProjectFromGeneralChat($request, $project, $userMessage, $researchRequest);
            }
        }

        $operationCost = $this->operationCost($project, $messageText);
        if (!$this->credits->canAfford($user, $operationCost)) {
            return response()->json([
                'message' => "Insufficient credits. You need {$operationCost} credits.",
                'credits_balance' => $user->credits_balance,
                'required_credits' => $operationCost,
            ], 402);
        }

        $result = $this->nuruAI->smartChat($project->fresh(), $messageText, 20);

        $assistantMessage = $project->messages()->create([
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => $result['message'] ?? 'Done.',
            'action_type' => $result['action'] ?? 'chat',
            'credits_used' => $operationCost,
            'metadata' => [
                'model' => $result['model'] ?? null,
                'tokens_used' => $result['tokens_used'] ?? 0,
                'history_messages_sent' => 20,
                'document_updated' => $result['document_updated'] ?? false,
                'target_section' => $result['target_section'] ?? null,
                'edit_type' => $result['edit_type'] ?? null,
                'new_title' => $result['new_title'] ?? null,
            ],
        ]);

        $this->credits->charge($user, $operationCost, 'AI workspace: ' . ($result['action'] ?? 'chat'), $project->id);
        $project->update(['last_edited_at' => now()]);

        $freshProject = $project->fresh();

        return response()->json([
            'success' => true,
            'action' => $result['action'] ?? 'chat',
            'message' => $result['message'] ?? 'Done.',
            'document_updated' => $result['document_updated'] ?? false,
            'target_section' => $result['target_section'] ?? null,
            'edit_type' => $result['edit_type'] ?? null,
            'project' => $this->projectPayload($freshProject),
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
            'credits_remaining' => $user->fresh()->credits_balance,
        ]);
    }

    protected function createResearchProjectFromGeneralChat(Request $request, NuruxploreProject $chatProject, $userMessage, array $researchRequest): JsonResponse
    {
        $user = $request->user();
        $type = $researchRequest['type'] ?? 'proposal';
        $generationCost = $this->credits->cost('generate_document', $type);
        $chatCost = $this->credits->cost('general_chat');
        $totalCost = $generationCost + $chatCost;

        if (!$this->credits->canAfford($user, $totalCost)) {
            return response()->json([
                'message' => "Insufficient credits. You need {$totalCost} credits to create and generate this {$type}.",
                'credits_balance' => $user->credits_balance,
                'required_credits' => $totalCost,
            ], 402);
        }

        $jobUuid = (string) Str::uuid();
        $newProject = $user->projects()->create([
            'uuid' => (string) Str::uuid(),
            'title' => $researchRequest['title'],
            'title_ai_generated' => true,
            'type' => $type,
            'citation_style' => 'APA7',
            'description' => $researchRequest['prompt'],
            'original_prompt' => $researchRequest['prompt'],
            'research_profile_status' => 'missing',
            'status' => 'queued',
            'generation_status' => 'queued',
            'generation_progress' => 1,
            'generation_current_step' => 'Generation queued from General Chat',
            'generation_steps' => [[
                'step' => 'queued',
                'status' => 'processing',
                'message' => 'Generation queued from General Chat...',
            ]],
            'generation_job_uuid' => $jobUuid,
            'generation_started_at' => now(),
            'credits_reserved' => $generationCost,
            'generation_settings' => [
                'created_from_general_chat_project_uuid' => $chatProject->uuid,
                'source_message_id' => $userMessage->id,
                'original_prompt' => $researchRequest['prompt'],
                'title_tokens_used' => $researchRequest['title_tokens_used'] ?? 0,
            ],
            'last_edited_at' => now(),
        ]);

        NuruxploreVersion::create([
            'project_id' => $newProject->id,
            'user_id' => $user->id,
            'version_number' => 1,
            'snapshot' => ['content' => null, 'word_count' => 0, 'title' => $newProject->title],
            'changes_description' => 'Project created from General Chat request',
            'change_type' => 'ai_create_project',
        ]);

        $this->credits->charge($user, $totalCost, 'General Chat → queued ' . $type . ' generation', $newProject->id);

        GenerateResearchDocumentJob::dispatch($newProject->id, $user->id, $researchRequest['prompt'], $type, $generationCost, $jobUuid);

        $assistantText = "Great — I created a new {$type} project and started generation.\n\n**Title:** {$newProject->title}\n\nOpen the workspace to watch progress and edit it when ready.";

        $assistantMessage = $chatProject->messages()->create([
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => $assistantText,
            'action_type' => 'create_research_project',
            'credits_used' => $chatCost,
            'metadata' => [
                'created_project_uuid' => $newProject->uuid,
                'created_project_title' => $newProject->title,
                'generation_job_uuid' => $jobUuid,
                'generation_cost' => $generationCost,
            ],
        ]);

        return response()->json([
            'success' => true,
            'action' => 'create_research_project',
            'message' => $assistantText,
            'created_project' => $this->projectPayload($newProject->fresh()),
            'workspace_url' => '/workspace/' . $newProject->uuid,
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
            'credits_remaining' => $user->fresh()->credits_balance,
        ], 202);
    }

    protected function operationCost(NuruxploreProject $project, string $message): int
    {
        $msg = strtolower($message);
        if ($project->type === 'chat') {
            return $this->credits->cost('general_chat');
        }
        if (str_contains($msg, 'title')) {
            return $this->credits->cost('edit_title');
        }
        if (preg_match('/\b(reference|references|bibliography|apa7|apa 7)\b/', $msg)) {
            return $this->credits->cost(str_contains($msg, '20') || str_contains($msg, 'more') ? 'expand_references' : 'fix_references');
        }
        if (preg_match('/\b(table|chart|graph|figure)\b/', $msg)) {
            return $this->credits->cost('insert_table');
        }
        if (preg_match('/\b(plagiarism|turnitin|similarity|review whole|overall review)\b/', $msg)) {
            return $this->credits->cost('document_review');
        }
        if (preg_match('/\b(expand|humanize|professional|rewrite|revise|improve|shorten)\b/', $msg)) {
            return $this->credits->cost('rewrite_section');
        }
        return $this->credits->cost('workspace_chat');
    }

    protected function projectPayload(NuruxploreProject $project): array
    {
        return [
            'id' => $project->id,
            'uuid' => $project->uuid,
            'title' => $project->title,
            'type' => $project->type,
            'status' => $project->status,
            'generation_status' => $project->generation_status,
            'generation_progress' => $project->generation_progress ?? 0,
            'generation_current_step' => $project->generation_current_step,
            'generation_error' => $project->generation_error,
            'word_count' => $project->word_count,
            'content' => $project->content,
            'last_edited_at' => $project->last_edited_at?->diffForHumans(),
        ];
    }
}
