<?php

namespace Model\Searchable\Tests\Feature;

use Model\Searchable\Tests\TestCase;
use Model\Searchable\Tests\Models\User;
use Model\Searchable\Tests\Models\Post;

class SearchTest extends TestCase
{
    /** @test */
    public function it_can_search_standard_fields()
    {
        User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        User::create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $results = User::search('John')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results->first()->name);
    }

    /** @test */
    public function it_can_search_json_fields()
    {
        User::create([
            'name' => 'John Doe', 
            'email' => 'john@example.com',
            'data' => json_encode(['city' => 'New York', 'brand' => 'Apple'])
        ]);

        $results = User::search('Apple')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results->first()->name);
    }

    /** @test */
    public function it_returns_empty_results_when_no_match_found()
    {
        User::create(['name' => 'John Doe', 'email' => 'john@example.com']);

        $results = User::search('NonExistent')->get();

        $this->assertCount(0, $results);
    }

    /** @test */
    public function it_handles_null_search_term()
    {
        User::create(['name' => 'John Doe', 'email' => 'john@example.com']);

        $results = User::search(null)->get();

        $this->assertCount(1, $results);
    }

    /** @test */
    public function it_can_search_relationships()
    {
        $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user->posts()->create(['title' => 'Laravel Searchable Package', 'content' => 'Content here']);

        $results = User::search('Laravel')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results->first()->name);
    }
}
