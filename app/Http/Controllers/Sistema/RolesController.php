<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesController extends Controller
{
    public function index(){
        return view('backend.admin.rolesypermisos.roles');
    }

    public function tablaRoles(){
        $roles = Role::all()->pluck('name', 'id');
        return view('backend.admin.rolesypermisos.tabla.tablaroles', compact('roles'));
    }

    public function vistaPermisos($id){
        // obtener todos los permisos que existen
        $permisos = Permission::all()->sortBy('name')->pluck('name', 'id');

        return view('backend.admin.rolesypermisos.rolespermisos', compact('id', 'permisos'));
    }

    public function tablaRolesPermisos($id)
    {
        // Validar que el ID sea válido antes de buscar
        $role = Role::findById($id, 'admin'); // o el guard correcto

        if (!$role) {
            abort(404, 'Rol no encontrado');
        }

        $permisos = $role->permissions()->pluck('name', 'id');

        return view('backend.admin.rolesypermisos.tabla.tablarolespermisos', compact('permisos'));
    }

    public function borrarPermiso(Request $request){

        $permission = Permission::findById($request->idpermiso, 'admin');
        $role = Role::findById($request->idrol, 'admin');

        $role->revokePermissionTo($permission);

        return ['success' => 1];
    }

    public function agregarPermiso(Request $request){

        $role = Role::findById($request->idrol, 'admin');
        $permission = Permission::findById($request->idpermiso, 'admin');

        $role->givePermissionTo($permission);

        return ['success' => 1];
    }

    public function listaTodosPermisos(){
        return view('backend.admin.rolesypermisos.listapermisos');
    }

    public function tablaTodosPermisos(){

        $permisos = Permission::all();
        return view('backend.admin.rolesypermisos.tabla.tablalistapermisos', compact('permisos'));
    }

    public function borrarRolGlobal(Request $request){

        $role = Role::findById($request->idrol, 'admin');

        $role->delete();

        return ['success' => 1];
    }
}
