<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Office;
use App\Models\Reservation;
use App\Http\Resources\OfficeResource;
use App\Models\Validators\OfficeValidator;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
use App\Notifications\OfficePendingApprovalNotification;

class OfficeController extends Controller
{

    public function index(): JsonResource
    {      
      $offices = Office::query()
                        ->when(request('user_id') && auth()->user() && request('user_id') == auth()->id(),
                              fn($builder) => $builder,
                              fn($builder) => $builder->where('approval_status', Office::APPROVAL_APPROVED)
                                                      ->where('hidden', false)
                        )
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
       abort_unless(auth()->user()->tokenCan('office.create'),
            Response::HTTP_FORBIDDEN
        );

        $attributes = (new OfficeValidator())->validate(
            $office = new Office(),
            request()->all()
        );

        $attributes['approval_status'] = Office::APPROVAL_PENDING;
        $attributes['user_id'] = auth()->id();

        $office = DB::transaction(function () use ($office, $attributes) {
            $office->fill(
                Arr::except($attributes, ['tags'])
            )->save();

            if (isset($attributes['tags'])) {
                $office->tags()->attach($attributes['tags']);
            }

            return $office;
        });

        Notification::send(User::where('is_admin', true)->get(), new OfficePendingApprovalNotification($office));

        return OfficeResource::make($office->load(['images', 'tags', 'user']));
     }

     /**
      * Update an existing Office
      *@Author<Heritier Mashini>
      */
     public function update(Office $office): JsonResource
     {
       abort_unless(auth()->user()->tokenCan('office.update'),
            Response::HTTP_FORBIDDEN
        );

       $this->authorize('update', $office);

       $attributes = (new OfficeValidator())->validate($office, request()->all());

       $office->fill(Arr::except($attributes, ['tags']));

       if($requiresApproval = $office->isDirty(['lat', 'lng', 'price_per_day']))
       {
         $office->fill(['approval_status' => Office::APPROVAL_PENDING]);
       }

       $office = DB::transaction(function() use ($office, $attributes) {
          $office->save();

          if (isset($attributes['tags'])) {
              $office->tags()->sync($attributes['tags']);
          }

          return $office;
        });

        if($requiresApproval)
        {
          Notification::send(User::where('is_admin', true)->get(), new OfficePendingApprovalNotification($office));
        }

        return OfficeResource::make($office->load(['images', 'tags', 'user']));

     }

     public function delete(Office $office)
     {
       abort_unless(auth()->user()->tokenCan('office.delete'),
            Response::HTTP_FORBIDDEN
        );

       $this->authorize('delete', $office);

       throw_if(
           $office->reservations()->where('status', Reservation::STATUS_ACTIVE)->exists(),
           ValidationException::withMessages(['office' => 'Cannot delete this office!'])
       );

       $office->images()->each(function ($image) {
         Storage::delete($image->path);

         $image->delete();

       });

       $office->delete();

     }

}
