<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\ReportDownloadController;
use App\Livewire\Audit;
use App\Livewire\Auth\Login;
use App\Livewire\BusinessRules;
use App\Livewire\Catalog;
use App\Livewire\ComparisonMatrix;
use App\Livewire\Customers;
use App\Livewire\Dashboard;
use App\Livewire\Diagnostics;
use App\Livewire\Implementations;
use App\Livewire\Issues;
use App\Livewire\Products;
use App\Livewire\Remediation;
use App\Livewire\Reports;
use App\Livewire\Strategies;
use App\Livewire\Technologies;
use App\Livewire\Versions;
use App\Livewire\Workflows;
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
    Route::livewire('/versions/compare', Versions\Compare::class)->name('versions.compare');
    Route::livewire('/versions/{version}', Versions\Show::class)->name('versions.show');
    Route::livewire('/versions/{version}/edit', Versions\Form::class)->name('versions.edit');
    Route::livewire('/versions/{version}/scope', Versions\Scope::class)->name('versions.scope');

    Route::livewire('/catalog', Catalog\Index::class)->name('catalog.index');
    Route::livewire('/catalog/duplicates', Catalog\Duplicates::class)->name('catalog.duplicates');
    Route::livewire('/comparison', ComparisonMatrix\Index::class)->name('comparison.index');
    Route::livewire('/workflows', Workflows\Index::class)->name('workflows.index');
    Route::livewire('/workflows/{workflow}', Workflows\Show::class)->name('workflows.show');

    Route::livewire('/implementations/{implementation}', Implementations\Show::class)->name('implementations.show');
    Route::livewire('/business-rules', BusinessRules\Index::class)->name('business-rules.index');
    Route::livewire('/business-rules/{rule}', BusinessRules\Show::class)->name('business-rules.show');
    Route::livewire('/issues', Issues\Index::class)->name('issues.index');
    Route::livewire('/issues/{issue}', Issues\Show::class)->name('issues.show');
    Route::livewire('/diagnostics', Diagnostics\Index::class)->name('diagnostics.index');
    Route::livewire('/actions', Remediation\Index::class)->name('actions.index');
    Route::livewire('/strategies', Strategies\Index::class)->name('strategies.index');
    Route::livewire('/strategies/{strategy}', Strategies\Show::class)->name('strategies.show');
    Route::livewire('/diagnostics/{campaign}', Diagnostics\Show::class)->name('diagnostics.show');

    Route::livewire('/reports', Reports\Index::class)->name('reports.index');
    Route::livewire('/audit', Audit\Index::class)->name('audit.index');
    Route::get('/reports/{generation}/{format}', ReportDownloadController::class)
        ->whereIn('format', ['docx', 'xlsx'])
        ->name('reports.download');

    Route::livewire('/technologies', Technologies\Index::class)->name('technologies.index');
});
