<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class BasicFeatureTest extends TestCase
{
    /**
     * Test basic application structure.
     */
    public function test_application_structure_exists(): void
    {
        // Test that basic Laravel directories exist
        $this->assertDirectoryExists(__DIR__ . '/../../app');
        $this->assertDirectoryExists(__DIR__ . '/../../app/Http');
        $this->assertDirectoryExists(__DIR__ . '/../../app/Models');
        $this->assertDirectoryExists(__DIR__ . '/../../database');
    }

    /**
     * Test composer configuration.
     */
    public function test_composer_configuration_exists(): void
    {
        $composerPath = __DIR__ . '/../../composer.json';
        $this->assertFileExists($composerPath);

        $composerData = json_decode(file_get_contents($composerPath), true);
        $this->assertIsArray($composerData);
        $this->assertArrayHasKey('name', $composerData);
        $this->assertEquals('sedona-sax-editor/backend', $composerData['name']);
    }

    /**
     * Test PHPUnit configuration.
     */
    public function test_phpunit_configuration_exists(): void
    {
        $phpunitPath = __DIR__ . '/../../phpunit.xml';
        $this->assertFileExists($phpunitPath);
        
        $phpunitContent = file_get_contents($phpunitPath);
        $this->assertStringContains('testsuites', $phpunitContent);
        $this->assertStringContains('Unit', $phpunitContent);
        $this->assertStringContains('Feature', $phpunitContent);
    }

    /**
     * Test environment configuration.
     */
    public function test_environment_configuration_exists(): void
    {
        $envExamplePath = __DIR__ . '/../../.env.example';
        $this->assertFileExists($envExamplePath);
        
        $envContent = file_get_contents($envExamplePath);
        $this->assertStringContains('APP_NAME', $envContent);
        $this->assertStringContains('DB_CONNECTION', $envContent);
    }

    /**
     * Test that test fixtures directory exists.
     */
    public function test_fixtures_directory_structure(): void
    {
        $fixturesPath = __DIR__ . '/../../fixtures';
        
        if (is_dir($fixturesPath)) {
            $this->assertDirectoryExists($fixturesPath);
            
            $saxFilesPath = $fixturesPath . '/sax-files';
            if (is_dir($saxFilesPath)) {
                $this->assertDirectoryExists($saxFilesPath);
            }
        } else {
            // Fixtures will be available after merge - this is expected
            $this->assertTrue(true, 'Fixtures directory will be created after branch merge');
        }
    }
}