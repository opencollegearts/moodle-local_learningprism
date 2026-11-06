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

defined('MOODLE_INTERNAL') || die();

/**
 * Image helpers shared by DOCX / EPUB converters.
 *
 * Word and many ebook readers do not reliably display SVG. PDF via Gotenberg
 * Chromium does, so SVG is rasterised to PNG before DOCX/EPUB packaging.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class image_helper {
    /**
     * Rewrite SVG (and svg+xml) data-URI images in HTML to PNG data-URIs.
     *
     * @param string $html
     * @return string
     */
    public static function rasterise_svg_data_uris(string $html): string {
        if ($html === '' || stripos($html, 'image/svg') === false) {
            return $html;
        }
        return preg_replace_callback(
            '#(data:image/(?:svg\+xml|svg);base64,)([A-Za-z0-9+/=\s]+)#i',
            static function (array $matches): string {
                $binary = base64_decode(preg_replace('/\s+/', '', $matches[2]) ?? '', true);
                if ($binary === false || $binary === '') {
                    return $matches[0];
                }
                $png = self::svg_to_png($binary);
                if ($png === null || $png === '') {
                    return $matches[0];
                }
                return 'data:image/png;base64,' . base64_encode($png);
            },
            $html
        ) ?? $html;
    }

    /**
     * Convert SVG markup to a PNG binary, or null when no rasteriser is available.
     *
     * @param string $svg
     * @return string|null
     */
    public static function svg_to_png(string $svg): ?string {
        $svg = trim($svg);
        if ($svg === '') {
            return null;
        }
        // Some Rise assets omit the XML declaration; Imagick/Chromium still accept them.
        if (stripos($svg, '<svg') === false) {
            return null;
        }

        $png = self::svg_to_png_imagick($svg);
        if ($png !== null) {
            return $png;
        }
        $png = self::svg_to_png_gotenberg($svg);
        if ($png !== null) {
            return $png;
        }
        return self::svg_to_png_cli($svg);
    }

    /**
     * @param string $svg
     * @return string|null
     */
    private static function svg_to_png_imagick(string $svg): ?string {
        if (!class_exists(\Imagick::class)) {
            return null;
        }
        try {
            $image = new \Imagick();
            $image->setBackgroundColor(new \ImagickPixel('transparent'));
            $image->setResolution(144, 144);
            $image->readImageBlob($svg);
            $image->setImageFormat('png');
            $png = $image->getImageBlob();
            $image->clear();
            $image->destroy();
            return ($png !== '' && self::is_png($png)) ? $png : null;
        } catch (\Throwable $unused) {
            return null;
        }
    }

    /**
     * Rasterise via Gotenberg Chromium screenshot when a PDF engine URL is configured.
     *
     * @param string $svg
     * @return string|null
     */
    private static function svg_to_png_gotenberg(string $svg): ?string {
        $base = rtrim((string) get_config('local_learningprism', 'pdfengineurl'), '/');
        if ($base === '' || !preg_match('#^https?://#i', $base)) {
            return null;
        }
        [$width, $height] = self::svg_dimensions($svg);
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"/><style>'
            . 'html,body{margin:0;padding:0;background:transparent;}'
            . 'svg,img{display:block;width:' . $width . 'px;height:' . $height . 'px;}'
            . '</style></head><body>' . $svg . '</body></html>';

        $tmpdir = make_request_directory();
        $htmlfile = $tmpdir . '/svg.html';
        if (file_put_contents($htmlfile, $html) === false) {
            return null;
        }

        try {
            $curl = new \curl();
            $response = $curl->post($base . '/forms/chromium/screenshot/html', [
                'files' => new \CURLFile($htmlfile, 'text/html', 'index.html'),
                'format' => 'png',
                'width' => (string) $width,
                'height' => (string) $height,
                'omitBackground' => 'true',
            ]);
            if ($curl->get_errno() || !is_string($response) || !self::is_png($response)) {
                return null;
            }
            return $response;
        } catch (\Throwable $unused) {
            return null;
        }
    }

    /**
     * Optional local CLI fallbacks (rsvg-convert / ImageMagick convert).
     *
     * @param string $svg
     * @return string|null
     */
    private static function svg_to_png_cli(string $svg): ?string {
        $bins = [];
        foreach (['rsvg-convert', 'convert', 'magick'] as $name) {
            $path = self::find_executable($name);
            if ($path !== '') {
                $bins[$name] = $path;
            }
        }
        if ($bins === []) {
            return null;
        }

        $tmpdir = make_request_directory();
        $svgfile = $tmpdir . '/in.svg';
        $pngfile = $tmpdir . '/out.png';
        if (file_put_contents($svgfile, $svg) === false) {
            return null;
        }

        $commands = [];
        if (!empty($bins['rsvg-convert'])) {
            $commands[] = [$bins['rsvg-convert'], '-o', $pngfile, $svgfile];
        }
        if (!empty($bins['magick'])) {
            $commands[] = [$bins['magick'], 'convert', $svgfile, $pngfile];
        }
        if (!empty($bins['convert'])) {
            $commands[] = [$bins['convert'], $svgfile, $pngfile];
        }

        foreach ($commands as $cmd) {
            $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $process = proc_open($cmd, $descriptors, $pipes, $tmpdir, null);
            if (!is_resource($process)) {
                continue;
            }
            fclose($pipes[0]);
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            if (is_readable($pngfile)) {
                $png = file_get_contents($pngfile);
                if (is_string($png) && self::is_png($png)) {
                    return $png;
                }
            }
        }
        return null;
    }

    /**
     * @param string $svg
     * @return array{0:int,1:int}
     */
    public static function svg_dimensions(string $svg): array {
        $width = 800;
        $height = 800;
        if (preg_match('/viewBox\s*=\s*["\']\s*([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)\s*["\']/i', $svg, $m)) {
            $width = max(1, (int) round((float) $m[3]));
            $height = max(1, (int) round((float) $m[4]));
        } else if (preg_match('/\bwidth\s*=\s*["\']\s*([\d.]+)/i', $svg, $m)
            && preg_match('/\bheight\s*=\s*["\']\s*([\d.]+)/i', $svg, $h)) {
            $width = max(1, (int) round((float) $m[1]));
            $height = max(1, (int) round((float) $h[1]));
        }
        // Cap extreme sizes so screenshot requests stay reasonable.
        $max = 2000;
        if ($width > $max || $height > $max) {
            $scale = $max / max($width, $height);
            $width = max(1, (int) round($width * $scale));
            $height = max(1, (int) round($height * $scale));
        }
        return [$width, $height];
    }

    /**
     * @param string $binary
     * @return bool
     */
    private static function is_png(string $binary): bool {
        return strncmp($binary, "\x89PNG\r\n\x1a\n", 8) === 0;
    }

    /**
     * @param string $name
     * @return string
     */
    private static function find_executable(string $name): string {
        $name = basename($name);
        if ($name === '' || $name === '.' || $name === '..') {
            return '';
        }
        foreach (['/opt/homebrew/bin', '/usr/local/bin', '/usr/bin'] as $dir) {
            $path = $dir . '/' . $name;
            if (is_executable($path)) {
                return $path;
            }
        }
        return '';
    }
}
