<template>
  <div ref="canvasWrapper" class="visual-canvas" @drop="handleDrop" @dragover.prevent>
    <div ref="konvaContainer" class="konva-container"></div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import Konva from 'konva'
import { useCanvasStore } from '@/stores/canvas'
import { useComponentStore } from '@/stores/component'
import ComponentNode from './ComponentNode'
import LinkLine from './LinkLine'

const props = defineProps({
  project: Object
})

const emit = defineEmits([
  'component-select',
  'component-delete',
  'link-create',
  'link-delete',
  'canvas-drop'
])

const canvasWrapper = ref(null)
const konvaContainer = ref(null)
const canvasStore = useCanvasStore()
const componentStore = useComponentStore()

let stage = null
let layer = null
let gridLayer = null
let transformer = null
let selectedNode = null
let linkingMode = false
let linkingSource = null

// Component and link maps
const componentNodes = new Map()
const linkLines = new Map()

onMounted(() => {
  initializeCanvas()
  
  // Watch for component changes
  watch(() => componentStore.components, updateComponents, { deep: true })
  watch(() => componentStore.links, updateLinks, { deep: true })
  watch(() => canvasStore.zoom, updateZoom)
  watch(() => canvasStore.showGrid, updateGrid)
})

onUnmounted(() => {
  if (stage) {
    stage.destroy()
  }
})

// Canvas initialization
function initializeCanvas() {
  const container = konvaContainer.value
  const wrapper = canvasWrapper.value
  
  stage = new Konva.Stage({
    container: container,
    width: wrapper.offsetWidth,
    height: wrapper.offsetHeight,
    draggable: true
  })
  
  // Grid layer
  gridLayer = new Konva.Layer()
  stage.add(gridLayer)
  updateGrid()
  
  // Main layer
  layer = new Konva.Layer()
  stage.add(layer)
  
  // Transformer for selection
  transformer = new Konva.Transformer({
    enabledAnchors: ['middle-left', 'middle-right'],
    boundBoxFunc: (oldBox, newBox) => {
      // Limit resize
      if (newBox.width < 100) newBox.width = 100
      if (newBox.height < 60) newBox.height = 60
      return newBox
    }
  })
  layer.add(transformer)
  
  // Stage events
  stage.on('click tap', handleStageClick)
  stage.on('wheel', handleWheel)
  
  // Window resize
  window.addEventListener('resize', handleResize)
  
  // Initial render
  updateComponents()
  updateLinks()
}

// Grid rendering
function updateGrid() {
  gridLayer.destroyChildren()
  
  if (!canvasStore.showGrid) {
    gridLayer.draw()
    return
  }
  
  const gridSize = 20
  const width = stage.width()
  const height = stage.height()
  
  // Vertical lines
  for (let x = 0; x < width; x += gridSize) {
    gridLayer.add(new Konva.Line({
      points: [x, 0, x, height],
      stroke: '#e5e7eb',
      strokeWidth: 1
    }))
  }
  
  // Horizontal lines
  for (let y = 0; y < height; y += gridSize) {
    gridLayer.add(new Konva.Line({
      points: [0, y, width, y],
      stroke: '#e5e7eb',
      strokeWidth: 1
    }))
  }
  
  gridLayer.draw()
}

// Component rendering
function updateComponents() {
  // Remove deleted components
  componentNodes.forEach((node, id) => {
    if (!componentStore.components.find(c => c.id === id)) {
      node.destroy()
      componentNodes.delete(id)
    }
  })
  
  // Add/update components
  componentStore.components.forEach(component => {
    let node = componentNodes.get(component.id)
    
    if (!node) {
      // Create new node
      node = new ComponentNode({
        component,
        onSelect: () => handleComponentSelect(component),
        onDelete: () => emit('component-delete', component.id),
        onSlotClick: (slot) => handleSlotClick(component, slot),
        onDragEnd: (pos) => handleComponentDragEnd(component.id, pos)
      })
      
      layer.add(node.group)
      componentNodes.set(component.id, node)
    } else {
      // Update existing node
      node.update(component)
    }
  })
  
  layer.batchDraw()
}

