<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\User;
use App\Models\Office;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
      Model::unguard(); // Disable mass assignment in all models of the app.

      /**
      *Customizind MorphMap
      * Instead of <<App/Models/Office>>, we will have <<office>>
      */
      Relation::enforceMorphMap([
        'office' => Office::class,
        'user'   => User::class
      ]);
    }
}
