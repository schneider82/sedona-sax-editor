<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'checksum',
        'description',
        'version',
        'manifest'
    ];

    protected $casts = [
        'manifest' => 'array'
    ];

    /**
     * Get the component types for the kit.
     */
    public function componentTypes(): HasMany
    {
        return $this->hasMany(ComponentType::class);
    }

    /**
     * Get component types by category.
     */
    public function componentTypesByCategory(): array
    {
        return $this->componentTypes
                    ->groupBy('category')
                    ->map(function ($types) {
                        return $types->sortBy('name')->values();
                    })
                    ->toArray();
    }

    /**
     * Find a component type by name.
     */
    public function findComponentType(string $typeName): ?ComponentType
    {
        return $this->componentTypes()->where('type_name', $typeName)->first();
    }

    /**
     * Check if kit has a specific component type.
     */
    public function hasComponentType(string $typeName): bool
    {
        return $this->componentTypes()->where('type_name', $typeName)->exists();
    }
}
