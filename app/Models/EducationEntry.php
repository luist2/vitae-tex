<?php

namespace App\Models;

use Database\Factories\EducationEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EducationEntry extends Model
{
    /** @use HasFactory<EducationEntryFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'institution',
        'qualification',
        'field_of_study',
        'location',
        'start_date',
        'end_date',
        'is_current',
        'description',
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
            'position' => 'integer',
        ];
    }
}
