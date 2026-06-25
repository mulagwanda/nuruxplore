<?php

namespace App\Services;

use App\Models\NuruxploreProject;
use App\Models\NuruxploreSection;
use App\Models\NuruxploreVersion;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NuruAIService
{
    public function __construct(protected GroqAIService $groq)
    {
    }

    /**
     * Build the project memory from uploaded proposal/data/source text.
     *
     * Main fixes:
     * - Smaller chunks so JSON output is less likely to be cut off.
     * - Chunk extraction does NOT return the full reference list.
     * - Invalid JSON chunks are retried once with the stronger model.
     * - A failed chunk becomes an extraction warning instead of killing the whole request.
     */
    public function buildResearchProfile(NuruxploreProject $project): array
    {
        $sources = $project->sources()
            ->whereNotNull('extracted_text')
            ->where('extracted_text', '!=', '')
            ->get();

        if ($sources->isEmpty()) {
            return [
                'success' => false,
                'error' => 'No extracted proposal/source text found. Upload a PDF/DOCX/TXT/CSV/XLSX file first and make sure extraction succeeds.',
            ];
        }

        $combined = '';
        $sourceDigest = [];

        foreach ($sources as $source) {
            $role = $source->metadata['document_role'] ?? $source->type ?? 'source';
            $text = trim((string) $source->extracted_text);
            if ($text === '') {
                continue;
            }

            $metadata = $source->metadata ?? [];
            $sourceDigest[] = [
                'id' => $source->id,
                'title' => $source->title,
                'role' => $role,
                'type' => $source->type,
                'word_count' => str_word_count(strip_tags($text)),
                'dataset_rows_extracted' => $metadata['dataset_rows_extracted'] ?? null,
                'dataset_columns' => $metadata['dataset_columns'] ?? null,
                'dataset_computed_indicators' => $metadata['dataset_computed_indicators'] ?? null,
                'dataset_frequency_tables' => $metadata['dataset_frequency_tables'] ?? null,
                'dataset_numeric_summaries' => $metadata['dataset_numeric_summaries'] ?? null,
            ];

            $combined .= "

--- SOURCE: {$source->title} | ROLE: {$role} ---
";
            if (!empty($metadata['dataset_analysis_markdown'])) {
                $combined .= "
DATASET ANALYSIS SUMMARY FROM UPLOADED FILE:
" . $metadata['dataset_analysis_markdown'] . "

";
            }
            $combined .= $text;
        }

        if (trim($combined) === '') {
            return [
                'success' => false,
                'error' => 'Uploaded files exist, but extracted text is empty. Please check the extraction service.',
            ];
        }

        $chunks = $this->chunkText($combined, 1400);
        $summaries = [];
        $warnings = [];
        $tokens = 0;

        foreach ($chunks as $i => $chunk) {
            $chunkNumber = $i + 1;

            // Reference-heavy chunks often exceed JSON limits. Extract a compact
            // local summary instead of asking the model to return a huge JSON array.
            if ($this->isReferenceHeavyChunk($chunk)) {
                $summaries[] = $this->referenceHeavyChunkSummary($chunk);
                $warnings[] = [
                    'chunk' => $chunkNumber,
                    'message' => 'Reference-heavy chunk summarized locally to avoid invalid JSON.',
                    'model' => 'deterministic',
                ];
                continue;
            }

            $result = $this->extractProfileChunk($project, $chunk, $chunkNumber, count($chunks));
            $tokens += (int) ($result['tokens_used'] ?? 0);

            if (!$result['success']) {
                $warnings[] = [
                    'chunk' => $chunkNumber,
                    'message' => $result['error'] ?? 'Chunk extraction failed.',
                    'model' => $result['model'] ?? null,
                ];

                Log::warning('Profile chunk extraction failed but workflow continued', [
                    'project_id' => $project->id,
                    'chunk' => $chunkNumber,
                    'error' => $result['error'] ?? null,
                    'content_sample' => Str::limit((string) ($result['content'] ?? ''), 500),
                ]);

                continue;
            }

            $chunkJson = $this->sanitizeProfileChunk($result['json'] ?? []);
            if (!empty($chunkJson)) {
                $summaries[] = $chunkJson;
            }
        }

        if (empty($summaries)) {
            return [
                'success' => false,
                'error' => 'Could not extract a research profile from uploaded documents. All chunks failed. Check Groq model/token settings and extracted text quality.',
                'warnings' => $warnings,
            ];
        }

        $mergeResult = $this->groq->jsonCall(
            'You merge proposal extraction chunks into one approved-ready research profile. Return ONLY valid JSON. Keep unknown items as null or empty arrays. Do not invent facts.',
            $this->mergeProfilePrompt($project, $summaries, $warnings, $sourceDigest),
            4200,
            $this->groq->writingModel()
        );

        $tokens += (int) ($mergeResult['tokens_used'] ?? 0);

        if (!$mergeResult['success']) {
            // Fallback: deterministic merge so long proposals do not crash the request.
            $warnings[] = [
                'chunk' => 'merge',
                'message' => $mergeResult['error'] ?? 'AI merge failed. Used deterministic fallback merge.',
                'model' => $mergeResult['model'] ?? null,
            ];

            $profile = $this->fallbackMergeProfile($project, $summaries, $warnings, $sourceDigest);
        } else {
            $profile = $this->normalizeResearchProfile($mergeResult['json'], $project);
            $profile['extraction_warnings'] = array_values(array_merge($profile['extraction_warnings'] ?? [], $warnings));
            $profile['source_digest'] = $sourceDigest;
            $profile['dataset_uploaded'] = $this->hasDataset($project);
        }

        $datasetProfile = $this->datasetProfileFromSources($project);
        if (!empty($datasetProfile)) {
            $profile['dataset_uploaded'] = true;
            $profile['dataset_profile'] = $datasetProfile;
            $profile['dataset_summary'] = array_values(array_filter(array_merge(
                $profile['dataset_summary'] ?? [],
                [$datasetProfile['summary'] ?? null]
            )));
            $profile['expected_or_actual_findings'] = array_values(array_filter(array_merge(
                $profile['expected_or_actual_findings'] ?? [],
                $datasetProfile['key_findings'] ?? []
            )));
        }

        $project->update([
            'research_profile' => $profile,
            'research_profile_status' => 'generated',
            'status' => 'profile_generated',
            'last_edited_at' => now(),
            'generation_settings' => array_merge($project->generation_settings ?? [], [
                'profile_built_at' => now()->toISOString(),
                'profile_tokens_used' => $tokens,
                'profile_source_count' => $sources->count(),
                'profile_chunk_count' => count($chunks),
                'profile_warning_count' => count($warnings),
            ]),
        ]);

        $this->createVersion($project->fresh(), 'Research profile generated', 'ai_profile', [
            'research_profile' => $profile,
            'tokens_used' => $tokens,
            'warnings' => $warnings,
        ]);

        return [
            'success' => true,
            'profile' => $profile,
            'tokens_used' => $tokens,
            'warnings' => $warnings,
            'message' => count($warnings) > 0
                ? 'Research profile generated with warnings. Please review before approving.'
                : 'Research profile generated successfully.',
        ];
    }

    protected function isReferenceHeavyChunk(string $chunk): bool
    {
        $lower = strtolower($chunk);
        $hasReferenceHeading = preg_match('/\b(references|bibliography)\b/i', $chunk) === 1;
        preg_match_all('/\(\d{4}\)|\(n\.d\.\)|doi:|https?:\/\//i', $chunk, $m);
        $referenceSignals = count($m[0] ?? []);
        return $hasReferenceHeading && ($referenceSignals >= 5 || str_word_count($chunk) > 900);
    }

    protected function referenceHeavyChunkSummary(string $chunk): array
    {
        $references = array_slice($this->extractReferenceEntries($chunk), 0, 8);
        return [
            'title' => null,
            'background_points' => [],
            'problem_statement_points' => [],
            'general_objective' => null,
            'specific_objectives' => [],
            'research_questions' => [],
            'hypotheses' => [],
            'methodology' => [],
            'variables' => [],
            'dataset_summary' => [],
            'limitations' => [],
            'references_available' => !empty($references),
            'reference_count_estimate' => count($references),
            'key_reference_examples' => $references,
            'extraction_note' => 'Reference-heavy chunk summarized locally.',
        ];
    }

    protected function extractProfileChunk(NuruxploreProject $project, string $chunk, int $index, int $total): array
    {
        $first = $this->groq->jsonCall(
            'You extract academic research facts from proposal/data text. Return ONLY valid JSON. Do not invent facts. Keep output concise.',
            $this->profileChunkPrompt($project, $chunk, $index, $total),
            1500,
            $this->groq->fastModel()
        );

        if ($first['success']) {
            return $first;
        }

        // Retry with the stronger model and an even stricter compact output instruction.
        $retry = $this->groq->jsonCall(
            'You repair/extract compact academic research facts. Return ONLY valid JSON. Never output long reference lists.',
            $this->profileChunkPrompt($project, $this->limitWords($chunk, 1000), $index, $total, true),
            1400,
            $this->groq->writingModel()
        );

        if ($retry['success']) {
            $retry['tokens_used'] = (int) ($first['tokens_used'] ?? 0) + (int) ($retry['tokens_used'] ?? 0);
            return $retry;
        }

        $retry['tokens_used'] = (int) ($first['tokens_used'] ?? 0) + (int) ($retry['tokens_used'] ?? 0);
        $retry['content'] = $retry['content'] ?? ($first['content'] ?? '');
        return $retry;
    }

    public function approveResearchProfile(NuruxploreProject $project, array $profile): array
    {
        $profile = $this->normalizeResearchProfile($profile, $project);

        $project->update([
            'research_profile' => $profile,
            'research_profile_status' => 'approved',
            'research_profile_approved_at' => now(),
            'status' => 'profile_approved',
            'last_edited_at' => now(),
        ]);

        $this->createVersion($project->fresh(), 'Research profile approved', 'manual', ['research_profile' => $profile]);

        return ['success' => true, 'profile' => $profile, 'message' => 'Research profile approved.'];
    }

    public function generateOutlineFromResearchProfile(NuruxploreProject $project): array
    {
        if (!$project->research_profile) {
            return ['success' => false, 'error' => 'Build and approve a research profile first.'];
        }

        $result = $this->groq->jsonCall(
            'You create academic thesis/proposal outlines. Return ONLY valid JSON. Do not create subsections under Abstract or References.',
            $this->outlinePrompt($project),
            2500,
            $this->groq->writingModel()
        );

        if (!$result['success']) {
            Log::warning('AI outline generation failed; using default outline', [
                'project_id' => $project->id,
                'error' => $result['error'] ?? null,
            ]);
            $chapters = $this->defaultOutline($project->type, $this->hasDataset($project));
        } else {
            $chapters = Arr::get($result['json'], 'chapters', $result['json']);
            if (!is_array($chapters) || empty($chapters)) {
                $chapters = $this->defaultOutline($project->type, $this->hasDataset($project));
            }
        }

        $chapters = $this->normalizeOutline($chapters, $project);

        $project->update([
            'structure' => $chapters,
            'status' => 'outline_generated',
            'last_edited_at' => now(),
        ]);

        $this->createVersion($project->fresh(), 'Outline generated from research profile', 'ai_outline', [
            'structure' => $chapters,
            'tokens_used' => $result['tokens_used'] ?? 0,
        ]);

        return [
            'success' => true,
            'outline' => $chapters,
            'tokens_used' => $result['tokens_used'] ?? 0,
        ];
    }

    /**
     * Generate a single section using the research profile as memory.
     * The backend owns headings. AI returns body only.
     */
    public function generateSectionFromProfile(NuruxploreSection $section, ?string $extraInstruction = null): array
    {
        $project = $section->project;
        if (!$project->research_profile) {
            return ['success' => false, 'error' => 'Research profile is missing. Build it before generating sections.'];
        }

        if ($this->isReferenceSectionTitle($section->title)) {
            $referenceResult = $this->generateApaReferences($project, 'Generate a proper APA7 reference list for the References section.', 8);
            if (!$referenceResult['success']) {
                return $referenceResult;
            }

            $content = trim((string) $referenceResult['content']);
            $section->update([
                'content' => $content,
                'status' => 'drafted',
                'ai_metadata' => array_merge($section->ai_metadata ?? [], [
                    'model' => $referenceResult['model'] ?? $this->groq->writingModel(),
                    'tokens_used' => $referenceResult['tokens_used'] ?? 0,
                    'summary' => 'APA-style references generated from available proposal/source evidence.',
                    'generated_at' => now()->toISOString(),
                ]),
            ]);

            return [
                'success' => true,
                'content' => $content,
                'summary' => 'APA-style references generated from available proposal/source evidence.',
                'tokens_used' => $referenceResult['tokens_used'] ?? 0,
                'model' => $referenceResult['model'] ?? null,
                'quality_flags' => [],
            ];
        }

        if ($project->type !== 'proposal' && $this->hasDataset($project) && $this->isDatasetResultsSection($section)) {
            $content = $this->deterministicDatasetResultsSection($section);
            $content = $this->cleanGeneratedSectionContent($content, $section);

            if (!blank($content)) {
                $summary = 'Dataset-backed findings generated from uploaded field data summaries.';
                $section->update([
                    'content' => $content,
                    'status' => 'drafted',
                    'ai_metadata' => array_merge($section->ai_metadata ?? [], [
                        'model' => 'deterministic-dataset-summary',
                        'tokens_used' => 0,
                        'target_words' => str_word_count(strip_tags($content)),
                        'summary' => $summary,
                        'quality_flags' => $this->qualityFlags($content),
                        'used_dataset_summary' => true,
                        'generated_at' => now()->toISOString(),
                    ]),
                ]);

                $project->update(['last_edited_at' => now(), 'status' => 'generating_sections']);

                return [
                    'success' => true,
                    'content' => $content,
                    'summary' => $summary,
                    'tokens_used' => 0,
                    'model' => 'deterministic-dataset-summary',
                    'quality_flags' => $this->qualityFlags($content),
                ];
            }
        }

        $targetWords = $this->targetWordsForSection($section);
        $context = $this->sectionContext($section);
        $datasetAvailable = $this->hasDataset($project) ? 'YES' : 'NO';

        $systemPrompt = <<<EOT
You are NuruXplore AI, an academic thesis drafting assistant.
The backend will add section headings. You must return BODY TEXT ONLY.
Never return Markdown headings, section numbers, title lines, or the full thesis.
Never output placeholders like [Author, Year], [Year], [number], [value], [study design], [sampling method], or [insert results].
Never invent results/findings when no dataset summary is provided.
Keep writing concise, non-repetitive, and aligned with the approved research profile.
EOT;

        $userPrompt = <<<EOT
{$context}

SECTION TO WRITE:
{$section->section_number} {$section->title}

TARGET WORDS:
{$targetWords}

DATASET AVAILABLE:
{$datasetAvailable}

EXTRA USER INSTRUCTION:
{$extraInstruction}

STRICT WRITING RULES:
- Return BODY TEXT ONLY. Do not include "{$section->title}" as a heading.
- Do not change the approved title, objectives, methodology, study area, population, or sample size.
- Do not use generic citation placeholders. If exact references are not available, write the claim without a fake citation.
- If this section is about results/findings and no dataset is available, write a planned findings/data-presentation framework, not actual findings.
- If a dataset is uploaded, use the DATASET PROFILE values and tables as real evidence. Do not invent frequencies, percentages, p-values, or tables outside the profile.
- For thesis sections after data collection, use completed-study tense (collected, revealed, showed, indicated), not proposal tense (will collect, will show, expected).
- For proposal Abstract sections, write 150-250 words only.
- For proposal General Objective sections, write one direct objective sentence only.
- For proposal Specific Objectives and Research Questions, use a numbered list, not paragraphs.
- For Timeline/Work Plan sections, prefer a concise Markdown table.
- Avoid repeating the same idea or paragraph.
- Use formal academic English.
- Use {$project->citation_style} only where actual reference details exist in the research profile.
EOT;

        $result = $this->groq->callGroqAPI($systemPrompt, $userPrompt, 2600, null, $this->groq->writingModel(), 0.22);
        $usedDeterministicFallback = false;
        $fallbackReason = null;

        if (!$result['success']) {
            $fallback = $this->deterministicSectionFallback($section, $extraInstruction, 'AI provider call failed: ' . ($result['error'] ?? 'unknown error'));
            if (blank($fallback)) {
                return $result;
            }

            $content = $this->cleanGeneratedSectionContent($fallback, $section);
            $usedDeterministicFallback = true;
            $fallbackReason = $result['error'] ?? 'AI provider call failed.';
            $result['tokens_used'] = $result['tokens_used'] ?? 0;
        } else {
            $content = $this->cleanGeneratedSectionContent((string) $result['content'], $section);

            // One quality retry if model produced repeated text or placeholders.
            if ($this->needsQualityRetry($content)) {
                $retryPrompt = $userPrompt . "\n\nQUALITY CORRECTION:\nYour previous response contained repeated text or placeholders. Rewrite cleanly. Body text only. No placeholders. No repeated paragraphs.";
                $retry = $this->groq->callGroqAPI($systemPrompt, $retryPrompt, 2300, null, $this->groq->writingModel(), 0.18);
                if ($retry['success'] && !blank($retry['content'] ?? '')) {
                    $content = $this->cleanGeneratedSectionContent((string) $retry['content'], $section);
                    $result['tokens_used'] = (int) ($result['tokens_used'] ?? 0) + (int) ($retry['tokens_used'] ?? 0);
                    $result['model'] = $retry['model'] ?? ($result['model'] ?? null);
                }
            }
        }

        if (blank($content)) {
            $fallback = $this->deterministicSectionFallback($section, $extraInstruction, 'AI returned empty section content after cleanup.');
            if (blank($fallback)) {
                return ['success' => false, 'error' => 'AI returned empty section content after cleanup.'];
            }

            $content = $this->cleanGeneratedSectionContent($fallback, $section);
            $usedDeterministicFallback = true;
            $fallbackReason = 'AI returned empty section content after cleanup.';
        }

        if (blank($content)) {
            return ['success' => false, 'error' => 'Fallback section content was empty after cleanup.'];
        }

        $summary = $this->summarizeSection($project, $section->title, $content);

        $section->update([
            'content' => $content,
            'status' => 'drafted',
            'ai_metadata' => array_merge($section->ai_metadata ?? [], [
                'model' => $result['model'] ?? $this->groq->writingModel(),
                'tokens_used' => $result['tokens_used'] ?? 0,
                'target_words' => $targetWords,
                'summary' => $summary,
                'quality_flags' => $this->qualityFlags($content),
                'used_deterministic_fallback' => $usedDeterministicFallback,
                'fallback_reason' => $fallbackReason,
                'generated_at' => now()->toISOString(),
            ]),
        ]);

        $project->update(['last_edited_at' => now(), 'status' => 'generating_sections']);

        return [
            'success' => true,
            'content' => $content,
            'summary' => $summary,
            'tokens_used' => $result['tokens_used'] ?? 0,
            'model' => $result['model'] ?? null,
            'quality_flags' => $this->qualityFlags($content),
        ];
    }

    public function reviseSection(NuruxploreSection $section, string $instruction): array
    {
        if (blank($section->content)) {
            return ['success' => false, 'error' => 'This section has no content to revise.'];
        }

        $context = $this->sectionContext($section);
        $systemPrompt = <<<EOT
You are an academic editor. Revise only the provided section.
Return body text only. Do not include section headings or explanations.
Do not introduce placeholders or fake citations.
EOT;
        $userPrompt = <<<EOT
PROJECT CONTEXT:
{$context}

ORIGINAL SECTION BODY:
{$section->content}

REVISION INSTRUCTION:
{$instruction}

Return only the revised section body. No heading.
EOT;

        $result = $this->groq->callGroqAPI($systemPrompt, $userPrompt, 2400, null, $this->groq->writingModel(), 0.2);
        if (!$result['success']) {
            return $result;
        }

        $content = $this->cleanGeneratedSectionContent((string) $result['content'], $section);
        if (blank($content)) {
            return ['success' => false, 'error' => 'AI returned empty revision after cleanup.'];
        }

        $summary = $this->summarizeSection($section->project, $section->title, $content);

        $section->update([
            'content' => $content,
            'status' => 'drafted',
            'ai_metadata' => array_merge($section->ai_metadata ?? [], [
                'last_revision_instruction' => $instruction,
                'last_revision_tokens' => $result['tokens_used'] ?? 0,
                'summary' => $summary,
                'quality_flags' => $this->qualityFlags($content),
                'revised_at' => now()->toISOString(),
            ]),
        ]);

        $section->project->update(['last_edited_at' => now()]);

        return [
            'success' => true,
            'content' => $content,
            'summary' => $summary,
            'tokens_used' => $result['tokens_used'] ?? 0,
            'quality_flags' => $this->qualityFlags($content),
        ];
    }

    public function assembleDocument(NuruxploreProject $project): array
    {
        $sections = $project->topLevelSections()->with('children')->get();
        if ($sections->isEmpty()) {
            return ['success' => false, 'error' => 'No sections found. Generate an outline first.'];
        }

        // Important: project.content must NOT include the project title.
        // The workspace preview and export templates already render project.title separately.
        // If we save the title here too, users see the title twice before the Abstract.
        $content = '';
        foreach ($sections as $chapter) {
            $content .= '## ' . trim(($chapter->section_number ? $chapter->section_number . ' ' : '') . $chapter->title) . "\n\n";

            if ($chapter->content) {
                $content .= $this->cleanGeneratedSectionContent((string) $chapter->content, $chapter) . "\n\n";
            }

            foreach ($chapter->children as $child) {
                $content .= '### ' . trim(($child->section_number ? $child->section_number . ' ' : '') . $child->title) . "\n\n";
                $content .= $this->cleanGeneratedSectionContent((string) $child->content, $child) . "\n\n";
            }
        }

        $content = $this->stripDocumentTitleFromContent($content, $project->title);

        $project->update([
            'content' => trim($content),
            'word_count' => str_word_count(strip_tags($content)),
            'status' => 'ready_for_review',
            'last_edited_at' => now(),
        ]);

        $this->createVersion($project->fresh(), 'Document assembled from generated sections', 'assembly', [
            'content' => $project->content,
            'word_count' => $project->word_count,
        ]);

        return ['success' => true, 'content' => $project->fresh()->content, 'word_count' => $project->fresh()->word_count];
    }

    public function consistencyCheck(NuruxploreProject $project): array
    {
        $document = $project->content ?: ($this->assembleDocument($project)['content'] ?? '');
        if (blank($document)) {
            return ['success' => false, 'error' => 'No document content to check.'];
        }

        $systemPrompt = 'You are an academic consistency reviewer. Return ONLY valid JSON.';
        $userPrompt = <<<EOT
APPROVED RESEARCH PROFILE:
{$this->json($project->research_profile)}

DOCUMENT EXCERPT:
{$this->limitWords($document, 6000)}

Check internal consistency. Also check duplicates, placeholders, fake findings, and repeated paragraphs.
Return JSON:
{
  "status": "pass|needs_revision",
  "issues": [{"section":"...", "problem":"...", "fix":"..."}],
  "summary": "..."
}
EOT;

        $result = $this->groq->jsonCall($systemPrompt, $userPrompt, 2500, $this->groq->writingModel());
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'Consistency check failed.'];
        }

        $settings = $project->generation_settings ?? [];
        $settings['last_consistency_check'] = $result['json'];
        $settings['last_consistency_checked_at'] = now()->toISOString();
        $project->update([
            'generation_settings' => $settings,
            'status' => ($result['json']['status'] ?? '') === 'pass' ? 'complete' : 'review',
        ]);

        return ['success' => true, 'report' => $result['json'], 'tokens_used' => $result['tokens_used'] ?? 0];
    }

    /**
     * Legacy dashboard "generate complete" path.
     * Kept compatible, but internally uses profile -> outline -> sections -> assembly.
     */
    public function generateCompleteThesis(NuruxploreProject $project, string $userTopic, string $type = 'thesis'): array
    {
        $steps = [];
        $project->update(['type' => $type ?: $project->type, 'last_edited_at' => now()]);
        $project = $project->fresh();

        if (!$project->research_profile) {
            $steps[] = ['step' => 'profile', 'status' => 'processing', 'message' => 'Building research profile...'];
            $this->persistGenerationProgress($project, $steps, 'building_profile', 8, 'Building research profile...');

            $hasExtractedSources = $project->sources()
                ->whereNotNull('extracted_text')
                ->where('extracted_text', '!=', '')
                ->exists();

            if ($hasExtractedSources) {
                $profile = $this->buildResearchProfile($project);
                if (!$profile['success']) {
                    $steps[] = ['step' => 'profile', 'status' => 'failed', 'message' => $profile['error']];
                    $this->persistGenerationProgress($project, $steps, 'failed', 100, $profile['error']);
                    return $steps;
                }
                $this->approveResearchProfile($project->fresh(), $profile['profile']);
                $steps[] = [
                    'step' => 'profile',
                    'status' => 'completed',
                    'message' => empty($profile['warnings']) ? 'Research profile ready.' : 'Research profile ready with warnings. Review recommended.',
                ];
                $this->persistGenerationProgress($project, $steps, 'building_profile', 20, 'Research profile ready.');
            } else {
                $minimalProfile = $this->minimalResearchProfile($project, $userTopic, $type);
                $this->approveResearchProfile($project, $minimalProfile);
                $steps[] = ['step' => 'profile', 'status' => 'completed', 'message' => 'Research profile created from topic.'];
                $this->persistGenerationProgress($project, $steps, 'building_profile', 20, 'Research profile created from topic.');
            }
        }

        $project = $project->fresh();

        $steps[] = ['step' => 'outline', 'status' => 'processing', 'message' => 'Generating outline from research profile...'];
        $this->persistGenerationProgress($project, $steps, 'generating_outline', 28, 'Generating outline from research profile...');
        $outline = $this->generateOutlineFromResearchProfile($project);
        if (!$outline['success']) {
            $steps[] = ['step' => 'outline', 'status' => 'failed', 'message' => $outline['error']];
            $this->persistGenerationProgress($project, $steps, 'failed', 100, $outline['error']);
            return $steps;
        }
        $this->replaceSectionsFromOutline($project->fresh(), $outline['outline']);
        $steps[] = ['step' => 'outline', 'status' => 'completed', 'message' => 'Outline generated.'];
        $this->persistGenerationProgress($project, $steps, 'generating_sections', 35, 'Outline generated. Writing sections...');

        $project = $project->fresh();
        $sections = $project->sections()
            ->with('children')
            ->orderBy('parent_id')
            ->orderBy('order')
            ->get()
            ->filter(fn (NuruxploreSection $section) => $section->children->isEmpty());

        if ($sections->isEmpty()) {
            $steps[] = ['step' => 'sections', 'status' => 'failed', 'message' => 'No sections found after outline generation.'];
            $this->persistGenerationProgress($project, $steps, 'failed', 100, 'No sections found after outline generation.');
            return $steps;
        }

        $steps[] = ['step' => 'sections', 'status' => 'processing', 'message' => 'Writing sections one by one...'];
        $this->persistGenerationProgress($project, $steps, 'generating_sections', 38, 'Writing sections one by one...');

        foreach ($sections as $section) {
            $section->update(['status' => 'drafting']);
            $result = $this->generateSectionFromProfile($section->fresh());
            if (!$result['success']) {
                $steps[] = [
                    'step' => 'section_' . $section->id,
                    'status' => 'failed',
                    'message' => "Failed: {$section->title}. " . ($result['error'] ?? ''),
                ];
                return $steps;
            }
            $steps[] = ['step' => 'section_' . $section->id, 'status' => 'completed', 'message' => '✅ ' . $section->section_number . ' ' . $section->title];
            $completedSections = max(1, collect($steps)->filter(fn ($step) => ($step['status'] ?? null) === 'completed' && str_starts_with((string) ($step['step'] ?? ''), 'section_'))->count());
            $totalSections = max(1, $sections->count());
            $progress = min(88, 38 + (int) round(($completedSections / $totalSections) * 45));
            $this->persistGenerationProgress($project, $steps, 'generating_sections', $progress, 'Writing ' . $section->section_number . ' ' . $section->title);
        }

        $this->persistGenerationProgress($project, $steps, 'assembling', 90, 'Assembling document...');
        $assembled = $this->assembleDocument($project->fresh());
        if (!$assembled['success']) {
            $steps[] = ['step' => 'assembly', 'status' => 'failed', 'message' => $assembled['error']];
            $this->persistGenerationProgress($project, $steps, 'failed', 100, $assembled['error']);
            return $steps;
        }

        $steps[] = ['step' => 'assembly', 'status' => 'completed', 'message' => '✅ Document assembled (' . $assembled['word_count'] . ' words).'];
        $this->persistGenerationProgress($project, $steps, 'completed', 100, 'Document assembled.');

        return $steps;
    }

    protected function datasetProfileFromSources(NuruxploreProject $project): array
    {
        $sources = $project->relationLoaded('sources') ? $project->sources : $project->sources()->get();
        $profiles = [];

        foreach ($sources as $source) {
            $metadata = $source->metadata ?? [];
            $role = strtolower((string) ($metadata['document_role'] ?? $source->type ?? ''));
            $isDataset = (bool) ($metadata['is_dataset'] ?? false)
                || in_array($role, ['dataset', 'data', 'survey_data', 'collected_data'], true)
                || !empty($metadata['dataset_columns']);

            if (!$isDataset) {
                continue;
            }

            $profiles[] = [
                'source_id' => $source->id,
                'title' => $source->title,
                'role' => $role ?: 'dataset',
                'row_count' => $metadata['dataset_rows_extracted'] ?? 0,
                'columns' => $metadata['dataset_columns'] ?? [],
                'sample_rows' => $metadata['dataset_sample_rows'] ?? [],
                'frequency_tables' => $metadata['dataset_frequency_tables'] ?? [],
                'numeric_summaries' => $metadata['dataset_numeric_summaries'] ?? [],
                'computed_indicators' => $metadata['dataset_computed_indicators'] ?? [],
                'analysis_markdown' => $metadata['dataset_analysis_markdown'] ?? null,
                'summary' => $metadata['dataset_summary'] ?? null,
            ];
        }

        if (empty($profiles)) {
            return [];
        }

        $first = $profiles[0];
        $keyFindings = $this->datasetKeyFindings($first);

        return [
            'available' => true,
            'source_count' => count($profiles),
            'primary' => $first,
            'sources' => $profiles,
            'summary' => 'Uploaded dataset available with ' . ($first['row_count'] ?? 0) . ' extracted respondent rows and ' . count($first['columns'] ?? []) . ' variables.',
            'key_findings' => $keyFindings,
            'instruction' => 'Use these values as real findings. Do not replace them with planned/expected findings and do not invent unsupported statistics.',
        ];
    }

    protected function datasetKeyFindings(array $dataset): array
    {
        $indicators = $dataset['computed_indicators'] ?? [];
        $findings = [];
        if (!empty($indicators['respondents'])) {
            $findings[] = 'The uploaded dataset contains ' . $indicators['respondents'] . ' respondent records.';
        }
        if (isset($indicators['uses_mobile_money_yes_percent'])) {
            $findings[] = $indicators['uses_mobile_money_yes_percent'] . '% of respondents reported using mobile money services.';
        }
        if (isset($indicators['average_revenue_before'], $indicators['average_revenue_after'])) {
            $findings[] = 'Average monthly revenue changed from TZS ' . number_format((float) $indicators['average_revenue_before']) . ' before mobile money use to TZS ' . number_format((float) $indicators['average_revenue_after']) . ' after mobile money use.';
        }
        if (isset($indicators['average_revenue_change_percent'])) {
            $findings[] = 'The computed average revenue change is ' . $indicators['average_revenue_change_percent'] . '%.';
        }
        if (isset($indicators['customer_reach_improved_yes_percent'])) {
            $findings[] = $indicators['customer_reach_improved_yes_percent'] . '% of respondents reported improved customer reach.';
        }
        if (isset($indicators['record_keeping_improved_yes_percent'])) {
            $findings[] = $indicators['record_keeping_improved_yes_percent'] . '% reported improved record keeping.';
        }
        if (isset($indicators['access_to_credit_improved_yes_percent'])) {
            $findings[] = $indicators['access_to_credit_improved_yes_percent'] . '% reported improved access to credit.';
        }
        if (isset($indicators['average_growth_rating'])) {
            $findings[] = 'The average business growth rating is ' . $indicators['average_growth_rating'] . ' on a 1-5 scale.';
        }
        return $findings;
    }

    protected function isDatasetResultsSection(NuruxploreSection $section): bool
    {
        $title = strtolower($section->title);
        $parent = strtolower((string) ($section->parent?->title ?? ''));
        $combined = $parent . ' ' . $title;

        if (!preg_match('/result|finding|respondent|demographic|profile|adoption|usage|financial|performance|benefit|challenge|comparison|summary/i', $combined)) {
            return false;
        }

        return preg_match('/literature|methodology|introduction|discussion|conclusion|recommendation|reference/i', $parent) !== 1;
    }

    protected function deterministicDatasetResultsSection(NuruxploreSection $section): string
    {
        $project = $section->project->fresh();
        $profile = $project->research_profile['dataset_profile'] ?? $this->datasetProfileFromSources($project);
        $dataset = $profile['primary'] ?? [];
        if (empty($dataset)) {
            return '';
        }

        $title = strtolower($section->title);
        $parent = strtolower((string) ($section->parent?->title ?? ''));
        $combined = $parent . ' ' . $title;
        $freq = $dataset['frequency_tables'] ?? [];
        $num = $dataset['numeric_summaries'] ?? [];
        $ind = $dataset['computed_indicators'] ?? [];
        $rows = (int) ($dataset['row_count'] ?? ($ind['respondents'] ?? 0));

        $out = [];
        $out[] = 'This section presents findings computed from the uploaded field dataset. The dataset contained ' . $rows . ' valid respondent records. The analysis used frequency distributions, percentages, and descriptive statistics to summarize small business owners’ characteristics, mobile money usage, and business growth indicators.';

        if (preg_match('/respondent|demographic|profile/i', $combined)) {
            $out[] = $this->frequencyMarkdownTable($freq, ['gender', 'age_group', 'education_level', 'business_type', 'years_in_business'], 'Table: Demographic and Business Characteristics of Respondents');
            $out[] = 'The demographic results provide the basis for interpreting mobile money adoption among different categories of small business owners. Variations by age group, education level, business type, and years in business help explain differences in adoption and perceived business benefits.';
        } elseif (preg_match('/adoption|usage|mobile money/i', $combined)) {
            $out[] = $this->frequencyMarkdownTable($freq, ['uses_mobile_money', 'primary_platform', 'daily_mobile_money_transactions'], 'Table: Adoption and Usage of Mobile Money Services');
            if (isset($ind['uses_mobile_money_yes_percent'])) {
                $out[] = 'The findings show that ' . $ind['uses_mobile_money_yes_percent'] . '% of respondents reported using mobile money services. This indicates that mobile money was widely adopted among the sampled small businesses and formed an important part of daily transaction management.';
            }
        } elseif (preg_match('/financial|performance|revenue|growth/i', $combined)) {
            $out[] = $this->numericMarkdownTable($num, ['monthly_revenue_before_tzs', 'monthly_revenue_after_tzs', 'daily_mobile_money_transactions', 'growth_rating_1_5'], 'Table: Financial Performance Indicators');
            if (isset($ind['average_revenue_before'], $ind['average_revenue_after'])) {
                $sentence = 'Average monthly revenue increased from TZS ' . number_format((float) $ind['average_revenue_before']) . ' before mobile money use to TZS ' . number_format((float) $ind['average_revenue_after']) . ' after mobile money use.';
                if (isset($ind['average_revenue_change_percent'])) {
                    $sentence .= ' This represents an average change of ' . $ind['average_revenue_change_percent'] . '%.';
                }
                $out[] = $sentence;
            }
            if (isset($ind['average_growth_rating'])) {
                $out[] = 'The average growth rating was ' . $ind['average_growth_rating'] . ' on a five-point scale, suggesting that respondents generally associated mobile money usage with positive business growth outcomes.';
            }
        } elseif (preg_match('/benefit|challenge/i', $combined)) {
            $out[] = $this->frequencyMarkdownTable($freq, ['main_benefit', 'main_challenge', 'customer_reach_improved', 'record_keeping_improved', 'access_to_credit_improved'], 'Table: Benefits and Challenges of Mobile Money Use');
            $parts = [];
            foreach (['customer_reach_improved' => 'customer reach', 'record_keeping_improved' => 'record keeping', 'access_to_credit_improved' => 'access to credit'] as $key => $label) {
                if (isset($ind[$key . '_yes_percent'])) {
                    $parts[] = $ind[$key . '_yes_percent'] . '% reported improved ' . $label;
                }
            }
            if ($parts) {
                $out[] = 'The results further indicate that ' . implode(', and ', $parts) . '. These findings suggest that mobile money contributed not only to transaction convenience but also to broader operational improvements among small businesses.';
            }
        } else {
            $out[] = $this->datasetSummaryTables($dataset);
            $keyFindings = $profile['key_findings'] ?? $this->datasetKeyFindings($dataset);
            if (!empty($keyFindings)) {
                $out[] = "Key findings from the uploaded dataset include:\n" . implode("\n", array_map(fn ($item) => '- ' . $item, $keyFindings));
            }
            $out[] = 'Overall, the dataset-backed findings show that mobile money services were associated with improved transaction convenience, wider customer reach, better record keeping, and positive revenue-related indicators among the sampled small businesses in Dar es Salaam.';
        }

        return trim(implode("\n\n", array_filter($out)));
    }

    protected function datasetSummaryTables(array $dataset): string
    {
        return trim($this->frequencyMarkdownTable($dataset['frequency_tables'] ?? [], ['gender', 'business_type', 'uses_mobile_money', 'primary_platform', 'main_benefit', 'main_challenge'], 'Table: Summary of Key Categorical Findings')
            . "\n\n" .
            $this->numericMarkdownTable($dataset['numeric_summaries'] ?? [], ['monthly_revenue_before_tzs', 'monthly_revenue_after_tzs', 'daily_mobile_money_transactions', 'growth_rating_1_5'], 'Table: Summary of Key Numeric Findings'));
    }

    protected function frequencyMarkdownTable(array $frequencies, array $preferredColumns, string $caption): string
    {
        $selected = [];
        foreach ($preferredColumns as $preferred) {
            foreach ($frequencies as $column => $items) {
                if (str_contains($column, $preferred) || str_contains($preferred, $column)) {
                    $selected[$column] = $items;
                    break;
                }
            }
        }
        if (empty($selected)) {
            $selected = array_slice($frequencies, 0, 4, true);
        }
        if (empty($selected)) {
            return '';
        }

        $lines = [$caption, '', '| Variable | Category | Frequency | Percentage |', '|---|---:|---:|---:|'];
        foreach ($selected as $column => $items) {
            foreach (array_slice((array) $items, 0, 8) as $item) {
                $lines[] = '| ' . $this->humanColumn($column) . ' | ' . ($item['value'] ?? '') . ' | ' . ($item['frequency'] ?? '') . ' | ' . ($item['percentage'] ?? '') . '% |';
            }
        }
        return implode("\n", $lines);
    }

    protected function numericMarkdownTable(array $numeric, array $preferredColumns, string $caption): string
    {
        $selected = [];
        foreach ($preferredColumns as $preferred) {
            foreach ($numeric as $column => $summary) {
                if (str_contains($column, $preferred) || str_contains($preferred, $column)) {
                    $selected[$column] = $summary;
                    break;
                }
            }
        }
        if (empty($selected)) {
            $selected = array_slice($numeric, 0, 6, true);
        }
        if (empty($selected)) {
            return '';
        }

        $lines = [$caption, '', '| Indicator | Count | Mean | Minimum | Maximum |', '|---|---:|---:|---:|---:|'];
        foreach ($selected as $column => $summary) {
            $lines[] = '| ' . $this->humanColumn($column) . ' | ' . ($summary['count'] ?? '') . ' | ' . ($summary['mean'] ?? '') . ' | ' . ($summary['min'] ?? '') . ' | ' . ($summary['max'] ?? '') . ' |';
        }
        return implode("\n", $lines);
    }

    protected function humanColumn(string $column): string
    {
        return Str::headline(str_replace('_', ' ', $column));
    }

    protected function persistGenerationProgress(NuruxploreProject $project, array $steps, string $status, int $progress, string $currentStep): void
    {
        try {
            $project->fresh()?->update([
                'generation_status' => $status,
                'generation_progress' => max(0, min(100, $progress)),
                'generation_current_step' => $currentStep,
                'generation_steps' => array_values($steps),
                'generation_error' => $status === 'failed' ? $currentStep : null,
                'last_edited_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not persist generation progress', [
                'project_id' => $project->id,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function smartChat(NuruxploreProject $project, string $userMessage, int $historyLimit = 20): array
    {
        if ($project->type === 'chat') {
            return $this->handleGeneralChatWithHistory($project, $historyLimit);
        }

        $intent = $this->classifyWorkspaceIntent($userMessage, $project);
        if (($intent['mode'] ?? 'chat') === 'edit') {
            return $this->handleTargetedWorkspaceEdit($project, $userMessage, $intent, $historyLimit);
        }

        return $this->handleProjectChatWithHistory($project, $historyLimit);
    }

    protected function handleGeneralChatWithHistory(NuruxploreProject $project, int $historyLimit = 20): array
    {
        $systemPrompt = 'You are NuruXplore AI, a helpful academic assistant. Use previous conversation messages to maintain context. Be helpful, informative, and encouraging.';
        $messages = $this->conversationMessages($project, $systemPrompt, $historyLimit);

        $answer = $this->groq->callGroqAPI($systemPrompt, '', 1400, $messages, $this->groq->fastModel(), 0.45);

        return [
            'action' => 'chat',
            'message' => $answer['success'] ? ($answer['content'] ?? 'Let me help with that.') : ($answer['error'] ?? 'Let me help with that.'),
            'tokens_used' => $answer['tokens_used'] ?? 0,
            'model' => $answer['model'] ?? null,
            'document_updated' => false,
        ];
    }

    protected function handleProjectChatWithHistory(NuruxploreProject $project, int $historyLimit = 20): array
    {
        $projectContext = $this->projectChatContext($project);
        $systemPrompt = "You are NuruXplore AI, an expert academic advisor. Use the project context and recent conversation history to answer. Do not edit the document unless the user clearly asks for a modification.\n\n" . $projectContext;
        $messages = $this->conversationMessages($project, $systemPrompt, $historyLimit);

        $answer = $this->groq->callGroqAPI($systemPrompt, '', 1400, $messages, $this->groq->fastModel(), 0.35);

        return [
            'action' => 'chat',
            'message' => $answer['success'] ? ($answer['content'] ?? 'Let me help with that.') : ($answer['error'] ?? 'Let me help with that.'),
            'tokens_used' => $answer['tokens_used'] ?? 0,
            'model' => $answer['model'] ?? null,
            'document_updated' => false,
        ];
    }

    /**
     * Workspace AI editing: targeted updates only.
     *
     * This deliberately avoids asking the model to rewrite the full thesis.
     * It finds the intended section, generates only replacement/inserted content,
     * replaces that section in project.content, then preserves the rest unchanged.
     */

    protected function handleTargetedWorkspaceEdit(NuruxploreProject $project, string $userMessage, array $intent, int $historyLimit = 12): array
    {
        $project = $project->fresh(['sections.children', 'sources', 'messages']);
        $document = trim((string) ($project->content ?? ''));
        $operation = $intent['operation'] ?? $intent['edit_type'] ?? 'rewrite_section';

        if (in_array($operation, ['update_project_title', 'suggest_titles', 'generate_academic_title'], true)) {
            return $this->handleProjectTitleOperation($project, $userMessage, $operation);
        }

        if ($document === '') {
            return [
                'action' => 'chat',
                'message' => 'I can update the thesis after the document has been generated or assembled. Please generate/assemble the document first.',
                'tokens_used' => 0,
                'document_updated' => false,
                'edit_type' => $operation,
            ];
        }

        if (in_array($operation, $this->reviewOperations(), true)) {
            return $this->handleWorkspaceReview($project, $userMessage, $operation, $historyLimit);
        }

        if ($operation === 'cleanup_document') {
            return $this->cleanupWholeDocument($project, $userMessage, $historyLimit);
        }

        if (in_array($operation, ['fix_truncation', 'fix_placeholder_text', 'remove_truncated_markers'], true)) {
            return $this->repairBrokenDocumentSections($project, $userMessage, $operation, $historyLimit);
        }

        if (in_array($operation, ['generate_references', 'format_references', 'expand_references', 'repair_references_section', 'fix_citation_style'], true)) {
            return $this->replaceReferencesViaWorkspaceChat($project, $userMessage, $historyLimit);
        }

        $targetKeyword = $intent['target'] ?? $intent['target_section'] ?? $this->guessTargetKeyword($userMessage);

        if (!$targetKeyword && ($intent['needs_clarification'] ?? false)) {
            return [
                'action' => 'chat',
                'message' => 'Which section should I update? For example, say “expand 2.1 Background”, “humanize Literature Review”, or “add a table to 5.1 Expected Data Presentation.”',
                'tokens_used' => 0,
                'document_updated' => false,
                'edit_type' => $operation,
            ];
        }

        $sectionRange = $this->findDocumentSectionRange($document, $targetKeyword);

        if (!$sectionRange && in_array($operation, ['insert_table', 'insert_chart_table', 'create_results_table', 'create_demographic_table', 'create_objective_analysis_table', 'create_frequency_table_template', 'create_chi_square_table_template', 'write_findings_placeholder'], true)) {
            $sectionRange = $this->findDocumentSectionRange($document, 'findings')
                ?: $this->findDocumentSectionRange($document, 'results')
                ?: $this->findDocumentSectionRange($document, 'planned presentation')
                ?: $this->findDocumentSectionRange($document, 'data presentation');
        }

        if (!$sectionRange) {
            return [
                'action' => 'chat',
                'message' => 'I understood that you want an edit, but I could not confidently find the target section in the current thesis. Please mention the section name, for example: “expand 2.1 Background” or “add a table to 5.1 Expected Data Presentation.”',
                'tokens_used' => 0,
                'document_updated' => false,
                'edit_type' => $operation,
            ];
        }

        $currentSection = trim(substr($document, $sectionRange['body_start'], $sectionRange['end'] - $sectionRange['body_start']));
        $newSectionBody = $this->generateWorkspaceSectionOperation($project, $userMessage, $operation, $sectionRange, $currentSection, $historyLimit);

        if (!$newSectionBody['success']) {
            return [
                'action' => 'chat',
                'message' => $newSectionBody['error'] ?? 'I could not update that section.',
                'tokens_used' => $newSectionBody['tokens_used'] ?? 0,
                'document_updated' => false,
                'edit_type' => $operation,
            ];
        }

        $replacementBody = trim((string) $newSectionBody['content']);
        $validation = $this->validateWorkspaceGeneratedContent($replacementBody, $operation);
        if (!$validation['valid']) {
            return [
                'action' => 'chat',
                'message' => $validation['message'],
                'tokens_used' => $newSectionBody['tokens_used'] ?? 0,
                'document_updated' => false,
                'edit_type' => $operation,
            ];
        }

        $updatedDocument = substr($document, 0, $sectionRange['body_start'])
            . $replacementBody . "\n\n"
            . substr($document, $sectionRange['end']);

        $updatedDocument = $this->finalizeDocumentContent($updatedDocument, $project->title);

        $project->update([
            'content' => $updatedDocument,
            'word_count' => str_word_count(strip_tags($updatedDocument)),
            'status' => 'draft',
            'last_edited_at' => now(),
        ]);

        $this->syncSectionModelFromDocumentEdit($project->fresh(), $sectionRange, $replacementBody);

        $this->createVersion($project->fresh(), 'Workspace AI operation: ' . $operation . ' → ' . $sectionRange['heading_text'], 'ai_revision', [
            'instruction' => $userMessage,
            'target_heading' => $sectionRange['heading_text'],
            'operation' => $operation,
            'tokens_used' => $newSectionBody['tokens_used'] ?? 0,
        ]);

        return [
            'action' => 'edit_section',
            'message' => 'Done. I applied “' . str_replace('_', ' ', $operation) . '” to “' . $sectionRange['heading_text'] . '” and preserved the rest of the thesis unchanged.',
            'tokens_used' => $newSectionBody['tokens_used'] ?? 0,
            'model' => $newSectionBody['model'] ?? null,
            'document_updated' => true,
            'target_section' => $sectionRange['heading_text'],
            'edit_type' => $operation,
        ];
    }

    protected function replaceReferencesViaWorkspaceChat(NuruxploreProject $project, string $userMessage, int $historyLimit = 12): array
    {
        $document = trim((string) ($project->content ?? ''));
        $sectionRange = $this->findDocumentSectionRange($document, 'references')
            ?: $this->findDocumentSectionRange($document, 'bibliography');

        if (!$sectionRange) {
            $document .= "\n\n## References\n\n";
            $sectionRange = $this->findDocumentSectionRange($document, 'references');
        }

        if (!$sectionRange) {
            return [
                'action' => 'chat',
                'message' => 'I could not locate or create the References section.',
                'tokens_used' => 0,
                'document_updated' => false,
            ];
        }

        $references = $this->generateApaReferences($project, $userMessage, $historyLimit);
        if (!$references['success']) {
            return [
                'action' => 'chat',
                'message' => $references['error'] ?? 'I could not generate the references section.',
                'tokens_used' => $references['tokens_used'] ?? 0,
                'document_updated' => false,
            ];
        }

        $content = trim((string) $references['content']);
        $validation = $this->validateWorkspaceGeneratedContent($content, 'generate_references');
        if (!$validation['valid']) {
            return [
                'action' => 'chat',
                'message' => $validation['message'],
                'tokens_used' => $references['tokens_used'] ?? 0,
                'document_updated' => false,
            ];
        }

        $updatedDocument = substr($document, 0, $sectionRange['body_start'])
            . $content . "\n\n"
            . substr($document, $sectionRange['end']);

        $updatedDocument = $this->finalizeDocumentContent($updatedDocument, $project->title);

        $project->update([
            'content' => $updatedDocument,
            'word_count' => str_word_count(strip_tags($updatedDocument)),
            'status' => 'draft',
            'last_edited_at' => now(),
        ]);

        $this->syncSectionModelFromDocumentEdit($project->fresh(), $sectionRange, $content);

        $this->createVersion($project->fresh(), 'Workspace AI updated APA references', 'ai_revision', [
            'instruction' => $userMessage,
            'target_heading' => $sectionRange['heading_text'],
            'edit_type' => 'generate_references',
            'tokens_used' => $references['tokens_used'] ?? 0,
        ]);

        return [
            'action' => 'edit_references',
            'message' => 'Done. I replaced the References section with a properly separated APA-style reference list and preserved the rest of the thesis unchanged.',
            'tokens_used' => $references['tokens_used'] ?? 0,
            'model' => $references['model'] ?? null,
            'document_updated' => true,
            'target_section' => $sectionRange['heading_text'],
            'edit_type' => 'generate_references',
        ];
    }

    protected function generateWorkspaceSectionOperation(NuruxploreProject $project, string $instruction, string $operation, array $sectionRange, string $currentSection, int $historyLimit = 12): array
    {
        if (in_array($operation, ['insert_table', 'insert_chart_table', 'create_results_table', 'create_demographic_table', 'create_objective_analysis_table', 'create_frequency_table_template', 'create_chi_square_table_template'], true)) {
            return $this->generateTableForSection($project, $instruction, $sectionRange, $currentSection, $operation);
        }

        if (in_array($operation, ['insert_conceptual_framework', 'insert_theoretical_framework', 'insert_definition_of_terms', 'insert_limitations', 'insert_delimitations', 'insert_ethics_section', 'insert_data_analysis_plan', 'insert_subsection', 'insert_paragraph', 'insert_figure_placeholder'], true)) {
            return $this->generateInsertionForSection($project, $instruction, $operation, $sectionRange, $currentSection, $historyLimit);
        }

        return $this->generateReplacementSectionBody($project, $instruction, $sectionRange, $currentSection, $historyLimit, $operation);
    }

    protected function generateReplacementSectionBody(NuruxploreProject $project, string $instruction, array $sectionRange, string $currentSection, int $historyLimit = 12, string $operation = 'rewrite_section'): array
    {
        $recentMessages = $this->recentConversationText($project, $historyLimit);
        $datasetRule = $this->hasDataset($project)
            ? 'Dataset context may be used only if it is present in the project profile/sources. Do not invent unsupported values.'
            : 'No dataset is uploaded. If this is a Results/Findings section, write planned presentation or analysis framework only. Do not invent real findings, percentages, p-values, totals, or participant counts beyond the approved sample size.';

        $operationRule = $this->operationInstruction($operation);

        $systemPrompt = 'You are NuruXplore AI, a careful academic thesis editor. You update ONLY the requested section body. Return only the revised body. Never include the heading.';
        $userPrompt = <<<EOT
PROJECT CONTEXT:
{$this->projectChatContext($project)}

TARGET SECTION HEADING:
{$sectionRange['heading_text']}

CURRENT SECTION BODY:
{$this->safeExcerpt($currentSection, 1700)}

RECENT WORKSPACE CHAT:
{$recentMessages}

USER INSTRUCTION:
{$instruction}

OPERATION:
{$operation}

OPERATION RULE:
{$operationRule}

RULES:
- Return the complete revised body for this section only.
- Do not include the section heading.
- Preserve approved study facts: title, population, study area, sample size, design, objectives, and methods unless the user explicitly provided corrected facts.
- {$datasetRule}
- Do not use placeholders such as [Author, Year], [Year], [insert results], [statistical software], or [truncated].
- Do not include literal phrases like "... [truncated]" or "content omitted".
- Use short academic paragraphs; avoid one huge paragraph.
- Avoid repetition and looping. Do not repeat the same idea more than once.
- If adding citations, use only known reference details from project context/sources. If details are not available, write the sentence without fake citation.
EOT;

        $result = $this->groq->callGroqAPI($systemPrompt, $userPrompt, 3000, null, $this->groq->writingModel(), 0.22);
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'AI section edit failed.', 'tokens_used' => $result['tokens_used'] ?? 0];
        }

        $content = $this->cleanStandaloneBody((string) $result['content'], $sectionRange['heading_text']);
        $content = $this->postProcessWorkspaceBody($content, $operation);

        if (blank($content)) {
            return ['success' => false, 'error' => 'AI returned empty section content.', 'tokens_used' => $result['tokens_used'] ?? 0];
        }

        if ($this->containsBadArtifacts($content)) {
            $retry = $this->retryCleanSection($project, $instruction, $operation, $sectionRange, $content);
            if ($retry['success']) {
                $content = $retry['content'];
                $result['tokens_used'] = (int) ($result['tokens_used'] ?? 0) + (int) ($retry['tokens_used'] ?? 0);
            }
        }

        return [
            'success' => true,
            'content' => $content,
            'tokens_used' => $result['tokens_used'] ?? 0,
            'model' => $result['model'] ?? null,
        ];
    }

    protected function generateInsertionForSection(NuruxploreProject $project, string $instruction, string $operation, array $sectionRange, string $currentSection, int $historyLimit = 12): array
    {
        $recentMessages = $this->recentConversationText($project, $historyLimit);
        $systemPrompt = 'You are NuruXplore AI, a careful academic thesis editor. Return the full revised section body after inserting the requested content. Do not include the section heading.';
        $userPrompt = <<<EOT
PROJECT CONTEXT:
{$this->projectChatContext($project)}

TARGET SECTION HEADING:
{$sectionRange['heading_text']}

CURRENT SECTION BODY:
{$this->safeExcerpt($currentSection, 1700)}

RECENT WORKSPACE CHAT:
{$recentMessages}

USER INSTRUCTION:
{$instruction}

OPERATION:
{$operation}

RULES:
- Insert the requested content in the most logical place inside this section.
- Return the complete revised section body only, without the heading.
- Use Markdown headings only for new subsections if needed, and use one level lower than the target heading.
- Preserve existing useful content.
- Do not invent real findings if no dataset exists.
- Do not use [Author, Year], [Year], [insert results], or [truncated].
EOT;

        $result = $this->groq->callGroqAPI($systemPrompt, $userPrompt, 2800, null, $this->groq->writingModel(), 0.22);
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'AI insertion failed.', 'tokens_used' => $result['tokens_used'] ?? 0];
        }

        $content = $this->cleanStandaloneBody((string) $result['content'], $sectionRange['heading_text']);
        $content = $this->postProcessWorkspaceBody($content, $operation);

        return [
            'success' => !blank($content),
            'content' => $content,
            'error' => blank($content) ? 'AI returned empty insertion content.' : null,
            'tokens_used' => $result['tokens_used'] ?? 0,
            'model' => $result['model'] ?? null,
        ];
    }

    protected function generateTableForSection(NuruxploreProject $project, string $instruction, array $sectionRange, string $currentSection, string $operation = 'insert_table'): array
    {
        $datasetRule = $this->hasDataset($project)
            ? 'If dataset summary is present, create a table using only supported variables/findings. Do not invent unsupported numbers.'
            : 'No dataset is available. Create planned analysis, expected data presentation, or chart-ready template tables only. Do not invent real findings, percentages, p-values, or totals.';

        $tableType = match ($operation) {
            'create_demographic_table' => 'demographic characteristics table template',
            'create_objective_analysis_table' => 'analysis by research objective table',
            'create_frequency_table_template' => 'frequency distribution table template',
            'create_chi_square_table_template' => 'chi-square association table template',
            'insert_chart_table' => 'chart-ready data table plus figure title/placeholder',
            default => 'useful academic Markdown table',
        };

        $systemPrompt = 'You are NuruXplore AI, an academic thesis editor. Add a valid Markdown table to the target section. Return ONLY the full revised section body. No heading.';
        $userPrompt = <<<EOT
PROJECT CONTEXT:
{$this->projectChatContext($project)}

TARGET SECTION HEADING:
{$sectionRange['heading_text']}

CURRENT SECTION BODY:
{$this->safeExcerpt($currentSection, 1500)}

USER INSTRUCTION:
{$instruction}

TABLE TYPE:
{$tableType}

RULES:
- Return the full revised section body with a valid Markdown table included.
- Do not include the section heading.
- {$datasetRule}
- If asked for chart/figure, create a chart-ready table and a short figure title, not an image.
- Use formal academic wording before/after the table.
- Table must use Markdown pipes and separator row.
- Keep the section concise and non-repetitive.
EOT;

        $result = $this->groq->callGroqAPI($systemPrompt, $userPrompt, 2600, null, $this->groq->writingModel(), 0.2);
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'AI table insertion failed.', 'tokens_used' => $result['tokens_used'] ?? 0];
        }

        $content = $this->cleanStandaloneBody((string) $result['content'], $sectionRange['heading_text']);
        if (!str_contains($content, '|')) {
            $content .= "\n\n" . $this->defaultPlannedResultsTable($project);
        }
        $content = $this->postProcessWorkspaceBody($content, $operation);

        return [
            'success' => true,
            'content' => trim($content),
            'tokens_used' => $result['tokens_used'] ?? 0,
            'model' => $result['model'] ?? null,
        ];
    }

    protected function generateApaReferences(NuruxploreProject $project, string $instruction, int $historyLimit = 12): array
    {
        $referenceEvidence = $this->referenceEvidenceFromSources($project);
        $profileReferences = $project->research_profile['key_reference_examples'] ?? [];
        $recentMessages = $this->recentConversationText($project, $historyLimit);
        $minimumReferences = $this->requestedReferenceCount($instruction);
        $minimumInstruction = $minimumReferences > 0
            ? "Return at least {$minimumReferences} reference entries if the uploaded proposal/source evidence supports that many. If exact details are missing, place additional entries under 'References to verify before submission'."
            : 'Return a complete reference list from the uploaded proposal/source evidence.';

        $systemPrompt = 'You are NuruXplore AI, an academic reference formatter. Return ONLY an APA7-style Markdown numbered list. No explanatory paragraphs.';
        $userPrompt = <<<EOT
PROJECT TITLE:
{$project->title}

CITATION STYLE:
{$project->citation_style}

USER INSTRUCTION:
{$instruction}

REFERENCE EXAMPLES FROM RESEARCH PROFILE:
{$this->json($profileReferences)}

REFERENCE EVIDENCE EXTRACTED FROM UPLOADED SOURCES:
{$referenceEvidence}

RECENT WORKSPACE CHAT:
{$recentMessages}

TASK:
Create a proper APA7 References section body.

RULES:
- {$minimumInstruction}
- Return reference entries only. Do not summarize what references say.
- Every reference entry MUST be on its own separate line.
- Use numbered Markdown list format: 1. Author. (Year). Title. Publisher/Journal.
- Do not merge multiple references into one paragraph.
- Do not write “Studies have shown...” paragraphs.
- Prefer exact references found in uploaded proposal/source evidence.
- Include reputable organizational sources only when they are in the proposal/profile/evidence.
- If details are incomplete, put those entries under: ### References to verify before submission
- Do not invent DOIs, journal issue numbers, URLs, or years that are not supplied.
- Use APA7 wording as much as the available evidence allows.
EOT;

        $result = $this->groq->callGroqAPI($systemPrompt, $userPrompt, 3400, null, $this->groq->writingModel(), 0.10);
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'Reference generation failed.', 'tokens_used' => $result['tokens_used'] ?? 0];
        }

        $content = trim((string) $result['content']);
        $content = preg_replace('/^#+\s*references\s*/i', '', $content) ?? $content;
        $content = preg_replace('/^```(?:markdown|md)?\s*/i', '', $content) ?? $content;
        $content = preg_replace('/\s*```$/', '', $content) ?? $content;
        $content = trim($content);

        if ($content === '' || str_word_count(strip_tags($content)) < 20) {
            $content = $this->fallbackReferenceList($project, $minimumReferences);
        }

        $content = $this->normalizeReferenceListFormat($content, $project, $minimumReferences);

        return [
            'success' => true,
            'content' => $content,
            'tokens_used' => $result['tokens_used'] ?? 0,
            'model' => $result['model'] ?? null,
        ];
    }

    protected function conversationMessages(NuruxploreProject $project, string $systemPrompt, int $historyLimit = 20): array
    {
        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        $history = $project->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at', 'desc')
            ->limit(max(1, min($historyLimit, 30)))
            ->get()
            ->reverse();

        foreach ($history as $message) {
            $messages[] = [
                'role' => $message->role === 'assistant' ? 'assistant' : 'user',
                'content' => $this->limitWords((string) $message->content, 450),
            ];
        }

        return $messages;
    }

    protected function projectChatContext(NuruxploreProject $project): string
    {
        $profile = $project->research_profile ? $this->json($project->research_profile) : 'No approved research profile yet.';
        $outline = $project->structure ? $this->json($project->structure) : 'No outline yet.';
        $documentSummary = $project->content ? $this->limitWords($project->content, 900) : 'No assembled document yet.';

        return <<<EOT
PROJECT TITLE: {$project->title}
PROJECT TYPE: {$project->type}
CITATION STYLE: {$project->citation_style}
RESEARCH QUESTION: {$project->research_question}
DATASET AVAILABLE: {$this->boolWord($this->hasDataset($project))}

RESEARCH PROFILE:
{$profile}

OUTLINE:
{$outline}

CURRENT DOCUMENT SUMMARY/EXCERPT:
{$documentSummary}
EOT;
    }


    protected function classifyWorkspaceIntent(string $message, NuruxploreProject $project): array
    {
        if ($project->type === 'chat') {
            return ['mode' => 'chat', 'operation' => 'chat_only'];
        }

        $msg = strtolower(trim($message));
        if ($msg === '') {
            return ['mode' => 'chat', 'operation' => 'chat_only'];
        }

        $operations = $this->workspaceOperationPatterns();
        foreach ($operations as $operation => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $msg)) {
                    return [
                        'mode' => in_array($operation, $this->reviewOperations(), true) ? 'edit' : 'edit',
                        'operation' => $operation,
                        'edit_type' => $operation,
                        'target' => $this->guessTargetKeyword($message),
                        'needs_clarification' => $this->operationNeedsTarget($operation) && !$this->guessTargetKeyword($message) && !$this->messageTargetsWholeDocument($message),
                    ];
                }
            }
        }

        $looksLikeQuestionOnly = str_contains($msg, '?')
            && !preg_match('/\b(please|can you|could you|kindly)\s+(add|create|update|replace|rewrite|expand|insert|generate|format|make|fix|improve|revise|remove|clean|check)\b/i', $message);

        if ($looksLikeQuestionOnly) {
            return ['mode' => 'chat', 'operation' => 'chat_only'];
        }

        $editWords = [
            'add', 'remove', 'delete', 'modify', 'change', 'update', 'rewrite', 'revise', 'expand', 'shorten',
            'replace', 'insert', 'fix', 'correct', 'edit', 'append', 'include', 'incorporate', 'enhance',
            'improve', 'rephrase', 'reword', 'restructure', 'extend', 'reduce', 'trim', 'elaborate',
            'clarify', 'simplify', 'strengthen', 'adjust', 'refine', 'polish', 'draft', 'write', 'generate',
            'create', 'compose', 'proofread', 'copyedit', 'reformat', 'format', 'make', 'humanize', 'professionalize'
        ];

        $hasEditVerb = false;
        foreach ($editWords as $word) {
            if (str_contains($msg, $word)) {
                $hasEditVerb = true;
                break;
            }
        }

        if (!$hasEditVerb) {
            return ['mode' => 'chat', 'operation' => 'chat_only'];
        }

        $operation = $this->inferGenericEditOperation($message);
        return [
            'mode' => 'edit',
            'operation' => $operation,
            'edit_type' => $operation,
            'target' => $this->guessTargetKeyword($message),
            'needs_clarification' => $this->operationNeedsTarget($operation) && !$this->guessTargetKeyword($message) && !$this->messageTargetsWholeDocument($message),
        ];
    }

    protected function workspaceOperationPatterns(): array
    {
        return [
            'update_project_title' => ['/\b(title|project title|research title)\b.*\b(change|update|fix|rewrite|improve|make|shorten|professional|academic|better)\b/', '/\b(change|update|fix|rewrite|improve|make|shorten)\b.*\b(title|project title|research title)\b/'],
            'suggest_titles' => ['/\b(suggest|give|generate|create)\b.*\b(title|titles|research title)\b/'],
            'generate_references' => ['/\b(reference|references|bibliography|apa\s*7|apa7|reference list)\b/'],
            'add_in_text_citations' => ['/\b(add|insert|include|fix).*\b(in[- ]?text citation|citation|citations)\b/'],
            'citation_gap_review' => ['/\b(citation gap|missing citation|needs citation|where.*citation)\b/'],
            'insert_table' => ['/\b(add|insert|create|make|generate).*\b(table|tabulate|matrix)\b/'],
            'insert_chart_table' => ['/\b(add|insert|create|make|generate).*\b(chart|graph|figure)\b/'],
            'create_demographic_table' => ['/\b(demographic|respondent profile).*\b(table)\b/'],
            'create_objective_analysis_table' => ['/\b(objective|research objective).*\b(table|analysis table)\b/'],
            'create_chi_square_table_template' => ['/\b(chi[- ]?square|association).*\b(table)\b/'],
            'create_frequency_table_template' => ['/\b(frequency|percentage|distribution).*\b(table)\b/'],
            'insert_conceptual_framework' => ['/\b(conceptual framework)\b/'],
            'insert_theoretical_framework' => ['/\b(theoretical framework|theory framework)\b/'],
            'insert_definition_of_terms' => ['/\b(definition of terms|define terms|key terms)\b/'],
            'insert_ethics_section' => ['/\b(ethical consideration|ethics section|research ethics)\b/'],
            'insert_limitations' => ['/\b(add|insert|include|write).*\b(limitations|delimitations)\b/'],
            'insert_data_analysis_plan' => ['/\b(data analysis plan|analysis plan)\b/'],
            'fix_truncation' => ['/\b(truncated|cut off|incomplete section|continues|missing ending)\b/'],
            'fix_placeholder_text' => ['/\b(\[author, year\]|\[year\]|placeholder|insert results|statistical software)\b/'],
            'fix_duplicate_headings' => ['/\b(duplicate heading|repeated heading|duplicate title)\b/'],
            'fix_heading_numbering' => ['/\b(heading numbering|numbering|chapter numbering)\b/'],
            'cleanup_document' => ['/\b(clean up|cleanup|clean the whole|format the whole|fix whole thesis|repair document)\b/'],
            'plagiarism_risk_review' => ['/\b(plagiarism|turnitin|similarity|originality)\b/'],
            'quality_review' => ['/\b(review my thesis|review the thesis|supervisor review|overall review|what should i improve)\b/'],
            'grammar_review' => ['/\b(grammar|proofread|spelling)\b/'],
            'academic_quality_review' => ['/\b(academic quality|make.*academic|academic tone|scholarly)\b/'],
            'consistency_check' => ['/\b(consistency|align|alignment|objectives.*methodology|methodology.*objectives)\b/'],
            'remove_repetition' => ['/\b(remove repetition|too repetitive|repeated|looping)\b/'],
            'humanize_section' => ['/\b(humanize|more human|less ai|ai-like|natural)\b/'],
            'professionalize_section' => ['/\b(professional|polish|formal|better tone)\b/'],
            'shorten_section' => ['/\b(shorten|summarize|collapse|reduce|trim)\b/'],
            'expand_section' => ['/\b(expand|elaborate|add more detail|make.*longer|extend)\b/'],
            'simplify_section' => ['/\b(simplify|make.*simple|plain language)\b/'],
            'rewrite_section' => ['/\b(rewrite|revise|rephrase|reword|improve|strengthen|make.*better)\b/'],
        ];
    }

    protected function inferGenericEditOperation(string $message): string
    {
        $msg = strtolower($message);
        return match (true) {
            str_contains($msg, 'expand') || str_contains($msg, 'elaborate') || str_contains($msg, 'longer') => 'expand_section',
            str_contains($msg, 'shorten') || str_contains($msg, 'collapse') || str_contains($msg, 'summarize') => 'shorten_section',
            str_contains($msg, 'human') || str_contains($msg, 'natural') || str_contains($msg, 'less ai') => 'humanize_section',
            str_contains($msg, 'professional') || str_contains($msg, 'formal') || str_contains($msg, 'polish') => 'professionalize_section',
            str_contains($msg, 'simplify') => 'simplify_section',
            str_contains($msg, 'remove repetition') || str_contains($msg, 'repetitive') => 'remove_repetition',
            default => 'rewrite_section',
        };
    }

    protected function operationNeedsTarget(string $operation): bool
    {
        return !in_array($operation, [
            'chat_only', 'quality_review', 'grammar_review', 'academic_quality_review', 'plagiarism_risk_review',
            'consistency_check', 'citation_gap_review', 'cleanup_document', 'fix_truncation', 'fix_placeholder_text',
            'fix_duplicate_headings', 'fix_heading_numbering', 'generate_references', 'format_references',
            'expand_references', 'repair_references_section', 'fix_citation_style', 'update_project_title', 'suggest_titles', 'generate_academic_title'
        ], true);
    }

    protected function messageTargetsWholeDocument(string $message): bool
    {
        return (bool) preg_match('/\b(whole thesis|whole document|entire thesis|entire document|all sections|full thesis)\b/i', $message);
    }

    protected function reviewOperations(): array
    {
        return [
            'quality_review', 'grammar_review', 'academic_quality_review', 'plagiarism_risk_review',
            'consistency_check', 'citation_gap_review', 'suggest_improvements', 'chapter_review',
            'methodology_review', 'literature_gap_review', 'objective_alignment_review',
        ];
    }

    protected function guessTargetKeyword(string $message): ?string
    {
        $msg = strtolower($message);
        $targets = [
            'abstract' => ['abstract'],
            'background' => ['background'],
            'problem statement' => ['problem statement', 'statement of problem'],
            'research objectives' => ['research objectives', 'objectives', 'specific objectives', 'general objective'],
            'research questions' => ['research questions', 'questions'],
            'significance' => ['significance'],
            'literature review' => ['literature review', 'literature', 'chapter two', 'chapter 2'],
            'methodology' => ['methodology', 'methods', 'chapter four', 'chapter 4', 'chapter three', 'chapter 3'],
            'research design' => ['research design', 'study design', 'design'],
            'study population' => ['study population', 'population', 'sampling'],
            'data collection' => ['data collection'],
            'data analysis' => ['data analysis', 'analysis procedures'],
            'results' => ['results', 'findings', 'planned presentation', 'expected data presentation', 'chapter five', 'chapter 5'],
            'discussion' => ['discussion'],
            'limitations' => ['limitations'],
            'conclusion' => ['conclusion'],
            'recommendations' => ['recommendations', 'practice and policy', 'policy makers'],
            'references' => ['references', 'bibliography'],
        ];

        foreach ($targets as $target => $phrases) {
            foreach ($phrases as $phrase) {
                if (str_contains($msg, $phrase)) {
                    return $target;
                }
            }
        }

        if (preg_match('/(?:section|chapter)\s+([0-9]+(?:\.[0-9]+)*)/i', $message, $m)) {
            return $m[1];
        }

        if (preg_match('/\b([0-9]+(?:\.[0-9]+)*)\b/', $message, $m)) {
            return $m[1];
        }

        return null;
    }

    protected function findDocumentSectionRange(string $document, ?string $keyword): ?array
    {
        if (blank($keyword)) {
            return null;
        }

        $lines = preg_split('/\R/', $document);
        if (!is_array($lines)) {
            return null;
        }

        $offsets = [];
        $cursor = 0;
        foreach ($lines as $line) {
            $offsets[] = $cursor;
            $cursor += strlen($line) + 1;
        }

        $keywordNorm = $this->normalizeHeadingText((string) $keyword);
        $best = null;

        foreach ($lines as $i => $line) {
            if (!preg_match('/^(#{1,6})\s+(.+)$/', trim($line), $m)) {
                continue;
            }

            $level = strlen($m[1]);
            $headingText = trim($m[2]);
            $headingNorm = $this->normalizeHeadingText($headingText);

            $score = 0;
            if ($headingNorm === $keywordNorm) {
                $score = 100;
            } elseif (preg_match('/^' . preg_quote($keywordNorm, '/') . '$/i', $headingNorm)) {
                $score = 95;
            } elseif (str_contains($headingNorm, $keywordNorm)) {
                $score = 80;
            } elseif (str_contains($keywordNorm, $headingNorm) && str_word_count($headingNorm) >= 1) {
                $score = 65;
            }

            if (preg_match('/^' . preg_quote((string) $keyword, '/') . '\b/', $headingText)) {
                $score = 110;
            }

            if ($score <= 0) {
                continue;
            }

            if (!$best || $score > $best['score']) {
                $best = [
                    'score' => $score,
                    'line_index' => $i,
                    'level' => $level,
                    'heading_text' => $headingText,
                    'heading_start' => $offsets[$i],
                    'body_start' => $offsets[$i] + strlen($line) + 1,
                ];
            }
        }

        if (!$best) {
            return null;
        }

        $end = strlen($document);
        for ($j = $best['line_index'] + 1; $j < count($lines); $j++) {
            if (preg_match('/^(#{1,' . $best['level'] . '})\s+(.+)$/', trim($lines[$j]))) {
                $end = $offsets[$j];
                break;
            }
        }

        $best['end'] = $end;
        return $best;
    }


    protected function cleanStandaloneBody(string $content, string $headingText = ''): string
    {
        $content = trim($content);
        $content = preg_replace('/^```(?:markdown|md)?\s*/i', '', $content) ?? $content;
        $content = preg_replace('/\s*```$/', '', $content) ?? $content;
        $content = trim($content);

        $lines = preg_split('/\R/', $content) ?: [];
        while (!empty($lines)) {
            $first = trim($lines[0]);
            $plainFirst = $this->normalizeHeadingText($first);
            $plainHeading = $this->normalizeHeadingText($headingText);
            $isHeading = preg_match('/^#{1,6}\s+/', $first) || preg_match('/^\d+(?:\.\d+)*\s+/', $first);
            $matchesHeading = $plainHeading !== '' && ($plainFirst === $plainHeading || str_contains($plainFirst, $plainHeading));

            if ($isHeading || $matchesHeading) {
                array_shift($lines);
                continue;
            }
            break;
        }

        $content = trim(implode("\n", $lines));
        return $this->postProcessWorkspaceBody($content, 'cleanup');
    }

    protected function postProcessWorkspaceBody(string $content, string $operation = 'rewrite_section'): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", trim($content));
        $content = preg_replace('/\[(?:Author|Authors),?\s*Year\]/i', '', $content) ?? $content;
        $content = preg_replace('/\[(?:Year|number|value|insert results|study design|sampling method|statistical software|data analysis plan)[^\]]*\]/i', '', $content) ?? $content;
        $content = preg_replace('/\.{3}\s*\[\s*truncated\s*\]\.?/i', '', $content) ?? $content;
        $content = preg_replace('/\[\s*(?:content\s*)?truncated\s*\]/i', '', $content) ?? $content;
        $content = preg_replace('/\[Context excerpt shortened here\. Do not copy this note into the thesis\.\]/i', '', $content) ?? $content;
        $content = preg_replace('/\b(?:content omitted|text omitted|continues below|continues\.\.\.)\b/i', '', $content) ?? $content;
        $content = preg_replace('/\(\s*\)/', '', $content) ?? $content;
        $content = preg_replace('/\s+([.,;:])/', '$1', $content) ?? $content;
        $content = preg_replace('/[ \t]{2,}/', ' ', $content) ?? $content;
        $content = $this->removeDuplicateParagraphs($content);
        return $this->normalizeMarkdownSpacing($content);
    }

    protected function normalizeMarkdownSpacing(string $content): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = preg_replace("/\n{3,}/", "\n\n", $content) ?? $content;
        $content = preg_replace('/\n\s+\n/', "\n\n", $content) ?? $content;
        return trim($content);
    }

    protected function finalizeDocumentContent(string $content, string $title): string
    {
        $content = $this->stripDocumentTitleFromContent($content, $title);
        $content = $this->postProcessWorkspaceBody($content, 'document');
        return $this->normalizeMarkdownSpacing($content);
    }

    protected function recentConversationText(NuruxploreProject $project, int $historyLimit = 12): string
    {
        return $project->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->orderBy('created_at', 'desc')
            ->limit(max(1, min($historyLimit, 20)))
            ->get()
            ->reverse()
            ->map(fn ($m) => strtoupper($m->role) . ': ' . $this->limitWords((string) $m->content, 250))
            ->implode("\n");
    }

    protected function referenceEvidenceFromSources(NuruxploreProject $project): string
    {
        $evidence = [];

        foreach ($project->sources()->whereNotNull('extracted_text')->limit(8)->get() as $source) {
            $text = (string) $source->extracted_text;
            if (blank($text)) {
                continue;
            }

            $referencePart = '';
            if (preg_match('/(?:references|bibliography)\s*[:\n]+(.+)$/is', $text, $m)) {
                $referencePart = trim($m[1]);
            }

            if ($referencePart === '') {
                $referencePart = $this->limitWords($text, 700);
            }

            $evidence[] = "SOURCE: {$source->title}\n" . $this->limitWords($referencePart, 900);
        }

        $text = trim(implode("\n\n---\n\n", $evidence));
        return $text !== '' ? $text : 'No source reference evidence found.';
    }


    protected function fallbackReferenceList(NuruxploreProject $project, int $minimumReferences = 0): string
    {
        $examples = array_values($project->research_profile['key_reference_examples'] ?? []);
        $lines = [];

        foreach ($examples as $example) {
            $example = trim((string) $example);
            if ($example !== '' && !$this->referenceConflictsWithProjectTopic($example, $project)) {
                $lines[] = rtrim($example, '.') . '.';
            }
        }

        // Never use one project/topic's old demo references as a global fallback.
        // When no verified references exist, create topic-aware preliminary entries
        // and mark them for verification.
        if (count($lines) < max(3, min($minimumReferences ?: 5, 8))) {
            $lines = array_merge($lines, $this->topicAwarePreliminaryReferences($project));
        }

        $lines = array_values(array_filter($this->uniqueReferenceEntries($lines), fn ($entry) => !$this->referenceConflictsWithProjectTopic($entry, $project)));

        if ($minimumReferences > count($lines)) {
            $needed = min($minimumReferences - count($lines), 30);
            for ($i = 1; $i <= $needed; $i++) {
                $lines[] = 'Additional topic-relevant source ' . $i . '. (n.d.). Reference details to verify for ' . $this->humanStudyPhrase($project->title) . '. [Verify author, year, title, publisher/journal, and URL/DOI before submission].';
            }
        }

        return $this->formatReferenceEntries($lines, $minimumReferences);
    }

    protected function topicAwarePreliminaryReferences(NuruxploreProject $project): array
    {
        $topic = strtolower($project->title . ' ' . ($project->description ?? '') . ' ' . ($project->research_question ?? ''));

        if (preg_match('/\b(hiv|aids|pmtct|mother-to-child|mtct|antenatal|pregnan|infant feeding)\b/i', $topic)) {
            return [
                'World Health Organization. (n.d.). Consolidated guidelines on HIV prevention, testing, treatment, service delivery and monitoring. World Health Organization. [Verify latest year and title before submission].',
                'Joint United Nations Programme on HIV/AIDS. (n.d.). Global AIDS update. UNAIDS. [Verify latest year and title before submission].',
                'Ministry of Health Tanzania. (n.d.). National guidelines for the management of HIV and AIDS. Government of Tanzania. [Verify latest year and title before submission].',
                'Avert. (n.d.). Prevention of mother-to-child transmission of HIV. Avert. [Verify publication details before submission].',
            ];
        }

        if (preg_match('/\b(social media|facebook|twitter|instagram|whatsapp|digital|online|civic|youth|political participation|engagement)\b/i', $topic)) {
            return [
                'Boyd, D. M., & Ellison, N. B. (2007). Social network sites: Definition, history, and scholarship. Journal of Computer-Mediated Communication, 13(1), 210–230. [Verify details before submission].',
                'Boulianne, S. (2015). Social media use and participation: A meta-analysis of current research. Information, Communication & Society, 18(5), 524–538. [Verify details before submission].',
                'Loader, B. D., Vromen, A., & Xenos, M. A. (2014). The networked young citizen: Social media, political participation, and civic engagement. Information, Communication & Society, 17(2), 143–150. [Verify details before submission].',
                'Xenos, M., Vromen, A., & Loader, B. D. (2014). The great equalizer? Patterns of social media use and youth political engagement. Information, Communication & Society, 17(2), 151–167. [Verify details before submission].',
                'Tanzania Communications Regulatory Authority. (n.d.). Communications statistics report. TCRA. [Verify latest report year and title before submission].',
                'United Republic of Tanzania. (n.d.). National ICT policy or digital transformation policy document. Government of Tanzania. [Verify latest title and year before submission].',
                'Norris, P. (2001). Digital divide: Civic engagement, information poverty, and the Internet worldwide. Cambridge University Press. [Verify details before submission].',
                'Putnam, R. D. (2000). Bowling alone: The collapse and revival of American community. Simon & Schuster. [Verify details before submission].',
            ];
        }

        if (preg_match('/\b(mobile banking|mobile money|financial inclusion|fintech|small business|sme|entrepreneur)\b/i', $topic)) {
            return [
                'Bank of Tanzania. (n.d.). Financial stability report. Bank of Tanzania. [Verify latest report year before submission].',
                'FinScope Tanzania. (n.d.). Financial inclusion insights report. [Verify latest report year and publisher before submission].',
                'World Bank. (n.d.). Global Findex database report. World Bank. [Verify latest edition before submission].',
                'GSMA. (n.d.). State of the industry report on mobile money. GSMA. [Verify latest edition before submission].',
                'Tanzania Communications Regulatory Authority. (n.d.). Communications statistics report. TCRA. [Verify latest report year before submission].',
            ];
        }

        if (preg_match('/\b(teacher|education|school|student|academic performance|secondary school)\b/i', $topic)) {
            return [
                'Ministry of Education, Science and Technology. (n.d.). Education sector performance report. Government of Tanzania. [Verify latest report year before submission].',
                'UNESCO. (n.d.). Global education monitoring report. UNESCO. [Verify latest report year before submission].',
                'World Bank. (n.d.). World development report on education or learning. World Bank. [Verify latest title and year before submission].',
                'Hattie, J. (2009). Visible learning: A synthesis of over 800 meta-analyses relating to achievement. Routledge. [Verify details before submission].',
            ];
        }

        return [
            'Creswell, J. W., & Creswell, J. D. (2018). Research design: Qualitative, quantitative, and mixed methods approaches. SAGE Publications. [Verify edition before submission].',
            'Bryman, A. (2016). Social research methods. Oxford University Press. [Verify edition before submission].',
            'Kothari, C. R. (2004). Research methodology: Methods and techniques. New Age International. [Verify details before submission].',
            'United Republic of Tanzania. (n.d.). Relevant national policy, strategy, or statistical report on the study topic. Government of Tanzania. [Verify exact title and year before submission].',
            'World Bank. (n.d.). Relevant development indicators or sector report. World Bank. [Verify exact title and year before submission].',
        ];
    }

    protected function referenceConflictsWithProjectTopic(string $reference, NuruxploreProject $project): bool
    {
        $topic = strtolower($project->title . ' ' . ($project->description ?? '') . ' ' . ($project->research_question ?? ''));
        $referenceLower = strtolower($reference);

        $isHivTopic = preg_match('/\b(hiv|aids|pmtct|mother-to-child|mtct|antenatal|pregnan|infant)\b/i', $topic) === 1;
        $isHivReference = preg_match('/\b(hiv|aids|pmtct|mother-to-child|mtct|antenatal|pregnan|infant feeding|unaids|avert)\b/i', $referenceLower) === 1;
        if (!$isHivTopic && $isHivReference) {
            return true;
        }

        $isSocialTopic = preg_match('/\b(social media|civic|digital|online|youth|political participation)\b/i', $topic) === 1;
        $isMedicalReference = preg_match('/\b(clinical|patient|disease|treatment|therapy|hospital|maternal|infant|hiv|aids)\b/i', $referenceLower) === 1;
        if ($isSocialTopic && $isMedicalReference) {
            return true;
        }

        return false;
    }

    protected function requestedReferenceCount(string $instruction): int
    {
        if (preg_match('/(?:at least|minimum of|not less than)\s+(\d{1,3})\s+(?:references|sources|citations)/i', $instruction, $m)) {
            return max(0, min(60, (int) $m[1]));
        }

        if (preg_match('/(?:add|include|generate|create|make).*?(\d{1,3})\s+(?:references|sources|citations)/i', $instruction, $m)) {
            return max(0, min(60, (int) $m[1]));
        }

        return 0;
    }

    protected function normalizeReferenceListFormat(string $content, NuruxploreProject $project, int $minimumReferences = 0): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", trim($content));
        $content = preg_replace('/^#+\s*references\s*/i', '', $content) ?? $content;
        $content = preg_replace('/^references\s*:?\s*/i', '', $content) ?? $content;
        $content = trim($content);

        $verifyEntries = [];
        if (preg_match('/(?:^|\n)#{0,3}\s*References to verify(?: before submission)?\s*:?\s*(.+)$/is', $content, $m)) {
            $pos = strpos($content, $m[0]);
            $before = $pos === false ? $content : trim(substr($content, 0, $pos));
            $verifyEntries = $this->extractReferenceEntries(trim($m[1]));
            $content = $before;
        }

        $entries = $this->extractReferenceEntries($content);
        if (count($entries) < 2) {
            $entries = $this->splitReferenceParagraph($content);
        }

        if (count($entries) < max(1, min($minimumReferences, 5))) {
            $fallback = $this->extractReferenceEntries($this->fallbackReferenceList($project, $minimumReferences));
            $entries = array_merge($entries, $fallback);
        }

        $entries = array_values(array_filter($this->uniqueReferenceEntries($entries), fn ($entry) => !$this->referenceConflictsWithProjectTopic($entry, $project)));
        if (count($entries) < max(1, min($minimumReferences ?: 5, 5))) {
            $entries = array_values(array_filter(array_merge($entries, $this->extractReferenceEntries($this->fallbackReferenceList($project, $minimumReferences))), fn ($entry) => !$this->referenceConflictsWithProjectTopic($entry, $project)));
            $entries = $this->uniqueReferenceEntries($entries);
        }
        $mainList = $this->formatReferenceEntries($entries, $minimumReferences);

        if (!empty($verifyEntries)) {
            $mainList .= "\n\n### References to verify before submission\n\n" . $this->formatReferenceEntries($verifyEntries, 0);
        }

        return trim($mainList);
    }

    protected function extractReferenceEntries(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        if ($text === '') {
            return [];
        }

        $lines = preg_split('/\n+/', $text) ?: [];
        $entries = [];
        $buffer = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                if ($buffer !== '') {
                    $entries[] = trim($buffer);
                    $buffer = '';
                }
                continue;
            }

            if (preg_match('/^#{1,6}\s+/', $line)) {
                if ($buffer !== '') {
                    $entries[] = trim($buffer);
                    $buffer = '';
                }
                continue;
            }

            $line = preg_replace('/^[-*•]\s+/u', '', $line) ?? $line;
            $line = preg_replace('/^\d+[\.)]\s+/u', '', $line) ?? $line;

            if ($this->looksLikeReferenceStart($line) && $buffer !== '') {
                $entries[] = trim($buffer);
                $buffer = $line;
            } else {
                $buffer = trim($buffer . ' ' . $line);
            }
        }

        if ($buffer !== '') {
            $entries[] = trim($buffer);
        }

        return $this->uniqueReferenceEntries($entries);
    }

    protected function splitReferenceParagraph(string $paragraph): array
    {
        $paragraph = preg_replace('/\s+/u', ' ', trim($paragraph)) ?? trim($paragraph);
        if ($paragraph === '') {
            return [];
        }

        $patterns = [
            '/\s+(?=(?:Avert|UNAIDS|UNICEF|World Health Organization|Ministry of Health|Tanzania Ministry of Health|Centers for Disease Control and Prevention|Joint United Nations Programme on HIV\/AIDS)\.\s*\()/u',
            '/\s+(?=[A-Z][A-Za-z’\'\-]+,\s+[A-Z](?:\.|[A-Za-z])[A-Za-z\.\s,&-]*\(\d{4}|n\.d\.\))/u',
        ];

        $parts = [$paragraph];
        foreach ($patterns as $pattern) {
            $newParts = [];
            foreach ($parts as $part) {
                $newParts = array_merge($newParts, preg_split($pattern, $part) ?: [$part]);
            }
            $parts = $newParts;
        }

        return $this->uniqueReferenceEntries(array_filter(array_map('trim', $parts)));
    }

    protected function looksLikeReferenceStart(string $line): bool
    {
        return (bool) preg_match('/^(?:Avert|UNAIDS|UNICEF|World Health Organization|Ministry of Health|Tanzania Ministry of Health|Centers for Disease Control and Prevention|Joint United Nations Programme on HIV\/AIDS|[A-Z][A-Za-z’\'\-]+,\s+[A-Z])/u', $line);
    }

    protected function uniqueReferenceEntries(array $entries): array
    {
        $unique = [];
        $seen = [];

        foreach ($entries as $entry) {
            $entry = trim(preg_replace('/\s+/u', ' ', (string) $entry) ?? (string) $entry);
            $entry = trim($entry, " \t\n\r\0\x0B-•*");
            if ($entry === '' || str_word_count($entry) < 3) {
                continue;
            }

            if (preg_match('/^(studies have shown|according to|the ministry|the world health organization has emphasized)/i', $entry) && !preg_match('/\(\d{4}|n\.d\.\)/i', $entry)) {
                continue;
            }

            $entry = rtrim($entry, '.') . '.';
            $key = mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', $entry) ?? $entry);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $entry;
        }

        return $unique;
    }

    protected function formatReferenceEntries(array $entries, int $minimumReferences = 0): string
    {
        $entries = $this->uniqueReferenceEntries($entries);

        $lines = [];
        foreach ($entries as $index => $entry) {
            $lines[] = ($index + 1) . '. ' . $entry;
        }

        if ($minimumReferences > 0 && count($entries) < $minimumReferences) {
            $lines[] = '';
            $lines[] = '### References to verify before submission';
            $lines[] = '';
            $lines[] = 'The uploaded proposal/source evidence did not provide enough complete bibliographic details to safely produce ' . $minimumReferences . ' verified APA7 references. Add the missing source pages or bibliography details, then ask NuruXplore AI to regenerate this section.';
        }

        return implode("\n", $lines);
    }

    protected function defaultPlannedResultsTable(NuruxploreProject $project): string
    {
        return <<<MD
| Study Objective | Key Variable | Planned Analysis | Expected Presentation |
|---|---|---|---|
| Determine knowledge of MTCT of HIV | Knowledge score/items | Frequencies and percentages | Table of knowledge levels |
| Assess knowledge of PMTCT methods | Awareness of HIV testing, ART, infant feeding, ANC follow-up | Frequencies and percentages | Table of correct responses |
| Identify sources of information | Source categories such as health workers, media, family, clinic visits | Frequencies and percentages | Ranked source table |
| Examine associated factors | Education, ANC attendance, residence, partner support | Chi-square/association tests | Association table |
MD;
    }


    protected function handleWorkspaceReview(NuruxploreProject $project, string $instruction, string $operation, int $historyLimit = 12): array
    {
        $systemPrompt = 'You are NuruXplore AI, an academic thesis supervisor and editor. Give a useful review report. Do not modify the document.';
        $reviewFocus = $this->operationInstruction($operation);
        $userPrompt = <<<EOT
PROJECT CONTEXT:
{$this->projectChatContext($project)}

RECENT WORKSPACE CHAT:
{$this->recentConversationText($project, $historyLimit)}

USER REQUEST:
{$instruction}

REVIEW TYPE:
{$operation}

FOCUS:
{$reviewFocus}

RULES:
- Do not claim to run Turnitin or database plagiarism detection.
- For plagiarism/originality, provide a plagiarism-risk and originality review only.
- Give practical, prioritized recommendations.
- Mention exact sections when possible.
- If the user wants you to apply fixes, tell them which command to send next.
EOT;

        $result = $this->groq->callGroqAPI($systemPrompt, $userPrompt, 1800, null, $this->groq->writingModel(), 0.25);
        return [
            'action' => 'review',
            'message' => $result['success'] ? trim((string) $result['content']) : ($result['error'] ?? 'I could not complete the review.'),
            'tokens_used' => $result['tokens_used'] ?? 0,
            'model' => $result['model'] ?? null,
            'document_updated' => false,
            'edit_type' => $operation,
        ];
    }

    protected function cleanupWholeDocument(NuruxploreProject $project, string $instruction, int $historyLimit = 12): array
    {
        $document = trim((string) $project->content);
        $cleaned = $this->finalizeDocumentContent($document, $project->title);
        $cleaned = $this->fixDuplicateHeadingLines($cleaned);

        $project->update([
            'content' => $cleaned,
            'word_count' => str_word_count(strip_tags($cleaned)),
            'status' => 'draft',
            'last_edited_at' => now(),
        ]);

        $this->createVersion($project->fresh(), 'Workspace AI cleaned document formatting', 'ai_revision', [
            'instruction' => $instruction,
            'operation' => 'cleanup_document',
        ]);

        return [
            'action' => 'cleanup_document',
            'message' => 'Done. I cleaned the document formatting, removed obvious placeholders/truncation markers, normalized spacing, and preserved the thesis structure.',
            'tokens_used' => 0,
            'document_updated' => true,
            'edit_type' => 'cleanup_document',
        ];
    }

    protected function repairBrokenDocumentSections(NuruxploreProject $project, string $instruction, string $operation, int $historyLimit = 12): array
    {
        $document = trim((string) $project->content);
        $ranges = $this->findSectionsWithBadArtifacts($document);

        if (empty($ranges)) {
            $cleaned = $this->finalizeDocumentContent($document, $project->title);
            if ($cleaned !== $document) {
                $project->update([
                    'content' => $cleaned,
                    'word_count' => str_word_count(strip_tags($cleaned)),
                    'last_edited_at' => now(),
                ]);
                return [
                    'action' => 'repair_document',
                    'message' => 'Done. I cleaned visible placeholder/truncation markers from the document. I did not find a section that needed AI regeneration.',
                    'tokens_used' => 0,
                    'document_updated' => true,
                    'edit_type' => $operation,
                ];
            }

            return [
                'action' => 'chat',
                'message' => 'I did not find visible truncated markers or placeholder artifacts in the current thesis content.',
                'tokens_used' => 0,
                'document_updated' => false,
                'edit_type' => $operation,
            ];
        }

        $tokens = 0;
        $updated = $document;
        $fixed = 0;

        // Work from bottom to top so string offsets remain valid.
        usort($ranges, fn ($a, $b) => $b['heading_start'] <=> $a['heading_start']);
        foreach (array_slice($ranges, 0, 5) as $range) {
            $currentSection = trim(substr($updated, $range['body_start'], $range['end'] - $range['body_start']));
            $repair = $this->generateReplacementSectionBody($project, $instruction, $range, $currentSection, $historyLimit, 'fix_truncation');
            $tokens += (int) ($repair['tokens_used'] ?? 0);
            if (!$repair['success']) {
                continue;
            }
            $replacement = trim((string) $repair['content']);
            if ($replacement === '' || $this->containsBadArtifacts($replacement)) {
                continue;
            }
            $updated = substr($updated, 0, $range['body_start']) . $replacement . "\n\n" . substr($updated, $range['end']);
            $fixed++;
        }

        if ($fixed === 0) {
            return [
                'action' => 'chat',
                'message' => 'I found broken/truncated section text, but the AI repair output was not safe to save. Try asking me to rewrite the specific section by name.',
                'tokens_used' => $tokens,
                'document_updated' => false,
                'edit_type' => $operation,
            ];
        }

        $updated = $this->finalizeDocumentContent($updated, $project->title);
        $project->update([
            'content' => $updated,
            'word_count' => str_word_count(strip_tags($updated)),
            'status' => 'draft',
            'last_edited_at' => now(),
        ]);

        $this->createVersion($project->fresh(), 'Workspace AI repaired broken sections', 'ai_revision', [
            'instruction' => $instruction,
            'operation' => $operation,
            'sections_fixed' => $fixed,
            'tokens_used' => $tokens,
        ]);

        return [
            'action' => 'repair_document',
            'message' => 'Done. I repaired ' . $fixed . ' section(s) containing truncated or placeholder text and preserved the rest of the thesis unchanged.',
            'tokens_used' => $tokens,
            'document_updated' => true,
            'edit_type' => $operation,
        ];
    }

    protected function findSectionsWithBadArtifacts(string $document): array
    {
        $headings = $this->documentHeadings($document);
        $ranges = [];
        foreach ($headings as $heading) {
            $body = substr($document, $heading['body_start'], $heading['end'] - $heading['body_start']);
            if ($this->containsBadArtifacts($body)) {
                $ranges[] = $heading;
            }
        }
        return $ranges;
    }

    protected function documentHeadings(string $document): array
    {
        $lines = preg_split('/\R/', $document) ?: [];
        $offsets = [];
        $cursor = 0;
        foreach ($lines as $line) {
            $offsets[] = $cursor;
            $cursor += strlen($line) + 1;
        }

        $headings = [];
        foreach ($lines as $i => $line) {
            if (!preg_match('/^(#{1,6})\s+(.+)$/', trim($line), $m)) {
                continue;
            }
            $level = strlen($m[1]);
            $end = strlen($document);
            for ($j = $i + 1; $j < count($lines); $j++) {
                if (preg_match('/^(#{1,' . $level . '})\s+(.+)$/', trim($lines[$j]))) {
                    $end = $offsets[$j];
                    break;
                }
            }
            $headings[] = [
                'score' => 100,
                'line_index' => $i,
                'level' => $level,
                'heading_text' => trim($m[2]),
                'heading_start' => $offsets[$i],
                'body_start' => $offsets[$i] + strlen($line) + 1,
                'end' => $end,
            ];
        }
        return $headings;
    }

    protected function validateWorkspaceGeneratedContent(string $content, string $operation): array
    {
        if (trim($content) === '') {
            return ['valid' => false, 'message' => 'The AI returned empty content, so I did not change the thesis.'];
        }

        if ($this->containsBadArtifacts($content)) {
            return ['valid' => false, 'message' => 'The AI output still contained placeholders or truncation markers, so I did not save it. Please try again with a more specific section instruction.'];
        }

        if (in_array($operation, ['generate_references', 'format_references', 'expand_references', 'repair_references_section'], true)) {
            if (!preg_match('/^\s*\d+[\.)]\s+/m', $content)) {
                return ['valid' => false, 'message' => 'The References output was not in list format, so I did not save it.'];
            }
        }

        return ['valid' => true, 'message' => 'OK'];
    }

    protected function containsBadArtifacts(string $content): bool
    {
        return (bool) preg_match('/\[Context excerpt shortened|\[\s*(?:author,?\s*year|year|insert results|statistical software|study design|sampling method|content\s*)?truncated\s*\]|\.\.\.\s*\[\s*truncated\s*\]|\[\s*(?:author,?\s*year|year|insert results|statistical software|study design|sampling method)\s*\]/i', $content);
    }

    protected function retryCleanSection(NuruxploreProject $project, string $instruction, string $operation, array $sectionRange, string $badContent): array
    {
        $systemPrompt = 'You repair broken academic section text. Return only clean section body. No headings.';
        $userPrompt = <<<EOT
PROJECT CONTEXT:
{$this->projectChatContext($project)}

TARGET SECTION HEADING:
{$sectionRange['heading_text']}

BROKEN SECTION BODY:
{$this->safeExcerpt($badContent, 1600)}

USER INSTRUCTION:
{$instruction}

RULES:
- Remove all placeholders and truncation markers.
- Complete any unfinished sentence.
- Preserve the meaning and approved study facts.
- Return the clean full section body only, no heading.
EOT;
        $result = $this->groq->callGroqAPI($systemPrompt, $userPrompt, 2200, null, $this->groq->writingModel(), 0.18);
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error'] ?? 'Repair failed.', 'tokens_used' => $result['tokens_used'] ?? 0];
        }
        $content = $this->postProcessWorkspaceBody($this->cleanStandaloneBody((string) $result['content'], $sectionRange['heading_text']), $operation);
        return [
            'success' => !blank($content) && !$this->containsBadArtifacts($content),
            'content' => $content,
            'tokens_used' => $result['tokens_used'] ?? 0,
            'model' => $result['model'] ?? null,
        ];
    }

    protected function operationInstruction(string $operation): string
    {
        return match ($operation) {
            'update_project_title' => 'Create or update the academic project title only. Do not rewrite the document.',
            'suggest_titles' => 'Suggest several focused academic titles without changing the document unless asked to apply one.',
            'expand_section' => 'Expand the section with relevant academic detail, but do not add unsupported facts or fake citations.',
            'shorten_section' => 'Condense the section while preserving the core meaning and approved study facts.',
            'humanize_section' => 'Make the writing more natural, less robotic, and easier to read while keeping academic tone.',
            'professionalize_section', 'academic_tone_section' => 'Improve formality, precision, academic tone, and paragraph flow.',
            'simplify_section' => 'Simplify wording while preserving academic correctness.',
            'remove_repetition' => 'Remove repeated ideas, merge overlapping sentences, and improve flow.',
            'rewrite_section' => 'Rewrite the section for clarity, flow, and academic quality.',
            'insert_table' => 'Add a relevant academic table in the correct location.',
            'insert_chart_table' => 'Create chart-ready table data and a figure title/placeholder. Do not create fake data.',
            'add_in_text_citations' => 'Add citations only where reference evidence is available. Do not invent fake citations.',
            'citation_gap_review' => 'Identify claims that need citations and suggest where sources are required.',
            'plagiarism_risk_review' => 'Review originality risk, generic phrasing, missing citations, and overly common wording. Do not claim database matching.',
            'grammar_review' => 'Check grammar, sentence structure, punctuation, and readability.',
            'consistency_check' => 'Check whether title, objectives, methodology, findings plan, and recommendations align.',
            default => 'Improve the section according to the user instruction while preserving factual consistency.',
        };
    }


    public function generateAcademicTitleFromPrompt(string $prompt, string $type = 'proposal'): array
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            return ['success' => false, 'title' => null, 'error' => 'Prompt is empty.'];
        }

        $label = match (strtolower($type)) {
            'proposal' => 'research proposal',
            'dissertation' => 'dissertation',
            default => 'thesis',
        };

        $systemPrompt = 'You create focused academic research titles. Return ONLY valid JSON.';
        $userPrompt = <<<EOT
