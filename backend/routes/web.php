<?php

use App\Http\Controllers\InterfaceController;
use Illuminate\Support\Facades\Route;

// L'interface Nuxt gère toutes les pages ; l'API est dans routes/api.php.
Route::get('/', InterfaceController::class)->name('interface');
Route::fallback(InterfaceController::class);
