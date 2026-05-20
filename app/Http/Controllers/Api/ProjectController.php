<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NuruxploreProject;
use App\Models\NuruxploreVersion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    public function index(): JsonResponse
    {
        $projects = request()->user()->projects()
            ->withCount('sections')
            ->latest('last_edited_at')
            ->get()
            ->map(fn($project) => [
                'id' => $project->id,
                'title' => $project->title,
                'type' => $project->type,
                'status' => $project->status,
                'word_count' => $project->word_count,
                'citation_style' => $project->citation_style,
                'sections_count' => $project->sections_count,
                'last_edited_at' => $project->last_edited_at?->diffForHumans(),
                'created_at' => $project->created_at->format('M d, Y'),
            ]);

        return response()->json($projects);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:thesis,dissertation,literature_review,lab_report,case_study,capstone',
            'citation_style' => 'in:APA7,MLA,Chicago,IEEE',
            'description' => 'nullable|string|max:1000',
            'research_question' => 'nullable|string|max:1000',
        ]);

        $project = $request->user()->projects()->create([
            ...$validated,
            'status' => 'draft',
            'last_edited_at' => now(),
        ]);

        // Create initial version
        NuruxploreVersion::create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'version_number' => 1,
            'snapshot' => ['project' => $project->toArray(), 'sections' => []],
            'changes_description' => 'Project created',
            'change_type' => 'manual',
        ]);

        return response()->json([
            'id' => $project->id,
            'title' => $project->title,
            'message' => 'Project created successfully',
        ], 201);
    }

    public function show($id): JsonResponse
    {
        $project = NuruxploreProject::with([
            'sections' => fn($q) => $q->orderBy('order')->with('children'),
            'sources',
            'versions' => fn($q) => $q->latest()->limit(10)
        ])->findOrFail($id);

        // Authorization check
        if ($project->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'project' => $project,
            'outline' => $project->structure,
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $project = NuruxploreProject::findOrFail($id);

        if ($project->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'status' => 'sometimes|in:draft,in_progress,review,complete',
            'structure' => 'sometimes|nullable|array',
            'description' => 'sometimes|nullable|string|max:1000',
            'research_question' => 'sometimes|nullable|string|max:1000',
        ]);

        $project->update([...$validated, 'last_edited_at' => now()]);

        return response()->json($project);
    }

    public function destroy($id): JsonResponse
    {
        $project = NuruxploreProject::findOrFail($id);

        if ($project->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $project->delete();

        return response()->json(null, 204);
    }

    public function duplicate($id): JsonResponse
    {
        $original = NuruxploreProject::with('sections', 'sources')->findOrFail($id);

        if ($original->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $duplicate = $original->replicate();
        $duplicate->title = $original->title . ' (Copy)';
        $duplicate->status = 'draft';
        $duplicate->last_edited_at = now();
        $duplicate->save();

        foreach ($original->sections as $section) {
            $newSection = $section->replicate();
            $newSection->project_id = $duplicate->id;
            $newSection->save();
        }

        return response()->json([
            'id' => $duplicate->id,
            'message' => 'Project duplicated successfully'
        ], 201);
    }
}