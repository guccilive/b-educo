<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TagsControllerTest extends TestCase
{
  /**
   * @test
   */
    public function test_returns_list_of_all_tags()
    {
      $response = $this->get('/api/tags');

      $response->assertOk();

      $response->assertJsonCount(3, 'data');

      $this->assertNotNull($response->json('data')[0]['id']);
    }

}
