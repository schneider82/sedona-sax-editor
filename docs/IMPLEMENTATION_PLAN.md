# Implementation Plan - Week 1 Action Items

## Immediate Next Steps (This Week)

### Day 1: Repository Setup

#### 1. Add Documentation Files
```bash
# In your sedona-sax-editor repository
git checkout -b feature/testing-infrastructure

# Create docs directory structure
mkdir -p docs/testing
mkdir -p tests/fixtures/sax-files
mkdir -p tests/fixtures/components

# Add the documentation files we created
# Copy TESTING_ROADMAP.md to docs/TESTING_ROADMAP.md
# Copy TESTING_GUIDELINES.md to docs/testing/TESTING_GUIDELINES.md  
# Copy CI_CD_SETUP.md to docs/testing/CI_CD_SETUP.md
```

#### 2. Create GitHub Issue Templates
Create `.github/ISSUE_TEMPLATE/testing-task.md`:
```markdown
---
name: Testing Task
about: Track testing implementation tasks
title: '[TEST] '
labels: testing
assignees: ''
---

## Task Description
Brief description of the testing task

## Acceptance Criteria
- [ ] Criterion 1
- [ ] Criterion 2

## Related Files
- File 1
- File 2

## Definition of Done
- [ ] Tests written and passing
- [ ] Code coverage targets met
- [ ] Documentation updated
```

### Day 2: Backend Testing Foundation

#### 1. Update PHPUnit Configuration
Update your `phpunit.xml`:
```xml
<!-- Copy the configuration from CI_CD_SETUP.md -->
```

#### 2. Create First API Test
Create `tests/Feature/ProjectApiTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_lists_projects_for_authenticated_user()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)
                         ->getJson('/api/projects');
        
        $response->assertStatus(200)
                 ->assertJsonStructure(['data']);
    }

    /** @test */
    public function it_requires_authentication_for_project_access()
    {
        $response = $this->getJson('/api/projects');
        
        $response->assertStatus(401);
    }

    /** @test */
    public function it_creates_project_with_valid_data()
    {
        $user = User::factory()->create();
        $projectData = [
            'name' => 'Test Project',
            'description' => 'A test project for validation'
        ];
        
        $response = $this->actingAs($user)
                         ->postJson('/api/projects', $projectData);
        
        $response->assertStatus(201)
                 ->assertJsonStructure(['id', 'name', 'description']);
                 
        $this->assertDatabaseHas('projects', [
            'name' => 'Test Project',
            'user_id' => $user->id
        ]);
    }
}
```

#### 3. Run Your First Test
```bash
php artisan test tests/Feature/ProjectApiTest.php
```

### Day 3: Frontend Testing Foundation

#### 1. Install Testing Dependencies
```bash
cd frontend
npm install --save-dev vitest @vue/test-utils jsdom @vitest/ui @vitest/coverage-c8
```

#### 2. Create Vitest Configuration
Create `frontend/vitest.config.js` (copy from CI_CD_SETUP.md)

#### 3. Create Test Setup File
Create `frontend/tests/setup.js` (copy from CI_CD_SETUP.md)

#### 4. Create First Component Test
Create `frontend/tests/components/CanvasEditor.test.js`:
```javascript
import { describe, test, expect, beforeEach } from 'vitest'
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
  
  test('mounts successfully', () => {
    expect(wrapper.exists()).toBe(true)
  })
  
  test('initializes with empty state', () => {
    // Test that the component starts with no components
    // This will depend on your actual component structure
    expect(wrapper.vm.components || []).toEqual([])
  })
})
```

#### 5. Run Your First Frontend Test
```bash
cd frontend
npm run test:unit
```

### Day 4: SAX Parser Testing

#### 1. Create Test Fixtures
Create `tests/fixtures/sax-files/basic-add-component.xml`:
```xml
<?xml version='1.0'?>
<sedonaApp>
  <schema>
    <kit name='sys'/>
    <kit name='control'/>
  </schema>
  <app>
    <comp name="add1" type="control::Add2" id="1">
      <prop name="in1" val="5.0"/>
      <prop name="in2" val="3.0"/>
    </comp>
  </app>
  <links/>
</sedonaApp>
```

Create `tests/fixtures/sax-files/invalid-component.xml`:
```xml
<?xml version='1.0'?>
<sedonaApp>
  <schema>
    <kit name='sys'/>
  </schema>
  <app>
    <comp name="invalid" type="nonexistent::Component" id="1"/>
  </app>
  <links/>
</sedonaApp>
```

