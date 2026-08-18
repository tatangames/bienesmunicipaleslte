@extends('adminlte::page')

@section('title', 'Reportes de Entradas y Salidas')

@section('plugins.Sweetalert2', true)
@include('backend.urlglobal')

{{-- ══════════════════════════════════════════
     NAV DERECHO: usuario + cerrar sesión
══════════════════════════════════════════ --}}
@section('content_top_nav_right')
    <link href="{{ asset('css/toastr.min.css') }}"                 rel="stylesheet">
    <link href="{{ asset('css/select2.min.css') }}"                rel="stylesheet">
    <link href="{{ asset('css/select2-bootstrap-5-theme.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/estiloToggle.css') }}"               rel="stylesheet">

    <li class="nav-item dropdown">
        <a href="#" class="nav-link" data-toggle="dropdown">
            <i class="fas fa-cogs"></i>
            <span class="d-none d-md-inline">{{ Auth::guard('admin')->user()->nombre }}</span>
        </a>
        <div class="dropdown-menu dropdown-menu-right">
            <a href="{{ route('admin.perfil') }}" class="dropdown-item">
                <i class="fas fa-user mr-2"></i> Editar Perfil
            </a>
        </div>
    </li>

    <li class="nav-item">
        <form action="{{ route('admin.logout') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="nav-link btn btn-link border-0 bg-transparent">
                <i class="fas fa-sign-out-alt"></i>
                <span class="d-none d-md-inline">Cerrar Sesión</span>
            </button>
        </form>
    </li>
@endsection

