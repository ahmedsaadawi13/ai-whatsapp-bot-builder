<?php
// FILE: /app/controllers/FileController.php

/**
 * File Controller - serves files from /storage/uploads (outside the web root).
 *
 * GET /files?path=tenant_1/media/abc.png
 *  - "media" files are public (WhatsApp providers must be able to fetch them;
 *    names are 128-bit random so they cannot be guessed).
 *  - everything else requires a logged-in user of the owning tenant.
 */
class FileController extends Controller {

    const TYPES = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf',
        'txt' => 'text/plain; charset=utf-8', 'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    public function serve() {
        $path = (string) $this->request->get('path', '');
        if (!preg_match('#^tenant_(\d+)/([a-z_]+)/[a-f0-9]{32}\.([a-z]+)$#', $path, $m)) {
            $this->abort(404, 'File not found');
        }
        list(, $ownerTenant, $category, $extension) = $m;

        if ($category !== 'media') {
            $this->requireAuth();
            if (!$this->auth->isPlatformAdmin() && $this->getTenantId() !== (int) $ownerTenant) {
                $this->abort(404, 'File not found');
            }
        }

        $full = FileUpload::resolve($path);
        if ($full === null || !isset(self::TYPES[$extension])) {
            $this->abort(404, 'File not found');
        }

        $inline = in_array($extension, ['jpg', 'jpeg', 'png', 'pdf'], true);
        header('Content-Type: ' . self::TYPES[$extension]);
        header('Content-Length: ' . filesize($full));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . basename($full) . '"');
        header('Cache-Control: private, max-age=86400');
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");
        readfile($full);
        exit;
    }
}
