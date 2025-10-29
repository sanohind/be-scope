<?php

namespace App\Http\Controllers\Api;

use App\Models\WarehouseOrderLine;
use Illuminate\Http\Request;

class WarehouseOrderLineController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $orderLines = WarehouseOrderLine::all();
            return $this->sendResponse($orderLines, 'Warehouse order lines retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving warehouse order lines: ' . $e->getMessage());
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
            $orderLine = WarehouseOrderLine::find($id);
            
            if (is_null($orderLine)) {
                return $this->sendError('Warehouse order line not found.');
            }

            return $this->sendResponse($orderLine, 'Warehouse order line retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving warehouse order line: ' . $e->getMessage());
        }
    }
}
