<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileDownloadController extends ApiController
{
    private const ALLOWED_FOLDERS = [
        'prod_plan' => 'prod_plan',
    ];

    /**
     * Get list of available files in a folder
     * GET /api/files/list/{folder}
     */
    public function listFiles($folder): JsonResponse
    {
        if (!isset(self::ALLOWED_FOLDERS[$folder])) {
            return $this->sendError('Folder tidak ditemukan', [], 404);
        }

        $folderPath = self::ALLOWED_FOLDERS[$folder];
        $fullPath = public_path($folderPath);

        if (!is_dir($fullPath)) {
            return $this->sendError('Folder tidak ada', [], 404);
        }

        try {
            $files = array_filter(
                scandir($fullPath),
                fn($file) => !in_array($file, ['.', '..', '~$' . substr($file, 0, 1)])
                    && is_file($fullPath . DIRECTORY_SEPARATOR . $file)
                    && preg_match('/\.(xlsx|xls|csv)$/i', $file)
            );

            $fileList = array_map(function ($file) use ($fullPath, $folder) {
                $filePath = $fullPath . DIRECTORY_SEPARATOR . $file;
                return [
                    'name' => $file,
                    'size' => filesize($filePath),
                    'size_formatted' => $this->formatBytes(filesize($filePath)),
                    'modified' => date('Y-m-d H:i:s', filemtime($filePath)),
                    'download_url' => "/api/files/download/{$folder}/{$file}",
                ];
            }, $files);

            return $this->sendResponse(
                array_values($fileList),
                'Daftar file berhasil diambil'
            );
        } catch (\Throwable $e) {
            return $this->sendError('Gagal mengambil daftar file: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Download file
     * GET /api/files/download/{folder}/{filename}
     */
    public function downloadFile($folder, $filename): BinaryFileResponse|JsonResponse
    {
        if (!isset(self::ALLOWED_FOLDERS[$folder])) {
            return $this->sendError('Folder tidak ditemukan', [], 404);
        }

        // Validate file extension first
        if (!preg_match('/\.(xlsx|xls|csv)$/i', $filename)) {
            return $this->sendError('Tipe file tidak diizinkan', [], 403);
        }

        $folderPath = self::ALLOWED_FOLDERS[$folder];
        $baseFolderPath = public_path($folderPath);
        $fullPath = $baseFolderPath . DIRECTORY_SEPARATOR . $filename;

        // Security check: prevent directory traversal
        $realFullPath = realpath($fullPath);
        $realBasePath = realpath($baseFolderPath);

        if ($realFullPath === false || $realBasePath === false || !str_starts_with($realFullPath, $realBasePath)) {
            return $this->sendError('File tidak ditemukan', [], 404);
        }

        if (!file_exists($fullPath)) {
            return $this->sendError('File tidak ditemukan', [], 404);
        }

        if (!is_file($fullPath)) {
            return $this->sendError('Path bukan file', [], 400);
        }

        try {
            return response()->download($fullPath, $filename);
        } catch (\Throwable $e) {
            return $this->sendError('Gagal download file: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
