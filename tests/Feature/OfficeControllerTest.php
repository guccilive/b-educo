<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use App\Models\Tag;
use App\Models\User;
use App\Models\Image;
use App\Models\Office;
use App\Models\Reservation;
use App\Notifications\OfficePendingApprovalNotification;
use Laravel\Sanctum\Sanctum;

class OfficeControllerTest extends TestCase
{
    use LazilyRefreshDatabase;
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
    public function test_returns_offices_include_unapproved_and_hidden_if_filtering_for_current_logged_in_user()
    {
      $user = User::factory()->create();

      Office::factory(3)->for($user)->create();

      Office::factory()->for($user)->create(['hidden' => true]);
      Office::factory()->for($user)->create(['approval_status' => Office::APPROVAL_PENDING]);

      $this->actingAs($user);

      $response = $this->get('/api/offices?user_id='.$user->id);


      // $response->dump();
      $response->assertOk();
      $response->assertJsonCount(5, 'data');
      $this->assertNotNull($response->json('data')[0]['id']);
      $this->assertNotNull($response->json('meta'));
      $this->assertNotNull($response->json('links'));
    }

    /**
     * @test
     */
    public function test_filters_by_userID()
    {
      Office::factory(3)->create();

      $host = User::factory()->create();

      $office = Office::factory()->for($host)->create();

      $response = $this->get('/api/offices?user_id='.$host->id);

      // $response->dump();
      $response->assertOk();
      $response->assertJsonCount(1, 'data');
      $this->assertEquals($office->id, $response->json('data')[0]['id']);
    }

    /**
     * @test
     */
    public function test_filters_by_visitorID() // Filter by user who placed reservation
    {
      Office::factory(3)->create();

      $user = User::factory()->create();
      $office = Office::factory()->create();

      Reservation::factory()->for(Office::factory())->create(); // This reservation shouldn't be returned
      Reservation::factory()->for($office)->for($user)->create(); // Only this recervation should be returned

      $response = $this->get('/api/offices?visitor_id='.$user->id);

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


    /**
     * @test
     */
     public function test_returns_office_ordered_by_distance_when_coordinates_are_provided()
     {

       // The farest office
       $office1 = Office::factory()->create([
         'lat' => '52.64120885327593',
         'lng' => '-1.11115359442758',
         'title' => 'Leicester'
       ]);

       // The nearest office
       $office2 = Office::factory()->create([
         'lat' => '51.88251871561174',
         'lng' => '-0.42793612379441487',
         'title' => 'Luton'
       ]);

       // If latitude and longetude are provided, we will expect to order by the nearest offfice
       $response = $this->get('/api/offices?lat=51.495784636352475&lng=-0.1758245173622218ß');
       // $response->dump();
       $response->assertOk();
       $this->assertEquals('Luton', $response->json('data')[0]['title']);
       $this->assertEquals('Leicester', $response->json('data')[1]['title']);

       // If latitude and longetude are not provided, we will expect to order by the oldest office in DB
       $response = $this->get('/api/offices');
       $response->assertOk();
       $this->assertEquals('Leicester', $response->json('data')[0]['title']);
       $this->assertEquals('Luton', $response->json('data')[1]['title']);

     }

     /**
      * @test
      */
      public function test_returns_a_single_office()
      {
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->tags()->attach($tag);
        $office->images()->create(['path' => 'test_image.png']);

        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_ACTIVE]); // We are expecting to return this reservation
        Reservation::factory()->for($office)->create(['status' => Reservation::STATUS_CANCELLED]); // Not epecting to return this reservation

        $response = $this->get('/api/offices/'.$office->id);

        // $response->dump();

        $response->assertOk();

