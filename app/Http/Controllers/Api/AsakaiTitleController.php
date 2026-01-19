<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AsakaiTitle;
use Illuminate\Http\Request;

class AsakaiTitleController extends Controller
{
    /**
     * Display a listing of asakai titles.
     */
    public function index()
    {
        try {
            $titles = AsakaiTitle::orderBy('category')->orderBy('title')->get();

            return response()->json([
                'success' => true,
                'data' => $titles
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch asakai titles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified asakai title.
     */
    public function show($id)
    {
        try {
            $title = AsakaiTitle::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $title
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Asakai title not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}
