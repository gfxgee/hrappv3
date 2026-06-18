<?php

namespace App\Models;

use App\Enum\SuggestionStatus;
use App\Models\Concerns\TracksActivity;
use Database\Factories\SuggestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An anonymous suggestion submitted by an employee. No author reference is ever
 * stored, so suggestions cannot be traced back to the person who filed them.
 * Only HR and super admins may read them. Deliberately does NOT use
 * {@see TracksActivity}, which would record the causing
 * user and defeat the anonymity guarantee.
 */
class Suggestion extends Model
{
    /** @use HasFactory<SuggestionFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Categories an employee can tag a suggestion with, to help HR triage.
     *
     * @var list<string>
     */
    public const CATEGORIES = [
        'Workplace Environment',
        'Management & Leadership',
        'Compensation & Benefits',
        'Facilities & Equipment',
        'Health & Wellbeing',
        'Other',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SuggestionStatus::class,
        ];
    }
}
