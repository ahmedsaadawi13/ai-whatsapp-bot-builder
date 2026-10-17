<?php
// FILE: /app/services/DocumentText.php

/**
 * Best-effort plain-text extraction for knowledge base documents.
 *
 * - txt:  read as UTF-8
 * - docx: text of word/document.xml (needs the zip extension)
 * - pdf:  text inside uncompressed or Flate-compressed content streams
 *         (works for most simple text PDFs; scanned PDFs return nothing)
 * - doc:  printable runs of the legacy binary format
 */
class DocumentText {

    const MAX_CHARS = 60000;

    public static function extract($path, $extension) {
        switch (strtolower($extension)) {
            case 'txt':
                $text = (string) file_get_contents($path);
                break;
            case 'docx':
                $text = self::docx($path);
                break;
            case 'pdf':
                $text = self::pdf($path);
                break;
            case 'doc':
                $text = preg_replace('/[^\x20-\x7E\n]+/', ' ', (string) file_get_contents($path));
                break;
            default:
                $text = '';
        }

        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
        }
        $text = trim(preg_replace("/[ \t]+/", ' ', preg_replace("/\r\n?/", "\n", $text)));
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return mb_substr($text, 0, self::MAX_CHARS, 'UTF-8');
    }

    private static function docx($path) {
        if (!class_exists('ZipArchive')) {
            return '';
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        $xml = str_replace(['</w:p>', '<w:br/>', '<w:tab/>'], ["\n", "\n", ' '], $xml);
        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function pdf($path) {
        $data = (string) file_get_contents($path);
        $text = '';
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $data, $streams)) {
            foreach ($streams[1] as $stream) {
                $decoded = @gzuncompress($stream);
                if ($decoded === false) {
                    $decoded = @gzinflate($stream);
                }
                $content = $decoded !== false ? $decoded : $stream;
                // Text-showing operators: (text) Tj  and  [(a) 10 (b)] TJ
                if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)\s*Tj|\[(?:.*?)\]\s*TJ/s', $content, $ops)) {
                    foreach ($ops[0] as $op) {
                        preg_match_all('/\(((?:\\\\.|[^\\\\)])*)\)/s', $op, $parts);
                        $text .= stripcslashes(implode('', $parts[1])) . ' ';
                    }
                    $text .= "\n";
                }
            }
        }
        return $text;
    }
}
