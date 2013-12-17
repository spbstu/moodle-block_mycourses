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

        if($this->content) return $this->content;

        $this->content = new stdClass;
        $this->content->text = '';
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
                    if($role->shortname !== 'student' // eek, fixme
                        and !in_array($role->roleid,
                        explode(',', $CFG->coursecontact))) continue;

                    $k = $role->shortname;
                    if(empty($mycourses[$k])) {
                        $mycourses[$k] = new stdClass;
                        $mycourses[$k]->header = 
                            get_string('enrolledas', 'block_mycourses', $tl->strtolower($role->name));
                    }
                    $mycourses[$k]->items[$course->id] = (object) array('id'=>$course->id, 'fullname'=>$course->fullname);
                }
            }
        }

        if($idnumber = trim($DB->get_field('user', 'idnumber', array('id' => $USER->id))))
        {
            if($groups = block_mycourses_get_groups_by_name($idnumber)) {
                $r = new stdClass;
                $r->header = get_string('recommended', 'block_mycourses', $idnumber);

                foreach($groups as $group) {
                    $coursecontext = get_context_instance(CONTEXT_COURSE, $group->courseid);
                    if(!is_enrolled($coursecontext, $USER)) {
                        $r->items[] = (object) array('id' => $group->courseid,
                                                     'fullname' => $coursecontext->get_context_name(),
                                                     'url' => $coursecontext->get_url());

                    }
                }
                if(!empty($r->courses)) {
                    $mycourses['recommended'] = $r;
                }
            }
        }

        // from admin/roles/userroles.php
        $sql = "SELECT
                ra.id, ra.contextid, ra.roleid,
                r.name AS rolename, r.shortname,
                COALESCE(rn.name, r.name) AS localname
            FROM
                {role_assignments} ra
                JOIN {context} c ON ra.contextid = c.id
                JOIN {role} r ON ra.roleid = r.id
                LEFT JOIN {role_names} rn ON rn.roleid = ra.roleid AND rn.contextid = ra.contextid
            WHERE
                ra.userid = ?
                AND c.contextlevel = ?
            ORDER BY
                contextlevel DESC, contextid ASC, r.sortorder";
        $roleassignments = $DB->get_records_sql($sql, array($USER->id, CONTEXT_COURSECAT));

        foreach($roleassignments as $ra) {
             $context = context::instance_by_id($ra->contextid);

             if(empty($mycourses[$context->id])) {
               $r = new stdClass;
               $r->header = $context->get_context_name();
               $mycourses[$context->id] = $r; 
             }
         
             $r->items[$ra->roleid] = (object) array('id' => $id, 'fullname' => $ra->rolename, 'url'=> $context->get_url(),
                                                     'icon' => html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/admin'), 'class' => 'icon')));

        }

// --- OUTPUT ---

        $uicon = html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('t/user'),
                                              'class' => 'smallicon'));
        $cicon = html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/course'),
                                              'class' => 'icon'));
        $aicon = html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/admin'),
                                              'class' => 'icon'));

        foreach($mycourses as $k => $r) {
            $list = array();
            foreach($r->items as $course) {
                $details = '';
                $link = new moodle_url('/course/view.php', array('id' => $course->id));
                if(!empty($course->url)) $link = $course->url;

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
                        $details = implode(", ", $groups);
                    } else {
                        $details = html_writer::link("/group/?id=".$course->id, get_string('creategroups', 'block_mycourses'));
                    }
                    $details = html_writer::tag('div', $details, array('class' => 'tiny'));
                }

                $pcb = plugin_callback('block', 'course_approval', 'approval', 'get', array($course));
                if($pcb and $pcb->approved and $k !== 'role student') {
                    $micon = html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('medal1', 'block_mycourses'),
                                              'class' => 'icon'));
                } else $micon = '';
                $icon = empty($course->icon) ? $cicon : $course->icon;
                $list[] = $icon . html_writer::link($link, format_string($course->fullname)) . $micon . $details;
            }
            $this->content->text .= html_writer::tag('div', 
                                                     html_writer::tag('h3', $r->header) .
                                                     html_writer::alist($list, 
                                                                      array('class' => 'unlist')),
                                                     array('class' => 'role '.$k));
        }

        if (!empty($CFG->enablecourserequests)) {
            $systemcontext = get_context_instance(CONTEXT_SYSTEM);

            if (!has_capability('moodle/course:create', $systemcontext)
                and has_capability('moodle/course:request', $systemcontext)) {
                $this->content->text = $OUTPUT->single_button('/course/request.php', get_string('requestcourse'), 'get') . $this->content->text;
            }
        }

        if(!empty($mycourses)) {
            $this->content->footer = html_writer::link(new moodle_url('/course/index.php'), 
                                                   get_string("fulllistofcourses")) . " &hellip;";
        }

        return $this->content;
    }
}