#### 2. Create SAX Parser Test
Create `tests/Unit/SAXParserTest.php`:
```php
<?php

namespace Tests\Unit;

use App\Services\SAXParser; // Adjust namespace as needed
use Tests\TestCase;

class SAXParserTest extends TestCase
{
    /** @test */
    public function it_parses_basic_sax_file()
    {
        $saxContent = file_get_contents(base_path('tests/fixtures/sax-files/basic-add-component.xml'));
        
        $parser = new SAXParser();
        $result = $parser->parse($saxContent);
        
        $this->assertArrayHasKey('components', $result);
        $this->assertCount(1, $result['components']);
        $this->assertEquals('control::Add2', $result['components'][0]['type']);
    }

    /** @test */
    public function it_validates_component_types()
    {
        $saxContent = file_get_contents(base_path('tests/fixtures/sax-files/invalid-component.xml'));
        
        $parser = new SAXParser();
        
        $this->expectException(\InvalidArgumentException::class);
        $parser->parse($saxContent);
    }
}
```

### Day 5: GitHub Actions Setup

#### 1. Create Basic Workflow
Create `.github/workflows/tests.yml`:
```yaml
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  backend-tests:
    runs-on: ubuntu-latest
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v4

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: 8.1
        extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite

    - name: Copy environment file
      run: cp .env.example .env

    - name: Install Composer dependencies
      run: composer install --no-progress --prefer-dist --optimize-autoloader

    - name: Generate application key
      run: php artisan key:generate

    - name: Run tests
      run: php artisan test

  frontend-tests:
    runs-on: ubuntu-latest

    steps:
    - name: Checkout code
      uses: actions/checkout@v4

    - name: Setup Node.js
      uses: actions/setup-node@v4
      with:
        node-version: '18'
        cache: 'npm'
        cache-dependency-path: frontend/package-lock.json

    - name: Install dependencies
      working-directory: ./frontend
      run: npm ci

    - name: Run tests
      working-directory: ./frontend
      run: npm run test:unit
```

#### 2. Test the Workflow
```bash
git add .
git commit -m "Add basic testing infrastructure"
git push origin feature/testing-infrastructure
```

Create a pull request to trigger the workflow.

---

## Week 1 Success Metrics

By the end of Week 1, you should have:

- [ ] **Documentation**: All testing docs added to repository
- [ ] **Backend**: At least 3 passing API tests
- [ ] **Frontend**: At least 2 passing component tests  
- [ ] **CI/CD**: GitHub Actions running on PRs
- [ ] **Test Fixtures**: Basic SAX files for testing
- [ ] **Coverage**: Basic coverage reporting set up

---

## Common Issues & Solutions

### Issue 1: PHPUnit Database Connection
**Problem**: Tests fail with database connection errors
**Solution**: Ensure your `.env.testing` file uses SQLite:
```bash
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

### Issue 2: Vue Component Import Errors
**Problem**: Cannot resolve component imports in tests
**Solution**: Check your `vitest.config.js` alias configuration:
```javascript
resolve: {
  alias: {
    '@': resolve(__dirname, './src')
  }
}
```

### Issue 3: GitHub Actions Failing
**Problem**: Workflow fails on dependency installation
**Solution**: Ensure `package-lock.json` is committed and cache paths are correct

### Issue 4: Konva.js Canvas Errors
**Problem**: Canvas-related errors in frontend tests
**Solution**: Use the canvas mocking in `tests/setup.js` from our CI_CD_SETUP.md

---

## Week 2 Preview

Next week's focus areas:
1. **Expand API Test Coverage**: Add tests for all CRUD operations
2. **Component Library Testing**: Test individual SAX components  
3. **Integration Tests**: Test complete import/export workflows
4. **Error Handling**: Test validation and error scenarios
5. **Performance Baseline**: Establish performance benchmarks

---

## Commands Cheat Sheet

```bash
# Backend testing
php artisan test                    # Run all tests
php artisan test --filter=ProjectApi # Run specific test
php artisan test --coverage        # Run with coverage

# Frontend testing  
npm run test                        # Run tests in watch mode
npm run test:unit                   # Run tests once
npm run test:coverage              # Run with coverage
npm run test:ui                    # Open test UI

# Code quality
vendor/bin/php-cs-fixer fix       # Fix PHP code style
npm run lint                       # Fix JS/Vue linting
npm run format                     # Format with Prettier
```

---

## Getting Help

### Resources
- **Laravel Testing**: https://laravel.com/docs/testing
- **Vue Test Utils**: https://test-utils.vuejs.org/
- **Vitest**: https://vitest.dev/
- **Testing Best Practices**: Our TESTING_GUIDELINES.md

### Project Team Support
- Create GitHub issues for blockers
- Use PR reviews for code feedback  
- Weekly testing sync meetings

---

**Ready to start? Let's build bulletproof tests! 🚀**