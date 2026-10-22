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
use local_learningprism\html_normalizer;
use local_learningprism\manager;
use stored_file;

defined('MOODLE_INTERNAL') || die();

/**
 * HTML to DOCX converter using OOXML (Heading styles and images preserved).
 *
 * Uses a lightweight ZipArchive writer so the plugin does not require Composer
 * on the Moodle host. Structure maps h1–h6 to Word Heading 1–6 and embeds
 * data-URI images into word/media.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class docx_converter implements converter_interface {
    /** @var array<int,array{rid:string,name:string,data:string,ext:string}> */
    private $media = [];

    /** @var int */
    private $drawingid = 0;

    /** @var string Word font name */
    private $fontname = 'Georgia';

    /** @var string Hex without # */
    private $bghex = 'FFFFFF';

    public function get_format(): string {
        return manager::FORMAT_DOCX;
    }

    public function is_configured(): bool {
        return class_exists(\ZipArchive::class);
    }

    public function convert(string $html, string $title, int $jobid, array $options = []): stored_file {
        $filename = self::safe_filename($title) . '.docx';
        $content = $this->build_docx($html, $title, $options);
        return filemanager::store_converted_file(
            $jobid,
            $filename,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $content
        );
    }

    /**
     * Build DOCX binary from HTML (also used as LibreOffice intermediate).
     *
     * @param string $html
     * @param string $title
     * @param array $options printbackground / font keys
     * @return string binary docx
     */
    public function build_docx(string $html, string $title, array $options = []): string {
        $this->media = [];
        $this->drawingid = 0;
        $options = html_normalizer::normalise_print_options($options);
        $this->fontname = html_normalizer::print_font_docx($options['font']);
        $this->bghex = ltrim(html_normalizer::print_background_hex($options['printbackground']), '#');
        $documentxml = $this->html_to_document_xml($html);
        $tmpdir = make_request_directory();
        $docxpath = $tmpdir . '/output.docx';

        $zip = new \ZipArchive();
        if ($zip->open($docxpath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \moodle_exception('error:conversionfailed', 'local_learningprism');
        }

        $zip->addFromString('[Content_Types].xml', $this->content_types());
        $zip->addFromString('_rels/.rels', $this->rels_root());
        $zip->addFromString('word/_rels/document.xml.rels', $this->rels_document());
        $zip->addFromString('word/document.xml', $documentxml);
        $zip->addFromString('word/styles.xml', $this->styles_xml());
        $zip->addFromString('word/settings.xml', $this->settings_xml());
        $zip->addFromString('docProps/core.xml', $this->core_xml($title));
        foreach ($this->media as $item) {
            $zip->addFromString('word/media/' . $item['name'], $item['data']);
        }
        $zip->close();

        $binary = file_get_contents($docxpath);
        if ($binary === false) {
            throw new \moodle_exception('error:conversionfailed', 'local_learningprism');
        }
        return $binary;
    }

    /**
     * @param string $html
     * @return string
     */
    private function html_to_document_xml(string $html): string {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $dom->getElementsByTagName('body')->item(0);
        $paragraphs = '';
        if ($body) {
            $paragraphs = $this->nodes_to_paragraphs($body);
        }
        if ($paragraphs === '') {
            $paragraphs = $this->p_xml(html_to_text($html, 0, false), 'Normal');
        }

        $bg = $this->bghex;
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            . ' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing">'
            . '<w:background w:color="' . htmlspecialchars($bg, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '"/>'
            . '<w:body>' . $paragraphs . '<w:sectPr/></w:body></w:document>';
    }

    /**
     * @param \DOMNode $node
     * @return string
     */
    private function nodes_to_paragraphs(\DOMNode $node): string {
        $out = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text = trim(preg_replace('/\s+/', ' ', $child->nodeValue ?? '') ?? '');
                if ($text !== '') {
                    $out .= $this->p_xml($text, 'Normal');
                }
                continue;
            }
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            $name = strtolower($child->nodeName);
            if (preg_match('/^h([1-6])$/', $name, $m)) {
                $out .= $this->p_xml($child->textContent ?? '', 'Heading' . $m[1]);
            } else if ($name === 'img') {
                $out .= $this->image_paragraph($child);
            } else if ($name === 'figure') {
                $out .= $this->figure_to_paragraphs($child);
            } else if ($name === 'figcaption') {
                $out .= $this->p_xml($child->textContent ?? '', 'Caption');
            } else if (in_array($name, ['p', 'li', 'td', 'th'], true)) {
                if ($child instanceof \DOMElement) {
                    foreach ($child->getElementsByTagName('img') as $img) {
                        $out .= $this->image_paragraph($img);
                    }
                }
                $out .= $this->p_xml($child->textContent ?? '', 'Normal');
            } else if ($name === 'br') {
                $out .= $this->p_xml('', 'Normal');
            } else {
                $out .= $this->nodes_to_paragraphs($child);
            }
        }
        return $out;
    }

    /**
     * @param \DOMNode $figure
     * @return string
     */
    private function figure_to_paragraphs(\DOMNode $figure): string {
        $out = '';
        foreach ($figure->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            $name = strtolower($child->nodeName);
            if ($name === 'img') {
                $out .= $this->image_paragraph($child);
            } else if ($name === 'figcaption') {
                $out .= $this->p_xml($child->textContent ?? '', 'Caption');
            } else {
                $out .= $this->nodes_to_paragraphs($child);
            }
        }
        return $out;
    }

    /**
     * Embed a data-URI image, or fall back to alt text.
     *
     * @param \DOMNode $img
     * @return string
     */
    private function image_paragraph(\DOMNode $img): string {
        $src = '';
        $alt = '';
        if ($img instanceof \DOMElement) {
            $src = $img->getAttribute('src');
            $alt = trim($img->getAttribute('alt'));
        }
        if (!preg_match('#^data:image/([a-z0-9.+-]+);base64,(.+)$#is', $src, $matches)) {
            return $alt !== '' ? $this->p_xml($alt, 'Caption') : '';
        }
        $ext = strtolower($matches[1]);
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        } else if ($ext === 'svg+xml') {
            $ext = 'svg';
        }
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?? 'png';
        if (!in_array($ext, ['png', 'jpg', 'gif', 'svg', 'webp'], true)) {
            $ext = 'png';
        }
        $binary = base64_decode($matches[2], true);
        if ($binary === false || $binary === '') {
            return $alt !== '' ? $this->p_xml($alt, 'Caption') : '';
        }

        $index = count($this->media) + 1;
        $rid = 'rId' . (2 + $index);
        $name = 'image' . $index . '.' . $ext;
        $this->media[] = [
            'rid' => $rid,
            'name' => $name,
            'data' => $binary,
            'ext' => $ext,
        ];
        return $this->drawing_xml($rid, $name, $binary, $alt);
    }

    /**
     * @param string $rid
     * @param string $name
     * @param string $binary
     * @param string $alt
     * @return string
     */
    private function drawing_xml(string $rid, string $name, string $binary, string $alt): string {
        $this->drawingid++;
        $id = $this->drawingid;
        $cx = 5486400;
        $cy = 3086550;
        $info = @getimagesizefromstring($binary);
        if (is_array($info) && !empty($info[0]) && !empty($info[1])) {
            $cx = (int) $info[0] * 9525;
            $cy = (int) $info[1] * 9525;
            $maxw = 5486400;
            if ($cx > $maxw && $cx > 0) {
                $scale = $maxw / $cx;
                $cx = $maxw;
                $cy = (int) round($cy * $scale);
            }
        }
        $cx = max(91440, $cx);
        $cy = max(91440, $cy);
        $altxml = htmlspecialchars($alt, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $namexml = htmlspecialchars($name, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return '<w:p><w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">'
            . '<wp:extent cx="' . $cx . '" cy="' . $cy . '"/>'
            . '<wp:docPr id="' . $id . '" name="Picture ' . $id . '" descr="' . $altxml . '"/>'
            . '<wp:cNvGraphicFramePr>'
            . '<a:graphicFrameLocks xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" noChangeAspect="1"/>'
            . '</wp:cNvGraphicFramePr>'
            . '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            . '<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:nvPicPr><pic:cNvPr id="0" name="' . $namexml . '" descr="' . $altxml . '"/><pic:cNvPicPr/></pic:nvPicPr>'
            . '<pic:blipFill><a:blip r:embed="' . htmlspecialchars($rid, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '"/>'
            . '<a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm>'
            . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            . '</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
    }

    /**
     * @param string $text
     * @param string $style
     * @return string
     */
    private function p_xml(string $text, string $style): string {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($text === '') {
            return '';
        }
        $xmltext = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return '<w:p><w:pPr><w:pStyle w:val="' . $style . '"/></w:pPr>'
            . '<w:r><w:t xml:space="preserve">' . $xmltext . '</w:t></w:r></w:p>';
    }

    private function content_types(): string {
        $defaults = [
            'rels' => 'application/vnd.openxmlformats-package.relationships+xml',
            'xml' => 'application/xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
        ];
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        foreach ($defaults as $ext => $type) {
            $xml .= '<Default Extension="' . $ext . '" ContentType="' . $type . '"/>';
        }
        $xml .= '<Override PartName="/word/document.xml" '
            . 'ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" '
            . 'ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '<Override PartName="/word/settings.xml" '
            . 'ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>'
            . '<Override PartName="/docProps/core.xml" '
            . 'ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '</Types>';
        return $xml;
    }

    private function rels_root(): string {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '</Relationships>';
    }

    private function rels_document(): string {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>';
        foreach ($this->media as $item) {
            $xml .= '<Relationship Id="' . htmlspecialchars($item['rid'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '"'
                . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"'
                . ' Target="media/' . htmlspecialchars($item['name'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '"/>';
        }
        $xml .= '</Relationships>';
        return $xml;
    }

    private function styles_xml(): string {
        $headings = [
            'Heading1' => ['name' => 'heading 1', 'outline' => '0', 'size' => '32', 'space' => '240'],
            'Heading2' => ['name' => 'heading 2', 'outline' => '1', 'size' => '26', 'space' => '200'],
            'Heading3' => ['name' => 'heading 3', 'outline' => '2', 'size' => '24', 'space' => '160'],
            'Heading4' => ['name' => 'heading 4', 'outline' => '3', 'size' => '22', 'space' => '120'],
            'Heading5' => ['name' => 'heading 5', 'outline' => '4', 'size' => '22', 'space' => '120'],
            'Heading6' => ['name' => 'heading 6', 'outline' => '5', 'size' => '20', 'space' => '80'],
        ];
        $rfonts = $this->rfonts_xml();
        $styles = '<w:docDefaults><w:rPrDefault><w:rPr>' . $rfonts
            . '<w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr></w:rPrDefault></w:docDefaults>';
        $styles .= '<w:style w:type="paragraph" w:default="1" w:styleId="Normal">'
            . '<w:name w:val="Normal"/><w:qFormat/>'
            . '<w:rPr>' . $rfonts . '<w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr></w:style>';
        foreach ($headings as $id => $meta) {
            $styles .= '<w:style w:type="paragraph" w:styleId="' . $id . '">'
                . '<w:name w:val="' . $meta['name'] . '"/>'
                . '<w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/>'
                . '<w:pPr><w:keepNext/><w:spacing w:before="' . $meta['space'] . '" w:after="80"/>'
                . '<w:outlineLvl w:val="' . $meta['outline'] . '"/></w:pPr>'
                . '<w:rPr>' . $rfonts . '<w:b/><w:bCs/><w:sz w:val="' . $meta['size'] . '"/><w:szCs w:val="' . $meta['size'] . '"/></w:rPr>'
                . '</w:style>';
        }
        $styles .= '<w:style w:type="paragraph" w:styleId="Caption">'
            . '<w:name w:val="caption"/><w:basedOn w:val="Normal"/><w:qFormat/>'
            . '<w:pPr><w:spacing w:after="200"/></w:pPr>'
            . '<w:rPr>' . $rfonts . '<w:i/><w:sz w:val="20"/><w:szCs w:val="20"/></w:rPr></w:style>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . $styles . '</w:styles>';
    }

    /**
     * @return string
     */
    private function rfonts_xml(): string {
        $font = htmlspecialchars($this->fontname, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return '<w:rFonts w:ascii="' . $font . '" w:hAnsi="' . $font . '" w:cs="' . $font . '" w:eastAsia="' . $font . '"/>';
    }

    private function settings_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:displayBackgroundShape/>'
            . '</w:settings>';
    }

    private function core_xml(string $title): string {
        $title = htmlspecialchars($title, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $now = gmdate('Y-m-d\TH:i:s\Z');
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>' . $title . '</dc:title>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
            . '</cp:coreProperties>';
    }

    /**
     * @param string $title
     * @return string
     */
    public static function safe_filename(string $title): string {
        $name = clean_filename($title);
        $name = preg_replace('/[^A-Za-z0-9_\-\.]+/', '_', $name) ?? 'document';
        return substr($name, 0, 80) ?: 'document';
    }
}
