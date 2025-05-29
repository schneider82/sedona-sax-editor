<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Link extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'from_component_id',
        'from_slot',
        'to_component_id',
        'to_slot',
        'path_data'
    ];

    protected $casts = [
        'path_data' => 'array'
    ];

    /**
     * Get the project that owns the link.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the source component.
     */
    public function fromComponent(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'from_component_id');
    }

    /**
     * Get the target component.
     */
    public function toComponent(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'to_component_id');
    }

    /**
     * Get the full source path (e.g., /AHU_1/Tstat2.out).
     */
    public function getFromPath(): string
    {
        return $this->fromComponent->getPath() . '.' . $this->from_slot;
    }

    /**
     * Get the full target path (e.g., /AHU_1/Comp1EN.in).
     */
    public function getToPath(): string
    {
        return $this->toComponent->getPath() . '.' . $this->to_slot;
    }

    /**
     * Check if the link is valid (both components have the required slots).
     */
    public function isValid(): bool
    {
        return $this->fromComponent->hasSlot($this->from_slot) &&
               $this->toComponent->hasSlot($this->to_slot);
    }

    /**
     * Check if the link creates a cycle.
     */
    public function createsCycle(): bool
    {
        // Simple cycle detection - in a real implementation, this would be more sophisticated
        $visited = [];
        $current = $this->toComponent;
        
        while ($current) {
            if (in_array($current->id, $visited)) {
                return true;
            }
            
            $visited[] = $current->id;
            
            // Check if there's a path back to the source
            $nextLink = Link::where('from_component_id', $current->id)
                           ->where('to_component_id', $this->from_component_id)
                           ->first();
            
            $current = $nextLink ? $nextLink->toComponent : null;
        }
        
        return false;
    }
}
