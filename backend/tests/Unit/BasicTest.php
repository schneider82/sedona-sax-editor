<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class BasicTest extends TestCase
{
    /**
     * Test that basic PHP functionality works.
     */
    public function test_basic_php_functionality(): void
    {
        $this->assertTrue(true);
        $this->assertEquals(2 + 2, 4);
        $this->assertIsString('hello world');
    }

    /**
     * Test array operations.
     */
    public function test_array_operations(): void
    {
        $array = [1, 2, 3, 4, 5];
        
        $this->assertCount(5, $array);
        $this->assertContains(3, $array);
        $this->assertEquals(15, array_sum($array));
    }

    /**
     * Test string operations.
     */
    public function test_string_operations(): void
    {
        $text = 'Sedona SAX Editor';
        
        $this->assertStringContains('SAX', $text);
        $this->assertEquals(17, strlen($text));
        $this->assertEquals('SEDONA SAX EDITOR', strtoupper($text));
    }
}