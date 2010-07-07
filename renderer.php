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
 * @see mr_html_tag
 */
require_once($CFG->dirroot.'/local/mr/framework/html/tag.php');

/**
 * MR Renderer
 *
 * Default renderer for the framework.
 *
 * @package mr
 * @author Mark Nielsen
 */
class local_mr_renderer extends plugin_renderer_base {
    /**
     * Returns rendered widget.
     *
     * Add another error catching layer for
     * rendering reports.
     *
     * @param renderable $widget instance with renderable interface
     * @return string
     */
    public function render(renderable $widget) {
        try {
            return parent::render($widget);
        } catch (coding_exception $e) {
            if ($widget instanceof mr_report_abstract) {
                return $this->render_mr_report_abstract($widget);
            }
            // Re-throw original error
            throw $e;
        }
    }

    /**
     * Renders mr_html_notify
     *
     * @param mr_html_notify $notify mr_html_notify instance
     * @return string
     */
    protected function render_mr_html_notify(mr_html_notify $notify) {
        $output = '';
        foreach($notify->get_messages() as $message) {
            $output .= $this->output->notification($message[0], $message[1]);
        }
        return $output;
    }

    /**
     * Renders mr_html_tabs
     *
     * @param mr_html_tabs $tabs mr_html_tabs instance
     * @return string
     */
    protected function render_mr_html_tabs(mr_html_tabs $tabs) {
        $rows   = $tabs->get_rows();
        $output = '';

        if (!empty($rows)) {
            $inactive = $active = array();

            if (count($rows) == 2 and !empty($tabs->subtab) and !empty($rows[1][$tabs->subtab])) {
                $active[]   = $tabs->toptab;
                $currenttab = $tabs->subtab;
            } else {
                $currenttab = $tabs->toptab;
            }
            $output = print_tabs($rows, $currenttab, $inactive, $active, true);
        }
        return $output;
    }

    /**
     * Render mr_html_heading
     *
     * @param mr_html_heading $heading mr_html_heading instance
     * @return string
     */
    protected function render_mr_html_heading(mr_html_heading $heading) {
        // Do we have anything to render?
        if (empty($heading->text)) {
            return '';
        }

        $icon = '';
        if (!empty($heading->icon)) {
            $icon = $this->output->pix_icon($heading->icon, $heading->iconalt, $heading->component, array('class'=>'icon'));
        }
        $help = '';
        if (!empty($heading->helpidentifier)) {
            $help = $this->output->help_icon($heading->helpidentifier, $heading->component);
        }
        return $this->output->heading($icon.$heading->text.$help, $heading->level, $heading->classes, $heading->id);
    }

    /**
     * Render mr_html_filter
     *
     * @param mr_html_filter $filter mr_html_filter instance
     * @return string
     */
    protected function render_mr_html_filter(mr_html_filter $filter) {
        // Only render the filter form if one of the filters is not hidden
        foreach ($filter->get_filters() as $mrfilter) {
            if (!$mrfilter instanceof mr_html_filter_hidden) {
                return $filter->init()->get_helper()->buffer(array($filter->get_mform(), 'display'));
            }
        }
        return '';
    }

    /**
     * Render mr_html_paging
     *
     * @param mr_html_paging $paging mr_html_paging instance
     * @return string
     */
    public function render_mr_html_paging(mr_html_paging $paging) {
        $output = '';
        if ($paging->get_perpage()) {
            $output = $this->output->paging_bar($paging->get_total(), $paging->get_page(), $paging->get_perpage(), $paging->get_url(), $paging->REQUEST_PAGE);
        }
        if ($paging->get_perpageopts()) {
            $options = array();
            foreach ($paging->get_perpageopts() as $opt) {
                if ($opt == 'all') {
                    $options[10000] = get_string('all');
                } else {
                    $options[$opt] = $opt;
                }
            }
            $select = $this->output->single_select($paging->get_url(), $paging->REQUEST_PERPAGE, $options, $paging->get_perpage(), array());

            // Attempt to place it within the paging bar's div
            if (substr($output, strlen($output)-6) == '</div>') {
                $output = substr($output, 0, -6)."$select</div>";
            } else {
                $output .= $this->output->box($select, 'paging');
            }
        }
        return $output;
    }

