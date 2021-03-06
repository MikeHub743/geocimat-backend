<?php

/*
|--------------------------------------------------------------------------
| GEOCIMAT Routes
|--------------------------------------------------------------------------
|
| APP PARA EL DEPARTAMENTO DE GEOFISICA DE LA UNIVERSIDAD DE EL SALVADOR
| 
| 
|
*/

use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    return view('geocimat.index');
});

Route::get('/token', function () {
    dd(Auth::id(), csrf_field());
});

// Route::prefix('repositorio')->group(function () {
//     Route::post('/descargar', 'RepositorioController@downloadFile');
// });

Route::prefix('repositorio')->group(function () {
    Route::get('/mostrar/{id}', 'RepositorioController@index');
    Route::post('/crear', 'RepositorioController@store');
    Route::get('/descargar', 'RepositorioController@downloadFile');
    Route::delete('/destruir', 'RepositorioController@destroy');
    Route::post('/almacenar', 'RepositorioController@upload');
});

Route::prefix('proyecto')->group(function () {
    Route::get('/', 'ProyectoController@index');
    Route::post('/crear', 'ProyectoController@store');
});

Route::prefix('clasificacion')->group(function () {
    Route::get('/', 'ClasificacionController@index');
    Route::post('/crear', 'ClasificacionController@store');
    Route::post('/modificar', 'ClasificacionController@update');
    Route::post('/visible', 'ClasificacionController@destroy');
});

Route::prefix('estadovisita')->group(function () {
    Route::get('/', 'EstadoVisitaController@index');
    Route::post('/crear', 'EstadoVisitaController@store');
    Route::post('/modificar', 'EstadoVisitaController@update');
    Route::post('/visible', 'EstadoVisitaController@destroy');
});

Route::prefix('calendario')->group(function () {
    Route::get('/', 'CalendarioController@index');
    Route::post('/crear', 'CalendarioController@store');
    Route::post('/modificar', 'CalendarioController@update');
    Route::post('/destruir', 'CalendarioController@destroy');
});
