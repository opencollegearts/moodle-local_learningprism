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

use local_learningprism\local\mod\rise_extractor;

defined('MOODLE_INTERNAL') || die();

/**
 * Articulate Rise runtime-data extractor tests.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_learningprism\local\mod\rise_extractor
 */
class rise_extractor_test extends \advanced_testcase {
    /**
     * Tests decode rejects garbage.
     */
    public function test_decode_rejects_garbage(): void {
        $this->assertFalse(rise_extractor::is_runtime_data('not rise'));
        $this->assertNull(rise_extractor::decode('not rise'));
    }

    /**
     * Tests to html linearises rise blocks.
     */
    public function test_to_html_linearises_rise_blocks(): void {
        $this->resetAfterTest();
        $js = $this->sample_runtime_js();
        $this->assertTrue(rise_extractor::is_runtime_data($js));
        $data = rise_extractor::decode($js);
        $this->assertNotNull($data);

        $resolver = static function (array $ref): string {
            $names = rise_extractor::media_filenames($ref);
            if (in_array('dot.png', $names, true) || in_array('map.png', $names, true)) {
                return 'data:image/png;base64,abc';
            }
            return '';
        };
        $html = rise_extractor::to_html($data, $resolver);

        $this->assertStringContainsString('<h2>Lesson One</h2>', $html);
        $this->assertStringContainsString('<h3>A heading</h3>', $html);
        $this->assertStringContainsString('Body copy', $html);
        $this->assertStringNotContainsString('When we show up to the present moment', $html);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('Quoted', $html);
        $this->assertStringContainsString('data:image/png;base64,abc', $html);
        $this->assertStringContainsString('A caption', $html);
        $this->assertStringContainsString('Topic', $html);
        $this->assertStringContainsString('Hidden text', $html);
        $this->assertStringContainsString('Hotspot', $html);
        $this->assertStringContainsString('Do this', $html);
        $this->assertStringContainsString('https://example.com', $html);
        $this->assertStringContainsString('guide.pdf', $html);
        $this->assertStringContainsString('<hr/>', $html);
        $this->assertStringContainsString('Question side', $html);
        $this->assertStringContainsString('Answer side', $html);
    }

    /**
     * Tests media filenames decodes rise keys.
     */
    public function test_media_filenames_decodes_rise_keys(): void {
        $names = rise_extractor::media_filenames([
            'crushedKey' => 'Screenshot%202025-07-05%201809.jpg',
            'originalUrl' => 'Screenshot 2025-07-05 180958.png',
            'key' => 'rise/courses/abc/8Q5-Screenshot%25202025.png',
        ]);
        $this->assertContains('Screenshot 2025-07-05 1809.jpg', $names);
        $this->assertContains('Screenshot 2025-07-05 180958.png', $names);
        $this->assertContains('8Q5-Screenshot 2025.png', $names);
    }

    /**
     * @return string
     */
    private function sample_runtime_js(): string {
        $payload = [
            'course' => [
                'title' => 'Test Rise Course',
                'description' => '<div data-editor-id="x"><p><strong>Contents:</strong></p></div>',
                'lessons' => [[
                    'title' => 'Lesson One',
                    'type' => 'blocks',
                    'items' => [
                        [
                            'type' => 'text',
                            'variant' => 'heading',
                            'items' => [[
                                'heading' => '<div data-editor-id="h"><p>A heading</p></div>',
                                'paragraph' => '<p>When we show up to the present moment with all of our senses, we invite the world to fill us with joy.</p>',
                            ]],
                        ],
                        [
                            'type' => 'text',
                            'variant' => 'paragraph',
                            'items' => [[
                                'paragraph' => '<div data-editor-id="p"><p>Body copy.</p></div>',
                            ]],
                        ],
                        [
                            'type' => 'list',
                            'variant' => 'bulleted',
                            'items' => [
                                ['paragraph' => '<p>One</p>'],
                                ['paragraph' => '<p>Two</p>'],
                            ],
                        ],
                        [
                            'type' => 'quote',
                            'items' => [[
                                'paragraph' => '<p>Quoted</p>',
                                'name' => '<p>Author</p>',
                            ]],
                        ],
                        [
                            'type' => 'image',
                            'items' => [[
                                'media' => ['image' => [
                                    'alt' => 'Dot',
                                    'crushedKey' => 'dot.png',
                                    'originalUrl' => 'dot.png',
                                ]],
                                'caption' => '<p>A caption</p>',
                            ]],
                        ],
                        [
                            'type' => 'interactive',
                            'variant' => 'accordion',
                            'items' => [['title' => 'Topic', 'description' => '<p>Hidden text</p>']],
                        ],
                        [
                            'type' => 'interactive',
                            'variant' => 'labeledgraphic',
                            'media' => ['image' => ['alt' => 'Map', 'crushedKey' => 'map.png']],
                            'items' => [['title' => 'Hotspot', 'description' => '<p>There</p>']],
                        ],
                        [
                            'type' => 'interactive',
                            'variant' => 'flashcard',
                            'items' => [[
                                'front' => ['description' => '<p>Question side</p>'],
                                'back' => ['description' => '<p>Answer side</p>'],
                            ]],
                        ],
                        [
                            'type' => 'interactive',
                            'variant' => 'process',
                            'items' => [['title' => 'Step 1', 'description' => '<p>Do this</p>']],
                        ],
                        [
                            'type' => 'interactive',
                            'variant' => 'button',
                            'items' => [[
                                'label' => 'Continue',
                                'description' => '<p>Open the library</p>',
                                'destination' => 'https://example.com',
                            ]],
                        ],
                        [
                            'type' => 'multimedia',
                            'variant' => 'attachment',
                            'items' => [[
                                'media' => ['attachment' => [
                                    'originalUrl' => 'guide.pdf',
                                    'filename' => 'guide.pdf',
                                ]],
                            ]],
                        ],
                        ['type' => 'divider', 'variant' => 'spacing divider', 'items' => []],
                        ['type' => 'divider', 'variant' => 'divider', 'items' => []],
                        ['type' => 'link', 'label' => 'Continue', 'items' => []],
                    ],
                ]],
            ],
        ];
        return '__jsonp("runtime-data.js","' . base64_encode(json_encode($payload)) . '");';
    }
}
