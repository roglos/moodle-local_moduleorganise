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

/**
 * A scheduled task for scripted database integrations.
 *
 * @package    local_moduleorganise - template
 * @copyright  2016 ROelmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moduleorganise\task;
use stdClass;

defined('MOODLE_INTERNAL') || die;

/**
 * A scheduled task for scripted external database integrations.
 *
 * @copyright  2016 ROelmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moduleorganise extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'local_moduleorganise');
    }

    /**
     * Run sync.
     */
    public function execute() {

        global $CFG, $DB;

        // Check connection and label Db/Table in cron output for debugging if required.
        if (!$this->get_config('dbtype')) {
            echo 'Database not defined.<br>';
            return 0;
        } else {
            echo 'Database: ' . $this->get_config('dbtype') . '<br>';
        }
        if (!$this->get_config('remotetablecat')) {
            echo 'Table not defined.<br>';
            return 0;
        } else {
            echo 'Table: ' . $this->get_config('remotetablecat') . '<br>';
        }
        if (!$this->get_config('remotetablecrs')) {
            echo 'Table not defined.<br>';
            return 0;
        } else {
            echo 'Table: ' . $this->get_config('remotetablecrs') . '<br>';
        }
        echo 'Starting connection...<br>';

        // Report connection error if occurs.
        if (!$extdb = $this->db_init()) {
            echo 'Error while communicating with external database <br>';
            return 1;
        }

        /*
         * Get category page details to ensure consistency with structure.
         */
        // Get external table name.
        $table = $this->get_config('remotetablecat');
        // Read data from table.
        $sql = $this->db_get_sql($table, array(), array(), true);
        if ($rs = $extdb->Execute($sql)) {
            if (!$rs->EOF) {
                while ($fields = $rs->FetchRow()) {
                    $fields = array_change_key_case($fields, CASE_LOWER);
                    $fields = $this->db_decode($fields);
                    $datacat = $fields; // Swap naming from template to meaningful/readable.

                    /* Get course record for each data category. */
                    $catidnumber = 'CRS-' . $datacat['category_idnumber'];
                    if ($DB->get_record('course',
                        array('idnumber' => $catidnumber)) &&
                        $datacat['category_idnumber'] !== '' ) {
                        $thiscourse = $DB->get_record('course',
                            array('idnumber' => $catidnumber));
                        /* Check any changes. */
                        $updated = 0;
                        // Check fullname.
                        if ($thiscourse->fullname !== $datacat['category_name']) {
                            $updated++;
                            $thiscourse->fullname = $datacat['category_name'];
                        }
                        // Check shortname.
                        if ($thiscourse->shortname !== $catidnumber) {
                            $updated++;
                            $thiscourse->shortname = $catidnumber;
                        }
                        // Get category id for the relevant category idnumber - this is what is needed in the table.
                        if ($DB->get_record('course_categories',
                            array('idnumber' => $datacat['category_idnumber'])) ) {
                            $category = $DB->get_record('course_categories',
                                array('idnumber' => $datacat['category_idnumber']));
                            // Check if category id has changed.
                            if ($thiscourse->category !== $category->id) {
                                $updated++;
                                $thiscourse->category = $category->id;
                            }
                        }
                        // Update course record - only if changes present.
                        if ($updated > 0 ) {
                            $DB->update_record('course', $thiscourse);
                        }
                    }
                }
            }
            $rs->Close();
        } else {
            // Report error if required.
            $extdb->Close();
            echo 'Error reading data from the external course table<br>';
            return 4;
        }
        /*
         * Get course page details to ensure consistency with SITS.
         */
        // Get external table name.
        $table = $this->get_config('remotetablecrs');
        // Read data from table.
        $sql = $this->db_get_sql($table, array(), array(), true);
        if ($rs = $extdb->Execute($sql)) {
            if (!$rs->EOF) {
                while ($fields = $rs->FetchRow()) {
                    $fields = array_change_key_case($fields, CASE_LOWER);
                    $fields = $this->db_decode($fields);
                    $datacourse = $fields; // Swap naming from template to meaningful/readable.

                    /* Get course record for each data course. */
                    if ($DB->get_record('course',
                        array('idnumber' => $datacourse['course_idnumber'])) &&
                        $datacourse['course_idnumber'] !== '' ) {
                        $thiscourse = $DB->get_record('course',
                            array('idnumber' => $datacourse['course_idnumber']));
                        /* Check any changes. */
                        $updated = 0;
                        // Check fullname.
                        if ($thiscourse->fullname !== $datacourse['course_fullname']) {
                            $updated++;
                            $thiscourse->fullname = $datacourse['course_fullname'];
                        }
                        // Check shortname.
                        if ($thiscourse->shortname !== $datacourse['course_shortname']) {
                            $updated++;
                            $thiscourse->shortname = $datacourse['course_shortname'];
                        }
                        // Check startdate. Staff can bring it forward not delay it.
                        if ($thiscourse->startdate > $datacourse['course_startdate']) {
                            $updated++;
                            $thiscourse->startdate = $datacourse['course_startdate'];
                        }
                        // Get category id for the relevant category idnumber - this is what is needed in the table.
                        if ($DB->get_record('course_categories',
                            array('idnumber' => $datacourse['category_idnumber'])) ) {
                            $category = $DB->get_record('course_categories',
                                array('idnumber' => $datacourse['category_idnumber']));
                            // Check if category id has changed.
                            if ($thiscourse->category !== $category->id) {
                                $updated++;
                                $thiscourse->category = $category->id;
                            }
                        }
                        // Update course record - only if changes present.
                        if ($updated > 0 ) {
                            $DB->update_record('course', $thiscourse);
                        }
                    }
                }
            }
            $rs->Close();
        } else {
            // Report error if required.
            $extdb->Close();
            echo 'Error reading data from the external course table<br>';
            return 4;
        }

        // Free memory.
        $extdb->Close();

    }

    /* Db functions cloned from enrol/db plugin.
     * ========================================= */

    /**
     * Tries to make connection to the external database.
     *
     * @return null|ADONewConnection
     */
    public function db_init() {
        global $CFG;

        require_once($CFG->libdir.'/adodb/adodb.inc.php');

        // Connect to the external database (forcing new connection).
        $extdb = ADONewConnection($this->get_config('dbtype'));
        if ($this->get_config('debugdb')) {
            $extdb->debug = true;
            ob_start(); // Start output buffer to allow later use of the page headers.
        }

        // The dbtype my contain the new connection URL, so make sure we are not connected yet.
        if (!$extdb->IsConnected()) {
            $result = $extdb->Connect($this->get_config('dbhost'),
                $this->get_config('dbuser'),
                $this->get_config('dbpass'),
                $this->get_config('dbname'), true);
            if (!$result) {
                return null;
            }
        }

        $extdb->SetFetchMode(ADODB_FETCH_ASSOC);
        if ($this->get_config('dbsetupsql')) {
            $extdb->Execute($this->get_config('dbsetupsql'));
        }
        return $extdb;
    }

    public function db_addslashes($text) {
        // Use custom made function for now - it is better to not rely on adodb or php defaults.
        if ($this->get_config('dbsybasequoting')) {
            $text = str_replace('\\', '\\\\', $text);
            $text = str_replace(array('\'', '"', "\0"), array('\\\'', '\\"', '\\0'), $text);
        } else {
            $text = str_replace("'", "''", $text);
        }
        return $text;
    }

    public function db_encode($text) {
        $dbenc = $this->get_config('dbencoding');
        if (empty($dbenc) or $dbenc == 'utf-8') {
            return $text;
        }
        if (is_array($text)) {
            foreach ($text as $k => $value) {
                $text[$k] = $this->db_encode($value);
            }
            return $text;
        } else {
            return core_text::convert($text, 'utf-8', $dbenc);
        }
    }

    public function db_decode($text) {
        $dbenc = $this->get_config('dbencoding');
        if (empty($dbenc) or $dbenc == 'utf-8') {
            return $text;
        }
        if (is_array($text)) {
            foreach ($text as $k => $value) {
                $text[$k] = $this->db_decode($value);
            }
            return $text;
        } else {
            return core_text::convert($text, $dbenc, 'utf-8');
        }
    }

    public function db_get_sql($table, array $conditions, array $fields, $distinct = false, $sort = "") {
        $fields = $fields ? implode(',', $fields) : "*";
        $where = array();
        if ($conditions) {
            foreach ($conditions as $key => $value) {
                $value = $this->db_encode($this->db_addslashes($value));

                $where[] = "$key = '$value'";
            }
        }
        $where = $where ? "WHERE ".implode(" AND ", $where) : "";
        $sort = $sort ? "ORDER BY $sort" : "";
        $distinct = $distinct ? "DISTINCT" : "";
        $sql = "SELECT $distinct $fields
                  FROM $table
                 $where
                  $sort";
        return $sql;
    }

    public function db_get_sql_like($table2, array $conditions, array $fields, $distinct = false, $sort = "") {
        $fields = $fields ? implode(',', $fields) : "*";
        $where = array();
        if ($conditions) {
            foreach ($conditions as $key => $value) {
                $value = $this->db_encode($this->db_addslashes($value));

                $where[] = "$key LIKE '%$value%'";
            }
        }
        $where = $where ? "WHERE ".implode(" AND ", $where) : "";
        $sort = $sort ? "ORDER BY $sort" : "";
        $distinct = $distinct ? "DISTINCT" : "";
        $sql2 = "SELECT $distinct $fields
                  FROM $table2
                 $where
                  $sort";
        return $sql2;
    }


    /**
     * Returns plugin config value
     * @param  string $name
     * @param  string $default value if config does not exist yet
     * @return string value or default
     */
    public function get_config($name, $default = null) {
        $this->load_config();
        return isset($this->config->$name) ? $this->config->$name : $default;
    }

    /**
     * Sets plugin config value
     * @param  string $name name of config
     * @param  string $value string config value, null means delete
     * @return string value
     */
    public function set_config($name, $value) {
        $pluginname = $this->get_name();
        $this->load_config();
        if ($value === null) {
            unset($this->config->$name);
        } else {
            $this->config->$name = $value;
        }
        set_config($name, $value, "local_$pluginname");
    }

    /**
     * Makes sure config is loaded and cached.
     * @return void
     */
    public function load_config() {
        if (!isset($this->config)) {
            $name = $this->get_name();
            $this->config = get_config("local_$name");
        }
    }
}
