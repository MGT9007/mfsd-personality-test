<?php
/**
 * Plugin Name: MFSD Personality Test
 * Description: Standalone personality test plugin with MBTI and DISC assessments, AI summaries, and week-based configuration.
 * Version: 3.3.0
 * Author: MisterT9007
 */

if (!defined('ABSPATH')) exit;

final class MFSD_Personality_Test {
    const VERSION = '3.3.0';
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
        $force_regenerate = $req->get_param('force_regenerate') === 'true';

        global $wpdb;
        $ans_table = $wpdb->prefix . self::TBL_ANSWERS;
        $res_table = $wpdb->prefix . self::TBL_RESULTS;

        // Check if caching is enabled
        $cache_enabled = get_option('mfsd_ptest_cache_ai_summaries', '1') === '1';

        // If caching is enabled and not forcing regeneration, check for existing results
        if ($cache_enabled && !$force_regenerate) {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $res_table WHERE user_id = %d AND week_num = %d AND test_type = 'COMBINED'",
                $user_id, $week
            ), ARRAY_A);

            if ($existing && !empty($existing['ai_summary'])) {
                // Return cached results
                return array(
                    'ok' => true,
                    'mbti_type' => $existing['mbti_type'],
                    'disc_scores' => array(
                        'D' => (float)$existing['disc_d_score'],
                        'I' => (float)$existing['disc_i_score'],
                        'S' => (float)$existing['disc_s_score'],
                        'C' => (float)$existing['disc_c_score'],
                        'primary' => $existing['disc_primary']
                    ),
                    'ai_summary' => $existing['ai_summary'],
                    'cached' => true
                );
            }
        }

        // Generate new results (either cache disabled or no cached results exist)
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

        // If AI summary is empty or too short, create a rich fallback
        if (!$ai_summary || strlen($ai_summary) < 200) {
            error_log('MFSD Personality Test: AI summary too short or empty, using fallback');
            $ai_summary = $this->generate_fallback_summary($mbti_type, $disc_scores, $week);
        }

        // Save results if caching is enabled
        if ($cache_enabled) {
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
        }

        return array(
            'ok' => true,
            'mbti_type' => $mbti_type,
            'disc_scores' => $disc_scores,
            'ai_summary' => $ai_summary,
            'cached' => false
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
        $prompt .= "This is their Week {$week} personality assessment results.\n\n";

        if ($mbti_type) {
            $mbti_context = $this->get_mbti_type_context($mbti_type);
            $prompt .= "=== MBTI ASSESSMENT ===\n";
            $prompt .= "Type: {$mbti_type} - {$mbti_context['nickname']}\n";
            $prompt .= "Group: {$mbti_context['group']}\n\n";
            $prompt .= "Core Description: {$mbti_context['description']}\n\n";
            $prompt .= "Key Strengths: {$mbti_context['strengths']}\n\n";
            $prompt .= "Learning Style: {$mbti_context['learning_style']}\n\n";
            $prompt .= "Communication Style: {$mbti_context['communication']}\n\n";
            $prompt .= "Potential Career Interests: {$mbti_context['careers']}\n\n";
            $prompt .= "Advice for Teens: {$mbti_context['teen_advice']}\n\n";
        }

        if ($disc_scores) {
            $disc_context = $this->get_disc_style_context($disc_scores['primary']);
            $primary = $disc_scores['primary'];
            $prompt .= "=== DISC PROFILE ===\n";
            $prompt .= "Primary Style: {$primary} - {$disc_context['name']}\n";
            $prompt .= "Scores: D={$disc_scores['D']}%, I={$disc_scores['I']}%, S={$disc_scores['S']}%, C={$disc_scores['C']}%\n\n";
            $prompt .= "Characteristics: {$disc_context['characteristics']}\n\n";
            $prompt .= "What Motivates Them: {$disc_context['motivations']}\n\n";
            $prompt .= "Growth Areas: {$disc_context['challenges']}\n\n";
            $prompt .= "Learning Preferences: {$disc_context['learning']}\n\n";
            $prompt .= "Teen Strengths: {$disc_context['strengths_for_teens']}\n\n";
            $prompt .= "Advice: {$disc_context['advice']}\n\n";
        }

        $prompt .= "=== YOUR TASK ===\n";
        $prompt .= "Based on this comprehensive personality profile, write a warm, personal, and encouraging summary (6-8 sentences) that:\n\n";
        $prompt .= "1. Opens with a warm greeting that acknowledges their unique personality type\n";
        $prompt .= "2. Explains what their results mean in simple, relatable terms they can understand\n";
        $prompt .= "3. Highlights their natural strengths and talents with specific examples\n";
        $prompt .= "4. Gives practical, actionable advice for school success based on their learning style\n";
        $prompt .= "5. Explains how they naturally interact with friends and how to build great friendships\n";
        $prompt .= "6. Suggests specific activities, subjects, or roles where they might excel\n";
        $prompt .= "7. Acknowledges any growth areas gently and positively, framing them as opportunities\n";
        $prompt .= "8. Ends with an encouraging message that all personality types are valuable and needed\n\n";
        $prompt .= "IMPORTANT GUIDELINES:\n";
        $prompt .= "- Write in second person (\"You are...\" \"Your strength is...\")\n";
        $prompt .= "- Use warm, encouraging, conversational tone\n";
        $prompt .= "- Be specific and personal, not generic\n";
        $prompt .= "- Use relatable examples from teen life (school, friends, hobbies, sports)\n";
        $prompt .= "- Keep language simple and age-appropriate for 12-14 year olds\n";
        $prompt .= "- Be positive but authentic - don't oversell or sound fake\n";
        $prompt .= "- Make them feel understood, valued, and excited about who they are\n";

        return $prompt;
    }

    private function get_mbti_type_context($type) {
        $mbti_data = array(
            'ISTJ' => array(
                'group' => 'Sentinel',
                'nickname' => 'The Logistician',
                'description' => 'Practical, fact-minded, and incredibly reliable. ISTJs are the rock-solid organizers who get things done right. They value tradition, responsibility, and doing things the proper way.',
                'strengths' => 'Excellent at planning and organizing, super responsible and dependable, amazing attention to detail, loyal friend who keeps promises, great at following through on commitments, calm under pressure',
                'learning_style' => 'Learn best with clear structure, step-by-step instructions, and practical real-world examples. Prefer written materials, organized notes, and having time to process information thoroughly before tests. Love checklists and study schedules.',
                'communication' => 'Direct, honest, and factual. Prefer clear, specific conversations without drama. May seem quiet or reserved at first, but are thoughtful listeners who remember details. Not fans of small talk - prefer meaningful, purposeful conversations.',
                'careers' => 'Accounting, engineering, law enforcement, military officer, healthcare administration, project management, quality control, data analysis, logistics, architecture',
                'teen_advice' => 'Your reliability is your superpower - friends know they can count on you. Use your organizing skills for school projects and sports teams. Don\'t be afraid to loosen up sometimes - not everything needs a perfect plan. Your consistency will take you far!'
            ),
            'ISFJ' => array(
                'group' => 'Sentinel',
                'nickname' => 'The Defender',
                'description' => 'Warm-hearted protectors who are incredibly dedicated to helping and supporting others. ISFJs are the caring friends who remember birthdays, notice when someone is upset, and always show up when needed.',
                'strengths' => 'Incredibly supportive and caring, excellent memory for personal details, hardworking and diligent, patient with others, great at creating harmony, practical helper who notices what needs to be done',
                'learning_style' => 'Learn best in supportive, encouraging environments with clear expectations. Prefer hands-on practice, working with study partners, and helping classmates understand material. Remember information better when connecting it to people and real situations.',
                'communication' => 'Warm, considerate, and thoughtful. Excellent listeners who remember what people tell them. May avoid confrontation to keep peace. Express care through actions more than words. Very attuned to others\' feelings.',
                'careers' => 'Nursing, teaching, counseling, social work, librarian, customer service, interior design, veterinary assistant, childcare, human resources, office management',
                'teen_advice' => 'Your kindness makes the world better - never lose that caring heart. It\'s okay to say no sometimes and put yourself first. Your friends are lucky to have someone so loyal. Don\'t forget to take care of yourself while caring for others!'
            ),
            'INFJ' => array(
                'group' => 'Diplomat',
                'nickname' => 'The Advocate',
                'description' => 'Quiet, mystical idealists with deep insights about people and a strong sense of purpose. INFJs are the creative dreamers who want to make the world better and help others find their path.',
                'strengths' => 'Incredibly insightful about people\'s feelings and motivations, creative problem-solvers who think outside the box, passionate about causes they believe in, great at inspiring and encouraging others, strong sense of right and wrong, visionary thinkers',
                'learning_style' => 'Learn best through big-picture concepts, meaningful discussions, and exploring "why" behind facts. Enjoy creative projects, writing assignments, and subjects connected to helping people. Need quiet time to process deep thoughts.',
                'communication' => 'Prefer deep, meaningful one-on-one conversations over group small talk. Express complex ideas through metaphors, stories, and creative writing. Great listeners who understand unspoken feelings. May keep thoughts private until they know someone well.',
                'careers' => 'Counseling, writing, psychology, teaching, social work, ministry, human resources, art therapy, environmental advocacy, coaching, non-profit leadership',
                'teen_advice' => 'Your ability to understand people is rare and special - trust your intuition. Your ideas can change the world, so don\'t be afraid to share them. Find friends who appreciate deep conversations. It\'s okay to need alone time to recharge!'
            ),
            'INTJ' => array(
                'group' => 'Analyst',
                'nickname' => 'The Architect',
                'description' => 'Brilliant strategic thinkers with a plan for everything. INTJs are the masterminds who see patterns others miss and love solving complex puzzles. Independent, curious, and incredibly focused.',
                'strengths' => 'Strategic planning and long-term thinking, independent and self-motivated learners, innovative problem-solvers, confident in their well-researched ideas, excellent at seeing patterns and connections, determined to master difficult subjects',
                'learning_style' => 'Love complex theories, abstract concepts, and intellectual challenges. Prefer independent study, research projects, and solving difficult problems. Get bored with repetitive drills - need mental stimulation and new challenges to stay engaged.',
                'communication' => 'Logical, direct, and focused on ideas rather than feelings. May seem intense or serious because they care deeply about accuracy and truth. Prefer meaningful debates over casual chat. Respect competence and intelligence in others.',
                'careers' => 'Science research, engineering, computer programming, strategy consulting, medicine, law, architecture, game design, financial analysis, systems design',
                'teen_advice' => 'Your strategic mind is brilliant - use it to plan your future and solve tough problems. Remember that not everyone thinks as deeply as you do, and that\'s okay. Your ideas are valuable even if others don\'t understand them right away. Find intellectually curious friends!'
            ),
            'ISTP' => array(
                'group' => 'Explorer',
                'nickname' => 'The Virtuoso',
                'description' => 'Bold, practical hands-on problem solvers who are masters with tools and mechanics. ISTPs are the cool-headed troubleshooters who stay calm in crises and figure out how things work.',
                'strengths' => 'Excellent with tools, machines, and hands-on projects, calm and collected under pressure, practical problem-solvers who fix things, adaptable and flexible, great at sports requiring precision, logical troubleshooters',
                'learning_style' => 'Learn by doing and taking things apart to see how they work. Need hands-on experience, practical applications, and freedom to experiment. Get bored sitting still listening to lectures - need physical activity and real-world practice.',
                'communication' => 'Brief, practical, and to-the-point. Focus on facts and solutions rather than emotions. May seem quiet but are sharp observers. Express themselves better through actions than words. Not into drama or emotional discussions.',
                'careers' => 'Mechanics, engineering, carpentry, athletics, emergency services (paramedic, firefighter), forensics, surgery, aviation, construction, video game design, extreme sports',
                'teen_advice' => 'Your hands-on skills are impressive - embrace projects where you build or fix things. Your calm attitude in emergencies is a real gift. Don\'t be afraid to show your adventurous side. Channel your energy into active hobbies and sports!'
            ),
            'ISFP' => array(
                'group' => 'Explorer',
                'nickname' => 'The Artist',
                'description' => 'Gentle, flexible souls with artistic flair who live in the moment and find beauty everywhere. ISFPs are the creative free spirits who express themselves through art, music, or nature.',
                'strengths' => 'Naturally artistic and creative, sensitive to beauty and aesthetics, kind and considerate to everyone, live fully in the present moment, excellent at hands-on creative work, harmonious and easy-going personality',
                'learning_style' => 'Learn best through artistic expression, hands-on activities, and sensory experiences. Prefer visual learning, creative projects, and freedom to explore at own pace. Struggle with rigid structure but thrive with flexibility.',
                'communication' => 'Gentle, considerate, and non-confrontational. Express feelings through art, music, or actions rather than words. Great at reading moods and responding sensitively. May avoid conflict and keep opinions private.',
                'careers' => 'Visual arts, graphic design, music, photography, veterinary care, massage therapy, chef, fashion design, nature conservation, interior design, cosmetology',
                'teen_advice' => 'Your artistic gifts make the world more beautiful - share your creativity! Trust your aesthetic sense and follow your inspiration. Don\'t let others pressure you into being louder than you are. Your gentle nature is a strength, not a weakness!'
            ),
            'INFP' => array(
                'group' => 'Diplomat',
                'nickname' => 'The Mediator',
                'description' => 'Poetic, idealistic souls with strong values who see potential for good in everyone. INFPs are the dreamers and writers who feel deeply and want to make the world more authentic and meaningful.',
                'strengths' => 'Deeply empathetic and compassionate, gifted creative writers and storytellers, passionate about personal values and causes, see the best in people, excellent at understanding complex emotions, imaginative and original thinkers',
                'learning_style' => 'Learn best when material connects to values, meaning, and helping others. Excel at creative writing, literature, and social studies. Enjoy personal projects where they can explore ideas deeply. Need connection to "why" material matters.',
                'communication' => 'Warm, empathetic, and excellent listeners who make others feel understood. Express ideas beautifully through creative writing and metaphors. May struggle to voice disagreement because they value harmony. Prefer deep conversations about meaning.',
                'careers' => 'Writing, counseling, teaching, psychology, social work, ministry, arts, librarian, human rights advocacy, music therapy, creative fields, non-profit work',
                'teen_advice' => 'Your empathy and idealism are gifts - the world needs dreamers like you! Your writing can touch hearts, so keep creating. Don\'t let harsh realities crush your hope. Find friends who appreciate your depth. It\'s okay to stand up for your values!'
            ),
            'INTP' => array(
                'group' => 'Analyst',
                'nickname' => 'The Logician',
                'description' => 'Brilliant innovators with an endless thirst for knowledge and understanding. INTPs are the logical puzzle-solvers who love learning new things and figuring out how systems work.',
                'strengths' => 'Exceptional analytical and logical thinking, love learning about everything, creative problem-solvers who find unique solutions, independent thinkers who question everything, excellent at understanding complex systems, naturally curious',
                'learning_style' => 'Love complex theories, logical systems, and intellectual exploration. Learn best through independent reading, online research, and solving challenging problems. Excel in math, science, and philosophy. Need freedom to explore ideas at own pace.',
                'communication' => 'Focus on ideas, logic, and accuracy over small talk. Enjoy intellectual debates and exploring theories. May seem absent-minded or lost in thought. Value precision in language. Can explain complex ideas clearly.',
                'careers' => 'Science, mathematics, computer programming, research, philosophy, engineering, game design, physics, economics, systems analysis, invention',
                'teen_advice' => 'Your brilliant mind is an incredible asset - never stop asking "why"! Your ideas might seem weird to others, but that\'s what makes them innovative. Find other curious minds to explore ideas with. Remember to take breaks from thinking and connect with people!'
            ),
            'ESTP' => array(
                'group' => 'Explorer',
                'nickname' => 'The Entrepreneur',
                'description' => 'Energetic action-takers who live in the moment and love excitement. ESTPs are the bold risk-takers who make things happen, think on their feet, and bring energy to any situation.',
                'strengths' => 'Quick thinking and fast reactions, incredibly energetic and fun-loving, great at negotiations and persuasion, practical hands-on learners, adaptable to change, excellent in crisis situations, natural athletes',
                'learning_style' => 'Learn by doing, taking action, and jumping right in. Need activity, variety, and real-world applications. Get restless sitting still listening to theory. Excel at learning through sports, projects, and hands-on experiments.',
                'communication' => 'Direct, energetic, and entertaining. Love lively conversations and telling exciting stories. Great at reading people and situations. May interrupt or dominate conversations. Natural salespeople and negotiators.',
                'careers' => 'Sales, entrepreneurship, emergency services, professional sports, entertainment, marketing, real estate, bartending, coaching, event planning, stock trading',
                'teen_advice' => 'Your energy and boldness are magnetic - people love being around you! Channel your risk-taking into sports, business, or adventures. Learn to think before acting sometimes. Your ability to adapt quickly is a major advantage in life!'
            ),
            'ESFP' => array(
                'group' => 'Explorer',
                'nickname' => 'The Entertainer',
                'description' => 'Spontaneous, enthusiastic performers who spread joy wherever they go. ESFPs are the life of the party who make others smile, live for fun experiences, and embrace every moment.',
                'strengths' => 'Incredibly friendly and outgoing, live fully in the present moment, encouraging and uplifting to others, practical and resourceful, excellent at bringing people together, natural performers who entertain easily',
                'learning_style' => 'Learn best through group activities, hands-on experiences, and social interaction. Need movement, variety, and fun. Excel in performing arts, group projects, and anything involving people. Struggle with abstract theory.',
                'communication' => 'Warm, enthusiastic, and very expressive. Love entertaining and making people laugh. Great at reading the room and keeping energy high. May struggle with serious or deep conversations. Prefer showing rather than telling.',
                'careers' => 'Entertainment, hospitality, teaching (especially young children), counseling, sales, event planning, childcare, fitness training, social media, restaurant service, tour guide',
                'teen_advice' => 'Your positive energy is contagious - you light up every room! Your talent for connecting with people is powerful. Remember that sometimes less is more with attention-seeking. Use your performance skills in drama, sports, or leadership. Stay true to your fun-loving self!'
            ),
            'ENFP' => array(
                'group' => 'Diplomat',
                'nickname' => 'The Campaigner',
                'description' => 'Enthusiastic, creative free spirits with endless optimism who see possibilities in everything. ENFPs are the inspiring dreamers who encourage others and find joy in connecting people and ideas.',
                'strengths' => 'Incredibly enthusiastic and inspiring to others, creative thinkers with wild imaginations, excellent with people and building connections, see possibilities and potential everywhere, passionate about many interests, natural encouragers',
                'learning_style' => 'Learn best through group discussions, creative projects, and exploring possibilities. Need variety, meaning, and connection to bigger picture. Excel in brainstorming, creative writing, and subjects involving people. Get bored with routine drills.',
                'communication' => 'Enthusiastic, expressive, and encouraging. Love brainstorming ideas and exploring possibilities. Great at inspiring others and making connections between concepts. May jump between topics excitedly. Very emotionally expressive.',
                'careers' => 'Teaching, counseling, journalism, arts, marketing, human resources, entrepreneurship, public relations, social work, photography, acting, coaching',
                'teen_advice' => 'Your enthusiasm and creativity are gifts that inspire others! Your ability to see potential in people is special. Learn to focus on finishing what you start. Surround yourself with people who appreciate your energy. Your optimism can change lives!'
            ),
            'ENTP' => array(
                'group' => 'Analyst',
                'nickname' => 'The Debater',
                'description' => 'Smart, quick-witted innovators who love intellectual challenges and can\'t resist a good debate. ENTPs are the clever idea-generators who question everything and find creative solutions.',
                'strengths' => 'Exceptionally quick-witted and clever, innovative idea-generators, great at brainstorming and thinking outside the box, enjoy intellectual challenges and debates, adaptable and resourceful, excellent at seeing different perspectives',
                'learning_style' => 'Learn through debate, exploring multiple perspectives, and challenging assumptions. Need intellectual stimulation and variety. Excel at arguing different viewpoints and finding logical flaws. Get bored with repetitive tasks.',
                'communication' => 'Lively debater who loves playing devil\'s advocate. Very quick-thinking and witty. Enjoy intellectual sparring and challenging others\' logic. May argue just for fun without realizing it bothers others. Natural storytellers.',
                'careers' => 'Law, engineering, entrepreneurship, consulting, invention, journalism, programming, marketing, business strategy, psychology, teaching (college level)',
                'teen_advice' => 'Your quick mind and clever ideas are impressive - use them for good! Remember that not everything needs to be debated - sometimes just agree. Your ability to see all sides of an issue is valuable. Channel your energy into entrepreneurship or invention!'
            ),
            'ESTJ' => array(
                'group' => 'Sentinel',
                'nickname' => 'The Executive',
                'description' => 'Organized, efficient leaders who get things done and done right. ESTJs are the natural managers who create order, set clear expectations, and make sure everyone follows through.',
                'strengths' => 'Extremely organized and efficient, natural leaders who take charge, practical and realistic about what works, excellent at following through on commitments, create clear systems and processes, honest and direct communicators',
                'learning_style' => 'Learn best with clear structure, proven methods, and practical applications. Prefer traditional teaching, textbooks, and measurable results. Excel at organizing study groups and creating detailed notes. Like knowing exactly what\'s expected.',
                'communication' => 'Direct, efficient, and no-nonsense. Good at giving clear instructions and expectations. Value honesty and responsibility. May come across as bossy without meaning to. Prefer action over endless discussion.',
                'careers' => 'Business management, law enforcement, military officer, project management, banking, accounting, operations management, school administration, quality control, logistics',
                'teen_advice' => 'Your leadership and organizational skills are valuable - use them to help your team succeed! Remember that not everyone works as efficiently as you, and that\'s okay. Your follow-through and reliability will open doors. Learn to appreciate different approaches!'
            ),
            'ESFJ' => array(
                'group' => 'Sentinel',
                'nickname' => 'The Consul',
                'description' => 'Warm, caring social coordinators who bring people together and make sure everyone feels included. ESFJs are the thoughtful friends who remember details, plan celebrations, and create community.',
                'strengths' => 'Incredibly warm and supportive, excellent at organizing people and events, loyal friends who remember everything, practical helpers who notice what needs doing, great at creating harmony in groups, naturally caring',
                'learning_style' => 'Learn best in supportive group settings with encouragement. Prefer hands-on learning, working with others, and helping classmates succeed. Remember information better when learning has social element. Like clear structure and positive feedback.',
                'communication' => 'Warm, friendly, and attentive. Love connecting with people and staying in touch. Very good at reading others\' emotions and responding supportively. May take criticism personally. Prefer cooperation over conflict.',
                'careers' => 'Teaching (especially elementary), nursing, social work, event planning, hospitality, office management, counseling, human resources, restaurant management, customer service',
                'teen_advice' => 'Your caring nature makes everyone feel valued - that\'s a superpower! Your ability to bring people together is special. Remember to take care of yourself too, not just others. Use your organizing skills to plan events and lead teams. People need friends like you!'
            ),
            'ENFJ' => array(
                'group' => 'Diplomat',
                'nickname' => 'The Protagonist',
                'description' => 'Charismatic, inspiring leaders who bring out the best in others. ENFJs are the natural mentors who see potential in everyone and motivate people to grow and succeed.',
                'strengths' => 'Incredibly inspiring and motivating, exceptional communicators and public speakers, deeply empathetic leaders who understand feelings, see potential in everyone, great at teaching and mentoring, natural at bringing out others\' best',
                'learning_style' => 'Learn best through group discussions, teaching others, and meaningful material. Need social interaction and connection to bigger purpose. Excel at presenting, leading study groups, and collaborative projects. Want to help classmates succeed.',
                'communication' => 'Warm, persuasive, and highly expressive. Natural teachers and motivational speakers. Very encouraging and supportive. Great at reading audience and adapting message. May try to "fix" others\' problems uninvited.',
                'careers' => 'Teaching, counseling, ministry, human resources, public relations, coaching, psychology, non-profit leadership, politics, training and development, social work',
                'teen_advice' => 'Your ability to inspire and lead others is remarkable - embrace it! Your encouragement changes lives, so keep lifting people up. Remember that not everyone wants advice, even when you see how to help. Your charisma and empathy will take you far!'
            ),
            'ENTJ' => array(
                'group' => 'Analyst',
                'nickname' => 'The Commander',
                'description' => 'Bold, strategic leaders with strong vision who make things happen. ENTJs are the determined commanders who set ambitious goals, create efficient plans, and mobilize people to achieve great things.',
                'strengths' => 'Exceptional strategic thinking and planning, confident leaders who inspire action, decisive and quick to make tough calls, efficient and goal-focused, excellent at organizing complex projects, natural at public speaking and debate',
                'learning_style' => 'Learn best through challenge, competition, and leadership opportunities. Prefer complex problems requiring strategic thinking. Excel at debates, presentations, and leading group projects. Want to understand systems and improve them.',
                'communication' => 'Direct, confident, and commanding. Natural at public speaking and persuasion. Very goal-focused - may seem impatient with inefficiency. Enjoy intellectual debates. May come across as too intense or bossy.',
                'careers' => 'Executive leadership, entrepreneurship, law, engineering management, consulting, finance, military officer, business strategy, operations director, political leadership',
                'teen_advice' => 'Your leadership and strategic vision are powerful - use them to create positive change! Remember that not everyone moves as fast as you, and that\'s okay. Your confidence inspires others. Balance your drive with patience for different working styles. You\'re destined to lead!'
            )
        );

        return isset($mbti_data[$type]) ? $mbti_data[$type] : array(
            'group' => 'Unique',
            'nickname' => 'The Individual',
            'description' => 'A unique personality type with individual characteristics',
            'strengths' => 'Personal strengths and talents',
            'learning_style' => 'Individual learning preferences',
            'communication' => 'Personal communication style',
            'careers' => 'Various career options',
            'teen_advice' => 'Embrace your unique qualities!'
        );
    }

    private function get_disc_style_context($primary) {
        $disc_data = array(
            'D' => array(
                'name' => 'Dominance',
                'characteristics' => 'Direct, results-oriented, decisive, competitive, and confident. Natural leaders who take charge, love challenges, and focus on winning and achieving goals. Move fast, make quick decisions, and push to get things done.',
                'motivations' => 'Motivated by winning, achieving goals, being in control, and overcoming challenges. Love seeing immediate results and being recognized for accomplishments. Thrive on competition and new challenges. Want to make things happen and see progress.',
                'challenges' => 'May seem too direct, blunt, or impatient to others. Can appear bossy or pushy without meaning to. Might prioritize tasks over people\'s feelings. Sometimes move too fast without thinking things through. Can benefit from slowing down, listening more, and considering how decisions affect others emotionally.',
                'learning' => 'Learn best through challenge, competition, and seeing quick results. Prefer independent work where they can set the pace. Like hands-on projects where they can lead and make decisions. Get bored with slow-paced instruction or too much detail. Want to know the bottom line and get to action.',
                'strengths_for_teens' => 'Excellent at sports (especially competitive ones), taking leadership roles in clubs or teams, making quick decisions when needed, standing up for what they believe in, organizing groups to accomplish goals, staying confident under pressure, and getting things done efficiently.',
                'advice' => 'Your drive and confidence are impressive - channel that competitive energy into positive goals! Remember that teamwork means letting others contribute too, not just leading. Your ability to take charge is valuable, but try listening to others\' ideas before deciding. Slow down sometimes and consider feelings, not just results. You\'re a natural leader!'
            ),
            'I' => array(
                'name' => 'Influence',
                'characteristics' => 'Enthusiastic, optimistic, outgoing, persuasive, and naturally social. Love being around people, making friends easily, and bringing energy to every situation. Expressive communicators who inspire and motivate others. Focus on fun and positive connections.',
                'motivations' => 'Motivated by social recognition, being liked and popular, having fun, connecting with lots of people, and expressing creativity. Love being the center of attention and making others happy. Thrive in social settings and with collaborative activities. Want to be appreciated and recognized.',
                'challenges' => 'May get distracted easily by social opportunities and have trouble focusing on boring tasks. Can overcommit to too many activities and struggle with follow-through. Might care too much about being liked. Sometimes forget important details or deadlines. Can benefit from better organization, time management, and focusing on finishing what they start.',
                'learning' => 'Learn best in groups, through discussion, and with interactive activities. Need social interaction and variety to stay engaged. Excel in presentations, group projects, and anything involving people. Struggle with solo studying and quiet environments. Remember information better through stories and personal connections.',
                'strengths_for_teens' => 'Amazing at making new friends anywhere, public speaking and presentations, cheering people up when they\'re down, creative activities and performing arts, getting people excited about ideas or events, being the social glue that brings groups together, and naturally connecting with all types of people.',
                'advice' => 'Your social skills and enthusiasm are magnetic - people love your positive energy! Use your gift for connecting people to build amazing friendships and teams. Remember to follow through on commitments, not just start things excitedly. Your optimism is contagious, but it\'s okay to be real about challenges too. Keep spreading joy - the world needs your light!'
            ),
            'S' => array(
                'name' => 'Steadiness',
                'characteristics' => 'Patient, supportive, reliable, calm, and loyal. Excellent team players who value stability, harmony, and helping others. Great listeners who create peaceful environments. Steady and consistent in everything they do. Prefer cooperation over competition.',
                'motivations' => 'Motivated by cooperation, helping others, maintaining stability, and building lasting relationships. Love being part of a supportive team where everyone gets along. Value security, predictability, and peaceful environments. Want to be needed and appreciated for their support and loyalty.',
                'challenges' => 'May avoid change or new situations even when needed. Can have trouble saying no or setting boundaries. Might stay in uncomfortable situations to avoid conflict. Sometimes too sensitive to criticism. May need others to push them to try new things. Can benefit from expressing opinions more directly, embracing change gradually, and speaking up about own needs.',
                'learning' => 'Learn best in stable, supportive environments with patient instruction. Prefer step-by-step guidance and time to practice. Excel when helping classmates or working in collaborative groups. Struggle with high-pressure competition or rapid changes. Need encouragement and reassurance when learning new things.',
                'strengths_for_teens' => 'Incredible at being the friend everyone can count on, listening when others need to talk, staying calm when everyone else is stressed, being patient with difficult people, creating peaceful environments, team sports where cooperation matters, and remembering to check on friends who are struggling.',
                'advice' => 'You\'re the steady, loyal friend everyone needs - never underestimate how valuable that is! Your calm presence helps others feel safe and supported. Don\'t be afraid to try new things - change can be good! It\'s okay to say no sometimes and put your needs first. Your patience and support make you an amazing friend and teammate. Keep being the rock others rely on!'
            ),
            'C' => array(
                'name' => 'Conscientiousness',
                'characteristics' => 'Analytical, precise, systematic, quality-focused, and detail-oriented. Love accuracy, following procedures, and doing things the right way. Careful thinkers who research thoroughly before deciding. High standards for themselves and their work. Prefer logic and facts over emotions.',
                'motivations' => 'Motivated by getting things right, achieving high quality, following proper procedures, mastering subjects deeply, and being seen as competent and accurate. Love detailed work where precision matters. Value expertise and correctness. Want clear standards and expectations.',
                'challenges' => 'May be too perfectionist and never feel work is good enough. Can worry too much about making mistakes. Sometimes overly critical of self and others. Might get stuck in analysis paralysis, researching forever without deciding. May miss big picture by focusing on details. Can benefit from accepting that "good enough" is sometimes okay, being less self-critical, and moving forward without perfect information.',
                'learning' => 'Learn best with clear standards, detailed instructions, and time for thorough study. Prefer independent research, reading, and mastering subjects deeply. Excel at detail-oriented subjects like math, science, and technical topics. Need time to process and verify information. Value accuracy over speed.',
                'strengths_for_teens' => 'Outstanding at detail-oriented subjects like math, science, and coding, research projects where accuracy matters, producing high-quality work consistently, catching errors others miss, following complex instructions perfectly, planning thoroughly before acting, and maintaining high standards.',
                'advice' => 'Your attention to detail and high standards are impressive gifts - quality matters! Remember that perfection isn\'t always possible or necessary - sometimes "good enough" really is good enough. Don\'t be so hard on yourself when you make mistakes - everyone does! Your analytical mind will take you far in technical fields. Trust yourself more and worry less. You\'re doing better than you think!'
            )
        );

        return isset($disc_data[$primary]) ? $disc_data[$primary] : array(
            'name' => 'Balanced',
            'characteristics' => 'A balanced blend of personality styles',
            'motivations' => 'Flexible motivations across different situations',
            'challenges' => 'Individual challenges and growth opportunities',
            'learning' => 'Adaptable learning style',
            'strengths_for_teens' => 'Versatile strengths',
            'advice' => 'Your balanced approach is valuable!'
        );
    }

    private function generate_fallback_summary($mbti_type, $disc_scores, $week) {
        $summary = "Welcome to your Week {$week} personality results! ";
        
        if ($mbti_type) {
            $mbti_context = $this->get_mbti_type_context($mbti_type);
            $summary .= "You're an {$mbti_type} - {$mbti_context['nickname']}. ";
            $summary .= $mbti_context['description'] . " ";
            $summary .= "Your strengths include: " . $mbti_context['strengths'] . " ";
        }
        
        if ($disc_scores) {
            $disc_context = $this->get_disc_style_context($disc_scores['primary']);
            $primary = $disc_scores['primary'];
            $summary .= "Your primary DISC style is {$primary} - {$disc_context['name']}. ";
            $summary .= $disc_context['characteristics'] . " ";
            $summary .= $disc_context['advice'];
        }
        
        return $summary;
    }

    private function call_ai($prompt) {
        $start_time = microtime(true);
        error_log('MFSD Personality Test: Calling AI with prompt length: ' . strlen($prompt));
        
        // Use same approach as RAG plugin - check for $GLOBALS['mwai']
        if (!isset($GLOBALS['mwai'])) {
            error_log('MFSD Personality Test: MWAI plugin not available in $GLOBALS');
            $this->log_ai_call('FAILED', 'MWAI not available', strlen($prompt), 0, 0);
            return null;
        }

        try {
            $mwai = $GLOBALS['mwai'];
            $result = $mwai->simpleTextQuery($prompt);
            
            $elapsed = round((microtime(true) - $start_time) * 1000);
            error_log('MFSD Personality Test: AI response length: ' . strlen($result) . ' (took ' . $elapsed . 'ms)');
            
            $this->log_ai_call('SUCCESS', 'AI call successful', strlen($prompt), strlen($result), $elapsed);
            
            return $result;
        } catch (Exception $e) {
            $elapsed = round((microtime(true) - $start_time) * 1000);
            error_log('MFSD Personality Test AI Error: ' . $e->getMessage());
            error_log('MFSD Personality Test AI Error Trace: ' . $e->getTraceAsString());
            
            $this->log_ai_call('ERROR', $e->getMessage(), strlen($prompt), 0, $elapsed);
            
            return null;
        }
    }
    
    private function log_ai_call($status, $message, $prompt_length, $response_length, $elapsed_ms) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'status' => $status,
            'message' => $message,
            'prompt_length' => $prompt_length,
            'response_length' => $response_length,
            'elapsed_ms' => $elapsed_ms
        );
        
        // Store last 10 AI calls in transient (expires in 1 day)
        $logs = get_transient('mfsd_ptest_ai_calls') ?: array();
        array_unshift($logs, $log_entry);
        $logs = array_slice($logs, 0, 10);
        set_transient('mfsd_ptest_ai_calls', $logs, DAY_IN_SECONDS);
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

        if (isset($_POST['mfsd_ptest_save_settings'])) {
            check_admin_referer('mfsd_ptest_settings');
            update_option('mfsd_ptest_cache_ai_summaries', isset($_POST['cache_ai_summaries']) ? '1' : '0');
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }

        if (isset($_POST['mfsd_ptest_clear_user_data'])) {
            check_admin_referer('mfsd_ptest_clear_user');
            
            $user_id = (int)$_POST['user_id'];
            $week = isset($_POST['week']) ? (int)$_POST['week'] : 0;
            
            $ans_table = $wpdb->prefix . self::TBL_ANSWERS;
            $res_table = $wpdb->prefix . self::TBL_RESULTS;
            
            if ($week > 0) {
                $wpdb->delete($ans_table, array('user_id' => $user_id, 'week_num' => $week));
                $wpdb->delete($res_table, array('user_id' => $user_id, 'week_num' => $week));
                echo '<div class="notice notice-success"><p>Cleared Week ' . $week . ' data for User ID ' . $user_id . '</p></div>';
            } else {
                $wpdb->delete($ans_table, array('user_id' => $user_id));
                $wpdb->delete($res_table, array('user_id' => $user_id));
                echo '<div class="notice notice-success"><p>Cleared all test data for User ID ' . $user_id . '</p></div>';
            }
        }

        if (isset($_POST['mfsd_ptest_clear_all_data'])) {
            check_admin_referer('mfsd_ptest_clear_all');
            if ($_POST['confirm_clear'] === 'DELETE ALL DATA') {
                $ans_table = $wpdb->prefix . self::TBL_ANSWERS;
                $res_table = $wpdb->prefix . self::TBL_RESULTS;
                
                $wpdb->query("TRUNCATE TABLE $ans_table");
                $wpdb->query("TRUNCATE TABLE $res_table");
                echo '<div class="notice notice-success"><p><strong>All test data has been cleared!</strong></p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Incorrect confirmation text. Data was not cleared.</p></div>';
            }
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