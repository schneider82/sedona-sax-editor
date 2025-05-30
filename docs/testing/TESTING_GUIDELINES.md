# Testing Guidelines - Sedona SAX Editor

## Overview
This document establishes coding standards and best practices for writing tests in the Sedona SAX Editor project.

---

## General Testing Principles

### 1. **Test Naming Conventions**
```php
// Backend (PHPUnit)
class ProjectControllerTest extends TestCase
{
    /** @test */
    public function it_creates_project_with_valid_sax_data() { }
    
    /** @test */  
    public function it_rejects_project_creation_with_invalid_data() { }
    
    /** @test */
    public function it_returns_404_when_project_not_found() { }
}
```

```javascript
// Frontend (Vitest)
describe('CanvasEditor', () => {
  test('adds component when dropped from palette', () => { })
  test('rejects invalid component connections', () => { })
  test('updates component properties correctly', () => { })
})
```

### 2. **Test Structure (AAA Pattern)**
All tests should follow **Arrange, Act, Assert**:

```php
/** @test */
public function it_creates_project_with_valid_sax_data()
{
    // Arrange
    $user = User::factory()->create();
    $saxData = $this->createValidSaxPayload();
    
    // Act
    $response = $this->actingAs($user)
                     ->postJson('/api/projects', $saxData);
    
    // Assert
    $response->assertStatus(201)
             ->assertJsonStructure(['id', 'name', 'components']);
    $this->assertDatabaseHas('projects', ['name' => $saxData['name']]);
}
```

---

## Backend Testing Standards

### 1. **Test Categories**

#### Feature Tests (`tests/Feature/`)
Test complete user workflows through HTTP requests:
```php
class SAXWorkflowTest extends TestCase
{
    use RefreshDatabase;
    
    /** @test */
    public function complete_import_edit_export_workflow()
    {
        // Test full user journey
        $user = User::factory()->create();
        
        // Import SAX file
        $importResponse = $this->actingAs($user)
            ->postJson('/api/import', ['file' => $this->createSampleSAX()]);
        
        // Edit project
        $projectId = $importResponse->json('id');
        $this->actingAs($user)
             ->postJson("/api/projects/{$projectId}/components", [
                 'type' => 'control::Add2',
                 'position' => ['x' => 100, 'y' => 100]
             ]);
        
        // Export and verify
        $exportResponse = $this->actingAs($user)
            ->postJson("/api/projects/{$projectId}/export");
        
        $this->assertStringContains('<comp name="add1" type="control::Add2"', 
                                   $exportResponse->getContent());
    }
}
```

#### Unit Tests (`tests/Unit/`)
Test individual classes in isolation:
```php
class SAXParserTest extends TestCase
{
    /** @test */
    public function it_parses_control_component_correctly()
    {
        $xmlData = '<type id="3" name="Add2" sizeof="48" base="sys::Component">';
        
        $parser = new SAXParser();
        $component = $parser->parseComponent($xmlData);
        
        $this->assertEquals('Add2', $component->name);
        $this->assertEquals('sys::Component', $component->base);
    }
}
```

### 2. **Database Testing**
```php
class ProjectTest extends TestCase
{
    use RefreshDatabase;
    
    /** @test */
    public function it_has_many_components()
    {
        $project = Project::factory()->create();
        $components = Component::factory()->count(3)->create([
            'project_id' => $project->id
        ]);
        
        $this->assertCount(3, $project->components);
        $this->assertInstanceOf(Component::class, $project->components->first());
    }
}
```

### 3. **API Testing Standards**
```php
/** @test */
public function it_validates_required_fields_on_project_creation()
{
    $response = $this->postJson('/api/projects', []);
    
    $response->assertStatus(422)
             ->assertJsonValidationErrors(['name', 'description']);
}

/** @test */
public function it_requires_authentication_for_project_creation()
{
    $response = $this->postJson('/api/projects', [
        'name' => 'Test Project'
    ]);
    
    $response->assertStatus(401);
}
```

---

## Frontend Testing Standards

### 1. **Component Testing**
```javascript
import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import CanvasEditor from '@/components/canvas/CanvasEditor.vue'

describe('CanvasEditor', () => {
  let wrapper
  
  beforeEach(() => {
    const pinia = createPinia()
    wrapper = mount(CanvasEditor, {
      global: {
        plugins: [pinia]
      }
    })
  })
  
  test('initializes with empty canvas', () => {
    expect(wrapper.vm.components).toEqual([])
    expect(wrapper.vm.connections).toEqual([])
  })
  
  test('adds component when dropped', async () => {
    await wrapper.vm.handleComponentDrop({
      type: 'control::Add2',
      position: { x: 100, y: 100 }
    })
    
    expect(wrapper.vm.components).toHaveLength(1)
    expect(wrapper.vm.components[0].type).toBe('control::Add2')
  })
})
```

### 2. **Store Testing (Pinia)**
```javascript
import { setActivePinia, createPinia } from 'pinia'
import { useProjectStore } from '@/stores/project'

describe('Project Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })
  
  test('creates new project', () => {
    const store = useProjectStore()
    
    store.createProject('Test Project')
    
    expect(store.currentProject).toBeDefined()
    expect(store.currentProject.name).toBe('Test Project')
    expect(store.currentProject.components).toEqual([])
  })
  
  test('adds component to project', () => {
    const store = useProjectStore()
    store.createProject('Test')
    
    store.addComponent({
      type: 'control::Timer',
      position: { x: 50, y: 50 }
    })
    
    expect(store.currentProject.components).toHaveLength(1)
  })
})
```

