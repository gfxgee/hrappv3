<?php

namespace App\Models;

use App\Models\Concerns\TracksActivity;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    use TracksActivity;

    protected $guarded = [];

    /**
     * @return list<string>
     */
    protected function activitylogFields(): array
    {
        return ['name'];
    }

    /**
     * Employees belonging to this department.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Team-leader assignments for this department.
     *
     * @return HasMany<TeamLeader, $this>
     */
    public function teamLeaderAssignments(): HasMany
    {
        return $this->hasMany(TeamLeader::class);
    }

    /**
     * Users who lead this department.
     *
     * @return BelongsToMany<User, $this>
     */
    public function leaders(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_leaders')->withTimestamps();
    }
}
