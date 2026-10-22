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

namespace local_learningprism\local\mod;

defined('MOODLE_INTERNAL') || die();

/**
 * Convert an Articulate Rise SCORM runtime-data.js payload to semantic HTML.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rise_extractor {
    /** Rise default body used on heading-only blocks. */
    private const PLACEHOLDER_BODY = 'When we show up to the present moment with all of our senses';

    /**
     * Whether the file looks like a Rise LMS export runtime payload.
     *
     * @param string $js
     * @return bool
     */
    public static function is_runtime_data(string $js): bool {
        return self::decode($js) !== null;
    }

    /**
     * Decode `__jsonp("runtime-data.js","base64…")` into an array.
     *
     * @param string $js
     * @return array|null
     */
    public static function decode(string $js): ?array {
        if (!preg_match('/__jsonp\(\s*"[^"]+"\s*,\s*"([A-Za-z0-9+\/=]+)"\s*\)/', $js, $matches)) {
            return null;
        }
        $json = base64_decode($matches[1], true);
        if ($json === false || $json === '') {
            return null;
        }
        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['course']) || !is_array($data['course'])) {
            return null;
        }
        return $data;
    }

    /**
     * Build body HTML (no document wrapper) from decoded Rise data.
     *
     * @param array $data
     * @param callable $resolvemedia function(array $imageref): string data URI or ''
     * @return string
     */
    public static function to_html(array $data, callable $resolvemedia): string {
        $course = $data['course'] ?? [];
        $html = '';

        $description = self::clean_fragment((string) ($course['description'] ?? ''));
        if ($description !== '' && !self::is_placeholder($description)) {
            $html .= '<div class="rise-description">' . $description . '</div>';
        }

        $lessons = $course['lessons'] ?? [];
        if (!is_array($lessons)) {
            return $html;
        }
        foreach ($lessons as $lesson) {
            if (!is_array($lesson)) {
                continue;
            }
            $title = trim((string) ($lesson['title'] ?? ''));
            if ($title !== '') {
                $html .= '<h2>' . self::esc($title) . '</h2>';
            }
            foreach ($lesson['items'] ?? [] as $block) {
                if (is_array($block)) {
                    $html .= self::render_block($block, $resolvemedia);
                }
            }
        }
        return $html;
    }

    /**
     * Candidate filenames for a Rise media object (crushedKey / originalUrl / key).
     *
     * @param array $ref
     * @return string[]
     */
    public static function media_filenames(array $ref): array {
        $raw = [];
        foreach (['crushedKey', 'originalUrl', 'filename', 'key', 'src'] as $field) {
            if (!empty($ref[$field]) && is_string($ref[$field])) {
                $raw[] = $ref[$field];
            }
        }
        $names = [];
        foreach ($raw as $value) {
            $value = str_replace('\\', '/', $value);
            $base = basename($value);
            foreach ([$base, rawurldecode($base), rawurldecode(rawurldecode($base))] as $candidate) {
                $candidate = trim((string) $candidate);
                if ($candidate !== '' && $candidate !== '/' && $candidate !== '.') {
                    $names[$candidate] = true;
                }
            }
        }
        return array_keys($names);
    }

    /**
     * @param array $block
     * @param callable $resolvemedia
     * @return string
     */
    private static function render_block(array $block, callable $resolvemedia): string {
        $type = (string) ($block['type'] ?? '');
        $variant = strtolower((string) ($block['variant'] ?? ''));
        if ($type === 'link') {
            return '';
        }
        if ($type === 'divider') {
            return ($variant === 'spacing divider') ? '' : '<hr/>';
        }
        if ($type === 'text') {
            return self::render_text($block);
        }
        if ($type === 'list') {
            return self::render_list($block);
        }
        if ($type === 'quote') {
            return self::render_quote($block);
        }
        if ($type === 'image' || $type === 'fullimage') {
            return self::render_image_block($block, $resolvemedia);
        }
        if ($type === 'multimedia') {
            return self::render_multimedia($block, $resolvemedia);
        }
        if ($type === 'interactive') {
            return self::render_interactive($block, $resolvemedia);
        }
        return self::render_generic($block, $resolvemedia);
    }

    /**
     * @param array $block
     * @return string
     */
    private static function render_text(array $block): string {
        $variant = strtolower((string) ($block['variant'] ?? 'paragraph'));
        $out = '';
        $family = strtolower((string) ($block['family'] ?? ''));
        $wrapperstart = '';
        $wrapperend = '';
        if ($family === 'impact' || $variant === 'note') {
            $wrapperstart = '<aside class="rise-note">';
            $wrapperend = '</aside>';
        }
        foreach ($block['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $heading = self::heading_html((string) ($item['heading'] ?? ''), 3);
            $para = self::clean_fragment((string) ($item['paragraph'] ?? ''));
            if ($para !== '' && self::is_placeholder($para)) {
                $para = '';
            }
            if ($variant === 'heading' && $heading === '' && $para !== '') {
                $heading = self::heading_html($para, 3);
                $para = '';
            }
            $out .= $heading . $para;
        }
        if ($out === '') {
            return '';
        }
        return $wrapperstart . $out . $wrapperend;
    }

    /**
     * @param array $block
     * @return string
     */
    private static function render_list(array $block): string {
        $variant = strtolower((string) ($block['variant'] ?? 'bulleted'));
        $tag = ($variant === 'numbered') ? 'ol' : 'ul';
        $items = '';
        foreach ($block['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $li = self::clean_fragment((string) ($item['paragraph'] ?? $item['description'] ?? ''));
            if ($li === '' || self::is_placeholder($li)) {
                continue;
            }
            $items .= '<li>' . $li . '</li>';
        }
        return $items === '' ? '' : '<' . $tag . '>' . $items . '</' . $tag . '>';
    }

    /**
     * @param array $block
     * @return string
     */
    private static function render_quote(array $block): string {
        $out = '';
        foreach ($block['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $para = self::clean_fragment((string) ($item['paragraph'] ?? ''));
            if ($para === '' || self::is_placeholder($para)) {
                continue;
            }
            $cite = self::plain_text((string) ($item['name'] ?? ''));
            $out .= '<blockquote>' . $para;
            if ($cite !== '') {
                $out .= '<footer>' . self::esc($cite) . '</footer>';
            }
            $out .= '</blockquote>';
        }
        return $out;
    }

    /**
     * @param array $block
     * @param callable $resolvemedia
     * @return string
     */
    private static function render_image_block(array $block, callable $resolvemedia): string {
        $out = '';
        foreach ($block['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $out .= self::figure_from_media($item['media']['image'] ?? null, $resolvemedia,
                (string) ($item['caption'] ?? ''));
            $overlay = self::clean_fragment((string) ($item['paragraph'] ?? ''));
            if ($overlay !== '' && !self::is_placeholder($overlay)) {
                $out .= $overlay;
            }
        }
        if ($out === '' && !empty($block['media']['image']) && is_array($block['media']['image'])) {
            $out .= self::figure_from_media($block['media']['image'], $resolvemedia, '');
        }
        return $out;
    }

    /**
     * @param array $block
     * @param callable $resolvemedia
     * @return string
     */
    private static function render_multimedia(array $block, callable $resolvemedia): string {
        $out = '';
        foreach ($block['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $media = $item['media'] ?? [];
            if (!empty($media['image']) && is_array($media['image'])) {
                $out .= self::figure_from_media($media['image'], $resolvemedia, '');
            }
            if (!empty($media['attachment']) && is_array($media['attachment'])) {
                $out .= self::attachment_html($media['attachment']);
            }
        }
        return $out;
    }

    /**
     * @param array $block
     * @param callable $resolvemedia
     * @return string
     */
    private static function render_interactive(array $block, callable $resolvemedia): string {
        $variant = strtolower((string) ($block['variant'] ?? ''));
        $out = '';
        if (!empty($block['media']['image']) && is_array($block['media']['image'])) {
            $out .= self::figure_from_media($block['media']['image'], $resolvemedia, '');
        }
        if ($variant === 'flashcard') {
            foreach ($block['items'] ?? [] as $card) {
                if (!is_array($card)) {
                    continue;
                }
                $out .= '<section class="rise-flashcard">';
                $out .= self::render_card_face($card['front'] ?? [], $resolvemedia, 'Front');
                $out .= self::render_card_face($card['back'] ?? [], $resolvemedia, 'Back');
                $out .= '</section>';
            }
            return $out;
        }
        if ($variant === 'button') {
            foreach ($block['items'] ?? [] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $label = trim((string) ($item['label'] ?? ''));
                $desc = self::clean_fragment((string) ($item['description'] ?? ''));
                $dest = trim((string) ($item['destination'] ?? ''));
                if ($desc !== '' && self::is_placeholder($desc)) {
                    $desc = '';
                }
                $isurl = (bool) preg_match('#^https?://#i', $dest);
                if ($label === '' && $desc === '' && !$isurl) {
                    continue;
                }
                $out .= '<div class="rise-button">';
                if ($label !== '') {
                    $out .= '<p><strong>' . self::esc($label) . '</strong></p>';
                }
                $out .= $desc;
                if ($isurl) {
                    $out .= '<p><a href="' . self::esc($dest) . '">' . self::esc($dest) . '</a></p>';
                }
                $out .= '</div>';
            }
            return $out;
        }
        if ($variant === 'process') {
            $items = '';
            foreach ($block['items'] ?? [] as $item) {
                if (!is_array($item) || !empty($item['isHidden'])) {
                    continue;
                }
                $title = trim((string) ($item['title'] ?? ''));
                $desc = self::clean_fragment((string) ($item['description'] ?? ''));
                if ($title === '' && ($desc === '' || self::is_placeholder($desc))) {
                    continue;
                }
                $items .= '<li>';
                if ($title !== '') {
                    $items .= '<h3>' . self::esc($title) . '</h3>';
                }
                if ($desc !== '' && !self::is_placeholder($desc)) {
                    $items .= $desc;
                }
                $items .= '</li>';
            }
            return $out . ($items === '' ? '' : '<ol class="rise-process">' . $items . '</ol>');
        }
        // Accordion, labeled graphic, tabs, and similar: title + description items.
        foreach ($block['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $title = trim((string) ($item['title'] ?? $item['label'] ?? ''));
            $desc = self::clean_fragment((string) ($item['description'] ?? $item['paragraph'] ?? ''));
            if ($desc !== '' && self::is_placeholder($desc)) {
                $desc = '';
            }
            if ($title === '' && $desc === '') {
                continue;
            }
            $out .= '<section>';
            if ($title !== '') {
                $out .= '<h3>' . self::esc($title) . '</h3>';
            }
            $out .= $desc;
            $out .= '</section>';
        }
        if ($out === '') {
            return self::render_generic($block, $resolvemedia);
        }
        return $out;
    }

    /**
     * @param mixed $face
     * @param callable $resolvemedia
     * @param string $fallbacklabel
     * @return string
     */
    private static function render_card_face($face, callable $resolvemedia, string $fallbacklabel): string {
        if (!is_array($face)) {
            return '';
        }
        $html = '';
        if (!empty($face['media']['image']) && is_array($face['media']['image'])) {
            $html .= self::figure_from_media($face['media']['image'], $resolvemedia, '');
        }
        $desc = self::clean_fragment((string) ($face['description'] ?? ''));
        if ($desc !== '' && !self::is_placeholder($desc)) {
            $html .= $desc;
        }
        if ($html === '') {
            return '';
        }
        return '<div><h3>' . self::esc($fallbacklabel) . '</h3>' . $html . '</div>';
    }

    /**
     * @param array $block
     * @param callable $resolvemedia
     * @return string
     */
    private static function render_generic(array $block, callable $resolvemedia): string {
        $out = '';
        if (!empty($block['media']['image']) && is_array($block['media']['image'])) {
            $out .= self::figure_from_media($block['media']['image'], $resolvemedia, '');
        }
        $title = trim((string) ($block['title'] ?? $block['label'] ?? ''));
        if ($title !== '') {
            $out .= '<h3>' . self::esc($title) . '</h3>';
        }
        foreach (['heading', 'paragraph', 'description', 'caption'] as $field) {
            if (empty($block[$field]) || !is_string($block[$field])) {
                continue;
            }
            $frag = ($field === 'heading')
                ? self::heading_html($block[$field], 3)
                : self::clean_fragment($block[$field]);
            if ($frag !== '' && !self::is_placeholder($frag)) {
                $out .= $frag;
            }
        }
        foreach ($block['items'] ?? [] as $item) {
            if (is_array($item)) {
                $out .= self::render_generic($item, $resolvemedia);
            }
        }
        return $out;
    }

    /**
     * @param array|null $image
     * @param callable $resolvemedia
     * @param string $captionhtml
     * @return string
     */
    private static function figure_from_media($image, callable $resolvemedia, string $captionhtml): string {
        if (!is_array($image)) {
            return '';
        }
        // Decorative Rise defaults that are not lesson content.
        $srcname = strtolower((string) ($image['originalUrl'] ?? $image['src'] ?? ''));
        $decorative = in_array($srcname, ['quote_background.jpg', 'mountains.jpg'], true)
            && (($image['sourcedFrom'] ?? '') === 'DEFAULT');
        $uri = '';
        $alt = $decorative ? '' : trim((string) ($image['alt'] ?? ''));
        if (!$decorative) {
            $uri = (string) $resolvemedia($image);
        }
        $caption = self::clean_fragment($captionhtml);
        if ($caption !== '' && self::is_placeholder($caption)) {
            $caption = '';
        }
        if ($uri === '' && $alt === '' && $caption === '') {
            return '';
        }
        $html = '<figure>';
        if ($uri !== '') {
            $html .= '<img src="' . self::esc($uri) . '" alt="' . self::esc($alt) . '"/>';
        }
        if ($caption !== '') {
            $html .= '<figcaption>' . $caption . '</figcaption>';
        } else if ($alt !== '' && $uri === '') {
            $html .= '<figcaption>' . self::esc($alt) . '</figcaption>';
        }
        $html .= '</figure>';
        return $html;
    }

    /**
     * @param array $attachment
     * @return string
     */
    private static function attachment_html(array $attachment): string {
        $name = (string) ($attachment['originalUrl'] ?? '');
        if ($name === '') {
            $name = (string) ($attachment['filename'] ?? $attachment['key'] ?? 'attachment');
            $name = rawurldecode(rawurldecode(basename(str_replace('\\', '/', $name))));
        }
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        return '<p>' . self::esc($name) . '</p>';
    }

    /**
     * @param string $html
     * @param int $level
     * @return string
     */
    private static function heading_html(string $html, int $level): string {
        $inner = self::unwrap_editor($html);
        $text = self::plain_text($inner);
        if ($text === '' || self::is_placeholder($inner)) {
            return '';
        }
        $level = max(2, min(6, $level));
        $inner = preg_replace('#^<p\b[^>]*>#i', '', $inner) ?? $inner;
        $inner = preg_replace('#</p>$#i', '', $inner) ?? $inner;
        return '<h' . $level . '>' . $inner . '</h' . $level . '>';
    }

    /**
     * @param string $html
     * @return string
     */
    private static function clean_fragment(string $html): string {
        $html = self::unwrap_editor($html);
        if ($html === '' || self::is_placeholder($html)) {
            return '';
        }
        return $html;
    }

    /**
     * Strip Rise editor wrappers / empty markup.
     *
     * @param string $html
     * @return string
     */
    private static function unwrap_editor(string $html): string {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        $html = preg_replace('/\sdata-editor-id="[^"]*"/i', '', $html) ?? $html;
        // Repeated Rise wrapping <div> around a single child.
        for ($i = 0; $i < 6; $i++) {
            $trimmed = preg_replace('#^<div\s*>\s*(.*)\s*</div>$#is', '$1', $html);
            if ($trimmed === null || $trimmed === $html) {
                break;
            }
            $html = trim($trimmed);
        }
        if (self::plain_text($html) === '' && !preg_match('/<(img|ul|ol|table|li)\b/i', $html)) {
            return '';
        }
        return $html;
    }

    /**
     * @param string $html
     * @return bool
     */
    private static function is_placeholder(string $html): bool {
        $text = self::plain_text($html);
        if ($text === '') {
            return true;
        }
        if (strpos($text, self::PLACEHOLDER_BODY) !== false) {
            return true;
        }
        return (bool) preg_match('/^Front of card(\s+\d+)?$/i', $text);
    }

    /**
     * @param string $html
     * @return string
     */
    private static function plain_text(string $html): string {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    /**
     * @param string $text
     * @return string
     */
    private static function esc(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
