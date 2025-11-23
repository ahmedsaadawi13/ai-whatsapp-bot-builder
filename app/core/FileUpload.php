<?php
// FILE: /app/core/FileUpload.php

/**
 * File Upload Handler
 *
 * Handles file uploads with validation and security measures.
 */
class FileUpload {

    private $uploadPath;
    private $allowedTypes;
    private $maxSize;
    private $errors = [];

    public function __construct($uploadPath = '/storage/uploads') {
        $this->uploadPath = __DIR__ . '/../../' . $uploadPath;
        $this->allowedTypes = ['jpg', 'jpeg', 'png', 'pdf', 'txt', 'doc', 'docx'];
        $this->maxSize = 10 * 1024 * 1024; // 10MB default

        // Create upload directory if it doesn't exist
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }

    /**
     * Set allowed file types
     *
     * @param array $types
     */
    public function setAllowedTypes($types) {
        $this->allowedTypes = $types;
    }

    /**
     * Set max file size in bytes
     *
     * @param int $size
     */
    public function setMaxSize($size) {
        $this->maxSize = $size;
    }

    /**
     * Upload file
     *
     * @param array $file $_FILES array element
     * @param string|null $subdirectory Optional subdirectory
     * @return string|false File path on success, false on failure
     */
    public function upload($file, $subdirectory = null) {
        $this->errors = [];

        // Check for upload errors
        if (!isset($file['error']) || is_array($file['error'])) {
            $this->errors[] = 'Invalid file upload';
            return false;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->getUploadErrorMessage($file['error']);
            return false;
        }

        // Validate file size
        if ($file['size'] > $this->maxSize) {
            $this->errors[] = 'File size exceeds maximum allowed size';
            return false;
        }

        // Get file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Validate file type
        if (!in_array($extension, $this->allowedTypes)) {
            $this->errors[] = 'File type not allowed';
            return false;
        }

        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!$this->isValidMimeType($mimeType, $extension)) {
            $this->errors[] = 'Invalid file type';
            return false;
        }

        // Generate unique filename
        $filename = $this->generateFilename($extension);

        // Set upload path
        $uploadDir = $this->uploadPath;
        if ($subdirectory) {
            $uploadDir .= '/' . $subdirectory;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
        }

        $filepath = $uploadDir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            $this->errors[] = 'Failed to move uploaded file';
            return false;
        }

        // Return relative path
        $relativePath = 'storage/uploads';
        if ($subdirectory) {
            $relativePath .= '/' . $subdirectory;
        }
        $relativePath .= '/' . $filename;

        return $relativePath;
    }

    /**
     * Generate unique filename
     *
     * @param string $extension
     * @return string
     */
    private function generateFilename($extension) {
        return uniqid('file_', true) . '_' . time() . '.' . $extension;
    }

    /**
     * Validate MIME type
     *
     * @param string $mimeType
     * @param string $extension
     * @return bool
     */
    private function isValidMimeType($mimeType, $extension) {
        $validMimes = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'pdf' => ['application/pdf'],
            'txt' => ['text/plain'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        ];

        if (!isset($validMimes[$extension])) {
            return false;
        }

        return in_array($mimeType, $validMimes[$extension]);
    }

    /**
     * Get upload error message
     *
     * @param int $errorCode
     * @return string
     */
    private function getUploadErrorMessage($errorCode) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];

        return isset($messages[$errorCode]) ? $messages[$errorCode] : 'Unknown upload error';
    }

    /**
     * Get validation errors
     *
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Delete file
     *
     * @param string $filepath Relative path from project root
     * @return bool
     */
    public function delete($filepath) {
        $fullPath = __DIR__ . '/../../' . $filepath;

        if (file_exists($fullPath) && is_file($fullPath)) {
            return unlink($fullPath);
        }

        return false;
    }
}
