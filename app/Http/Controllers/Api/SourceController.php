<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NuruxploreProject;
use App\Models\NuruxploreSource;
use App\Services\DocumentExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SourceController extends Controller
{
    public function index($projectUuid): JsonResponse
    {
        $project = NuruxploreProject::where('uuid', $projectUuid)->firstOrFail();
        $this->authorizeProject($project);

        $sources = $project->sources()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($source) => $this->sourcePayload($source));

        return response()->json(['sources' => $sources]);
    }

    public function store(Request $request, $projectUuid): JsonResponse
    {
        $project = NuruxploreProject::where('uuid', $projectUuid)->firstOrFail();
        $this->authorizeProject($project, $request);

        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'author' => 'nullable|string|max:255',
            'year' => 'nullable|string|max:4',
            'type' => 'required|in:book,journal,website,report,conference,thesis,proposal,dataset,template,supervisor_comments,other',
            'doi' => 'nullable|string|max:255',
            'url' => 'nullable|url|max:500',
            'document_role' => 'nullable|in:proposal,dataset,reference,template,supervisor_comments,other',
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
            'metadata' => ['document_role' => $validated['document_role'] ?? $validated['type']],
        ]);

        return response()->json(['source' => $this->sourcePayload($source), 'message' => 'Source added successfully'], 201);
    }

    public function upload(Request $request, DocumentExtractionService $extractor): JsonResponse
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:nuruxplore_projects,id',
            'file' => 'required|file|mimes:pdf,doc,docx,txt,md,csv,xlsx|max:20480',
            'title' => 'nullable|string|max:500',
            'document_role' => 'nullable|in:proposal,dataset,reference,template,supervisor_comments,other',
        ]);

        $project = NuruxploreProject::findOrFail($validated['project_id']);
        $this->authorizeProject($project, $request);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $role = $validated['document_role'] ?? ($extension === 'csv' || $extension === 'xlsx' ? 'dataset' : 'proposal');
        $path = $file->store('sources/' . $request->user()->id, 'public');

        $source = $project->sources()->create([
            'user_id' => $request->user()->id,
            'title' => $validated['title'] ?? $file->getClientOriginalName(),
            'type' => $role,
            'file_path' => $path,
            'verification_status' => 'unverified',
            'metadata' => [
                'document_role' => $role,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_extension' => $extension,
                'file_size' => $file->getSize(),
                'extraction_status' => 'pending',
            ],
        ]);

        $source = $extractor->extractAndSave($source);
        $project->update(['status' => 'files_uploaded', 'last_edited_at' => now()]);

        return response()->json([
            'source' => $this->sourcePayload($source),
            'has_extracted_text' => !empty($source->extracted_text),
            'message' => !empty($source->extracted_text) ? 'File uploaded and text extracted successfully' : 'File uploaded, but text extraction needs review',
        ], 201);
    }

    public function verify($id): JsonResponse
    {
        $source = NuruxploreSource::findOrFail($id);
        abort_if($source->user_id !== request()->user()->id, 403, 'Unauthorized');

        $user = request()->user();
        if ($user->credits_balance < 1) {
            return response()->json(['message' => 'Insufficient credits', 'credits_balance' => $user->credits_balance], 402);
        }

        $verified = !empty($source->doi) || !empty($source->url);
        $source->update(['verification_status' => $verified ? 'verified' : 'flagged']);
        $user->deductCredits(1, 'Source verification', $source->project_id);

        return response()->json([
            'source' => $this->sourcePayload($source->fresh()),
            'credits_remaining' => $user->fresh()->credits_balance,
            'message' => $verified ? 'Source verified' : 'Source flagged - manual review needed',
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $source = NuruxploreSource::findOrFail($id);
        abort_if($source->user_id !== request()->user()->id, 403, 'Unauthorized');

        if ($source->file_path) {
            Storage::disk('public')->delete($source->file_path);
        }

        $source->delete();

        return response()->json(null, 204);
    }

    protected function sourcePayload(NuruxploreSource $source): array
    {
        return [
            'id' => $source->id,
            'title' => $source->title,
            'author' => $source->author,
            'year' => $source->year,
            'type' => $source->type,
            'document_role' => $source->metadata['document_role'] ?? $source->type,
            'citation_count' => $source->citation_count,
            'verification_status' => $source->verification_status,
            'doi' => $source->doi,
            'url' => $source->url,
            'has_file' => !empty($source->file_path),
            'has_extracted_text' => !empty($source->extracted_text),
            'extraction_status' => $source->metadata['extraction_status'] ?? null,
            'word_count' => $source->metadata['word_count'] ?? null,
            'metadata' => $source->metadata,
            'created_at' => $source->created_at->format('M d, Y'),
        ];
    }

    protected function authorizeProject(NuruxploreProject $project, ?Request $request = null): void
    {
        $user = $request?->user() ?? request()->user();
        abort_if($project->user_id !== $user->id, 403, 'Unauthorized');
    }
}