USER TOPIC/PROMPT:
{$prompt}

DOCUMENT TYPE:
{$label}

Create one polished academic title suitable for a university {$label}.
Rules:
- Do not start with "Draft", "Proposal about", or "Thesis on".
- Keep it specific, formal, and researchable.
- Include study area/population if clearly provided.
- Avoid colon-heavy titles unless necessary.
Return JSON:
{"title":"...","reason":"..."}
EOT;

        $result = $this->groq->jsonCall($systemPrompt, $userPrompt, 700, $this->groq->fastModel());
        if (($result['success'] ?? false) && !blank($result['json']['title'] ?? null)) {
            return [
                'success' => true,
                'title' => $this->sanitizeAcademicTitle((string) $result['json']['title'], $prompt),
                'reason' => $result['json']['reason'] ?? null,
                'tokens_used' => $result['tokens_used'] ?? 0,
                'model' => $result['model'] ?? null,
            ];
        }

        return [
            'success' => true,
            'title' => $this->fallbackAcademicTitle($prompt, $type),
            'reason' => 'Fallback title generated deterministically.',
            'tokens_used' => $result['tokens_used'] ?? 0,
            'model' => $result['model'] ?? null,
        ];
    }

    public function detectResearchProjectRequest(string $message): array
    {
        $msg = trim($message);
        $lower = strtolower($msg);

        $wantsGeneration = preg_match('/\b(generate|draft|write|create|prepare|make)\b/i', $msg);
        $type = null;
        if (preg_match('/\b(proposal|research proposal)\b/i', $msg)) {
            $type = 'proposal';
        } elseif (preg_match('/\b(thesis|dissertation)\b/i', $msg)) {
            $type = str_contains($lower, 'dissertation') ? 'dissertation' : 'thesis';
        }

        if (!$wantsGeneration || !$type) {
            return ['should_create' => false];
        }

        $title = $this->generateAcademicTitleFromPrompt($msg, $type);

        return [
            'should_create' => true,
            'type' => $type,
            'prompt' => $msg,
            'title' => $title['title'] ?? $this->fallbackAcademicTitle($msg, $type),
            'title_tokens_used' => $title['tokens_used'] ?? 0,
        ];
    }

    protected function handleProjectTitleOperation(NuruxploreProject $project, string $instruction, string $operation): array
    {
        if ($operation === 'suggest_titles') {
            $systemPrompt = 'You suggest academic research titles. Return concise Markdown list only.';
            $prompt = <<<EOT
CURRENT TITLE: {$project->title}
PROJECT TYPE: {$project->type}
PROJECT PROMPT/DESCRIPTION: {$project->original_prompt} {$project->description}
USER REQUEST: {$instruction}

Suggest 5 strong academic titles. Do not update anything. Return a numbered list only.
EOT;
            $result = $this->groq->callGroqAPI($systemPrompt, $prompt, 900, null, $this->groq->fastModel(), 0.35);
            return [
                'action' => 'suggest_titles',
                'message' => $result['success'] ? trim((string) $result['content']) : 'I could not suggest titles right now.',
                'tokens_used' => $result['tokens_used'] ?? 0,
                'model' => $result['model'] ?? null,
                'document_updated' => false,
                'edit_type' => 'suggest_titles',
            ];
        }

        $sourcePrompt = trim($project->original_prompt ?: $project->description ?: $project->title);
        $titleResult = $this->generateAcademicTitleFromPrompt($sourcePrompt . "\n\nUser title instruction: " . $instruction, $project->type);
        $newTitle = $this->sanitizeAcademicTitle((string) ($titleResult['title'] ?? $project->title), $project->title);

        if ($newTitle === '' || mb_strlen($newTitle) < 10) {
            return [
                'action' => 'chat',
                'message' => 'I could not create a reliable new title from that instruction. Please add a little more detail.',
                'tokens_used' => $titleResult['tokens_used'] ?? 0,
                'document_updated' => false,
                'edit_type' => 'update_project_title',
            ];
        }

        $oldTitle = $project->title;
        $profile = $project->research_profile ?: [];
        if (is_array($profile)) {
            $profile['title'] = $newTitle;
        }
        $settings = $project->generation_settings ?: [];
        $settings['title_history'][] = [
            'old_title' => $oldTitle,
            'new_title' => $newTitle,
            'instruction' => $instruction,
            'changed_at' => now()->toISOString(),
        ];

        $project->update([
            'title' => $newTitle,
            'title_ai_generated' => true,
            'research_profile' => $profile ?: $project->research_profile,
            'generation_settings' => $settings,
            'last_edited_at' => now(),
        ]);

        $this->createVersion($project->fresh(), 'Workspace AI updated project title', 'ai_revision', [
            'old_title' => $oldTitle,
            'new_title' => $newTitle,
            'instruction' => $instruction,
        ]);

        return [
            'action' => 'edit_title',
            'message' => "Done. I updated the project title to:\n\n**{$newTitle}**",
            'tokens_used' => $titleResult['tokens_used'] ?? 0,
            'model' => $titleResult['model'] ?? null,
            'document_updated' => true,
            'target_section' => 'Project title',
            'edit_type' => 'update_project_title',
            'new_title' => $newTitle,
        ];
    }

    protected function sanitizeAcademicTitle(string $title, string $fallback): string
    {
        $title = trim(preg_replace('/["“”]+/', '', $title) ?? $title);
        $title = preg_replace('/^(draft|write|generate|create|prepare)\s+(a\s+)?(proposal|thesis|dissertation)\s+(on|about|for)\s+/i', '', $title) ?? $title;
        $title = trim($title, " \t\n\r\0\x0B.-");
        return Str::limit($title !== '' ? $title : $fallback, 240, '');
    }

    protected function fallbackAcademicTitle(string $prompt, string $type = 'proposal'): string
    {
        $clean = strtolower($prompt);
        $clean = preg_replace('/\b(draft|write|generate|create|prepare|make|a|an|the|proposal|thesis|dissertation|on|about|for)\b/i', ' ', $clean) ?? $clean;
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? $clean);

        if (str_contains($clean, 'social media') && str_contains($clean, 'tanzania')) {
            return 'Assessment of the Influence of Social Media Use on Socioeconomic Outcomes in Tanzania';
        }

        return 'Assessment of ' . Str::headline(Str::limit($clean !== '' ? $clean : $prompt, 140, ''));
    }

    protected function removeDuplicateParagraphs(string $content): string
    {
        $blocks = preg_split('/\n{2,}/', trim($content)) ?: [];
        $seen = [];
        $kept = [];
        foreach ($blocks as $block) {
            $key = mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', '', strip_tags($block)) ?? $block);
            if ($key !== '' && isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $kept[] = trim($block);
        }
        return implode("\n\n", $kept);
    }

    protected function fixDuplicateHeadingLines(string $content): string
    {
        $lines = preg_split('/\R/', $content) ?: [];
        $out = [];
        $prevHeading = null;
        foreach ($lines as $line) {
            $trim = trim($line);
            if (preg_match('/^#{1,6}\s+(.+)$/', $trim, $m)) {
                $norm = $this->normalizeHeadingText($m[1]);
                if ($prevHeading === $norm) {
                    continue;
                }
                $prevHeading = $norm;
            } elseif ($trim !== '') {
                $prevHeading = null;
            }
            $out[] = $line;
        }
        return trim(implode("\n", $out));
    }

    protected function safeExcerpt(string $text, int $wordLimit): string
    {
        $words = preg_split('/\s+/', trim(strip_tags($text)));
        if (!$words || count($words) <= $wordLimit) {
            return $text;
        }
        return implode(' ', array_slice($words, 0, $wordLimit)) . "\n\n[Context excerpt shortened here. Do not copy this note into the thesis.]";
    }

    protected function syncSectionModelFromDocumentEdit(NuruxploreProject $project, array $sectionRange, string $replacementBody): void
    {
        $heading = $this->normalizeHeadingText($sectionRange['heading_text'] ?? '');
        if ($heading === '') {
            return;
        }

        $sections = $project->sections()->get();
        foreach ($sections as $section) {
            $candidate = $this->normalizeHeadingText(trim(($section->section_number ? $section->section_number . ' ' : '') . $section->title));
            $titleOnly = $this->normalizeHeadingText($section->title);
            if ($candidate === $heading || $titleOnly === $heading || str_contains($heading, $titleOnly)) {
                $section->update([
                    'content' => $replacementBody,
                    'status' => 'drafted',
                    'ai_metadata' => array_merge($section->ai_metadata ?? [], [
                        'last_workspace_edit_at' => now()->toISOString(),
                    ]),
                ]);
                return;
            }
        }
    }

    protected function boolWord(bool $value): string
    {
        return $value ? 'YES' : 'NO';
    }

    protected function minimalResearchProfile(NuruxploreProject $project, string $topic, string $type): array
    {
        return $this->normalizeResearchProfile([
            'title' => $topic ?: $project->title,
            'document_type' => $type ?: $project->type,
            'citation_style' => $project->citation_style,
            'background' => [],
            'problem_statement' => [],
            'general_objective' => null,
            'specific_objectives' => [],
            'research_questions' => $project->research_question ? [$project->research_question] : [],
            'hypotheses' => [],
            'methodology' => [],
            'variables' => [],
            'dataset_summary' => [],
            'expected_or_actual_findings' => [],
            'limitations' => [],
            'key_reference_examples' => [],
            'references_available' => false,
            'missing_information' => ['No proposal/source document was uploaded. Generated from user topic only.'],
            'generation_rules' => ['Do not invent precise sample size, study area, findings, or citations unless supplied by the user.'],
            'dataset_uploaded' => false,
        ], $project);
    }

    protected function replaceSectionsFromOutline(NuruxploreProject $project, array $outline): void
    {
        $project->sections()->delete();

        $outline = $this->normalizeOutline($outline, $project);
        $order = 1;
        $chapterNumber = 1;

        foreach ($outline as $chapter) {
            $chapterTitle = $chapter['title'] ?? 'Untitled Chapter';
            $subsections = $chapter['subsections'] ?? [];

            $parent = $project->sections()->create([
                'title' => $chapterTitle,
                'section_number' => (string) $chapterNumber,
                'order' => $order++,
                'status' => 'outlined',
            ]);

            foreach (array_values($subsections) as $i => $subsection) {
                $title = is_array($subsection) ? ($subsection['title'] ?? 'Untitled Section') : (string) $subsection;
                $parent->children()->create([
                    'project_id' => $project->id,
                    'title' => $title,
                    'section_number' => $chapterNumber . '.' . ($i + 1),
                    'order' => $i + 1,
                    'status' => 'outlined',
                ]);
            }

            $chapterNumber++;
        }
    }

    protected function profileChunkPrompt(NuruxploreProject $project, string $chunk, int $index, int $total, bool $compact = false): string
    {
        $limit = $compact ? 'Return very compact JSON. Use short phrases only.' : 'Return compact JSON.';

        return <<<EOT
Project title: {$project->title}
Project type: {$project->type}
Citation style: {$project->citation_style}
Chunk {$index} of {$total}

Extract only facts present in the chunk. {$limit}
IMPORTANT: Do NOT return a full bibliography/reference list. If this chunk contains references, return only up to 3 short reference examples and set references_available=true.

Return exactly this JSON shape:
{
  "title": null,
  "background_points": [],
  "problem_statement_points": [],
  "general_objective": null,
  "specific_objectives": [],
  "research_questions": [],
  "hypotheses": [],
  "methodology": {
    "approach": null,
    "design": null,
    "study_area": null,
    "population": null,
    "sample_size": null,
    "sampling": null,
    "data_collection": [],
    "data_analysis": [],
    "ethical_considerations": []
  },
  "variables": [],
  "dataset_summary": [],
  "limitations": [],
  "references_available": false,
  "reference_count_estimate": null,
  "key_reference_examples": []
}

CHUNK:
{$chunk}
EOT;
    }

    protected function mergeProfilePrompt(NuruxploreProject $project, array $summaries, array $warnings = [], array $sourceDigest = []): string
    {
        return <<<EOT
Project title: {$project->title}
Project type: {$project->type}
Citation style: {$project->citation_style}

Merge these chunk extractions into one profile. Remove duplicates. Do not invent missing facts.
Do not create long reference lists. Keep maximum 12 key_reference_examples.

Return JSON:
{
  "title": "...",
  "document_type": "thesis|proposal|dissertation|...",
  "citation_style": "{$project->citation_style}",
  "background": [],
  "problem_statement": [],
  "general_objective": null,
  "specific_objectives": [],
  "research_questions": [],
  "hypotheses": [],
  "methodology": {
    "approach": null,
    "design": null,
    "study_area": null,
    "population": null,
    "sample_size": null,
    "sampling": null,
    "data_collection": [],
    "data_analysis": [],
    "ethical_considerations": []
  },
  "variables": [],
  "dataset_summary": [],
  "expected_or_actual_findings": [],
  "limitations": [],
  "references_available": false,
  "key_reference_examples": [],
  "missing_information": [],
  "extraction_warnings": [],
  "source_digest": [],
  "generation_rules": [
    "Do not change approved objectives.",
    "Do not change approved methodology.",
    "Do not invent findings unsupported by uploaded data.",
    "Do not use fake citation placeholders."
  ]
}

SOURCE DIGEST:
{$this->json($sourceDigest)}

EXTRACTION WARNINGS:
{$this->json($warnings)}

CHUNK EXTRACTIONS:
{$this->json($summaries)}
EOT;
    }

    protected function outlinePrompt(NuruxploreProject $project): string
    {
        $datasetRule = $this->hasDataset($project)
            ? 'Dataset appears available. Results/Findings may include findings by objective.'
            : 'No dataset is available. Do not create subsections that require actual findings. Use planned data presentation / expected findings framework only.';

        return <<<EOT
APPROVED RESEARCH PROFILE:
{$this->json($project->research_profile)}

Generate an outline for a {$project->type}. Return JSON only:
{
  "chapters": [
    {"title":"Abstract", "subsections":[]},
    {"title":"Introduction", "subsections":["Background", "Problem Statement", "Research Objectives", "Research Questions", "Significance of the Study"]}
  ]
}

Rules:
- Match the profile objectives and methodology.
- Abstract must have no subsections.
- References must have no subsections.
- {$datasetRule}
- For thesis/dissertation include: Abstract, Introduction, Literature Review, Methodology, Results/Findings or Planned Findings Framework, Discussion, Conclusion and Recommendations, References.
- For proposal include: Abstract, Introduction, Objectives, Research Questions, Literature Review, Methodology, Expected Outcomes, Timeline, References.
- Keep each chapter to 3-5 useful subsections except Abstract and References.
EOT;
    }


    /**
     * Deterministic safety fallback for small required sections.
     *
     * Long document generation should not fail because the model returned an empty
     * body for structural sections like General Objective, Specific Objectives,
     * Research Questions, Work Plan, or Planned Data Presentation. This fallback
     * keeps the job moving while clearly using approved project/profile facts.
     */
    protected function deterministicSectionFallback(NuruxploreSection $section, ?string $extraInstruction = null, ?string $reason = null): string
    {
        $project = $section->project;
        $title = strtolower(trim($section->title));
        $parent = strtolower((string) ($section->parent?->title ?? ''));
        $combined = trim($parent . ' ' . $title);
        $profile = is_array($project->research_profile) ? $project->research_profile : [];
        $studyTitle = trim((string) ($profile['title'] ?? $project->title));
        $studyPhrase = $this->humanStudyPhrase($studyTitle);
        $studyArea = trim((string) data_get($profile, 'methodology.study_area', 'the selected study area'));
        $population = trim((string) data_get($profile, 'methodology.population', 'the target population'));

        $generalObjective = trim((string) ($profile['general_objective'] ?? ''));
        $specificObjectives = array_values(array_filter(array_map('trim', (array) ($profile['specific_objectives'] ?? []))));
        $researchQuestions = array_values(array_filter(array_map('trim', (array) ($profile['research_questions'] ?? []))));

        if (str_contains($combined, 'general objective')) {
            return $generalObjective ?: 'The general objective of this study is to assess ' . $studyPhrase . '.';
        }

        if (str_contains($combined, 'specific objective')) {
            if (!empty($specificObjectives)) {
                return $this->numberedLines($specificObjectives);
            }

            return $this->numberedLines([
                'To examine the current level, pattern, or extent of ' . $studyPhrase . '.',
                'To identify the key factors associated with ' . $studyPhrase . '.',
                'To determine the major challenges or gaps related to ' . $studyPhrase . '.',
                'To propose practical recommendations for improving policy, practice, or future research on ' . $studyPhrase . '.',
            ]);
        }

        if (str_contains($combined, 'main research question')) {
            if (!empty($researchQuestions)) {
                return $researchQuestions[0];
            }

            return 'What is the current pattern, influence, or significance of ' . $studyPhrase . '?';
        }

        if (str_contains($combined, 'specific question') || preg_match('/\bresearch questions?\b/i', $combined)) {
            if (!empty($researchQuestions)) {
                return $this->numberedLines($researchQuestions);
            }

            return $this->numberedLines([
                'What is the current level or pattern of ' . $studyPhrase . '?',
                'What factors are associated with ' . $studyPhrase . '?',
                'What challenges or gaps affect ' . $studyPhrase . '?',
                'What practical measures can improve outcomes related to ' . $studyPhrase . '?',
            ]);
        }

        if (str_contains($combined, 'hypoth')) {
            return $this->numberedLines([
                'H0: There is no statistically significant relationship between the selected independent variables and ' . $studyPhrase . '.',
                'H1: There is a statistically significant relationship between the selected independent variables and ' . $studyPhrase . '.',
            ]);
        }

        if (str_contains($combined, 'research design')) {
            return 'This study will use an appropriate research design to examine ' . $studyPhrase . '. The design will guide the selection of respondents, data collection procedures, and analysis methods so that the findings remain aligned with the study objectives. Where primary data are collected, the design will support systematic collection of evidence from ' . $population . ' in ' . $studyArea . '.';
        }

        if (str_contains($combined, 'population') || str_contains($combined, 'sampling')) {
            return 'The target population for this study will include ' . $population . '. The sample will be selected using a suitable sampling strategy based on the nature of the study, available resources, and the need to obtain reliable information from respondents who can address the research objectives. The final sampling procedure and sample size should be confirmed before field data collection.';
        }

        if (str_contains($combined, 'data collection')) {
            return 'Data will be collected using tools that are appropriate to the study objectives, such as questionnaires, interviews, document review, or observation where relevant. The data collection process will focus on obtaining information that directly addresses the research questions and supports valid analysis of ' . $studyPhrase . '.';
        }

        if (str_contains($combined, 'data analysis')) {
            return "Data will be organized, cleaned, and analyzed according to the study objectives. Quantitative data may be summarized using frequencies, percentages, and relevant statistical tests, while qualitative responses may be analyzed thematically. The selected analysis approach should remain consistent with the study design and the type of data collected.";
        }

        if (str_contains($combined, 'theoretical framework')) {
            return 'The theoretical framework will provide the academic foundation for understanding ' . $studyPhrase . '. It will explain the key concepts, relationships, and assumptions that guide the study. The selected theory should be directly linked to the research objectives and should help interpret the expected findings within the Tanzanian context where applicable.';
        }

        if (str_contains($combined, 'conceptual framework')) {
            return "The conceptual framework will show the expected relationship between the study variables. It will identify the main independent variables, the dependent variable, and possible contextual factors that may influence the relationship. This framework will help guide data collection, analysis, and interpretation of findings.\n\n| Independent Variables | Dependent Variable | Expected Relationship |\n|---|---|---|\n| Individual, social, institutional, or contextual factors | Main study outcome related to " . $studyPhrase . " | These factors may influence the level, pattern, or outcome being investigated |";
        }

        if (str_contains($combined, 'planned data presentation') || str_contains($combined, 'expected finding') || str_contains($combined, 'expected outcome') || str_contains($combined, 'findings')) {
            return "The findings will be presented according to the research objectives. Since no verified dataset has been uploaded, this section provides a planned presentation structure rather than actual findings.\n\n| Research Objective | Data to be Presented | Analysis Method | Expected Output |\n|---|---|---|---|\n| Objective 1 | Descriptive information related to the main study issue | Frequencies and percentages | Summary of current level or pattern |\n| Objective 2 | Factors associated with the study issue | Cross-tabulation or relevant statistical test | Explanation of key associations |\n| Objective 3 | Challenges, gaps, or barriers | Descriptive or thematic analysis | Identification of major challenges |\n| Objective 4 | Recommendations | Synthesis of findings | Practical recommendations for improvement |";
        }

        if (str_contains($combined, 'contribution')) {
            return 'The study is expected to contribute to academic knowledge, policy discussion, and practical decision-making related to ' . $studyPhrase . '. The findings may help researchers, institutions, practitioners, and policy makers understand the issue more clearly and identify areas for future improvement.';
        }

        if (str_contains($combined, 'work plan') || str_contains($combined, 'schedule') || str_contains($combined, 'timeline')) {
            return "The study will be implemented in phases, beginning with proposal development and approval, followed by tool preparation, data collection, analysis, report writing, and final submission.\n\n| Activity | Expected Period | Output |\n|---|---|---|\n| Proposal development and approval | Month 1 | Approved research proposal |\n| Literature review and tool preparation | Month 1–2 | Reviewed literature and data collection tools |\n| Data collection | Month 2–3 | Completed field data collection |\n| Data analysis | Month 3 | Analyzed data |\n| Report writing and revision | Month 4 | Draft and final research report |";
        }

        if (str_contains($combined, 'limitation')) {
            return 'The study may be limited by time, availability of respondents, access to complete records, and the accuracy of self-reported information. These limitations will be managed through careful planning, clear data collection procedures, and transparent reporting of the study scope.';
        }

        if (str_contains($combined, 'ethic')) {
            return 'The study will observe relevant ethical principles, including voluntary participation, informed consent, confidentiality, and respect for respondents. Permission will be sought from relevant authorities before data collection, and collected information will be used only for academic purposes.';
        }

        if (str_contains($combined, 'abstract')) {
            return 'This study focuses on ' . $studyPhrase . '. It is designed to examine the background, problem, objectives, methodology, and expected contribution of the proposed research. The study will use appropriate data collection and analysis procedures to generate evidence that can support academic understanding and practical decision-making.';
        }

        if (str_contains($combined, 'background')) {
            return 'The background of this study explains the context and importance of ' . $studyPhrase . '. The topic is relevant because it reflects an issue that affects individuals, institutions, communities, or policy practice. Understanding this area provides a foundation for identifying the research problem, formulating objectives, and designing an appropriate methodology.';
        }

        if (str_contains($combined, 'problem')) {
            return 'Despite growing attention to ' . $studyPhrase . ', important gaps remain in understanding the nature, extent, and influencing factors of the problem. These gaps limit the ability of stakeholders to design evidence-based interventions or make informed decisions. This study therefore seeks to address the problem by generating structured evidence aligned with the research objectives.';
        }

        if (str_contains($combined, 'gap')) {
            return 'The research gap is based on the need for more focused evidence on ' . $studyPhrase . '. Existing knowledge may not fully explain the local context, key determinants, or practical implications of the issue. This study is therefore intended to contribute context-specific evidence and support future academic and policy discussions.';
        }

        // Generic fallback only for short structural sections. Long literature/discussion
        // sections should normally be handled by the model, but this prevents hard job failure.
        return 'This section will address ' . $studyPhrase . ' in line with the approved research title, objectives, and methodology. It should be reviewed and expanded where necessary to match supervisor guidance and institutional formatting requirements.';
    }

    protected function humanStudyPhrase(string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            return 'the research problem under investigation';
        }

        $phrase = preg_replace('/^(assessment|analysis|evaluation|investigation|study|the study|research)\s+of\s+/i', '', $title);
        $phrase = preg_replace('/^(the\s+)?(impact|influence|effect|factors)\s+of\s+/i', '$2 of ', $phrase);
        $phrase = trim((string) $phrase, " .\t\n\r\0\x0B");
        $phrase = lcfirst($phrase);

        return $phrase !== '' ? $phrase : 'the research problem under investigation';
    }

    protected function numberedLines(array $items): string
    {
        $lines = [];
        foreach (array_values($items) as $index => $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            $item = rtrim($item, '.');
            $lines[] = ($index + 1) . '. ' . $item . '.';
        }

        return implode("\n", $lines);
    }

    protected function datasetContext(NuruxploreProject $project): string
    {
        $sources = $project->relationLoaded('sources') ? $project->sources : $project->sources()->get();
        $datasetSources = [];
        foreach ($sources as $source) {
            $role = strtolower((string) ($source->metadata['document_role'] ?? $source->type ?? ''));
            $type = strtolower((string) $source->type);
            $ext = strtolower((string) ($source->metadata['file_extension'] ?? ''));
            if (in_array($role, ['dataset', 'data', 'survey_data', 'collected_data'], true) || in_array($type, ['dataset', 'data', 'csv', 'excel'], true) || in_array($ext, ['csv', 'xlsx'], true)) {
                $datasetSources[] = [
                    'title' => $source->title,
                    'role' => $role ?: $type,
                    'metadata' => $source->metadata,
                    'sample' => $this->limitWords((string) $source->extracted_text, 600),
                ];
            }
        }

        if (empty($datasetSources)) {
            return 'No uploaded dataset found. Results/findings must be planned or expected only; do not invent actual findings.';
        }

        return $this->json([
            'dataset_uploaded' => true,
            'sources' => $datasetSources,
            'rule' => 'Use only values, columns, and patterns visible in the uploaded dataset/source text. Do not invent frequencies, percentages, p-values, or totals.',
        ]);
    }

    protected function sectionContext(NuruxploreSection $section): string
    {
        $project = $section->project;
        $parent = $section->parent;
        $previous = NuruxploreSection::where('project_id', $project->id)
            ->where('order', '<', $section->order)
            ->where('parent_id', $section->parent_id)
            ->orderByDesc('order')
            ->first();

        return "APPROVED RESEARCH PROFILE:\n" . $this->json($project->research_profile)
            . "\n\nAPPROVED OUTLINE:\n" . $this->json($project->structure)
            . "\n\nPROJECT TITLE: {$project->title}"
            . "\nDOCUMENT TYPE: {$project->type}"
            . "\nCITATION STYLE: {$project->citation_style}"
            . "\nDATASET AVAILABLE: " . ($this->hasDataset($project) ? 'YES' : 'NO')
            . "\nPARENT CHAPTER: " . ($parent ? $parent->title : 'Top level')
            . "\nPREVIOUS SECTION SUMMARY: " . ($previous?->summary() ?: 'None');
    }

    protected function summarizeSection(NuruxploreProject $project, string $sectionTitle, string $content): string
    {
        $result = $this->groq->callGroqAPI(
            'Summarize academic sections briefly. Return plain text only.',
            "Summarize this section in 2-3 sentences for continuity.\n\nSECTION: {$sectionTitle}\n\nCONTENT:\n" . $this->limitWords($content, 700),
            250,
            null,
            $this->groq->fastModel(),
            0.2
        );

        return $result['success'] ? trim((string) $result['content']) : '';
    }

    protected function isReferenceSectionTitle(string $title): bool
    {
        return preg_match('/\b(references|bibliography|works cited)\b/i', $title) === 1;
    }

    protected function targetWordsForSection(NuruxploreSection $section): int
    {
        $title = strtolower($section->title);
        $parent = strtolower((string) ($section->parent?->title ?? ''));
        $combined = trim($parent . ' ' . $title);
        $isProposal = ($section->project?->type === 'proposal');

        if ($isProposal) {
            return match (true) {
                str_contains($combined, 'abstract') => 210,
                str_contains($combined, 'reference') => 180,
                str_contains($combined, 'general objective') => 70,
                str_contains($combined, 'specific objective') => 160,
                str_contains($combined, 'research question') || str_contains($combined, 'specific question') || str_contains($combined, 'main research question') => 160,
                str_contains($combined, 'background') => 520,
                str_contains($combined, 'problem') => 380,
                str_contains($combined, 'gap') => 250,
                str_contains($combined, 'literature'), str_contains($combined, 'empirical'), str_contains($combined, 'theoretical') => 520,
                str_contains($combined, 'methodology'), str_contains($combined, 'design'), str_contains($combined, 'sampling'), str_contains($combined, 'data collection'), str_contains($combined, 'data analysis') => 360,
                str_contains($combined, 'timeline') || str_contains($combined, 'work plan') || str_contains($combined, 'schedule') => 180,
                str_contains($combined, 'expected') || str_contains($combined, 'planned') || str_contains($combined, 'finding') => 320,
                default => 320,
            };
        }

        return match (true) {
            str_contains($combined, 'abstract') => 280,
            str_contains($combined, 'reference') => 180,
            str_contains($combined, 'background') => 650,
            str_contains($combined, 'problem') => 500,
            str_contains($combined, 'objective') => 350,
            str_contains($combined, 'question') => 350,
            str_contains($combined, 'literature'), str_contains($combined, 'empirical'), str_contains($combined, 'theoretical') => 750,
            str_contains($combined, 'methodology'), str_contains($combined, 'design'), str_contains($combined, 'sampling'), str_contains($combined, 'data collection'), str_contains($combined, 'data analysis') => 550,
            str_contains($combined, 'discussion') => 600,
            str_contains($combined, 'result'), str_contains($combined, 'finding') => $this->hasDataset($section->project) ? 650 : 420,
            str_contains($combined, 'conclusion'), str_contains($combined, 'recommendation') => 550,
            default => 480,
        };
    }

    protected function cleanGeneratedSectionContent(string $content, NuruxploreSection $section): string
    {
        $content = trim($content);
        $content = preg_replace('/^```(?:markdown|md)?\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        $content = trim($content);

        // Remove leading Markdown headings because the assembler owns headings.
        $lines = preg_split('/\R/', $content) ?: [];
        while (!empty($lines)) {
            $first = trim($lines[0]);
            $plain = $this->normalizeHeadingText($first);
            $sectionTitle = $this->normalizeHeadingText($section->title);
            $sectionNumber = preg_quote((string) $section->section_number, '/');

            $isHeading = preg_match('/^#{1,6}\s+/', $first)
                || preg_match('/^\*\*.+\*\*$/', $first)
                || ($sectionNumber && preg_match('/^' . $sectionNumber . '\s+/i', $first));

            // Only remove the first line when it is genuinely a heading/title line.
            // Previous logic removed any sentence that merely contained the section title.
            // Example bug: "The general objective of this study is..." contains
            // "general objective", so it was stripped and the fallback became empty.
            $wordCount = str_word_count($plain);
            $isShortTitleLine = $wordCount > 0 && $wordCount <= 8;
            $matchesExactTitle = $plain !== '' && $plain === $sectionTitle;
            $matchesShortTitle = $isShortTitleLine
                && $plain !== ''
                && $sectionTitle !== ''
                && (str_contains($plain, $sectionTitle) || str_contains($sectionTitle, $plain));

            if ($isHeading || $matchesExactTitle || $matchesShortTitle) {
                array_shift($lines);
                continue;
            }
            break;
        }
        $content = trim(implode("\n", $lines));

        // Remove common fake placeholder citations/tokens.
        $placeholderPatterns = [
            '/\[(?:Author|Authors),?\s*Year\]/i',
            '/\[(?:WHO|UNAIDS|CDC|Tanzania Ministry of Health|United Nations),?\s*Year\]/i',
            '/\[(?:Year|number|value|percentage|insert results|study design|sampling method|statistical software|data analysis procedures|data collection tools|limitations|future research directions)[^\]]*\]/i',
            '/\(\s*\[?Author,?\s*Year\]?\s*\)/i',
        ];
        foreach ($placeholderPatterns as $pattern) {
            $content = preg_replace($pattern, '', $content);
        }

        // Remove empty parenthetical leftovers and awkward spacing.
        $content = preg_replace('/\(\s*\)/', '', $content);
        $content = preg_replace('/\s+([.,;:])/', '$1', $content);
        $content = preg_replace('/[ \t]{2,}/', ' ', $content);

        // De-duplicate repeated paragraphs.
        $paragraphs = preg_split('/\n\s*\n/', trim($content)) ?: [];
        $seen = [];
        $cleanParagraphs = [];
        foreach ($paragraphs as $paragraph) {
            $p = trim($paragraph);
            if ($p === '') {
                continue;
            }
            $key = md5(strtolower(preg_replace('/\s+/', ' ', strip_tags($p))));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $cleanParagraphs[] = $p;
        }

        $content = implode("\n\n", $cleanParagraphs);

        // Trim if section still ran too long due model looping.
        $maxWords = max($this->targetWordsForSection($section) + 220, 550);
        $content = $this->limitWords($content, $maxWords);

        return trim($content);
    }

    protected function normalizeHeadingText(string $text): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/^#{1,6}\s+/', '', $text);
        $text = preg_replace('/^\d+(?:\.\d+)*\s+/', '', $text);
        $text = trim($text, " *\t\n\r\0\x0B:-");
        return strtolower($text);
    }

    protected function needsQualityRetry(string $content): bool
    {
        $flags = $this->qualityFlags($content);
        return in_array('contains_placeholders', $flags, true)
            || in_array('high_repetition', $flags, true)
            || in_array('too_short', $flags, true);
    }

    protected function qualityFlags(string $content): array
    {
        $flags = [];
        $wordCount = str_word_count(strip_tags($content));

        if ($wordCount < 80) {
            $flags[] = 'too_short';
        }

        if (preg_match('/\[(?:Author|Year|number|value|insert|study design|sampling method|statistical software)[^\]]*\]/i', $content)) {
            $flags[] = 'contains_placeholders';
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', strip_tags($content)) ?: [];
        $normalized = array_filter(array_map(fn ($s) => strtolower(trim(preg_replace('/\s+/', ' ', $s))), $sentences));
        $counts = array_count_values($normalized);
        foreach ($counts as $sentence => $count) {
            if ($count >= 3 && str_word_count($sentence) >= 8) {
                $flags[] = 'high_repetition';
                break;
            }
        }

        return array_values(array_unique($flags));
    }

    protected function sanitizeProfileChunk(array $chunk): array
    {
        $chunk['key_reference_examples'] = array_slice(array_values($chunk['key_reference_examples'] ?? $chunk['reference_examples'] ?? []), 0, 3);
        unset($chunk['references']);
        $chunk['references_available'] = (bool) ($chunk['references_available'] ?? !empty($chunk['key_reference_examples']));
        return $chunk;
    }

    protected function fallbackMergeProfile(NuruxploreProject $project, array $summaries, array $warnings = [], array $sourceDigest = []): array
    {
        $merged = [
            'title' => $project->title,
            'document_type' => $project->type,
            'citation_style' => $project->citation_style,
            'background' => [],
            'problem_statement' => [],
            'general_objective' => null,
            'specific_objectives' => [],
            'research_questions' => [],
            'hypotheses' => [],
            'methodology' => [],
            'variables' => [],
            'dataset_summary' => [],
            'expected_or_actual_findings' => [],
            'limitations' => [],
            'references_available' => false,
            'key_reference_examples' => [],
            'missing_information' => [],
            'extraction_warnings' => $warnings,
            'source_digest' => $sourceDigest,
            'generation_rules' => [
                'Do not change approved objectives.',
                'Do not change approved methodology.',
                'Do not invent findings unsupported by uploaded data.',
                'Do not use fake citation placeholders.',
            ],
            'dataset_uploaded' => $this->hasDataset($project),
        ];

        foreach ($summaries as $summary) {
            $merged['background'] = array_merge($merged['background'], $summary['background_points'] ?? []);
            $merged['problem_statement'] = array_merge($merged['problem_statement'], $summary['problem_statement_points'] ?? []);
            $merged['specific_objectives'] = array_merge($merged['specific_objectives'], $summary['specific_objectives'] ?? []);
            $merged['research_questions'] = array_merge($merged['research_questions'], $summary['research_questions'] ?? []);
            $merged['hypotheses'] = array_merge($merged['hypotheses'], $summary['hypotheses'] ?? []);
            $merged['variables'] = array_merge($merged['variables'], $summary['variables'] ?? []);
            $merged['dataset_summary'] = array_merge($merged['dataset_summary'], $summary['dataset_summary'] ?? []);
            $merged['limitations'] = array_merge($merged['limitations'], $summary['limitations'] ?? []);
            $merged['key_reference_examples'] = array_merge($merged['key_reference_examples'], $summary['key_reference_examples'] ?? []);
            $merged['references_available'] = $merged['references_available'] || (bool) ($summary['references_available'] ?? false);

            if (empty($merged['general_objective']) && !empty($summary['general_objective'])) {
                $merged['general_objective'] = $summary['general_objective'];
            }
            if (!empty($summary['methodology']) && is_array($summary['methodology'])) {
                $merged['methodology'] = array_replace_recursive($merged['methodology'], array_filter($summary['methodology'], fn ($v) => !blank($v)));
            }
            if (!empty($summary['title']) && $merged['title'] === $project->title) {
                $merged['title'] = $summary['title'];
            }
        }

        foreach (['background', 'problem_statement', 'specific_objectives', 'research_questions', 'hypotheses', 'variables', 'dataset_summary', 'limitations', 'key_reference_examples'] as $key) {
            $merged[$key] = array_slice(array_values(array_unique(array_filter($merged[$key]))), 0, $key === 'key_reference_examples' ? 12 : 25);
        }

        return $this->normalizeResearchProfile($merged, $project);
    }

    protected function normalizeResearchProfile(array $profile, NuruxploreProject $project): array
    {
        $profile['title'] = $profile['title'] ?? $project->title;
        $profile['document_type'] = $profile['document_type'] ?? $project->type;
        $profile['citation_style'] = $profile['citation_style'] ?? $project->citation_style;
        $profile['background'] = array_values($profile['background'] ?? []);
        $profile['problem_statement'] = array_values($profile['problem_statement'] ?? []);
        $profile['specific_objectives'] = array_values($profile['specific_objectives'] ?? []);
        $profile['research_questions'] = array_values($profile['research_questions'] ?? []);
        $profile['hypotheses'] = array_values($profile['hypotheses'] ?? []);
        $profile['variables'] = array_values($profile['variables'] ?? []);
        $profile['dataset_summary'] = array_values($profile['dataset_summary'] ?? []);
        $profile['dataset_profile'] = is_array($profile['dataset_profile'] ?? null) ? $profile['dataset_profile'] : $this->datasetProfileFromSources($project);
        $profile['expected_or_actual_findings'] = array_values($profile['expected_or_actual_findings'] ?? []);
        $profile['limitations'] = array_values($profile['limitations'] ?? []);
        $profile['key_reference_examples'] = array_slice(array_values($profile['key_reference_examples'] ?? $profile['references'] ?? []), 0, 12);
        unset($profile['references']);
        $profile['references_available'] = (bool) ($profile['references_available'] ?? !empty($profile['key_reference_examples']));
        $profile['missing_information'] = array_values($profile['missing_information'] ?? []);
        $profile['extraction_warnings'] = array_values($profile['extraction_warnings'] ?? []);
        $profile['generation_rules'] = array_values($profile['generation_rules'] ?? [
            'Do not change approved objectives.',
            'Do not change approved methodology.',
            'Do not invent findings unsupported by uploaded data.',
            'Do not use fake citation placeholders.',
        ]);
        $profile['dataset_uploaded'] = (bool) ($profile['dataset_uploaded'] ?? $this->hasDataset($project));

        return $profile;
    }

    protected function normalizeOutline(array $chapters, NuruxploreProject $project): array
    {
        $normalized = [];
        $seen = [];
        foreach ($chapters as $chapter) {
            if (is_string($chapter)) {
                $title = trim($chapter);
                $subsections = [];
            } else {
                $title = trim((string) ($chapter['title'] ?? 'Untitled Chapter'));
                $subsections = $chapter['subsections'] ?? [];
            }

            $title = $title ?: 'Untitled Chapter';
            $titleKey = strtolower(preg_replace('/^chapter\s+\w+[:.\-]?\s*/i', '', $title));
            if (isset($seen[$titleKey])) {
                continue;
            }
            $seen[$titleKey] = true;

            if ($this->isAbstractOrReferences($title)) {
                $subsections = [];
            }

            if ($project->type === 'proposal' && preg_match('/introduction/i', $title)) {
                $subsections = array_values(array_filter((array) $subsections, function ($subsection) {
                    $subTitle = strtolower(is_array($subsection) ? (string) ($subsection['title'] ?? '') : (string) $subsection);
                    return !preg_match('/objective|research question|specific question|general objective|main research question/i', $subTitle);
                }));
                if (empty($subsections)) {
                    $subsections = ['Background', 'Problem Statement', 'Research Gap'];
                }
            }

            if (!$this->hasDataset($project) && preg_match('/result|finding/i', $title)) {
                $title = 'Planned Presentation of Findings';
                $subsections = ['Expected Data Presentation', 'Analysis by Research Objective', 'Interpretation Framework'];
            }

            $cleanSubs = [];
            $subSeen = [];
            foreach (array_values($subsections) as $subsection) {
                $subTitle = trim(is_array($subsection) ? (string) ($subsection['title'] ?? '') : (string) $subsection);
                if ($subTitle === '') {
                    continue;
                }
                $subKey = strtolower($subTitle);
                if (isset($subSeen[$subKey]) || strtolower($subTitle) === strtolower($title)) {
                    continue;
                }
                $subSeen[$subKey] = true;
                $cleanSubs[] = $subTitle;
            }

            $normalized[] = [
                'title' => $title,
                'subsections' => array_slice($cleanSubs, 0, 5),
            ];
        }

        if (empty($normalized)) {
            return $this->defaultOutline($project->type, $this->hasDataset($project));
        }

        return $normalized;
    }

    protected function isAbstractOrReferences(string $title): bool
    {
        $title = strtolower($title);
        return str_contains($title, 'abstract') || str_contains($title, 'reference') || str_contains($title, 'bibliography');
    }

    protected function defaultOutline(string $type, bool $hasDataset = false): array
    {
        if ($type === 'proposal') {
            return [
                ['title' => 'Abstract', 'subsections' => []],
                ['title' => 'Introduction', 'subsections' => ['Background', 'Problem Statement', 'Research Gap']],
                ['title' => 'Research Objectives', 'subsections' => ['General Objective', 'Specific Objectives']],
                ['title' => 'Research Questions', 'subsections' => ['Main Research Question', 'Specific Questions']],
                ['title' => 'Literature Review', 'subsections' => ['Theoretical Review', 'Empirical Review', 'Conceptual Framework']],
                ['title' => 'Methodology', 'subsections' => ['Research Design', 'Population and Sampling', 'Data Collection', 'Data Analysis']],
                ['title' => 'Expected Outcomes', 'subsections' => ['Expected Findings', 'Contribution']],
                ['title' => 'Timeline', 'subsections' => ['Work Plan']],
                ['title' => 'References', 'subsections' => []],
            ];
        }

        return [
            ['title' => 'Abstract', 'subsections' => []],
            ['title' => 'Introduction', 'subsections' => ['Background', 'Problem Statement', 'Research Objectives', 'Research Questions', 'Significance of the Study']],
            ['title' => 'Literature Review', 'subsections' => ['Theoretical Review', 'Empirical Review', 'Research Gap', 'Conceptual Framework']],
            ['title' => 'Methodology', 'subsections' => ['Research Design', 'Study Area and Population', 'Sampling Procedure', 'Data Collection Methods', 'Data Analysis Plan', 'Ethical Considerations']],
            $hasDataset
                ? ['title' => 'Results and Findings', 'subsections' => ['Respondent Profile', 'Adoption and Usage of Mobile Money Services', 'Impact on Financial Performance', 'Benefits and Challenges', 'Summary of Findings']]
                : ['title' => 'Planned Presentation of Findings', 'subsections' => ['Expected Data Presentation', 'Analysis by Research Objective', 'Interpretation Framework']],
            ['title' => 'Discussion', 'subsections' => ['Interpretation Framework', 'Comparison with Literature', 'Implications for Practice']],
            ['title' => 'Conclusion and Recommendations', 'subsections' => ['Summary', 'Conclusion', 'Recommendations', 'Further Research']],
            ['title' => 'References', 'subsections' => []],
        ];
    }

    protected function hasDataset(NuruxploreProject $project): bool
    {
        if ($project->relationLoaded('sources')) {
            $sources = $project->sources;
        } else {
            $sources = $project->sources()->get();
        }

        foreach ($sources as $source) {
            $role = strtolower((string) ($source->metadata['document_role'] ?? ''));
            $type = strtolower((string) $source->type);
            $title = strtolower((string) $source->title);
            if (in_array($role, ['dataset', 'data', 'survey_data', 'collected_data'], true)
                || in_array($type, ['dataset', 'data', 'csv', 'excel'], true)
                || str_contains($title, 'dataset')
                || str_contains($title, '.csv')
                || str_contains($title, '.xlsx')) {
                return true;
            }
        }

        $profile = $project->research_profile ?? [];
        return !empty($profile['dataset_summary']) || !empty($profile['expected_or_actual_findings']);
    }

    /**
     * Remove a duplicated document title from generated/assembled content.
     *
     * The UI and ExportService print the project title as document metadata.
     * Therefore stored project.content should begin with Abstract/Introduction,
     * not with the thesis title. This helper is intentionally tolerant so it
     * also cleans old documents that already saved "# Title" or uppercase title.
     */
    protected function stripDocumentTitleFromContent(string $content, string $title): string
    {
        $content = trim($content);
        $title = trim($title);

        if ($content === '' || $title === '') {
            return $content;
        }

        $lines = preg_split('/\R/u', $content) ?: [];
        $cleanLines = [];
        $removed = false;

        foreach ($lines as $index => $line) {
            $rawLine = trim((string) $line);

            // Only inspect the first few non-empty lines. A title appearing later
            // may be legitimate body text and should not be removed.
            if ($index > 5 && !$removed) {
                $cleanLines[] = $line;
                continue;
            }

            if ($rawLine === '') {
                if ($removed) {
                    $cleanLines[] = $line;
                }
                continue;
            }

            $candidate = $this->normalizeDocumentTitleLine($rawLine);
            $expected = $this->normalizeDocumentTitleLine($title);

            if (!$removed && $candidate === $expected) {
                $removed = true;
                continue;
            }

            $cleanLines[] = $line;
        }

        return trim(implode("\n", $cleanLines));
    }

    protected function normalizeDocumentTitleLine(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/^\s*#{1,6}\s+/u', '', $value) ?? $value;
        $value = preg_replace('/^\s*<h[1-6][^>]*>|<\/h[1-6]>\s*$/iu', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        $value = preg_replace('/[^\p{L}\p{N}\s]/u', '', $value) ?? $value;
        return mb_strtolower(trim($value));
    }

    protected function createVersion(NuruxploreProject $project, string $description, string $type, array $snapshot): void
    {
        $version = (int) (NuruxploreVersion::where('project_id', $project->id)->max('version_number') ?? 0);
        NuruxploreVersion::create([
            'project_id' => $project->id,
            'user_id' => $project->user_id,
            'version_number' => $version + 1,
            'snapshot' => $snapshot,
            'changes_description' => $description,
            'change_type' => $type,
        ]);
    }

    protected function chunkText(string $text, int $wordsPerChunk = 1400): array
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');
        $words = preg_split('/\s+/', $text);
        if (!$words || $words === ['']) {
            return [];
        }
        return array_map(fn ($chunk) => implode(' ', $chunk), array_chunk($words, $wordsPerChunk));
    }

    protected function limitWords(string $text, int $limit): string
    {
        $words = preg_split('/\s+/', trim($text));
        if (!$words || count($words) <= $limit) {
            return $text;
        }
        return implode(' ', array_slice($words, 0, $limit)) . ' …';
    }

    protected function json(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
