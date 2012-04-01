<?php

defined('MOODLE_INTERNAL') || die();

function block_mycourses_get_groups_by_name($name) {
    global $DB;

    return $DB->get_records('groups', array('name' => $name));
}

function block_mycourses_get_course_by_id($id) {
    global $DB;

    return $DB->get_record('course', array('id' => $id));
}

?>
