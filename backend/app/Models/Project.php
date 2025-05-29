<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'user_id',
        'app_name',
        'schema',
        'canvas_settings',
        'is_public',
        'last_modified'
    ];

    protected $casts = [
        'schema' => 'array',
        'canvas_settings' => 'array',
        'is_public' => 'boolean',
        'last_modified' => 'datetime'
    ];

    /**
     * Get the user that owns the project.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the components for the project.
     */
    public function components(): HasMany
    {
        return $this->hasMany(Component::class);
    }

    /**
     * Get the root components (no parent) for the project.
     */
    public function rootComponents(): HasMany
    {
        return $this->hasMany(Component::class)->whereNull('parent_id');
    }

    /**
     * Get the links for the project.
     */
    public function links(): HasMany
    {
        return $this->hasMany(Link::class);
    }

    /**
     * Get the collaborators for the project.
     */
    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_collaborators')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    /**
     * Check if a user can edit the project.
     */
    public function canEdit(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        return $this->collaborators()
                    ->where('user_id', $user->id)
                    ->whereIn('role', ['editor', 'admin'])
                    ->exists();
    }

    /**
     * Check if a user can view the project.
     */
    public function canView(User $user): bool
    {
        if ($this->is_public) {
            return true;
        }

        if ($this->user_id === $user->id) {
            return true;
        }

        return $this->collaborators()
                    ->where('user_id', $user->id)
                    ->exists();
    }

    /**
     * Export project as SAX XML.
     */
    public function toSax(): string
    {
        // This will be implemented by the SaxExportService
        return app(\App\Services\SaxExportService::class)->export($this);
    }
}
