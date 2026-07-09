<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <table id="tabla" class="table table-bordered table-striped">
                            <thead>
                            <tr>
                                <th style="width:4%">ID</th>
                                <th style="width:8%">Fecha</th>
                                <th style="width:9%">Talonario</th>
                                <th style="width:10%">N. Contrato</th>
                                <th style="width:10%">N. Orden</th>
                                <th style="width:12%">Recibe</th>
                                <th style="width:15%">Descripción</th>
                                <th style="width:32%">Opciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($arraySalidas as $dato)
                                <tr>
                                    <td>{{ $dato->id }}</td>
                                    <td>{{ $dato->fecha_fmt }}</td>
                                    <td>{{ $dato->ficha_talonario ?? '' }}</td>
                                    <td>{{ $dato->numero_contrato ?? '' }}</td>
                                    <td>{{ $dato->numero_orden ?? '' }}</td>
                                    <td>{{ $dato->nombre_firma_3 ?? '' }}</td>
                                    <td>{{ $dato->descripcion ?? '' }}</td>
                                    <td class="text-center">
                                        <button type="button"
                                                class="btn btn-success btn-xs"
                                                style="margin:2px"
                                                onclick="window.location.href='{{ url('/admin/historial/salidas/extras') }}/{{ $dato->id }}'">
                                            <i class="fas fa-plus"></i> Extras
                                        </button>
                                        <button type="button"
                                                class="btn btn-info btn-xs"
                                                style="margin:2px"
                                                onclick="verDetalle({{ $dato->id }}, 'Salida #{{ $dato->id }} — {{ $dato->fecha_fmt }}')">
                                            <i class="fas fa-list"></i> Detalle
                                        </button>
                                        <button type="button"
                                                class="btn btn-secondary btn-xs"
                                                style="margin:2px"
                                                onclick="window.open('{{ url('/admin/historial/salidas/pdf') }}/{{ $dato->id }}', '_blank')">
                                            <i class="fas fa-file-pdf"></i> PDF
                                        </button>
                                        <button type="button"
                                                class="btn btn-warning btn-xs"
                                                style="margin:2px"
                                                onclick="modalEditar({{ $dato->id }})">
                                            <i class="fas fa-edit"></i> Editar
                                        </button>
                                        <button type="button"
                                                class="btn btn-danger btn-xs"
                                                style="margin:2px"
                                                onclick="eliminar({{ $dato->id }})">
                                            <i class="fas fa-trash"></i> Borrar
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
