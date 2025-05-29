<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Component;
use App\Models\Link;

class SaxExportService
{
    /**
     * Export a project as SAX XML.
     */
    public function export(Project $project): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0"?><sedonaApp></sedonaApp>');
        
        // Add schema
        $this->addSchema($xml, $project);
        
        // Add app
        $app = $xml->addChild('app');
        $this->addAppProperties($app, $project);
        
        // Add components
        $this->addComponents($app, $project);
        
        // Add links
        $this->addLinks($xml, $project);
        
        // Format the XML nicely
        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        
        return $dom->saveXML();
    }
    
    /**
     * Add schema (kits) to XML.
     */
    private function addSchema(\SimpleXMLElement $xml, Project $project): void
    {
        $schema = $xml->addChild('schema');
        
        // Get unique kits used in the project
        $kits = $project->components()
                       ->with('componentType.kit')
                       ->get()
                       ->pluck('componentType.kit')
                       ->unique('id')
                       ->sortBy('name');
        
        foreach ($kits as $kit) {
            $kitElement = $schema->addChild('kit');
            $kitElement->addAttribute('name', $kit->name);
            
            if ($kit->checksum) {
                $kitElement->addAttribute('checksum', $kit->checksum);
            }
        }
    }
    
    /**
     * Add app properties.
     */
    private function addAppProperties(\SimpleXMLElement $app, Project $project): void
    {
        $prop = $app->addChild('prop');
        $prop->addAttribute('name', 'appName');
        $prop->addAttribute('val', $project->app_name);
    }
    
    /**
     * Add components recursively.
     */
    private function addComponents(\SimpleXMLElement $parent, Project $project, ?int $parentId = null): void
    {
        $components = Component::where('project_id', $project->id)
                              ->where('parent_id', $parentId)
                              ->with('componentType.kit')
                              ->orderBy('component_id')
                              ->get();
        
        foreach ($components as $component) {
            $this->addComponent($parent, $component, $project);
        }
    }
    
    /**
     * Add a single component.
     */
    private function addComponent(\SimpleXMLElement $parent, Component $component, Project $project): void
    {
        // Add comment with path
        $parent->addChild('!-- ' . $component->getPath() . ' --');
        
        $comp = $parent->addChild('comp');
        $comp->addAttribute('name', $component->name);
        $comp->addAttribute('id', $component->component_id);
        $comp->addAttribute('type', $component->componentType->getFullTypeName());
        
        // Add meta if present
        if ($component->meta && isset($component->meta['value'])) {
            $prop = $comp->addChild('prop');
            $prop->addAttribute('name', 'meta');
            $prop->addAttribute('val', $component->meta['value']);
        }
        
        // Add other properties
        if ($component->properties) {
            foreach ($component->properties as $name => $value) {
                if ($name === 'meta') continue;
                
                $prop = $comp->addChild('prop');
                $prop->addAttribute('name', $name);
                
                // Convert value to string
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                } elseif (is_float($value)) {
                    $value = number_format($value, 1, '.', '');
                }
                
                $prop->addAttribute('val', (string)$value);
            }
        }
        
        // Add child components
        $this->addComponents($comp, $project, $component->id);
    }
    
    /**
     * Add links to XML.
     */
    private function addLinks(\SimpleXMLElement $xml, Project $project): void
    {
        $links = $xml->addChild('links');
        
        $projectLinks = Link::where('project_id', $project->id)
                           ->with(['fromComponent', 'toComponent'])
                           ->get();
        
        foreach ($projectLinks as $link) {
            $linkElement = $links->addChild('link');
            $linkElement->addAttribute('from', $link->getFromPath());
            $linkElement->addAttribute('to', $link->getToPath());
        }
    }
}