        $this->assertEquals(1, $response->json('data')['reservations_count']);
        $this->assertIsArray($response->json('data')['tags']);
        $this->assertCount(1, $response->json('data')['tags']); // We are expecting only one tags in the array
        $this->assertIsArray($response->json('data')['images']);
        $this->assertCount(1, $response->json('data')['images']);// We are expecting only one imades in the array
        $this->assertEquals($user->id, $response->json('data')['user']['id']);
      }

      /*
      * @test
      */
      public function test_create_new_office()
      {
        $admin = User::factory()->create(['is_admin' => true]);

        $user = User::factory()->createQuietly();

        Notification::fake();

        $tag1 = Tag::factory()->create();
        $tag2 = Tag::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson('/api/offices', [
          'title' => 'Educo Kinshasa Office',
          'description' => 'The Office is so big that noone can aforde it. Just let me kmow manager!',
          'lat' => '52.64120885327593',
          'lng' => '-1.11115359442758',
          'address_line1' => 'Address of the office',
          'price_per_day' => 10_000,
          'monthly_discount' => 5,
          'tags' => [
            $tag1->id, $tag2->id
          ]

        ]);

        // dd($response->json());

        $response->assertCreated()
                 ->assertJsonPath('data.title', 'Educo Kinshasa Office')
                 ->assertJsonPath('data.approval_status', Office::APPROVAL_PENDING)
                 ->assertJsonPath('data.user.id', $user->id)
                 ->assertJsonCount(2, 'data.tags');

         $this->assertDatabaseHas('offices', [
             'id' => $response->json('data.id')
         ]);

         Notification::assertSentTo($admin, OfficePendingApprovalNotification::class);
      }


      /*
      * @test
      */
      public function test_not_allowing_creating_new_office_if_scope_is_not_provided()
      {
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['office.create']);

       $response = $this->postJson('/api/offices');

        $this->assertNotEquals(Response::HTTP_FORBIDDEN, $response->status());
      }

      /*
      * @test
      */
      public function test_update_existing_office()
      {
        $user = User::factory()->create();
        $tags = Tag::factory(4)->create();
        $anotherTag = Tag::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->tags()->attach($tags);

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'.$office->id, [
          'title' => 'Educo Minneapolis US Office',
          'tags'  => [ $tags[0]->id, $anotherTag->id ]

        ]);

        // dd($response->json());

        $response->assertOk()
                 ->assertJsonCount(2, 'data.tags')
                 ->assertJsonPath('data.tags.0.id', $tags[0]->id) // To make sure the tag we have on db are exactly this one
                 ->assertJsonPath('data.tags.1.id', $anotherTag->id) // and this one
                 ->assertJsonPath('data.title', 'Educo Minneapolis US Office');
      }

      /*
      * @test
      */
      public function test_cannot_update_office_belongs_to_other_user()
      {
        $user      = User::factory()->create();
        $otherUser = User::factory()->create();
        $office    = Office::factory()->for($otherUser)->create();

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'.$office->id, [
          'title' => 'Educo Minneapolis US Office'

        ]);

        // dd($response->status());

        $response->assertStatus(Response::HTTP_FORBIDDEN);
      }


      /*
      * @test
      * Set the Aproval Status ot the office to Pending if
      * one the params below changed for admin reviews
      * ['lat','lng', 'price_per_date']
      */
      public function test_maks_office_as_pending_if_isDirty()
      {
        $admin = User::factory()->create(['is_admin' => true]);

        Notification::fake();

        $user   = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'.$office->id, [
          'lat' => 20.64120885327593

        ]);

        $response->assertOk();

        $this->assertDatabaseHas('offices', [
          'id'              =>  $office->id,
          'approval_status' =>  Office::APPROVAL_PENDING,
        ]);

        Notification::assertSentTo($admin, OfficePendingApprovalNotification::class);
      }

      /*
      * @test
      */
      public function test_update_the_featured_image_of_an_office()
      {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create(['path' => 'Test_Image.png']);

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'.$office->id, [
          'featured_image_id' => $image->id,
        ]);

        // dd($response->json());

        $response->assertOk()
                 ->assertJsonPath('data.featured_image_id', $image->id);
      }

      /*
      * @test
      */
      public function test_does_not_update_the_featured_image_belongsTo_another_office()
      {
        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $office2 = Office::factory()->for($user)->create();

        $image = $office2->images()->create(['path' => 'Test_Image.png']);

        $this->actingAs($user);

        $response = $this->putJson('/api/offices/'.$office->id, [
          'featured_image_id' => $image->id,
        ]);

        // dd($response->json());

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)// 422 Http errorß
                 ->assertInvalid('featured_image_id');
      }


      /*
      *@test
      */
      public function test_user_can_delete_offices()
      {
        Storage::put('Office_Image.png', 'empty');

        $user      = User::factory()->create();
        $office    = Office::factory()->for($user)->create();

        $office->images()->create([
          'path' => 'Test2_Image.jpg'
        ]);

        $image = $office->images()->create([
          'path' => 'Office_Image.png'
        ]);

        $this->actingAs($user);

        $response = $this->delete('/api/offices/'.$office->id);

        $response->assertOk();

        $this->AssertSoftDeleted($office);

        $this->assertModelMissing($image);

        Storage::assertMissing('Office_Image.png');
      }

      /*
      *@test
      */
      public function test_user_cannot_delete_offices_that_as_reservations()
      {
        $user      = User::factory()->create();
        $office    = Office::factory()->for($user)->create();

        Reservation::factory(3)->for($office)->create();

        $this->actingAs($user);

        $response = $this->deleteJson('/api/offices/'.$office->id);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Http error

        $this->assertNotSoftDeleted($office);
      }
}
