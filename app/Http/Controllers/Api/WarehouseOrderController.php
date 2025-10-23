<?php

namespace App\Http\Controllers\Api;

use App\Models\WarehouseOrder;
use Illuminate\Http\Request;

class WarehouseOrderController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $orders = WarehouseOrder::all();
            return $this->sendResponse($orders, 'Warehouse orders retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving warehouse orders: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $order = WarehouseOrder::find($id);
            
            if (is_null($order)) {
                return $this->sendError('Warehouse order not found.');
            }

            return $this->sendResponse($order, 'Warehouse order retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving warehouse order: ' . $e->getMessage());
        }
    }
}
