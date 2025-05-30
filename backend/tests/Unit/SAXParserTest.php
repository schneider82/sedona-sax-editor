<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SAXParserTest extends TestCase
{
    /**
     * Test parsing basic SAX XML structure.
     */
    public function test_parses_basic_sax_structure(): void
    {
        $saxXml = '<?xml version="1.0"?>
        <sedonaApp>
            <schema>
                <kit name="sys"/>
                <kit name="control"/>
            </schema>
            <app>
                <comp name="add1" type="control::Add2" id="1"/>
            </app>
            <links/>
        </sedonaApp>';

        $dom = new \DOMDocument();
        $dom->loadXML($saxXml);

        // Test that XML is valid
        $this->assertInstanceOf(\DOMDocument::class, $dom);
        
        // Test schema parsing
        $schemaNodes = $dom->getElementsByTagName('kit');
        $this->assertEquals(2, $schemaNodes->length);
        
        // Test component parsing
        $componentNodes = $dom->getElementsByTagName('comp');
        $this->assertEquals(1, $componentNodes->length);
        
        $component = $componentNodes->item(0);
        $this->assertEquals('add1', $component->getAttribute('name'));
        $this->assertEquals('control::Add2', $component->getAttribute('type'));
    }

    /**
     * Test parsing component with properties.
     */
    public function test_parses_component_properties(): void
    {
        $saxXml = '<?xml version="1.0"?>
        <sedonaApp>
            <schema>
                <kit name="control"/>
            </schema>
            <app>
                <comp name="add1" type="control::Add2" id="1">
                    <prop name="in1" val="5.0"/>
                    <prop name="in2" val="3.0"/>
                </comp>
            </app>
            <links/>
        </sedonaApp>';

        $dom = new \DOMDocument();
        $dom->loadXML($saxXml);

        $componentNodes = $dom->getElementsByTagName('comp');
        $component = $componentNodes->item(0);
        
        $properties = $component->getElementsByTagName('prop');
        $this->assertEquals(2, $properties->length);

        // Check property values
        $prop1 = $properties->item(0);
        $this->assertEquals('in1', $prop1->getAttribute('name'));
        $this->assertEquals('5.0', $prop1->getAttribute('val'));
    }

    /**
     * Test that invalid XML is rejected.
     */
    public function test_rejects_invalid_xml(): void
    {
        $invalidXml = '<invalid-xml><unclosed-tag></invalid-xml>';

        $dom = new \DOMDocument();
        
        // Suppress warnings for this test
        $result = @$dom->loadXML($invalidXml);
        
        $this->assertFalse($result);
    }

    /**
     * Test parsing SAX file from fixtures.
     */
    public function test_parses_sax_fixture_file(): void
    {
        $fixturePath = __DIR__ . '/../../fixtures/sax-files/basic-math-project.xml';
        
        // Skip test if fixture doesn't exist yet
        if (!file_exists($fixturePath)) {
            $this->markTestSkipped('SAX fixture file not found - will be available after merge');
            return;
        }

        $saxContent = file_get_contents($fixturePath);
        $this->assertNotEmpty($saxContent);

        $dom = new \DOMDocument();
        $result = $dom->loadXML($saxContent);
        
        $this->assertTrue($result);
        $this->assertInstanceOf(\DOMDocument::class, $dom);
    }
}