# CI/CD Setup - Sedona SAX Editor

## Overview
This document provides the complete GitHub Actions workflow configuration for automated testing, code quality checks, and deployment of the Sedona SAX Editor.

---

## GitHub Actions Workflow

### Main Testing Workflow
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
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: sedona_sax_editor_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
    - name: Checkout code
      uses: actions/checkout@v4

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: 8.1
        extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd, exif, iconv
        coverage: xdebug

    - name: Copy environment file
      run: cp .env.example .env

    - name: Install Composer dependencies
      run: composer install --no-progress --prefer-dist --optimize-autoloader

    - name: Generate application key
      run: php artisan key:generate

    - name: Clear config cache
      run: php artisan config:clear

    - name: Run database migrations
      run: php artisan migrate --env=testing

    - name: Run PHPUnit tests
      run: php artisan test --coverage --min=80

    - name: Upload coverage reports to Codecov
      uses: codecov/codecov-action@v3
      with:
        file: ./coverage.xml
        flags: backend
        name: backend-coverage

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

    - name: Run linting
      working-directory: ./frontend
      run: npm run lint

    - name: Run type checking
      working-directory: ./frontend
      run: npm run type-check

    - name: Run unit tests
      working-directory: ./frontend
      run: npm run test:unit -- --coverage

    - name: Upload coverage reports to Codecov
      uses: codecov/codecov-action@v3
      with:
        file: ./frontend/coverage/lcov.info
        flags: frontend
        name: frontend-coverage

  e2e-tests:
    runs-on: ubuntu-latest
    needs: [backend-tests, frontend-tests]

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: sedona_sax_editor_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
    - name: Checkout code
      uses: actions/checkout@v4

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: 8.1
        extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd, exif, iconv

    - name: Setup Node.js
      uses: actions/setup-node@v4
      with:
        node-version: '18'
        cache: 'npm'
        cache-dependency-path: frontend/package-lock.json

    - name: Install backend dependencies
      run: composer install --no-progress --prefer-dist --optimize-autoloader

    - name: Install frontend dependencies
      working-directory: ./frontend
      run: npm ci

    - name: Copy environment file
      run: cp .env.example .env

    - name: Generate application key
      run: php artisan key:generate

    - name: Run database migrations
      run: php artisan migrate

    - name: Build frontend
      working-directory: ./frontend
      run: npm run build

    - name: Start Laravel server
      run: php artisan serve &
      env:
        APP_ENV: testing

    - name: Run Dusk tests
      run: php artisan dusk
      env:
        APP_URL: http://127.0.0.1:8000

    - name: Upload Dusk screenshots
      uses: actions/upload-artifact@v3
      if: failure()
      with:
        name: dusk-screenshots
        path: tests/Browser/screenshots

  code-quality:
    runs-on: ubuntu-latest

    steps:
    - name: Checkout code
      uses: actions/checkout@v4

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: 8.1
        extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite, bcmath, soap, intl, gd, exif, iconv

    - name: Install Composer dependencies
      run: composer install --no-progress --prefer-dist --optimize-autoloader

    - name: Run PHP CS Fixer
      run: vendor/bin/php-cs-fixer fix --dry-run --diff --verbose

    - name: Run PHPStan
      run: vendor/bin/phpstan analyse

    - name: Setup Node.js
      uses: actions/setup-node@v4
      with:
        node-version: '18'
        cache: 'npm'
        cache-dependency-path: frontend/package-lock.json

    - name: Install frontend dependencies
      working-directory: ./frontend
      run: npm ci

    - name: Run ESLint
      working-directory: ./frontend
      run: npm run lint

    - name: Run Prettier check
      working-directory: ./frontend
      run: npm run format:check
```

---

## Package Configuration Files

### Backend Test Configuration

#### `phpunit.xml` (Update existing file)
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="./vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <include>
            <directory suffix=".php">./app</directory>
        </include>
        <exclude>
            <directory suffix=".php">./app/Console</directory>
            <directory suffix=".php">./app/Exceptions</directory>
            <directory suffix=".php">./app/Http/Middleware</directory>
        </exclude>
        <report>
            <clover outputFile="coverage.xml"/>
            <html outputDirectory="coverage-html"/>
        </report>
    </coverage>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="TELESCOPE_ENABLED" value="false"/>
    </php>
</phpunit>
```

