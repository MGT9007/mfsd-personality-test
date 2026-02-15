<?php
/**
 * Plugin Name: MFSD Personality Test
 * Description: Standalone personality test plugin with MBTI and DISC assessments, AI summaries, and week-based configuration.
 * Version: 1.0.1
 * Author: MisterT9007
 */

if (!defined('ABSPATH')) exit;

final class MFSD_Personality_Test {
    const VERSION = '1.0.0';
    const NONCE_ACTION = 'mfsd_ptest_nonce';

    const TBL_QUESTIONS = 'mfsd_ptest_questions';
    const TBL_ANSWERS = 'mfsd_ptest_answers';
    const TBL_RESULTS = 'mfsd_ptest_results';

    public static function instance() {
        static $i = null;
        return $i ?: $i = new self();
    }
    
    private function __construct() {
        register_activation_hook(__FILE__, array($this, 'install'));
        add_action('init', array($this,'assets'));
        add_shortcode('mfsd_personality_test', array($this,'shortcode'));
        add_action('rest_api_init', array($this,'register_routes'));
        add_action('admin_menu', array($this,'admin_menu'));
    }

    public function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $q = $wpdb->prefix . self::TBL_QUESTIONS;
        $a = $wpdb->prefix . self::TBL_ANSWERS;
        $r = $wpdb->prefix . self::TBL_RESULTS;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Questions table - supports MBTI and DISC question types
        dbDelta("CREATE TABLE $q (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          q_order INT NOT NULL DEFAULT 0,
          q_type ENUM('MBTI','DISC') NOT NULL DEFAULT 'MBTI',
          q_text TEXT NOT NULL,
          mbti_axis CHAR(1) NULL COMMENT 'E/I, S/N, T/F, J/P',
          mbti_letter CHAR(1) NULL COMMENT 'E,I,S,N,T,F,J,P',
          disc_mapping JSON NULL COMMENT 'D,I,S,C contribution values',
          w1 TINYINT(1) DEFAULT 0,
          w2 TINYINT(1) DEFAULT 0,
          w3 TINYINT(1) DEFAULT 0,
          w4 TINYINT(1) DEFAULT 0,
          w5 TINYINT(1) DEFAULT 0,
          w6 TINYINT(1) DEFAULT 0,
          PRIMARY KEY (id),
          KEY idx_type (q_type),
          KEY idx_order (q_order)
        ) $charset;");

        // Answers table - stores individual question responses
        dbDelta("CREATE TABLE $a (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id BIGINT UNSIGNED NOT NULL,
          week_num TINYINT NOT NULL,
          question_id BIGINT UNSIGNED NOT NULL,
          q_type ENUM('MBTI','DISC') NOT NULL,
          answer VARCHAR(10) NOT NULL COMMENT 'R/A/G for MBTI, 1-5 for DISC',
          mbti_axis CHAR(1) NULL,
          mbti_letter CHAR(1) NULL,
          d_contribution FLOAT NULL,
          i_contribution FLOAT NULL,
          s_contribution FLOAT NULL,
          c_contribution FLOAT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_user_week (user_id, week_num),
          KEY idx_user_question (user_id, question_id)
        ) $charset;");

        // Results table - stores final personality assessments and AI summaries
        dbDelta("CREATE TABLE $r (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id BIGINT UNSIGNED NOT NULL,
          week_num TINYINT NOT NULL,
          test_type ENUM('MBTI','DISC','COMBINED') NOT NULL,
          mbti_type CHAR(4) NULL,
          mbti_details JSON NULL,
          disc_d_score FLOAT NULL,
          disc_i_score FLOAT NULL,
          disc_s_score FLOAT NULL,
          disc_c_score FLOAT NULL,
          disc_primary VARCHAR(20) NULL,
          disc_details JSON NULL,
          ai_summary LONGTEXT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uniq_user_week_type (user_id, week_num, test_type),
          KEY idx_user (user_id)
        ) $charset;");