    /**
     * Render mr_html_table
     *
     * @param mr_html_table $table mr_html_table instance
     * @return string
     */
    protected function render_mr_html_table(mr_html_table $table) {
        $tag     = new mr_html_tag();
        $rows    = $table->get_rows();
        $columns = $table->get_columns();

        // Table setup
        $htmltable             = new html_table();
        $htmltable->data       = array();
        $htmltable->attributes = array_merge($htmltable->attributes, $table->get_attributes());

        // Check if we have any column headings
        $haveheadings = false;
        foreach ($columns as $column) {
            if ($column->has_heading()) {
                $haveheadings = true;
                break;
            }
        }
        if ($haveheadings) {
            $htmltable->head = array();
            foreach ($columns as $column) {
                // Must set sortable to false if table is not sort enabled or if empty $rows
                if (!$table->get_sortenabled() or empty($rows)) {
                    $column->set_config('sortable', false);
                }
                $config = $column->get_config();

                // Figure out column sort controls
                if ($config->sortable) {
                    $icon    = '';
                    $torder  = 'asc';
                    $sortstr = get_string('asc');

                    if ($table->get_sort() == $column->get_name()) {
                        if ($table->get_order() == SORT_ASC) {
                            $icon    = $tag->img()->src($this->output->pix_url('t/down'))->alt(get_string('asc'));
                            $sortstr = get_string('asc');
                            $torder  = 'desc';
                        } else {
                            $icon = $tag->img()->src($this->output->pix_url('t/up'))->alt(get_string('desc'));
                        }
                    }
                    $url     = $table->get_url()->out(false, array('tsort' => $config->name, 'torder' => $torder));
                    $heading = get_string('sortby').' '.$config->heading.' '.$sortstr;
                    $heading = $config->heading.get_accesshide($heading);
                    $heading = $tag->a($heading)->href($url).$icon;
                } else {
                    $heading = $config->heading;
                }
                $cell = new html_table_cell($heading);
                $cell->attributes = array_merge($cell->attributes, $config->attributes);

                $htmltable->head[] = $cell;
            }
        }

        if (empty($rows)) {
            $cell = new html_table_cell($table->get_emptymessage());
            $cell->attributes['colspan'] = count($htmltable->head);
            $htmltable->data[] = array($cell);
        } else {
            $suppress = array();
            foreach ($rows as $row) {
                // Generate a html_table_row
                if ($row instanceof html_table_row) {
                    $htmlrow = $row;
                } else {
                    $htmlrow = new html_table_row();
                    foreach ($columns as $column) {
                        $cell = $column->get_cell($row);

                        if ($cell instanceof html_table_cell) {
                            $htmlrow->cells[] = $cell;
                        } else {
                            $cell = new html_table_cell($cell);
                            foreach ($column->get_config()->attributes as $name => $value) {
                                if (property_exists($cell, $name)) {
                                    $cell->$name = $value;
                                } else {
                                    $cell->attributes[$name] = $value;
                                }
                            }
                            $htmlrow->cells[] = $cell;
                        }
                    }
                }

                // Apply column suppression to the row
                $position = -1;
                foreach ($columns as $column) {
                    $position++;

                    if (!$column->get_config()->suppress or !array_key_exists($position, $htmlrow->cells)) {
                        continue;
                    }
                    $cell = $htmlrow->cells[$position];

                    if (isset($suppress[$position]) and $suppress[$position] == $cell->text) {
                        $htmlrow->cells[$position]->text = '';  // Suppressed
                    } else {
                        // If a cell changes, reset suppression for all cells after it (left to right)
                        if (isset($suppress[$position]) and $suppress[$position] != $cell->text) {
                            foreach ($suppress as $key => $value) {
                                if ($key > $position) {
                                    unset($suppress[$key]);
                                }
                            }
                        }
                        $suppress[$position] = $cell->text;
                    }
                }

                // Add the row to the table
                $htmltable->data[] = $htmlrow;
            }
        }
        return html_writer::table($htmltable);
    }

