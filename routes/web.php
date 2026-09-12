<?php

use App\Http\Controllers\ContractController;
use App\Http\Controllers\ImportCsvController;
use App\Http\Controllers\ZohoAuthController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('import');
});

Route::controller(ZohoAuthController::class)->group(function(){
    Route::get('/zoho/redirect','redirect')
    ->name('zoho.redirect');
    Route::get('/zoho/callback','callback')
        ->name('zoho.callback');
    Route::get('/test-zoho','test')->name('test-zoho');
});

Route::controller(ImportCsvController::class)->group(function(){
    Route::get('/import-csv','index')->name('importcsv');
    Route::post('/import/preview','preview')->name('import.preview');
    Route::post('/import/confirm', 'importConfirm')->name('import.confirm');
});

Route::controller(ContractController::class)->group(function (){
    Route::get('contracts','index')->name('contracts.index');
    Route::post('/contracts/follow-up','createFollowup')->name('contracts.followup');
    Route::post('/contracts/store','store')->name('contracts.store');
});