### Frontend Test Configuration

#### `frontend/vitest.config.js`
```javascript
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { resolve } from 'path'

export default defineConfig({
  plugins: [vue()],
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./tests/setup.js'],
    coverage: {
      provider: 'c8',
      reporter: ['text', 'lcov', 'html'],
      reportsDirectory: './coverage',
      exclude: [
        'node_modules/',
        'tests/',
        '**/*.d.ts',
        '**/*.config.js',
        '**/dist/**'
      ],
      thresholds: {
        global: {
          branches: 80,
          functions: 80,
          lines: 80,
          statements: 80
        }
      }
    }
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, './src')
    }
  }
})
```

#### `frontend/tests/setup.js`
```javascript
import { vi } from 'vitest'
import { config } from '@vue/test-utils'

// Mock global objects
global.ResizeObserver = vi.fn().mockImplementation(() => ({
  observe: vi.fn(),
  unobserve: vi.fn(),
  disconnect: vi.fn(),
}))

// Mock Canvas API (for Konva.js)
HTMLCanvasElement.prototype.getContext = vi.fn().mockReturnValue({
  fillRect: vi.fn(),
  clearRect: vi.fn(),
  getImageData: vi.fn().mockReturnValue({
    data: new Array(4),
  }),
  putImageData: vi.fn(),
  createImageData: vi.fn().mockReturnValue([]),
  setTransform: vi.fn(),
  drawImage: vi.fn(),
  save: vi.fn(),
  fillText: vi.fn(),
  restore: vi.fn(),
  beginPath: vi.fn(),
  moveTo: vi.fn(),
  lineTo: vi.fn(),
  closePath: vi.fn(),
  stroke: vi.fn(),
  translate: vi.fn(),
  scale: vi.fn(),
  rotate: vi.fn(),
  arc: vi.fn(),
  fill: vi.fn(),
  measureText: vi.fn().mockReturnValue({ width: 0 }),
  transform: vi.fn(),
  rect: vi.fn(),
  clip: vi.fn(),
})

// Mock WebSocket
global.WebSocket = vi.fn().mockImplementation(() => ({
  send: vi.fn(),
  close: vi.fn(),
  readyState: 1,
  addEventListener: vi.fn(),
  removeEventListener: vi.fn(),
}))

// Global test plugins
config.global.plugins = []
```

#### `frontend/package.json` (Add test scripts)
```json
{
  "scripts": {
    "test": "vitest",
    "test:unit": "vitest run",
    "test:coverage": "vitest run --coverage",
    "test:watch": "vitest",
    "test:ui": "vitest --ui",
    "lint": "eslint . --ext .vue,.js,.jsx,.cjs,.mjs,.ts,.tsx,.cts,.mts --fix --ignore-path .gitignore",
    "format": "prettier --write src/",
    "format:check": "prettier --check src/",
    "type-check": "vue-tsc --noEmit"
  },
  "devDependencies": {
    "@vitest/ui": "^1.0.0",
    "@vue/test-utils": "^2.4.0",
    "jsdom": "^23.0.0",
    "vitest": "^1.0.0",
    "prettier": "^3.0.0",
    "eslint": "^8.0.0",
    "vue-tsc": "^1.8.0"
  }
}
```

---

## Quality Gates & Branch Protection

### Branch Protection Rules
Configure in GitHub Settings > Branches:

```yaml
Branch protection rules for 'main':
  - Require pull request reviews before merging: ✓
  - Require status checks to pass before merging: ✓
    - backend-tests
    - frontend-tests
    - code-quality
  - Require branches to be up to date before merging: ✓
  - Require conversation resolution before merging: ✓
  - Restrict pushes that create files larger than 100MB: ✓
```

### Status Check Configuration
Required status checks:
- `backend-tests`
- `frontend-tests` 
- `code-quality`
- `e2e-tests` (for production releases)

---

## Deployment Workflow

### Staging Deployment
Create `.github/workflows/deploy-staging.yml`:

```yaml
name: Deploy to Staging

on:
  push:
    branches: [ develop ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/develop'
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v4

    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: 8.1

    - name: Setup Node.js
      uses: actions/setup-node@v4
      with:
        node-version: '18'

    - name: Install dependencies
      run: |
        composer install --no-dev --optimize-autoloader
        cd frontend && npm ci && npm run build

    - name: Deploy to staging
      run: |
        # Add your deployment script here
        echo "Deploying to staging environment..."
```

