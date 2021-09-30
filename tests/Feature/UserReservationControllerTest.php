<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Reservation;

class UserReservationControllerTest extends TestCase
{
    /**
     * @test
     */
    public function test_returns_reservations_that_belongs_to_user()
    {
      $user = User::factory()->create();

      $reservation = Reservation::factory()->for($user)->create();

      $image = $reservation->office->images()->create([
        'path' => 'Test2_Image.jpg'
      ]);

      $reservation->office->update(['featured_image_id' => $image->id]);

      Reservation::factory(2)->for($user)->create();
      Reservation::factory(3)->create();

      $this->actingAs($user);

      $response = $this->getJson('/api/reservations');

      // dd($response->json());

      $response->assertJsonStructure(['data', 'meta', 'links'])
               ->assertJsonStructure(['data' => ['*' => ['id', 'office']]])
               ->assertJsonPath('data.0.office.featured_image.id', $image->id)
               ->assertJsonCount(3, 'data');
    }
}
