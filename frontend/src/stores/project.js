import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'
import { useComponentStore } from './component'

export const useProjectStore = defineStore('project', () => {
  const projects = ref([])
  const currentProject = ref(null)
  const loading = ref(false)
  const error = ref(null)
  
  const componentStore = useComponentStore()
  
  // Computed
  const projectCount = computed(() => projects.value.length)
  const isLoaded = computed(() => currentProject.value !== null)
  
  // Actions
  async function fetchProjects() {
    loading.value = true
    error.value = null
    
    try {
      const response = await api.get('/projects')
      projects.value = response.data.data
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }
  
  async function loadProject(id) {
    loading.value = true
    error.value = null
    
    try {
      const response = await api.get(`/projects/${id}`)
      currentProject.value = response.data
      
      // Load components and links into component store
      componentStore.setComponents(response.data.components || [])
      componentStore.setLinks(response.data.links || [])
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }
  
  async function createProject(projectData) {
    loading.value = true
    error.value = null
    
    try {
      const response = await api.post('/projects', projectData)
      currentProject.value = response.data
      projects.value.unshift(response.data)
      
      // Clear component store
      componentStore.clearAll()
      
      return response.data
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }
  
  async function updateProject(id, updates) {
    loading.value = true
    error.value = null
    
    try {
      const response = await api.put(`/projects/${id}`, updates)
      
      if (currentProject.value?.id === id) {
        currentProject.value = { ...currentProject.value, ...response.data }
      }
      
      const index = projects.value.findIndex(p => p.id === id)
      if (index !== -1) {
        projects.value[index] = response.data
      }
      
      return response.data
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }
  
  async function deleteProject(id) {
    loading.value = true
    error.value = null
    
    try {
      await api.delete(`/projects/${id}`)
      
      projects.value = projects.value.filter(p => p.id !== id)
      
      if (currentProject.value?.id === id) {
        currentProject.value = null
      }
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }
  
  async function saveProject() {
    if (!currentProject.value) return
    
    const components = componentStore.components
    const links = componentStore.links
    
    // Save via API
    await api.put(`/projects/${currentProject.value.id}/components`, {
      components,
      links
    })
    
    // Update last modified
    await updateProject(currentProject.value.id, {
      last_modified: new Date().toISOString()
    })
  }
  
  async function exportProject() {
    if (!currentProject.value) return
    
    try {
      const response = await api.get(`/projects/${currentProject.value.id}/export`, {
        responseType: 'blob'
      })
      
      // Create download link
      const url = window.URL.createObjectURL(new Blob([response.data]))
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', `${currentProject.value.app_name}.sax`)
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
    } catch (err) {
      error.value = err.message
      throw err
    }
  }
  
  async function importProject(file) {
    loading.value = true
    error.value = null
    
    try {
      const formData = new FormData()
      formData.append('file', file)
      
      const response = await api.post('/projects/import', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })
      
      currentProject.value = response.data.project
      projects.value.unshift(response.data.project)
      
      // Load components and links
      componentStore.setComponents(response.data.project.components || [])
      componentStore.setLinks(response.data.project.links || [])
      
      return response.data.project
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }
  
  async function duplicateProject(id) {
    loading.value = true
    error.value = null
    
    try {
      const response = await api.post(`/projects/${id}/duplicate`)
      projects.value.unshift(response.data)
      return response.data
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }
  
  function clearCurrentProject() {
    currentProject.value = null
    componentStore.clearAll()
  }
  
  return {
    // State
    projects,
    currentProject,
    loading,
    error,
    
    // Computed
    projectCount,
    isLoaded,
    
    // Actions
    fetchProjects,
    loadProject,
    createProject,
    updateProject,
    deleteProject,
    saveProject,
    exportProject,
    importProject,
    duplicateProject,
    clearCurrentProject
  }
})
