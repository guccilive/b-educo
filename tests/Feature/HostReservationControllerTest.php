<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use App\Models\Reservation;

class HostReservationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;
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

    /**
     * @test
     */
    public function test_returns_reservations_filtered_by_date_range()
    {
      $user = User::factory()->create();

      $fromDate = '2021-03-03';
      $toDate   = '2021-04-04';

      // Reservation within the date range
      $reservation1 = Reservation::factory()->for($user)->create([
        'start_date' => '2021-03-01',
        'end_date'   => '2021-03-15'
      ]);

      $reservation2 = Reservation::factory()->for($user)->create([
        'start_date' => '2021-03-25',
        'end_date'   => '2021-04-15'
      ]);

      $reservation3 = Reservation::factory()->for($user)->create([
        'start_date' => '2021-03-25',
        'end_date'   => '2021-03-29'
      ]);

      // Reservation within the date range but belongs to another USER
      Reservation::factory()->create([
        'start_date' => '2021-03-05',
        'end_date'   => '2021-03-10'
      ]);

      // Reservation outside the date range
      Reservation::factory()->for($user)->create([
        'start_date' => '2021-02-25',
        'end_date'   => '2021-03-01'
      ]);

      Reservation::factory()->for($user)->create([
        'start_date' => '2021-05-01',
        'end_date'   => '2021-05-15'
      ]);

      $this->actingAs($user);

      // DB::enableQueryLog();

      $response = $this->getJson('/api/reservations?'.http_build_query([
        'from_date' => $fromDate,
        'to_date'    => $toDate
      ]));

      // dd($response->json());
      // dd(DB::getQueryLog());

      $response->assertJsonCount(3, 'data');

      $this->assertEquals([$reservation1->id, $reservation2->id, $reservation3->id], collect($response->json('data'))->pluck('id')->toArray());
    }

    /**
     * @test
     */
     public function test_filter_results_by_status()
     {
       $user = User::factory()->create();

       $reservation = Reservation::factory()->for($user)->create(['status' => Reservation::STATUS_ACTIVE]);

       $reservation2 = Reservation::factory()->create(['status' => Reservation::STATUS_CANCELLED]);

       $this->actingAs($user);

       // DB::enableQueryLog();

       $response = $this->getJson('/api/reservations?'.http_build_query([
         'status' => Reservation::STATUS_ACTIVE
       ]));

       $response->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $reservation->id);
     }

     /**
      * @test
      */
      public function test_filter_results_by_office()
      {
        $user = User::factory()->create();

        $office = Office::factory()->create();

        $reservation = Reservation::factory()->for($user)->for($office)->create();

        $reservation2 = Reservation::factory()->create(); // belongs to a different office

        $this->actingAs($user);

        // DB::enableQueryLog();

        $response = $this->getJson('/api/reservations?'.http_build_query([
          'office_id' => $office->id,
        ]));

        $response->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.id', $office->id);
      }
}
