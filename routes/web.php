<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\PushSubscriptionController;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('appointments.index');
    }
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return redirect()->route('appointments.index');
})->middleware(['auth'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

Route::middleware(['auth'])->group(function () {
    Route::resource('services', ServiceController::class);

    Route::get('/appointments/events', [AppointmentController::class, 'events'])->name('appointments.events');
    Route::get('/appointments/available-slots', [AppointmentController::class, 'availableSlots'])->name('appointments.available-slots');
    Route::resource('appointments', AppointmentController::class)->except(['show']);

    Route::resource('transactions', TransactionController::class);

    Route::post('/appointments/check-overlap', [AppointmentController::class, 'checkOverlap'])->name('appointments.check-overlap');

    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/process', [AttendanceController::class, 'process'])->name('attendance.process');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    Route::post('/push-subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');

    Route::post('/appointments/sync', [AppointmentController::class, 'sync'])->name('appointments.sync');
});
