<?php

include_once($CFG->dirroot . '/lib/accesslib.php');
include_once($CFG->dirroot . '/lib/grouplib.php');
include_once($CFG->dirroot . '/lib/datalib.php');

include_once('locallib.php');

class block_mycourses extends block_base {
    function init() {
        $this->title = get_string('mycourses');
    }

    function get_content() {
        global $CFG, $USER, $DB, $OUTPUT;

/*
        if($this->content !== NULL) {
            return $this->content;
        }
*/

        $this->content = new stdClass;
        $this->content->footer = '';
        $tl = textlib_get_instance();

        $mycourses = array();
        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        if ($courses = enrol_get_my_courses()) {
            foreach ($courses as $course) {
                $coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);

                $roles = get_user_roles($coursecontext, $USER->id);
                foreach($roles as $role) {
                    if(empty($mycourses[$role->roleid])) {
                        $mycourses[$role->roleid] = new stdClass;
                        $mycourses[$role->roleid]->enrolledas = 
                            get_string('enrolledas', 'block_mycourses', $tl->strtolower($role->name));
                    }
                    $mycourses[$role->roleid]->courses[] = $course;
                }
            }

            if($idnumber = trim($USER->idnumber))
            {
                if($groups = block_mycourses_get_groups_by_name($idnumber)) {
                    $r = new stdClass; // Recommended
                    $r->enrolledas = get_string('recommended', 'block_mycourses', $idnumber);

                    foreach($groups as $group) {
                        $coursecontext = get_context_instance(CONTEXT_COURSE, $group->courseid);
                        if(!is_enrolled($coursecontext, $USER)) {
                            $r->courses[] = block_mycourses_get_course_by_id($group->courseid);
                        }
                    }

                    if(!empty($r->courses)) {
                        $mycourses[] = $r;
                    }
                }
            }

            $icon = html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/course'),
                                                  'class' => 'icon'));

            foreach($mycourses as $r) {
                $list = array();
                foreach($r->courses as $course) {
                    $link = new moodle_url('/course/view.php', array('id' => $course->id));

                    $list[] = html_writer::link($link, $icon . format_string($course->fullname));
                }
                $this->content->text .= html_writer::tag('div', 
                                                         html_writer::tag('h3', $r->enrolledas) .
                                                         html_writer::tag('ul', html_writer::alist($list), 
                                                                          array('class' => 'unlist')),
                                                         array('class' => 'enrolledas'));
            }
    
            $this->content->footer = html_writer::link(new moodle_url('/course/index.php'), 
                                                       get_string("fulllistofcourses")) . " &hellip;";

        }

        return $this->content;
    }
}
