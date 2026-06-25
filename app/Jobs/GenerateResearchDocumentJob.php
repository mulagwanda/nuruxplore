<?php

namespace App\Jobs;

use App\Models\NuruxploreProject;
use App\Models\User;
use App\Services\NuruAIService;
use App\Services\NuruCreditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateResearchDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(
        public int $projectId,
        public int $userId,
        public string $topic,
        public string $type,
        public int $creditsCharged,
        public string $jobUuid
    ) {
        $this->onQueue('nuruxplore-generation');
    }

    public function handle(NuruAIService $nuruAI): void
    {
        $project = NuruxploreProject::findOrFail($this->projectId);

        $this->mark($project, 'building_profile', 5, 'Building research profile...');

        $steps = $nuruAI->generateCompleteThesis($project->fresh(), $this->topic, $this->type);
        $failed = collect($steps)->contains(fn ($step) => ($step['status'] ?? null) === 'failed');

        if ($failed) {
            $message = collect($steps)->firstWhere('status', 'failed')['message'] ?? 'Document generation failed.';
            throw new \RuntimeException($message);
        }

        $project = $project->fresh();
        $hasContent = trim((string) ($project->content ?? '')) !== '';
        if (!$hasContent) {
            throw new \RuntimeException('Generation completed but project content is empty.');
        }

        $project->update([
            'generation_status' => 'completed',
            'generation_progress' => 100,
            'generation_current_step' => 'Completed',
            'generation_steps' => $steps,
            'generation_error' => null,
            'generation_finished_at' => now(),
            'credits_reserved' => 0,
            'status' => 'ready_for_review',
            'last_edited_at' => now(),
        ]);

        $project->messages()->create([
            'user_id' => $project->user_id,
            'role' => 'assistant',
            'content' => 'Done. I generated the ' . $this->label($this->type) . ' and updated the document preview.',
            'action_type' => 'generation_completed',
            'credits_used' => 0,
            'metadata' => [
                'job_uuid' => $this->jobUuid,
                'generation_status' => 'completed',
            ],
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $project = NuruxploreProject::find($this->projectId);
        $user = User::find($this->userId);

        if ($project) {
            $project->update([
                'generation_status' => 'failed',
                'generation_progress' => 100,
                'generation_current_step' => 'Failed',
                'generation_error' => $exception->getMessage(),
                'generation_finished_at' => now(),
                'credits_reserved' => 0,
                'status' => 'generation_failed',
            ]);

            $project->messages()->create([
                'user_id' => $project->user_id,
                'role' => 'assistant',
                'content' => 'Generation failed: ' . $exception->getMessage() . ' Your credits were refunded.',
                'action_type' => 'generation_failed',
                'credits_used' => 0,
                'metadata' => [
                    'job_uuid' => $this->jobUuid,
                    'generation_status' => 'failed',
                    'error' => $exception->getMessage(),
                ],
            ]);
        }

        if ($user && $this->creditsCharged > 0) {
            app(NuruCreditService::class)->refund($user, $this->creditsCharged, 'Refund: failed ' . $this->label($this->type) . ' generation', $this->projectId);
        }

        Log::error('NuruXplore generation job failed', [
            'project_id' => $this->projectId,
            'job_uuid' => $this->jobUuid,
            'error' => $exception->getMessage(),
        ]);
    }

    protected function mark(NuruxploreProject $project, string $status, int $progress, string $step): void
    {
        $project->update([
            'generation_status' => $status,
            'generation_progress' => $progress,
            'generation_current_step' => $step,
            'generation_error' => null,
            'last_edited_at' => now(),
        ]);
    }

    protected function label(string $type): string
    {
        return match (strtolower($type)) {
            'proposal' => 'research proposal',
            'dissertation' => 'dissertation',
            default => 'thesis',
        };
    }
}
