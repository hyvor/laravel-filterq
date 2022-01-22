<?php

namespace Hyvor\FilterQ;

use Illuminate\Support\ServiceProvider;

class FilterQServiceProvider extends ServiceProvider
{
    public function register()
    {

        $this->app->bind('filterq', function ($app) {
            return new FilterQ();
        });
    }

    public function boot()
    {
    }
}
