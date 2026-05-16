@extends('adminlte::page')

@section('title', 'Reservas Pendientes')

@section('content_header')
    <h1>Reservas Pendientes</h1>
@stop

@section('plugins.Sweetalert2', true)
@include('backend.urlglobal')

@section('content_top_nav_right')
    <link href="{{ asset('css/toastr.min.css') }}" type="text/css" rel="stylesheet" />
    <link href="{{ asset('css/select2.min.css') }}" type="text/css" rel="stylesheet">
    <link href="{{ asset('css/select2-bootstrap-5-theme.min.css') }}" type="text/css" rel="stylesheet">

    <li class="nav-item dropdown">
        <a href="#" class="nav-link" data-toggle="dropdown">
            <i class="fas fa-cogs"></i>
            <span class="d-none d-md-inline">{{ Auth::guard('admin')->user()->nombre }}</span>
        </a>
        <div class="dropdown-menu dropdown-menu-right">
            <a href="{{ route('admin.perfil') }}" class="dropdown-item">
                <i class="fas fa-user mr-2"></i>Editar Perfil
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
        *:focus { outline: none; }
        .seccion-header {
            background: linear-gradient(135deg, #1a3a6b 0%, #2156af 100%);
            border-radius: 10px 10px 0 0;
            padding: 12px 18px;
        }
        .seccion-header h3 {
            color: #fff; font-size: 14px; font-weight: 700;
            letter-spacing: .05em; text-transform: uppercase; margin: 0;
        }
        .card-info {
            border: none; border-radius: 10px;
            box-shadow: 0 2px 18px rgba(33,86,175,.13); margin-bottom: 20px;
        }
        .field-label {
            font-size: 11px; font-weight: 700; color: #6b7a99;
            text-transform: uppercase; letter-spacing: .06em;
            margin-bottom: 5px; display: block;
        }
        #tablaReservas thead th {
            background: #6f42c1; color: #fff; font-size: 11px;
            font-weight: 700; text-transform: uppercase;
            border: none !important; padding: 10px 12px;
        }
        #tablaReservas tbody td { vertical-align: middle; font-size: 13px; padding: 8px 10px; }

        .btn-despachar {
            background: linear-gradient(135deg, #6f42c1, #5a2d91);
            color: #fff; border: none; border-radius: 8px;
            padding: 10px 28px; font-weight: 400; font-size: 14px;
            box-shadow: 0 4px 14px rgba(111,66,193,.35); transition: all .2s;
        }
        .btn-despachar:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(111,66,193,.45); color: #fff;
        }

        .destino-select { font-size: 12px; padding: 4px 6px; border-radius: 6px; border: 1px solid #dee2e6; }
        .proyecto-select { display: none; margin-top: 4px; font-size: 12px; padding: 4px 6px; border-radius: 6px; border: 1px solid #dee2e6; width: 100%; }
    </style>

    <div id="divcontenedor" style="display:none">

        {{-- Cabecera fecha + descripción --}}
        <section class="content" style="margin-bottom:0">
            <div class="container-fluid">
                <div class="card card-info">
                    <div class="seccion-header">
                        <h3><i class="fas fa-calendar-check mr-2"></i>Datos del Despacho</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="field-label"><i class="fas fa-calendar-alt mr-1"></i>Fecha de Despacho</label>
                                <input type="date" class="form-control" id="fecha-despacho">
                            </div>
                            <div class="col-md-9">
                                <label class="field-label">
                                    <i class="fas fa-align-left mr-1"></i>Descripción
                                    <small style="text-transform:none; font-weight:400">(Opcional)</small>
                                </label>
                                <input type="text" class="form-control" id="descripcion-despacho"
                                       maxlength="800" placeholder="Descripción general del despacho…">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Tabla reservas --}}
        <section class="content">
            <div class="container-fluid">
                <div class="card card-info">
                    <div class="seccion-header" style="display:flex; justify-content:space-between; align-items:center">
                        <h3><i class="fas fa-lock mr-2"></i>Reservas Pendientes de Despacho</h3>
                        <span id="contador-reservas"
                              style="background:rgba(255,255,255,.2); color:#fff; border-radius:20px;
                                 padding:2px 12px; font-size:12px; font-weight:700">
                        Cargando…
                    </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0"
                                   id="tablaReservas" style="table-layout:fixed; width:100%">
                                <thead>
                                <tr>
                                    <th style="width:4%">
                                        <input type="checkbox" id="chkTodos" onclick="toggleTodos(this)">
                                    </th>
                                    <th style="width:20%">Material</th>
                                    <th style="width:15%">Proyecto Origen</th>
                                    <th style="width:8%">Cant.</th>
                                    <th style="width:12%">Fecha Reserva</th>
                                    <th style="width:20%">Motivo</th>
                                    <th style="width:21%">Destino</th>
                                </tr>
                                </thead>
                                <tbody id="tbodyReservas"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between align-items-center"
                         style="border-top:2px solid #e8eef8; background:#f8faff; border-radius:0 0 10px 10px">
                        <small class="text-muted">Seleccione las reservas a despachar y defina el destino de cada una</small>
                        <button type="button" class="btn-despachar" onclick="preguntaDespachar()">
                            <i class="fas fa-paper-plane mr-1"></i> Despachar Seleccionados
                        </button>
                    </div>
                </div>
            </div>
        </section>

    </div>

