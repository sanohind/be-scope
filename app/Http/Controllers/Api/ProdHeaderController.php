<?php

namespace App\Http\Controllers\Api;

use App\Models\ProdHeader;
use Illuminate\Http\Request;

class ProdHeaderController extends ApiController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $prodHeaders = ProdHeader::all();
            return $this->sendResponse($prodHeaders, 'Production headers retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving production headers: ' . $e->getMessage());
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
            $prodHeader = ProdHeader::find($id);
            
            if (is_null($prodHeader)) {
                return $this->sendError('Production header not found.');
            }

            return $this->sendResponse($prodHeader, 'Production header retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving production header: ' . $e->getMessage());
        }
    }
}
