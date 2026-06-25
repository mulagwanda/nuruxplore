<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NuruxploreProject;
use App\Models\NuruxploreSection;
use App\Models\NuruxploreVersion;
use App\Jobs\GenerateResearchDocumentJob;
use App\Services\GroqAIService;
use App\Services\NuruAIService;
use App\Services\NuruCreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function __construct(
        protected GroqAIService $aiService,
        protected NuruAIService $nuruAI,
        protected NuruCreditService $credits
    ) {}

    public function index(): JsonResponse
    {
        $projects = request()->user()->projects()
            ->latest('last_edited_at')
            ->get()
            ->map(fn($project) => [
                'id' => $project->id,
                'uuid' => $project->uuid,
                'title' => $project->title,
                'type' => $project->type,
                'status' => $project->status,
                'generation_status' => $project->generation_status,
                'generation_progress' => $project->generation_progress ?? 0,
                'generation_current_step' => $project->generation_current_step,
                'word_count' => $project->word_count,
                'citation_style' => $project->citation_style,
                'research_profile_status' => $project->research_profile_status ?? 'missing',
                'has_research_profile' => !empty($project->research_profile),
                'has_outline' => !empty($project->structure),
                'last_edited_at' => $project->last_edited_at?->diffForHumans(),
                'created_at' => $project->created_at->format('M d, Y'),
            ]);

        return response()->json($projects);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:2000',
            'type' => 'required|in:thesis,dissertation,literature_review,lab_report,case_study,capstone,chat,proposal',
            'citation_style' => 'nullable|in:APA7,MLA,Chicago,IEEE',
            'description' => 'nullable|string|max:2000',
            'research_question' => 'nullable|string|max:2000',
            'auto_title' => 'nullable|boolean',
        ]);

        $type = $validated['type'];
        $rawPrompt = trim($validated['title']);
        $shouldGenerateTitle = ($validated['auto_title'] ?? true) && in_array($type, ['proposal', 'thesis', 'dissertation'], true);

        $titleResult = $shouldGenerateTitle
            ? $this->nuruAI->generateAcademicTitleFromPrompt($rawPrompt, $type)
            : ['success' => true, 'title' => $rawPrompt, 'tokens_used' => 0];

        $title = trim((string) ($titleResult['title'] ?? $rawPrompt));
        if ($title === '') {
            $title = Str::limit($rawPrompt, 180, '');
        }

        $project = $request->user()->projects()->create([
            'uuid' => (string) Str::uuid(),
            'title' => $title,
            'title_ai_generated' => $shouldGenerateTitle && $title !== $rawPrompt,
            'type' => $type,
            'citation_style' => $validated['citation_style'] ?? 'APA7',
            'description' => $validated['description'] ?? $rawPrompt,
            'original_prompt' => $rawPrompt,
            'research_question' => $validated['research_question'] ?? null,
            'research_profile_status' => 'missing',
            'status' => 'draft',
            'generation_settings' => [
                'original_prompt' => $rawPrompt,
                'title_generated_at' => $shouldGenerateTitle ? now()->toISOString() : null,
                'title_tokens_used' => $titleResult['tokens_used'] ?? 0,
            ],
            'last_edited_at' => now(),
        ]);

        NuruxploreVersion::create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'version_number' => 1,
            'snapshot' => ['content' => null, 'word_count' => 0, 'title' => $project->title],
            'changes_description' => $shouldGenerateTitle ? 'Project created with AI-generated academic title' : 'Project created',
            'change_type' => 'manual',
        ]);

        return response()->json([
            'project' => $project->fresh(),
            'id' => $project->id,
            'uuid' => $project->uuid,
            'title' => $project->title,
            'original_prompt' => $rawPrompt,
            'title_ai_generated' => $project->title_ai_generated,
            'message' => 'Project created successfully',
        ], 201);
    }

    public function show(NuruxploreProject $project): JsonResponse
    {
        $this->authorizeOwner($project);

        return response()->json([
            'project' => [
                'id' => $project->id,
                'uuid' => $project->uuid,
                'title' => $project->title,
                'type' => $project->type,
                'citation_style' => $project->citation_style,
                'description' => $project->description,
                'research_question' => $project->research_question,
                'research_profile' => $project->research_profile,
                'research_profile_status' => $project->research_profile_status ?? 'missing',
                'research_profile_approved_at' => $project->research_profile_approved_at,
                'generation_settings' => $project->generation_settings,
                'generation_status' => $project->generation_status,
                'generation_progress' => $project->generation_progress ?? 0,
                'generation_current_step' => $project->generation_current_step,
                'generation_steps' => $project->generation_steps,
                'generation_error' => $project->generation_error,
                'word_count' => $project->word_count,
                'status' => $project->status,
                'content' => $project->content,
                'structure' => $project->structure,
                'last_edited_at' => $project->last_edited_at?->diffForHumans(),
            ],
        ]);
    }

    public function update(Request $request, NuruxploreProject $project): JsonResponse
    {
        $this->authorizeOwner($project, $request);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|max:60',
            'structure' => 'sometimes|nullable|array',
            'description' => 'sometimes|nullable|string|max:2000',
            'research_question' => 'sometimes|nullable|string|max:2000',
            'generation_settings' => 'sometimes|nullable|array',
        ]);

        $project->update([...$validated, 'last_edited_at' => now()]);

        return response()->json(['project' => $project->fresh()]);
    }

    public function destroy(NuruxploreProject $project): JsonResponse
    {
        $this->authorizeOwner($project);
        $project->delete();
        return response()->json(null, 204);
    }

    public function duplicate($uuid): JsonResponse
    {
        $original = NuruxploreProject::where('uuid', $uuid)->with('sections', 'sources')->firstOrFail();
        $this->authorizeOwner($original);

        $duplicate = $original->replicate();
        $duplicate->title = $original->title . ' (Copy)';
        $duplicate->status = 'draft';
        $duplicate->last_edited_at = now();
        $duplicate->uuid = (string) Str::uuid();
        $duplicate->save();

        foreach ($original->sections as $section) {
            $newSection = $section->replicate();
            $newSection->project_id = $duplicate->id;
            $newSection->save();
        }

        return response()->json([
            'id' => $duplicate->id,
            'uuid' => $duplicate->uuid,
            'message' => 'Project duplicated successfully',
        ], 201);
    }

    public function buildResearchProfile(Request $request, NuruxploreProject $project): JsonResponse
    {
        $this->authorizeOwner($project, $request);
        $user = $request->user();
        $cost = 3;

        if ($user->credits_balance < $cost) {
            return response()->json(['message' => "Insufficient credits. Need {$cost} credits.", 'credits_balance' => $user->credits_balance], 402);
        }

        $result = $this->nuruAI->buildResearchProfile($project);
        if (!$result['success']) {
            return response()->json(['message' => $result['error']], 422);
        }

        $user->deductCredits($cost, 'Research profile generation', $project->id);

        return response()->json([
            'success' => true,
            'profile' => $result['profile'],
            'tokens_used' => $result['tokens_used'] ?? 0,
            'credits_remaining' => $user->fresh()->credits_balance,
            'message' => $result['message'] ?? 'Research profile generated.',
        ]);
    }

    public function updateResearchProfile(Request $request, NuruxploreProject $project): JsonResponse
    {
        $this->authorizeOwner($project, $request);
        $validated = $request->validate(['research_profile' => 'required|array']);

        $project->update([
            'research_profile' => $validated['research_profile'],
            'research_profile_status' => 'generated',
            'last_edited_at' => now(),
        ]);

        return response()->json(['success' => true, 'profile' => $project->fresh()->research_profile]);
    }

    public function approveResearchProfile(Request $request, NuruxploreProject $project): JsonResponse
    {
        $this->authorizeOwner($project, $request);
        $validated = $request->validate(['research_profile' => 'nullable|array']);

        $profile = $validated['research_profile'] ?? $project->research_profile;
        if (empty($profile)) {
            return response()->json(['message' => 'Research profile is empty. Build it first.'], 422);
        }

        $result = $this->nuruAI->approveResearchProfile($project, $profile);

        return response()->json([
            'success' => true,
            'profile' => $result['profile'],
            'message' => $result['message'],
        ]);
    }

    public function generateOutline(Request $request, $project): JsonResponse
    {
        $project = $project instanceof NuruxploreProject ? $project : NuruxploreProject::where('uuid', $project)->firstOrFail();
        $this->authorizeOwner($project, $request);

        $user = $request->user();
        $cost = 5;
        if ($user->credits_balance < $cost) {
            return response()->json(['message' => 'Insufficient credits. You need 5 credits.', 'credits_balance' => $user->credits_balance], 402);
        }

        $result = $project->research_profile
            ? $this->nuruAI->generateOutlineFromResearchProfile($project)
            : $this->aiService->generateThesisOutline($request->input('topic', $project->title), $project->type);

        if (!$result['success']) {
            return response()->json(['message' => $result['error']], 500);
        }

        $outline = $result['outline'] ?? ($result['json']['chapters'] ?? $this->parseOutlineFromAI($result['content'] ?? ''));
        $project->update(['structure' => $outline, 'status' => 'outline_generated', 'last_edited_at' => now()]);

        NuruxploreSection::where('project_id', $project->id)->delete();
        $this->createSectionsFromOutline($project, $outline);

        $user->deductCredits($cost, 'Outline generation', $project->id);

        return response()->json([
            'success' => true,
            'outline' => $outline,
            'sections' => $project->fresh()->topLevelSections()->with('children')->get()->values(),
            'credits_remaining' => $user->fresh()->credits_balance,
            'message' => 'Outline generated successfully with ' . count($outline) . ' chapters.',
        ]);
    }

    public function generateComplete(Request $request, NuruxploreProject $project): JsonResponse
    {
        $this->authorizeOwner($project, $request);

        $user = $request->user();
        $type = $request->input('type', $project->type ?: 'thesis');
        $topic = $request->input('topic', $project->original_prompt ?: $project->title);
        $cost = $this->credits->cost('generate_document', $type, $project);

        if (!$this->credits->canAfford($user, $cost)) {
            return response()->json([
                'message' => "Insufficient credits. Need {$cost} credits.",
                'credits_balance' => $user->credits_balance,
                'required_credits' => $cost,
                'credit_usd_value' => NuruCreditService::CREDIT_USD_VALUE,
            ], 402);
        }

        // Optional emergency sync mode for local debugging only. The production default is queued.
        if ($request->boolean('sync')) {
            $steps = $this->nuruAI->generateCompleteThesis($project, $topic, $type);
            $failed = collect($steps)->contains(fn($step) => ($step['status'] ?? null) === 'failed');
            if ($failed) {
                return response()->json(['success' => false, 'steps' => $steps, 'message' => 'Generation failed before credits were deducted.'], 422);
            }

            $this->credits->charge($user, $cost, ucfirst($type) . ' workflow generation', $project->id);

            return response()->json([
                'success' => true,
                'queued' => false,
                'steps' => $steps,
                'project_uuid' => $project->uuid,
                'project' => $project->fresh(),
                'credits_remaining' => $user->fresh()->credits_balance,
            ]);
        }

        $jobUuid = (string) Str::uuid();
        $this->credits->charge($user, $cost, ucfirst($type) . ' queued generation', $project->id);

        $project->update([
            'type' => $type,
            'original_prompt' => $project->original_prompt ?: $topic,
            'generation_status' => 'queued',
            'generation_progress' => 1,
            'generation_current_step' => 'Generation queued',
            'generation_steps' => [[
                'step' => 'queued',
                'status' => 'processing',
                'message' => 'Generation queued...',
            ]],
            'generation_error' => null,
            'generation_job_uuid' => $jobUuid,
            'generation_started_at' => now(),
            'generation_finished_at' => null,
            'credits_reserved' => $cost,
            'status' => 'queued',
            'last_edited_at' => now(),
        ]);

        GenerateResearchDocumentJob::dispatch($project->id, $user->id, $topic, $type, $cost, $jobUuid);

        return response()->json([
            'success' => true,
            'queued' => true,
            'status' => 'queued',
            'message' => ucfirst($type) . ' generation started.',
            'project_uuid' => $project->uuid,
            'job_uuid' => $jobUuid,
            'required_credits' => $cost,
            'credits_remaining' => $user->fresh()->credits_balance,
            'project' => $project->fresh(),
        ], 202);
    }

    public function generationStatus(Request $request, NuruxploreProject $project): JsonResponse
    {
        $this->authorizeOwner($project, $request);
        $project = $project->fresh();

        return response()->json([
            'success' => true,
            'project_uuid' => $project->uuid,
            'status' => $project->generation_status ?: $project->status,
            'progress' => (int) ($project->generation_progress ?? 0),
            'current_step' => $project->generation_current_step,
            'steps' => $project->generation_steps ?? [],
            'error' => $project->generation_error,
            'content_ready' => filled($project->content),
            'word_count' => $project->word_count,
            'credits_reserved' => $project->credits_reserved ?? 0,
            'started_at' => $project->generation_started_at,
            'finished_at' => $project->generation_finished_at,
            'project' => [
                'id' => $project->id,
                'uuid' => $project->uuid,
                'title' => $project->title,
                'type' => $project->type,
                'status' => $project->status,
                'content' => $project->content,
                'word_count' => $project->word_count,
            ],
        ]);
    }

    public function assembleDocument(Request $request, NuruxploreProject $project): JsonResponse
    {
        $this->authorizeOwner($project, $request);
        $result = $this->nuruAI->assembleDocument($project);
        if (!$result['success']) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['success' => true, 'project' => $project->fresh(), 'content' => $result['content'], 'word_count' => $result['word_count']]);
    }

    public function consistencyCheck(Request $request, NuruxploreProject $project): JsonResponse
    {
        $this->authorizeOwner($project, $request);
        $user = $request->user();
        $cost = 5;
        if ($user->credits_balance < $cost) {
            return response()->json(['message' => "Insufficient credits. Need {$cost} credits.", 'credits_balance' => $user->credits_balance], 402);
        }

        $result = $this->nuruAI->consistencyCheck($project);
        if (!$result['success']) {
            return response()->json(['message' => $result['error']], 422);
        }

        $user->deductCredits($cost, 'Consistency check', $project->id);

        return response()->json(['success' => true, 'report' => $result['report'], 'credits_remaining' => $user->fresh()->credits_balance]);
    }

    protected function parseOutlineFromAI(string $aiResponse): array
    {
        $cleanResponse = trim(preg_replace('/```json\s*|\s*```/', '', $aiResponse));
        $json = json_decode($cleanResponse, true);
        if ($json && isset($json['chapters'])) {
            return $json['chapters'];
        }
        if (preg_match('/\{.*"chapters".*\}/s', $aiResponse, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json && isset($json['chapters'])) {
                return $json['chapters'];
            }
        }
        return [
            ['title' => 'Abstract', 'subsections' => ['Background', 'Methodology Overview', 'Key Findings', 'Conclusions']],
            ['title' => 'Introduction', 'subsections' => ['Background', 'Problem Statement', 'Research Questions', 'Significance']],
            ['title' => 'Literature Review', 'subsections' => ['Theoretical Framework', 'Empirical Studies', 'Research Gaps']],
            ['title' => 'Methodology', 'subsections' => ['Research Design', 'Data Collection', 'Analysis Methods']],
            ['title' => 'Results', 'subsections' => ['Descriptive Statistics', 'Main Findings', 'Analysis']],
            ['title' => 'Discussion', 'subsections' => ['Interpretation', 'Implications', 'Limitations']],
            ['title' => 'Conclusion', 'subsections' => ['Summary', 'Recommendations', 'Future Research']],
        ];
    }

    protected function createSectionsFromOutline(NuruxploreProject $project, array $outline): void
    {
        $order = 1;
        $chapterNum = 1;

        foreach ($outline as $chapter) {
            $chapter = is_string($chapter) ? ['title' => $chapter, 'subsections' => []] : $chapter;
            $section = $project->sections()->create([
                'title' => $chapter['title'] ?? 'Untitled Chapter',
                'section_number' => (string) $chapterNum,
                'order' => $order++,
                'status' => 'outlined',
            ]);

            foreach (($chapter['subsections'] ?? []) as $subNum => $subsection) {
                $subsectionTitle = is_string($subsection) ? $subsection : ($subsection['title'] ?? 'Untitled Section');
                $section->children()->create([
                    'project_id' => $project->id,
                    'title' => $subsectionTitle,
                    'section_number' => $chapterNum . '.' . ($subNum + 1),
                    'order' => $subNum + 1,
                    'status' => 'outlined',
                ]);
            }
            $chapterNum++;
        }
    }

    protected function authorizeOwner(NuruxploreProject $project, ?Request $request = null): void
    {
        $user = $request?->user() ?? request()->user();
        abort_if($project->user_id !== $user->id, 403, 'Unauthorized');
    }
}
