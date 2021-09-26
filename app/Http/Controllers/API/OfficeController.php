<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Office;
use App\Models\Reservation;
use App\Http\Resources\OfficeResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Support\Arr;

class OfficeController extends Controller
{

    public function index(): JsonResource
    {
      $offices = Office::query()
                        ->where('approval_status', Office::APPROVAL_APPROVED)
                        ->where('hidden', false)
                        ->when(request('user_id'), fn ($builder) => $builder->whereUserId(request('user_id')))
                        ->when(request('visitor_id'),
                              fn (Builder $builder)
                                  => $builder->whereRelation('reservations', 'user_id', '=', request('visitor_id')))
                        ->when(
                          request('lat') && request('lng'),
                          fn($builder) => $builder->nearestTo(request('lat'), request('lng')),
                          fn($builder) => $builder->orderBy('id', 'ASC')
                          )
                        ->with(['images', 'tags', 'user'])
                        ->withCount(['reservations' => fn ($builder) => $builder->where('status', Reservation::STATUS_ACTIVE)])
                        ->paginate(20);

      return OfficeResource::collection($offices);
    }

    /**
     * Show a single Office
     *@Author<Heritier Mashini>
     */
    public function show(Office $office)
    {
      $office->loadCount(['reservations' => fn ($builder) => $builder->where('status', Reservation::STATUS_ACTIVE)])
             ->load(['images', 'tags', 'user']);

      return OfficeResource::make($office);
    }

    /**
     * Create a new Office
     *@Author<Heritier Mashini>
     */
     public function create(): JsonResource
     {
       // auth()->user()->tokenCan('office.create');
       abort_unless(auth()->user()->tokenCan('office.create'),
            Response::HTTP_FORBIDDEN
        );

       $attributes = validator(request()->all(),
           [
              'title' => ['required', 'string'],
              'description' => ['required', 'string'],
              'lat' => ['required', 'numeric'],
              'lng' => ['required', 'numeric'],
              'address_line1' => ['required', 'string'],
              'address_line2' => ['string'],
              'price_per_day' => ['required', 'integer', 'min:100'],
              'hidden' => ['bool'],
              'monthly_discount' => ['integer', 'min:0', 'max:90'],

              'tags' => ['array'],
              'tags.*' => ['integer', Rule::exists('tags', 'id')]
           ]
        )->validate();

        $attributes['user_id'] = auth()->id();
        $attributes['approval_status'] = Office::APPROVAL_PENDING;

        $office = Office::create(
            Arr::except($attributes, ['tags'])
        );

        $office->tags()->sync($attributes['tags']);

        return OfficeResource::make($office);
     }

}
