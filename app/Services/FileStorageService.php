<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileStorageService
{
    private string $disk;

    public function __construct()
    {
        $this->disk = config('filesystems.default', 'public');
    }

    public function store(UploadedFile $file, string $folder = ''): string
    {
        try {
            $path = $folder
                ? $file->store($folder, $this->disk)
                : $file->store('/', $this->disk);

            if (!$path) {
                throw new \RuntimeException('File storage returned empty path');
            }

            return $path;
        } catch (\Exception $e) {
            Log::error('File storage exception', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    public function delete(string $path): bool
    {
        try {
            if (Storage::disk($this->disk)->exists($path)) {
                return Storage::disk($this->disk)->delete($path);
            }

            return false;
        } catch (\Exception $e) {
            Log::error('File delete exception', ['message' => $e->getMessage()]);
            return false;
        }
    }

    public function getUrl(string $path): string
    {
        return asset('storage/' . $path);
    }

    public function getFilePath(string $path): string
    {
        return Storage::disk($this->disk)->path($path);
    }

    public function getDisk(): string
    {
        return $this->disk;
    }
}
