<?php

namespace App\Http\Controllers\Geocimat;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RepositorioController extends Controller
{
    protected $geocimat = 'geocimat/';

    function getFileList($path)
    {
        $listadoArchivos = collect();
        $archivos = Storage::disk('public')->files($path);
        foreach ($archivos as $archivo) {
            $nombreArchivo = array_reverse(explode('/', $archivo))[0];
            $listadoArchivos->push([
                'name' => $nombreArchivo,
                'id' => Str::random(30),
                'ruta' => $archivo,
                "url" => asset('storage/' . $archivo),
                'mime' => Storage::disk('public')->mimeType($archivo),
            ]);
        }
        return $listadoArchivos;
    }

    function getSubDirectory($path)
    {
        $listadoCarpeta = collect();
        $directorios = Storage::disk('public')->directories($path);
        foreach ($directorios as $directorio) {
            $children = $this->getFileList($directorio);
            $carpeta = array_reverse(explode('/', $directorio))[0];
            $listadoCarpeta->push([
                'name' => $carpeta,
                'id' => Str::random(30),
                'children' => $children,
                'ruta' => $directorio,
                "url" => asset('storage/' . $directorio),
                'mime' => 'folder',
            ]);
        }
        return $listadoCarpeta;
    }

    function getDirectory($path)
    {
        $proyecto = collect($this->getSubDirectory($path));
        $proyecto = $proyecto->merge($this->getFileList($path));
        return  $proyecto;
    }

    function getGalery($path)
    {
        return collect(Storage::disk('public')
            ->allFiles($path))
            ->map(function ($item) {
                return collect([
                    "id" => Str::random(),
                    "url" => asset('storage/' . $item)
                ]);
            })
            ->filter(function ($value) {
                return Str::endsWith($value["url"], ['.jpg', '.jpeg', '.png']);
            })->values();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id)
    {
        $user_id = Auth::id() ?? 1;
        $proyecto = DB::table('geo_proyecto')
            ->join('geo_clasificacion', 'geo_proyecto.id_clasificacion', 'geo_clasificacion.id')
            ->join('users', 'geo_proyecto.user_id', 'users.id')
            ->select(
                'geo_proyecto.nombre',
                'geo_proyecto.descripcion',
                'geo_proyecto.latitud',
                'geo_proyecto.longitud',
                'geo_clasificacion.nombre AS categoria',
                'geo_clasificacion.nombre AS unidad'
            )
            ->where('identificador', $id)
            ->where('user_id', $user_id)
            ->first();

        if (!$proyecto) {
            return response()->json(['mensaje' => 'No tiene permisos para ver este proyecto.', 'directorio' => []], 404);
        }

        if (File::exists(Storage::disk('public')->path('/') . $this->geocimat . $id)) {
            return response()->json([
                'directorio' => $this->getDirectory($this->geocimat . $id),
                'galeria' => $this->getGalery($this->geocimat . $id),
                'datos' => $proyecto
            ]);
        }
        return response()->json(['mensaje' => 'El directorio no existe.', 'directorio' => []], 404);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nodoPadre' => 'required',
            'folder' => 'required',
        ]);

        if (strpbrk($validated['folder'], "\\/?%*:|\"<>")) {
            return response()->json(['mensaje' => 'El nombre del proyecto debe contener solo valores alfanuméricos.'], 422);
        }

        if (!Storage::disk('public')->exists($this->geocimat . $validated['nodoPadre'])) {
            return response()->json(['mensaje' => 'El directorio no existe.'], 404);
        }

        $directorio = Storage::disk('public')->makeDirectory($this->geocimat . $validated['nodoPadre'] . '/' . $validated['folder']);
        if ($directorio) {
            return response()->json(['mensaje' => 'Directorio creado.'], 201);
        }

        return response()->json(['mensaje' => 'No se pudo crear el directorio.'], 422);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'nodo' => 'required',
            'elemento' => 'required',
        ]);

        $nodo = $this->geocimat . $validated['nodo'];
        if (!Storage::disk('public')->exists($nodo)) {
            return response()->json(['mensaje' => 'Directorio no encontrado.'], 404);
        }

        $elementos = $validated['elemento'];
        Storage::disk('public')->delete($elementos);
        Storage::disk('public')->deleteDirectory($elementos[0]);

        $mensaje = sizeof($elementos) <= 1 ? 'Elemento eliminado.' : count($elementos) . ' Elementos eliminados.';
        return response()->json([
            'mensaje' => $mensaje
        ]);
    }

    /**
     * Download file or zip.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function downloadFile(Request $request)
    {
        $validated = $request->validate([
            'nodo' => 'required',
            'elemento' => 'required',
        ]);
        $ruta = $validated["elemento"];
        $storagePath = "/app/public/" . $ruta;
        if (is_dir(storage_path($storagePath))) {
            return response()->json(['mensaje' => 'Este elemento no se puede descargar.'], 404);
        }
        if (Storage::disk('public')->exists($ruta) && is_file(storage_path($storagePath))) {
            return response()->json(["ruta" => Storage::disk('public')->url($storagePath)]);
            // return response()->json(["ruta" => asset("storage" . $storagePath)]);
        }
        return response()->json(['mensaje' => 'El elemento no existe.'], 404);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function upload(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'directorio' => 'required',
            'archivos' => 'required',
        ]);

        $id =  $request->idProyecto;
        $directorio =  $request->directorio;
        $archivos = $request->file('archivos');
        $paths  = [];

        foreach ($archivos as $archivo) {
            $nombreArchivo = pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $archivo->extension();
            $archivoConExtension = $this->obtenerNombre($directorio, $nombreArchivo, $extension);
            $storePath = Storage::disk('public')->putFileAs($directorio, $archivo, $archivoConExtension);
            $paths[] = $storePath;
        }

        $mensaje = sizeof($paths) <= 1 ? 'Elemento agregado.' : sizeof($paths) . ' Nuevos elementos.';
        return response()->json(['mensaje' => $mensaje]);
    }

    function obtenerNombre($ruta, $nombre, $extension)
    {
        $i = 1;
        $nombreConExtension =  $nombre . "." . $extension;
        $nombreTemporal = $nombre;
        while (Storage::disk('public')->exists($ruta . '/' . $nombre . "." . $extension)) {
            $nombre = (string)$nombreTemporal . ' (' . $i . ')';
            $nombreConExtension = $nombre . "." . $extension;
            $i++;
        }
        return $nombreConExtension;
    }
}