// Link rendering
function updateLinks() {
  // Remove deleted links
  linkLines.forEach((line, id) => {
    if (!componentStore.links.find(l => l.id === id)) {
      line.destroy()
      linkLines.delete(id)
    }
  })
  
  // Add/update links
  componentStore.links.forEach(link => {
    let line = linkLines.get(link.id)
    
    if (!line) {
      // Create new link
      const fromNode = componentNodes.get(link.from_component_id)
      const toNode = componentNodes.get(link.to_component_id)
      
      if (fromNode && toNode) {
        line = new LinkLine({
          link,
          fromNode,
          toNode,
          onDelete: () => emit('link-delete', link.id)
        })
        
        layer.add(line.group)
        linkLines.set(link.id, line)
      }
    } else {
      // Update existing link
      line.update()
    }
  })
  
  // Move links to back
  linkLines.forEach(line => {
    line.group.moveToBottom()
  })
  
  layer.batchDraw()
}

// Event handlers
function handleStageClick(e) {
  // Click on empty area - deselect
  if (e.target === stage) {
    transformer.nodes([])
    selectedNode = null
    emit('component-select', null)
    linkingMode = false
    linkingSource = null
  }
}

function handleComponentSelect(component) {
  const node = componentNodes.get(component.id)
  if (node) {
    transformer.nodes([node.group])
    selectedNode = node
    emit('component-select', component)
  }
}

function handleComponentDragEnd(componentId, position) {
  componentStore.updateComponent(componentId, {
    x: Math.round(position.x),
    y: Math.round(position.y)
  })
}

function handleSlotClick(component, slot) {
  if (!linkingMode) {
    // Start linking
    linkingMode = true
    linkingSource = { component, slot }
  } else {
    // Complete link
    if (linkingSource.component.id !== component.id) {
      emit('link-create', {
        from: {
          componentId: linkingSource.component.id,
          slot: linkingSource.slot.name
        },
        to: {
          componentId: component.id,
          slot: slot.name
        }
      })
    }
    
    linkingMode = false
    linkingSource = null
  }
}

function handleWheel(e) {
  e.evt.preventDefault()
  
  const oldScale = stage.scaleX()
  const pointer = stage.getPointerPosition()
  
  const scaleBy = 1.1
  const newScale = e.evt.deltaY > 0 ? oldScale / scaleBy : oldScale * scaleBy
  
  canvasStore.setZoom(newScale)
}

function updateZoom() {
  const pointer = stage.getPointerPosition()
  const oldScale = stage.scaleX()
  const newScale = canvasStore.zoom
  
  const mousePointTo = {
    x: (pointer.x - stage.x()) / oldScale,
    y: (pointer.y - stage.y()) / oldScale
  }
  
  stage.scale({ x: newScale, y: newScale })
  
  const newPos = {
    x: pointer.x - mousePointTo.x * newScale,
    y: pointer.y - mousePointTo.y * newScale
  }
  
  stage.position(newPos)
  stage.batchDraw()
}

function handleResize() {
  const wrapper = canvasWrapper.value
  stage.width(wrapper.offsetWidth)
  stage.height(wrapper.offsetHeight)
  updateGrid()
  stage.batchDraw()
}

function handleDrop(event) {
  event.preventDefault()
  emit('canvas-drop', event)
}

// Public methods
function getRelativePosition(clientPos) {
  const transform = stage.getAbsoluteTransform().copy()
  transform.invert()
  const pos = transform.point(clientPos)
  
  return {
    x: Math.round(pos.x / 20) * 20, // Snap to grid
    y: Math.round(pos.y / 20) * 20
  }
}

defineExpose({
  getRelativePosition
})
</script>

<style scoped>
.visual-canvas {
  width: 100%;
  height: 100%;
  background-color: #f9fafb;
  position: relative;
}

.konva-container {
  width: 100%;
  height: 100%;
}
</style>
