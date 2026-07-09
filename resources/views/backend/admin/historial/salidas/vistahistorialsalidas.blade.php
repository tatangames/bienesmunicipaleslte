@extends('adminlte::page')

@section('title', 'Historial / Salidas')

@section('content_header')
    <h1>Historial / Salidas</h1>
@stop

@section('plugins.Datatables', true)
@section('plugins.DatatablesPlugins', true)
@section('plugins.Sweetalert2', true)

@include('backend.urlglobal')

@section('content_top_nav_right')
    <link href="{{ asset('css/toastr.min.css') }}" type="text/css" rel="stylesheet"/>
    <link href="{{ asset('css/select2.min.css') }}" type="text/css" rel="stylesheet">
    <link href="{{ asset('css/select2-bootstrap-5-theme.min.css') }}" type="text/css" rel="stylesheet">

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

@section('content')

    <style>
        .drop-filtro-item {
            padding: 7px 12px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }
        .drop-filtro-item:hover {
            background: #eef3ff;
            font-weight: bold;
        }
    </style>

    <div id="divcontenedor">

        {{-- ══ FILTROS ══ --}}
        <section class="content" style="margin-bottom:0">
            <div class="container-fluid">
                <div class="card card-blue">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-filter mr-1"></i> Filtros</h3>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <label class="font-weight-bold">Fecha desde</label>
                                <input type="date" class="form-control" id="filtro-fecha-desde">
                            </div>
                            <div class="col-md-3">
                                <label class="font-weight-bold">Fecha hasta</label>
                                <input type="date" class="form-control" id="filtro-fecha-hasta">
                            </div>
                            <div class="col-md-3">
                                <label class="font-weight-bold">N. Talonario</label>
                                <input type="text" class="form-control" id="filtro-talonario" placeholder="Talonario...">
                            </div>
                            <div class="col-md-3">
                                <label class="font-weight-bold">N. Contrato</label>
                                <input type="text" class="form-control" id="filtro-contrato" placeholder="Contrato...">
                            </div>
                        </div>
                        <div class="row align-items-end mt-3">
                            <div class="col-md-3">
                                <label class="font-weight-bold">N. Orden</label>
                                <input type="text" class="form-control" id="filtro-orden" placeholder="Orden...">
                            </div>
                            <div class="col-md-4">
                                <label class="font-weight-bold">Buscar por material</label>
                                <div style="position:relative;">
                                    <input type="text" class="form-control" id="filtro-material"
                                           placeholder="Nombre del material..." autocomplete="off">
                                    <div id="drop-filtro-material"
                                         style="display:none; position:absolute; z-index:999; width:100%;
                                                background:#fff; border:1px solid #ccc; border-radius:4px;
                                                box-shadow:0 4px 10px rgba(0,0,0,.15); max-height:220px; overflow-y:auto;">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <div style="width:100%">
                                    <button class="btn btn-primary btn-block mb-1" onclick="recargar()">
                                        <i class="fas fa-search mr-1"></i> Filtrar
                                    </button>
                                    <button class="btn btn-secondary btn-block" onclick="limpiarFiltros()">
                                        <i class="fas fa-times mr-1"></i> Limpiar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ══ TABLA ══ --}}
        <section class="content">
            <div class="container-fluid">
                <div class="card card-blue">
                    <div class="card-header">
                        <h3 class="card-title">Listado de Salidas</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-12">
                                <div id="tablaDatatable"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    {{-- ══ Modal Editar Salida ══ --}}
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-edit mr-2"></i>Editar Salida
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="formulario-editar">
                        <input type="hidden" id="id-editar">

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Fecha <span class="text-danger">*</span></label>
                                    <input type="date" id="fecha-editar" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>N. Talonario <small class="text-muted">(Opcional)</small></label>
                                    <input type="text" id="talonario-editar" class="form-control" maxlength="100">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Número Contrato <small class="text-muted">(Opcional)</small></label>
                                    <input type="text" id="numerocontrato-editar" class="form-control" maxlength="100">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Número Orden <small class="text-muted">(Opcional)</small></label>
                                    <input type="text" id="numeroorden-editar" class="form-control" maxlength="100">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Firma 1 <small class="text-muted">(Opcional)</small></label>
                                    <input type="text" id="firma1-editar" class="form-control" maxlength="100">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Firma 2 <small class="text-muted">(Opcional)</small></label>
                                    <input type="text" id="firma2-editar" class="form-control" maxlength="100">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Firma 3 <small class="text-muted">(Opcional)</small></label>
                                    <input type="text" id="firma3-editar" class="form-control" maxlength="100">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Autoriza a <small class="text-muted">(Opcional)</small></label>
                                    <input type="text" id="autorizadoa-editar" class="form-control" maxlength="200">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>A petición de <small class="text-muted">(Opcional)</small></label>
                                    <input type="text" id="peticiona-editar" class="form-control" maxlength="200">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Para uso en <small class="text-muted">(Opcional)</small></label>
                                    <input type="text" id="usoa-editar" class="form-control" maxlength="200">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Encabezado <small class="text-muted">(Opcional)</small></label>
                                    <textarea id="encabezado-editar" class="form-control" rows="3" maxlength="5000"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Pie Página <small class="text-muted">(Opcional)</small></label>
                                    <textarea id="piepagina-editar" class="form-control" rows="3" maxlength="5000"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>Descripción <small class="text-muted">(Opcional)</small></label>
                                    <textarea id="descripcion-editar" class="form-control"
                                              rows="3" maxlength="800"></textarea>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning" onclick="editar()">
                        <i class="fas fa-save mr-1"></i> Guardar cambios
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Modal Detalle Salida ══ --}}
    <div class="modal fade" id="modalDetalle" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-list mr-2"></i>
                        Detalle — <span id="detalle-titulo"></span>
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="detalle-loading" class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                    </div>
                    <div id="detalle-contenido" style="display:none;">
                        <table class="table table-bordered table-striped table-sm">
                            <thead class="thead-dark">
                            <tr>
                                <th>#</th>
                                <th>Material</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-right">Precio unitario</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                            </thead>
                            <tbody id="detalle-tbody"></tbody>
                        </table>
                    </div>
                    <div id="detalle-vacio" class="text-center text-muted py-4" style="display:none;">
                        <i class="fas fa-inbox fa-2x mb-2"></i>
                        <p>Esta salida no tiene materiales registrados.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Modal Editar Cantidad Detalle ══ --}}
    <div class="modal fade" id="modalEditarCantidad" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title text-white">
                        <i class="fas fa-edit mr-2"></i>Editar Cantidad
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="ec-id">
                    <div class="form-group">
                        <label class="font-weight-bold" style="font-size:17px;">Material</label>
                        <p id="ec-label-material"  style="font-size:17px;"></p>
                    </div>
                    <div class="form-group">
                        <label>Nueva cantidad <span class="text-danger">*</span></label>
                        <input type="number" id="ec-cantidad" class="form-control" min="1">
                    </div>
                    <div id="ec-error" class="text-danger" style="display:none; font-size:12px;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning btn-sm" onclick="guardarCantidad()">
                        <i class="fas fa-save mr-1"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </div>

