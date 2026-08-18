<?php

namespace App\Models;

use Database\Factories\CvFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cv extends Model
{
    /** @use HasFactory<CvFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'template_key',
        'full_name',
        'professional_headline',
        'contact_email',
        'phone',
        'location',
        'professional_summary',
    ];

    /**
     * Get the user that owns the CV.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<WorkExperience, $this>
     */
    public function workExperiences(): HasMany
    {
        return $this->hasMany(WorkExperience::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<EducationEntry, $this>
     */
    public function educationEntries(): HasMany
    {
        return $this->hasMany(EducationEntry::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<SkillGroup, $this>
     */
    public function skillGroups(): HasMany
    {
        return $this->hasMany(SkillGroup::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<Certification, $this>
     */
    public function certifications(): HasMany
    {
        return $this->hasMany(Certification::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<CvLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(CvLink::class)->orderBy('position')->orderBy('id');
    }
}
