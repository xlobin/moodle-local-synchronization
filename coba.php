<?php

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once(__DIR__ . '/lib/MyClient.php');
require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/lib/course.php');

admin_externalpage_setup('localsynchfromserver');

$course = $DB->get_record('course', array('id' => '2'));

$sections = $DB->get_records('course_sections', array('course' => $course->id));
foreach ($sections as $keySection => $section) {
    if ($section->sequence != "" || $section->sequence) {
        $modlist = explode(',', $section->sequence);
        $modules = array();
        foreach ($modlist as $mod) {
            $module = $DB->get_record('course_modules', array('id' => $mod));
            $mod_type = $DB->get_record('modules', array('id' => $module->module));
            $module_item = $DB->get_records($mod_type->name, array('id' => $module->instance));
            foreach ($module_item as $keyItem => $item) {
                $functionName = 'getMy' . ucfirst(strtolower($mod_type->name));
                if (function_exists($functionName)) {
                    if ($moduleItem = $functionName($item))
                        $module_item[$keyItem]->my_item = $moduleItem;
                }
            }
            $module->my_item[$mod_type->name] = $module_item;
            $modules[$module->id] = $module;
        }
        $section->my_item = array('course_modules' => $modules);
    }
    $sections[$keySection] = $section;
}

function getMyQuiz($quiz) {
    global $DB;

    $quizSlots = $DB->get_records('quiz_slots', array('quizid' => $quiz->id));
    foreach ($quizSlots as $keySlot => $slot) {
        $questions = $DB->get_records('question', array('id' => $slot->questionid));
        foreach ($questions as $keyQuestion => $question) {
            /* ANSWER */
            $answer = $DB->get_records('question_answers', array('question' => $question->id));
            if (count($answer) > 0) {
                $questions[$keyQuestion]->my_item['question_answers'] = $answer;
            }

            /* HINTS */
            $hint = $DB->get_records('question_hints', array('questionid' => $question->id));
            if (count($hint) > 0) {
                $questions[$keyQuestion]->my_item['question_hints'] = $hint;
            }

            /* TRUEFALSE */
            $truefalse = $DB->get_records('question_truefalse', array('question' => $question->id));
            if (count($truefalse) > 0) {
                $questions[$keyQuestion]->my_item['question_truefalse'] = $truefalse;
            }

            /* NUMERICAL */
            $numerical = $DB->get_records('question_numerical', array('question' => $question->id));
            if (count($numerical) > 0) {
                $questions[$keyQuestion]->my_item['question_numerical'] = $numerical;

                $numerical_option = $DB->get_records('question_numerical_options', array('question' => $question->id));
                if (count($numerical_option) > 0) {
                    $questions[$keyQuestion]->my_item['question_numerical_options'] = $numerical_option;
                }
                $numerical_unit = $DB->get_records('question_numerical_units', array('question' => $question->id));
                if (count($numerical_unit) > 0) {
                    $questions[$keyQuestion]->my_item['question_numerical_units'] = $numerical_unit;
                }
            }

            /* CALCULATED OPTION */
            $calculated_option = $DB->get_records('question_calculated_options', array('question' => $question->id));
            if (count($calculated_option) > 0) {
                $questions[$keyQuestion]->my_item['question_calculated_options'] = $calculated_option;
            }

            /* CALCULATED */
            $calculated = $DB->get_records('question_calculated', array('question' => $question->id));
            if (count($calculated) > 0) {
                $questions[$keyQuestion]->my_item['question_calculated'] = $calculated;
            }

            /* MULTICHOICE OPTION */
            $multiopt = $DB->get_records('qtype_multichoice_options', array('questionid' => $question->id));
            if (count($multiopt) > 0) {
                $questions[$keyQuestion]->my_item['qtype_multichoice_options'] = $multiopt;
            }

            /* MULTIANSWER */
            $multianswer = $DB->get_records('question_multianswer', array('question' => $question->id));
            if (count($multianswer) > 0) {
                $questions[$keyQuestion]->my_item['question_multianswer'] = $multianswer;
            }

            /* RANDOMSAMATCH OPTION */
            $randomsamatch = $DB->get_records('qtype_randomsamatch_options', array('questionid' => $question->id));
            if (count($randomsamatch) > 0) {
                $questions[$keyQuestion]->my_item['qtype_randomsamatch_options'] = $randomsamatch;
            }

            /* SHORTANSWER OPTION */
            $shortopt = $DB->get_records('qtype_shortanswer_options', array('questionid' => $question->id));
            if (count($shortopt) > 0) {
                $questions[$keyQuestion]->my_item['qtype_shortanswer_options'] = $shortopt;
            }

            /* ESSAY */
            $essay = $DB->get_records('qtype_essay_options', array('questionid' => $question->id));
            if (count($essay) > 0) {
                $questions[$keyQuestion]->my_item['qtype_essay_options'] = $essay;
            }

            /* MATCH SUBQUESTION */
            $matchsub = $DB->get_records('qtype_match_subquestions', array('questionid' => $question->id));
            if (count($matchsub) > 0) {
                $questions[$keyQuestion]->my_item['qtype_match_subquestions'] = $matchsub;
            }

            /* MATCH OPTION */
            $matchopt = $DB->get_records('qtype_match_options', array('questionid' => $question->id));
            if (count($matchopt) > 0) {
                $questions[$keyQuestion]->my_item['qtype_match_options'] = $matchopt;
            }

            /* DATASET */
            $dataset = $DB->get_records('question_datasets', array('question' => $question->id));
            if (count($dataset) > 0) {
                $questions[$keyQuestion]->my_item['question_datasets'] = $dataset;
            }
        }
        $quizSlots[$keySlot]->my_item['question'] = $questions;
    }

    return (($quizSlots) ? array('quiz_slots' => $quizSlots) : null);
}

function getMyBook($book) {
    global $DB;

    $chapter = $DB->get_records('book_chapters', array('bookid' => $book->id));
    return (($chapter) ? array('book_chapters' => $chapter) : null);
}