@stop

@section('js')
    <script src="{{ asset('js/toastr.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/axios.min.js') }}" type="text/javascript"></script>
    <script src="{{ asset('js/alertaPersonalizada.js') }}"></script>
    <script src="{{ asset('js/select2.min.js') }}" type="text/javascript"></script>

    <script>
        var _salidaIdActual       = null;
        var _salidaTituloActual   = '';
        var _tablaCargada         = false;
        var _seguroFiltroMaterial = true;

        $(function () {
            const ruta = "{{ url('/admin/historial/salidas/tabla') }}";

            function initDataTable() {
                if ($.fn.DataTable.isDataTable('#tabla')) {
                    $('#tabla').DataTable().destroy();
                }
                $('#tabla').DataTable({
                    paging: true,
                    lengthChange: true,
                    searching: true,
                    ordering: true,
                    info: true,
                    autoWidth: false,
                    responsive: true,
                    pagingType: "full_numbers",
                    lengthMenu: [[50, 100, -1], [50, 100, "Todo"]],
                    language: {
                        sProcessing:   "Procesando...",
                        sLengthMenu:   "Mostrar _MENU_ registros",
                        sZeroRecords:  "No se encontraron resultados",
                        sEmptyTable:   "Ningún dato disponible en esta tabla",
                        sInfo:         "Mostrando _START_ a _END_ de _TOTAL_ registros",
                        sInfoEmpty:    "Mostrando 0 a 0 de 0 registros",
                        sInfoFiltered: "(filtrado de _MAX_ registros)",
                        sSearch:       "Buscar:",
                        oPaginate: {
                            sFirst: "Primero", sLast: "Último",
                            sNext: "Siguiente", sPrevious: "Anterior"
                        }
                    },
                    dom:
                        "<'row align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 text-md-right'f>>" +
                        "tr" +
                        "<'row align-items-center'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
                });
                $('#tabla_length select').addClass('form-control form-control-sm');
                $('#tabla_filter input').addClass('form-control form-control-sm').css('display', 'inline-block');
            }

            function cargarTabla() {
                const fechaDesde = $('#filtro-fecha-desde').val();
                const fechaHasta = $('#filtro-fecha-hasta').val();
                const material   = $('#filtro-material').val().trim();
                const talonario  = $('#filtro-talonario').val().trim();
                const contrato   = $('#filtro-contrato').val().trim();
                const orden      = $('#filtro-orden').val().trim();

                const params = new URLSearchParams();
                if (fechaDesde) params.append('fecha_desde', fechaDesde);
                if (fechaHasta) params.append('fecha_hasta', fechaHasta);
                if (material)   params.append('material',    material);
                if (talonario)  params.append('talonario',   talonario);
                if (contrato)   params.append('contrato',    contrato);
                if (orden)      params.append('orden',       orden);

                const url = params.toString() ? ruta + '?' + params.toString() : ruta;
                $('#tablaDatatable').load(url, function () { initDataTable(); });
            }

            window.recargar = function () {
                _tablaCargada = true;
                cargarTabla();
            };

            window.limpiarFiltros = function () {
                $('#filtro-fecha-desde').val('');
                $('#filtro-fecha-hasta').val('');
                $('#filtro-material').val('');
                $('#filtro-talonario').val('');
                $('#filtro-contrato').val('');
                $('#filtro-orden').val('');
                $('#drop-filtro-material').hide().html('');
                if (_tablaCargada) cargarTabla();
            };

            // ── Autocomplete filtro material ──────────────────────
            $('#filtro-material').on('keyup', function () {
                var texto = $(this).val().trim();
                if (texto.length < 2) {
                    $('#drop-filtro-material').hide().html('');
                    return;
                }
                if (!_seguroFiltroMaterial) return;
                _seguroFiltroMaterial = false;

                axios.post(urlAdmin + '/admin/historial/buscarmaterial/nombre', { query: texto })
                    .then((response) => {
                        _seguroFiltroMaterial = true;
                        var nombres = response.data;

                        if (!nombres.length) {
                            $('#drop-filtro-material').hide().html('');
                            return;
                        }

                        var html = '';
                        nombres.forEach(function (nombre) {
                            html += '<div class="drop-filtro-item">' + nombre + '</div>';
                        });
                        $('#drop-filtro-material').html(html).fadeIn();
                    })
                    .catch(() => { _seguroFiltroMaterial = true; });
            });

            $(document).on('click', '.drop-filtro-item', function () {
                $('#filtro-material').val($(this).text().trim());
                $('#drop-filtro-material').hide().html('');
            });

            $(document).on('click', function (e) {
                if (!$(e.target).closest('#filtro-material, #drop-filtro-material').length) {
                    $('#drop-filtro-material').hide();
                }
            });

            // ── Delegación botones detalle ────────────────────────
            $(document).on('click', '.btn-eliminar-detalle-salida', function () {
                eliminarDetalleItem($(this).data('id'), $(this).data('material'), $(this).data('salida-id'));
            });
        });

        // ── Editar cabecera ───────────────────────────────────────
        function modalEditar(id) {
            openLoading();
            document.getElementById('formulario-editar').reset();

            axios.post(urlAdmin + '/admin/historial/salidas/informacion', { id: id })
                .then((response) => {
                    closeLoading();
                    if (response.data.success === 1) {
                        const s = response.data.salida;
                        $('#id-editar').val(s.id);
                        $('#fecha-editar').val(s.fecha ? s.fecha.substring(0, 10) : '');
                        $('#talonario-editar').val(s.ficha_talonario   ?? '');
                        $('#numerocontrato-editar').val(s.numero_contrato ?? '');
                        $('#numeroorden-editar').val(s.numero_orden    ?? '');
                        $('#firma1-editar').val(s.nombre_firma_1       ?? '');
                        $('#firma2-editar').val(s.nombre_firma_2       ?? '');
                        $('#firma3-editar').val(s.nombre_firma_3       ?? '');
                        $('#autorizadoa-editar').val(s.autoriza_a      ?? '');
                        $('#peticiona-editar').val(s.peticion_a        ?? '');
                        $('#usoa-editar').val(s.para_uso               ?? '');
                        $('#encabezado-editar').val(s.encabezado       ?? '');
                        $('#piepagina-editar').val(s.pie_pagina        ?? '');
                        $('#descripcion-editar').val(s.descripcion     ?? '');
                        $('#modalEditar').modal('show');
                    } else {
                        toastr.error('No se pudo cargar la información');
                    }
                })
                .catch(() => { closeLoading(); toastr.error('Error al obtener información'); });
        }

        function editar() {
            const id    = $('#id-editar').val();
            const fecha = $('#fecha-editar').val().trim();

            if (!fecha) { toastr.error('La fecha es requerida'); return; }

            openLoading();
            const formData = new FormData();
            formData.append('id',              id);
            formData.append('fecha',           fecha);
            formData.append('descripcion',     $('#descripcion-editar').val().trim());
            formData.append('ficha_talonario', $('#talonario-editar').val().trim());
            formData.append('numero_contrato', $('#numerocontrato-editar').val().trim());
            formData.append('numero_orden',    $('#numeroorden-editar').val().trim());
            formData.append('nombre_firma_1',  $('#firma1-editar').val().trim());
            formData.append('nombre_firma_2',  $('#firma2-editar').val().trim());
            formData.append('nombre_firma_3',  $('#firma3-editar').val().trim());
            formData.append('autoriza_a',      $('#autorizadoa-editar').val().trim());
            formData.append('peticion_a',      $('#peticiona-editar').val().trim());
            formData.append('para_uso',        $('#usoa-editar').val().trim());
            formData.append('encabezado',      $('#encabezado-editar').val().trim());
            formData.append('pie_pagina',      $('#piepagina-editar').val().trim());

            axios.post(urlAdmin + '/admin/historial/salidas/editar', formData)
                .then((response) => {
                    closeLoading();
                    if (response.data.success === 1) {
                        toastr.success('Salida actualizada correctamente');
                        $('#modalEditar').modal('hide');
                        recargar();
                    } else {
                        toastr.error('Error al actualizar');
                    }
                })
                .catch(() => { closeLoading(); toastr.error('Error al actualizar'); });
        }

        // ── Eliminar salida completa ──────────────────────────────
        function eliminar(id) {
            Swal.fire({
                title: '¿Eliminar salida?',
                text: 'Se eliminarán también todos los materiales asociados. Esta acción no se puede deshacer.',
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.value) {
                    openLoading();
                    axios.post(urlAdmin + '/admin/historial/salidas/eliminar', { id: id })
                        .then((response) => {
                            closeLoading();
                            if (response.data.success === 1) {
                                toastr.success('Salida eliminada correctamente');
                                recargar();
                            } else {
                                toastr.error('Error al eliminar');
                            }
                        })
                        .catch(() => { closeLoading(); toastr.error('Error al eliminar'); });
                }
            });
        }

        // ── Ver detalle ───────────────────────────────────────────
        function verDetalle(id, titulo) {
            _salidaIdActual     = id;
            _salidaTituloActual = titulo;

            $('#detalle-titulo').text(titulo);
            $('#detalle-tbody').html('');
            $('#detalle-contenido').hide();
            $('#detalle-vacio').hide();
            $('#detalle-loading').show();
            $('#modalDetalle').modal('show');

            axios.post(urlAdmin + '/admin/historial/salidas/detalle', { id: id })
                .then((response) => {
                    $('#detalle-loading').hide();
                    if (response.data.success === 1 && response.data.detalle.length > 0) {
                        let html = '';
                        response.data.detalle.forEach((fila, index) => {
                            html += `
                                <tr>
                                    <td>${index + 1}</td>
                                    <td>${fila.material}</td>
                                    <td class="text-center">
                                        <span id="cantidad-span-${fila.id}">${fila.cantidad_salida}</span>
                                    </td>
                                    <td class="text-right">$${fila.precio}</td>
                                    <td class="text-center">
                                        <button type="button"
                                                class="btn btn-warning btn-xs mr-1"
                                                onclick="modalEditarCantidad(${fila.id}, ${fila.cantidad_salida}, '${fila.material}')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button"
                                                class="btn btn-danger btn-xs btn-eliminar-detalle-salida"
                                                data-id="${fila.id}"
                                                data-material="${fila.material}"
                                                data-salida-id="${id}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>`;
                        });
                        $('#detalle-tbody').html(html);
                        $('#detalle-contenido').show();
                    } else {
                        $('#detalle-vacio').show();
                    }
                })
                .catch(() => {
                    $('#detalle-loading').hide();
                    $('#detalle-vacio').show();
                    toastr.error('Error al cargar el detalle');
                });
        }

        function recargarDetalle() {
            if (_salidaIdActual) {
                verDetalle(_salidaIdActual, _salidaTituloActual);
            }
        }

        // ── Editar cantidad detalle ───────────────────────────────
        function modalEditarCantidad(id, cantidadActual, material) {
            $('#ec-id').val(id);
            $('#ec-cantidad').val(cantidadActual);
            $('#ec-label-material').text(material);
            $('#ec-error').hide().text('');
            $('#modalEditarCantidad').modal('show');
        }

        function guardarCantidad() {
            const id       = $('#ec-id').val();
            const cantidad = parseInt($('#ec-cantidad').val());

            if (!cantidad || cantidad < 1) {
                $('#ec-error').text('Ingrese una cantidad válida mayor a 0').show();
                return;
            }

            $('#ec-error').hide();
            openLoading();

            const formData = new FormData();
            formData.append('id',       id);
            formData.append('cantidad', cantidad);

            axios.post(urlAdmin + '/admin/historial/salidas/editarcantidad', formData)
                .then((response) => {
                    closeLoading();
                    if (response.data.success === 1) {
                        toastr.success('Cantidad actualizada');
                        $('#modalEditarCantidad').modal('hide');
                        recargarDetalle();
                    } else if (response.data.success === 2) {
                        $('#ec-error').text(response.data.mensaje).show();
                    } else {
                        toastr.error('Error al actualizar');
                    }
                })
                .catch(() => { closeLoading(); toastr.error('Error al actualizar'); });
        }

        // ── Eliminar item de detalle ──────────────────────────────
        function eliminarDetalleItem(id, material, salidaId) {
            Swal.fire({
                title: '¿Eliminar material?',
                html: `Se eliminará: <b>${material}</b><br><small class="text-muted">Si es el último material, la salida también será eliminada.</small>`,
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.value) {
                    openLoading();
                    axios.post(urlAdmin + '/admin/historial/salidas/detalle/eliminar', { id: id })
                        .then((response) => {
                            closeLoading();
                            switch (response.data.success) {
                                case 1:
                                    if (response.data.salida_borrada) {
                                        toastr.success('Material eliminado. La salida fue eliminada por quedar vacía.');
                                        $('#modalDetalle').modal('hide');
                                        recargar();
                                    } else {
                                        toastr.success('Material eliminado correctamente');
                                        recargarDetalle();
                                        recargar();
                                    }
                                    break;
                                case 0:
                                    toastr.error('El material no existe o ya fue eliminado');
                                    break;
                                default:
                                    toastr.error('Error al eliminar');
                            }
                        })
                        .catch(() => { closeLoading(); toastr.error('Error al eliminar'); });
                }
            });
        }
    </script>
@endsection