@stop

@section('js')
    <script src="{{ asset('js/toastr.min.js') }}"></script>
    <script src="{{ asset('js/axios.min.js') }}"></script>
    <script src="{{ asset('js/sweetalert2.all.min.js') }}"></script>
    <script src="{{ asset('js/alertaPersonalizada.js') }}"></script>
    <script src="{{ asset('js/select2.min.js') }}"></script>

    <script>
        // Proyectos activos disponibles para destino
        var proyectosActivos = @json($proyectosActivos);

        $(document).ready(function () {
            document.getElementById("divcontenedor").style.display = "block";

            var hoy = new Date();
            document.getElementById('fecha-despacho').value = hoy.toJSON().slice(0, 10);

            cargarReservas();
        });

        // ── Cargar reservas pendientes ────────────────────────────────────
        function cargarReservas() {
            axios.post(urlAdmin + '/admin/reservas/listar')
                .then((response) => {
                    if (response.data.success !== 1) {
                        toastr.error('Error al cargar reservas');
                        return;
                    }

                    var lista = response.data.reservas;
                    $('#tbodyReservas').empty();
                    $('#contador-reservas').text(lista.length + (lista.length === 1 ? ' reserva' : ' reservas'));

                    if (lista.length === 0) {
                        $('#tbodyReservas').append(
                            "<tr><td colspan='7' class='text-center text-muted py-4'>" +
                            "<i class='fas fa-check-circle mr-2' style='color:#28a745'></i>" +
                            "No hay reservas pendientes</td></tr>"
                        );
                        return;
                    }

                    // Armar opciones de proyectos para select
                    var opcionesProyecto = "<option value='0' disabled selected>Seleccionar proyecto…</option>";
                    $.each(proyectosActivos, function (i, p) {
                        opcionesProyecto += "<option value='" + p.id + "'>" + p.id + " — " + p.nombre + "</option>";
                    });

                    $.each(lista, function (i, r) {
                        var fechaFmt = r.fecha_reserva
                            ? new Date(r.fecha_reserva).toLocaleDateString('es-SV')
                            : '—';

                        var fila = "<tr data-id='" + r.id + "'>" +
                            "<td style='text-align:center'>" +
                            "<input type='checkbox' class='chk-reserva' data-id='" + r.id + "'>" +
                            "</td>" +
                            "<td style='font-size:12px'>" + (r.nombre_material ?? '—') + "</td>" +
                            "<td style='font-size:12px'>" + (r.nombre_proyecto_origen ?? '—') + "</td>" +
                            "<td style='text-align:center; font-weight:700'>" + r.cantidad + "</td>" +
                            "<td style='font-size:12px'>" + fechaFmt + "</td>" +
                            "<td style='font-size:12px'>" + (r.descripcion ?? '—') + "</td>" +
                            "<td>" +
                            // Select tipo destino
                            "<select class='destino-select select-tipo' style='width:100%' " +
                            "onchange=\"cambiarTipoDestino(this, " + r.id + ")\">" +
                            "<option value=''>— Elegir destino —</option>" +
                            "<option value='proyecto'>Transferir a Proyecto</option>" +
                            "<option value='general'>Salida General</option>" +
                            "</select>" +
                            // Select proyecto (aparece si elige 'proyecto')
                            "<select class='proyecto-select select-proyecto' id='proy-" + r.id + "'>" +
                            opcionesProyecto +
                            "</select>" +
                            "</td>" +
                            "</tr>";

                        $('#tbodyReservas').append(fila);
                    });
                })
                .catch(() => { toastr.error('Error al cargar reservas'); });
        }

        // ── Mostrar/ocultar select de proyecto según tipo ─────────────────
        function cambiarTipoDestino(selectEl, idReserva) {
            var val = $(selectEl).val();
            var proySelect = $('#proy-' + idReserva);
            if (val === 'proyecto') {
                proySelect.show();
            } else {
                proySelect.hide().val('0');
            }
        }

        // ── Seleccionar todos ─────────────────────────────────────────────
        function toggleTodos(chk) {
            $('.chk-reserva').prop('checked', chk.checked);
        }

        // ── Confirmar despacho ────────────────────────────────────────────
        function preguntaDespachar() {
            var seleccionados = $('.chk-reserva:checked');

            if (seleccionados.length === 0) {
                toastr.warning('Seleccione al menos una reserva');
                return;
            }

            // Validar que todos tengan destino elegido
            var valido = true;
            seleccionados.each(function () {
                var idReserva  = $(this).data('id');
                var fila       = $(this).closest('tr');
                var tipo       = fila.find('.select-tipo').val();
                var proyDest   = fila.find('.select-proyecto').val();

                if (!tipo) {
                    toastr.error('Defina el destino de todas las reservas seleccionadas');
                    valido = false;
                    return false;
                }
                if (tipo === 'proyecto' && (!proyDest || proyDest === '0')) {
                    toastr.error('Seleccione el proyecto destino para todas las marcadas como "Transferir a Proyecto"');
                    valido = false;
                    return false;
                }
            });

            if (!valido) return;

            Swal.fire({
                title: '¿Despachar reservas?',
                text:  'Se generarán las salidas correspondientes y las reservas quedarán marcadas como despachadas.',
                icon:  'question',
                showCancelButton:   true,
                confirmButtonColor: '#6f42c1',
                cancelButtonColor:  '#d33',
                cancelButtonText:   'Cancelar',
                confirmButtonText:  'Sí, despachar'
            }).then((result) => { if (result.isConfirmed) ejecutarDespacho(); });
        }

        // ── Ejecutar despacho ─────────────────────────────────────────────
        function ejecutarDespacho() {
            var fecha       = document.getElementById('fecha-despacho').value;
            var descripcion = document.getElementById('descripcion-despacho').value;

            if (!fecha) { toastr.error('Fecha es requerida'); return; }

            var despachos = [];
            $('.chk-reserva:checked').each(function () {
                var idReserva = $(this).data('id');
                var fila      = $(this).closest('tr');
                var tipo      = fila.find('.select-tipo').val();
                var proyDest  = fila.find('.select-proyecto').val();

                despachos.push({
                    idReserva:   idReserva,
                    tipoDestino: tipo,
                    idDestino:   (tipo === 'proyecto') ? proyDest : null,
                });
            });

            openLoading();
            var formData = new FormData();
            formData.append('fecha',       fecha);
            formData.append('descripcion', descripcion);
            formData.append('despachos',   JSON.stringify(despachos));

            axios.post(urlAdmin + '/admin/reservas/despachar', formData)
                .then((response) => {
                    closeLoading();

                    if (response.data.success === 10) {
                        Swal.fire({
                            title: 'Despacho Exitoso',
                            text:  'Las reservas han sido despachadas correctamente.',
                            icon:  'success',
                            allowOutsideClick:  false,
                            confirmButtonColor: '#6f42c1',
                            confirmButtonText:  'Aceptar'
                        }).then((r) => { if (r.isConfirmed) location.reload(); });
                    } else if (response.data.success === 2) {
                        toastr.error(response.data.msg ?? 'Error en reserva');
                    } else {
                        toastr.error('Error al despachar');
                    }
                })
                .catch(() => { toastr.error('Error al despachar'); closeLoading(); });
        }
    </script>
@endsection
