<?php

use App\Http\Controllers\Auth\AzureController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/login', function () {
    if (Auth::check()) {
        return redirect()->route('home');
    }

    return view('auth.login');
})->name('login');

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

Route::get('/auth/azure/redirect', [AzureController::class, 'redirect'])->name('azure.redirect');
Route::get('/auth/azure/callback', [AzureController::class, 'callback'])->name('azure.callback');

Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return view('home');
    })->name('home');
});
