<?php

/*
 * Administrators only ("auth" + "roles" => admin, same pattern as the other FreeScout modules), "web" group (CSRF),
 * FreeScout's subdirectory prefix.
 */
Route::group([
    'middleware' => 'web',
    'prefix'     => \Helper::getSubdirectory().'/hydrolink',
    'namespace'  => 'Modules\HydroLink\Http\Controllers',
], function () {
    $a = ['middleware' => ['auth', 'roles'], 'roles' => ['admin']];

    Route::get('/', $a + ['uses' => 'PanelController@index'])->name('hydrolink.panel');
    Route::get('/state', $a + ['uses' => 'PanelController@state'])->name('hydrolink.state');
    Route::post('/settings', $a + ['uses' => 'SettingsController@save'])->name('hydrolink.settings');
    Route::post('/service', $a + ['uses' => 'PanelController@service'])->name('hydrolink.service');
    Route::post('/binary', $a + ['uses' => 'PanelController@binary'])->name('hydrolink.binary');
    Route::post('/notifications/test', $a + ['uses' => 'SettingsController@testNotification'])->name('hydrolink.notify.test');
    Route::post('/virustotal/test', $a + ['uses' => 'SettingsController@testVirusTotal'])->name('hydrolink.vt.test');

    Route::post('/accounts', $a + ['uses' => 'AccountsController@store'])->name('hydrolink.accounts.store');
    Route::post('/accounts/{id}/reconnect', $a + ['uses' => 'AccountsController@reconnect'])->name('hydrolink.accounts.reconnect');
    Route::post('/accounts/{id}/totp', $a + ['uses' => 'AccountsController@totp'])->name('hydrolink.accounts.totp');
    Route::post('/accounts/{id}/verified', $a + ['uses' => 'AccountsController@verified'])->name('hydrolink.accounts.verified');
    Route::post('/accounts/{id}/pause', $a + ['uses' => 'AccountsController@pause'])->name('hydrolink.accounts.pause');
    Route::post('/accounts/{id}/delete', $a + ['uses' => 'AccountsController@destroy'])->name('hydrolink.accounts.delete');
    Route::get('/accounts/{id}/logs', $a + ['uses' => 'AccountsController@logs'])->name('hydrolink.accounts.logs');
});
