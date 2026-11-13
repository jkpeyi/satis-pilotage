<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Auth\Login;
use App\Livewire\Customers;
use App\Livewire\Dashboard;
use App\Livewire\Products;
use App\Livewire\Technologies;
use App\Livewire\Versions;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::livewire('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', LogoutController::class)->name('logout');

    Route::livewire('/', Dashboard::class)->name('dashboard');

    Route::livewire('/products', Products\Index::class)->name('products.index');
    Route::livewire('/products/create', Products\Form::class)->name('products.create');
    Route::livewire('/products/{product}', Products\Show::class)->name('products.show');
    Route::livewire('/products/{product}/edit', Products\Form::class)->name('products.edit');

    Route::livewire('/customers', Customers\Index::class)->name('customers.index');
    Route::livewire('/customers/create', Customers\Form::class)->name('customers.create');
    Route::livewire('/customers/{customer}/edit', Customers\Form::class)->name('customers.edit');

    Route::livewire('/versions', Versions\Index::class)->name('versions.index');
    Route::livewire('/versions/create', Versions\Form::class)->name('versions.create');
    Route::livewire('/versions/{version}', Versions\Show::class)->name('versions.show');
    Route::livewire('/versions/{version}/edit', Versions\Form::class)->name('versions.edit');

    Route::livewire('/technologies', Technologies\Index::class)->name('technologies.index');
});