        // Insert sample questions
        $this->insert_sample_mbti_questions();
        $this->insert_sample_disc_questions();
    }

    private function insert_sample_mbti_questions() {
        global $wpdb;
        $table = $wpdb->prefix . self::TBL_QUESTIONS;
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE q_type = 'MBTI'");
        if ($count > 0) return;

        $mbti_questions = array(
            // E/I axis
            array('order' => 1, 'text' => 'I enjoy being the center of attention at social gatherings.', 'axis' => '1', 'letter' => 'E'),
            array('order' => 2, 'text' => 'I prefer quiet evenings at home over busy social events.', 'axis' => '1', 'letter' => 'I'),
            array('order' => 3, 'text' => 'I feel energized after spending time with large groups.', 'axis' => '1', 'letter' => 'E'),
            
            // S/N axis
            array('order' => 4, 'text' => 'I focus on practical details rather than big-picture ideas.', 'axis' => '2', 'letter' => 'S'),
            array('order' => 5, 'text' => 'I often think about future possibilities and abstract concepts.', 'axis' => '2', 'letter' => 'N'),
            array('order' => 6, 'text' => 'I prefer concrete facts over theoretical discussions.', 'axis' => '2', 'letter' => 'S'),
            
            // T/F axis
            array('order' => 7, 'text' => 'I make decisions based on logic rather than feelings.', 'axis' => '3', 'letter' => 'T'),
            array('order' => 8, 'text' => 'I consider how decisions will affect people emotionally.', 'axis' => '3', 'letter' => 'F'),
            array('order' => 9, 'text' => 'I value objective analysis over personal values.', 'axis' => '3', 'letter' => 'T'),
            
            // J/P axis
            array('order' => 10, 'text' => 'I like to have a clear plan and stick to schedules.', 'axis' => '4', 'letter' => 'J'),
            array('order' => 11, 'text' => 'I prefer to keep my options open and be spontaneous.', 'axis' => '4', 'letter' => 'P'),
            array('order' => 12, 'text' => 'I feel uncomfortable when things are left unfinished.', 'axis' => '4', 'letter' => 'J'),
        );

        foreach ($mbti_questions as $q) {
            $wpdb->insert($table, array(
                'q_order' => $q['order'],
                'q_type' => 'MBTI',
                'q_text' => $q['text'],
                'mbti_axis' => $q['axis'],
                'mbti_letter' => $q['letter'],
                'w1' => 1,
                'w2' => 1,
                'w3' => 1,
                'w4' => 1,
                'w5' => 1,
                'w6' => 1,
            ));
        }
    }

    private function insert_sample_disc_questions() {
        global $wpdb;
        $table = $wpdb->prefix . self::TBL_QUESTIONS;
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE q_type = 'DISC'");
        if ($count > 0) return;

        $disc_questions = array(
            array('order' => 13, 'text' => 'I am assertive and direct in my communication.', 'mapping' => '{"D":1,"I":0,"S":0,"C":0}'),
            array('order' => 14, 'text' => 'I enjoy being around people and socializing.', 'mapping' => '{"D":0,"I":1,"S":0,"C":0}'),
            array('order' => 15, 'text' => 'I prefer stable, predictable environments.', 'mapping' => '{"D":0,"I":0,"S":1,"C":0}'),
            array('order' => 16, 'text' => 'I pay close attention to details and accuracy.', 'mapping' => '{"D":0,"I":0,"S":0,"C":1}'),
            array('order' => 17, 'text' => 'I like to take charge and make quick decisions.', 'mapping' => '{"D":1,"I":0,"S":0,"C":0}'),
            array('order' => 18, 'text' => 'I am enthusiastic and optimistic about new ideas.', 'mapping' => '{"D":0,"I":1,"S":0,"C":0}'),
            array('order' => 19, 'text' => 'I am patient and supportive with others.', 'mapping' => '{"D":0,"I":0,"S":1,"C":0}'),
            array('order' => 20, 'text' => 'I follow rules and procedures carefully.', 'mapping' => '{"D":0,"I":0,"S":0,"C":1}'),
        );

        foreach ($disc_questions as $q) {
            $wpdb->insert($table, array(
                'q_order' => $q['order'],
                'q_type' => 'DISC',
                'q_text' => $q['text'],
                'disc_mapping' => $q['mapping'],
                'w1' => 1,
                'w2' => 1,
                'w3' => 1,
                'w4' => 1,
                'w5' => 1,
                'w6' => 1,
            ));
        }
    }

    public function assets() {
        $h = 'mfsd-personality-test';
        $base = plugin_dir_url(__FILE__);

        wp_register_script($h, $base . 'assets/mfsd-personality-test.js', array('wp-element'), self::VERSION, true);
        wp_register_style($h, $base . 'assets/mfsd-personality-test.css', array(), self::VERSION);
    }

    public function shortcode($atts) {
        $week = 1;
        if (is_page()) {
            $title = get_the_title();
            error_log('MFSD Personality Test: Page title is: ' . $title);
            
            if (preg_match('/Week\s*([1-6])/i', $title, $m)) {
                $week = (int) $m[1];
                error_log('MFSD Personality Test: Extracted week number: ' . $week);
            } else {
                error_log('MFSD Personality Test: Could not extract week from title, using default week 1');
            }
        }

        wp_localize_script('mfsd-personality-test', 'MFSD_PTEST_CFG', array(
            'restUrlQuestions'    => esc_url_raw(rest_url('mfsd-ptest/v1/questions')),
            'restUrlAnswer'       => esc_url_raw(rest_url('mfsd-ptest/v1/answer')),
            'restUrlSummary'      => esc_url_raw(rest_url('mfsd-ptest/v1/summary')),
            'restUrlStatus'       => esc_url_raw(rest_url('mfsd-ptest/v1/status')),
            'restUrlIntro'        => esc_url_raw(rest_url('mfsd-ptest/v1/intro')),
            'restUrlGuidance'     => esc_url_raw(rest_url('mfsd-ptest/v1/question-guidance')),
            'restUrlQuestionChat' => esc_url_raw(rest_url('mfsd-ptest/v1/question-chat')),
            'nonce'               => wp_create_nonce('wp_rest'),
            'week'                => $week,
        ));

        wp_enqueue_script('mfsd-personality-test');
        wp_enqueue_style('mfsd-personality-test');

        $chat_html = do_shortcode('[mwai_chatbot id="chatbot-vxk8pu"]');

        return '<div id="mfsd-ptest-root"></div>'
             . '<div id="mfsd-ptest-chat-source" style="display:none">' . $chat_html . '</div>';
    }

    public function register_routes() {
        register_rest_route('mfsd-ptest/v1', '/questions', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'api_questions'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        register_rest_route('mfsd-ptest/v1', '/answer', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'api_answer'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        register_rest_route('mfsd-ptest/v1', '/summary', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'api_summary'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        register_rest_route('mfsd-ptest/v1', '/status', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'api_status'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        register_rest_route('mfsd-ptest/v1', '/intro', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'api_intro'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        register_rest_route('mfsd-ptest/v1', '/question-guidance', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'api_question_guidance'),
            'permission_callback' => array($this, 'check_permission'),
        ));

        register_rest_route('mfsd-ptest/v1', '/question-chat', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array($this, 'api_question_chat'),
            'permission_callback' => array($this, 'check_permission'),
        ));
    }

    public function check_permission() {
        return is_user_logged_in();
    }

    public function api_questions($req) {
        $week = (int) $req->get_param('week') ?: 1;
        if ($week < 1 || $week > 6) {
            return new WP_Error('invalid_week', 'Week must be 1-6', array('status' => 400));
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TBL_QUESTIONS;
        $col = 'w' . $week;

        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT id, q_order, q_type, q_text, mbti_axis, mbti_letter, disc_mapping 
             FROM $table 
             WHERE $col = 1 
             ORDER BY q_order ASC"
        ), ARRAY_A);

        foreach ($questions as &$q) {
            if (!empty($q['disc_mapping'])) {
                $q['disc_mapping'] = json_decode($q['disc_mapping'], true);
            }
        }

        return array(
            'ok' => true,
            'questions' => $questions,
            'week' => $week
        );
    }

    public function api_answer($req) {
        $user_id = get_current_user_id();
        $week = (int) $req->get_param('week');
        $question_id = (int) $req->get_param('question_id');
        $q_type = $req->get_param('q_type');
        $answer = $req->get_param('answer');

        if (!$user_id || !$week || !$question_id || !$q_type) {
            return new WP_Error('missing_params', 'Missing required parameters', array('status' => 400));
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TBL_ANSWERS;

        $data = array(
            'user_id' => $user_id,
            'week_num' => $week,
            'question_id' => $question_id,
            'q_type' => $q_type,
            'answer' => $answer,
        );

        if ($q_type === 'MBTI') {
            $data['mbti_axis'] = $req->get_param('axis');
            $data['mbti_letter'] = $req->get_param('letter');
        } elseif ($q_type === 'DISC') {
            $data['d_contribution'] = $req->get_param('d_contribution');
            $data['i_contribution'] = $req->get_param('i_contribution');
            $data['s_contribution'] = $req->get_param('s_contribution');
            $data['c_contribution'] = $req->get_param('c_contribution');
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND week_num = %d AND question_id = %d",
            $user_id, $week, $question_id
        ));

        if ($existing) {
            $wpdb->update($table, $data, array('id' => $existing));
        } else {
            $wpdb->insert($table, $data);
        }

        return array('ok' => true);
    }

    public function api_status($req) {
        $user_id = get_current_user_id();
        $week = (int) $req->get_param('week') ?: 1;

        global $wpdb;
        $ans_table = $wpdb->prefix . self::TBL_ANSWERS;
        $res_table = $wpdb->prefix . self::TBL_RESULTS;
        $q_table = $wpdb->prefix . self::TBL_QUESTIONS;

        $completed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $res_table WHERE user_id = %d AND week_num = %d",
            $user_id, $week
        ));

        if ($completed > 0) {
            return array(
                'ok' => true,
                'status' => 'completed',
                'week' => $week
            );
        }

        $col = 'w' . $week;
        $total_questions = $wpdb->get_var("SELECT COUNT(*) FROM $q_table WHERE $col = 1");

        $answered = $wpdb->get_results($wpdb->prepare(
            "SELECT question_id FROM $ans_table WHERE user_id = %d AND week_num = %d",
            $user_id, $week
        ), ARRAY_A);

        $answered_ids = array_map(function($a) { return (int)$a['question_id']; }, $answered);

        if (count($answered) === 0) {
            return array(
                'ok' => true,
                'status' => 'not_started',
                'week' => $week,
                'total_questions' => $total_questions
            );
        }

        $last = $wpdb->get_var($wpdb->prepare(
            "SELECT question_id FROM $ans_table 
             WHERE user_id = %d AND week_num = %d 
             ORDER BY created_at DESC LIMIT 1",
            $user_id, $week
        ));

        return array(
            'ok' => true,
            'status' => 'in_progress',
            'week' => $week,
            'total_questions' => $total_questions,
            'answered_count' => count($answered),
            'answered_question_ids' => $answered_ids,
            'last_question_id' => (int)$last
        );
    }

    public function api_intro($req) {
        $week = (int) $req->get_param('week') ?: 1;

        global $wpdb;
        $q_table = $wpdb->prefix . self::TBL_QUESTIONS;
        $col = 'w' . $week;

        $mbti_count = $wpdb->get_var("SELECT COUNT(*) FROM $q_table WHERE q_type = 'MBTI' AND $col = 1");
        $disc_count = $wpdb->get_var("SELECT COUNT(*) FROM $q_table WHERE q_type = 'DISC' AND $col = 1");
        $total = $mbti_count + $disc_count;

        $test_types = array();
        if ($mbti_count > 0) $test_types[] = 'Myers-Briggs (MBTI)';
        if ($disc_count > 0) $test_types[] = 'DISC';
        $test_type_str = implode(' and ', $test_types);

        $prompt = "You are a friendly educational assistant for students aged 12-14. Generate a brief, encouraging introduction (2-3 sentences) for a personality test. The test includes {$test_type_str} with {$total} total questions. Keep it light, fun, and reassuring that there are no wrong answers. Make it engaging for young teens.";

        $intro_message = $this->call_ai($prompt);

        if (!$intro_message) {
            $intro_message = "Welcome to your Week {$week} Personality Test! You'll answer {$total} questions about {$test_type_str}. This is just for fun to help you understand yourself better - there are no wrong answers!";
        }

        return array(
            'ok' => true,
            'intro_message' => $intro_message,
            'test_types' => $test_type_str,
            'total_questions' => $total,
            'mbti_count' => $mbti_count,
            'disc_count' => $disc_count
        );
    }

    public function api_question_guidance($req) {
        $question_text = $req->get_param('question_text');
        $q_type = $req->get_param('q_type');

        $prompt = "You are helping a 12-14 year old student understand a personality test question. The question is: '{$question_text}'. Provide a brief (2-3 sentences) explanation to help them understand what the question is asking. Keep it simple, relatable, and age-appropriate.";

        $guidance = $this->call_ai($prompt);

        return array(
            'ok' => true,
            'guidance' => $guidance ?: 'Think about how you naturally behave in everyday situations.'
        );
    }

    public function api_question_chat($req) {
        $user_message = $req->get_param('message');
        $question_text = $req->get_param('question_text');

        $prompt = "You are a helpful tutor for 12-14 year olds. A student is working on this personality test question: '{$question_text}'. They asked: '{$user_message}'. Provide a brief, helpful answer that helps them understand the question better. Keep it friendly and age-appropriate.";

        $response = $this->call_ai($prompt);

        return array(
            'ok' => true,
            'response' => $response ?: 'I understand you need help. Try thinking about how you usually act in similar situations.'
        );
    }

    public function api_summary($req) {
        $user_id = get_current_user_id();
        $week = (int) $req->get_param('week');

        global $wpdb;
        $ans_table = $wpdb->prefix . self::TBL_ANSWERS;
        $res_table = $wpdb->prefix . self::TBL_RESULTS;

        $mbti_answers = $wpdb->get_results($wpdb->prepare(
            "SELECT mbti_axis, mbti_letter, answer FROM $ans_table 
             WHERE user_id = %d AND week_num = %d AND q_type = 'MBTI'",
            $user_id, $week
        ), ARRAY_A);

        $mbti_type = $this->calculate_mbti($mbti_answers);

        $disc_answers = $wpdb->get_results($wpdb->prepare(
            "SELECT d_contribution, i_contribution, s_contribution, c_contribution FROM $ans_table 
             WHERE user_id = %d AND week_num = %d AND q_type = 'DISC'",
            $user_id, $week
        ), ARRAY_A);

        $disc_scores = $this->calculate_disc($disc_answers);

        $summary_prompt = $this->build_summary_prompt($mbti_type, $disc_scores, $week);
        $ai_summary = $this->call_ai($summary_prompt);

        if ($mbti_type) {
            $wpdb->replace($res_table, array(
                'user_id' => $user_id,
                'week_num' => $week,
                'test_type' => 'MBTI',
                'mbti_type' => $mbti_type,
                'mbti_details' => json_encode(array('raw_answers' => $mbti_answers)),
                'ai_summary' => $ai_summary,
            ));
        }

        if ($disc_scores) {
            $wpdb->replace($res_table, array(
                'user_id' => $user_id,
                'week_num' => $week,
                'test_type' => 'DISC',
                'disc_d_score' => $disc_scores['D'],
                'disc_i_score' => $disc_scores['I'],
                'disc_s_score' => $disc_scores['S'],
                'disc_c_score' => $disc_scores['C'],
                'disc_primary' => $disc_scores['primary'],
                'disc_details' => json_encode($disc_scores),
                'ai_summary' => $ai_summary,
            ));
        }

        $wpdb->replace($res_table, array(
            'user_id' => $user_id,
            'week_num' => $week,
            'test_type' => 'COMBINED',
            'mbti_type' => $mbti_type,
            'disc_d_score' => $disc_scores['D'] ?? null,
            'disc_i_score' => $disc_scores['I'] ?? null,
            'disc_s_score' => $disc_scores['S'] ?? null,
            'disc_c_score' => $disc_scores['C'] ?? null,
            'disc_primary' => $disc_scores['primary'] ?? null,
            'ai_summary' => $ai_summary,
        ));

        return array(
            'ok' => true,
            'mbti_type' => $mbti_type,
            'disc_scores' => $disc_scores,
            'ai_summary' => $ai_summary
        );
    }

    private function calculate_mbti($answers) {
        if (empty($answers)) return null;

        $scores = array('1' => array(), '2' => array(), '3' => array(), '4' => array());

        foreach ($answers as $a) {
            $axis = $a['mbti_axis'];
            $letter = $a['mbti_letter'];
            $answer = $a['answer'];

            if (!isset($scores[$axis][$letter])) {
                $scores[$axis][$letter] = 0;
            }

            if ($answer === 'G') {
                $scores[$axis][$letter] += 2;
            } elseif ($answer === 'A') {
                $scores[$axis][$letter] += 1;
            }
        }

        $type = '';
        $axis_map = array('1' => array('E', 'I'), '2' => array('S', 'N'), '3' => array('T', 'F'), '4' => array('J', 'P'));

        foreach ($axis_map as $axis => $letters) {
            $score_a = $scores[$axis][$letters[0]] ?? 0;
            $score_b = $scores[$axis][$letters[1]] ?? 0;
            $type .= $score_a >= $score_b ? $letters[0] : $letters[1];
        }

        return $type;
    }

    private function calculate_disc($answers) {
        if (empty($answers)) return null;

        $totals = array('D' => 0, 'I' => 0, 'S' => 0, 'C' => 0);

        foreach ($answers as $a) {
            $totals['D'] += (float)$a['d_contribution'];
            $totals['I'] += (float)$a['i_contribution'];
            $totals['S'] += (float)$a['s_contribution'];
            $totals['C'] += (float)$a['c_contribution'];
        }

        $sum = array_sum($totals);
        if ($sum > 0) {
            foreach ($totals as $k => $v) {
                $totals[$k] = round(($v / $sum) * 100, 1);
            }
        }

        $primary = array_keys($totals, max($totals))[0];

        return array(
            'D' => $totals['D'],
            'I' => $totals['I'],
            'S' => $totals['S'],
            'C' => $totals['C'],
            'primary' => $primary,
            'percentages' => $totals
        );
    }

    private function build_summary_prompt($mbti_type, $disc_scores, $week) {
        $prompt = "You are an educational counselor providing personality test results to a 12-14 year old student. ";
        $prompt .= "This is their Week {$week} results. ";

        if ($mbti_type) {
            $prompt .= "Their MBTI type is {$mbti_type}. ";
        }

        if ($disc_scores) {
            $primary = $disc_scores['primary'];
            $prompt .= "Their primary DISC style is {$primary} (Dominance: {$disc_scores['D']}%, Influence: {$disc_scores['I']}%, Steadiness: {$disc_scores['S']}%, Conscientiousness: {$disc_scores['C']}%). ";
        }

        $prompt .= "Write a friendly, encouraging summary (4-5 sentences) explaining what these results mean about their personality, strengths, and how they might approach school and friendships. Keep it positive, age-appropriate, and relatable for young teens.";

        return $prompt;
    }

    private function call_ai($prompt) {
        if (!function_exists('mwai_core')) {
            error_log('MFSD Personality Test: MWAI plugin not available');
            return null;
        }

        try {
            $ai = Meow_MWAI_Core::get_instance();
            $query = new Meow_MWAI_Query_Text($prompt);
            $query->set_max_tokens(500);
            $reply = $ai->run($query);
            return $reply->result;
        } catch (Exception $e) {
            error_log('MFSD Personality Test AI Error: ' . $e->getMessage());
            return null;
        }
    }

    public function admin_menu() {
        add_menu_page(
            'Personality Test Admin',
            'Personality Test',
            'manage_options',
            'mfsd-ptest-admin',
            array($this, 'admin_page'),
            'dashicons-smiley',
            30
        );
    }

    public function admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TBL_QUESTIONS;

        if (isset($_POST['mfsd_ptest_add_question'])) {
            check_admin_referer('mfsd_ptest_add');
            $this->handle_add_question($_POST);
        }

        if (isset($_POST['mfsd_ptest_update_weeks'])) {
            check_admin_referer('mfsd_ptest_update');
            $this->handle_update_weeks($_POST);
        }

        if (isset($_GET['delete_question'])) {
            check_admin_referer('mfsd_ptest_delete_' . $_GET['delete_question']);
            $wpdb->delete($table, array('id' => (int)$_GET['delete_question']));
            echo '<div class="notice notice-success"><p>Question deleted.</p></div>';
        }

        $questions = $wpdb->get_results("SELECT * FROM $table ORDER BY q_type, q_order", ARRAY_A);

        include plugin_dir_path(__FILE__) . 'admin-page.php';
    }

    private function handle_add_question($data) {
        global $wpdb;
        $table = $wpdb->prefix . self::TBL_QUESTIONS;

        $insert_data = array(
            'q_order' => (int)$data['q_order'],
            'q_type' => sanitize_text_field($data['q_type']),
            'q_text' => sanitize_textarea_field($data['q_text']),
            'w1' => isset($data['w1']) ? 1 : 0,
            'w2' => isset($data['w2']) ? 1 : 0,
            'w3' => isset($data['w3']) ? 1 : 0,
            'w4' => isset($data['w4']) ? 1 : 0,
            'w5' => isset($data['w5']) ? 1 : 0,
            'w6' => isset($data['w6']) ? 1 : 0,
        );

        if ($data['q_type'] === 'MBTI') {
            $insert_data['mbti_axis'] = sanitize_text_field($data['mbti_axis']);
            $insert_data['mbti_letter'] = sanitize_text_field($data['mbti_letter']);
        } elseif ($data['q_type'] === 'DISC') {
            $mapping = array(
                'D' => (float)$data['disc_d'],
                'I' => (float)$data['disc_i'],
                'S' => (float)$data['disc_s'],
                'C' => (float)$data['disc_c'],
            );
            $insert_data['disc_mapping'] = json_encode($mapping);
        }

        $wpdb->insert($table, $insert_data);
        echo '<div class="notice notice-success"><p>Question added successfully!</p></div>';
    }

    private function handle_update_weeks($data) {
        global $wpdb;
        $table = $wpdb->prefix . self::TBL_QUESTIONS;

        foreach ($data['questions'] as $q_id => $weeks) {
            $update = array(
                'w1' => isset($weeks['w1']) ? 1 : 0,
                'w2' => isset($weeks['w2']) ? 1 : 0,
                'w3' => isset($weeks['w3']) ? 1 : 0,
                'w4' => isset($weeks['w4']) ? 1 : 0,
                'w5' => isset($weeks['w5']) ? 1 : 0,
                'w6' => isset($weeks['w6']) ? 1 : 0,
            );
            $wpdb->update($table, $update, array('id' => (int)$q_id));
        }

        echo '<div class="notice notice-success"><p>Week settings updated!</p></div>';
    }
}

MFSD_Personality_Test::instance();
