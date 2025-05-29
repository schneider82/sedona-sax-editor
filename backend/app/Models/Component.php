<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Component extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'component_type_id',
        'parent_id',
        'name',
        'component_id',
        'properties',
        'meta',
        'x',
        'y',
        'width',
        'height'
    ];

    protected $casts = [
        'properties' => 'array',
        'meta' => 'array',
        'x' => 'integer',
        'y' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'component_id' => 'integer'
    ];

    /**
     * Get the project that owns the component.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the component type.
     */
    public function componentType(): BelongsTo
    {
        return $this->belongsTo(ComponentType::class);
    }

    /**
     * Get the parent component.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Component::class, 'parent_id');
    }

    /**
     * Get the child components.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Component::class, 'parent_id');
    }

    /**
     * Get the outgoing links from this component.
     */
    public function outgoingLinks(): HasMany
    {
        return $this->hasMany(Link::class, 'from_component_id');
    }

    /**
     * Get the incoming links to this component.
     */
    public function incomingLinks(): HasMany
    {
        return $this->hasMany(Link::class, 'to_component_id');
    }

    /**
     * Get the full path of the component (e.g., /app/service/plat).
     */
    public function getPath(): string
    {
        $path = [$this->name];
        $parent = $this->parent;
        
        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }
        
        return '/' . implode('/', $path);
    }

    /**
     * Get available slots based on component type.
     */
    public function getSlots(): array
    {
        return $this->componentType->slots ?? [];
    }

    /**
     * Check if component has a specific slot.
     */
    public function hasSlot(string $slotName): bool
    {
        $slots = $this->getSlots();
        return collect($slots)->contains('name', $slotName);
    }

    /**
     * Get slot value from properties.
     */
    public function getSlotValue(string $slotName)
    {
        return $this->properties[$slotName] ?? null;
    }

    /**
     * Set slot value in properties.
     */
    public function setSlotValue(string $slotName, $value): void
    {
        $properties = $this->properties ?? [];
        $properties[$slotName] = $value;
        $this->properties = $properties;
    }
}
