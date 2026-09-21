@extends('adminlte::page')

@section('title', 'Jefe Firma')

@section('content_header')
    <h1>Jefe Firma</h1>
@stop

@section('plugins.Sweetalert2', true)
@include('backend.urlglobal')

@section('content_top_nav_right')
    <link href="{{ asset('css/toastr.min.css') }}" type="text/css" rel="stylesheet" />

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
    <section class="content">
        <div class="container-fluid">
            <div class="card card-blue">
                <div class="card-header">
                    <h3 class="card-title">Firmas</h3>
                </div>
                <div class="card-body">

                    {{-- ══ Firmas Izquierda / Derecha ══ --}}
                    <div class="row">

                        {{-- Columna Izquierda --}}
                        <div class="col-md-6">
                            <p class="font-weight-bold">Para Firmas lado Izquierdo en Registro de Salidas</p>

                            <div class="form-group">
                                <label>Nombre</label>
                                <input type="text" maxlength="100" class="form-control" id="nombre_izq1"
                                       value="{{ $infoGeneral->nombre_firma_1 }}" autocomplete="off">
                            </div>

                            <div class="form-group">
                                <label>Cargo</label>
                                <input type="text" maxlength="100" class="form-control" id="nombre_izq2"
                                       value="{{ $infoGeneral->nombre_firma_2 }}" autocomplete="off">
                            </div>
                        </div>

                        {{-- Columna Derecha --}}
                        <div class="col-md-6">
                            <p class="font-weight-bold">Para Firmas lado Derecho en Registro de Salidas</p>

                            <div class="form-group">
                                <label>Nombre</label>
                                <input type="text" maxlength="100" class="form-control" id="nombre_der1"
                                       value="{{ $infoGeneral->nombre_firma_3 }}" autocomplete="off">
                            </div>

                            <div class="form-group">
                                <label>Cargo</label>
                                <input type="text" maxlength="100" class="form-control" id="nombre_der2"
                                       value="{{ $infoGeneral->nombre_firma_4 }}" autocomplete="off">
                            </div>
                        </div>

                    </div>

                    <hr>

                    {{-- ══ Encabezado y Distancias ══ --}}
                    <p class="font-weight-bold text-muted">
                        <i class="fas fa-ruler-combined mr-1"></i> Encabezado y distancias del documento
                    </p>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Encabezado</label>
                                <input type="text" maxlength="100" class="form-control" id="encabezado"
                                       value="{{ $infoGeneral->encabezado }}" autocomplete="off">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Distancia Firmas</label>
                                <input type="number" class="form-control" id="px_firmas"
                                       value="{{ $infoGeneral->px_firmas }}" autocomplete="off">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="d-block">Salto de página</label>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="salto_pagina"
                                        {{ $infoGeneral->salto_pagina ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="salto_pagina">
                                        Activar salto de página antes de las firmas
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="card-footer">
                    <button class="btn btn-primary" onclick="guardar()">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                </div>
            </div>
        </div>
    </section>
@stop

@section('js')
    <script src="{{ asset('js/toastr.min.js') }}"></script>
    <script src="{{ asset('js/axios.min.js') }}"></script>
    <script src="{{ asset('js/alertaPersonalizada.js') }}"></script>

    <script>
        function guardar() {

            const pxFirmas = document.getElementById('px_firmas').value.trim();

            if (pxFirmas === '') {
                toastr.error('PX Firmas es requerido');
                document.getElementById('px_firmas').focus();
                return;
            }

            openLoading();

            var formData = new FormData();
            formData.append('nombre_izq1', document.getElementById('nombre_izq1').value);
            formData.append('nombre_izq2', document.getElementById('nombre_izq2').value);
            formData.append('nombre_der1', document.getElementById('nombre_der1').value);
            formData.append('nombre_der2', document.getElementById('nombre_der2').value);
            formData.append('encabezado', document.getElementById('encabezado').value);
            formData.append('px_firmas', document.getElementById('px_firmas').value);
            formData.append('salto_pagina', document.getElementById('salto_pagina').checked ? 1 : 0);

            axios.post(urlAdmin + '/admin/jefefirma/actualizar', formData)
                .then((response) => {
                    closeLoading();
                    if (response.data.success === 1) {
                        toastr.success('Actualizado correctamente');
                    } else {
                        toastr.error('Error al actualizar');
                    }
                })
                .catch(() => {
                    closeLoading();
                    toastr.error('Error al actualizar');
                });
        }
    </script>
@endsection
