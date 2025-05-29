<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\SaxParserService;
use App\Services\SaxExportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    public function __construct(
        private SaxParserService $saxParser,
        private SaxExportService $saxExporter
    ) {}
    
    /**
     * Display a listing of projects.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        
        $projects = Project::where(function ($query) use ($user) {
                        $query->where('user_id', $user->id)
                              ->orWhereHas('collaborators', function ($q) use ($user) {
                                  $q->where('user_id', $user->id);
                              })
                              ->orWhere('is_public', true);
                    })
                    ->with(['user:id,name', 'collaborators:id,name'])
                    ->orderBy('updated_at', 'desc')
                    ->paginate(20);
        
        return response()->json($projects);
    }
    
    /**
     * Store a newly created project.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'app_name' => 'nullable|string|max:255',
            'is_public' => 'boolean'
        ]);
        
        $project = Project::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'app_name' => $validated['app_name'] ?? 'SedonaApp',
            'is_public' => $validated['is_public'] ?? false,
            'user_id' => Auth::id(),
            'schema' => [],
            'canvas_settings' => [
                'zoom' => 1,
                'offsetX' => 0,
                'offsetY' => 0
            ]
        ]);
        
        return response()->json($project->load(['user:id,name']), 201);
    }
    
    /**
     * Display the specified project.
     */
    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        
        $project->load([
            'user:id,name',
            'collaborators:id,name',
            'components.componentType.kit',
            'components.children',
            'links.fromComponent',
            'links.toComponent'
        ]);
        
        return response()->json($project);
    }
    
    /**
     * Update the specified project.
     */
    public function update(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);
        
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'app_name' => 'sometimes|string|max:255',
            'is_public' => 'sometimes|boolean',
            'canvas_settings' => 'sometimes|array'
        ]);
        
        $project->update($validated);
        $project->touch('last_modified');
        
        return response()->json($project);
    }
    
    /**
     * Remove the specified project.
     */
    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);
        
        $project->delete();
        
        return response()->json(null, 204);
    }
    
    /**
     * Import a SAX file.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xml,sax|max:10240' // 10MB max
        ]);
        
        try {
            $xmlContent = file_get_contents($request->file('file')->path());
            $project = $this->saxParser->import($xmlContent, Auth::id());
            
            return response()->json([
                'message' => 'Project imported successfully',
                'project' => $project->load(['components', 'links'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to import SAX file',
                'error' => $e->getMessage()
            ], 422);
        }
    }
    
    /**
     * Export a project as SAX.
     */
    public function export(Project $project): \Illuminate\Http\Response
    {
        $this->authorize('view', $project);
        
        $xml = $this->saxExporter->export($project);
        
        return response($xml, 200)
            ->header('Content-Type', 'application/xml')
            ->header('Content-Disposition', 'attachment; filename="' . $project->app_name . '.sax"');
    }
    
    /**
     * Duplicate a project.
     */
    public function duplicate(Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        
        $newProject = $project->replicate();
        $newProject->name = $project->name . ' (Copy)';
        $newProject->user_id = Auth::id();
        $newProject->save();
        
        // Duplicate components
        $componentMap = [];
        foreach ($project->components as $component) {
            $newComponent = $component->replicate();
            $newComponent->project_id = $newProject->id;
            
            // Map old parent_id to new parent_id
            if ($component->parent_id && isset($componentMap[$component->parent_id])) {
                $newComponent->parent_id = $componentMap[$component->parent_id];
            }
            
            $newComponent->save();
            $componentMap[$component->id] = $newComponent->id;
        }
        
        // Duplicate links
        foreach ($project->links as $link) {
            $newLink = $link->replicate();
            $newLink->project_id = $newProject->id;
            $newLink->from_component_id = $componentMap[$link->from_component_id];
            $newLink->to_component_id = $componentMap[$link->to_component_id];
            $newLink->save();
        }
        
        return response()->json($newProject->load(['components', 'links']), 201);
    }
}
