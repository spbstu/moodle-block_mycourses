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
            usort($courses, function($a, $b) {
              return ($a->category.$a->sortorder > $b->category.$b->sortorder) ? 1 : -1;
            });
            foreach ($courses as $course) {
                $coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);

                $roles = get_user_roles($coursecontext, $USER->id);
                foreach($roles as $role) {
                    if($role->shortname == 'manager') continue; /* HACK HACK HACK */
                    if($role->shortname == 'coursecreator') continue;
                    $id = 'role '.$role->shortname;
                    if(empty($mycourses[$id])) {
                        $mycourses[$id] = new stdClass;
                        $mycourses[$id]->enrolledas = 
                            get_string('enrolledas', 'block_mycourses', $tl->strtolower($role->name));
                    }
                    $mycourses[$id]->courses[$course->id] = $course;
                }
            }
        }

        if($idnumber = trim($DB->get_field('user', 'idnumber', array('id' => $USER->id))))
        {
            if($groups = block_mycourses_get_groups_by_name($idnumber)) {
                $r = new stdClass;
                $r->enrolledas = get_string('recommended', 'block_mycourses', $idnumber);

                foreach($groups as $group) {
                    $coursecontext = get_context_instance(CONTEXT_COURSE, $group->courseid);
                    if(!is_enrolled($coursecontext, $USER)) {
                        $r->courses[] = block_mycourses_get_course_by_id($group->courseid);
                    }
                }
                if(!empty($r->courses)) {
                    $mycourses['recommended'] = $r;
                }
            }
        }

        $uicon = html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('t/user'),
                                              'class' => 'smallicon'));
        $cicon = html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/course'),
                                              'class' => 'icon'));

        foreach($mycourses as $k => $r) {
            $list = array();
            foreach($r->courses as $course) {
                $details = '';
                $link = new moodle_url('/course/view.php', array('id' => $course->id));

                $teachers = array();
                if($k === 'recommended') {
                    $coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);
                    $coursecontactroles = explode(',', $CFG->coursecontact);

                    foreach ($coursecontactroles as $roleid) {
                        $users = get_role_users($roleid, $coursecontext);
                        foreach ($users as $user) {
                            $teachers[] = $uicon . html_writer::link(new moodle_url($CFG->wwwroot.'/message/', 
                                                            array('id' => $user->id)), fullname($user));
                        }
                    }
                    $details = html_writer::alist($teachers);
                }

                $groups = array();
                if($k === 'role maineditingteacher') {
                    foreach(groups_get_all_groups($course->id) as $group) {
                        $groups[] = $group->name;
                    }
                    
                    if(!empty($groups)) {
                        $details = html_writer::tag('div', implode(", ", $groups),
                                    array('class' => 'tiny'));
                    }
                }

                $list[] = $cicon . html_writer::link($link, format_string($course->fullname)) . $details;
            }
            $this->content->text .= html_writer::tag('div', 
                                                     html_writer::tag('h3', $r->enrolledas) .
                                                     html_writer::alist($list, 
                                                                      array('class' => 'unlist')),
                                                     array('class' => $k));
        }

        if(!empty($mycourses)) {
            $this->content->footer = html_writer::link(new moodle_url('/course/index.php'), 
                                                   get_string("fulllistofcourses")) . " &hellip;";
        }

        return $this->content;
    }
}
