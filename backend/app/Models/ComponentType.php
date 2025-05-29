<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComponentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'kit_id',
        'name',
        'type_name',
        'description',
        'slots',
        'properties',
        'icon',
        'category',
        'is_folder'
    ];

    protected $casts = [
        'slots' => 'array',
        'properties' => 'array',
        'is_folder' => 'boolean'
    ];

    /**
     * Get the kit that owns the component type.
     */
    public function kit(): BelongsTo
    {
        return $this->belongsTo(Kit::class);
    }

    /**
     * Get the components of this type.
     */
    public function components(): HasMany
    {
        return $this->hasMany(Component::class);
    }

    /**
     * Get the full type name (e.g., "sys::Folder").
     */
    public function getFullTypeName(): string
    {
        return $this->kit->name . '::' . $this->type_name;
    }

    /**
     * Get input slots.
     */
    public function getInputSlots(): array
    {
        return collect($this->slots ?? [])
                ->filter(function ($slot) {
                    return $slot['direction'] === 'input' || $slot['direction'] === 'both';
                })
                ->values()
                ->toArray();
    }

    /**
     * Get output slots.
     */
    public function getOutputSlots(): array
    {
        return collect($this->slots ?? [])
                ->filter(function ($slot) {
                    return $slot['direction'] === 'output' || $slot['direction'] === 'both';
                })
                ->values()
                ->toArray();
    }

    /**
     * Get default property values.
     */
    public function getDefaultProperties(): array
    {
        return collect($this->properties ?? [])
                ->mapWithKeys(function ($prop) {
                    return [$prop['name'] => $prop['default'] ?? null];
                })
                ->toArray();
    }

    /**
     * Check if this type can connect to another type.
     */
    public function canConnectTo(ComponentType $targetType, string $fromSlot, string $toSlot): bool
    {
        $sourceSlot = collect($this->slots ?? [])->firstWhere('name', $fromSlot);
        $targetSlot = collect($targetType->slots ?? [])->firstWhere('name', $toSlot);
        
        if (!$sourceSlot || !$targetSlot) {
            return false;
        }
        
        // Check if slots have compatible types
        if (isset($sourceSlot['type']) && isset($targetSlot['type'])) {
            return $sourceSlot['type'] === $targetSlot['type'];
        }
        
        return true;
    }
}