{{-- ══════════════════════════════════════════
     ESTILOS
══════════════════════════════════════════ --}}
@section('content')
    <style>
        /* ── Reset ── */
        *:focus { outline: none; }

        /* ── Tarjeta ── */
        .reporte-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 18px rgba(0, 0, 0, .10);
            margin-bottom: 24px;
            overflow: hidden;
        }

        /* ── Cabecera de tarjeta ── */
        .reporte-header {
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .reporte-header.entradas { background: linear-gradient(135deg, #1a6b2a, #28a745); }
        .reporte-header.salidas  { background: linear-gradient(135deg, #6b1a1a, #dc3545); }
        .reporte-header i  { font-size: 22px; color: #fff; }
        .reporte-header h5 {
            margin: 0;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        /* ── Cuerpo de tarjeta ── */
        .reporte-body { padding: 22px 24px; background: #fff; }

        /* ── Etiqueta de campo ── */
        .field-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #6b7a99;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 6px;
        }

        /* ── Separador ── */
        .divider {
            border: none;
            border-top: 2px dashed #e8eef8;
            margin: 12px 0 18px;
        }

        /* ── Botón PDF ── */
        .btn-pdf {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            margin-top: 14px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all .2s;
        }
        .btn-pdf.verde {
            background: linear-gradient(135deg, #1a6b2a, #28a745);
            color: #fff;
            box-shadow: 0 4px 14px rgba(40, 167, 69, .35);
        }
        .btn-pdf.rojo {
            background: linear-gradient(135deg, #6b1a1a, #dc3545);
            color: #fff;
            box-shadow: 0 4px 14px rgba(220, 53, 69, .35);
        }
        .btn-pdf:hover { transform: translateY(-1px); filter: brightness(1.08); color: #fff; }

        /* ── Fila de fechas ── */
        .fecha-row { display: flex; gap: 14px; margin-bottom: 14px; }
        .fecha-box { flex: 1; }
        .fecha-box label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #6b7a99;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
    </style>

    {{-- ══════════════════════════════════════════
         CONTENIDO
    ══════════════════════════════════════════ --}}
    <section class="content">
        <div class="container-fluid">
            <div class="row">

                {{-- ── Entradas ── --}}
                <div class="col-md-6">
                    <div class="reporte-card">
                        <div class="reporte-header entradas">
                            <i class="fas fa-arrow-circle-down"></i>
                            <h5>Reporte de Entradas de Materiales</h5>
                        </div>
                        <div class="reporte-body">
                            <p style="font-size:13px; color:#666; margin-bottom:14px;">
                                Materiales ingresados en el rango de fechas seleccionado.
                            </p>
                            <hr class="divider">

                            <div class="fecha-row">
                                <div class="fecha-box">
                                    <label>Desde</label>
                                    <input type="date" id="entrada-desde" class="form-control">
                                </div>
                                <div class="fecha-box">
                                    <label>Hasta</label>
                                    <input type="date" id="entrada-hasta" class="form-control">
                                </div>
                            </div>

                            <label class="field-label mt-2">Tipo de Reporte</label>
                            <select id="tipo-entrada" class="form-control" style="width:100%">
                                <option value="1">Juntos — materiales iguales del mismo precio se suman</option>
                                <option value="2">Separado — cada entrada por separado</option>
                            </select>

                            <button type="button" onclick="generarPdfEntrada()" class="btn-pdf verde">
                                <i class="fas fa-file-pdf"></i> Generar PDF
                            </button>
                        </div>
                    </div>
                </div>

                {{-- ── Salidas ── --}}
                <div class="col-md-6">
                    <div class="reporte-card">
                        <div class="reporte-header salidas">
                            <i class="fas fa-arrow-circle-up"></i>
                            <h5>Reporte de Salidas de Materiales</h5>
                        </div>
                        <div class="reporte-body">
                            <p style="font-size:13px; color:#666; margin-bottom:14px;">
                                Materiales entregados en el rango de fechas seleccionado.
                            </p>
                            <hr class="divider">

                            <div class="fecha-row">
                                <div class="fecha-box">
                                    <label>Desde</label>
                                    <input type="date" id="salida-desde" class="form-control">
                                </div>
                                <div class="fecha-box">
                                    <label>Hasta</label>
                                    <input type="date" id="salida-hasta" class="form-control">
                                </div>
                            </div>

                            <label class="field-label mt-2">Tipo de Reporte</label>
                            <select id="tipo-salida" class="form-control" style="width:100%">
                                <option value="1">Juntos — materiales iguales del mismo precio se suman</option>
                                <option value="2">Separado — cada salida por separado</option>
                            </select>

                            <button type="button" onclick="generarPdfSalida()" class="btn-pdf rojo">
                                <i class="fas fa-file-pdf"></i> Generar PDF
                            </button>
                        </div>
                    </div>
                </div>

                {{-- ── Inventario actual ── --}}
                <div class="col-md-12">
                    <div class="reporte-card">
                        <div class="reporte-header" style="background: linear-gradient(135deg, #1a4a6b, #1a73e8);">
                            <i class="fas fa-boxes"></i>
                            <h5>Inventario Actual de Materiales</h5>
                        </div>
                        <div class="reporte-body">
                            <p style="font-size:13px; color:#666; margin-bottom:14px;">
                                Existencias actuales (entradas menos salidas).
                                Solo muestra materiales con cantidad mayor a cero.
                            </p>
                            <hr class="divider">

                            <div class="row">
                                <div class="col-md-6">
                                    <label class="field-label">Material</label>
                                    <select id="inv-material" class="form-control" style="width:100%">
                                        <option value="0">— Todos los materiales —</option>
                                        @foreach($materiales as $mat)
                                            <option value="{{ $mat->id }}">{{ $mat->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <button type="button" onclick="generarPdfInventario()" class="btn-pdf"
                                            style="background: linear-gradient(135deg, #1a4a6b, #1a73e8);
                                               color: #fff;
                                               box-shadow: 0 4px 14px rgba(26,115,232,.35);
                                               margin-top: 0;">
                                        <i class="fas fa-file-pdf"></i> Generar PDF
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ── Control de Entradas/Salidas por Período ── --}}
                <div class="col-md-6 d-flex">
                    <div class="reporte-card h-100 w-100">
                        <div class="reporte-header" style="background: linear-gradient(135deg, #6b4a1a, #e88e1a);">
                            <i class="fas fa-exchange-alt"></i>
                            <h5>Control de Entradas/Salidas por Período</h5>
                        </div>
                        <div class="reporte-body">
                            <p style="font-size:13px; color:red; margin-bottom:14px;">
                                Si el material tuvo todas sus salidas en el mes, sí aparecerá;
                                pero en el siguiente mes ya no aparecerá si ya no tiene unidades.
                            </p>
                            <hr class="divider">

                            <div class="fecha-row">
                                <div class="fecha-box">
                                    <label>Fecha desde <span class="text-danger">*</span></label>
                                    <input type="date" id="periodo-fecha-desde" class="form-control form-control-sm">
                                </div>
                                <div class="fecha-box">
                                    <label>Fecha hasta <span class="text-danger">*</span></label>
                                    <input type="date" id="periodo-fecha-hasta" class="form-control form-control-sm">
                                </div>
                            </div>

                            <button type="button" onclick="generarPdfPeriodo()" class="btn-pdf"
                                    style="background: linear-gradient(135deg, #6b4a1a, #e88e1a);
                                       color: #fff;
                                       box-shadow: 0 4px 14px rgba(232,142,26,.35);
                                       margin-top: 0;">
                                <i class="fas fa-file-pdf"></i> Generar PDF
                            </button>
                        </div>
                    </div>
                </div>

                {{-- ── Nombre para Firma ── --}}
                <div class="col-md-6 d-flex">
                    <div class="reporte-card h-100 w-100">
                        <div class="reporte-header" style="background: linear-gradient(135deg, #6b4a1a, #e88e1a);">
                            <i class="fas fa-exchange-alt"></i>
                            <h5>Nombre para Firma en Reporte</h5>
                        </div>
                        <div class="reporte-body">

                            <div class="fecha-row">
                                <div class="fecha-box">
                                    <label for="nombre-firma">Nombre</label>
                                    <input type="text"
                                           id="nombre-firma"
                                           maxlength="100"
                                           class="form-control form-control-sm"
                                           value="{{ $informacionGeneral->nombre_reporte }}">
                                </div>
                            </div>

                            <div class="fecha-row">
                                <div class="fecha-box">
                                    <label for="nombre-firma">Distancia para Firma</label>
                                    <input type="number"
                                           id="px_firmas"
                                           class="form-control form-control-sm"
                                           value="{{ $informacionGeneral->px_firmas }}">
                                </div>
                            </div>

                            <div class="custom-control custom-switch mt-3">
                                <input type="checkbox"
                                       id="config-salto-pagina"
                                       class="custom-control-input"
                                    {{ ($informacionGeneral->salto_pagina ?? false) ? 'checked' : '' }}>
                                <label class="custom-control-label"
                                       for="config-salto-pagina"
                                       style="font-size:13px; padding-top:2px;">
                                    Salto de página antes de firma
                                </label>
                            </div>

                            <div class="mt-3">
                                <button type="button" class="btn btn-primary" onclick="guardarNombreReporte()">
                                    <i class="fas fa-save mr-1"></i> Guardar
                                </button>
                            </div>

                        </div>
                    </div>
                </div>

            </div>{{-- /.row --}}
        </div>{{-- /.container-fluid --}}
    </section>
@stop

{{-- ══════════════════════════════════════════
     SCRIPTS
══════════════════════════════════════════ --}}
@section('js')
    <script src="{{ asset('js/toastr.min.js') }}"></script>
    <script src="{{ asset('js/axios.min.js') }}"></script>
    <script src="{{ asset('js/sweetalert2.all.min.js') }}"></script>
    <script src="{{ asset('js/alertaPersonalizada.js') }}"></script>
    <script src="{{ asset('js/jquery.simpleaccordion.js') }}"></script>
    <script src="{{ asset('js/select2.min.js') }}"></script>

    <script>
        /* ── Select2 ── */
        $('#inv-material').select2({
            theme: 'bootstrap-5',
            language: {
                noResults: () => 'Búsqueda no encontrada',
            },
        });

        /* ── Generadores de PDF ── */
        function generarPdfEntrada() {
            const desde = document.getElementById('entrada-desde').value || 'null';
            const hasta = document.getElementById('entrada-hasta').value || 'null';
            const tipo  = document.getElementById('tipo-entrada').value;
            window.open(`{{ url('admin/reporte/quehaentrado/pdf') }}/${desde}/${hasta}/${tipo}`, '_blank');
        }

        function generarPdfSalida() {
            const desde = document.getElementById('salida-desde').value || 'null';
            const hasta = document.getElementById('salida-hasta').value || 'null';
            const tipo  = document.getElementById('tipo-salida').value;
            window.open(`{{ url('admin/reporte/quehasalido/pdf') }}/${desde}/${hasta}/${tipo}`, '_blank');
        }

        function generarPdfInventario() {
            const idMaterial = document.getElementById('inv-material').value;
            window.open(`{{ url('admin/reporte/inventario/pdf') }}/${idMaterial}`, '_blank');
        }

        function generarPdfPeriodo() {
            const desde = document.getElementById('periodo-fecha-desde').value;
            const hasta = document.getElementById('periodo-fecha-hasta').value;

            if (!desde || !hasta) {
                toastr.error('Debes seleccionar fecha desde y fecha hasta');
                return;
            }
            if (desde > hasta) {
                toastr.error('La fecha "desde" no puede ser mayor que "hasta"');
                return;
            }

            window.open(`{{ url('admin/bodega/reportespdf/inicial/final') }}/${desde}/${hasta}`, '_blank');
        }

        /* ── Guardar nombre de firma ── */
        function guardarNombreReporte() {
            const nombreFirma = $('#nombre-firma').val().trim();
            const saltoPagina = $('#config-salto-pagina').is(':checked') ? 1 : 0;
            const px_firmas = $('#px_firmas').val().trim();

            if (!nombreFirma) {
                toastr.error('Debe ingresar un nombre');
                return;
            }

            axios.post('{{ route('admin.informacion.actualizar.px') }}', {
                _token: '{{ csrf_token() }}',
                nombre_reporte: nombreFirma,
                salto_pagina: saltoPagina,
                px_firmas: px_firmas,
            })
                .then(({ data }) => {
                    if (data.success === 1) {
                        toastr.success('Configuración actualizada correctamente');
                    } else {
                        toastr.error('No se pudo actualizar la configuración');
                    }
                })
                .catch(() => toastr.error('Ocurrió un error al guardar'));
        }
    </script>
@endsection
