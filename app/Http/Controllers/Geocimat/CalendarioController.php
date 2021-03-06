<?php

namespace App\Http\Controllers\Geocimat;

use App\Models\Geocimat\Calendario;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Geocimat\Administracion;
use App\Models\Geocimat\EstadoVisita;
use App\Models\Geocimat\Proyecto;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CalendarioController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $user_id = Auth::id() ?? 2;
            if ($this->pj_list($user_id)) {

                $calendario = DB::table('geo_calendario')
                    ->join('geo_proyecto', 'geo_calendario.identificador', '=', 'geo_proyecto.identificador')
                    ->join('geo_clasificacion', 'geo_clasificacion.id', '=', 'geo_proyecto.id_clasificacion')
                    ->join('geo_estado_visita', 'geo_estado_visita.id', '=', 'geo_calendario.id_estado')
                    ->select('geo_calendario.id', 'geo_calendario.descripcion', 'geo_calendario.fecha_inicio AS start', 'geo_calendario.fecha_fin AS end', 'geo_calendario.identificador AS name', 'geo_calendario.id_estado AS id_status', 'geo_proyecto.nombre as proyecto', 'geo_estado_visita.material_color AS materialColor', 'geo_clasificacion.nombre as clasificacion', 'geo_clasificacion.material_color as cla_material_color')
                    ->get();
                $proyectos = Proyecto::select("nombre", "identificador")->get();
            } else {
                $calendario = DB::table('geo_calendario')
                    ->join('geo_proyecto', 'geo_calendario.identificador', '=', 'geo_proyecto.identificador')
                    ->join('geo_clasificacion', 'geo_clasificacion.id', '=', 'geo_proyecto.id_clasificacion')
                    ->join('geo_estado_visita', 'geo_estado_visita.id', '=', 'geo_calendario.id_estado')
                    ->select('geo_calendario.id', 'geo_calendario.descripcion', 'geo_calendario.fecha_inicio AS start', 'geo_calendario.fecha_fin AS end', 'geo_calendario.identificador AS name', 'geo_calendario.id_estado AS id_status', 'geo_proyecto.nombre as proyecto', 'geo_estado_visita.material_color AS materialColor', 'geo_clasificacion.nombre as clasificacion', 'geo_clasificacion.material_color as cla_material_color')
                    ->where('geo_proyecto.user_id', $user_id)
                    ->get();
                $proyectos = Proyecto::where("user_id", $user_id)
                    ->select("nombre", "identificador")->get();
            }
            $estadoVisita = EstadoVisita::select("id", "nombre", "material_color")->get();
            return response()->json(["calendario" => $calendario, "estadoVisita" => $estadoVisita, "proyectos" => $proyectos]);
        } catch (\Throwable $th) {
            return response()->json(['message' => "Ocurrio un error " . $th->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            if (Proyecto::findOrFail($request->identificador) || EstadoVisita::findOrFail($request->id_estado)) {

                $calendario = new Calendario;
                $calendario->fecha_inicio = $request->fecha_inicio;
                $calendario->fecha_fin = $request->fecha_fin;
                $calendario->id_estado = $request->id_estado;
                $calendario->identificador = $request->identificador;
                $calendario->descripcion = $request->descripcion;
                $calendario->save();
                return response()->json(["message" => "Evento agregado al calendario", "newDate" => $calendario->id]);
            }
            return response()->json(["message" => "Este proyecto no se encuentra en nuestros registros", 404]);
        } catch (\Exception $th) {
            return response()->json(["message" => "Ocurrio un error " . $th->getMessage()], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Geocimat\Calendario  $calendario
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        try {
            if (Calendario::Find($request->id) && EstadoVisita::Find($request->id_estado)) {
                Calendario::where("id", "=", $request->id)->update(["id_estado" => $request->id_estado, "descripcion" => $request->descripcion]);
                return response()->json(["message" => "Evento Actualizado"]);
            }
            return response()->json(["message" => "El evento no se fue actualizado"], 404);
        } catch (\Exception $th) {
            return response()->json(["message" => "Ocurrio un error " . $th->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Geocimat\Calendario  $calendario
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request)
    {
        try {
            if (Calendario::Find($request->id)) {
                Calendario::where("id", "=", $request->id)->delete();
                return response()->json(["message" => "Evento eliminado"]);
            }
            return response()->json(["message" => "El evento no se fue eliminado"], 404);
        } catch (\Exception $th) {
            return response()->json(["message" => "Ocurrio un error " . $th->getMessage()], 500);
        }
    }

    /**
     * Verifica el permiso de listado completo.
     *
     * @param  Auth::id 
     * @return Boolean
     */
    public function pj_list($user_id)
    {
        $pj_list = true;
        if (Administracion::where('user_id', $user_id)->where("pj_list", $pj_list)->value("pj_list")) {
            return true;
        } else return false;
    }
}
