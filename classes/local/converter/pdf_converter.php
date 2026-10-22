<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_learningprism\local\converter;

use local_learningprism\filemanager;
use local_learningprism\manager;
use stored_file;

defined('MOODLE_INTERNAL') || die();

/**
 * HTML to PDF via Gotenberg, WeasyPrint HTTP, or LibreOffice.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdf_converter implements converter_interface {
    public function get_format(): string {
        return manager::FORMAT_PDF;
    }

    public function is_configured(): bool {
        $engine = get_config('local_learningprism', 'pdfengine');
        if (!$engine || $engine === 'none') {
            return false;
        }
        if ($engine === 'libreoffice') {
            $path = get_config('local_learningprism', 'libreofficepath');
            return $path && is_executable($path);
        }
        $url = get_config('local_learningprism', 'pdfengineurl');
        return self::is_http_url((string) $url);
    }

    public function convert(string $html, string $title, int $jobid, array $options = []): stored_file {
        $engine = get_config('local_learningprism', 'pdfengine');
        switch ($engine) {
            case 'gotenberg':
                $pdf = $this->via_gotenberg($html);
                break;
            case 'weasyprint':
                $pdf = $this->via_weasyprint($html);
                break;
            case 'libreoffice':
                $pdf = $this->via_libreoffice($html, $title, $jobid, $options);
                break;
            default:
                throw new \moodle_exception('error:pdfnotconfigured', 'local_learningprism');
        }

        $filename = docx_converter::safe_filename($title) . '.pdf';
        return filemanager::store_converted_file($jobid, $filename, 'application/pdf', $pdf);
    }

    /**
     * Whether a configured engine URL is http(s) only.
     *
     * @param string $url
     * @return bool
     */
    private static function is_http_url(string $url): bool {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        return in_array(strtolower($parts['scheme']), ['http', 'https'], true);
    }

    /**
     * Sanitised PDF engine base URL.
     *
     * @return string
     */
    private function engine_base_url(): string {
        $base = rtrim((string) get_config('local_learningprism', 'pdfengineurl'), '/');
        if (!self::is_http_url($base)) {
            throw new \moodle_exception('error:pdfnotconfigured', 'local_learningprism');
        }
        return $base;
    }

    /**
     * Gotenberg Chromium HTML convert endpoint.
     *
     * @param string $html
     * @return string
     */
    private function via_gotenberg(string $html): string {
        $base = $this->engine_base_url();
        $tmpdir = make_request_directory();
        $htmlfile = $tmpdir . '/index.html';
        file_put_contents($htmlfile, $html);

        $curl = new \curl();
        $curl->setHeader([
            'Content-Type: multipart/form-data',
        ]);
        // Moodle curl multipart via CURLOPT_POSTFIELDS array with CURLFile.
        $response = $curl->post($base . '/forms/chromium/convert/html', [
            'files' => new \CURLFile($htmlfile, 'text/html', 'index.html'),
        ]);
        if ($curl->get_errno() || empty($response) || strpos($response, '%PDF') !== 0) {
            throw new \moodle_exception('error:conversionfailed', 'local_learningprism');
        }
        return $response;
    }

    /**
     * Generic WeasyPrint-style HTTP: POST HTML body, receive PDF.
     *
     * @param string $html
     * @return string
     */
    private function via_weasyprint(string $html): string {
        $base = $this->engine_base_url();
        $curl = new \curl();
        $curl->setHeader(['Content-Type: text/html; charset=utf-8']);
        $response = $curl->post($base . '/pdf', $html);
        if ($curl->get_errno() || empty($response) || strpos($response, '%PDF') !== 0) {
            // Try alternate path used by some wrappers.
            $response = $curl->post($base, $html);
        }
        if ($curl->get_errno() || empty($response) || strpos($response, '%PDF') !== 0) {
            throw new \moodle_exception('error:conversionfailed', 'local_learningprism');
        }
        return $response;
    }

    /**
     * Convert via DOCX intermediate + LibreOffice headless.
     *
     * @param string $html
     * @param string $title
     * @param int $jobid
     * @param array $options
     * @return string
     */
    private function via_libreoffice(string $html, string $title, int $jobid, array $options = []): string {
        $docx = new docx_converter();
        $docxbytes = $docx->build_docx($html, $title, $options);

        $tmpdir = make_request_directory();
        $docxfile = $tmpdir . '/source.docx';
        file_put_contents($docxfile, $docxbytes);

        $soffice = get_config('local_learningprism', 'libreofficepath');
        $cmd = escapeshellarg($soffice)
            . ' --headless --nologo --nolockcheck --nodefault --nofirststartwizard'
            . ' --convert-to pdf --outdir ' . escapeshellarg($tmpdir)
            . ' ' . escapeshellarg($docxfile);

        $output = [];
        $exit = 0;
        exec($cmd, $output, $exit);
        $pdffile = $tmpdir . '/source.pdf';
        if ($exit !== 0 || !is_readable($pdffile)) {
            throw new \moodle_exception('error:conversionfailed', 'local_learningprism');
        }
        $pdf = file_get_contents($pdffile);
        if ($pdf === false) {
            throw new \moodle_exception('error:conversionfailed', 'local_learningprism');
        }
        return $pdf;
    }
}
