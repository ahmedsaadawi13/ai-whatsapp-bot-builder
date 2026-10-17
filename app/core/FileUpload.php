<?php
// FILE: /app/core/FileUpload.php

/**
 * File Upload Handler
 *
 * Validates extension, MIME type and size, generates random file names,
 * and stores files under /storage/uploads/tenant_{id}/{category}/.
 * Paths returned are relative to /storage/uploads.
 */
class FileUpload {

    /** Allowed extension => accepted MIME types. */
    const MIME_MAP = [
        'jpg'  => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png'  => ['image/png'],
        'pdf'  => ['application/pdf'],
        'txt'  => ['text/plain'],
        'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'csv'  => ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'],
    ];

    private $baseDir;
    private $allowedTypes;
    private $maxSize;
    private $errors = [];
    private $lastSize = 0;

    public function __construct() {
        $this->baseDir = storage_path('uploads');
        $this->allowedTypes = config('app.upload.allowed_types', ['jpg', 'jpeg', 'png', 'pdf', 'txt', 'doc', 'docx']);
        $this->maxSize = (int) config('app.upload.max_size', 10 * 1024 * 1024);

        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0755, true);
        }
    }

    public function setAllowedTypes($types) {
        $this->allowedTypes = $types;
    }

    public function setMaxSize($bytes) {
        $this->maxSize = (int) $bytes;
    }

    /**
     * Validate and store an uploaded file.
     *
     * @param array  $file     One entry from $_FILES
     * @param int    $tenantId Owner tenant
     * @param string $category Sub-folder, e.g. "logos", "media", "knowledge"
     * @return string|false Relative path on success
     */
    public function upload($file, $tenantId, $category) {
        $this->errors = [];

        if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
            $this->errors[] = 'Invalid file upload';
            return false;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->uploadErrorMessage($file['error']);
            return false;
        }
        if ($file['size'] <= 0 || $file['size'] > $this->maxSize) {
            $this->errors[] = 'File is empty or exceeds the maximum size of ' . round($this->maxSize / 1048576, 1) . ' MB';
            return false;
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedTypes, true) || !isset(self::MIME_MAP[$extension])) {
            $this->errors[] = 'File type not allowed. Allowed: ' . implode(', ', $this->allowedTypes);
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, self::MIME_MAP[$extension], true)) {
            $this->errors[] = 'File content does not match its extension';
            return false;
        }
        if (in_array($extension, ['jpg', 'jpeg', 'png'], true) && @getimagesize($file['tmp_name']) === false) {
            $this->errors[] = 'Invalid image file';
            return false;
        }

        if (!preg_match('/^[a-z0-9_]+$/', $category)) {
            $this->errors[] = 'Invalid upload category';
            return false;
        }

        $relativeDir = 'tenant_' . (int) $tenantId . '/' . $category;
        $dir = $this->baseDir . '/' . $relativeDir;
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            $this->errors[] = 'Could not create upload directory';
            return false;
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        if (!$this->moveFile($file['tmp_name'], $dir . '/' . $filename)) {
            $this->errors[] = 'Failed to save uploaded file';
            return false;
        }

        $this->lastSize = (int) $file['size'];
        return $relativeDir . '/' . $filename;
    }

    /** move_uploaded_file in web requests; rename() for CLI tests. */
    protected function moveFile($from, $to) {
        if (is_uploaded_file($from)) {
            return move_uploaded_file($from, $to);
        }
        return PHP_SAPI === 'cli' && rename($from, $to);
    }

    public function getLastSize() {
        return $this->lastSize;
    }

    public function getErrors() {
        return $this->errors;
    }

    /**
     * Resolve a stored relative path to an absolute path, refusing anything
     * outside /storage/uploads (directory traversal protection).
     */
    public static function resolve($relativePath) {
        if (!is_string($relativePath) || $relativePath === '' || strpos($relativePath, "\0") !== false) {
            return null;
        }
        $base = realpath(storage_path('uploads'));
        $full = realpath(storage_path('uploads') . '/' . ltrim($relativePath, '/'));
        if ($base === false || $full === false || strpos($full, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($full)) {
            return null;
        }
        return $full;
    }

    public function delete($relativePath) {
        $full = self::resolve($relativePath);
        return $full !== null && unlink($full);
    }

    /** Total bytes stored for a tenant. */
    public static function tenantUsageBytes($tenantId) {
        $dir = storage_path('uploads') . '/tenant_' . (int) $tenantId;
        if (!is_dir($dir)) {
            return 0;
        }
        $bytes = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $bytes += $file->getSize();
        }
        return $bytes;
    }

    private function uploadErrorMessage($code) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds the server upload limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds the form upload limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload'
        ];
        return isset($messages[$code]) ? $messages[$code] : 'Unknown upload error';
    }
}
