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

namespace local_coursegenerator;

defined('MOODLE_INTERNAL') || die();

/**
 * Turns AI-generated JSON into real Moodle course content (sections, pages, quizzes)
 * and keeps a log of every generation for the admin report.
 *
 * @package    local_coursegenerator
 * @copyright  2026 Course generator
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_generator {

    /**
     * Generate N new course sections (with name + summary) from a topic.
     *
     * @param int $courseid
     * @param string $topic
     * @param int $numsections
     * @param int $userid
     * @return string[] names of the sections that were created.
     */
    public static function generate_sections(int $courseid, string $topic, int $numsections, int $userid): array {
        $course = get_course($courseid);

        $maxsections = (int) get_config('local_coursegenerator', 'maxsections');
        $numsections = max(1, min($numsections, $maxsections > 0 ? $maxsections : 10));

        $ai = ai_client::from_config();
        $system = 'Eres un asistente experto en diseño curricular. Responde EXCLUSIVAMENTE con un objeto JSON '
            . 'valido (sin texto adicional, sin markdown) con la forma {"sections":[{"name":"...","summary":"..."}]}. '
            . 'El array "sections" debe tener exactamente ' . $numsections . ' elementos, ordenados de forma '
            . 'progresiva. "summary" puede incluir HTML sencillo (p, ul, li, strong).';
        $user = "Tema del curso: {$topic}\nIdioma: español.\nGenera {$numsections} secciones/temas progresivos y "
            . 'coherentes para este curso.';

        $data = $ai->generate_json($system, $user);
        $items = $data['sections'] ?? [];
        if (!is_array($items) || empty($items)) {
            throw new \moodle_exception('error_aiempty', 'local_coursegenerator');
        }

        $format = course_get_format($course);
        $last = max(0, (int) $format->get_last_section_number());
        $newnums = range($last + 1, $last + count($items));

        course_create_sections_if_missing($course, $newnums);
        rebuild_course_cache($course->id, true);
        $modinfo = get_fast_modinfo($course);

        $names = [];
        foreach (array_values($items) as $index => $item) {
            if (!isset($newnums[$index])) {
                break;
            }
            $sectionnum = $newnums[$index];
            $sectioninfo = $modinfo->get_section_info($sectionnum);
            if (!$sectioninfo) {
                continue;
            }

            $name = clean_param((string) ($item['name'] ?? ''), PARAM_TEXT);
            $summary = clean_param((string) ($item['summary'] ?? ''), PARAM_CLEANHTML);
            if ($name === '') {
                $name = 'Tema ' . $sectionnum;
            }

            course_update_section($course, $sectioninfo, (object) [
                'name' => $name,
                'summary' => $summary,
                'summaryformat' => FORMAT_HTML,
            ]);
            $names[] = $name;
        }

        if (empty($names)) {
            throw new \moodle_exception('error_aiempty', 'local_coursegenerator');
        }

        log_manager::log($courseid, $userid, 'sections', implode(', ', $names), count($names), $topic, $ai->get_model());

        return $names;
    }

    /**
     * Generate a single mod_page resource with AI-written HTML content.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @param string $title
     * @param string $topic
     * @param int $userid
     * @return \stdClass the created module info (->coursemodule, ->instance, ->name...).
     */
    public static function generate_page(int $courseid, int $sectionnum, string $title, string $topic, int $userid): \stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/lib/resourcelib.php');

        $course = get_course($courseid);

        $ai = ai_client::from_config();
        $system = 'Eres un redactor de contenido educativo. Responde EXCLUSIVAMENTE con un objeto JSON valido '
            . '(sin texto adicional, sin markdown) con la forma {"title":"...","content":"..."} donde "content" es '
            . 'HTML sencillo (usa <p>, <h3>, <ul>, <li>, <strong>) con el desarrollo completo del tema.';
        $user = "Título propuesto: {$title}\nTema/instrucciones: {$topic}\nIdioma: español.";

        $data = $ai->generate_json($system, $user);
        $name = clean_param((string) ($data['title'] ?? $title), PARAM_TEXT);
        $content = clean_param((string) ($data['content'] ?? ''), PARAM_CLEANHTML);

        if ($name === '') {
            $name = $title;
        }
        if ($content === '') {
            throw new \moodle_exception('error_aiempty', 'local_coursegenerator');
        }

        $moduleinfo = self::create_module($course, 'page', $sectionnum, [
            'name' => $name,
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'content' => $content,
            'contentformat' => FORMAT_HTML,
            'display' => RESOURCELIB_DISPLAY_AUTO,
            'printintro' => 0,
            'printlastmodified' => 1,
        ]);

        log_manager::log($courseid, $userid, 'page', $name, 1, $topic, $ai->get_model());

        return $moduleinfo;
    }

    /**
     * Generate a quiz activity with N AI-generated multiple choice questions.
     *
     * @param int $courseid
     * @param int $sectionnum
     * @param string $title
     * @param string $topic
     * @param int $numquestions
     * @param int $userid
     * @return \stdClass with ->name, ->count, ->cmid.
     */
    public static function generate_quiz(
        int $courseid,
        int $sectionnum,
        string $title,
        string $topic,
        int $numquestions,
        int $userid
    ): \stdClass {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->libdir . '/questionlib.php');
        require_once($CFG->dirroot . '/question/engine/bank.php');

        $course = get_course($courseid);

        $maxquestions = (int) get_config('local_coursegenerator', 'maxquestions');
        $numquestions = max(1, min($numquestions, $maxquestions > 0 ? $maxquestions : 10));

        $ai = ai_client::from_config();
        $system = 'Eres un experto en evaluación educativa. Responde EXCLUSIVAMENTE con un objeto JSON valido '
            . '(sin texto adicional, sin markdown) con la forma {"questions":[{"question":"...",'
            . '"options":["...","...","...","..."],"correctindex":0}]}. Genera exactamente ' . $numquestions
            . ' preguntas de opción múltiple, cada una con exactamente 4 opciones y "correctindex" (0 a 3) '
            . 'indicando cuál opción es correcta.';
        $user = "Tema: {$topic}\nIdioma: español.\nNúmero de preguntas: {$numquestions}.";

        $data = $ai->generate_json($system, $user);
        $questions = $data['questions'] ?? [];
        if (!is_array($questions) || empty($questions)) {
            throw new \moodle_exception('error_aiempty', 'local_coursegenerator');
        }

        $moduleinfo = self::create_quiz_instance($course, $sectionnum, $title);

        $quiz = $DB->get_record('quiz', ['id' => $moduleinfo->instance], '*', MUST_EXIST);
        $quiz->cmid = $moduleinfo->coursemodule;

        $coursecontext = \context_course::instance($course->id);
        $category = question_make_default_categories([$coursecontext]);

        $created = 0;
        foreach (array_values($questions) as $index => $q) {
            $qtext = clean_param((string) ($q['question'] ?? ''), PARAM_TEXT);
            $options = $q['options'] ?? [];
            $correctindex = (int) ($q['correctindex'] ?? 0);

            if ($qtext === '' || !is_array($options) || count($options) < 2) {
                continue;
            }

            $answers = [];
            foreach (array_values($options) as $i => $optiontext) {
                $text = clean_param((string) $optiontext, PARAM_TEXT);
                if ($text === '') {
                    continue;
                }
                $answers[] = [
                    'text' => $text,
                    'fraction' => ($i === $correctindex) ? 1.0 : 0.0,
                ];
            }
            if (count($answers) < 2) {
                continue;
            }

            $questionname = 'P' . ($index + 1) . ': ' . shorten_text($qtext, 50);
            $questionid = self::create_multichoice_question($category, $questionname, $qtext, $answers);
            quiz_add_quiz_question($questionid, $quiz);
            $created++;
        }

        if ($created === 0) {
            throw new \moodle_exception('error_aiempty', 'local_coursegenerator');
        }

        \mod_quiz\quiz_settings::create($quiz->id)
            ->get_grade_calculator()
            ->recompute_quiz_sumgrades();

        log_manager::log($courseid, $userid, 'quiz', $quiz->name, $created, $topic, $ai->get_model());

        $result = new \stdClass();
        $result->name = $quiz->name;
        $result->count = $created;
        $result->cmid = $moduleinfo->coursemodule;
        return $result;
    }

    /**
     * Generic course-module creation helper, built on top of core's add_moduleinfo(),
     * following the same defaulting pattern used by Moodle's own module generators.
     *
     * @param \stdClass $course
     * @param string $modulename
     * @param int $sectionnum
     * @param array $fields modulename-specific fields (name, intro, content...).
     * @return \stdClass the created module info.
     */
    protected static function create_module(\stdClass $course, string $modulename, int $sectionnum, array $fields): \stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');

        $moduleinfo = (object) $fields;
        $moduleinfo->modulename = $modulename;
        $moduleinfo->module = $DB->get_field('modules', 'id', ['name' => $modulename]);
        $moduleinfo->course = $course->id;
        $moduleinfo->section = $sectionnum;

        $defaults = [
            'visible' => 1,
            'visibleoncoursepage' => 1,
            'cmidnumber' => '',
            'groupmode' => 0,
            'groupingid' => 0,
            'availability' => null,
            'completion' => 0,
            'completionview' => 0,
            'completionexpected' => 0,
            'completionpassgrade' => 0,
            'conditiongradegroup' => [],
            'conditionfieldgroup' => [],
            'intro' => '',
            'introformat' => FORMAT_HTML,
        ];
        foreach ($defaults as $key => $value) {
            if (!isset($moduleinfo->$key)) {
                $moduleinfo->$key = $value;
            }
        }

        return add_moduleinfo($moduleinfo, $course);
    }

    /**
     * Create an (almost) default quiz activity, ready to have questions added to it.
     *
     * @param \stdClass $course
     * @param int $sectionnum
     * @param string $name
     * @return \stdClass the created module info.
     */
    protected static function create_quiz_instance(\stdClass $course, int $sectionnum, string $name): \stdClass {
        $quizdefaults = [
            'timeopen' => 0,
            'timeclose' => 0,
            'preferredbehaviour' => 'deferredfeedback',
            'attempts' => 0,
            'attemptonlast' => 0,
            'grademethod' => QUIZ_GRADEHIGHEST,
            'decimalpoints' => 2,
            'questiondecimalpoints' => -1,
            'attemptduring' => 1,
            'correctnessduring' => 1,
            'maxmarksduring' => 1,
            'marksduring' => 1,
            'specificfeedbackduring' => 1,
            'generalfeedbackduring' => 1,
            'rightanswerduring' => 1,
            'overallfeedbackduring' => 0,
            'attemptimmediately' => 1,
            'correctnessimmediately' => 1,
            'maxmarksimmediately' => 1,
            'marksimmediately' => 1,
            'specificfeedbackimmediately' => 1,
            'generalfeedbackimmediately' => 1,
            'rightanswerimmediately' => 1,
            'overallfeedbackimmediately' => 1,
            'attemptopen' => 1,
            'correctnessopen' => 1,
            'maxmarksopen' => 1,
            'marksopen' => 1,
            'specificfeedbackopen' => 1,
            'generalfeedbackopen' => 1,
            'rightansweropen' => 1,
            'overallfeedbackopen' => 1,
            'attemptclosed' => 0,
            'correctnessclosed' => 0,
            'maxmarksclosed' => 0,
            'marksclosed' => 0,
            'specificfeedbackclosed' => 0,
            'generalfeedbackclosed' => 0,
            'rightanswerclosed' => 0,
            'overallfeedbackclosed' => 0,
            'questionsperpage' => 1,
            'shuffleanswers' => 1,
            'sumgrades' => 0,
            'grade' => 100,
            'timelimit' => 0,
            'overduehandling' => 'autosubmit',
            'graceperiod' => 86400,
            'quizpassword' => '',
            'subnet' => '',
            'browsersecurity' => '',
            'delay1' => 0,
            'delay2' => 0,
            'showuserpicture' => 0,
            'showblocks' => 0,
            'navmethod' => QUIZ_NAVMETHOD_FREE,
        ];

        return self::create_module($course, 'quiz', $sectionnum, array_merge($quizdefaults, [
            'name' => $name,
        ]));
    }

    /**
     * Create a multichoice question in the question bank, mirroring the field layout
     * produced by the standard question edit form (name, questiontext, answer[]/fraction[]/feedback[]...).
     *
     * @param \stdClass $category question_categories record (->id, ->contextid).
     * @param string $name
     * @param string $questiontext
     * @param array $answers list of ['text' => string, 'fraction' => float].
     * @return int the new question id.
     */
    protected static function create_multichoice_question(
        \stdClass $category,
        string $name,
        string $questiontext,
        array $answers
    ): int {
        $question = new \stdClass();
        $question->qtype = 'multichoice';
        $question->category = $category->id . ',' . $category->contextid;
        $question->createdby = 0;
        $question->idnumber = null;

        $form = new \stdClass();
        $form->category = $category->id . ',' . $category->contextid;
        $form->name = shorten_text($name, 255);
        $form->questiontext = ['text' => $questiontext, 'format' => FORMAT_HTML, 'itemid' => 0];
        $form->questiontextformat = FORMAT_HTML;
        $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];
        $form->defaultmark = 1;
        $form->penalty = 0.3333333;
        $form->single = 1;
        $form->shuffleanswers = 1;
        $form->answernumbering = 'abc';
        $form->showstandardinstruction = 0;
        $form->shownumcorrect = 0;
        $form->correctfeedback = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];
        $form->partiallycorrectfeedback = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];
        $form->incorrectfeedback = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];

        $form->answer = [];
        $form->fraction = [];
        $form->feedback = [];
        foreach ($answers as $answer) {
            $form->answer[] = ['text' => $answer['text'], 'format' => FORMAT_HTML, 'itemid' => 0];
            $form->fraction[] = $answer['fraction'];
            $form->feedback[] = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];
        }

        $saved = \question_bank::get_qtype('multichoice')->save_question($question, $form);

        return (int) $saved->id;
    }
}
