<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Entradas;
use App\Models\EntradasDetalle;
use App\Models\Equipos;
use App\Models\InformacionGeneral;
use App\Models\Materiales;
use App\Models\Proveedor;
use App\Models\Reserva;
use App\Models\Salidas;
use App\Models\SalidasDetalle;
use App\Models\TipoCompra;
use App\Models\TipoEntrada;
use App\Models\TipoProyecto;
use App\Models\Transferencia;
use App\Models\TransferenciaDetalle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class HistorialController extends Controller
{

    public function indexHistorialEntradas()
    {
        $proveedores = Proveedor::orderBy('nombre')->get();
        return view('backend.admin.historial.entradas.vistahistorialentradas', compact('proveedores'));
    }

    public function tablaHistorialEntradas(Request $request)
    {
        $arrayEntradas = Entradas::with(['proveedor'])
            ->when($request->fecha_desde, fn($q) =>
            $q->whereDate('fecha', '>=', $request->fecha_desde)
            )
            ->when($request->fecha_hasta, fn($q) =>
            $q->whereDate('fecha', '<=', $request->fecha_hasta)
            )
            ->when($request->factura, fn($q) =>
            $q->where('factura', 'LIKE', '%' . $request->factura . '%')
            )
            ->when($request->proveedor, fn($q) =>
            $q->where('id_proveedor', $request->proveedor)
            )
            ->orderBy('fecha', 'desc')
            ->get()
            ->map(function ($item) {
                $item->fecha_fmt       = date('d/m/Y', strtotime($item->fecha));
                $item->proveedor_nombre = $item->proveedor->nombre ?? '';
                return $item;
            });

        return view('backend.admin.historial.entradas.tablahistorialentradas',
            compact('arrayEntradas'));
    }

    public function informacionEntrada(Request $request)
    {
        $entrada = Entradas::with('proveedor')->find($request->id);

        if (!$entrada) {
            return response()->json(['success' => 0]);
        }

        return response()->json([
            'success' => 1,
            'entrada' => [
                'id'           => $entrada->id,
                'fecha'        => $entrada->fecha,
                'factura'      => $entrada->factura,
                'descripcion'  => $entrada->descripcion,
                'id_proveedor' => $entrada->id_proveedor,
            ]
        ]);
    }

    public function editarEntrada(Request $request)
    {
        $entrada = Entradas::find($request->id);

        if (!$entrada) {
            return response()->json(['success' => 0]);
        }

        $entrada->fecha        = $request->fecha;
        $entrada->factura      = $request->factura      ?: null;
        $entrada->descripcion  = $request->descripcion  ?: null;
        $entrada->id_proveedor = $request->id_proveedor ?: $entrada->id_proveedor;
        $entrada->save();

        return response()->json(['success' => 1]);
    }


    public function eliminarEntrada(Request $request)
    {
        $entrada = Entradas::find($request->id);

        if (!$entrada) {
            return response()->json(['success' => 0]);
        }

        DB::beginTransaction();

        try {
            $idsDetalle = $entrada->detalle()->pluck('id');

            if ($idsDetalle->isNotEmpty()) {

                // Verificar si algún detalle tiene salidas
                $tieneSalidas = SalidasDetalle::whereIn('id_entrada_detalle', $idsDetalle)->exists();

                if ($tieneSalidas) {
                    DB::rollback();
                    return response()->json([
                        'success' => 2,
                        'msg' => 'Esta entrada tiene salidas registradas y no puede eliminarse.',
                    ]);
                }

                // Borrar entradas_detalle
                $entrada->detalle()->delete();
            }

            $entrada->delete();

            DB::commit();
            return response()->json(['success' => 1]);

        } catch (\Throwable $e) {
            DB::rollback();
            Log::error('eliminarEntrada: ' . $e->getMessage());
            return response()->json(['success' => 99]);
        }
    }

    public function detalleEntrada(Request $request)
    {
        $entrada = Entradas::find($request->id);

        if (!$entrada) {
            return response()->json(['success' => 0]);
        }

        $detalle = $entrada->detalle()
            ->with('material')
            ->get()
            ->map(function ($item) {
                $tieneSalidas = SalidasDetalle::where('id_entrada_detalle', $item->id)->exists();
                return [
                    'id'               => $item->id,
                    'codigo'           => $item->codigo ?? '',
                    'nombre'           => $item->nombre ?? '',
                    'material'         => $item->material->nombre ?? '',
                    'cantidad_inicial' => $item->cantidad_inicial,
                    'precio'           => number_format($item->precio, 4),
                    'precio_raw'       => $item->precio,
                    'tiene_salidas'    => $tieneSalidas ? 1 : 0,
                ];
            });

        return response()->json([
            'success' => 1,
            'detalle' => $detalle,
        ]);
    }

    public function editarDetalleEntrada(Request $request)
    {
        $detalle = EntradasDetalle::find($request->id);

        if (!$detalle) {
            return response()->json(['success' => 0]);
        }

        $detalle->codigo = $request->codigo ?: null;
        $detalle->precio = $request->precio;

        // Actualizar cantidad solo si no tiene salidas
        if ($request->filled('cantidad')) {
            $tieneSalidas = SalidasDetalle::where('id_entrada_detalle', $detalle->id)->exists();
            if ($tieneSalidas) {
                return response()->json([
                    'success' => 2,
                    'msg'     => 'No se puede modificar la cantidad porque este material ya tiene salidas registradas.',
                ]);
            }
            $detalle->cantidad_inicial = (int) $request->cantidad;
        }

        $detalle->save();

        return response()->json(['success' => 1]);
    }

    public function eliminarDetalleEntrada(Request $request)
    {
        $detalle = EntradasDetalle::find($request->id);

        if (!$detalle) {
            return response()->json(['success' => 0]);
        }

        // Bloquear si tiene salidas
        $tieneSalidas = SalidasDetalle::where('id_entrada_detalle', $detalle->id)->exists();
        if ($tieneSalidas) {
            return response()->json([
                'success' => 4,
                'msg' => 'Este material ya tiene salidas registradas y no puede eliminarse.',
            ]);
        }

        DB::beginTransaction();
        try {
            $entradaId = $detalle->id_entradas;
            $detalle->delete();

            // Si era el último detalle, eliminar también la cabecera
            $quedan = EntradasDetalle::where('id_entradas', $entradaId)->count();

            if ($quedan === 0) {
                Entradas::where('id', $entradaId)->delete();
                DB::commit();
                return response()->json(['success' => 1, 'entrada_borrada' => true]);
            }

            DB::commit();
            return response()->json(['success' => 1, 'entrada_borrada' => false]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('eliminarDetalleEntrada: ' . $e->getMessage());
            return response()->json(['success' => 99, 'msg' => 'Error al eliminar.']);
        }
    }


    public function vistaExtrasEntrada($id)
    {
        $entrada = Entradas::with('tipoproyecto')->find($id);

        if (!$entrada || $entrada->tipoproyecto->transferido == 1) {
            return redirect()->route('admin.historial.entradas.index')
                ->with('error', 'El proyecto está cerrado, no se pueden agregar extras');
        }

        return view('backend.admin.historial.entradas.vistaextras', compact('entrada'));
    }

    public function guardarExtrasEntrada(Request $request)
    {
        $entrada = Entradas::find($request->id_entrada);

        if (!$entrada) {
            return response()->json(['success' => 0]);
        }

        // Verificar que el proyecto no esté cerrado
        if ($entrada->tipoproyecto->transferido == 1) {
            return response()->json(['success' => 1, 'mensaje' => 'El proyecto está cerrado']);
        }

        $contenedor = json_decode($request->contenedorArray, true);

        if (empty($contenedor)) {
            return response()->json(['success' => 0]);
        }

        foreach ($contenedor as $item) {
            EntradasDetalle::create([
                'id_entradas' => $entrada->id,
                'id_material' => $item['idMaterial'],
                'cantidad_inicial' => $item['infoCantidad'],
                'codigo' => $item['infoCodigo'] ?: null,
                'precio' => $item['infoPrecio'],
            ]);
        }

        return response()->json(['success' => 2]);
    }

    //***** ========================================================================================= **********


    public function indexHistorialSalidas()
    {
        return view('backend.admin.historial.salidas.vistahistorialsalidas');
    }

    public function tablaHistorialSalidas(Request $request)
    {
        $arraySalidas = Salidas::query()
            ->when($request->fecha_desde, fn($q) =>
            $q->whereDate('fecha', '>=', $request->fecha_desde)
            )
            ->when($request->fecha_hasta, fn($q) =>
            $q->whereDate('fecha', '<=', $request->fecha_hasta)
            )
            ->when($request->talonario, fn($q) =>
            $q->where('ficha_talonario', 'LIKE', '%' . $request->talonario . '%')
            )
            ->when($request->contrato, fn($q) =>
            $q->where('numero_contrato', 'LIKE', '%' . $request->contrato . '%')
            )
            ->when($request->orden, fn($q) =>
            $q->where('numero_orden', 'LIKE', '%' . $request->orden . '%')
            )
            ->when($request->material, function ($q) use ($request) {
                $busqueda = '%' . $request->material . '%';
                $q->whereHas('detalle.entradaDetalle.material', function ($q2) use ($busqueda) {
                    $q2->where('nombre', 'LIKE', $busqueda);
                });
            })
            ->orderBy('fecha', 'desc')
            ->get()
            ->map(function ($item) {
                $item->fecha_fmt = date('d/m/Y', strtotime($item->fecha));
                return $item;
            });

        return view('backend.admin.historial.salidas.tablahistorialsalidas',
            compact('arraySalidas'));
    }


    public function informacionSalida(Request $request)
    {
        $salida = Salidas::find($request->id);

        if (!$salida) {
            return response()->json(['success' => 0]);
        }

        return response()->json([
            'success' => 1,
            'salida'  => [
                'id'              => $salida->id,
                'fecha'           => $salida->fecha,
                'descripcion'     => $salida->descripcion,
                'ficha_nombre'    => $salida->ficha_nombre,
                'ficha_talonario' => $salida->ficha_talonario,
                'numero_contrato' => $salida->numero_contrato,
                'numero_orden'    => $salida->numero_orden,
                'nombre_firma_1'  => $salida->nombre_firma_1,
                'nombre_firma_2'  => $salida->nombre_firma_2,
                'nombre_firma_3'  => $salida->nombre_firma_3,
                'autoriza_a'      => $salida->autoriza_a,
                'para_uso'        => $salida->para_uso,
                'peticion_a'      => $salida->peticion_a,
                'encabezado'      => $salida->encabezado,
                'pie_pagina'      => $salida->pie_pagina,
            ]
        ]);
    }

    public function editarSalida(Request $request)
    {
        $salida = Salidas::find($request->id);

        if (!$salida) {
            return response()->json(['success' => 0]);
        }

        $salida->fecha           = $request->fecha;
        $salida->descripcion     = $request->descripcion     ?: null;
        $salida->ficha_talonario = $request->ficha_talonario ?: null;
        $salida->numero_contrato = $request->numero_contrato ?: null;
        $salida->numero_orden    = $request->numero_orden    ?: null;
        $salida->nombre_firma_1  = $request->nombre_firma_1  ?: null;
        $salida->nombre_firma_2  = $request->nombre_firma_2  ?: null;
        $salida->nombre_firma_3  = $request->nombre_firma_3  ?: null;
        $salida->autoriza_a      = $request->autoriza_a      ?: null;
        $salida->para_uso        = $request->para_uso        ?: null;
        $salida->peticion_a      = $request->peticion_a      ?: null;
        $salida->encabezado      = $request->encabezado      ?: null;
        $salida->pie_pagina      = $request->pie_pagina      ?: null;
        $salida->save();

        return response()->json(['success' => 1]);
    }


    public function eliminarSalida(Request $request)
    {
        $salida = Salidas::find($request->id);

        if (!$salida) {
            return response()->json(['success' => 0]);
        }

        DB::beginTransaction();
        try {
            $salida->detalle()->delete();
            $salida->delete();
            DB::commit();
            return response()->json(['success' => 1]);
        } catch (\Throwable $e) {
            DB::rollback();
            Log::error('eliminarSalida: ' . $e->getMessage());
            return response()->json(['success' => 99]);
        }
    }

    public function detalleSalida(Request $request)
    {
        $salida = Salidas::find($request->id);

        if (!$salida) {
            return response()->json(['success' => 0]);
        }

        $detalle = $salida->detalle()
            ->with('entradaDetalle.material')
            ->get()
            ->map(function ($item) {
                return [
                    'id'              => $item->id,
                    'material'        => $item->entradaDetalle->material->nombre ?? '',
                    'cantidad_salida' => $item->cantidad_salida,
                    'precio'          => number_format($item->entradaDetalle->precio ?? 0, 4),
                ];
            });

        return response()->json([
            'success' => 1,
            'detalle' => $detalle,
        ]);
    }


    public function vistaExtrasSalida($id)
    {
        $salida = Salidas::with('equipo')->find($id);

        if (!$salida) {
            return redirect()->route('admin.historial.salidas.index');
        }

        return view('backend.admin.historial.salidas.vistaextrassalidas', compact('salida'));
    }

    public function guardarExtrasSalida(Request $request)
    {
        $salida = Salidas::find($request->id_salida);

        if (!$salida) {
            return response()->json(['success' => 0]);
        }

        $contenedor = json_decode($request->contenedorArray, true);

        if (empty($contenedor)) {
            return response()->json(['success' => 0]);
        }

        // ── Agrupar por id_entrada_detalle para sumar si viene el mismo lote dos veces ──
        $agrupado = [];
        foreach ($contenedor as $index => $item) {
            $id = $item['infoIdEntradaDeta'];
            if (!isset($agrupado[$id])) {
                $agrupado[$id] = ['cantidad' => 0, 'fila' => $index + 1];
            }
            $agrupado[$id]['cantidad'] += (int) $item['infoCantidad'];
        }

        // ── Validar disponibilidad ──
        foreach ($agrupado as $idEntradaDeta => $datos) {
            $entDetalle = EntradasDetalle::with('material')->find($idEntradaDeta);

            if (!$entDetalle) {
                return response()->json([
                    'success' => 2,
                    'fila'    => $datos['fila'],
                    'msg'     => 'Material no encontrado en el lote.',
                ]);
            }

            $totalSalido = SalidasDetalle::where('id_entrada_detalle', $entDetalle->id)
                ->sum('cantidad_salida');

            $disponible = $entDetalle->cantidad_inicial - $totalSalido;

            if ($datos['cantidad'] > $disponible) {
                return response()->json([
                    'success'         => 2,
                    'fila'            => $datos['fila'],
                    'msg'             => 'Cantidad insuficiente.',
                    'nombre_material' => $entDetalle->material->nombre ?? 'Material desconocido',
                    'cantidad_pedida' => $datos['cantidad'],
                    'disponible'      => (int) $disponible,
                ]);
            }
        }

        // ── Guardar ──
        foreach ($contenedor as $item) {
            SalidasDetalle::create([
                'id_salida'          => $salida->id,
                'id_entrada_detalle' => $item['infoIdEntradaDeta'],
                'cantidad_salida'    => (int) $item['infoCantidad'],
            ]);
        }

        return response()->json(['success' => 10]);
    }


    public function eliminarDetalleSalida(Request $request)
    {
        $detalle = SalidasDetalle::find($request->id);

        if (!$detalle) {
            return response()->json(['success' => 0]);
        }

        DB::beginTransaction();
        try {
            $salidaId = $detalle->id_salida;
            $detalle->delete();

            $quedan = SalidasDetalle::where('id_salida', $salidaId)->count();

            if ($quedan === 0) {
                Salidas::where('id', $salidaId)->delete();
                DB::commit();
                return response()->json(['success' => 1, 'salida_borrada' => true]);
            }

            DB::commit();
            return response()->json(['success' => 1, 'salida_borrada' => false]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('eliminarDetalleSalida: ' . $e->getMessage());
            return response()->json(['success' => 99]);
        }
    }


    public function buscadorMaterialGetNombre(Request $request)
    {
        if (!$request->get('query')) return response()->json([]);

        $query = $request->get('query');

        $materiales = Materiales::where('nombre', 'LIKE', "%{$query}%")
            ->orderBy('nombre')
            ->limit(20)
            ->pluck('nombre');

        return response()->json($materiales);
    }


    public function editarCantidadSalida(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'       => 'required|integer',
            'cantidad' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'mensaje' => 'Datos inválidos']);
        }

        $detalle = SalidasDetalle::find($request->id);

        if (!$detalle) {
            return response()->json(['success' => 0, 'mensaje' => 'Registro no encontrado']);
        }

        // Disponible real = cantidad_inicial - todo lo salido de otros registros (excluye el actual)
        $disponibleReal = DB::table('entradas_detalle as ed')
            ->leftJoin(
                DB::raw('(
                SELECT id_entrada_detalle, SUM(cantidad_salida) as total_salido
                FROM salidas_detalle
                WHERE id != ' . (int)$detalle->id . '
                GROUP BY id_entrada_detalle
            ) as sd'),
                'sd.id_entrada_detalle', '=', 'ed.id'
            )
            ->where('ed.id', $detalle->id_entrada_detalle)
            ->selectRaw('(ed.cantidad_inicial - COALESCE(sd.total_salido, 0)) as disponible')
            ->value('disponible');

        if (is_null($disponibleReal) || $request->cantidad > $disponibleReal) {
            return response()->json([
                'success'    => 2,
                'disponible' => (int)$disponibleReal,
                'mensaje'    => 'Cantidad supera el disponible. Máximo permitido: ' . (int)$disponibleReal,
            ]);
        }

        $detalle->cantidad_salida = $request->cantidad;
        $detalle->save();

        return response()->json(['success' => 1]);
    }



    public function generarPDFSalidaGuardado($id)
    {
        $salida = Salidas::with(['detalle.entradaDetalle.material.unidadMedida'])->findOrFail($id);

        $infoGeneral = InformacionGeneral::where('id', 1)->first();

        $fechaFmt        = $salida->fecha ? date('d/m/Y', strtotime($salida->fecha)) : '';
        $logoalcaldia    = 'images/logo.png';
        $autorizaEntrega = htmlspecialchars($salida->autoriza_a    ?? '');
        $peticionDe      = htmlspecialchars($salida->peticion_a    ?? '');
        $paraUsoEn       = htmlspecialchars($salida->para_uso      ?? '');
        $firmaDerecha    = htmlspecialchars($salida->nombre_firma_1 ?? '');

        // ── Encabezado ────────────────────────────────────────────────
        $html = "
<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif;'>
    <tr>
        <td style='width:25%; border:0.8px solid #000; padding:6px 8px;'>
            <table width='100%'>
                <tr>
                    <td style='width:30%; text-align:left;'>
                        <img src='{$logoalcaldia}' style='height:38px'>
                    </td>
                    <td style='width:70%; text-align:left; color:#104e8c; font-size:13px; font-weight:bold; line-height:1.3;'>
                        SANTA ANA NORTE<br>EL SALVADOR
                    </td>
                </tr>
            </table>
        </td>
        <td style='width:50%; border-top:0.8px solid #000; border-bottom:0.8px solid #000;
             padding:6px 8px; text-align:center; font-size:15px; font-weight:bold;'>
            FORMULARIO ENTREGA DE<br>MATERIALES DE BODEGA
        </td>
        <td style='width:25%; border:0.8px solid #000; padding:0; vertical-align:top;'>
            <table width='100%' style='font-size:10px;'>
                <tr>
                    <td width='40%' style='border-right:0.8px solid #000; border-bottom:0.8px solid #000; padding:4px 6px;'><strong>Código:</strong></td>
                    <td width='60%' style='border-bottom:0.8px solid #000; padding:4px 6px; text-align:center;'>MANB-002-FORM</td>
                </tr>
                <tr>
                    <td style='border-right:0.8px solid #000; border-bottom:0.8px solid #000; padding:4px 6px;'><strong>Versión:</strong></td>
                    <td style='border-bottom:0.8px solid #000; padding:4px 6px; text-align:center;'>000</td>
                </tr>
                <tr>
                    <td style='border-right:0.8px solid #000; padding:4px 6px;'><strong>Fecha de vigencia:</strong></td>
                    <td style='padding:4px 6px; text-align:center;'>22/10/2025</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
<br>";

        // ── Fecha ─────────────────────────────────────────────────────
        $html .= "
<table width='100%' style='font-family:Arial, sans-serif; font-size:13px; border-collapse:collapse;'>
    <tr>
        <td width='100%' style='text-align:right;'>
            <strong>FECHA:</strong> {$fechaFmt}
        </td>
    </tr>
</table>

<table width='100%' style='font-family:Arial, sans-serif;'>
    <tr>
        <td align='left'>
            <div style='border-top:1px solid #000; width:250px;'></div>
            <div style='margin-top:5px; font-size:13px; font-weight:normal;'>
                {$infoGeneral->encabezado}
            </div>
        </td>
    </tr>
</table>
<br>";

        // ── Autoriza / Petición / Uso ─────────────────────────────────
        $html .= "
<table width='100%' style='font-family:Arial, sans-serif; font-size:13px; border-collapse:collapse;'>
    <tr>
        <td style='white-space:nowrap; padding:3px 0; width:210px;'>Autoriza la entrega de materiales a:</td>
        <td style='padding:3px 6px;'>{$autorizaEntrega}</td>
    </tr>
    <tr>
        <td style='white-space:nowrap; padding:3px 0;'>A petición de:</td>
        <td style='padding:3px 6px;'>{$peticionDe}</td>
    </tr>
    <tr>
        <td style='white-space:nowrap; padding:3px 0;'>Para uso en:</td>
        <td style='padding:3px 6px;'>{$paraUsoEn}</td>
    </tr>
    <tr>
        <td style='padding:5px 0;' colspan='2'>Según el siguiente detalle:</td>
    </tr>
</table>
<br>";

        // ── Tabla de materiales ───────────────────────────────────────
        $html .= "
<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; font-size:11px;'>
    <thead>
        <tr>
            <th style='width:5%;  border:0.8px solid #000; padding:5px 4px; text-align:center; background:#e8e8e8; font-size:12px'>N°</th>
            <th style='width:42%; border:0.8px solid #000; padding:5px 8px; text-align:center; background:#e8e8e8; font-size:12px'>DESCRIPCION</th>
            <th style='width:16%; border:0.8px solid #000; padding:5px 4px; text-align:center; background:#e8e8e8; font-size:12px'>UNIDAD DE MEDIDA</th>
            <th style='width:10%; border:0.8px solid #000; padding:5px 4px; text-align:center; background:#e8e8e8; font-size:12px'>CANTIDAD</th>
            <th style='width:27%; border:0.8px solid #000; padding:5px 8px; text-align:center; background:#e8e8e8; font-size:12px'>OBSERVACIONES</th>
        </tr>
    </thead>
    <tbody>";

        $num = 0;
        foreach ($salida->detalle as $det) {
            $num++;
            $cantidad    = $det->cantidad_salida;
            $observacion = htmlspecialchars($det->observaciones ?? '');
            $nombreMat   = '';
            $unidadMed   = '';

            if ($det->entradaDetalle && $det->entradaDetalle->material) {
                $mat       = $det->entradaDetalle->material;
                $nombreMat = htmlspecialchars($mat->nombre);
                $unidadMed = htmlspecialchars($mat->unidadMedida->nombre ?? '');
            }

            $html .= "
        <tr>
            <td style='border:0.8px solid #000; padding:4px; text-align:center; vertical-align:middle; font-size:13px'>{$num}</td>
            <td style='border:0.8px solid #000; padding:4px; text-align:left; vertical-align:middle; font-size:13px;'>{$nombreMat}</td>
            <td style='border:0.8px solid #000; padding:4px; text-align:center; vertical-align:middle; font-size:13px;'>{$unidadMed}</td>
            <td style='border:0.8px solid #000; padding:4px; text-align:center; vertical-align:middle; font-size:13px'>{$cantidad}</td>
            <td style='border:0.8px solid #000; padding:4px 8px; vertical-align:middle; text-align:left; font-size:13px'>{$observacion}</td>
        </tr>";
        }

        $html .= "
    </tbody>
</table>
<br>";

        // ── Pie de página ─────────────────────────────────────────────
        $html .= "
<table width='100%' style='font-family:Arial, sans-serif;'>
    <tr>
        <td align='left'>
            <div style='margin-top:5px; font-size:12px;'>
                {$infoGeneral->pie_pagina}
            </div>
        </td>
    </tr>
</table>
<br><br><br>";

        // ── Firmas ────────────────────────────────────────────────────
        $firmaDerTexto = $firmaDerecha ?: '________________________________';

        $html .= "
<table width='100%' style='margin-top:" . ($infoGeneral->px_firmas ?? 0) . "px; font-family:Arial, sans-serif; font-size:11px; border-collapse:collapse;'>
    <tr>
        <td width='40%' style='text-align:center; padding-bottom:4px;'>________________________________</td>
        <td width='20%'></td>
        <td width='40%' style='text-align:center; padding-bottom:4px;'>________________________________</td>
    </tr>
    <tr>
        <td style='text-align:center; font-size:12px; padding-top:6px;'>{$salida->nombre_firma_2}</td>
        <td></td>
        <td style='text-align:center; font-size:12px; padding-top:6px;'>{$salida->nombre_firma_3}</td>
    </tr>
    <tr>
        <td style='text-align:center; font-size:12px; font-weight:bold;'>{$salida->nombre_firma_1}</td>
        <td></td>
        <td></td>
    </tr>
</table>";

        // ── Generar PDF ───────────────────────────────────────────────
        $mpdf = new \Mpdf\Mpdf([
            'tempDir'       => sys_get_temp_dir(),
            'format'        => 'LETTER',
            'margin_top'    => 15,
            'margin_bottom' => 15,
            'margin_left'   => 15,
            'margin_right'  => 15,
        ]);

        $mpdf->SetTitle('Formulario de Salida de Bodega');
        $mpdf->showImageErrors = false;

        $stylesheet = file_get_contents('css/cssregistro.css');
        $mpdf->WriteHTML($stylesheet, 1);
        $mpdf->WriteHTML($html, 2);
        $mpdf->Output('salida_bodega_' . $salida->id . '_' . date('Ymd') . '.pdf', 'I');
    }



}
