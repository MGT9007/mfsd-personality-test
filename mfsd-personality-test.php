<?php
/**
 * Plugin Name: MFSD Personality Test
 * Description: Standalone personality test plugin — "Who Am I (Part 1)" — with either/or personality questions, AI summaries, and tabbed results.
 * Version: 9.2.0
 * Author: MisterT9007
 */

if (!defined('ABSPATH')) exit;

final class MFSD_Personality_Test {
    const VERSION = '9.2.0';
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

    /* ================================================================
       INSTALL — DB tables + sample questions
       ================================================================ */
    public function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $q = $wpdb->prefix . self::TBL_QUESTIONS;
        $a = $wpdb->prefix . self::TBL_ANSWERS;
        $r = $wpdb->prefix . self::TBL_RESULTS;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Questions table — MBTI now has either/or option text + two letter mappings
        dbDelta("CREATE TABLE $q (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          q_order INT NOT NULL DEFAULT 0,
          q_type ENUM('MBTI','DISC') NOT NULL DEFAULT 'MBTI',
          q_text TEXT NOT NULL,
          mbti_axis CHAR(1) NULL COMMENT '1=E/I, 2=S/N, 3=T/F, 4=J/P',
          mbti_letter CHAR(1) NULL COMMENT 'Letter option A maps to',
          mbti_letter_b CHAR(1) NULL COMMENT 'Letter option B maps to',
          mbti_option_a_text TEXT NULL COMMENT 'Display text for option A',
          mbti_option_b_text TEXT NULL COMMENT 'Display text for option B',
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

        // Answers table — answer is 'A'/'B' for MBTI, 1-5 for DISC
        dbDelta("CREATE TABLE $a (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id BIGINT UNSIGNED NOT NULL,
          week_num TINYINT NOT NULL,
          question_id BIGINT UNSIGNED NOT NULL,
          q_type ENUM('MBTI','DISC') NOT NULL,
          answer VARCHAR(10) NOT NULL COMMENT 'A/B for MBTI, 1-5 for DISC',
          mbti_axis CHAR(1) NULL,
          mbti_letter CHAR(1) NULL COMMENT 'The chosen MBTI letter',
          d_contribution FLOAT NULL,
          i_contribution FLOAT NULL,
          s_contribution FLOAT NULL,
          c_contribution FLOAT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_user_week (user_id, week_num),
          KEY idx_user_question (user_id, question_id)
        ) $charset;");

        // Results table
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

        $this->insert_sample_mbti_questions();
        $this->insert_sample_disc_questions();
    }

    /* ================================================================
       SAMPLE MBTI QUESTIONS — either/or format
       ================================================================ */
    private function insert_sample_mbti_questions() {
        global $wpdb;
        $table = $wpdb->prefix . self::TBL_QUESTIONS;
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE q_type = 'MBTI'");
        if ($count > 0) return;

        $mbti_questions = array(
            // Axis 1: E/I (questions 1-3)
            array(
                'order' => 1, 'axis' => '1',
                'text' => 'Do you prefer to spend your free time home alone or out with others?',
                'option_a' => 'Home alone', 'letter_a' => 'I',
                'option_b' => 'Out with others', 'letter_b' => 'E',
            ),
            array(
                'order' => 2, 'axis' => '1',
                'text' => 'Are social gatherings and meeting new people something to be avoided or enjoyed?',
                'option_a' => 'Something to be avoided', 'letter_a' => 'I',
                'option_b' => 'Something to be enjoyed', 'letter_b' => 'E',
            ),
            array(
                'order' => 3, 'axis' => '1',
                'text' => 'Do you consider yourself to be private and reserved, or outgoing and social?',
                'option_a' => 'Private and reserved', 'letter_a' => 'I',
                'option_b' => 'Outgoing and social', 'letter_b' => 'E',
            ),

            // Axis 2: S/N (questions 4-6)
            array(
                'order' => 4, 'axis' => '2',
                'text' => 'Do you prefer to focus on facts, details and go step by step, or concepts, theories and the big picture?',
                'option_a' => 'Facts, details and step by step', 'letter_a' => 'S',
                'option_b' => 'Concepts, theories and the big picture', 'letter_b' => 'N',
            ),
            array(
                'order' => 5, 'axis' => '2',
                'text' => 'Do you tend to think about and appreciate things that are actual and practical, or things that can only be imagined?',
                'option_a' => 'Actual and practical', 'letter_a' => 'S',
                'option_b' => 'Things that can only be imagined', 'letter_b' => 'N',
            ),
            array(
                'order' => 6, 'axis' => '2',
                'text' => 'Do you prefer to be realistic and rely on common sense, or imaginative and do it your own way?',
                'option_a' => 'Realistic and common sense', 'letter_a' => 'S',
                'option_b' => 'Imaginative and my own way', 'letter_b' => 'N',
            ),

            // Axis 3: T/F (questions 7-9)
            array(
                'order' => 7, 'axis' => '3',
                'text' => 'Do you tend to make decisions based on your heart, feelings and emotions, or your head, reasoning and logic?',
                'option_a' => 'Heart, feelings and emotions', 'letter_a' => 'F',
                'option_b' => 'Head, reasoning and logic', 'letter_b' => 'T',
            ),
            array(
                'order' => 8, 'axis' => '3',
                'text' => 'Do you prefer to engage with others in a close and personable way, or somewhat distant and objectively?',
                'option_a' => 'Close and personable', 'letter_a' => 'F',
                'option_b' => 'Somewhat distant and objectively', 'letter_b' => 'T',
            ),
            array(
                'order' => 9, 'axis' => '3',
                'text' => 'Which have you been accused of more often — being too emotional, or being too cold?',
                'option_a' => 'Too emotional', 'letter_a' => 'F',
                'option_b' => 'Too cold', 'letter_b' => 'T',
            ),

            // Axis 4: J/P (questions 10-12)
            array(
                'order' => 10, 'axis' => '4',
                'text' => 'Do you view structure and organisation as liberating or restricting?',
                'option_a' => 'Liberating', 'letter_a' => 'J',
                'option_b' => 'Restricting', 'letter_b' => 'P',
            ),
            array(
                'order' => 11, 'axis' => '4',
                'text' => 'Do you prefer your activities to be structured and planned out, or unstructured and spontaneous?',
                'option_a' => 'Structured and planned', 'letter_a' => 'J',
                'option_b' => 'Unstructured and spontaneous', 'letter_b' => 'P',
            ),
            array(
                'order' => 12, 'axis' => '4',
                'text' => 'Do you prefer to control your environment, or go with the flow?',
                'option_a' => 'Control my environment', 'letter_a' => 'J',
                'option_b' => 'Go with the flow', 'letter_b' => 'P',
            ),
        );

        foreach ($mbti_questions as $q) {
            $wpdb->insert($table, array(
                'q_order'            => $q['order'],
                'q_type'             => 'MBTI',
                'q_text'             => $q['text'],
                'mbti_axis'          => $q['axis'],
                'mbti_letter'        => $q['letter_a'],
                'mbti_letter_b'      => $q['letter_b'],
                'mbti_option_a_text' => $q['option_a'],
                'mbti_option_b_text' => $q['option_b'],
                'w1' => 1, 'w2' => 1, 'w3' => 1, 'w4' => 1, 'w5' => 1, 'w6' => 1,
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
                'q_order' => $q['order'], 'q_type' => 'DISC',
                'q_text' => $q['text'], 'disc_mapping' => $q['mapping'],
                'w1' => 1, 'w2' => 1, 'w3' => 1, 'w4' => 1, 'w5' => 1, 'w6' => 1,
            ));
        }
    }

