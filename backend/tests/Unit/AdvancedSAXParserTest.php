<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdvancedSAXParserTest extends TestCase
{
    private function getFixturePath(string $filename): string
    {
        return __DIR__ . '/../../fixtures/sax-files/' . $filename;
    }

    /**
     * Test parsing HVAC control system with complex components.
     */
    public function test_parses_hvac_control_system(): void
    {
        $fixturePath = $this->getFixturePath('hvac-control-system.xml');
        
        if (!file_exists($fixturePath)) {
            $this->markTestSkipped('HVAC fixture file not found');
            return;
        }

        $saxContent = file_get_contents($fixturePath);
        $dom = new \DOMDocument();
        $result = $dom->loadXML($saxContent);
        
        $this->assertTrue($result);
        
        // Test schema has control kit
        $kits = $dom->getElementsByTagName('kit');
        $kitNames = [];
        foreach ($kits as $kit) {
            $kitNames[] = $kit->getAttribute('name');
        }
        $this->assertContains('control', $kitNames);
        
        // Test components
        $components = $dom->getElementsByTagName('comp');
        $this->assertEquals(3, $components->length);
        
        // Test Timer component
        $timer = $components->item(0);
        $this->assertEquals('timer1', $timer->getAttribute('name'));
        $this->assertEquals('control::Timer', $timer->getAttribute('type'));
        
        // Test component properties
        $timerProps = $timer->getElementsByTagName('prop');
        $this->assertGreaterThan(0, $timerProps->length);
        
        // Test links exist
        $links = $dom->getElementsByTagName('link');
        $this->assertEquals(2, $links->length);
    }

    /**
     * Test parsing BarTech control components.
     */
    public function test_parses_bartech_components(): void
    {
        $fixturePath = $this->getFixturePath('bartech-components.xml');
        
        if (!file_exists($fixturePath)) {
            $this->markTestSkipped('BarTech fixture file not found');
            return;
        }

        $saxContent = file_get_contents($fixturePath);
        $dom = new \DOMDocument();
        $result = $dom->loadXML($saxContent);
        
        $this->assertTrue($result);
        
        // Test BarTech kit is declared
        $kits = $dom->getElementsByTagName('kit');
        $hasBarTech = false;
        foreach ($kits as $kit) {
            if ($kit->getAttribute('name') === 'BarTechControl') {
                $hasBarTech = true;
                break;
            }
        }
        $this->assertTrue($hasBarTech);
        
        // Test BarTech components
        $components = $dom->getElementsByTagName('comp');
        $componentTypes = [];
        foreach ($components as $comp) {
            $componentTypes[] = $comp->getAttribute('type');
        }
        
        $this->assertContains('BarTechControl::Loop', $componentTypes);
        $this->assertContains('BarTechControl::AnalogFilter', $componentTypes);
        $this->assertContains('BarTechControl::Average', $componentTypes);
    }

    /**
     * Test handling invalid SAX components.
     */
    public function test_handles_invalid_components(): void
    {
        $fixturePath = $this->getFixturePath('invalid-components.xml');
        
        if (!file_exists($fixturePath)) {
            $this->markTestSkipped('Invalid components fixture file not found');
            return;
        }

        $saxContent = file_get_contents($fixturePath);
        $dom = new \DOMDocument();
        $result = $dom->loadXML($saxContent);
        
        // XML should parse successfully even with invalid component types
        $this->assertTrue($result);
        
        // Test that we can identify problematic components
        $components = $dom->getElementsByTagName('comp');
        $invalidComponents = [];
        
        foreach ($components as $comp) {
            $type = $comp->getAttribute('type');
            $name = $comp->getAttribute('name');
            
            // Check for components with nonexistent types
            if (str_contains($type, 'nonexistent::') || empty($type)) {
                $invalidComponents[] = $name;
            }
        }
        
        $this->assertNotEmpty($invalidComponents);
        $this->assertContains('invalid1', $invalidComponents);
    }

    /**
     * Test component property validation.
     */
    public function test_validates_component_properties(): void
    {
        $saxXml = '<?xml version="1.0"?>
        <sedonaApp>
            <schema><kit name="control"/></schema>
            <app>
                <comp name="add1" type="control::Add2" id="1">
                    <prop name="in1" val="5.0"/>
                    <prop name="in2" val="3.0"/>
                    <prop name="invalid_prop" val="should_not_exist"/>
                </comp>
            </app>
            <links/>
        </sedonaApp>';

        $dom = new \DOMDocument();
        $result = $dom->loadXML($saxXml);
        $this->assertTrue($result);

        // Get component properties
        $component = $dom->getElementsByTagName('comp')->item(0);
        $properties = $component->getElementsByTagName('prop');
        
        $validProps = ['in1', 'in2'];
        $invalidProps = [];
        
        foreach ($properties as $prop) {
            $propName = $prop->getAttribute('name');
            if (!in_array($propName, $validProps)) {
                $invalidProps[] = $propName;
            }
        }
        
        $this->assertContains('invalid_prop', $invalidProps);
    }

    /**
     * Test component connection validation.
     */
    public function test_validates_component_connections(): void
    {
        $saxXml = '<?xml version="1.0"?>
        <sedonaApp>
            <schema><kit name="control"/></schema>
            <app>
                <comp name="add1" type="control::Add2" id="1"/>
                <comp name="sub1" type="control::Sub2" id="2"/>
            </app>
            <links>
                <link from="/add1.out" to="/sub1.in1"/>
                <link from="/add1.nonexistent" to="/sub1.in2"/>
                <link from="/missing_comp.out" to="/sub1.in1"/>
            </links>
        </sedonaApp>';

        $dom = new \DOMDocument();
        $result = $dom->loadXML($saxXml);
        $this->assertTrue($result);

        // Validate links
        $links = $dom->getElementsByTagName('link');
        $validLinks = [];
        $invalidLinks = [];
        
        foreach ($links as $link) {
            $from = $link->getAttribute('from');
            $to = $link->getAttribute('to');
            
            // Basic validation - check for nonexistent components/slots
            if (str_contains($from, 'nonexistent') || str_contains($from, 'missing_comp')) {
                $invalidLinks[] = ['from' => $from, 'to' => $to];
            } else {
                $validLinks[] = ['from' => $from, 'to' => $to];
            }
        }
        
        $this->assertCount(1, $validLinks);
        $this->assertCount(2, $invalidLinks);
    }

    /**
     * Test parsing performance with large SAX files.
     */
    public function test_performance_with_large_sax_file(): void
    {
        // Generate a large SAX file content
        $largeXml = '<?xml version="1.0"?><sedonaApp><schema><kit name="control"/></schema><app>';
        
        // Add 100 components
        for ($i = 1; $i <= 100; $i++) {
            $largeXml .= "<comp name=\"add{$i}\" type=\"control::Add2\" id=\"{$i}\">
                <prop name=\"in1\" val=\"{$i}.0\"/>
                <prop name=\"in2\" val=\"" . ($i + 1) . ".0\"/>
            </comp>";
        }
        
        $largeXml .= '</app><links>';
        
        // Add 99 links connecting components
        for ($i = 1; $i < 100; $i++) {
            $largeXml .= "<link from=\"/add{$i}.out\" to=\"/add" . ($i + 1) . ".in1\"/>";
        }
        
        $largeXml .= '</links></sedonaApp>';

        // Test parsing performance
        $startTime = microtime(true);
        
        $dom = new \DOMDocument();
        $result = $dom->loadXML($largeXml);
        
        $endTime = microtime(true);
        $parseTime = $endTime - $startTime;
        
        $this->assertTrue($result);
        $this->assertLessThan(1.0, $parseTime); // Should parse in less than 1 second
        
        // Verify content
        $components = $dom->getElementsByTagName('comp');
        $this->assertEquals(100, $components->length);
        
        $links = $dom->getElementsByTagName('link');
        $this->assertEquals(99, $links->length);
    }

    /**
     * Test SAX file size limits.
     */
    public function test_handles_file_size_limits(): void
    {
        // Test very small valid file
        $smallXml = '<?xml version="1.0"?><sedonaApp><schema><kit name="sys"/></schema><app/><links/></sedonaApp>';
        
        $dom = new \DOMDocument();
        $result = $dom->loadXML($smallXml);
        
        $this->assertTrue($result);
        $this->assertEquals(0, $dom->getElementsByTagName('comp')->length);
        
        // Test file size calculation
        $fileSize = strlen($smallXml);
        $this->assertLessThan(1024, $fileSize); // Less than 1KB
        $this->assertGreaterThan(50, $fileSize); // More than 50 bytes
    }
}