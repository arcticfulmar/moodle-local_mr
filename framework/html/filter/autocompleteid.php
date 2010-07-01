<?php
/**
 * Moodlerooms Framework
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see http://opensource.org/licenses/gpl-3.0.html.
 *
 * @copyright Copyright (c) 2009 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @package mr
 * @author Mark Nielsen
 */

defined('MOODLE_INTERNAL') or die('Direct access to this script is forbidden.');

/**
 * @see mr_filter_abstract
 */
require_once($CFG->dirroot.'/local/mr/framework/filter/abstract.php');

/**
 * MR Filter Autocomplete with IDs
 *
 * @author Mark Nielsen
 * @package mr
 * @todo Make this work again
 */
class mr_filter_autocompleteid extends mr_filter_abstract {

    /**
     * Autocomplete options
     *
     * Options must be: array(recordID => 'display text')
     *
     * @var array
     */
    protected $options = array();

    /**
     * Adding an options param for the select options
     *
     * Options must be: array(recordID => 'display text')
     */
    public function __construct($name, $label, $options, $advanced = false, $field = NULL) {
        parent::__construct($name, $label, $advanced, $field);
        $this->options = $options;
    }

    /**
     * Defaults for two fields
     */
    public function preferences_defaults() {
        return array($this->name => 0, $this->name.'_autocompletetext' => '');
    }

    /**
     * Add text input for autocomplete and hidden field to store ID
     */
    public function add_element($mform) {
        global $CFG;

        $helper = new mr_helper();

        $textfieldname = "{$this->name}_autocompletetext";

        // Attempt to load relavent display text
        $text = $this->preferences_get($textfieldname);
        $key  = $this->preferences_get($this->name);
        if (!empty($key) and isset($this->options[$key])) {
            $text = $this->options[$key];
        }

        $mform->addElement('text', $textfieldname, $this->label);
        $mform->setType($textfieldname, PARAM_TEXT);
        $mform->setDefault($textfieldname, $text);

        $mform->addElement('hidden', $this->name, $this->preferences_get($this->name));
        $mform->setType('name', PARAM_INT);

        $helper->html->mform_autocomplete($mform, $this->options, $textfieldname, $this->name);

        if ($this->advanced) {
            $mform->setAdvanced($textfieldname);
        }

        return $this;
    }

    /**
     * Restrict to value
     */
    public function sql() {
        $preference = $this->preferences_get($this->name);
        if (!empty($preference)) {
            if (is_numeric($preference)) {
                return "$this->field = $preference";
            }
            return $this->field.' = \''.addslashes($preference).'\'';
        }
        return false;
    }
}