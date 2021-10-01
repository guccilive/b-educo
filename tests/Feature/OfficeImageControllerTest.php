<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\User;
use App\Models\Office;

class OfficeImageControllerTest extends TestCase
{
   use LazilyRefreshDatabase;
    /**
     *@test
     */
    public function test_uploade_office_image()
    {

        Storage::fake();

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->postJson("/api/offices/{$office->id}/images", [
          'image' => UploadedFile::fake()->image('Test_Image.jpg')
        ]);

        // dd($response->json());

        $response->assertCreated();

        Storage::assertExists([
          $response->json('data.path')
        ]);
    }

    /**
     *@test
     */
    public function test_delete_an_office_image()
    {
        Storage::put('Office_Image.png', 'empty');

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->images()->create([
          'path' => 'Test2_Image.jpg'
        ]);

        $image = $office->images()->create([
          'path' => 'Office_Image.png'
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        // dd($response->status());
        $response->assertOk();

        $this->assertModelMissing($image);

        Storage::assertMissing('Office_Image.png');
    }

    /**
     *@test
     */
    public function test_does_not_delete_the_only_image()
    {

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create([
          'path' => 'Office_Image.png'
        ]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Http error
    }

    /**
     *@test
     */
    public function test_does_not_delete_the_office_featured_image()
    {

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->images()->create([
          'path' => 'Test2_Image.jpg'
        ]);

        $image = $office->images()->create([
          'path' => 'Office_Image.png'
        ]);

        $office->update(['featured_image_id' => $image->id]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY); // 422 Http error
    }

    /**
     *@test
     */
    public function test_cannot_delete_the_image_belong_to_another_resource()
    {

        $user    = User::factory()->create();
        $office  = Office::factory()->for($user)->create();
        $office2 = Office::factory()->for($user)->create();

        $office->images()->create([
          'path' => 'Test2_Image.jpg'
        ]);

        $image = $office2->images()->create([
          'path' => 'Office_Image.png'
        ]);

        $office->update(['featured_image_id' => $image->id]);

        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");

        $response->assertNotFound();
    }
}
