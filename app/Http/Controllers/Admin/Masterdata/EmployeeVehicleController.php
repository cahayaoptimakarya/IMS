<?php

namespace App\Http\Controllers\Admin\Masterdata;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeVehicleController extends Controller
{
    public function index()
    {
        return view('admin.masterdata.employees_vehicles.index');
    }

    public function data(Request $request)
    {
        $type = $request->input('type');
        $search = $request->input('search.value', '');
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 10);
        $draw = (int) $request->input('draw', 0);

        if ($type === 'employees') {
            $base = Employee::query();
            $total = (clone $base)->count();
            if ($search) {
                $base->where(function($q) use ($search){
                    $q->where('name','like',"%$search%")
                      ->orWhere('phone','like',"%$search%")
                      ->orWhere('position','like',"%$search%")
                      ->orWhere('license_number','like',"%$search%") ;
                });
            }
            $filtered = (clone $base)->count();
            $rows = $base->orderBy('id','desc')->offset($start)->limit($length)->get();
            $data = $rows->map(function($r){
                return [
                    'id' => $r->id,
                    'name' => $r->name,
                    'phone' => $r->phone,
                    'position' => $r->position,
                    'license_number' => $r->license_number,
                    'active' => (bool) $r->active,
                ];
            });
        } else {
            $base = Vehicle::query();
            $total = (clone $base)->count();
            if ($search) {
                $base->where(function($q) use ($search){
                    $q->where('license_plate','like',"%$search%")
                      ->orWhere('type','like',"%$search%")
                      ->orWhere('brand','like',"%$search%")
                      ->orWhere('model','like',"%$search%") ;
                });
            }
            $filtered = (clone $base)->count();
            $rows = $base->orderBy('id','desc')->offset($start)->limit($length)->get();
            $data = $rows->map(function($r){
                return [
                    'id' => $r->id,
                    'license_plate' => $r->license_plate,
                    'type' => $r->type,
                    'brand' => $r->brand,
                    'model' => $r->model,
                    'color' => $r->color,
                    'active' => (bool) $r->active,
                ];
            });
        }

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $type = $request->input('type');
        if ($type === 'employees') {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'nullable|string|max:255',
                'position' => 'nullable|string|max:255',
                'license_number' => 'nullable|string|max:255',
                'active' => 'sometimes',
                'notes' => 'nullable|string',
            ]);
            $validated['active'] = $request->has('active') && in_array($request->input('active'), ['1',1,true,'true','on'], true);
            Employee::create($validated);
        } else {
            $validated = $request->validate([
                'license_plate' => 'required|string|max:255|unique:vehicles,license_plate',
                'type' => 'nullable|string|max:255',
                'brand' => 'nullable|string|max:255',
                'model' => 'nullable|string|max:255',
                'color' => 'nullable|string|max:255',
                'active' => 'sometimes',
                'description' => 'nullable|string',
            ]);
            $validated['active'] = $request->has('active') && in_array($request->input('active'), ['1',1,true,'true','on'], true);
            Vehicle::create($validated);
        }
        return response()->json(['success' => true]);
    }

    public function update(Request $request, string $type, int $id)
    {
        if ($type === 'employees') {
            $emp = Employee::findOrFail($id);
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'nullable|string|max:255',
                'position' => 'nullable|string|max:255',
                'license_number' => 'nullable|string|max:255',
                'active' => 'sometimes',
                'notes' => 'nullable|string',
            ]);
            $validated['active'] = $request->has('active') ? in_array($request->input('active'), ['1',1,true,'true','on'], true) : (bool) $emp->active;
            $emp->update($validated);
        } else {
            $veh = Vehicle::findOrFail($id);
            $validated = $request->validate([
                'license_plate' => 'required|string|max:255|unique:vehicles,license_plate,'.$veh->id,
                'type' => 'nullable|string|max:255',
                'brand' => 'nullable|string|max:255',
                'model' => 'nullable|string|max:255',
                'color' => 'nullable|string|max:255',
                'active' => 'sometimes',
                'description' => 'nullable|string',
            ]);
            $validated['active'] = $request->has('active') ? in_array($request->input('active'), ['1',1,true,'true','on'], true) : (bool) $veh->active;
            $veh->update($validated);
        }
        return response()->json(['success' => true]);
    }

    public function destroy(string $type, int $id)
    {
        if ($type === 'employees') {
            Employee::whereKey($id)->delete();
        } else {
            Vehicle::whereKey($id)->delete();
        }
        return response()->json(['success' => true]);
    }
}
