<?php

use App\Http\Controllers\Api\ProcessInstanceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/workflows/{slug}/instances', [ProcessInstanceController::class, 'start']);
    Route::get('/instances/{code}', [ProcessInstanceController::class, 'show']);
    Route::get('/instances/{code}/activities', [ProcessInstanceController::class, 'activities']);
    Route::post('/instances/{code}/activities/{activityId}/complete', [ProcessInstanceController::class, 'completeActivity']);
});
