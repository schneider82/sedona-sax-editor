<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Component;
use App\Models\ComponentType;
use App\Models\Kit;
use App\Models\Link;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SaxParserService
{
    /**
     * Import a SAX file and create a project.
     */
    public function import(string $xmlContent, int $userId): Project
    {
        $xml = simplexml_load_string($xmlContent);
        
        if (!$xml) {
            throw new \Exception('Invalid XML content');
        }
        
        return DB::transaction(function () use ($xml, $userId) {
            // Create the project
            $project = $this->createProject($xml, $userId);
            
            // Import schema (kits)
            $this->importSchema($xml->schema, $project);
            
            // Import components
            $componentMap = $this->importComponents($xml->app, $project);
            
            // Import links
            $this->importLinks($xml->links, $project, $componentMap);
            
            return $project->fresh(['components', 'links']);
        });
    }
    
    /**
     * Create project from XML.
     */
    private function createProject($xml, int $userId): Project
    {
        $appName = 'SedonaApp';
        
        // Get app name from first property if available
        if (isset($xml->app->prop)) {
            foreach ($xml->app->prop as $prop) {
                if ((string)$prop['name'] === 'appName') {
                    $appName = (string)$prop['val'];
                    break;
                }
            }
        }
        
        return Project::create([
            'name' => $appName . ' - ' . now()->format('Y-m-d H:i'),
            'user_id' => $userId,
            'app_name' => $appName,
            'schema' => [],
            'canvas_settings' => [
                'zoom' => 1,
                'offsetX' => 0,
                'offsetY' => 0
            ]
        ]);
    }
    
    /**
     * Import schema (kits) information.
     */
    private function importSchema($schema, Project $project): void
    {
        $schemaData = [];
        
        if (isset($schema->kit)) {
            foreach ($schema->kit as $kit) {
                $kitName = (string)$kit['name'];
                $checksum = (string)$kit['checksum'];
                
                $schemaData[$kitName] = $checksum;
                
                // Ensure kit exists in database
                Kit::firstOrCreate(
                    ['name' => $kitName],
                    [
                        'display_name' => $kitName,
                        'checksum' => $checksum
                    ]
                );
            }
        }
        
        $project->update(['schema' => $schemaData]);
    }
    
    /**
     * Import components recursively.
     */
    private function importComponents($app, Project $project, ?int $parentId = null): array
    {
        $componentMap = [];
        
        if (!isset($app->comp)) {
            return $componentMap;
        }
        
        foreach ($app->comp as $comp) {
            $component = $this->createComponent($comp, $project, $parentId);
            $componentMap[(string)$comp['id']] = $component->id;
            
            // Recursively import child components
            if ($comp->comp) {
                $childMap = $this->importComponents($comp, $project, $component->id);
                $componentMap = array_merge($componentMap, $childMap);
            }
        }
        
        return $componentMap;
    }
    
    /**
     * Create a single component.
     */
    private function createComponent($comp, Project $project, ?int $parentId): Component
    {
        $name = (string)$comp['name'];
        $id = (int)$comp['id'];
        $type = (string)$comp['type'];
        
        // Parse type (format: kit::type)
        [$kitName, $typeName] = explode('::', $type);
        
        // Find or create component type
        $kit = Kit::firstOrCreate(['name' => $kitName], [
            'display_name' => $kitName
        ]);
        
        $componentType = ComponentType::firstOrCreate(
            [
                'kit_id' => $kit->id,
                'type_name' => $typeName
            ],
            [
                'name' => $typeName,
                'is_folder' => $typeName === 'Folder'
            ]
        );
        
        // Parse properties
        $properties = [];
        if (isset($comp->prop)) {
            foreach ($comp->prop as $prop) {
                $propName = (string)$prop['name'];
                $propValue = (string)$prop['val'];
                
                // Convert string values to appropriate types
                if ($propValue === 'true') {
                    $propValue = true;
                } elseif ($propValue === 'false') {
                    $propValue = false;
                } elseif (is_numeric($propValue)) {
                    $propValue = strpos($propValue, '.') !== false ? (float)$propValue : (int)$propValue;
                }
                
                $properties[$propName] = $propValue;
            }
        }
        
        // Calculate position (this is a simplified version)
        $position = $this->calculatePosition($project, $parentId);
        
        return Component::create([
            'project_id' => $project->id,
            'component_type_id' => $componentType->id,
            'parent_id' => $parentId,
            'name' => $name,
            'component_id' => $id,
            'properties' => $properties,
            'meta' => isset($properties['meta']) ? ['value' => $properties['meta']] : null,
            'x' => $position['x'],
            'y' => $position['y']
        ]);
    }
    
    /**
     * Calculate position for a new component.
     */
    private function calculatePosition(Project $project, ?int $parentId): array
    {
        // Get existing components at the same level
        $existingCount = Component::where('project_id', $project->id)
                                 ->where('parent_id', $parentId)
                                 ->count();
        
        // Simple grid layout
        $cols = 4;
        $spacing = 200;
        
        return [
            'x' => ($existingCount % $cols) * $spacing + 50,
            'y' => floor($existingCount / $cols) * $spacing + 50
        ];
    }
    
    /**
     * Import links between components.
     */
    private function importLinks($links, Project $project, array $componentMap): void
    {
        if (!isset($links->link)) {
            return;
        }
        
        foreach ($links->link as $link) {
            $from = (string)$link['from'];
            $to = (string)$link['to'];
            
            // Parse link format: /path/to/component.slot
            $fromParts = $this->parseLinkPath($from);
            $toParts = $this->parseLinkPath($to);
            
            // Find components
            $fromComponent = $this->findComponentByPath($project, $fromParts['path']);
            $toComponent = $this->findComponentByPath($project, $toParts['path']);
            
            if ($fromComponent && $toComponent) {
                Link::create([
                    'project_id' => $project->id,
                    'from_component_id' => $fromComponent->id,
                    'from_slot' => $fromParts['slot'],
                    'to_component_id' => $toComponent->id,
                    'to_slot' => $toParts['slot']
                ]);
            }
        }
    }
    
    /**
     * Parse a link path into component path and slot.
     */
    private function parseLinkPath(string $path): array
    {
        $lastDot = strrpos($path, '.');
        
        return [
            'path' => substr($path, 0, $lastDot),
            'slot' => substr($path, $lastDot + 1)
        ];
    }
    
    /**
     * Find a component by its path.
     */
    private function findComponentByPath(Project $project, string $path): ?Component
    {
        $parts = array_filter(explode('/', $path));
        $current = null;
        
        foreach ($parts as $part) {
            $query = Component::where('project_id', $project->id)
                             ->where('name', $part);
            
            if ($current) {
                $query->where('parent_id', $current->id);
            } else {
                $query->whereNull('parent_id');
            }
            
            $current = $query->first();
            
            if (!$current) {
                return null;
            }
        }
        
        return $current;
    }
}
