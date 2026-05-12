<?php

namespace App\Http\Controllers\Restaurant;

use App\BusinessLocation;
use App\Restaurant\ResTable;
use Datatables;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class TableController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        if (! auth()->user()->can('access_tables')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $tables = ResTable::where('res_tables.business_id', $business_id)
                        ->join('business_locations AS BL', 'res_tables.location_id', '=', 'BL.id')
                        ->select(['res_tables.name as name', 'BL.name as location',
                            'res_tables.description', 'res_tables.id', 'res_tables.qr_token']);

            return Datatables::of($tables)
                ->addColumn(
                    'action',
                    '@role("Admin#'.$business_id.'")
                    <button data-href="{{action(\'App\Http\Controllers\Restaurant\TableController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary edit_table_button"><i class="glyphicon glyphicon-edit"></i> @lang("messages.edit")</button>
                        &nbsp;
                    @endrole
                    @role("Admin#'.$business_id.'")
                        <button data-href="{{action(\'App\Http\Controllers\Restaurant\TableController@destroy\', [$id])}}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_table_button"><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</button>
                    @endrole
                    <button data-token="{{$qr_token}}" data-name="{{$name}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-success show_qr_button"><i class="fa fa-qrcode"></i> QR</button>'
                )
                ->removeColumn('id')
                ->removeColumn('qr_token')
                ->escapeColumns(['action'])
                ->make(true);
        }

        return view('restaurant.table.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        if (! auth()->user()->can('access_tables')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $business_locations = BusinessLocation::forDropdown($business_id);

        return view('restaurant.table.create')
            ->with(compact('business_locations'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        if (! auth()->user()->can('access_tables')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'description', 'location_id', 'section', 'shape', 'capacity']);
            $business_id = $request->session()->get('user.business_id');
            $input['business_id'] = $business_id;
            $input['created_by'] = $request->session()->get('user.id');

            $table = ResTable::create($input);
            $output = ['success' => true,
                'data' => $table,
                'msg' => __('lang_v1.added_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Show the specified resource.
     *
     * @return Response
     */
    public function show()
    {
        if (! auth()->user()->can('access_tables')) {
            abort(403, 'Unauthorized action.');
        }

        return view('restaurant.table.show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('access_tables')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $table = ResTable::where('business_id', $business_id)->find($id);

            return view('restaurant.table.edit')
                ->with(compact('table'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function update(Request $request, $id)
    {
        if (! auth()->user()->can('access_tables')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $input = $request->only(['name', 'description', 'section', 'shape', 'capacity']);
                $business_id = $request->session()->get('user.business_id');

                $table = ResTable::where('business_id', $business_id)->findOrFail($id);
                $table->fill($input);
                $table->save();

                $output = ['success' => true,
                    'msg' => __('lang_v1.updated_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Transfer active order from one table to another.
     */
    public function transfer(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $transaction_id  = $request->input('transaction_id');
        $target_table_id = $request->input('target_table_id');

        // Pastikan target meja milik business ini
        $target = ResTable::where('business_id', $business_id)->find($target_table_id);
        if (!$target) {
            return response()->json(['success' => false, 'msg' => 'Meja tidak ditemukan.']);
        }

        // Cek target meja sudah terisi order draft
        $occupied = DB::table('transactions')
            ->where('business_id', $business_id)
            ->where('res_table_id', $target_table_id)
            ->where('type', 'sell')
            ->where('status', 'draft')
            ->exists();

        if ($occupied) {
            return response()->json(['success' => false, 'msg' => 'Meja ' . $target->name . ' sudah ada order aktif.']);
        }

        // Pindahkan
        $updated = DB::table('transactions')
            ->where('business_id', $business_id)
            ->where('id', $transaction_id)
            ->where('type', 'sell')
            ->update(['res_table_id' => $target_table_id]);

        if (!$updated) {
            return response()->json(['success' => false, 'msg' => 'Transaksi tidak ditemukan.']);
        }

        return response()->json([
            'success'    => true,
            'msg'        => 'Order berhasil dipindah ke ' . $target->name,
            'table_name' => $target->name,
            'table_id'   => $target->id,
        ]);
    }

    /**
     * Floor plan view — returns table statuses for visual display.
     */
    public function floorPlan(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $location_id = $request->input('location_id');

        $tables = ResTable::where('business_id', $business_id)
            ->when($location_id, fn($q) => $q->where('location_id', $location_id))
            ->whereNull('deleted_at')
            ->get();

        // Get active (unpaid) transactions per table
        $active = DB::table('transactions')
            ->where('business_id', $business_id)
            ->where('type', 'sell')
            ->whereIn('status', ['draft', 'final', 'ordered'])
            ->where('payment_status', '!=', 'paid')
            ->whereNotNull('res_table_id')
            ->whereIn('res_table_id', $tables->pluck('id'))
            ->select('res_table_id', 'id as transaction_id', 'status', 'invoice_no')
            ->orderBy('created_at', 'desc')
            ->get()
            ->keyBy('res_table_id');

        $result = $tables->map(function ($table) use ($active) {
            $trx = $active->get($table->id);
            $status = 'available';
            if ($trx) {
                $status = $trx->status === 'final' ? 'bill' : 'occupied';
            }
            return [
                'id'           => $table->id,
                'name'         => $table->name,
                'section'      => $table->section ?? 'Main Floor',
                'shape'        => $table->shape ?? 'square',
                'capacity'     => $table->capacity ?? 4,
                'status'       => $status,
                'transaction_id' => $trx->transaction_id ?? null,
                'invoice_no'   => $trx->invoice_no ?? null,
                'pos_x'        => $table->pos_x,
                'pos_y'        => $table->pos_y,
            ];
        });

        return response()->json($result->groupBy('section'));
    }

    /**
     * Save drag-and-drop position for a table.
     */
    public function savePosition(Request $request, $id)
    {
        $business_id = $request->session()->get('user.business_id');
        $table = ResTable::where('business_id', $business_id)->findOrFail($id);
        $table->pos_x = (int) $request->input('pos_x', 0);
        $table->pos_y = (int) $request->input('pos_y', 0);
        $table->save();
        return response()->json(['success' => true]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('access_tables')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->user()->business_id;

                $table = ResTable::where('business_id', $business_id)->findOrFail($id);
                $table->delete();

                $output = ['success' => true,
                    'msg' => __('lang_v1.deleted_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }
}
