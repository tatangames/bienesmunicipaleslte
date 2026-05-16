<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Entradas;
use App\Models\EntradasDetalle;
use App\Models\Materiales;
use App\Models\Salidas;
use App\Models\SalidasDetalle;
use App\Models\TipoProyecto;
use App\Models\Transferencia;
use App\Models\TransferenciaDetalle;
use App\Models\UnidadMedida;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportesController extends Controller
{



    public function pdfQueHaSalidoProyectos($idproy, $desde, $hasta, $tipo)
    {
        $infoProyecto = Tipoproyecto::find($idproy);

        $sinFecha = ($desde === 'null' || $desde === '' || $hasta === 'null' || $hasta === '');

        $logoalcaldia = 'images/logo.png';

        if (!$sinFecha) {
            $start      = date('Y-m-d 00:00:00', strtotime($desde));
            $end        = date('Y-m-d 23:59:59', strtotime($hasta));
            $fechaLabel = "Fecha: " . date("d-m-Y", strtotime($desde)) . "  -  " . date("d-m-Y", strtotime($hasta));
        } else {
            $fechaLabel = "Todas las fechas";
        }

        $encabezado = "
<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:6px;'>
    <tr>
        <td style='width:30%; border:0.8px solid #000; padding:6px 8px;'>
            <table width='100%'>
                <tr>
                    <td style='width:35%; text-align:left;'>
                        <img src='{$logoalcaldia}' style='height:40px'>
                    </td>
                    <td style='width:65%; text-align:left; color:#104e8c;
                                font-size:12px; font-weight:bold; line-height:1.4;'>
                        SANTA ANA NORTE<br>EL SALVADOR
                    </td>
                </tr>
            </table>
        </td>
        <td style='width:70%; border:0.8px solid #000;
                    padding:8px; text-align:center; vertical-align:middle;'>
            <h2 style='margin:0;'>Reporte de Materiales Entregados</h2>
            <p style='margin:0; font-size:12px;'>$fechaLabel</p>
        </td>
    </tr>
</table>";

        // ─── TIPO 1: JUNTOS ───────────────────────────────────────────
        if ($tipo == 1) {

            $query = Salidas::where('id_tipoproyecto', $idproy);
            if (!$sinFecha) {
                $query->whereBetween('fecha', [$start, $end]);
            }
            $idsSalidas = $query->orderBy('fecha', 'ASC')->pluck('id');

            $totalSalidas = $idsSalidas->count();

            $detalles = SalidasDetalle::with('entradaDetalle.material.unidadMedida')
                ->whereIn('id_salida', $idsSalidas)
                ->get();

            $dataArray         = [];
            $sumaTotalCantidad = 0;

            foreach ($detalles as $det) {
                $entDet = $det->entradaDetalle;
                if (!$entDet || !$entDet->material) continue;

                $idMat = $entDet->id_material;

                if (!isset($dataArray[$idMat])) {
                    $dataArray[$idMat] = [
                        'nombre'   => $entDet->material->nombre ?? '',
                        'medida'   => $entDet->material->unidadMedida->nombre ?? '',
                        'codigo'   => $entDet->codigo ?? '',
                        'cantidad' => 0,
                        'total'    => 0,
                        'precio'   => 0,
                    ];
                }

                $dataArray[$idMat]['cantidad']  += $det->cantidad_salida;
                $dataArray[$idMat]['total']     += ($det->cantidad_salida * $entDet->precio);
                $dataArray[$idMat]['precio']     = $entDet->precio;
                $sumaTotalCantidad              += $det->cantidad_salida;
            }

            usort($dataArray, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

            $granTotal            = array_sum(array_column($dataArray, 'total'));
            $granTotalFmt         = number_format($granTotal, 4);
            $sumaTotalCantidadFmt = number_format($sumaTotalCantidad, 2, '.', ',');

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Reporte de Materiales Entregados');
            $mpdf->showImageErrors = false;

            $tabla  = $encabezado;
            $tabla .= "
        <p style='font-size:15px;'>
            <span style='font-weight:bold;'>Proyecto:</span> {$infoProyecto->nombre}
        </p>
        <p style='font-size:13px;'>
            <span style='font-weight:bold;'>Total de salidas registradas:</span> $totalSalidas
        </p>";

            $tabla .= "
    <table width='100%' id='tablaFor'>
        <tbody>
            <tr>
                <td style='font-weight:bold; width:13%; font-size:13px;'>Código</td>
                <td style='font-weight:bold; width:35%; font-size:13px;'>Material</td>
                <td style='font-weight:bold; width:12%; font-size:13px;'>Medida</td>
                <td style='font-weight:bold; width:12%; font-size:13px;'>Cantidad</td>
                <td style='font-weight:bold; width:15%; font-size:13px;'>Precio Unit.</td>
                <td style='font-weight:bold; width:15%; font-size:13px;'>Total ($)</td>
            </tr>";

            foreach ($dataArray as $info) {
                $precioFmt = number_format($info['precio'], 4);
                $totalFmt  = number_format($info['total'], 4);

                $tabla .= "
            <tr>
                <td style='font-size:12px;'>{$info['codigo']}</td>
                <td style='text-align:left; font-size:12px;'>{$info['nombre']}</td>
                <td style='font-size:12px;'>{$info['medida']}</td>
                <td style='font-size:12px;'>{$info['cantidad']}</td>
                <td style='font-size:12px;'>$ $precioFmt</td>
                <td style='font-size:12px;'>$ $totalFmt</td>
            </tr>";
            }

            $tabla .= "
            <tr>
                <td colspan='3' style='font-weight:bold; font-size:13px; text-align:right;
                                        border-top:1.5px solid #000; padding-top:4px;'>
                    TOTAL CANTIDAD:
                </td>
                <td style='font-weight:bold; font-size:13px; border-top:1.5px solid #000; padding-top:4px;'>
                    $sumaTotalCantidadFmt
                </td>
                <td style='font-weight:bold; font-size:13px; text-align:right;
                            border-top:1.5px solid #000; padding-top:4px;'>
                    TOTAL GENERAL:
                </td>
                <td style='font-weight:bold; font-size:13px; border-top:1.5px solid #000; padding-top:4px;'>
                    $ $granTotalFmt
                </td>
            </tr>
        </tbody>
    </table>";

            // ─── TIPO 2: SEPARADOS ────────────────────────────────────────
        } else {

            $query = Salidas::with([
                'detalle.entradaDetalle.material.unidadMedida',
                'proyectoTransferencia', // ← relación al proyecto destino
            ])->where('id_tipoproyecto', $idproy);

            if (!$sinFecha) {
                $query->whereBetween('fecha', [$start, $end]);
            }

            $arraySalidas = $query->orderBy('fecha', 'ASC')->get();

            $totalSalidas      = $arraySalidas->count();
            $granTotal         = 0;
            $sumaTotalCantidad = 0;

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Reporte de Materiales Entregados');
            $mpdf->showImageErrors = false;

            $tabla  = $encabezado;
            $tabla .= "
        <p style='font-size:15px;'>
            <span style='font-weight:bold;'>Proyecto:</span> {$infoProyecto->nombre}
        </p>
        <p style='font-size:13px;'>
            <span style='font-weight:bold;'>Total de salidas registradas:</span> $totalSalidas
        </p>";

            foreach ($arraySalidas as $salida) {

                $fechaFmt        = date("d-m-Y", strtotime($salida->fecha));
                $descripcion     = $salida->descripcion ?? '';
                $esTransferencia = (int) $salida->es_transferencia === 1;

                // ── Badge de transferencia con destino ──
                if ($esTransferencia) {

                    if ($salida->id_tipoproyecto_transferencia) {
                        // Fue a un proyecto específico
                        $nombreDestino = $salida->proyectoTransferencia
                            ? $salida->proyectoTransferencia->nombre
                            : 'Proyecto #' . $salida->id_tipoproyecto_transferencia;
                        $textoLabel = "TRANSFERENCIA &#8594; $nombreDestino";
                    } else {
                        // Salida general sin proyecto destino
                        $textoLabel = "SALIDA GENERAL (Sin proyecto destino)";
                    }

                    $tabla .= "
                <table width='100%' style='margin-bottom:3px;'>
                    <tbody>
                        <tr>
                            <td style='
                                background-color:#e9e9e9;
                                border:1px solid #aaaaaa;
                                color:#444444;
                                font-weight:bold;
                                font-size:12px;
                                padding:4px 8px;
                                text-align:center;
                            '>
                                $textoLabel
                            </td>
                        </tr>
                    </tbody>
                </table>";
                }

                $tabla .= "
        <table width='100%' id='tablaFor'>
            <tbody>
                <tr>
                    <td style='font-weight:bold; width:15%; font-size:13px;'>Fecha</td>
                    <td style='font-weight:bold; width:85%; font-size:13px;'>Descripción</td>
                </tr>
                <tr>
                    <td style='font-size:12px;'>$fechaFmt</td>
                    <td style='font-size:12px;'>$descripcion</td>
                </tr>
            </tbody>
        </table>";

                $tabla .= "
        <table width='100%' id='tablaFor'>
            <tbody>
                <tr>
                    <td style='font-weight:bold; width:12%; font-size:13px;'>Código</td>
                    <td style='font-weight:bold; width:12%; font-size:13px;'>Medida</td>
                    <td style='font-weight:bold; width:30%; font-size:13px;'>Material</td>
                    <td style='font-weight:bold; width:11%; font-size:13px;'>Cantidad</td>
                    <td style='font-weight:bold; width:15%; font-size:13px;'>Precio Unit.</td>
                    <td style='font-weight:bold; width:15%; font-size:13px;'>Total ($)</td>
                </tr>";

                $subtotal         = 0;
                $subtotalCantidad = 0;

                foreach ($salida->detalle as $det) {
                    $entDet = $det->entradaDetalle;
                    if (!$entDet || !$entDet->material) continue;

                    $codigo    = $entDet->codigo ?? '';
                    $medida    = $entDet->material->unidadMedida->nombre ?? '';
                    $nombreMat = $entDet->material->nombre ?? '';
                    $cantidad  = $det->cantidad_salida;
                    $precio    = $entDet->precio ?? 0;
                    $total     = $cantidad * $precio;

                    $granTotal         += $total;
                    $subtotal          += $total;
                    $sumaTotalCantidad += $cantidad;
                    $subtotalCantidad  += $cantidad;

                    $precioFmt = number_format($precio, 4);
                    $totalFmt  = number_format($total, 4);

                    $tabla .= "
                <tr>
                    <td style='font-size:12px;'>$codigo</td>
                    <td style='font-size:12px;'>$medida</td>
                    <td style='font-size:12px;'>$nombreMat</td>
                    <td style='font-size:12px;'>$cantidad</td>
                    <td style='font-size:12px;'>$ $precioFmt</td>
                    <td style='font-size:12px;'>$ $totalFmt</td>
                </tr>";
                }

                $subtotalFmt         = number_format($subtotal, 4);
                $subtotalCantidadFmt = number_format($subtotalCantidad, 2, '.', ',');

                $tabla .= "
                <tr>
                    <td colspan='2' style='border-top:1px solid #000;'></td>
                    <td style='font-weight:bold; font-size:12px; text-align:right;
                               border-top:1px solid #000; padding-top:3px;'>
                        Subtotal cantidad:
                    </td>
                    <td style='font-weight:bold; font-size:12px;
                               border-top:1px solid #000; padding-top:3px;'>
                        $subtotalCantidadFmt
                    </td>
                    <td style='font-weight:bold; font-size:12px; text-align:right;
                               border-top:1px solid #000; padding-top:3px;'>
                        Subtotal:
                    </td>
                    <td style='font-weight:bold; font-size:12px;
                               border-top:1px solid #000; padding-top:3px;'>
                        $ $subtotalFmt
                    </td>
                </tr>
            </tbody>
        </table><br>";
            }

            $granTotalFmt         = number_format($granTotal, 4);
            $sumaTotalCantidadFmt = number_format($sumaTotalCantidad, 2, '.', ',');

            $tabla .= "
    <table width='100%' style='margin-top:10px;'>
        <tbody>
            <tr>
                <td style='font-weight:bold; font-size:14px; text-align:right;
                            border-top:2px solid #000; padding-top:6px;'>
                    TOTAL CANTIDAD:&nbsp;&nbsp;
                </td>
                <td style='font-weight:bold; font-size:14px; width:15%;
                            border-top:2px solid #000; padding-top:6px;'>
                    $sumaTotalCantidadFmt
                </td>
                <td style='font-weight:bold; font-size:14px; text-align:right;
                            border-top:2px solid #000; padding-top:6px;'>
                    TOTAL GENERAL:&nbsp;&nbsp;
                </td>
                <td style='font-weight:bold; font-size:14px; width:18%;
                            border-top:2px solid #000; padding-top:6px;'>
                    $ $granTotalFmt
                </td>
            </tr>
        </tbody>
    </table>";
        }

        $stylesheet = file_get_contents('css/cssregistro.css');
        $mpdf->WriteHTML($stylesheet, 1);
        $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
        $mpdf->WriteHTML($tabla, 2);
        $mpdf->Output();
    }



    public function vistaQueTengoPorProyecto()
    {
        $proyectos   = Tipoproyecto::where('transferido', 0)->orderBy('nombre', 'ASC')->get();
        $transferido = Tipoproyecto::where('transferido', 1)->orderBy('nombre', 'ASC')->get();

        return view('backend.admin.repuestos.reporte.vistaquetengoporproyecto', compact('proyectos', 'transferido'));
    }

    public function reporteQueTengoPorProyecto($idproy)
    {
        $infoProyecto = Tipoproyecto::find($idproy);
        $fechaFormat  = date("d-m-Y");
        $logoalcaldia = 'images/logo.png';

        // Obtener entradas_detalle del proyecto con material y medida
        $detalles = EntradasDetalle::with('material.unidadMedida')
            ->whereHas('entrada', fn($q) => $q->where('id_tipoproyecto', $idproy))
            ->get();

        // Agrupar por material y calcular stock
        $porMaterial = [];

        foreach ($detalles as $det) {
            if (!$det->material) continue;

            $idMat = $det->id_material;

            if (!isset($porMaterial[$idMat])) {
                $porMaterial[$idMat] = [
                    'nombre'   => $det->material->nombre ?? '',
                    'medida'   => $det->material->unidadMedida->nombre ?? '',
                    'codigo'   => $det->codigo ?? '',
                    'entradas' => 0,
                    'salidas'  => 0,
                    'precio'   => 0,
                ];
            }

            $porMaterial[$idMat]['entradas'] += $det->cantidad_inicial;
            $porMaterial[$idMat]['precio']    = $det->precio;

            $salidas = SalidasDetalle::where('id_entrada_detalle', $det->id)
                ->sum('cantidad_salida');
            $porMaterial[$idMat]['salidas'] += $salidas;
        }

        // Solo materiales con stock > 0
        $porMaterial = array_filter($porMaterial, fn($m) => ($m['entradas'] - $m['salidas']) > 0);

        // Ordenar por nombre
        usort($porMaterial, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

        $granTotal = 0;

        $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
        $mpdf->SetTitle('Inventario Actual');
        $mpdf->showImageErrors = false;

        $tabla = "
    <table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:6px;'>
        <tr>
            <td style='width:30%; border:0.8px solid #000; padding:6px 8px;'>
                <table width='100%'>
                    <tr>
                        <td style='width:35%; text-align:left;'>
                            <img src='{$logoalcaldia}' style='height:40px'>
                        </td>
                        <td style='width:65%; text-align:left; color:#104e8c;
                                    font-size:12px; font-weight:bold; line-height:1.4;'>
                            SANTA ANA NORTE<br>EL SALVADOR
                        </td>
                    </tr>
                </table>
            </td>
            <td style='width:70%; border:0.8px solid #000;
                        padding:8px; text-align:center; vertical-align:middle;'>
                <h2 style='margin:0;'>Inventario de Proyecto</h2>
                <p style='margin:0; font-size:12px;'>Fecha: $fechaFormat</p>
            </td>
        </tr>
    </table>";

        $tabla .= "<p style='font-size:15px;'><span style='font-weight:bold;'>Proyecto:</span> {$infoProyecto->nombre}</p>";

        $tabla .= "
    <table width='100%' id='tablaFor'>
        <tbody>
            <tr>
                <td style='font-weight:bold; width:12%; font-size:13px;'>Código</td>
                <td style='font-weight:bold; width:38%; font-size:13px;'>Material</td>
                <td style='font-weight:bold; width:15%; font-size:13px;'>Medida</td>
                <td style='font-weight:bold; width:10%; font-size:13px;'>Stock</td>
                <td style='font-weight:bold; width:12%; font-size:13px;'>Precio Unit.</td>
                <td style='font-weight:bold; width:13%; font-size:13px;'>Total ($)</td>
            </tr>";

        foreach ($porMaterial as $mat) {
            $stock      = $mat['entradas'] - $mat['salidas'];
            $totalLinea = $stock * $mat['precio'];
            $granTotal += $totalLinea;

            $precioFmt = number_format($mat['precio'], 4);
            $totalFmt  = number_format($totalLinea, 4);

            $tabla .= "
            <tr>
                <td style='font-size:12px;'>{$mat['codigo']}</td>
                <td style='font-size:12px;'>{$mat['nombre']}</td>
                <td style='font-size:12px;'>{$mat['medida']}</td>
                <td style='font-size:12px;'>$stock</td>
                <td style='font-size:12px;'>$ $precioFmt</td>
                <td style='font-size:12px;'>$ $totalFmt</td>
            </tr>";
        }

        $granTotalFmt = number_format($granTotal, 4);

        $tabla .= "
            <tr>
                <td colspan='5' style='font-weight:bold; font-size:13px; text-align:right;
                                        border-top:1.5px solid #000; padding-top:4px;'>
                    TOTAL GENERAL:
                </td>
                <td style='font-weight:bold; font-size:13px; border-top:1.5px solid #000; padding-top:4px;'>
                    $ $granTotalFmt
                </td>
            </tr>
        </tbody>
    </table>";

        $stylesheet = file_get_contents('css/cssregistro.css');
        $mpdf->WriteHTML($stylesheet, 1);
        $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
        $mpdf->WriteHTML($tabla, 2);
        $mpdf->Output();
    }


    public function vistaProyectoCompletado()
    {
        // Proyectos cerrados = transferido 1
        $transferido = Tipoproyecto::where('transferido', 1)
            ->orderBy('nombre', 'ASC')
            ->get();

        return view('backend.admin.repuestos.reporte.vistaproyectocompletado', compact('transferido'));
    }

    public function reporteProyectoTerminado($idtrans)
    {
        $infoProyecto  = Tipoproyecto::find($idtrans);
        $fechaGenerado = date("d-m-Y");
        $logoalcaldia  = 'images/logo.png';

        // ── Buscar el registro de cierre (snapshot) ───────────────────────
        $transferencia = Transferencia::where('id_tipoproyecto', $idtrans)
            ->orderBy('id', 'desc')
            ->first();

        if (!$transferencia) {
            // Si no hay snapshot aún, mostrar PDF con aviso
            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->WriteHTML("<p style='font-family:Arial; font-size:14px; color:red;'>
            Este proyecto no tiene registro de cierre generado.</p>", 2);
            $mpdf->Output();
            return;
        }

        $fechaCierre = date("d-m-Y", strtotime($transferencia->fecha));

        // ── Leer snapshot de transferencia_detalle ────────────────────────
        $detallesSnapshot = TransferenciaDetalle::where('id_transferencia', $transferencia->id)
            ->get();

        // Agrupar por nombre_material (ya es snapshot, no necesita joins complejos)
        $porMaterial = [];

        foreach ($detallesSnapshot as $det) {
            $key = $det->nombre_material ?? 'SIN NOMBRE';

            if (!isset($porMaterial[$key])) {
                // Obtener medida y código desde entradas_detalle → material
                $entradaDet = \App\Models\EntradasDetalle::with('material.unidadMedida')
                    ->find($det->id_entrada_detalle);

                $porMaterial[$key] = [
                    'nombre'           => $det->nombre_material ?? '—',
                    'medida'           => $entradaDet?->material?->unidadMedida?->nombre ?? '—',
                    'codigo'           => $entradaDet?->codigo ?? '—',
                    'cantidad_cierre'  => 0,   // stock al momento del cierre
                    'precio'           => $det->precio,
                ];
            }

            $porMaterial[$key]['cantidad_cierre'] += $det->cantidad_sobrante;
        }

        // Filtrar si por alguna razón quedó en 0
        $porMaterial = array_filter($porMaterial, fn($m) => $m['cantidad_cierre'] > 0);

        usort($porMaterial, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

        $granTotal = 0;

        $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
        $mpdf->SetTitle('Reporte de Proyecto Completado');
        $mpdf->showImageErrors = false;

        $tabla = "
<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:6px;'>
    <tr>
        <td style='width:30%; border:0.8px solid #000; padding:6px 8px;'>
            <table width='100%'>
                <tr>
                    <td style='width:35%; text-align:left;'>
                        <img src='{$logoalcaldia}' style='height:40px'>
                    </td>
                    <td style='width:65%; text-align:left; color:#104e8c;
                                font-size:12px; font-weight:bold; line-height:1.4;'>
                        SANTA ANA NORTE<br>EL SALVADOR
                    </td>
                </tr>
            </table>
        </td>
        <td style='width:70%; border:0.8px solid #000;
                    padding:8px; text-align:center; vertical-align:middle;'>
            <h2 style='margin:0; font-size:15px;'>Reporte de Proyecto Completado</h2>
            <p style='margin:0; font-size:12px;'>Generado: $fechaGenerado</p>
        </td>
    </tr>
</table>";

        $tabla .= "
<table width='100%' style='margin-bottom:4px;'>
    <tbody>
        <tr>
            <td style='font-size:15px;'>
                <span style='font-weight:bold;'>Proyecto:</span> {$infoProyecto->nombre}
            </td>
            <td style='font-size:13px; text-align:right;'>
                <span style='font-weight:bold;'>Fecha de Cierre:</span> $fechaCierre
            </td>
        </tr>
    </tbody>
</table>";

        // ── Nota informativa ──────────────────────────────────────────────
        $tabla .= "
<table width='100%' style='margin-bottom:6px;'>
    <tbody>
        <tr>
            <td style='
                background-color:#e9e9e9;
                border:1px solid #aaaaaa;
                color:#444444;
                font-size:11px;
                font-weight:bold;
                padding:4px 8px;
            '>
                Este reporte muestra el inventario sobrante registrado
                al momento del cierre del proyecto. Los movimientos posteriores
                al cierre no afectan este reporte.
            </td>
        </tr>
    </tbody>
</table>";

        $tabla .= "
<table width='100%' id='tablaFor'>
    <tbody>
        <tr>
            <td style='font-weight:bold; width:13%; font-size:13px;'>Código</td>
            <td style='font-weight:bold; width:35%; font-size:13px;'>Material</td>
            <td style='font-weight:bold; width:12%; font-size:13px;'>Medida</td>
            <td style='font-weight:bold; width:13%; font-size:13px;'>Cant. al Cierre</td>
            <td style='font-weight:bold; width:13%; font-size:13px;'>Precio Unit.</td>
            <td style='font-weight:bold; width:14%; font-size:13px;'>Total ($)</td>
        </tr>";

        foreach ($porMaterial as $mat) {
            $totalLinea = $mat['cantidad_cierre'] * $mat['precio'];
            $granTotal += $totalLinea;

            $precioFmt = number_format($mat['precio'], 4);
            $totalFmt  = number_format($totalLinea, 4);

            $tabla .= "
        <tr>
            <td style='font-size:12px;'>{$mat['codigo']}</td>
            <td style='font-size:12px;'>{$mat['nombre']}</td>
            <td style='font-size:12px;'>{$mat['medida']}</td>
            <td style='font-size:12px; font-weight:bold;'>{$mat['cantidad_cierre']}</td>
            <td style='font-size:12px;'>$ $precioFmt</td>
            <td style='font-size:12px;'>$ $totalFmt</td>
        </tr>";
        }

        $granTotalFmt = number_format($granTotal, 4);

        $tabla .= "
        <tr>
            <td colspan='5' style='font-weight:bold; font-size:13px; text-align:right;
                                    border-top:1.5px solid #000; padding-top:4px;'>
                TOTAL GENERAL:
            </td>
            <td style='font-weight:bold; font-size:13px; border-top:1.5px solid #000; padding-top:4px;'>
                $ $granTotalFmt
            </td>
        </tr>
    </tbody>
</table>";

        $stylesheet = file_get_contents('css/cssregistro.css');
        $mpdf->WriteHTML($stylesheet, 1);
        $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
        $mpdf->WriteHTML($tabla, 2);
        $mpdf->Output();
    }


    public function vistaQueHaEntradoProyecto()
    {
        $proyectos = TipoProyecto::orderBy('nombre', 'ASC')->get();

        return view('backend.admin.repuestos.reporte.vistaquehaentradoproyecto', compact('proyectos'));
    }


    public function pdfQueHaEntradoProyectos($idproy, $desde, $hasta, $tipo)
    {
        $infoProyecto = Tipoproyecto::find($idproy);

        $sinFecha = ($desde === 'null' || $desde === '' || $hasta === 'null' || $hasta === '');

        $logoalcaldia = 'images/logo.png';

        if (!$sinFecha) {
            $start       = date('Y-m-d 00:00:00', strtotime($desde));
            $end         = date('Y-m-d 23:59:59', strtotime($hasta));
            $fechaLabel  = "Fecha: " . date("d-m-Y", strtotime($desde)) . "  -  " . date("d-m-Y", strtotime($hasta));
        } else {
            $fechaLabel = "Todas las fechas";
        }

        $encabezado = "
<table width='100%' style='border-collapse:collapse; font-family:Arial, sans-serif; margin-bottom:6px;'>
    <tr>
        <td style='width:30%; border:0.8px solid #000; padding:6px 8px;'>
            <table width='100%'>
                <tr>
                    <td style='width:35%; text-align:left;'>
                        <img src='{$logoalcaldia}' style='height:40px'>
                    </td>
                    <td style='width:65%; text-align:left; color:#104e8c;
                                font-size:12px; font-weight:bold; line-height:1.4;'>
                        SANTA ANA NORTE<br>EL SALVADOR
                    </td>
                </tr>
            </table>
        </td>
        <td style='width:70%; border:0.8px solid #000;
                    padding:8px; text-align:center; vertical-align:middle;'>
            <h2 style='margin:0;'>Reporte de Materiales Recibidos</h2>
            <p style='margin:0; font-size:12px;'>$fechaLabel</p>
        </td>
    </tr>
</table>";

        $totalCantidad = 0;

        // ─── TIPO 1: JUNTOS ───────────────────────────────────────────
        if ($tipo == 1) {

            $query = Entradas::where('id_tipoproyecto', $idproy);
            if (!$sinFecha) {
                $query->whereBetween('fecha', [$start, $end]);
            }
            $idsEntradas = $query->orderBy('fecha', 'ASC')->pluck('id');

            $detalles = EntradasDetalle::with('material.unidadMedida')
                ->whereIn('id_entradas', $idsEntradas)
                ->get();

            $dataArray = [];
            $granTotal = 0;

            foreach ($detalles as $det) {
                $idMat = $det->id_material;
                $totalCantidad += $det->cantidad_inicial;

                if (!isset($dataArray[$idMat])) {
                    $dataArray[$idMat] = [
                        'nombre'         => $det->material->nombre ?? '',
                        'medida'         => $det->material->unidadMedida->nombre ?? '',
                        'codigo'         => $det->codigo ?? '',
                        'cantidad'       => 0,
                        'totalMaterial'  => 0,
                        'precioUnitario' => 0,
                    ];
                }

                $dataArray[$idMat]['cantidad']       += $det->cantidad_inicial;
                $dataArray[$idMat]['totalMaterial']  += ($det->precio * $det->cantidad_inicial);
                $dataArray[$idMat]['precioUnitario']  = $det->precio;
            }

            usort($dataArray, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

            foreach ($dataArray as $item) {
                $granTotal += $item['totalMaterial'];
            }

            $granTotalFmt     = number_format($granTotal, 2);
            $totalCantidadFmt = number_format($totalCantidad, 2);

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Reporte de Materiales Recibidos');
            $mpdf->showImageErrors = false;

            $tabla  = $encabezado;
            $tabla .= "<p style='font-size:15px;'><span style='font-weight:bold;'>Proyecto:</span> {$infoProyecto->nombre}</p>";

            $tabla .= "
<table width='100%' id='tablaFor'>
    <tbody>
        <tr>
            <td style='font-weight:bold; width:13%; font-size:13px;'>Código</td>
            <td style='font-weight:bold; width:35%; font-size:13px;'>Material</td>
            <td style='font-weight:bold; width:12%; font-size:13px;'>Medida</td>
            <td style='font-weight:bold; width:13%; font-size:13px;'>Cantidad</td>
            <td style='font-weight:bold; width:15%; font-size:13px;'>Precio Unit.</td>
            <td style='font-weight:bold; width:15%; font-size:13px;'>Total ($)</td>
        </tr>";

            foreach ($dataArray as $info) {
                $precioFmt = number_format($info['precioUnitario'], 4);
                $totalFmt  = number_format($info['totalMaterial'], 4);

                $tabla .= "
        <tr>
            <td style='font-size:12px;'>{$info['codigo']}</td>
            <td style='text-align:left; font-size:12px;'>{$info['nombre']}</td>
            <td style='font-size:12px;'>{$info['medida']}</td>
            <td style='font-size:12px;'>{$info['cantidad']}</td>
            <td style='font-size:12px;'>$ $precioFmt</td>
            <td style='font-size:12px;'>$ $totalFmt</td>
        </tr>";
            }

            $tabla .= "
        <tr>
            <td colspan='3' style='font-weight:bold; font-size:13px; text-align:right;
                                    border-top:1.5px solid #000; padding-top:4px;'>
                TOTAL CANTIDAD:
            </td>
            <td style='font-weight:bold; font-size:13px; border-top:1.5px solid #000; padding-top:4px;'>
                $totalCantidadFmt
            </td>
            <td style='font-weight:bold; font-size:13px; text-align:right;
                        border-top:1.5px solid #000; padding-top:4px;'>
                TOTAL GENERAL:
            </td>
            <td style='font-weight:bold; font-size:13px; border-top:1.5px solid #000; padding-top:4px;'>
                $ $granTotalFmt
            </td>
        </tr>
    </tbody>
</table>";

            // ─── TIPO 2: SEPARADOS ────────────────────────────────────────
        } else {

            $query = Entradas::with([
                'detalle.material.unidadMedida',
            ])
                ->where('id_tipoproyecto', $idproy);

            if (!$sinFecha) {
                $query->whereBetween('fecha', [$start, $end]);
            }

            $arrayEntradas = $query->orderBy('fecha', 'ASC')->get();

            $granTotal = 0;

            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir(), 'format' => 'LETTER']);
            $mpdf->SetTitle('Reporte de Materiales Recibidos');
            $mpdf->showImageErrors = false;

            $tabla  = $encabezado;
            $tabla .= "<p style='font-size:15px;'><span style='font-weight:bold;'>Proyecto:</span> {$infoProyecto->nombre}</p>";

            foreach ($arrayEntradas as $entrada) {

                $fechaFmt        = date("d-m-Y", strtotime($entrada->fecha));
                $descripcion     = $entrada->descripcion ?? '';
                $factura         = $entrada->factura ?? '';
                $esTransferencia = (int) $entrada->es_transferencia === 1;

                // ── Fila de cierre/transferencia solo si aplica ──
                if ($esTransferencia) {

                    // Buscar el nombre del proyecto origen
                    $proyectoOrigen = null;
                    if ($entrada->id_tipoproyecto_transferencia) {
                        $proyectoOrigen = Tipoproyecto::find($entrada->id_tipoproyecto_transferencia);
                    }
                    $nombreOrigen = $proyectoOrigen ? $proyectoOrigen->nombre : 'Proyecto #' . $entrada->id_tipoproyecto_transferencia;

                    $tabla .= "
                <table width='100%' style='margin-bottom:3px;'>
                    <tbody>
                        <tr>
                            <td style='
                                background-color:#e9e9e9;
                                border:1px solid #aaaaaa;
                                color:#444444;
                                font-weight:bold;
                                font-size:12px;
                                padding:4px 8px;
                                text-align:center;
                            '>
                                 ENTRADA POR CIERRE DE PROYECTO: $nombreOrigen
                            </td>
                        </tr>
                    </tbody>
                </table>";
                }

                $tabla .= "
        <table width='100%' id='tablaFor'>
            <tbody>
                <tr>
                    <td style='font-weight:bold; width:15%; font-size:13px;'>Fecha</td>
                    <td style='font-weight:bold; width:20%; font-size:13px;'>Factura</td>
                    <td style='font-weight:bold; width:65%; font-size:13px;'>Descripción</td>
                </tr>
                <tr>
                    <td style='font-size:12px;'>$fechaFmt</td>
                    <td style='font-size:12px;'>$factura</td>
                    <td style='font-size:12px;'>$descripcion</td>
                </tr>
            </tbody>
        </table>";

                $tabla .= "
        <table width='100%' id='tablaFor'>
            <tbody>
                <tr>
                    <td style='font-weight:bold; width:13%; font-size:13px;'>Código</td>
                    <td style='font-weight:bold; width:12%; font-size:13px;'>Medida</td>
                    <td style='font-weight:bold; width:30%; font-size:13px;'>Material</td>
                    <td style='font-weight:bold; width:11%; font-size:13px;'>Cantidad</td>
                    <td style='font-weight:bold; width:15%; font-size:13px;'>Precio Unit.</td>
                    <td style='font-weight:bold; width:15%; font-size:13px;'>Total ($)</td>
                </tr>";

                $subtotal         = 0;
                $subtotalCantidad = 0;

                foreach ($entrada->detalle as $det) {
                    $totalCantidad    += $det->cantidad_inicial;
                    $subtotalCantidad += $det->cantidad_inicial;

                    $totalLinea  = $det->precio * $det->cantidad_inicial;
                    $granTotal  += $totalLinea;
                    $subtotal   += $totalLinea;

                    $codigo    = $det->codigo ?? '';
                    $nombreMat = $det->material->nombre ?? '';
                    $medida    = $det->material->unidadMedida->nombre ?? '';
                    $precioFmt = number_format($det->precio, 4);
                    $totalFmt  = number_format($totalLinea, 4);

                    $tabla .= "
                <tr>
                    <td style='font-size:12px;'>$codigo</td>
                    <td style='font-size:12px;'>$medida</td>
                    <td style='font-size:12px;'>$nombreMat</td>
                    <td style='font-size:12px;'>{$det->cantidad_inicial}</td>
                    <td style='font-size:12px;'>$ $precioFmt</td>
                    <td style='font-size:12px;'>$ $totalFmt</td>
                </tr>";
                }

                $subtotalFmt         = number_format($subtotal, 4);
                $subtotalCantidadFmt = number_format($subtotalCantidad, 2);

                $tabla .= "
                <tr>
                    <td colspan='3' style='font-weight:bold; font-size:12px; text-align:right;
                                           border-top:1px solid #000; padding-top:3px;'>
                        Subtotal Cantidad:
                    </td>
                    <td style='font-weight:bold; font-size:12px; border-top:1px solid #000; padding-top:3px;'>
                        $subtotalCantidadFmt
                    </td>
                    <td style='font-weight:bold; font-size:12px; text-align:right;
                                border-top:1px solid #000; padding-top:3px;'>
                        Subtotal:
                    </td>
                    <td style='font-weight:bold; font-size:12px; border-top:1px solid #000; padding-top:3px;'>
                        $ $subtotalFmt
                    </td>
                </tr>
            </tbody>
        </table><br>";
            }

            $granTotalFmt     = number_format($granTotal, 4);
            $totalCantidadFmt = number_format($totalCantidad, 2);

            $tabla .= "
<table width='100%' style='margin-top:10px;'>
    <tbody>
        <tr>
            <td style='font-weight:bold; font-size:14px; text-align:right;
                        border-top:2px solid #000; padding-top:6px;'>
                TOTAL CANTIDAD:&nbsp;&nbsp;
            </td>
            <td style='font-weight:bold; font-size:14px; width:12%;
                        border-top:2px solid #000; padding-top:6px;'>
                $totalCantidadFmt
            </td>
            <td style='font-weight:bold; font-size:14px; text-align:right;
                        border-top:2px solid #000; padding-top:6px;'>
                TOTAL GENERAL:&nbsp;&nbsp;
            </td>
            <td style='font-weight:bold; font-size:14px; width:18%;
                        border-top:2px solid #000; padding-top:6px;'>
                $ $granTotalFmt
            </td>
        </tr>
    </tbody>
</table>";
        }

        $stylesheet = file_get_contents('css/cssregistro.css');
        $mpdf->WriteHTML($stylesheet, 1);
        $mpdf->setFooter("Página: " . '{PAGENO}' . "/" . '{nb}');
        $mpdf->WriteHTML($tabla, 2);
        $mpdf->Output();
    }







}
