<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Office;
use App\Models\Reservation;
use App\Http\Resources\OfficeResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Database\Eloquent\Builder;

class OfficeController extends Controller
{

    public function index(): AnonymousResourceCollection
    {
      $offices = Office::query()
                        ->where('approval_status', Office::APPROVAL_APPROVED)
                        ->where('hidden', false)
                        ->when(request('host_id'), fn ($builder) => $builder->whereUserId(request('host_id')))
                        ->when(request('user_id'),
                              fn (Builder $builder)
                                  => $builder->whereRelation('reservations', 'user_id', '=', request('user_id')))
                        ->latest('id')
                        ->with(['images', 'tags', 'user'])
                        ->withCount(['reservations' => fn ($builder) => $builder->where('status', Reservation::STATUS_ACTIVE)])
                        ->paginate(20);

      return OfficeResource::collection($offices);
    }
}