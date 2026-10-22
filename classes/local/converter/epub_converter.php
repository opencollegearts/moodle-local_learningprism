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
 * HTML to EPUB 3 converter (ZipArchive, no extra service).
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class epub_converter implements converter_interface {
    public function get_format(): string {
        return manager::FORMAT_EPUB;
    }

    public function is_configured(): bool {
        return class_exists(\ZipArchive::class);
    }

    public function convert(string $html, string $title, int $jobid, array $options = []): stored_file {
        $filename = docx_converter::safe_filename($title) . '.epub';
        $content = $this->build_epub($html, $title, $options);
        return filemanager::store_converted_file($jobid, $filename, 'application/epub+zip', $content);
    }

    /**
     * Build EPUB 3 binary from normalised HTML.
     *
     * @param string $html
     * @param string $title
     * @param array $options printbackground / font keys
     * @return string
     */
    public function build_epub(string $html, string $title, array $options = []): string {
        $lang = $this->html_lang($html);
        $packaged = $this->package_html($html);
        $uid = 'urn:uuid:' . $this->uuid();
        $options = html_normalizer::normalise_print_options($options);

        $tmpdir = make_request_directory();
        $epubpath = $tmpdir . '/output.epub';

        $zip = new \ZipArchive();
        if ($zip->open($epubpath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \moodle_exception('error:conversionfailed', 'local_learningprism');
        }

        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->setCompressionName('mimetype', \ZipArchive::CM_STORE);

        $zip->addFromString('META-INF/container.xml', $this->container_xml());
        $zip->addFromString('EPUB/styles.css', html_normalizer::print_css($options['printbackground'], $options['font']));
        $zip->addFromString('EPUB/nav.xhtml', $this->nav_xhtml($title, $lang, $packaged['nav']));
        $zip->addFromString('EPUB/chapter.xhtml', $this->chapter_xhtml($title, $lang, $packaged['body']));
        $zip->addFromString('EPUB/content.opf', $this->package_opf($title, $lang, $uid, $packaged['images']));

        foreach ($packaged['images'] as $image) {
            $zip->addFromString('EPUB/' . $image['href'], $image['data']);
        }

        $zip->close();

        $binary = file_get_contents($epubpath);
        if ($binary === false) {
            throw new \moodle_exception('error:conversionfailed', 'local_learningprism');
        }
        return $binary;
    }

    /**
     * @param string $html
     * @return string
     */
    private function html_lang(string $html): string {
        if (preg_match('/<html[^>]*\slang=["\']([^"\']+)["\']/i', $html, $m)) {
            $lang = preg_replace('/[^a-zA-Z0-9\-]/', '', $m[1]);
            if ($lang) {
                return $lang;
            }
        }
        return 'en';
    }

    /**
     * Parse HTML: heading ids, extract data-URI images, XHTML body inner XML.
     *
     * @param string $html
     * @return array{body:string,nav:array,images:array}
     */
    private function package_html(string $html): array {
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $images = [];
        $imgindex = 1;
        foreach ($dom->getElementsByTagName('img') as $img) {
            $src = $img->getAttribute('src');
            if (!preg_match('#^data:image/([a-z0-9.+-]+);base64,(.+)$#i', $src, $m)) {
                continue;
            }
            $ext = strtolower($m[1]);
            $ext = ($ext === 'jpeg') ? 'jpg' : preg_replace('/[^a-z0-9]/', '', $ext);
            if ($ext === '') {
                $ext = 'png';
            }
            $binary = base64_decode($m[2], true);
            if ($binary === false) {
                continue;
            }
            $href = 'images/img' . $imgindex . '.' . $ext;
            $images[] = [
                'id' => 'img' . $imgindex,
                'href' => $href,
                'media' => 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext),
                'data' => $binary,
            ];
            $img->setAttribute('src', $href);
            $imgindex++;
        }

        $nav = [];
        $headingindex = 1;
        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//h1|//h2|//h3|//h4|//h5|//h6') as $heading) {
            $id = $heading->getAttribute('id');
            if ($id === '') {
                $id = 'h' . $headingindex;
                $heading->setAttribute('id', $id);
            }
            $nav[] = [
                'id' => $id,
                'level' => (int) substr(strtolower($heading->nodeName), 1),
                'label' => trim(preg_replace('/\s+/', ' ', $heading->textContent ?? '') ?? ''),
            ];
            $headingindex++;
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        $inner = '';
        if ($body) {
            foreach ($body->childNodes as $child) {
                $inner .= $dom->saveXML($child);
            }
        }
        if (trim($inner) === '') {
            $inner = '<p></p>';
        }
        // DOM saveXML may emit <img ...></img> or unclosed tags; both are invalid XHTML.
        $inner = preg_replace('#<(img|br|hr|meta|link|source)(\s[^>]*)?></\1>#i', '<$1$2/>', $inner) ?? $inner;
        $inner = preg_replace('#<(img|br|hr|meta|link|source)(\s[^>]*?)?(?<!/)>#i', '<$1$2/>', $inner) ?? $inner;

        return ['body' => $inner, 'nav' => $nav, 'images' => $images];
    }

    /**
     * @param string $title
     * @param string $lang
     * @param array $nav
     * @return string
     */
    private function nav_xhtml(string $title, string $lang, array $nav): string {
        $items = '';
        foreach ($nav as $entry) {
            $label = htmlspecialchars($entry['label'] !== '' ? $entry['label'] : $entry['id'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $items .= '<li><a href="chapter.xhtml#' . htmlspecialchars($entry['id'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '">'
                . $label . '</a></li>';
        }
        if ($items === '') {
            $items = '<li><a href="chapter.xhtml">' . htmlspecialchars($title, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</a></li>';
        }
        $lang = htmlspecialchars($lang, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $titlexml = htmlspecialchars($title, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" xml:lang="' . $lang . '" lang="' . $lang . '">'
            . '<head><title>' . $titlexml . '</title></head><body>'
            . '<nav epub:type="toc" id="toc"><h1>' . $titlexml . '</h1><ol>' . $items . '</ol></nav>'
            . '</body></html>';
    }

    /**
     * @param string $title
     * @param string $lang
     * @param string $bodyinner
     * @return string
     */
    private function chapter_xhtml(string $title, string $lang, string $bodyinner): string {
        $lang = htmlspecialchars($lang, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $titlexml = htmlspecialchars($title, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="' . $lang . '" lang="' . $lang . '">'
            . '<head><title>' . $titlexml . '</title>'
            . '<link rel="stylesheet" type="text/css" href="styles.css"/>'
            . '</head><body>' . $bodyinner . '</body></html>';
    }

    /**
     * @param string $title
     * @param string $lang
     * @param string $uid
     * @param array $images
     * @return string
     */
    private function package_opf(string $title, string $lang, string $uid, array $images): string {
        $titlexml = htmlspecialchars($title, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $lang = htmlspecialchars($lang, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $uidxml = htmlspecialchars($uid, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $modified = gmdate('Y-m-d\TH:i:s\Z');
        $manifest = '<item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav"/>'
            . '<item id="chapter" href="chapter.xhtml" media-type="application/xhtml+xml"/>'
            . '<item id="css" href="styles.css" media-type="text/css"/>';
        foreach ($images as $image) {
            $manifest .= '<item id="' . htmlspecialchars($image['id'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '"'
                . ' href="' . htmlspecialchars($image['href'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '"'
                . ' media-type="' . htmlspecialchars($image['media'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<package xmlns="http://www.idpf.org/2007/opf" unique-identifier="pub-id" version="3.0" xml:lang="' . $lang . '">'
            . '<metadata xmlns:dc="http://purl.org/dc/elements/1.1/">'
            . '<dc:identifier id="pub-id">' . $uidxml . '</dc:identifier>'
            . '<dc:title>' . $titlexml . '</dc:title>'
            . '<dc:language>' . $lang . '</dc:language>'
            . '<meta property="dcterms:modified">' . $modified . '</meta>'
            . '</metadata>'
            . '<manifest>' . $manifest . '</manifest>'
            . '<spine><itemref idref="chapter"/></spine>'
            . '</package>';
    }

    /**
     * @return string
     */
    private function container_xml(): string {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">'
            . '<rootfiles><rootfile full-path="EPUB/content.opf" media-type="application/oebps-package+xml"/></rootfiles>'
            . '</container>';
    }

    /**
     * @return string
     */
    private function uuid(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
