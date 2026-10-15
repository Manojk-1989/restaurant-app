<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use App\Http\Middleware\RequireAnalyticsServerKey;
use Illuminate\Support\Facades\Route;

Route::get('/menu', MenuController::class);
Route::post('/events', [EventController::class, 'browser']);
Route::post('/server-events', [EventController::class, 'server'])->middleware(RequireAnalyticsServerKey::class);
Route::get('/analytics', AnalyticsController::class);
Route::post('/orders', [OrderController::class, 'store']);