    /**
     * Render mr_file_export
     *
     * @param mr_file_export $export mr_file_export instance
     * @return string
     */
    protected function render_mr_file_export(mr_file_export $export) {
        $select = new single_select($export->get_url(), 'mrexporter', $export->get_select_options());
        $select->set_label(get_string('export', 'local_mr'));

        return html_writer::tag('div', $this->output->render($select), array('class' => 'mr_file_export'));
    }

    /**
     * Render mr_report_abstract
     *
     * @param mr_report_abstract $report mr_report_abstract instance
     * @return string
     * @todo Perhaps wrap everything in a div to control layout?  Then other render methods don't do align, etc
     * @todo Render in heading with help button?
     * @todo How to toggle report SQL on/off?
     */
    public function render_mr_report_abstract(mr_report_abstract $report) {
        global $CFG, $USER;

        // Fill the table
        $report->table_fill();

        $output = '';

        // @todo good idea?
        if ($report->get_export() instanceof mr_file_export and $report->get_export()->is_exporting()) {
            $report->get_export()->send();
        }

        // Heading
        // $output .= $this->output->heading($report->name());

        // Report SQL
        $executedsql = $report->get_executedsql();
        $usernames   = array('mrsupport', 'mrdev');
        if (!empty($CFG->reportviewsql) and is_array($CFG->reportviewsql)) {
            $usernames = array_merge($usernames, $CFG->reportviewsql);
        }
        if (in_array($USER->username, $usernames) and !empty($executedsql)) {
            $sql = '';
            foreach ($executedsql as $values) {
                list($rawsql, $params) = $values;
                $rawsql = trim($rawsql);

                $sql .= s($rawsql)."\n\n";
                if (!is_null($params)) {
                    $sql .= s(var_export($params, true))."\n\n\n";
                }
            }
            $output .= $this->output->box(
                $this->output->heading(get_string('reportsql', 'local_mr'), 4).
                $this->output->box('<pre>'.trim($sql).'</pre>', ''),
                'generalbox boxwidthwide boxaligncenter mr_report_sqlbox'
            );
        }

        // Description
        if ($description = $report->get_description()) {
            $output .= $this->output->box($description, 'generalbox boxwidthnormal boxaligncenter reportdescription');
        }

        // Filter
        if ($report->get_filter() instanceof mr_html_filter) {
            $output .= $this->output->box(
                $this->render($report->get_filter()),
                'boxwidthwide boxaligncenter mr_html_filter'
            );
        }
        // Render Paging top
        $output .= $this->render($report->get_paging());

        // Render table and allow report to wrap it with w/e
        $output .= $report->output_wrapper(
            $this->render($report->get_table())
        );

        // Render Paging bottom
        $output .= $this->render($report->get_paging());

        // Export
        if ($report->get_export() instanceof mr_file_export) {
            $output .= $this->render($report->get_export());
        }

        // AJAX DISPLAY: internal only
        // if ($this->config->ajax) {
        //     if ($this->preferences->get('ajax', self::$ajaxdefault)) {
        //         $newajax = 0;
        //         $label   = get_string('basichtml', 'local_mr');
        //     } else {
        //         $newajax = 1;
        //         $label   = get_string('standard', 'local_mr');
        //     }
        //     $title = s($label);
        //     $url   = $this->url->out(false, array('setajax' => $newajax));
        // 
        //     return "<p class=\"toggleajax\"><a href=\"$url\" title=\"$title\">$label</a></p>";
        // }
        // return '';

        return $output;
    }
}