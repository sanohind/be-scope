<?php

namespace App\Http\Controllers\Api;

use App\Models\StockByWh;
use Illuminate\Http\Request;

class StockByWhController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $stocks = StockByWh::all();
            return $this->sendResponse($stocks, 'Stocks retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving stocks: ' . $e->getMessage());
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
            $stock = StockByWh::find($id);
            
            if (is_null($stock)) {
                return $this->sendError('Stock not found.');
            }

            return $this->sendResponse($stock, 'Stock retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving stock: ' . $e->getMessage());
        }
    }
}
