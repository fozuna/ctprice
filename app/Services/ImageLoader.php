<?php

namespace App\Services;

use App\Core\Logger;

class ImageLoader
{
    private $directory;
    private $logger;
    private $allowedMimeTypes = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'image/svg+xml' => ['svg'],
        'image/bmp' => ['bmp'],
        'image/tiff' => ['tiff', 'tif'],
        'image/x-icon' => ['ico'],
    ];

    public function __construct($directory)
    {
        $this->directory = rtrim($directory, '/\\');
        $this->logger = new Logger('image_loader.log');
    }

    public function getImages()
    {
        $this->logger->info("Starting scan of directory: {$this->directory}");
        
        if (!is_dir($this->directory)) {
            $this->logger->error("Directory not found: {$this->directory}");
            return [];
        }

        $images = [];
        $files = scandir($this->directory);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $this->directory . DIRECTORY_SEPARATOR . $file;
            
            try {
                if ($this->isValidImage($filePath)) {
                    $images[] = $file;
                    $this->logger->info("Loaded image: {$file}");
                }
            } catch (\Throwable $e) {
                $this->logger->error("Error processing file {$file}: " . $e->getMessage());
            }
        }

        $this->logger->info("Scan complete. Found " . count($images) . " images.");
        return $images;
    }

    private function isValidImage($filePath)
    {
        if (!is_readable($filePath)) {
            $this->logger->warning("File not readable: {$filePath}");
            return false;
        }

        // Check magic numbers using finfo
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filePath);

        if (!$mimeType) {
            $this->logger->warning("Could not determine mime type for: {$filePath}");
            return false;
        }

        if (array_key_exists($mimeType, $this->allowedMimeTypes)) {
            return true;
        }

        $this->logger->warning("Unsupported mime type '{$mimeType}' for file: {$filePath}");
        return false;
    }

    public function addSupportedMimeType($mimeType, $extensions)
    {
        $this->allowedMimeTypes[$mimeType] = (array) $extensions;
        $this->logger->info("Added custom mime type support: {$mimeType}");
    }
}
