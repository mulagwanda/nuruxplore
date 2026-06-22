<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NuruxploreProject extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'uuid',
        'title',
        'type',
        'citation_style',
        'description',
        'research_question',
        'research_profile',
        'research_profile_status',
        'research_profile_approved_at',
        'generation_settings',
        'structure',
        'content',
        'word_count',
        'status',
        'last_edited_at',
    ];

    protected $casts = [
        'research_profile' => 'array',
        'generation_settings' => 'array',
        'structure' => 'array',
        'last_edited_at' => 'datetime',
        'research_profile_approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($project) {
            if (empty($project->uuid)) {
                $project->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(NuruxploreSection::class, 'project_id')->orderBy('order');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(NuruxploreSource::class, 'project_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(NuruxploreMessage::class, 'project_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(NuruxploreVersion::class, 'project_id');
    }

    public function topLevelSections(): HasMany
    {
        return $this->hasMany(NuruxploreSection::class, 'project_id')
            ->whereNull('parent_id')
            ->orderBy('order');
    }

    public function hasApprovedResearchProfile(): bool
    {
        return $this->research_profile_status === 'approved' && !empty($this->research_profile);
    }

    public function updateWordCount(): void
    {
        $sectionsWordCount = (int) $this->sections()->sum('word_count');

        $this->forceFill([
            'word_count' => $sectionsWordCount > 0
                ? $sectionsWordCount
                : str_word_count(strip_tags((string) $this->content)),
        ])->saveQuietly();
    }
}
