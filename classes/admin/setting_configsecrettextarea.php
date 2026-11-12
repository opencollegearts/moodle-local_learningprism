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

namespace local_learningprism\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * Textarea for secrets (e.g. service-account JSON) that never redisplays stored values.
 *
 * Leave blank when saving to keep the existing secret. Tick "Clear" to remove it.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_configsecrettextarea extends \admin_setting_configtextarea {

    /** @var int */
    private $secretcols;

    /** @var int */
    private $secretrows;

    /**
     * @param string $name
     * @param string $visiblename
     * @param string $description
     * @param mixed $defaultsetting
     * @param mixed $paramtype
     * @param string|int $cols
     * @param string|int $rows
     */
    public function __construct(
        $name,
        $visiblename,
        $description,
        $defaultsetting,
        $paramtype = PARAM_RAW,
        $cols = '60',
        $rows = '8'
    ) {
        $this->secretcols = (int) $cols;
        $this->secretrows = (int) $rows;
        parent::__construct($name, $visiblename, $description, $defaultsetting, $paramtype, $cols, $rows);
    }

    /**
     * Never put the stored secret into the HTML form.
     *
     * @param string $data
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = '') {
        global $OUTPUT;

        $isset = is_string($data) && trim($data) !== '';
        $defaultinfo = $isset
            ? get_string('settings:secrethidden', 'local_learningprism')
            : get_string('none');

        $context = (object) [
            'cols' => $this->secretcols,
            'rows' => $this->secretrows,
            'id' => $this->get_id(),
            'name' => $this->get_full_name(),
            'value' => '',
            'forceltr' => $this->get_force_ltr(),
            'readonly' => $this->is_readonly(),
        ];
        $element = $OUTPUT->render_from_template('core_admin/setting_configtextarea', $context);

        if ($isset && !$this->is_readonly()) {
            $clearid = $this->get_id() . '_clear';
            $clearname = $this->get_full_name() . '_clear';
            $element .= \html_writer::div(
                \html_writer::checkbox(
                    $clearname,
                    1,
                    false,
                    get_string('settings:secretclear', 'local_learningprism'),
                    ['id' => $clearid]
                ),
                'form-text mt-1'
            );
        }

        return format_admin_setting(
            $this,
            $this->visiblename,
            $element,
            $this->description,
            true,
            '',
            $defaultinfo,
            $query
        );
    }

    /**
     * Empty means keep the existing secret; clear checkbox removes it.
     *
     * @param mixed $data
     * @return string Empty string on success, error message otherwise
     */
    public function write_setting($data) {
        $clear = (int) optional_param($this->get_full_name() . '_clear', 0, PARAM_INT);
        if ($clear) {
            return parent::write_setting('');
        }

        if (!is_string($data) || trim($data) === '') {
            // Do not overwrite a stored secret when the empty placeholder is submitted.
            return '';
        }

        return parent::write_setting($data);
    }

    /**
     * Redact secrets in the config change log.
     *
     * @param string $name
     * @param string $oldvalue
     * @param string $value
     */
    protected function add_to_config_log($name, $oldvalue, $value) {
        if ($value !== '') {
            $value = '********';
        }
        if ($oldvalue !== '' && $oldvalue !== null) {
            $oldvalue = '********';
        }
        parent::add_to_config_log($name, $oldvalue, $value);
    }
}
