<?php

namespace App\Http\Controllers\Api;

use App\Models\ReceiptPurchase;
use Illuminate\Http\Request;

class ReceiptPurchaseController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $receipts = ReceiptPurchase::all();
            return $this->sendResponse($receipts, 'Receipt purchases retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving receipt purchases: ' . $e->getMessage());
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
            $receipt = ReceiptPurchase::find($id);
            
            if (is_null($receipt)) {
                return $this->sendError('Receipt purchase not found.');
            }

            return $this->sendResponse($receipt, 'Receipt purchase retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving receipt purchase: ' . $e->getMessage());
        }
    }
}
