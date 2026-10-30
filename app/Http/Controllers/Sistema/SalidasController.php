<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Entradas;
use App\Models\EntradasDetalle;
use App\Models\InformacionGeneral;
use App\Models\Materiales;
use App\Models\Salidas;
use App\Models\SalidasDetalle;
use App\Models\UnidadMedida;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SalidasController extends Controller
{

    public function indexRegistroSalida()
    {
        $informacionGeneral = InformacionGeneral::where('id', 1)->first();

        return view('backend.admin.repuestos.salidas.vistasalidaregistro', compact('informacionGeneral'));
    }


    public function buscadorMaterialDisponible(Request $request)
    {
        if (!$request->get('query')) return '';

        $query = $request->get('query');

        $materiales = Materiales::where('nombre', 'LIKE', "%{$query}%")->pluck('id');

        if ($materiales->isEmpty()) return '';

        $listado = DB::table('entradas_detalle as ed')
            ->leftJoin(
                DB::raw('(
                SELECT id_entrada_detalle, SUM(cantidad_salida) as total_salido
                FROM salidas_detalle
                GROUP BY id_entrada_detalle
            ) as sd'),
                'sd.id_entrada_detalle', '=', 'ed.id'
            )
            ->select(
                'ed.id_material',
                DB::raw('SUM(ed.cantidad_inicial) as total_inicial'),
                DB::raw('COALESCE(SUM(sd.total_salido), 0) as total_salido'),
                DB::raw('(SUM(ed.cantidad_inicial) - COALESCE(SUM(sd.total_salido), 0)) as disponible')
            )
            ->whereIn('ed.id_material', $materiales)
            ->groupBy('ed.id_material')
            ->havingRaw('disponible > 0')
            ->orderBy('ed.id_material')
            ->get();

        if ($listado->isEmpty()) return '';

        // Todos los materiales con su unidad en una sola consulta (evita N+1)
        $infoMateriales = Materiales::with('unidadMedida')
            ->whereIn('id', $listado->pluck('id_material'))
            ->get()
            ->keyBy('id');

        $output = '<ul class="dropdown-menu" style="display:block;position:relative;overflow:auto;max-height:300px;width:800px">';

        foreach ($listado as $row) {
            $infoMaterial = $infoMateriales[$row->id_material] ?? null;
            if (!$infoMaterial) continue;

            // NOMBRE | CODIGO (marca) | UNIDAD
            $partes = [$infoMaterial->nombre];

            if (!empty($infoMaterial->codigo)) {
                $partes[] = $infoMaterial->codigo;
            }

            $unidad = optional($infoMaterial->unidadMedida)->nombre;
            if (!empty($unidad)) {
                $partes[] = $unidad;
            }

            $texto = implode(' | ', $partes) . ' — Disponible: ' . $row->disponible;

            $output .= '<li class="cursor-pointer" onclick="modificarValor(this)"'
                . ' id="' . $row->id_material . '"'
                . ' data-tipo="material"'
                . ' data-codigo="' . e($infoMaterial->codigo ?? '') . '">'
                . e($texto)
                . '</li><hr>';
        }

        $output .= '</ul>';
        return $output;
    }



    public function infoBodegaMaterialDetalleFila(Request $request)
    {
        $validar = Validator::make($request->all(), ['id' => 'required']);
        if ($validar->fails()) return ['success' => 0];

        $infoMaterial = Materiales::find($request->id);
        if (!$infoMaterial) return ['success' => 0];

        $infoMedida = UnidadMedida::find($infoMaterial->id_medida);

        $listado = DB::table('entradas_detalle as ed')
            ->leftJoin(
                DB::raw('(
                SELECT id_entrada_detalle, SUM(cantidad_salida) as total_salido
                FROM salidas_detalle
                GROUP BY id_entrada_detalle
            ) as sd'),
                'sd.id_entrada_detalle', '=', 'ed.id'
            )
            ->join('entradas as e', 'e.id', '=', 'ed.id_entradas')
            ->select(
                'ed.id',
                'ed.id_entradas',
                'ed.cantidad_inicial',
                'ed.precio',
                'e.fecha',
                'ed.codigo',
                DB::raw('COALESCE(sd.total_salido, 0) as total_salido'),
                DB::raw('(ed.cantidad_inicial - COALESCE(sd.total_salido, 0)) as cantidadActual')
            )
            ->where('ed.id_material', $request->id)
            ->havingRaw('cantidadActual > 0')
            ->orderBy('ed.id')
            ->get();

        foreach ($listado as $fila) {
            $fila->fechaIngreso = date('d-m-Y', strtotime($fila->fecha));
            $fila->precioFormat = '$' . number_format($fila->precio, 2, '.', ',');
        }

        return [
            'success'        => 1,
            'nombreMaterial' => $infoMaterial->nombre ?? '',
            'nombreMedida'   => $infoMedida->nombre   ?? '',
            'arrayIngreso'   => $listado,
            'disponible'     => $listado->isEmpty() ? 1 : 0,
        ];
    }





    public function guardarSalida(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date',
        ]);

        if ($validator->fails()) {
            return ['success' => 0];
        }

        $contenedor = json_decode($request->contenedorArray, true);

        if (empty($contenedor) || !is_array($contenedor)) {
            return ['success' => 0];
        }

        // Agrupar por id_entrada_detalle, sumando cantidades (en centavos, decimal 8,2)
        // y tomando la última observación
        $agrupado = [];
        foreach ($contenedor as $item) {
            if (!is_array($item)) {
                return ['success' => 0];
            }

            $itemValidator = Validator::make($item, [
                'infoIdEntradaDeta' => 'required|integer',
                'infoCantidad'      => ['required', 'numeric', 'min:0.01', 'max:999999.99', 'regex:/^\d+(\.\d{1,2})?$/'],
            ]);

            if ($itemValidator->fails()) {
                return ['success' => 0];
            }

            $id = $item['infoIdEntradaDeta'];
            if (!isset($agrupado[$id])) {
                $agrupado[$id] = ['centavos' => 0, 'observacion' => ''];
            }
            $agrupado[$id]['centavos']    += (int) round((float) $item['infoCantidad'] * 100);
            $agrupado[$id]['observacion']  = $item['observacion'] ?? '';
        }

        $fechaSalida = Carbon::parse($request->fecha)->startOfDay();

        DB::beginTransaction();

        try {
            $fila = 1;

            foreach ($agrupado as $idEntradaDetalle => $datos) {

                $info = DB::table('entradas_detalle as ed')
                    ->join('entradas as e', 'e.id', '=', 'ed.id_entradas')
                    ->join('materiales as m', 'm.id', '=', 'ed.id_material')
                    ->leftJoin(
                        DB::raw('(
                    SELECT id_entrada_detalle, SUM(cantidad_salida) as total_salido
                    FROM salidas_detalle
                    GROUP BY id_entrada_detalle
                ) as sd'),
                        'sd.id_entrada_detalle', '=', 'ed.id'
                    )
                    ->where('ed.id', $idEntradaDetalle)
                    ->selectRaw('
                (ed.cantidad_inicial - COALESCE(sd.total_salido, 0)) as disponible,
                e.fecha as fecha_entrada,
                m.nombre as nombre_material
            ')
                    ->first();

                // Item no encontrado
                if (!$info) {
                    DB::rollback();
                    return [
                        'success'         => 2,
                        'fila'            => $fila,
                        'nombre_material' => 'Material desconocido',
                        'cantidad_pedida' => number_format($datos['centavos'] / 100, 2, '.', ''),
                        'disponible'      => '0.00',
                    ];
                }

                // Validar que la fecha de salida no sea menor a la fecha de entrada
                $fechaEntrada = Carbon::parse($info->fecha_entrada)->startOfDay();

                if ($fechaSalida->lt($fechaEntrada)) {
                    DB::rollback();
                    return [
                        'success'         => 3,
                        'fila'            => $fila,
                        'nombre_material' => $info->nombre_material ?? 'Material desconocido',
                        'fecha_entrada'   => $fechaEntrada->format('d/m/Y'),
                        'fecha_salida'    => $fechaSalida->format('d/m/Y'),
                    ];
                }

                // Validar disponibilidad (comparación exacta en centavos)
                $disponibleCentavos = (int) round(((float) $info->disponible) * 100);

                if ($datos['centavos'] > $disponibleCentavos) {
                    DB::rollback();
                    return [
                        'success'         => 2,
                        'fila'            => $fila,
                        'nombre_material' => $info->nombre_material ?? 'Material desconocido',
                        'cantidad_pedida' => number_format($datos['centavos'] / 100, 2, '.', ''),
                        'disponible'      => number_format($disponibleCentavos / 100, 2, '.', ''),
                    ];
                }

                $fila++;
            }

            $infoGeneral = InformacionGeneral::where('id', 1)->first();

            // Guardar cabecera
            $salida                  = new Salidas();
            $salida->fecha           = $request->fecha;
            $salida->descripcion     = $request->descripcion;
            $salida->ficha_talonario = $request->ficha_talonario;
            $salida->numero_contrato = $request->numero_contrato;
            $salida->numero_orden    = $request->numero_orden;
            $salida->autoriza_a      = $request->autoriza_entrega;
            $salida->peticion_a      = $request->peticion_de;
            $salida->para_uso        = $request->para_uso_en;
            $salida->nombre_firma_1  = $infoGeneral->nombre_firma_1;
            $salida->nombre_firma_2  = $infoGeneral->nombre_firma_2;
            $salida->nombre_firma_3  = $infoGeneral->nombre_firma_3;
            $salida->nombre_firma_4  = $infoGeneral->nombre_firma_4;
            $salida->encabezado      = $infoGeneral->encabezado;
            $salida->pie_pagina      = $infoGeneral->pie_pagina;
            $salida->save();

            // Guardar detalle
            foreach ($agrupado as $idEntradaDetalle => $datos) {
                $detalle                     = new SalidasDetalle();
                $detalle->id_salida          = $salida->id;
                $detalle->id_entrada_detalle = $idEntradaDetalle;
                $detalle->cantidad_salida    = round($datos['centavos'] / 100, 2);
                $detalle->observaciones      = $datos['observacion'];
                $detalle->save();
            }

            DB::commit();
            return ['success' => 10];

        } catch (\Throwable $e) {
            DB::rollback();
            Log::error('guardarSalida: ' . $e);
            return ['success' => 99];
        }
    }





















}
