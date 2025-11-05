<?php

namespace App\Http\Controllers\Api;

use App\Models\SoInvoiceLine2;
use Illuminate\Http\Request;

class SoInvoiceLine2Controller extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $invoiceLines = SoInvoiceLine2::all();
            return $this->sendResponse($invoiceLines, 'Sales order invoice lines 2 retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving sales order invoice lines 2: ' . $e->getMessage());
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
            $invoiceLine = SoInvoiceLine2::find($id);
            
            if (is_null($invoiceLine)) {
                return $this->sendError('Sales order invoice line 2 not found.');
            }

            return $this->sendResponse($invoiceLine, 'Sales order invoice line 2 retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving sales order invoice line 2: ' . $e->getMessage());
        }
    }
}
