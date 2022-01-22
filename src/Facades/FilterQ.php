<?php

namespace Hyvor\FilterQ\Facades;

use Illuminate\Support\Facades\Facade;

class FilterQ extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'filterq';
    }
}
