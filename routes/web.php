<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This is an API-only application. All routes are defined in routes/api.php
| Web routes are not used in this project.
|
*/

// Redirect root to API documentation
Route::get('/', function () {
    return redirect('/api/documentation');
});
