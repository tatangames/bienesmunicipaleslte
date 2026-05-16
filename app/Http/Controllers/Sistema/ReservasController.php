<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Entradas;
use App\Models\EntradasDetalle;
use App\Models\Materiales;
use App\Models\Reserva;
use App\Models\Salidas;
use App\Models\SalidasDetalle;
use App\Models\TipoProyecto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
class ReservasController extends Controller
{

    public function indexReservasPendientes()
    {
        $proyectosActivos = TipoProyecto::where('transferido', 0)
            ->orderBy('nombre')
            ->get();

        return view('backend.admin.repuestos.transferenciacerrados.vistareservaspendientes', [
            'proyectosActivos' => $proyectosActivos,
        ]);
    }

    // Cargar reservas pendientes (para tabla via axios)
    public function listar(Request $request)
    {
        $reservas = DB::table('reservas as r')
            ->join('entradas_detalle as ed', 'ed.id', '=', 'r.id_entrada_detalle')
            ->join('materiales as m', 'm.id', '=', 'ed.id_material')
            ->leftJoin('unidadmedida as um', 'um.id', '=', 'm.id_medida')
            ->join('tipoproyecto as tp', 'tp.id', '=', 'r.id_tipoproyecto')
            ->where('r.despachado', 0)
            ->selectRaw('
            r.id,
            r.cantidad,
            r.descripcion,
            r.fecha_reserva,
            m.nombre as nombre_material,
            COALESCE(um.nombre, "—") as medida,
            ed.precio,
            tp.nombre as nombre_proyecto_origen
        ')
            ->orderBy('r.fecha_reserva', 'ASC')
            ->get();

        return ['success' => 1, 'reservas' => $reservas];
    }

    public function despachar(Request $request)
    {
        $rules = [
            'fecha'        => 'required',
            'despachos'    => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ['success' => 0];
        }

        DB::beginTransaction();

        try {
            $despachos = json_decode($request->despachos, true);

            if (empty($despachos)) {
                return ['success' => 1];
            }

            foreach ($despachos as $d) {
                $idReserva      = $d['idReserva'];
                $tipoDestino    = $d['tipoDestino'];    // 'proyecto' | 'general'
                $idDestino      = $d['idDestino'] ?? null; // id proyecto si es 'proyecto'

                $reserva = Reserva::find($idReserva);
                if (!$reserva || $reserva->despachado) {
                    DB::rollback();
                    return ['success' => 2, 'msg' => 'Reserva no encontrada o ya despachada'];
                }

                $entradaDetalle = EntradasDetalle::find($reserva->id_entrada_detalle);
                if (!$entradaDetalle) {
                    DB::rollback();
                    return ['success' => 2, 'msg' => 'Material no encontrado'];
                }

                // Cabecera salida del proyecto cerrado origen
                $salida                                = new Salidas();
                $salida->fecha                         = Carbon::parse($request->fecha);
                $salida->descripcion                   = $request->descripcion ?? $reserva->descripcion;
                $salida->id_tipoproyecto               = $reserva->id_tipoproyecto;
                $salida->es_transferencia              = $tipoDestino === 'proyecto' ? 1 : 0;
                $salida->id_tipoproyecto_transferencia = $tipoDestino === 'proyecto' ? $idDestino : null;
                $salida->save();

                // Detalle salida
                $salidaDet                     = new SalidasDetalle();
                $salidaDet->id_salida          = $salida->id;
                $salidaDet->id_entrada_detalle = $reserva->id_entrada_detalle;
                $salidaDet->cantidad_salida    = $reserva->cantidad;
                $salidaDet->save();

                // Si va a proyecto → generar entrada en destino
                if ($tipoDestino === 'proyecto' && $idDestino) {
                    $infoMaterial = Materiales::find($entradaDetalle->id_material);

                    $entrada                                = new Entradas();
                    $entrada->id_tipoproyecto               = $idDestino;
                    $entrada->fecha                         = Carbon::parse($request->fecha);
                    $entrada->descripcion                   = $request->descripcion ?? $reserva->descripcion;
                    $entrada->es_transferencia              = 1;
                    $entrada->id_tipoproyecto_transferencia = $reserva->id_tipoproyecto;
                    $entrada->save();

                    $entradaDet                   = new EntradasDetalle();
                    $entradaDet->id_entradas      = $entrada->id;
                    $entradaDet->id_material      = $entradaDetalle->id_material;
                    $entradaDet->cantidad_inicial = $reserva->cantidad;
                    $entradaDet->precio           = $entradaDetalle->precio;
                    $entradaDet->nombre           = $infoMaterial ? $infoMaterial->nombre : $entradaDetalle->nombre;
                    $entradaDet->save();
                }

                // Marcar reserva como despachada
                $reserva->despachado           = 1;
                $reserva->fecha_despacho       = Carbon::parse($request->fecha);
                $reserva->id_tipoproyecto_destino = $tipoDestino === 'proyecto' ? $idDestino : null;
                $reserva->save();
            }

            DB::commit();
            return ['success' => 10];

        } catch (\Throwable $e) {
            Log::error('despachar reserva: ' . $e);
            DB::rollback();
            return ['success' => 99];
        }
    }


}
