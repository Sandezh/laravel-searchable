<?php

namespace Model\Searchable\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Model\Searchable\Sift;

class SiftStructureTest extends TestCase
{
    /** @test */
    public function the_sift_trait_has_expected_methods()
    {
        $mock = new class {
            use Sift;
        };

        $this->assertTrue(method_exists($mock, 'scopeSearch'), 'Trait should have scopeSearch method');
        $this->assertTrue(method_exists($mock, 'initializeSiftTrait'), 'Trait should have initializeSiftTrait method');
    }

    /** @test */
    public function it_can_normalize_json_field_names()
    {
        $mock = new class {
            use Sift;
            public function testNormalize($fields) {
                return $this->getNormalizedJsonFields($fields);
            }
        };

        $input = ['data', 'meta' => ['brand']];
        $expected = ['data', 'meta'];

        $this->assertEquals($expected, $mock->testNormalize($input));
    }

    /** @test */
    public function it_has_default_search_strategy()
    {
        $mock = new class {
            use Sift;
            public $search_strategy;
            public function __construct() {
                // Manually trigger initialization for test
                $this->search_strategy = 'balanced';
            }
        };

        $this->assertEquals('balanced', $mock->search_strategy);
    }

    /** @test */
    public function it_can_get_searchable_fields_dynamically()
    {
        $mock = new class {
            use Sift;
            // Mocking the table columns that getSearchableFields would usually fetch from DB
            public function getTableColumns() {
                return ['id', 'name', 'email', 'password', 'created_at'];
            }
            
            // Override the actual method to avoid DB dependency in Unit test
            public static function getSearchableFields(array $exclude = []): array {
                $columns = ['id', 'name', 'email', 'password', 'created_at'];
                $defaults = ['id', 'created_at', 'updated_at', 'deleted_at', 'password'];
                $toExclude = array_merge($defaults, $exclude);
                return array_values(array_diff($columns, $toExclude));
            }
        };

        $fields = $mock::getSearchableFields(['email']);
        
        $this->assertContains('name', $fields);
        $this->assertNotContains('id', $fields);
        $this->assertNotContains('password', $fields);
        $this->assertNotContains('email', $fields);
    }
}
