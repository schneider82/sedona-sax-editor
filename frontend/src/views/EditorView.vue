<template>
  <div class="editor-view">
    <!-- Toolbar -->
    <EditorToolbar 
      @save="saveProject"
      @export="exportProject"
      @import="showImportDialog"
      @zoom-in="zoomIn"
      @zoom-out="zoomOut"
      @zoom-reset="zoomReset"
      @toggle-grid="toggleGrid"
      :zoom="canvasStore.zoom"
      :show-grid="canvasStore.showGrid"
    />
    
    <div class="editor-main">
      <!-- Component Palette -->
      <ComponentPalette 
        v-if="showPalette"
        @drag-start="handlePaletteDragStart"
      />
      
      <!-- Canvas -->
      <div class="canvas-container" ref="canvasContainer">
        <VisualCanvas
          ref="visualCanvas"
          :project="currentProject"
          @component-select="handleComponentSelect"
          @component-delete="handleComponentDelete"
          @link-create="handleLinkCreate"
          @link-delete="handleLinkDelete"
          @canvas-drop="handleCanvasDrop"
        />
      </div>
      
      <!-- Properties Panel -->
      <PropertiesPanel
        v-if="showProperties && selectedComponent"
        :component="selectedComponent"
        @update="handleComponentUpdate"
        @close="selectedComponent = null"
      />
    </div>
    
    <!-- Import Dialog -->
    <ImportDialog 
      v-if="showImportDialog"
      @close="showImportDialog = false"
      @import="handleImport"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useProjectStore } from '@/stores/project'
import { useCanvasStore } from '@/stores/canvas'
import { useComponentStore } from '@/stores/component'
import { useToast } from 'vue-toastification'
import EditorToolbar from '@/components/editor/EditorToolbar.vue'
import ComponentPalette from '@/components/palette/ComponentPalette.vue'
import VisualCanvas from '@/components/canvas/VisualCanvas.vue'
import PropertiesPanel from '@/components/properties/PropertiesPanel.vue'
import ImportDialog from '@/components/dialogs/ImportDialog.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()

const projectStore = useProjectStore()
const canvasStore = useCanvasStore()
const componentStore = useComponentStore()

const visualCanvas = ref(null)
const canvasContainer = ref(null)
const selectedComponent = ref(null)
const showPalette = ref(true)
const showProperties = ref(true)
const showImportDialog = ref(false)
const draggedComponentType = ref(null)

const currentProject = computed(() => projectStore.currentProject)

// Load project on mount
onMounted(async () => {
  if (route.params.id) {
    try {
      await projectStore.loadProject(route.params.id)
    } catch (error) {
      toast.error('Failed to load project')
      router.push({ name: 'projects' })
    }
  } else {
    // Create new project
    await projectStore.createProject({
      name: 'New Project',
      description: 'A new Sedona project'
    })
  }
  
  // Set up keyboard shortcuts
  window.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown)
  projectStore.clearCurrentProject()
})

// Event handlers
function handleKeyDown(event) {
  // Ctrl/Cmd + S = Save
  if ((event.ctrlKey || event.metaKey) && event.key === 's') {
    event.preventDefault()
    saveProject()
  }
  
  // Delete key = Delete selected component
  if (event.key === 'Delete' && selectedComponent.value) {
    handleComponentDelete(selectedComponent.value.id)
  }
  
  // Escape = Deselect
  if (event.key === 'Escape') {
    selectedComponent.value = null
  }
}

function handleComponentSelect(component) {
  selectedComponent.value = component
}

function handleComponentDelete(componentId) {
  componentStore.deleteComponent(componentId)
  if (selectedComponent.value?.id === componentId) {
    selectedComponent.value = null
  }
  toast.success('Component deleted')
}

function handleComponentUpdate(updates) {
  componentStore.updateComponent(selectedComponent.value.id, updates)
}

function handleLinkCreate({ from, to }) {
  componentStore.createLink(from, to)
  toast.success('Link created')
}

function handleLinkDelete(linkId) {
  componentStore.deleteLink(linkId)
  toast.success('Link deleted')
}

function handlePaletteDragStart(componentType) {
  draggedComponentType.value = componentType
}

function handleCanvasDrop(event) {
  if (!draggedComponentType.value) return
  
  const position = visualCanvas.value.getRelativePosition({
    x: event.clientX,
    y: event.clientY
  })
  
  componentStore.createComponent({
    componentType: draggedComponentType.value,
    position,
    parentId: null // TODO: Handle dropping into folders
  })
  
  draggedComponentType.value = null
}

// Project operations
async function saveProject() {
  try {
    await projectStore.saveProject()
    toast.success('Project saved')
  } catch (error) {
    toast.error('Failed to save project')
  }
}

async function exportProject() {
  try {
    await projectStore.exportProject()
  } catch (error) {
    toast.error('Failed to export project')
  }
}

function showImportDialog() {
  showImportDialog.value = true
}

async function handleImport(file) {
  try {
    await projectStore.importProject(file)
    showImportDialog.value = false
    toast.success('Project imported successfully')
  } catch (error) {
    toast.error('Failed to import project')
  }
}

// Canvas operations
function zoomIn() {
  canvasStore.zoomIn()
}

function zoomOut() {
  canvasStore.zoomOut()
}

function zoomReset() {
  canvasStore.zoomReset()
}

function toggleGrid() {
  canvasStore.toggleGrid()
}
</script>

<style scoped>
.editor-view {
  display: flex;
  flex-direction: column;
  height: 100vh;
  background-color: #f3f4f6;
}

.editor-main {
  display: flex;
  flex: 1;
  overflow: hidden;
}

.canvas-container {
  flex: 1;
  position: relative;
  overflow: hidden;
}
</style>
