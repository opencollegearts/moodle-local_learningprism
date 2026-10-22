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

namespace local_learningprism;

defined('MOODLE_INTERNAL') || die();

/**
 * Accessibility-oriented HTML normalisation for conversions.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class html_normalizer {
    /**
     * Build a clean HTML document from activity title + body HTML.
     *
     * @param string $title
     * @param string $bodyhtml
     * @param string $lang
     * @return string
     */
    public static function normalise(string $title, string $bodyhtml, string $lang = 'en'): string {
        $title = trim(html_to_text($title, 0, false));
        $bodyhtml = self::strip_unsafe($bodyhtml);
        $bodyhtml = self::normalise_headings($bodyhtml);

        $lang = preg_replace('/[^a-zA-Z0-9\-]/', '', $lang) ?: 'en';

        $html = '<!DOCTYPE html><html lang="' . s($lang) . '"><head><meta charset="utf-8"/>';
        $html .= '<title>' . s($title) . '</title>';
        $html .= '<style>' . self::print_css() . '</style>';
        $html .= '</head><body>';
        $html .= '<h1>' . s($title) . '</h1>';
        $html .= '<main>' . $bodyhtml . '</main>';
        $html .= '</body></html>';

        return $html;
    }

    /**
     * Content hash for cache/dedupe.
     *
     * @param string $html
     * @return string
     */
    public static function content_hash(string $html): string {
        return sha1($html);
    }

    /**
     * Plain text / light SSML-friendly text for TTS.
     *
     * @param string $html
     * @return string
     */
    public static function to_speech_text(string $html): string {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $parts = [];
        self::walk_for_speech($dom->documentElement, $parts);
        $text = trim(preg_replace("/[ \t]+/", ' ', implode(' ', $parts)));
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return $text;
    }

    /**
     * @param \DOMNode|null $node
     * @param string[] $parts
     */
    private static function walk_for_speech(?\DOMNode $node, array &$parts): void {
        if (!$node) {
            return;
        }
        if ($node->nodeType === XML_TEXT_NODE) {
            $t = trim(preg_replace('/\s+/', ' ', $node->nodeValue ?? ''));
            if ($t !== '') {
                $parts[] = $t;
            }
            return;
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return;
        }
        $name = strtolower($node->nodeName);
        if (in_array($name, ['script', 'style', 'noscript'], true)) {
            return;
        }
        if (in_array($name, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'li', 'tr', 'br', 'div', 'section'], true)) {
            $parts[] = "\n";
        }
        foreach ($node->childNodes as $child) {
            self::walk_for_speech($child, $parts);
        }
        if (in_array($name, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'li'], true)) {
            $parts[] = ".\n";
        }
    }

    /**
     * Remove scripts and event handlers.
     *
     * @param string $html
     * @return string
     */
    private static function strip_unsafe(string $html): string {
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? $html;
        $html = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $html) ?? $html;
        $html = preg_replace('/\son\w+\s*=\s*(["\']).*?\1/i', '', $html) ?? $html;
        $html = preg_replace('/javascript\s*:/i', '', $html) ?? $html;
        return $html;
    }

    /**
     * Ensure heading levels do not skip more than one level from the document h1.
     *
     * @param string $html
     * @return string
     */
    private static function normalise_headings(string $html): string {
        if (!preg_match_all('/<h([1-6])\b/i', $html, $matches)) {
            return $html;
        }
        $levels = array_map('intval', $matches[1]);
        $min = min($levels);
        // Body content headings should start at h2 (h1 is the activity title).
        $shift = 2 - $min;
        if ($shift === 0) {
            return $html;
        }
        return preg_replace_callback('/<\/?h([1-6])\b/i', static function ($m) use ($shift) {
            $level = (int) $m[1] + $shift;
            $level = max(2, min(6, $level));
            return str_replace($m[1], (string) $level, $m[0]);
        }, $html) ?? $html;
    }

    /**
     * Print-background colour presets (light overlays; body text stays dark).
     *
     * @return array<string,string> key => #RRGGBB
     */
    public static function print_background_presets(): array {
        return [
            'white' => '#FFFFFF',
            'cream' => '#FBF3D5',
            'yellow' => '#FFF8C5',
            'blue' => '#E8F4FC',
            'green' => '#EAF7EA',
            'pink' => '#FDECEC',
        ];
    }

    /**
     * Configured print background as #RRGGBB.
     *
     * @param string|null $key Preset key, or null for the site default
     * @return string
     */
    public static function print_background_hex(?string $key = null): string {
        $presets = self::print_background_presets();
        if ($key === null || $key === '' || !isset($presets[$key])) {
            $key = (string) (get_config('local_learningprism', 'printbackground') ?: 'white');
        }
        return $presets[$key] ?? '#FFFFFF';
    }

    /**
     * Dyslexia-friendly font presets (CSS stack + Word font name).
     *
     * @return array<string,array{css:string,docx:string}>
     */
    public static function print_font_presets(): array {
        return [
            'default' => ['css' => 'Georgia, "Times New Roman", serif', 'docx' => 'Georgia'],
            'arial' => ['css' => 'Arial, Helvetica, sans-serif', 'docx' => 'Arial'],
            'verdana' => ['css' => 'Verdana, Geneva, sans-serif', 'docx' => 'Verdana'],
            'tahoma' => ['css' => 'Tahoma, Geneva, sans-serif', 'docx' => 'Tahoma'],
            'trebuchet' => ['css' => '"Trebuchet MS", Helvetica, sans-serif', 'docx' => 'Trebuchet MS'],
            'centurygothic' => ['css' => '"Century Gothic", CenturyGothic, AppleGothic, sans-serif', 'docx' => 'Century Gothic'],
            'comicsans' => ['css' => '"Comic Sans MS", "Comic Sans", cursive, sans-serif', 'docx' => 'Comic Sans MS'],
            'opendyslexic' => ['css' => 'OpenDyslexic, "Comic Sans MS", Arial, sans-serif', 'docx' => 'OpenDyslexic'],
            'atkinson' => ['css' => '"Atkinson Hyperlegible", Arial, Helvetica, sans-serif', 'docx' => 'Atkinson Hyperlegible'],
            'lexend' => ['css' => 'Lexend, Arial, Helvetica, sans-serif', 'docx' => 'Lexend'],
        ];
    }

    /**
     * CSS font-family stack for a preset (site default font when null/unknown).
     *
     * @param string|null $key
     * @return string
     */
    public static function print_font_css(?string $key = null): string {
        $presets = self::print_font_presets();
        if ($key === null || $key === '' || !isset($presets[$key])) {
            $key = 'default';
        }
        return $presets[$key]['css'];
    }

    /**
     * Word rFonts name for a preset.
     *
     * @param string|null $key
     * @return string
     */
    public static function print_font_docx(?string $key = null): string {
        $presets = self::print_font_presets();
        if ($key === null || $key === '' || !isset($presets[$key])) {
            $key = 'default';
        }
        return $presets[$key]['docx'];
    }

    /**
     * Normalise print background + font keys.
     *
     * @param array $options
     * @return array{printbackground:string,font:string}
     */
    public static function normalise_print_options(array $options): array {
        $bg = (string) ($options['printbackground'] ?? '');
        $font = (string) ($options['font'] ?? '');
        $bgs = self::print_background_presets();
        $fonts = self::print_font_presets();
        if ($bg === '' || !isset($bgs[$bg])) {
            $bg = (string) (get_config('local_learningprism', 'printbackground') ?: 'white');
            if (!isset($bgs[$bg])) {
                $bg = 'white';
            }
        }
        if ($font === '' || !isset($fonts[$font])) {
            $font = 'default';
        }
        return ['printbackground' => $bg, 'font' => $font];
    }

    /**
     * Stable hash of print options for personal job identity.
     *
     * @param array $options
     * @return string
     */
    public static function print_options_hash(array $options): string {
        $options = self::normalise_print_options($options);
        return sha1($options['printbackground'] . '|' . $options['font']);
    }

    /**
     * Current user's saved print options (falls back to site colour + default font).
     *
     * @param int $userid
     * @return array{printbackground:string,font:string}
     */
    public static function user_print_options(int $userid = 0): array {
        global $USER;
        if ($userid <= 0) {
            $userid = (int) ($USER->id ?? 0);
        }
        $bg = '';
        $font = 'default';
        if ($userid > 0) {
            $bg = (string) get_user_preferences('local_learningprism_printbackground', '', $userid);
            $font = (string) get_user_preferences('local_learningprism_font', 'default', $userid);
        }
        return self::normalise_print_options(['printbackground' => $bg, 'font' => $font]);
    }

    /**
     * Persist print options as user preferences.
     *
     * @param int $userid
     * @param array $options
     * @return array{printbackground:string,font:string}
     */
    public static function save_user_print_options(int $userid, array $options): array {
        $options = self::normalise_print_options($options);
        if ($userid > 0) {
            set_user_preference('local_learningprism_printbackground', $options['printbackground'], $userid);
            set_user_preference('local_learningprism_font', $options['font'], $userid);
        }
        return $options;
    }

    /**
     * Replace the document print stylesheet using the given options.
     *
     * @param string $html
     * @param array $options
     * @return string
     */
    public static function apply_print_style(string $html, array $options): string {
        $options = self::normalise_print_options($options);
        $css = self::print_css($options['printbackground'], $options['font']);
        if (preg_match('/<style\b[^>]*>.*?<\/style>/is', $html)) {
            return preg_replace('/<style\b[^>]*>.*?<\/style>/is', '<style>' . $css . '</style>', $html, 1) ?? $html;
        }
        return $html;
    }

    /**
     * Print stylesheet for PDF/EPUB-oriented rendering.
     *
     * @param string|null $backgroundkey
     * @param string|null $fontkey
     * @return string
     */
    public static function print_css(?string $backgroundkey = null, ?string $fontkey = null): string {
        $bg = self::print_background_hex($backgroundkey);
        $font = self::print_font_css($fontkey);
        return <<<CSS
@page { background-color: {$bg}; }
body { font-family: {$font}; font-size: 12pt; line-height: 1.5; color: #111; background-color: {$bg}; margin: 2cm; }
h1 { font-size: 20pt; margin: 0 0 1em; }
h2 { font-size: 16pt; margin: 1.2em 0 0.5em; }
h3 { font-size: 14pt; margin: 1em 0 0.4em; }
h4,h5,h6 { font-size: 12pt; margin: 0.8em 0 0.3em; }
p,li { margin: 0.4em 0; }
table { border-collapse: collapse; width: 100%; margin: 1em 0; }
th,td { border: 1px solid #444; padding: 0.35em 0.5em; text-align: left; }
img { max-width: 100%; height: auto; }
CSS;
    }
}