    /* ================================================================
       ASSETS + SHORTCODE
       ================================================================ */
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
            if (preg_match('/Week\s*([1-6])/i', $title, $m)) {
                $week = (int) $m[1];
            }
        }

        // Ordering gate
        if ( function_exists( 'mfsd_get_task_status' ) && get_option( 'mfsd_ptest_course_management', 1 ) ) {
            $student_id = get_current_user_id();
            $task_slug  = 'personality_test_week_' . $week;
            $status     = mfsd_get_task_status( $student_id, $task_slug );
            if ( $status === 'locked' ) {
                if ( function_exists( 'mfsd_ordering_locked_message' ) ) return mfsd_ordering_locked_message( $task_slug );
                return '<p style="text-align:center;padding:40px;color:#555;">This activity is not available yet. Please complete the previous activity first.</p>';
            }
            if ( $status === 'available' ) mfsd_set_task_status( $student_id, $task_slug, 'in_progress' );
        }

        $avatar_url = plugin_dir_url(__FILE__) . 'assets/personality-avatars.jpg';
        $avatars_base = plugin_dir_url(__FILE__) . 'assets/Avatars/';

        $current_user_id = get_current_user_id();
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
            'avatarImageUrl'      => $avatar_url,
            'avatarsBaseUrl'      => $avatars_base,
            'urlBadges'           => 'https://mfsd.me/badges/',
            'urlCourse'           => 'https://mfsd.me/about/parent-portal-home/?course_id=1&student_id=' . $current_user_id,
        ));

        wp_enqueue_script('mfsd-personality-test');
        wp_enqueue_style('mfsd-personality-test');

       $chat_html = do_shortcode('[stevegpt_chatbot id="chatbot_69eb7ca000e67"]');

        return '<div id="mfsd-ptest-root"></div>'
        .'<div id="mfsd-ptest-chat-source" style="display:none">' . $chat_html . '</div>';
    }

    /* ================================================================
       REST API ROUTES
       ================================================================ */
    public function register_routes() {
        $routes = array(
            '/questions'        => array('GET',  'api_questions'),
            '/answer'           => array('POST', 'api_answer'),
            '/summary'          => array('POST', 'api_summary'),
            '/status'           => array('GET',  'api_status'),
            '/intro'            => array('GET',  'api_intro'),
            '/question-guidance'=> array('POST', 'api_question_guidance'),
            '/question-chat'    => array('POST', 'api_question_chat'),
        );
        foreach ($routes as $path => $cfg) {
            register_rest_route('mfsd-ptest/v1', $path, array(
                'methods'             => $cfg[0] === 'GET' ? WP_REST_Server::READABLE : WP_REST_Server::CREATABLE,
                'callback'            => array($this, $cfg[1]),
                'permission_callback' => array($this, 'check_permission'),
            ));
        }
    }

    public function check_permission() { return is_user_logged_in(); }

    /* ── Questions — now returns option text + both letters for MBTI ── */
    public function api_questions($req) {
        $week = (int) $req->get_param('week') ?: 1;
        if ($week < 1 || $week > 6) return new WP_Error('invalid_week', 'Week must be 1-6', array('status' => 400));

        global $wpdb;
        $table = $wpdb->prefix . self::TBL_QUESTIONS;
        $col = 'w' . $week;

        $questions = $wpdb->get_results(
            "SELECT id, q_order, q_type, q_text, mbti_axis, mbti_letter, mbti_letter_b, 
                    mbti_option_a_text, mbti_option_b_text, disc_mapping 
             FROM $table WHERE $col = 1 ORDER BY q_order ASC",
            ARRAY_A
        );

        foreach ($questions as &$q) {
            if (!empty($q['disc_mapping'])) $q['disc_mapping'] = json_decode($q['disc_mapping'], true);
        }

        return array('ok' => true, 'questions' => $questions, 'week' => $week);
    }

    /* ── Answer — MBTI stores chosen letter; DISC unchanged ── */
    public function api_answer($req) {
        $user_id     = get_current_user_id();
        $week        = (int) $req->get_param('week');
        $question_id = (int) $req->get_param('question_id');
        $q_type      = $req->get_param('q_type');
        $answer      = $req->get_param('answer');

        if (!$user_id || !$week || !$question_id || !$q_type) {
            return new WP_Error('missing_params', 'Missing required parameters', array('status' => 400));
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TBL_ANSWERS;

        $data = array(
            'user_id' => $user_id, 'week_num' => $week,
            'question_id' => $question_id, 'q_type' => $q_type, 'answer' => $answer,
        );

        if ($q_type === 'MBTI') {
            // answer = 'A' or 'B'; letter = the chosen MBTI letter
            $data['mbti_axis']   = $req->get_param('axis');
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

        if ($existing) $wpdb->update($table, $data, array('id' => $existing));
        else $wpdb->insert($table, $data);

        return array('ok' => true);
    }

    /* ── Status ── */
    public function api_status($req) {
        $user_id = get_current_user_id();
        $week = (int) $req->get_param('week') ?: 1;

        global $wpdb;
        $ans_table = $wpdb->prefix . self::TBL_ANSWERS;
        $res_table = $wpdb->prefix . self::TBL_RESULTS;
        $q_table   = $wpdb->prefix . self::TBL_QUESTIONS;

        $completed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $res_table WHERE user_id = %d AND week_num = %d", $user_id, $week));
        if ($completed > 0) return array('ok' => true, 'status' => 'completed', 'week' => $week);

        $col = 'w' . $week;
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $q_table WHERE $col = 1");
        $answered = $wpdb->get_results($wpdb->prepare(
            "SELECT question_id FROM $ans_table WHERE user_id = %d AND week_num = %d", $user_id, $week), ARRAY_A);
        $answered_ids = array_map(function($a) { return (int)$a['question_id']; }, $answered);

        if (count($answered) === 0) {
            return array('ok' => true, 'status' => 'not_started', 'week' => $week, 'total_questions' => $total);
        }

        $last = $wpdb->get_var($wpdb->prepare(
            "SELECT question_id FROM $ans_table WHERE user_id = %d AND week_num = %d ORDER BY created_at DESC LIMIT 1",
            $user_id, $week));

        return array('ok' => true, 'status' => 'in_progress', 'week' => $week,
                      'total_questions' => $total, 'answered_count' => count($answered),
                      'answered_question_ids' => $answered_ids, 'last_question_id' => (int)$last);
    }

    /* ── Intro ── */
    public function api_intro($req) {
        $week = (int) $req->get_param('week') ?: 1;
        global $wpdb;
        $q_table = $wpdb->prefix . self::TBL_QUESTIONS;
        $col = 'w' . $week;
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $q_table WHERE $col = 1");

        $prompt = "You are Steve Sallis, a friendly motivational teacher-coach for students aged 12-14 working on their My Future Self Foundation Course. ";
        $prompt .= "Generate a brief, encouraging introduction (2-3 sentences) for a personality quiz called 'Who Am I'. ";
        $prompt .= "The quiz has {$total} questions — each one is a simple 'this or that' choice. ";
        $prompt .= "It will help the student discover which of 16 personality types they are. ";
        $prompt .= "Remember you are talking to a young person aged 12-14. ";
        $prompt .= "Keep it light, fun, and reassuring — there are no wrong answers, just pick whichever feels most like you. ";
        $prompt .= "Do NOT mention 'MBTI', 'Myers-Briggs', or 'DISC'. ";
        $prompt .= "Start your message with 'Steve says:' and keep the tone warm, encouraging, and age-appropriate.";

        $intro = $this->call_ai($prompt);
        if (!$intro) {
            $intro = "Steve says: Hey there, superstar! You're about to take a fun quiz that helps you discover what makes YOU uniquely awesome. Each question gives you two choices — just pick whichever one feels most like you. There are no wrong answers, so relax and be yourself! Let's do this! 👍😊";
        }
        return array('ok' => true, 'intro_message' => $intro, 'total_questions' => $total);
    }

    /* ── Question guidance — explains either/or questions ── */
    public function api_question_guidance($req) {
        $question_text = $req->get_param('question_text');

        $prompt = "You are Steve Sallis, a motivational teacher-coach helping a 12-14 year old student with a personality quiz called 'Who Am I'. ";
        $prompt .= "The question is: '{$question_text}'. ";
        $prompt .= "This is an either/or question — the student picks whichever option feels most like them. ";
        $prompt .= "Provide a brief (2-3 sentences) explanation to help them think about what the question is really asking. ";
        $prompt .= "Start with 'Steve says:' — do NOT start with 'Great question' or similar. ";
        $prompt .= "Use examples from their everyday life to make it click. ";
        $prompt .= "Remind them to go with their gut — there's no right or wrong answer. ";
        $prompt .= "Keep it simple and age-appropriate.";

        $guidance = $this->call_ai($prompt);
        return array('ok' => true,
            'guidance' => $guidance ?: "Steve says: Just think about what you'd naturally do — there's no right or wrong here, just go with whichever feels most like you! 😊");
    }

    /* ── Question chat ── */
    public function api_question_chat($req) {
        $user_message  = $req->get_param('message');
        $question_text = $req->get_param('question_text');

        $prompt = "You are SteveGPT, the AI version of Steve Sallis — a motivational teacher-coach for 12-14 year olds on their My Future Self Foundation Course. ";
        $prompt .= "A student is working on this either/or personality question: '{$question_text}'. ";
        $prompt .= "They asked: '{$user_message}'. ";
        $prompt .= "Help them think about the question. Keep it friendly, encouraging, and age-appropriate. ";
        $prompt .= "Do NOT reference 'MBTI', 'Myers-Briggs', or 'DISC'. ";
        $prompt .= "Sign your response with '- SteveGPT' at the end.";

        $response = $this->call_ai($prompt);
        return array('ok' => true,
            'response' => $response ?: "Just think about which option feels most like the real you — trust your gut! - SteveGPT");
    }

    /* ================================================================
       SUMMARY + SCORING
       ================================================================ */
    public function api_summary($req) {
        $user_id = get_current_user_id();
        $week = (int) $req->get_param('week');
        $force = $req->get_param('force_regenerate') === 'true';

        global $wpdb;
        $ans_table = $wpdb->prefix . self::TBL_ANSWERS;
        $res_table = $wpdb->prefix . self::TBL_RESULTS;

        $cache_on = get_option('mfsd_ptest_cache_ai_summaries', '1') === '1';

        if ($cache_on && !$force) {
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $res_table WHERE user_id = %d AND week_num = %d AND test_type = 'COMBINED'",
                $user_id, $week), ARRAY_A);
            if ($existing && !empty($existing['ai_summary'])) {
                return array('ok' => true, 'mbti_type' => $existing['mbti_type'],
                    'disc_scores' => array('D' => (float)$existing['disc_d_score'], 'I' => (float)$existing['disc_i_score'],
                        'S' => (float)$existing['disc_s_score'], 'C' => (float)$existing['disc_c_score'],
                        'primary' => $existing['disc_primary']),
                    'ai_summary' => $existing['ai_summary'], 'cached' => true);
            }
        }

        // Calculate MBTI — simple majority count
        $mbti_answers = $wpdb->get_results($wpdb->prepare(
            "SELECT mbti_axis, mbti_letter FROM $ans_table WHERE user_id = %d AND week_num = %d AND q_type = 'MBTI'",
            $user_id, $week), ARRAY_A);
        $mbti_type = $this->calculate_mbti($mbti_answers);

        // Calculate DISC — unchanged
        $disc_answers = $wpdb->get_results($wpdb->prepare(
            "SELECT d_contribution, i_contribution, s_contribution, c_contribution FROM $ans_table 
             WHERE user_id = %d AND week_num = %d AND q_type = 'DISC'",
            $user_id, $week), ARRAY_A);
        $disc_scores = $this->calculate_disc($disc_answers);

        $summary_prompt = $this->build_summary_prompt($mbti_type, $disc_scores, $week);
        $ai_summary = $this->call_ai($summary_prompt);

        if (!$ai_summary || strlen($ai_summary) < 200) {
            $ai_summary = $this->generate_fallback_summary($mbti_type, $disc_scores, $week);
        }

        if ($cache_on) {
            if ($mbti_type) {
                $wpdb->replace($res_table, array('user_id' => $user_id, 'week_num' => $week, 'test_type' => 'MBTI',
                    'mbti_type' => $mbti_type, 'mbti_details' => json_encode(array('raw_answers' => $mbti_answers)),
                    'ai_summary' => $ai_summary));
            }
            if ($disc_scores) {
                $wpdb->replace($res_table, array('user_id' => $user_id, 'week_num' => $week, 'test_type' => 'DISC',
                    'disc_d_score' => $disc_scores['D'], 'disc_i_score' => $disc_scores['I'],
                    'disc_s_score' => $disc_scores['S'], 'disc_c_score' => $disc_scores['C'],
                    'disc_primary' => $disc_scores['primary'], 'disc_details' => json_encode($disc_scores),
                    'ai_summary' => $ai_summary));
            }
            $wpdb->replace($res_table, array('user_id' => $user_id, 'week_num' => $week, 'test_type' => 'COMBINED',
                'mbti_type' => $mbti_type,
                'disc_d_score' => $disc_scores['D'] ?? null, 'disc_i_score' => $disc_scores['I'] ?? null,
                'disc_s_score' => $disc_scores['S'] ?? null, 'disc_c_score' => $disc_scores['C'] ?? null,
                'disc_primary' => $disc_scores['primary'] ?? null, 'ai_summary' => $ai_summary));

            if ( function_exists( 'mfsd_set_task_status' ) && get_option( 'mfsd_ptest_course_management', 1 ) ) {
                mfsd_set_task_status( $user_id, 'personality_test_week_' . $week, 'completed' );
            }
        }

        return array('ok' => true, 'mbti_type' => $mbti_type, 'disc_scores' => $disc_scores,
                      'ai_summary' => $ai_summary, 'cached' => false);
    }

    /* ================================================================
       CALCULATE MBTI — majority count per axis (2 out of 3 wins)
       ================================================================ */
    private function calculate_mbti($answers) {
        if (empty($answers)) return null;

        // Count chosen letters per axis
        $counts = array();
        foreach ($answers as $a) {
            $axis   = $a['mbti_axis'];
            $letter = $a['mbti_letter'];
            if (!isset($counts[$axis][$letter])) $counts[$axis][$letter] = 0;
            $counts[$axis][$letter]++;
        }

        // For each axis, whichever letter has the higher count wins
        $type = '';
        $axis_map = array('1' => array('E','I'), '2' => array('S','N'), '3' => array('T','F'), '4' => array('J','P'));

        foreach ($axis_map as $axis => $letters) {
            $count_a = $counts[$axis][$letters[0]] ?? 0;
            $count_b = $counts[$axis][$letters[1]] ?? 0;
            $type .= $count_a >= $count_b ? $letters[0] : $letters[1];
        }

        return $type;
    }

    /* ── DISC scoring — completely unchanged ── */
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
        if ($sum > 0) foreach ($totals as $k => $v) $totals[$k] = round(($v / $sum) * 100, 1);
        $primary = array_keys($totals, max($totals))[0];
        return array('D' => $totals['D'], 'I' => $totals['I'], 'S' => $totals['S'], 'C' => $totals['C'],
                      'primary' => $primary, 'percentages' => $totals);
    }

    /* ================================================================
       Summary prompt, type context, fallback — unchanged from v6.0.0
       ================================================================ */
    private function build_summary_prompt($mbti_type, $disc_scores, $week) {
        $prompt = "You are Steve Sallis, a motivational teacher-coach providing 'Who Am I' personality quiz results to a 12-14 year old student on their My Future Self Foundation Course. ";
        $prompt .= "This is their Week {$week} 'Who Am I (Part 1)' results.\n\n";

        if ($mbti_type) {
            $ctx = $this->get_mbti_type_context($mbti_type);
            $prompt .= "=== PERSONALITY ASSESSMENT (internal reference only — do NOT show the code '{$mbti_type}' to the student) ===\n";
            $prompt .= "Personality Name: The {$ctx['nickname']}\nPersonality Family: {$ctx['group']}\n\n";
            $prompt .= "Core Description: {$ctx['description']}\n\nKey Strengths: {$ctx['strengths']}\n\n";
            $prompt .= "Learning Style: {$ctx['learning_style']}\n\nCommunication Style: {$ctx['communication']}\n\n";
            $prompt .= "Potential Career Interests: {$ctx['careers']}\n\nAdvice for Teens: {$ctx['teen_advice']}\n\n";
        }

        if ($disc_scores) {
            $dc = $this->get_disc_style_context($disc_scores['primary']);
            $prompt .= "=== COMMUNICATION STYLE ===\nPrimary Style: {$disc_scores['primary']} - {$dc['name']}\n";
            $prompt .= "Scores: D={$disc_scores['D']}%, I={$disc_scores['I']}%, S={$disc_scores['S']}%, C={$disc_scores['C']}%\n\n";
            $prompt .= "Characteristics: {$dc['characteristics']}\n\nMotivations: {$dc['motivations']}\n\n";
            $prompt .= "Growth Areas: {$dc['challenges']}\n\nLearning Preferences: {$dc['learning']}\n\n";
            $prompt .= "Teen Strengths: {$dc['strengths_for_teens']}\n\nAdvice: {$dc['advice']}\n\n";
        }

        $prompt .= "=== YOUR TASK ===\nWrite a warm, personal, encouraging summary as Steve Sallis.\n\n";
        $prompt .= "CRITICAL FORMATTING RULES:\n- Structure your response using these exact section markers:\n\n";
        $prompt .= "[SECTION:Who You Are]\n- Warm greeting, introduce personality name and family\n- Do NOT use four-letter codes\n\n";
        $prompt .= "[SECTION:Your Strengths]\n- Natural strengths with teen-life examples\n\n";
        $prompt .= "[SECTION:Learning & School]\n- Practical school advice based on learning style\n\n";
        $prompt .= "[SECTION:Friends & Relationships]\n- How they interact with friends, tips for friendships\n\n";
        $prompt .= "[SECTION:Your Growth Edge]\n- Growth areas framed positively\n- End encouraging, sign '- Steve'\n\n";
        $prompt .= "IMPORTANT: Start first section with 'Steve says:', write in second person, NEVER use four-letter codes or 'MBTI'/'Myers-Briggs'/'DISC'. Keep it age-appropriate for 12-14 year olds.\n";

        return $prompt;
    }

    private function get_mbti_type_context($type) {
        $mbti_data = array(
            'ISTJ' => array('group'=>'Sentinel','nickname'=>'The Logistician','description'=>'Practical, fact-minded, and incredibly reliable. The rock-solid organizers who get things done right.','strengths'=>'Excellent at planning and organizing, super responsible and dependable, amazing attention to detail, loyal friend who keeps promises, calm under pressure','learning_style'=>'Learn best with clear structure, step-by-step instructions, and practical real-world examples. Love checklists and study schedules.','communication'=>'Direct, honest, and factual. Prefer clear conversations without drama. Thoughtful listeners who remember details.','careers'=>'Accounting, engineering, law enforcement, healthcare administration, project management, data analysis, architecture','teen_advice'=>'Your reliability is your superpower. Use your organizing skills for school projects and sports teams. Your consistency will take you far!'),
            'ISFJ' => array('group'=>'Sentinel','nickname'=>'The Defender','description'=>'Warm-hearted protectors incredibly dedicated to helping and supporting others. The caring friends who remember birthdays and always show up.','strengths'=>'Incredibly supportive and caring, excellent memory for details, hardworking and diligent, patient with others, great at creating harmony','learning_style'=>'Learn best in supportive environments with clear expectations. Prefer hands-on practice and working with study partners.','communication'=>'Warm, considerate, and thoughtful. Excellent listeners. Express care through actions more than words.','careers'=>'Nursing, teaching, counseling, social work, veterinary assistant, childcare, human resources','teen_advice'=>'Your kindness makes the world better. It\'s okay to say no sometimes and put yourself first. Your friends are lucky to have you!'),
            'INFJ' => array('group'=>'Diplomat','nickname'=>'The Advocate','description'=>'Quiet, mystical idealists with deep insights about people and a strong sense of purpose. Creative dreamers who want to make the world better.','strengths'=>'Incredibly insightful about people, creative problem-solvers, passionate about causes they believe in, great at inspiring others, visionary thinkers','learning_style'=>'Learn best through big-picture concepts and exploring "why" behind facts. Enjoy creative projects and subjects connected to helping people.','communication'=>'Prefer deep, meaningful one-on-one conversations. Express ideas through stories and creative writing. Great listeners.','careers'=>'Counseling, writing, psychology, teaching, social work, art therapy, environmental advocacy, coaching','teen_advice'=>'Your ability to understand people is rare and special. Your ideas can change the world, so don\'t be afraid to share them!'),
            'INTJ' => array('group'=>'Analyst','nickname'=>'The Architect','description'=>'Brilliant strategic thinkers with a plan for everything. Masterminds who see patterns others miss and love solving complex puzzles.','strengths'=>'Strategic planning and long-term thinking, independent learners, innovative problem-solvers, excellent at seeing patterns and connections','learning_style'=>'Love complex theories, abstract concepts, and intellectual challenges. Prefer independent study and research projects.','communication'=>'Logical, direct, and focused on ideas. Prefer meaningful debates over casual chat. Respect competence in others.','careers'=>'Science research, engineering, computer programming, strategy consulting, medicine, game design','teen_advice'=>'Your strategic mind is brilliant. Remember that not everyone thinks as deeply as you, and that\'s okay. Find intellectually curious friends!'),
            'ISTP' => array('group'=>'Explorer','nickname'=>'The Virtuoso','description'=>'Bold, practical hands-on problem solvers. Cool-headed troubleshooters who stay calm in crises and figure out how things work.','strengths'=>'Excellent with tools and hands-on projects, calm under pressure, practical problem-solvers, adaptable and flexible, great at sports','learning_style'=>'Learn by doing and taking things apart. Need hands-on experience and freedom to experiment.','communication'=>'Brief, practical, and to-the-point. Express themselves better through actions than words.','careers'=>'Mechanics, engineering, carpentry, athletics, emergency services, forensics, aviation, video game design','teen_advice'=>'Your hands-on skills are impressive! Your calm attitude in emergencies is a real gift. Channel your energy into active hobbies!'),
            'ISFP' => array('group'=>'Explorer','nickname'=>'The Adventurer','description'=>'Gentle, flexible souls with artistic flair who live in the moment. Creative free spirits who express themselves through art, music, or nature.','strengths'=>'Naturally artistic and creative, sensitive to beauty, kind and considerate, live fully in the present moment','learning_style'=>'Learn best through artistic expression, hands-on activities, and sensory experiences. Thrive with flexibility.','communication'=>'Gentle, considerate, and non-confrontational. Express feelings through art, music, or actions rather than words.','careers'=>'Visual arts, graphic design, music, photography, veterinary care, chef, fashion design, nature conservation','teen_advice'=>'Your artistic gifts make the world more beautiful! Trust your aesthetic sense. Your gentle nature is a strength, not a weakness!'),
            'INFP' => array('group'=>'Diplomat','nickname'=>'The Mediator','description'=>'Poetic, idealistic souls with strong values who see potential for good in everyone. Dreamers and writers who feel deeply.','strengths'=>'Deeply empathetic, gifted creative writers, passionate about values, see the best in people, imaginative thinkers','learning_style'=>'Learn best when material connects to values and meaning. Excel at creative writing, literature, and social studies.','communication'=>'Warm, empathetic, and excellent listeners. Express ideas beautifully through creative writing.','careers'=>'Writing, counseling, teaching, psychology, social work, arts, human rights advocacy, music therapy','teen_advice'=>'Your empathy and idealism are gifts — the world needs dreamers like you! Keep creating. It\'s okay to stand up for your values!'),
            'INTP' => array('group'=>'Analyst','nickname'=>'The Logician','description'=>'Brilliant innovators with an endless thirst for knowledge. Logical puzzle-solvers who love learning new things.','strengths'=>'Exceptional analytical thinking, love learning about everything, creative problem-solvers, independent thinkers, naturally curious','learning_style'=>'Love complex theories and intellectual exploration. Learn best through independent reading and solving challenging problems.','communication'=>'Focus on ideas, logic, and accuracy over small talk. Enjoy intellectual debates. Can explain complex ideas clearly.','careers'=>'Science, mathematics, computer programming, research, philosophy, engineering, game design, physics','teen_advice'=>'Your brilliant mind is an incredible asset — never stop asking "why"! Find other curious minds to explore ideas with.'),
            'ESTP' => array('group'=>'Explorer','nickname'=>'The Entrepreneur','description'=>'Energetic action-takers who live in the moment and love excitement. Bold risk-takers who make things happen.','strengths'=>'Quick thinking, incredibly energetic, great at persuasion, practical learners, adaptable, excellent in crisis situations, natural athletes','learning_style'=>'Learn by doing and jumping right in. Need activity, variety, and real-world applications.','communication'=>'Direct, energetic, and entertaining. Love lively conversations. Great at reading people.','careers'=>'Sales, entrepreneurship, emergency services, professional sports, entertainment, marketing, coaching','teen_advice'=>'Your energy and boldness are magnetic! Channel your risk-taking into sports, business, or adventures.'),
            'ESFP' => array('group'=>'Explorer','nickname'=>'The Entertainer','description'=>'Spontaneous, enthusiastic performers who spread joy wherever they go. Life of the party who make others smile.','strengths'=>'Incredibly friendly and outgoing, encouraging to others, practical and resourceful, excellent at bringing people together, natural performers','learning_style'=>'Learn best through group activities, hands-on experiences, and social interaction. Need movement and variety.','communication'=>'Warm, enthusiastic, and very expressive. Love entertaining and making people laugh.','careers'=>'Entertainment, hospitality, teaching, counseling, sales, event planning, fitness training, social media','teen_advice'=>'Your positive energy is contagious! Use your performance skills in drama, sports, or leadership. Stay true to your fun-loving self!'),
            'ENFP' => array('group'=>'Diplomat','nickname'=>'The Campaigner','description'=>'Enthusiastic, creative free spirits with endless optimism. Inspiring dreamers who encourage others and find joy in connecting people.','strengths'=>'Incredibly enthusiastic and inspiring, creative thinkers, excellent with people, see possibilities everywhere, passionate about many interests','learning_style'=>'Learn best through group discussions, creative projects, and exploring possibilities. Get bored with routine drills.','communication'=>'Enthusiastic, expressive, and encouraging. Great at inspiring others and making connections between concepts.','careers'=>'Teaching, counseling, journalism, arts, marketing, human resources, entrepreneurship, public relations, coaching','teen_advice'=>'Your enthusiasm and creativity inspire others! Learn to focus on finishing what you start. Your optimism can change lives!'),
            'ENTP' => array('group'=>'Analyst','nickname'=>'The Debater','description'=>'Smart, quick-witted innovators who love intellectual challenges. Clever idea-generators who question everything.','strengths'=>'Exceptionally quick-witted, innovative, great at brainstorming, enjoy intellectual challenges, adaptable, excellent at seeing different perspectives','learning_style'=>'Learn through debate and challenging assumptions. Need intellectual stimulation and variety.','communication'=>'Lively debater, very quick-thinking and witty. Enjoy intellectual sparring. Natural storytellers.','careers'=>'Law, engineering, entrepreneurship, consulting, journalism, programming, marketing, business strategy','teen_advice'=>'Your quick mind is impressive! Remember not everything needs debating. Channel your energy into entrepreneurship or invention!'),
            'ESTJ' => array('group'=>'Sentinel','nickname'=>'The Executive','description'=>'Organized, efficient leaders who get things done right. Natural managers who create order and make sure everyone follows through.','strengths'=>'Extremely organized and efficient, natural leaders, practical and realistic, excellent at follow-through, honest and direct communicators','learning_style'=>'Learn best with clear structure, proven methods, and practical applications. Excel at organizing study groups.','communication'=>'Direct, efficient, and no-nonsense. Good at giving clear instructions. Value honesty and responsibility.','careers'=>'Business management, law enforcement, project management, banking, accounting, operations management, quality control','teen_advice'=>'Your leadership and organizational skills are valuable! Remember not everyone works as efficiently as you, and that\'s okay.'),
            'ESFJ' => array('group'=>'Sentinel','nickname'=>'The Consul','description'=>'Warm, caring social coordinators who bring people together and make sure everyone feels included.','strengths'=>'Incredibly warm and supportive, excellent at organizing people and events, loyal friends, practical helpers, great at creating harmony','learning_style'=>'Learn best in supportive group settings with encouragement. Remember information better when learning has social element.','communication'=>'Warm, friendly, and attentive. Love connecting with people. Very good at reading others\' emotions.','careers'=>'Teaching, nursing, social work, event planning, hospitality, office management, counseling, human resources','teen_advice'=>'Your caring nature makes everyone feel valued — that\'s a superpower! Use your organizing skills to plan events and lead teams.'),
            'ENFJ' => array('group'=>'Diplomat','nickname'=>'The Protagonist','description'=>'Charismatic, inspiring leaders who bring out the best in others. Natural mentors who see potential in everyone.','strengths'=>'Incredibly inspiring, exceptional communicators, deeply empathetic leaders, see potential in everyone, great at teaching and mentoring','learning_style'=>'Learn best through group discussions, teaching others, and meaningful material. Excel at presenting and collaborative projects.','communication'=>'Warm, persuasive, and highly expressive. Natural teachers and motivational speakers.','careers'=>'Teaching, counseling, human resources, public relations, coaching, psychology, non-profit leadership','teen_advice'=>'Your ability to inspire and lead is remarkable! Your encouragement changes lives. Your charisma and empathy will take you far!'),
            'ENTJ' => array('group'=>'Analyst','nickname'=>'The Commander','description'=>'Bold, strategic leaders with strong vision who make things happen. Determined commanders who set ambitious goals.','strengths'=>'Exceptional strategic thinking, confident leaders, decisive, efficient and goal-focused, excellent at organizing complex projects','learning_style'=>'Learn best through challenge, competition, and leadership opportunities. Excel at debates and presentations.','communication'=>'Direct, confident, and commanding. Natural at public speaking. Very goal-focused.','careers'=>'Executive leadership, entrepreneurship, law, engineering management, consulting, finance, business strategy','teen_advice'=>'Your leadership and strategic vision are powerful! Balance your drive with patience for different working styles. You\'re destined to lead!'),
        );
        return $mbti_data[$type] ?? array('group'=>'Unique','nickname'=>'The Individual','description'=>'A unique personality type','strengths'=>'Personal strengths','learning_style'=>'Individual learning preferences','communication'=>'Personal communication style','careers'=>'Various career options','teen_advice'=>'Embrace your unique qualities!');
    }

    private function get_disc_style_context($primary) {
        $disc_data = array(
            'D' => array('name'=>'Dominance','characteristics'=>'Direct, results-oriented, decisive, competitive, and confident.','motivations'=>'Motivated by winning, achieving goals, and overcoming challenges.','challenges'=>'May seem too direct or impatient. Can benefit from listening more.','learning'=>'Learn best through challenge, competition, and seeing quick results.','strengths_for_teens'=>'Excellent at sports, leadership roles, making quick decisions, and staying confident under pressure.','advice'=>'Your drive and confidence are impressive! Remember teamwork means letting others contribute too.'),
            'I' => array('name'=>'Influence','characteristics'=>'Enthusiastic, optimistic, outgoing, persuasive, and naturally social.','motivations'=>'Motivated by social recognition, having fun, and connecting with people.','challenges'=>'May get distracted easily. Can benefit from better organization.','learning'=>'Learn best in groups, through discussion, and with interactive activities.','strengths_for_teens'=>'Amazing at making friends, public speaking, cheering people up, and being the social glue.','advice'=>'Your social skills are magnetic! Remember to follow through on commitments.'),
            'S' => array('name'=>'Steadiness','characteristics'=>'Patient, supportive, reliable, calm, and loyal.','motivations'=>'Motivated by cooperation, helping others, and maintaining stability.','challenges'=>'May avoid change. Can benefit from expressing opinions more directly.','learning'=>'Learn best in stable, supportive environments with patient instruction.','strengths_for_teens'=>'Incredible at being dependable, listening, staying calm under stress, and team sports.','advice'=>'You\'re the steady friend everyone needs! Don\'t be afraid to try new things.'),
            'C' => array('name'=>'Conscientiousness','characteristics'=>'Analytical, precise, systematic, quality-focused, and detail-oriented.','motivations'=>'Motivated by getting things right and achieving high quality.','challenges'=>'May be too perfectionist. Can benefit from accepting "good enough".','learning'=>'Learn best with clear standards and time for thorough study.','strengths_for_teens'=>'Outstanding at detail-oriented subjects, research projects, and producing high-quality work.','advice'=>'Your attention to detail is impressive! Don\'t be so hard on yourself when you make mistakes.'),
        );
        return $disc_data[$primary] ?? array('name'=>'Balanced','characteristics'=>'A balanced blend','motivations'=>'Flexible','challenges'=>'Individual growth','learning'=>'Adaptable','strengths_for_teens'=>'Versatile','advice'=>'Your balanced approach is valuable!');
    }

    private function generate_fallback_summary($mbti_type, $disc_scores, $week) {
        $summary = '';
        if ($mbti_type) {
            $ctx = $this->get_mbti_type_context($mbti_type);
            $summary .= "[SECTION:Who You Are]\nSteve says: Welcome to your Who Am I results! You are {$ctx['nickname']}, and you belong to the {$ctx['group']} family. {$ctx['description']}\n\n";
            $summary .= "[SECTION:Your Strengths]\nYour strengths include: {$ctx['strengths']}\n\n";
            $summary .= "[SECTION:Learning & School]\nHere's how you learn best: {$ctx['learning_style']}\n\n";
            $summary .= "[SECTION:Friends & Relationships]\nHere's how you connect: {$ctx['communication']}\n\n";
            $summary .= "[SECTION:Your Growth Edge]\n{$ctx['teen_advice']} Every personality type is needed — the world needs people like you!\n\n- Steve";
        } else {
            $summary .= "[SECTION:Your Results]\nSteve says: Welcome to your Who Am I results! You've completed the quiz — every personality type is unique and valuable, and so are you!\n\n- Steve";
        }
        return $summary;
    }

    /* ================================================================
       AI CALL + LOGGING — unchanged
       ================================================================ */
    private function call_ai($prompt) {
    $start = microtime(true);
    
    // Use SteveGPT instead of MWAI - includes skills + context awareness
    if (!isset($GLOBALS['stevegpt'])) { 
        $this->log_ai_call('FAILED','SteveGPT not available',strlen($prompt),0,0); 
        return null; 
    }
    
    try {
        // Get the Who Am I chatbot (has personality types + DISC skills + context)
        $chatbot = SteveGPT_Chatbot::get('chatbot_69eb7ca000e67');
        $result = $chatbot->simpleTextQuery($prompt);
        
        $this->log_ai_call('SUCCESS','OK',strlen($prompt),strlen($result),round((microtime(true)-$start)*1000));
        return $result;
    } catch (Exception $e) {
        $this->log_ai_call('ERROR',$e->getMessage(),strlen($prompt),0,round((microtime(true)-$start)*1000));
        return null;
    }
    }
    
    private function log_ai_call($status, $msg, $plen, $rlen, $ms) {
        $logs = get_transient('mfsd_ptest_ai_calls') ?: array();
        array_unshift($logs, array('timestamp'=>current_time('mysql'),'status'=>$status,'message'=>$msg,'prompt_length'=>$plen,'response_length'=>$rlen,'elapsed_ms'=>$ms));
        set_transient('mfsd_ptest_ai_calls', array_slice($logs,0,10), DAY_IN_SECONDS);
    }

    /* ================================================================
       ADMIN — unchanged from v6.0.0
       ================================================================ */
    public function admin_menu() {
        add_menu_page('Personality Test Admin','Personality Test','manage_options','mfsd-ptest-admin',array($this,'admin_page'),'dashicons-smiley',30);
    }

    public function admin_page() {
        if (!current_user_can('manage_options')) wp_die('Unauthorized');
        global $wpdb;
        $table = $wpdb->prefix . self::TBL_QUESTIONS;

        if (isset($_POST['mfsd_ptest_add_question']))  { check_admin_referer('mfsd_ptest_add');    $this->handle_add_question($_POST); }
        if (isset($_POST['mfsd_ptest_update_weeks']))   { check_admin_referer('mfsd_ptest_update'); $this->handle_update_weeks($_POST); }
        if (isset($_GET['delete_question']))             { check_admin_referer('mfsd_ptest_delete_' . $_GET['delete_question']); $wpdb->delete($table, array('id'=>(int)$_GET['delete_question'])); echo '<div class="notice notice-success"><p>Question deleted.</p></div>'; }
        if (isset($_POST['mfsd_ptest_save_settings']))  { check_admin_referer('mfsd_ptest_settings'); update_option('mfsd_ptest_cache_ai_summaries',isset($_POST['cache_ai_summaries'])?'1':'0'); update_option('mfsd_ptest_course_management',isset($_POST['course_management'])?'1':'0'); echo '<div class="notice notice-success"><p>Settings saved.</p></div>'; }

        if (isset($_POST['mfsd_ptest_clear_user_data'])) {
            check_admin_referer('mfsd_ptest_clear_user');
            $uid = (int)$_POST['user_id']; $wk = isset($_POST['week']) ? (int)$_POST['week'] : 0;
            $at = $wpdb->prefix.self::TBL_ANSWERS; $rt = $wpdb->prefix.self::TBL_RESULTS;
            if ($wk > 0) { $wpdb->delete($at,array('user_id'=>$uid,'week_num'=>$wk)); $wpdb->delete($rt,array('user_id'=>$uid,'week_num'=>$wk)); }
            else { $wpdb->delete($at,array('user_id'=>$uid)); $wpdb->delete($rt,array('user_id'=>$uid)); }
            if (function_exists('mfsd_get_task_order_row')) { for($w=1;$w<=6;$w++) $wpdb->delete($wpdb->prefix.'mfsd_task_progress',array('student_id'=>$uid,'task_slug'=>'personality_test_week_'.$w)); }
            echo '<div class="notice notice-success"><p>Data cleared.</p></div>';
        }
        if (isset($_POST['mfsd_ptest_clear_all_data'])) {
            check_admin_referer('mfsd_ptest_clear_all');
            if ($_POST['confirm_clear']==='DELETE ALL DATA') { $wpdb->query("TRUNCATE TABLE ".$wpdb->prefix.self::TBL_ANSWERS); $wpdb->query("TRUNCATE TABLE ".$wpdb->prefix.self::TBL_RESULTS); echo '<div class="notice notice-success"><p><strong>All data cleared.</strong></p></div>'; }
        }

        $questions = $wpdb->get_results("SELECT * FROM $table ORDER BY q_type, q_order", ARRAY_A);
        include plugin_dir_path(__FILE__) . 'admin-page.php';
    }

    private function handle_add_question($data) {
        global $wpdb;
        $table = $wpdb->prefix . self::TBL_QUESTIONS;
        $insert = array('q_order'=>(int)$data['q_order'],'q_type'=>sanitize_text_field($data['q_type']),'q_text'=>sanitize_textarea_field($data['q_text']),
            'w1'=>isset($data['w1'])?1:0,'w2'=>isset($data['w2'])?1:0,'w3'=>isset($data['w3'])?1:0,'w4'=>isset($data['w4'])?1:0,'w5'=>isset($data['w5'])?1:0,'w6'=>isset($data['w6'])?1:0);
        if ($data['q_type']==='MBTI') {
            $insert['mbti_axis']          = sanitize_text_field($data['mbti_axis']);
            $insert['mbti_letter']        = sanitize_text_field($data['mbti_letter'] ?? '');
            $insert['mbti_letter_b']      = sanitize_text_field($data['mbti_letter_b'] ?? '');
            $insert['mbti_option_a_text'] = sanitize_text_field($data['mbti_option_a_text'] ?? '');
            $insert['mbti_option_b_text'] = sanitize_text_field($data['mbti_option_b_text'] ?? '');
        } elseif ($data['q_type']==='DISC') {
            $insert['disc_mapping'] = json_encode(array('D'=>(float)$data['disc_d'],'I'=>(float)$data['disc_i'],'S'=>(float)$data['disc_s'],'C'=>(float)$data['disc_c']));
        }
        $wpdb->insert($table, $insert);
        echo '<div class="notice notice-success"><p>Question added.</p></div>';
    }

    private function handle_update_weeks($data) {
        global $wpdb;
        $table = $wpdb->prefix . self::TBL_QUESTIONS;
        foreach ($data['questions'] as $qid => $weeks) {
            $wpdb->update($table, array('w1'=>isset($weeks['w1'])?1:0,'w2'=>isset($weeks['w2'])?1:0,'w3'=>isset($weeks['w3'])?1:0,'w4'=>isset($weeks['w4'])?1:0,'w5'=>isset($weeks['w5'])?1:0,'w6'=>isset($weeks['w6'])?1:0), array('id'=>(int)$qid));
        }
        echo '<div class="notice notice-success"><p>Weeks updated.</p></div>';
    }
}

MFSD_Personality_Test::instance();