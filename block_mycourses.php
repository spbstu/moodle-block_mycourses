<?php

include_once($CFG->dirroot . '/course/lib.php');
include_once($CFG->dirroot . '/lib/accesslib.php');


class block_mycourses extends block_base {
    function init() {
        $this->title = get_string('mycourses');
    }

    function get_content() {
        global $CFG, $USER, $DB, $OUTPUT;

        if($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->footer = '';

        $icon  = '<img src="' . $OUTPUT->pix_url('i/course') . '" class="icon" alt="" />&nbsp;';

	$coursesbyroles = array();
        if (isloggedin() && !isguestuser()) {
          if ($courses = enrol_get_my_courses(NULL, 'fullname ASC')) {
            foreach ($courses as $course) {
              $coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);

	      $roles = get_user_roles($coursecontext, $USER->id);
	      foreach($roles as $role) {
		if(empty($coursesbyroles[$role->roleid]))
	 	{
		  $coursesbyroles[$role->roleid] = new stdClass;
		  $coursesbyroles[$role->roleid]->role = $role;
		}
		$coursesbyroles[$role->roleid]->courses[] = $course;
	      }
            }
            foreach($coursesbyroles as $r) {
	      $list = array();
	      foreach($r->courses as $course) {
          	$link = new moodle_url('/course/view.php', array('id' => $course->id));

                $list[] = html_writer::link($link, format_string($course->fullname));
              }
              $this->content->text .= html_writer::tag('div', 
			html_writer::tag('h3', $r->role->name) .
			html_writer::tag('ul', html_writer::alist($list)));
            }
	
            $this->content->footer = html_writer::link(new moodle_url('/course/index.php'), 
							get_string("fulllistofcourses"));
            return $this->content;
          }
          
	  if(include_once($CFG->dirroot . '/blocks/category_combo/block_category_combo.php'))
	  {
	    $cc = new block_category_combo();

	    $cc->init();
	    $this->content = $cc->get_content();
          }
        }

        return $this->content;
    }
}
