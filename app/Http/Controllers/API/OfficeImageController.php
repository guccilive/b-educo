<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\ImageResource;

class OfficeImageController extends Controller
{
    public function store(Office $office): JsonResource
    {
      abort_unless(auth()->user()->tokenCan('office.update'),
            Response::HTTP_FORBIDDEN
        );

      $this->authorize('update', $office);

      request()->validate([
        'image' => ['file', 'max:5000', 'mimes:jpg,png']
      ]);

      $path = request()->file('image')->storePublicly('/upload/images', ['disk' => 'public']);

      $image = $office->images()->create([
        'path' => $path,
      ]);

      return ImageResource::make($image);

    }

    public function delete(Office $office, Image $image)
    {
      abort_unless(auth()->user()->tokenCan('office.delete'),
          Response::HTTP_FORBIDDEN
      );

      $this->authorize('delete', $office);

      throw_if($office->images()->count() == 1,
        ValidationException::withMessages(['image' => ' Cannot delete the only Image.'])
      );

      throw_if($office->featured_image_id == $image->id,
        ValidationException::withMessages(['image' => ' This Image has been used as featured image and cannot be deleted.'])
      );

      Storage::delete($image->path);

      $image->delete();

    }
}
