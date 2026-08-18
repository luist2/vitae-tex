<?php

namespace App\Models;

use Database\Factories\CvLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CvLink extends Model
{
    /** @use HasFactory<CvLinkFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'type',
        'label',
        'url',
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
            'position' => 'integer',
        ];
    }
}
