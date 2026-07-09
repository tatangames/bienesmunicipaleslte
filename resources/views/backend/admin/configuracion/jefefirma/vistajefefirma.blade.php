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

                    <div class="form-group">
                        <label>Nombre Firma 1</label>
                        <input type="text" maxlength="100" class="form-control" id="nombre1"
                               value="{{ $infoGeneral->nombre_firma_1 }}" autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label>Nombre Firma 2</label>
                        <input type="text" maxlength="100" class="form-control" id="nombre2"
                               value="{{ $infoGeneral->nombre_firma_2 }}" autocomplete="off">
                    </div>

                    <hr>

                    <div class="form-group">
                        <label>Encabezado</label>
                        <input type="text" maxlength="100" class="form-control" id="nombre2"
                               value="{{ $infoGeneral->encabezado }}" autocomplete="off">
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
            openLoading();

            var formData = new FormData();
            formData.append('nombre1', document.getElementById('nombre1').value);
            formData.append('nombre2', document.getElementById('nombre2').value);

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
