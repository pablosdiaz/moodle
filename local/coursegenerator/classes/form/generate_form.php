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

namespace local_coursegenerator\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Teacher-facing form to request AI generated content for a course.
 *
 * @package    local_coursegenerator
 * @copyright  2026 Course generator
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_form extends \moodleform {

    /** Sentinel value meaning "append a new section at the end of the course". */
    const NEW_SECTION = -1;

    /**
     * @var array section options for the "sectionnum" selector.
     */
    protected array $sectionoptions;

    protected function definition() {
        $mform = $this->_form;
        $course = $this->_customdata['course'];
        $this->sectionoptions = $this->_customdata['sectionoptions'];
        $maxsections = $this->_customdata['maxsections'];
        $maxquestions = $this->_customdata['maxquestions'];

        $mform->addElement('hidden', 'id', $course->id);
        $mform->setType('id', PARAM_INT);

        $typeoptions = [
            'sections' => get_string('contenttype_sections', 'local_coursegenerator'),
            'page' => get_string('contenttype_page', 'local_coursegenerator'),
            'quiz' => get_string('contenttype_quiz', 'local_coursegenerator'),
        ];
        $mform->addElement('select', 'contenttype', get_string('form_contenttype', 'local_coursegenerator'), $typeoptions);

        $mform->addElement('textarea', 'topic', get_string('form_topic', 'local_coursegenerator'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('topic', PARAM_TEXT);
        $mform->addRule('topic', null, 'required', null, 'client');
        $mform->addHelpButton('topic', 'form_topic', 'local_coursegenerator');

        // Only used when contenttype == sections.
        $mform->addElement('text', 'numsections', get_string('form_numsections', 'local_coursegenerator'));
        $mform->setType('numsections', PARAM_INT);
        $mform->setDefault('numsections', min(3, $maxsections));
        $mform->hideIf('numsections', 'contenttype', 'neq', 'sections');

        // Only used when contenttype == page or quiz.
        $mform->addElement('select', 'sectionnum', get_string('form_sectionnum', 'local_coursegenerator'), $this->sectionoptions);
        $mform->hideIf('sectionnum', 'contenttype', 'eq', 'sections');

        $mform->addElement('text', 'title', get_string('form_title', 'local_coursegenerator'));
        $mform->setType('title', PARAM_TEXT);
        $mform->hideIf('title', 'contenttype', 'eq', 'sections');

        $mform->addElement('text', 'numquestions', get_string('form_numquestions', 'local_coursegenerator'));
        $mform->setType('numquestions', PARAM_INT);
        $mform->setDefault('numquestions', min(5, $maxquestions));
        $mform->hideIf('numquestions', 'contenttype', 'neq', 'quiz');

        $this->add_action_buttons(false, get_string('form_submit', 'local_coursegenerator'));
    }

    public function validation($data, $files) {
        $errors = [];

        if (trim((string) ($data['topic'] ?? '')) === '') {
            $errors['topic'] = get_string('required');
        }

        if (($data['contenttype'] ?? '') === 'sections') {
            $n = (int) ($data['numsections'] ?? 0);
            if ($n < 1) {
                $errors['numsections'] = get_string('required');
            }
        } else {
            if (trim((string) ($data['title'] ?? '')) === '') {
                $errors['title'] = get_string('required');
            }
            if (!array_key_exists((string) ($data['sectionnum'] ?? ''), $this->sectionoptions)) {
                $errors['sectionnum'] = get_string('required');
            }
        }

        if (($data['contenttype'] ?? '') === 'quiz') {
            $n = (int) ($data['numquestions'] ?? 0);
            if ($n < 1) {
                $errors['numquestions'] = get_string('required');
            }
        }

        return $errors;
    }
}
