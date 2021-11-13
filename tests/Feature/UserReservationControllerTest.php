<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Models\User;
use App\Models\Office;
use App\Models\Reservation;

class UserReservationControllerTest extends TestCase
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
      $reservations = Reservation::factory()->for($user)->createMany([
        [
          'start_date' => '2021-03-01',
          'end_date'   => '2021-03-15',
        ],
        [
          'start_date' => '2021-03-25',
          'end_date'   => '2021-04-15',
        ],
        [
          'start_date' => '2021-03-25',
          'end_date'   => '2021-03-29'
        ],
        [
          'start_date' => '2021-03-01', // This reservation start before the from_date and end after the to_date
          'end_date'   => '2021-04-15' // it should be included on the serach result
        ]

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

      $response->assertJsonCount(4, 'data');

      $this->assertEquals($reservations->pluck('id')->toArray(), collect($response->json('data'))->pluck('id')->toArray());
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

        // dd($response->json());

        $response->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.office_id', $office->id);
      }

      /**
       * @test
      */
      public function can_make_reservation()
      {
        // $this->withoutExceptionHandling();

        $user = User::factory()->create();

        $office = Office::factory()->create([
          'price_per_day'    => 1_000,
          'monthly_discount' => 10,
        ]);

        $this->actingAs($user);

        $response = $this->postJson(route('api.reservation.create'), [
          'office_id' => $office->id,
          'start_date' => now()->addDays(1),
          'end_date' => now()->addDays(40),
        ]);

        // dd($response->json());

        $response->assertCreated()
                 ->assertJsonPath('data.price', 36000)
                 ->assertJsonPath('data.user_id', $user->id)
                 ->assertJsonPath('data.status', Reservation::STATUS_ACTIVE);

      }
      /**
       * @test
      */
      public function cannot_make_reservation_on_non_existing_office()
      {
        // $this->withoutExceptionHandling();

        $user = User::factory()->create();

        $office = Office::factory()->for($user)->create();

        $reservation = Reservation::factory(3)->for($office)->create();

        $this->actingAs($user);

        $response = $this->postJson(route('api.reservation.create'), [
          'office_id' => 10000,
          'start_date' => now()->addDays(1),
          'end_date' => now()->addDays(40),
        ]);

        // dd($response->json());

        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['office_id' => 'Invalid Office']);

      }

      /**
       * @test
      */
      public function cannot_make_reservation_on_his_own_office()
      {
        // $this->withoutExceptionHandling();

        $user = User::factory()->create();

        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->postJson(route('api.reservation.create'), [
          'office_id' => $office->id,
          'start_date' => now()->addDays(1),
          'end_date' => now()->addDays(40),
        ]);

        // dd($response->json());

        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['office_id' => 'You cannot make reservation on your own office.']);

      }

      /**
       * @test
      */
      public function cannot_make_reservation_on_booked_office()
      {
        // $this->withoutExceptionHandling();

        $user = User::factory()->create();

        $fromDate = '2021-10-05';
        $toDate   = '2021-10-06';

        $office = Office::factory()->create();

        Reservation::factory()->for($office)->create([
            'start_date' => '2021-10-01',
            'end_date'   => '2021-10-10',
        ]);


        $this->actingAs($user);

        $response = $this->postJson(route('api.reservation.create'), [
          'office_id'  => $office->id,
          'start_date' => $fromDate,
          'end_date'   => $toDate,
        ]);

        // dd($response->json());

        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['office_id' => 'You cannot make reservation during  this time.']);

      }

      /**
       * @test
      */
      public function cannot_make_reservation_on_a_booked_office_if_the_start_date_is_before_the_booked_start_date()
      {
        // $this->withoutExceptionHandling();

        $user = User::factory()->create();

        $fromDate = '2021-10-01';
        $toDate   = '2021-10-30';

        $office = Office::factory()->create();

        Reservation::factory()->for($office)->create([
            'start_date' => '2021-10-05',
            'end_date'   => '2021-10-10',
        ]);


        $this->actingAs($user);

        $response = $this->postJson(route('api.reservation.create'), [
          'office_id'  => $office->id,
          'start_date' => $fromDate,
          'end_date'   => $toDate,
        ]);

        // dd($response->json());

        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['office_id' => 'You cannot make reservation during  this time.']);

      }
      /**
       * @test
      */
      public function cannot_make_reservation_on_a_booked_office_if_the_start_date_is_before_the_booked_start_date_and_the_end_date_is_after_the_booked_end_date()
      {
        // $this->withoutExceptionHandling();

        $user = User::factory()->create();

        $fromDate = '2021-10-01';
        $toDate   = '2021-10-06';

        $office = Office::factory()->create();

        Reservation::factory()->for($office)->create([
            'start_date' => '2021-10-05',
            'end_date'   => '2021-10-10',
        ]);


        $this->actingAs($user);

        $response = $this->postJson(route('api.reservation.create'), [
          'office_id'  => $office->id,
          'start_date' => $fromDate,
          'end_date'   => $toDate,
        ]);

        // dd($response->json());

        $response->assertUnprocessable()
                 ->assertJsonValidationErrors(['office_id' => 'You cannot make reservation during  this time.']);

      }
}
