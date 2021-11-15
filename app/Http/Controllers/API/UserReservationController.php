<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Office;
use App\Models\Reservation;
use App\Http\Resources\ReservationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use App\Notifications\NewUserReservationNotification;
use App\Notifications\NewHostReservationNotification;

use Carbon\Carbon;

class UserReservationController extends Controller
{
    public function index()
    {
      abort_unless(auth()->user()->tokenCan('reservations.show'),
          Response::HTTP_FORBIDDEN
      );

      $data = validator(request()->all(), [
        'status'    => [Rule::in([Reservation::STATUS_ACTIVE, Reservation::STATUS_CANCELLED])],
        'office_id' => ['integer'],
        'from_date' => ['date', 'required_with:to_date'],
        'to_date'   => ['date', 'required_with:from_date', 'after:from_date'],
      ])->validate();

      $reservations = Reservation::query()
                     ->where('user_id', auth()->id())
                     ->when(request('office_id'),
                        fn ($query) => $query->where('office_id', $data['office_id'])
                     )->when(request('status'),
                        fn ($query) => $query->where('status', $data['status'])
                     )->when(request('from_date') && request('to_date'),
                        fn($query) => $query->betweenDates(request('from_date'), request('to_date'))
                     )
                     ->with(['office.featuredImage'])
                     ->paginate(20);

      return ReservationResource::collection($reservations);

    }

    public function create()
    {

      abort_unless(auth()->user()->tokenCan('reservations.make'),
          Response::HTTP_FORBIDDEN
      );

      $data = validator(request()->all(), [
        'office_id'  => ['required', 'integer'],
        'start_date' => ['required', 'date:Y-m-d', 'after:today'],
        'end_date'   => ['required', 'date:Y-m-d', 'after:start_date'],
      ])->validate();

      try {
        $office = Office::findOrFail($data['office_id']);
      } catch (ModelNotFoundException $e) {
        throw ValidationException::withMessages([
          'office_id' => 'Invalid Office'
        ]);
      }

      if($office->user_id == auth()->id()){
        throw ValidationException::withMessages([
          'office_id' => 'You cannot make reservation on your own office.'
        ]);
      }

      if($office->hidden OR $office->approval_status == Office::APPROVAL_PENDING){
        throw ValidationException::withMessages([
          'office_id' => 'You cannot make reservation on a pending or hidden office.'
        ]);
      }

      $reservation = Cache::lock('reservations_office_', $office->id, 10)->block(3, function() use ($data, $office) {
        $numberOfDays = Carbon::parse($data['end_date'])->endOfDay()->diffInDays(
          Carbon::parse($data['start_date'])->startOfDay()
        ) + 1;

        if($office->reservations()->activeBetween($data['start_date'], $data['end_date'])->exists()) {
          throw ValidationException::withMessages([
            'office_id' => 'You cannot make reservation during  this time.'
          ]);
        }

        $price = $numberOfDays * $office->price_per_day;


        if($numberOfDays >= 28 && $office->monthly_discount) {
          $price = $price - ($price * $office->monthly_discount /100);
        }

        return Reservation::create([
          'user_id'       => auth()->id(),
          'office_id'     => $office->id,
          'start_date'    => $data['start_date'],
          'end_date'      => $data['end_date'],
          'status'        => Reservation::STATUS_ACTIVE,
          'price'         => $price,
          'wifi_password' => Str::random(9)
        ]);

      });

      Notification::send(auth()->user(), new NewUserReservationNotification($reservation));
      Notification::send($office->user, new NewHostReservationNotification($reservation));

     return ReservationResource::make($reservation->load('office'));

   }

    public function cancel(Reservation $reservation)
    {
        abort_unless(auth()->user()->tokenCan('reservations.cancel'),
            Response::HTTP_FORBIDDEN
        );

        if ($reservation->user_id != auth()->id() || $reservation->status == Reservation::STATUS_CANCELLED)
        {
            throw ValidationException::withMessages([
                'reservation' => 'You cannot cancel this reservation'
            ]);
        }

        $diff_days = $reservation->start_date->diffInDays(now()->toDateString());        
        if ($diff_days <= 1) {
            throw ValidationException::withMessages([
                'reservation' => 'You cannot cancel reservation 1 day prior to start.'
            ]);
        }

        $reservation->update([
            'status' => Reservation::STATUS_CANCELLED
        ]);

        return ReservationResource::make(
            $reservation->load('office')
        );
    }
}
