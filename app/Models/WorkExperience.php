<?php

namespace App\Models;

use Database\Factories\WorkExperienceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkExperience extends Model
{
    /** @use HasFactory<WorkExperienceFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'employer',
        'role',
        'location',
        'start_date',
        'end_date',
        'is_current',
        'highlights',
        'position',
    ];

    /**
     * @return BelongsTo<Cv, $this>
     */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(Cv::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
            'highlights' => 'array',
            'position' => 'integer',
        ];
    }
}
