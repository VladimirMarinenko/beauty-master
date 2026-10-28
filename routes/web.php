<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ClientBookingController;
use App\Http\Controllers\ClientCabinetController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\MasterInviteController;
use App\Http\Controllers\MasterPublicController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (!auth()->check()) {
        return redirect()->route('login');
    }
    return auth()->user()->isClient()
        ? redirect()->route('me.index')
        : redirect()->route('appointments.index');
});

Route::get('/dashboard', function () {
    return auth()->user()->isClient()
        ? redirect()->route('me.index')
        : redirect()->route('appointments.index');
})->middleware(['auth'])->name('dashboard');

// =========================
// Публичные страницы мастера
// =========================
Route::get('/m/{slug}/{token}', [MasterPublicController::class, 'show'])->name('master.public');
Route::post('/m/{slug}/{token}/register', [MasterPublicController::class, 'registerClient'])->name('master.register');

Route::middleware('auth')->group(function () {
    Route::post('/m/{slug}/{token}/attach', [MasterPublicController::class, 'attach'])->name('master.attach');
});

// =========================
// Общее для всех ролей (auth)
// =========================
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // push-subscribe доступен и мастеру, и клиенту
    Route::post('/push-subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::post('/push-unsubscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
});

require __DIR__.'/auth.php';

// =========================
// Кабинет МАСТЕРА
// =========================
Route::middleware(['auth', 'role:master'])->group(function () {
    Route::resource('services', ServiceController::class);

    Route::resource('appointments', AppointmentController::class)->except(['show']);
    Route::get('/appointments/events', [AppointmentController::class, 'events'])->name('appointments.events');
    Route::get('/appointments/available-slots', [AppointmentController::class, 'availableSlots'])->name('appointments.available-slots');
    Route::post('/appointments/check-overlap', [AppointmentController::class, 'checkOverlap'])->name('appointments.check-overlap');
    Route::post('/appointments/sync', [AppointmentController::class, 'sync'])->name('appointments.sync');

    Route::resource('transactions', TransactionController::class);

    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/process', [AttendanceController::class, 'process'])->name('attendance.process');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    Route::get('/invite', [MasterInviteController::class, 'show'])->name('master.invite');
    Route::post('/invite/regenerate', [MasterInviteController::class, 'regenerate'])->name('master.invite.regenerate');

    // clients: все кастомные роуты ДО resource
    Route::post('/clients/mark-notified', [ClientController::class, 'markNotified'])->name('clients.mark-notified');
    Route::get('/clients/{client}/merge', [ClientController::class, 'mergeForm'])->name('clients.merge');
    Route::post('/clients/{client}/merge', [ClientController::class, 'merge'])->name('clients.merge.store');
    Route::get('/clients/live', [ClientController::class, 'live'])->name('clients.live');
    Route::get('/clients/search', [ClientController::class, 'search'])->name('clients.search');
    Route::resource('clients', ClientController::class)->except(['show']);
});

// =========================
// Кабинет КЛИЕНТА
// =========================
Route::middleware(['auth', 'role:client'])->prefix('me')->name('me.')->group(function () {
    Route::get('/', [ClientCabinetController::class, 'index'])->name('index');
    Route::get('/appointments', [ClientCabinetController::class, 'appointments'])->name('appointments');
    Route::post('/appointments/{appointment}/cancel', [ClientBookingController::class, 'cancel'])->name('appointments.cancel');

    Route::get('/book/{master}', [ClientBookingController::class, 'show'])->name('book');
    Route::post('/book/{master}/slots', [ClientBookingController::class, 'slots'])->name('book.slots');
    Route::post('/book/{master}', [ClientBookingController::class, 'book'])->name('book.store');
});