### 3. **Async Testing**
```javascript
test('loads project from API', async () => {
  const mockProject = { id: 1, name: 'Test', components: [] }
  vi.spyOn(api, 'getProject').mockResolvedValue(mockProject)
  
  const store = useProjectStore()
  await store.loadProject(1)
  
  expect(store.currentProject).toEqual(mockProject)
  expect(api.getProject).toHaveBeenCalledWith(1)
})
```

---

## Test Data Management

### 1. **Factory Pattern (Backend)**
```php
// database/factories/ProjectFactory.php
class ProjectFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'user_id' => User::factory(),
            'sax_data' => $this->createBasicSAXStructure()
        ];
    }
    
    public function withComponents($count = 3)
    {
        return $this->afterCreating(function (Project $project) use ($count) {
            Component::factory()->count($count)->create([
                'project_id' => $project->id
            ]);
        });
    }
}
```

### 2. **Test Fixtures (Frontend)**
```javascript
// tests/fixtures/sampleProjects.js
export const basicProject = {
  id: 1,
  name: 'Basic Math Project',
  components: [
    { id: 1, type: 'control::Add2', position: { x: 100, y: 100 } },
    { id: 2, type: 'control::Sub2', position: { x: 200, y: 100 } }
  ],
  connections: [
    { from: '1.out', to: '2.in1' }
  ]
}

export const complexProject = {
  // ... larger test project
}
```

---

## Mocking Guidelines

### 1. **API Mocking (Frontend)**
```javascript
import { vi } from 'vitest'

// Mock API responses
const mockApi = {
  getProjects: vi.fn().mockResolvedValue([]),
  createProject: vi.fn().mockResolvedValue({ id: 1 }),
  deleteProject: vi.fn().mockResolvedValue(true)
}

vi.mock('@/services/api', () => ({
  default: mockApi
}))
```

### 2. **WebSocket Mocking**
```javascript
class MockWebSocket {
  constructor() {
    this.messages = []
    this.readyState = WebSocket.OPEN
  }
  
  send(data) {
    this.messages.push(JSON.parse(data))
  }
  
  close() {
    this.readyState = WebSocket.CLOSED
  }
}

global.WebSocket = MockWebSocket
```

### 3. **Database Mocking (Backend)**
```php
/** @test */
public function it_handles_database_connection_failure()
{
    DB::shouldReceive('table')->andThrow(new Exception('Connection failed'));
    
    $response = $this->getJson('/api/projects');
    
    $response->assertStatus(500);
}
```

---

## Performance Testing

### 1. **Load Testing Guidelines**
```javascript
test('handles large project without performance degradation', async () => {
  const largeProject = createProjectWithComponents(1000)
  
  const startTime = performance.now()
  await store.loadProject(largeProject)
  const endTime = performance.now()
  
  expect(endTime - startTime).toBeLessThan(2000) // 2 seconds max
})
```

### 2. **Memory Leak Detection**
```javascript
test('prevents memory leaks on component deletion', () => {
  const initialMemory = performance.memory?.usedJSHeapSize || 0
  
  // Add and remove 100 components
  for (let i = 0; i < 100; i++) {
    store.addComponent({ type: 'control::Timer' })
    store.removeComponent(store.components[0].id)
  }
  
  // Force garbage collection if available
  if (global.gc) global.gc()
  
  const finalMemory = performance.memory?.usedJSHeapSize || 0
  expect(finalMemory - initialMemory).toBeLessThan(1024 * 1024) // Less than 1MB growth
})
```

---

## Code Coverage Standards

### Target Coverage Levels
- **Backend API**: 90%+ line coverage
- **Frontend Components**: 85%+ line coverage  
- **SAX Parser**: 95%+ line coverage
- **Critical Business Logic**: 100% line coverage

### Coverage Commands
```bash
# Backend coverage
php artisan test --coverage --min=90

# Frontend coverage  
npm run test:coverage -- --reporter=lcov --reporter=text
```

---

## Test Environment Setup

### 1. **Test Database Configuration**
```php
// phpunit.xml
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="DB_CONNECTION" value="sqlite"/>
    <env name="DB_DATABASE" value=":memory:"/>
    <env name="CACHE_DRIVER" value="array"/>
    <env name="SESSION_DRIVER" value="array"/>
    <env name="QUEUE_CONNECTION" value="sync"/>
</php>
```

### 2. **Frontend Test Configuration**
```javascript
// vitest.config.js
export default defineConfig({
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./tests/setup.js'],
    coverage: {
      reporter: ['text', 'lcov', 'html'],
      exclude: ['node_modules/', 'tests/']
    }
  }
})
```

---

## Common Patterns & Anti-Patterns

### ✅ **Good Practices**
```javascript
// Good: Descriptive test names
test('creates connection between compatible float output and float input', () => {})

// Good: One assertion per concept
test('validates component type exists', () => {
  expect(ComponentRegistry.exists('control::Add2')).toBe(true)
})

// Good: Isolated tests
beforeEach(() => {
  store.reset() // Clean state for each test
})
```

### ❌ **Anti-Patterns**
```javascript
// Bad: Vague test names
test('it works', () => {})

// Bad: Multiple unrelated assertions
test('component behavior', () => {
  expect(component.isValid()).toBe(true)
  expect(component.connections.length).toBe(2)
  expect(user.hasPermission()).toBe(true) // Unrelated!
})

// Bad: Shared state between tests
let globalComponent = new Component() // Don't do this!
```

---

## Test Maintenance

### 1. **Regular Review Schedule**
- **Weekly**: Review failing tests and flaky tests
- **Monthly**: Analyze coverage reports and identify gaps
- **Quarterly**: Refactor test code and update patterns

### 2. **Test Hygiene**
- Remove obsolete tests when features are removed
- Update test data when business rules change
- Refactor test code alongside production code

---

*This document should be updated as testing practices evolve.*