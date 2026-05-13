<?php

namespace App\Http\Controllers\Sistema;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermisoController extends Controller
{
    public function index(){
        $roles = Role::all()->pluck('name', 'id');

        return view('backend.admin.rolesypermisos.permisos', compact('roles'));
    }

    public function tablaUsuarios(){
        $usuarios = Usuario::orderBy('id', 'ASC')->get();

        return view('backend.admin.rolesypermisos.tabla.tablapermisos', compact('usuarios'));
    }

    public function nuevoUsuario(Request $request){

        if(Usuario::where('usuario', $request->usuario)->first()){
            return ['success' => 1];
        }

        $u = new Usuario();
        $u->nombre = $request->nombre;
        $u->usuario = $request->usuario;
        $u->password = bcrypt($request->password);

        if ($u->save()) {
            $role = Role::findById($request->rol, 'admin');
            $u->assignRole($role);
            return ['success' => 2];
        } else {
            return ['success' => 3];
        }
    }

    public function infoUsuario(Request $request){
        if($info = Usuario::where('id', $request->id)->first()){

            $roles = Role::all()->pluck('name', 'id');

            $idrol = $info->roles->pluck('id');

            return ['success' => 1,
                'info' => $info,
                'roles' => $roles,
                'idrol' => $idrol];

        }else{
            return ['success' => 2];
        }
    }

    public function editarUsuario(Request $request){

        if(Usuario::where('id', $request->id)->first()){

            if(Usuario::where('usuario', $request->usuario)
                ->where('id', '!=', $request->id)->first()){
                return ['success' => 1];
            }

            $usuario = Usuario::find($request->id);
            $usuario->nombre = $request->nombre;
            $usuario->usuario = $request->usuario;

            if($request->password != null){
                $usuario->password = bcrypt($request->password);
            }

            // Buscar el rol con el guard correcto y sincronizar
            $role = Role::findById($request->rol, 'admin');
            $usuario->syncRoles($role);

            $usuario->save();

            return ['success' => 2];
        }else{
            return ['success' => 3];
        }
    }

    public function nuevoRol(Request $request){

        $regla = array(
            'nombre' => 'required',
        );

        $validar = Validator::make($request->all(), $regla);

        if ($validar->fails()){return ['success' => 0];}

        // verificar si existe el rol
        if(Role::where('name', $request->nombre)->first()){
            return ['success' => 1];
        }

        Role::create([
            'name' => $request->nombre,
            'guard_name' => 'admin'
        ]);

        return ['success' => 2];
    }

    public function nuevoPermisoExtra(Request $request){

        // verificar si existe el permiso
        if(Permission::where('name', $request->nombre)->first()){
            return ['success' => 1];
        }

        Permission::create(['name' => $request->nombre, 'description' => $request->descripcion, 'guard_name' => 'admin']);

        return ['success' => 2];
    }

    public function borrarPermisoGlobal(Request $request){

        // buscamos el permiso el cual queremos eliminar
        $permission = Permission::findById($request->idpermiso)->delete();

        return ['success' => 1];
    }
}
