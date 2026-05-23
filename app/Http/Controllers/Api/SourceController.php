<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NuruxploreSource;
use App\Models\NuruxploreProject;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class SourceController extends Controller
{
    /**
     * List all sources for a project
     */
    public function index($projectUuid): JsonResponse
    {
        $project = NuruxploreProject::where('uuid', $projectUuid)->firstOrFail();
        
        if ($project->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $sources = $project->sources()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($source) => [
                'id' => $source->id,
                'title' => $source->title,
                'author' => $source->author,
                'year' => $source->year,
                'type' => $source->type,
                'citation_count' => $source->citation_count,
                'verification_status' => $source->verification_status,
                'doi' => $source->doi,
                'url' => $source->url,
                'has_file' => !empty($source->file_path),
                'created_at' => $source->created_at->format('M d, Y'),
            ]);

        return response()->json(['sources' => $sources]);
    }

    /**
     * Add a new source to a project
     */
    public function store(Request $request, $projectUuid): JsonResponse
    {
        $project = NuruxploreProject::where('uuid', $projectUuid)->firstOrFail();
        
        if ($project->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'author' => 'nullable|string|max:255',
            'year' => 'nullable|string|max:4',
            'type' => 'required|in:book,journal,website,report,conference,thesis,other',
            'doi' => 'nullable|string|max:255',
            'url' => 'nullable|url|max:500',
        ]);

        $source = $project->sources()->create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'author' => $validated['author'] ?? null,
            'year' => $validated['year'] ?? null,
            'type' => $validated['type'],
            'doi' => $validated['doi'] ?? null,
            'url' => $validated['url'] ?? null,
            'verification_status' => 'unverified',
        ]);

        return response()->json([
            'source' => $source,
            'message' => 'Source added successfully',
        ], 201);
    }

    /**
     * Upload a file as a source
     */
    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:nuruxplore_projects,id',
            'file' => 'required|file|mimes:pdf,doc,docx|max:10240',
            'title' => 'nullable|string|max:500',
        ]);

        $project = NuruxploreProject::findOrFail($validated['project_id']);
        
        if ($project->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $file = $request->file('file');
        $path = $file->store('sources/' . $request->user()->id, 'public');

        $source = $project->sources()->create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'] ?? $file->getClientOriginalName(),
            'type' => 'journal',
            'file_path' => $path,
            'verification_status' => 'unverified',
        ]);

        return response()->json([
            'source' => $source,
            'message' => 'File uploaded successfully',
        ], 201);
    }

    /**
     * Verify a source
     */
    public function verify($id): JsonResponse
    {
        $source = NuruxploreSource::findOrFail($id);
        
        if ($source->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user = request()->user();
        if ($user->credits_balance < 1) {
            return response()->json([
                'message' => 'Insufficient credits',
                'credits_balance' => $user->credits_balance,
            ], 402);
        }

        // Simulate verification
        $verified = !empty($source->doi);
        
        $source->update([
            'verification_status' => $verified ? 'verified' : 'flagged',
        ]);

        $user->deductCredits(1, 'Source verification', $source->project_id);

        return response()->json([
            'source' => $source->fresh(),
            'credits_remaining' => $user->fresh()->credits_balance,
            'message' => $verified ? 'Source verified' : 'Source flagged - manual review needed',
        ]);
    }

    /**
     * Delete a source
     */
    public function destroy($id): JsonResponse
    {
        $source = NuruxploreSource::findOrFail($id);
        
        if ($source->user_id !== request()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($source->file_path) {
            Storage::disk('public')->delete($source->file_path);
        }

        $source->delete();

        return response()->json(null, 204);
    }
}