### Production Deployment
Create `.github/workflows/deploy-production.yml`:

```yaml
name: Deploy to Production

on:
  release:
    types: [published]

jobs:
  deploy:
    runs-on: ubuntu-latest
    environment: production
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v4

    - name: Run full test suite
      uses: ./.github/workflows/tests.yml

    - name: Deploy to production
      run: |
        # Add your production deployment script here
        echo "Deploying to production environment..."
```

---

## Code Quality Tools

### PHP Code Style (PHP CS Fixer)
Create `.php-cs-fixer.dist.php`:

```php
<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude(['bootstrap', 'storage', 'vendor'])
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
    ])
    ->setFinder($finder);
```

### Static Analysis (PHPStan)
Create `phpstan.neon`:

```neon
parameters:
    level: 8
    paths:
        - app
    excludePaths:
        - app/Console/Kernel.php
        - app/Exceptions/Handler.php
        - app/Http/Kernel.php
    ignoreErrors:
        - '#Call to an undefined method Illuminate\\Database\\Eloquent\\Builder#'
```

### ESLint Configuration
Create `frontend/.eslintrc.js`:

```javascript
module.exports = {
  root: true,
  env: {
    browser: true,
    es2021: true,
    node: true,
  },
  extends: [
    'eslint:recommended',
    '@vue/eslint-config-typescript',
    '@vue/eslint-config-prettier',
  ],
  rules: {
    'vue/multi-word-component-names': 'off',
    '@typescript-eslint/no-unused-vars': 'error',
    'prefer-const': 'error',
    'no-var': 'error',
  },
}
```

### Prettier Configuration
Create `frontend/.prettierrc`:

```json
{
  "semi": false,
  "singleQuote": true,
  "tabWidth": 2,
  "trailingComma": "es5",
  "printWidth": 80,
  "vueIndentScriptAndStyle": false
}
```

---

## Environment Variables for CI

### GitHub Secrets Setup
Add these secrets in GitHub Settings > Secrets:

```bash
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sedona_sax_editor_test
DB_USERNAME=root
DB_PASSWORD=password

# Application
APP_KEY=base64:generated_key_here
APP_ENV=testing
APP_DEBUG=true

# Testing
CODECOV_TOKEN=your_codecov_token
```

---

## Performance Monitoring

### Performance Testing Workflow
Create `.github/workflows/performance.yml`:

```yaml
name: Performance Tests

on:
  schedule:
    - cron: '0 2 * * *'  # Run daily at 2 AM
  workflow_dispatch:

jobs:
  performance:
    runs-on: ubuntu-latest

    steps:
    - name: Checkout code
      uses: actions/checkout@v4

    - name: Setup Node.js
      uses: actions/setup-node@v4
      with:
        node-version: '18'

    - name: Install dependencies
      working-directory: ./frontend
      run: npm ci

    - name: Run Lighthouse CI
      run: |
        npm install -g @lhci/cli
        lhci autorun
      env:
        LHCI_GITHUB_APP_TOKEN: ${{ secrets.LHCI_GITHUB_APP_TOKEN }}

    - name: Run performance tests
      working-directory: ./frontend
      run: npm run test:performance
```

---

## Notification Setup

### Slack Notifications
Add to workflow files:

```yaml
    - name: Notify Slack on failure
      if: failure()
      uses: 8398a7/action-slack@v3
      with:
        status: failure
        text: 'Tests failed on ${{ github.ref }}'
      env:
        SLACK_WEBHOOK_URL: ${{ secrets.SLACK_WEBHOOK_URL }}
```

### Email Notifications
GitHub automatically sends email notifications for failed builds to repository owners and collaborators.

---

## Monitoring & Metrics

### Test Metrics Dashboard
Set up monitoring for:
- Test execution time trends
- Coverage percentage over time
- Failed test frequency
- Performance regression detection

### Recommended Tools
- **Codecov**: Code coverage tracking
- **Lighthouse CI**: Performance monitoring
- **GitHub Insights**: Built-in repository analytics
- **Dependabot**: Automated dependency updates

---

*Update this document as CI/CD requirements evolve.*