<?php

include_once($CFG->dirroot . '/lib/accesslib.php');
include_once($CFG->dirroot . '/lib/grouplib.php');
include_once($CFG->dirroot . '/lib/datalib.php');

function get_pending() {
    global $DB, $USER, $OUTPUT;

    if($pending = $DB->get_records('course_request', array('requester'=>$USER->id))) {
    
        $r = new stdClass;
        $r->header = get_string('coursespending');
        $r->items = array();
        foreach($pending as $course) {
            $r->items[] = (object) array('fullname' => '('.$course->shortname.') '.$course->fullname,
                                'icon' => html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/scheduled'), 'class' => 'icon')));
        }
        return $r;
    } else
        return null;
}

function get_recommended_for_group($groupname) { 
    global $CFG, $OUTPUT, $DB, $USER;

    $uicon = html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('t/user'),
                                          'class' => 'smallicon'));

    if($groups = $DB->get_records('groups', array('name' => $groupname))) {
        $r = new stdClass;
        $r->header = get_string('recommended', 'block_mycourses', $groupname);

        foreach($groups as $group) {
            $coursecontext = get_context_instance(CONTEXT_COURSE, $group->courseid);
            $visible = $DB->get_field('course', 'visible', array('id'=>$group->courseid));
            if($visible and !is_enrolled($coursecontext, $USER)) {

                $coursecontext = get_context_instance(CONTEXT_COURSE, $group->courseid);
                $coursecontactroles = explode(',', $CFG->coursecontact);

                $teachers = array();
                foreach ($coursecontactroles as $roleid) {
                    $users = get_role_users($roleid, $coursecontext);
                    foreach ($users as $user) {
                        $teachers[] = $uicon . html_writer::link(new moodle_url($CFG->wwwroot.'/message/', 
                                                        array('id' => $user->id)), fullname($user));
                    }
                }

                $r->items[] = (object) array('fullname' => $coursecontext->get_context_name(),
                                             'url' => $coursecontext->get_url(),
                                             'medals' => '',
                                             'details' => html_writer::alist($teachers));
            }
        }
        if(!empty($r->items)) {
            return $r;
        } else
            return null;
    }
}
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

        if($r = get_pending()) $mycourses['pending'] = $r;

        if ($courses = enrol_get_my_courses('format, visible', 'sortorder ASC')) {
            $cnames = array();
            foreach ($courses as $course) {
                if(!empty($cnames[$course->fullname])) {
                    $cnames[$course->fullname] ++;
                } else {
                    $cnames[$course->fullname] = 1;
                }
            }
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

                    $medals = null;
                    if($k === 'maineditingteacher' and $course->format == 'weeks') {
                        $groups = array();
                        foreach(groups_get_all_groups($course->id) as $group) {
                            $groups[] = $group->name;
                        }
                        
                        if(!empty($groups)) {
                            $details = implode(", ", $groups);
                        } else {
                            $details = html_writer::link("/group/?id=".$course->id, get_string('creategroups', 'block_mycourses'));
                        }
                        $details = html_writer::tag('div', $details, array('class' => 'tiny'));
                        $pcb = plugin_callback('block', 'course_approval', 'approval', 'get', array($course));

                        if($pcb and $pcb->approved) {
                            $medals = html_writer::empty_tag('img', 
                                                      array('src' => $OUTPUT->pix_url('medal1', 'block_mycourses'),
                                                            'class' => 'icon',
                                                            'title' => strftime(get_string('approved', 'block_course_approval'), $pcb->approved)));
                        };
                    } else $details = null;

                    $mycourses[$k]->items[$course->id] = (object) array('fullname' => ($cnames[$course->fullname] > 1 ? $course->shortname : $course->fullname),
                                                                        'url' => $coursecontext->get_url(),
                                                                        'details' => $details,
                                                                        'medals' => $medals,
                                         'icon' => html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url($course->visible ? 'i/course':'i/show'), 'class' => 'icon')));

                }
            }
        }

        if($idnumber = trim($DB->get_field('user', 'idnumber', array('id' => $USER->id)))) {
            if($r = get_recommended_for_group($idnumber)) $mycourses['recommended'] = $r;
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
             $context = get_context_instance_by_id($ra->contextid);

             if(empty($mycourses[$context->id])) {
               $r = new stdClass;
               $r->header = $context->get_context_name();

                $sql = "SELECT COUNT(*) as counter FROM {course} c
                       LEFT JOIN {course_approval} ca ON ca.course = c.id
                       WHERE c.format = 'weeks' AND c.category = ?";
                $approved = " AND ca.approved IS NOT NULL";

                $rec = function($catid) use(&$rec, $sql, $approved) { 
                    global $DB;

                    $x = $DB->get_record_sql($sql,           array($catid))->counter;
                    $y = $DB->get_record_sql($sql.$approved, array($catid))->counter;

                    foreach(get_child_categories($catid) as $c)  {
                        list($a, $b) = $rec($c->id);

                        $x += $a;
                        $y += $b;
                    }

                    return array($x, $y);
                };

                if($DB->get_field('course_categories', 'visible', array('id'=>$context->instanceid))) {
                    list($a, $b) = $rec($context->instanceid);
                    if($a != 0)
                        $r->items[] =  (object) array('fullname' => 'ВСЕГО курсов: '. $a .', аттестовано: '. $b. ' ('.round($b/$a*100).'%)',
                                                    'url' => $context->get_url(), 
                                                    'icon' => html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/admin'), 'class' => 'icon')),
                                                    'details' => '', 'medals' => '');

                    $categories = get_child_categories($context->instanceid);

                    foreach($categories as $c) {
                      list($a, $b) = $rec($c->id);

                      $ctxt = get_context_instance(CONTEXT_COURSECAT, $c->id);
                      if($c->visible)
                      $r->items[] =  (object) array('fullname' => $c->name, 'url' => $ctxt->get_url(),
                                                    'details' => html_writer::tag('div', 'Курсов: '. $a .', аттестовано: '. $b. ' ('.round($b/$a*100).'%)', array('class' => 'tiny')),
                                                    'icon' => html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/admin'), 'class' => 'icon'))
                                                    );
                    }
                }

                if(!empty($r->items))
                    $mycourses[$context->id] = $r; 
            } 
        }

// --- OUTPUT ---

        $aicon = html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('i/admin'),
                                              'class' => 'icon'));

        foreach($mycourses as $k => $r) {
            $list = array();
            foreach($r->items as $course) {

                $icon = empty($course->icon) ? $cicon : $course->icon;
                if(!empty($course->url)) {
                    $list[] = $icon . html_writer::link($course->url, format_string($course->fullname)) . $course->medals . $course->details;
                } else {
                   $list[] = $icon . format_string($course->fullname) . $course->details;
                }
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
