<?php

namespace App\Http\Controllers\Api;

use App\Models\SoInvoiceLine;
use Illuminate\Http\Request;

class SoInvoiceLineController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $invoiceLines = SoInvoiceLine::all();
            return $this->sendResponse($invoiceLines, 'Sales order invoice lines retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving sales order invoice lines: ' . $e->getMessage());
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
            $invoiceLine = SoInvoiceLine::find($id);
            
            if (is_null($invoiceLine)) {
                return $this->sendError('Sales order invoice line not found.');
            }

            return $this->sendResponse($invoiceLine, 'Sales order invoice line retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving sales order invoice line: ' . $e->getMessage());
        }
    }
}
