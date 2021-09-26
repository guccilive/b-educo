<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Tag;
use App\Models\User;
use App\Models\Image;
use App\Models\Office;
use App\Models\Reservation;

class OfficeControllerTest extends TestCase
{
    use RefreshDatabase;
    /**
     * @test
     */
    public function test_returns_list_of_all_offices()
    {
      Office::factory(3)->create();

      $response = $this->get('/api/offices');

      // $response->dump();
      $response->assertOk();
      $response->assertJsonCount(3, 'data');
      $this->assertNotNull($response->json('data')[0]['id']);
      $this->assertNotNull($response->json('meta'));
      $this->assertNotNull($response->json('links'));
    }

    /**
     * @test
     */
    public function test_returns_only_approved_and_not_hidden_offices()
    {
      Office::factory(3)->create();

      Office::factory()->create(['hidden' => true]);
      Office::factory()->create(['approval_status' => Office::APPROVAL_PENDING]);

      $response = $this->get('/api/offices');

      // $response->dump();
      $response->assertOk();
      $response->assertJsonCount(3, 'data');
      $this->assertNotNull($response->json('data')[0]['id']);
      $this->assertNotNull($response->json('meta'));
      $this->assertNotNull($response->json('links'));
    }

    /**
     * @test
     */
    public function test_filters_by_hostID()
    {
      Office::factory(3)->create();

      $host = User::factory()->create();

      $office = Office::factory()->for($host)->create();

      $response = $this->get('/api/offices?host_id='.$host->id);

      // $response->dump();
      $response->assertOk();
      $response->assertJsonCount(1, 'data');
      $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }

    /**
     * @test
     */
    public function test_filters_by_userID() // Filter by user who placed reservation
    {
      Office::factory(3)->create();

      $user = User::factory()->create();
      $office = Office::factory()->create();

      Reservation::factory()->for(Office::factory())->create(); // This reservation shouldn't be returned
      Reservation::factory()->for($office)->for($user)->create(); // Only this recervation should be returned

      $response = $this->get('/api/offices?user_id='.$user->id);

      // $response->dump();
      $response->assertOk();
      $response->assertJsonCount(1, 'data');
      $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }

    /**
     * @test
     */
    public function test_returns_office_with_its_user_images_tags_relationship()
    {
      $user = User::factory()->create();
      $tag = Tag::factory()->create();
      $office = Office::factory()->for($user)->create();

      $office->tags()->attach($tag);
      $office->images()->create(['path' => 'test_image.png']);


      $response = $this->get('/api/offices');

      // $response->dump();
      $response->assertOk();

      $this->assertIsArray($response->json('data')[0]['tags']);
      $this->assertCount(1, $response->json('data')[0]['tags']); // We are expecting only one tags in the array
      $this->assertIsArray($response->json('data')[0]['images']);
      $this->assertCount(1, $response->json('data')[0]['images']);// We are expecting only one imades in the array
      $this->assertEquals($user->id, $response->json('data')[0]['user']['id']);
    }


    /**
     * @test
     */
    public function test_returns_number_of_active_reservations()
    {
      $office = Office::factory()->create();

      Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_ACTIVE]); // We are expecting to return this reservation
      Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_CANCELLED]); // Not epecting to return this reservation

      $response = $this->get('/api/offices');

      $response->assertOk();
      // $response->dump();

      $this->assertEquals(1, $response->json('data')[0]['reservations_count']);

    }
}
