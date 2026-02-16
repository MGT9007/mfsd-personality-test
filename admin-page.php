<div class="wrap">
    <h1>Personality Test Admin</h1>

    <?php
    // Handle settings save
    if (isset($_POST['mfsd_ptest_save_settings'])) {
        check_admin_referer('mfsd_ptest_settings');
        update_option('mfsd_ptest_cache_ai_summaries', isset($_POST['cache_ai_summaries']) ? '1' : '0');
        echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
    }

    // Handle clear user data
    if (isset($_POST['mfsd_ptest_clear_user_data'])) {
        check_admin_referer('mfsd_ptest_clear_user_' . $_POST['user_id']);
        global $wpdb;
        $user_id = (int)$_POST['user_id'];
        $week = isset($_POST['week']) ? (int)$_POST['week'] : 0;
        
        $ans_table = $wpdb->prefix . 'mfsd_ptest_answers';
        $res_table = $wpdb->prefix . 'mfsd_ptest_results';
        
        if ($week > 0) {
            // Clear specific week
            $wpdb->delete($ans_table, array('user_id' => $user_id, 'week_num' => $week));
            $wpdb->delete($res_table, array('user_id' => $user_id, 'week_num' => $week));
            echo '<div class="notice notice-success"><p>Cleared Week ' . $week . ' data for User ID ' . $user_id . '</p></div>';
        } else {
            // Clear all weeks for user
            $wpdb->delete($ans_table, array('user_id' => $user_id));
            $wpdb->delete($res_table, array('user_id' => $user_id));
            echo '<div class="notice notice-success"><p>Cleared all test data for User ID ' . $user_id . '</p></div>';
        }
    }

    // Handle clear all data
    if (isset($_POST['mfsd_ptest_clear_all_data'])) {
        check_admin_referer('mfsd_ptest_clear_all');
        if ($_POST['confirm_clear'] === 'DELETE ALL DATA') {
            global $wpdb;
            $ans_table = $wpdb->prefix . 'mfsd_ptest_answers';
            $res_table = $wpdb->prefix . 'mfsd_ptest_results';
            
            $wpdb->query("TRUNCATE TABLE $ans_table");
            $wpdb->query("TRUNCATE TABLE $res_table");
            echo '<div class="notice notice-success"><p><strong>All test data has been cleared!</strong></p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Incorrect confirmation text. Data was not cleared.</p></div>';
        }
    }

    // Get current settings
    $cache_ai = get_option('mfsd_ptest_cache_ai_summaries', '1');
    ?>
    
    <style>
        .ptest-admin-tabs {
            margin: 20px 0;
            border-bottom: 1px solid #ccc;
        }
        .ptest-admin-tabs button {
            padding: 10px 20px;
            border: none;
            background: #f0f0f0;
            cursor: pointer;
            border-top-left-radius: 4px;
            border-top-right-radius: 4px;
            margin-right: 5px;
        }
        .ptest-admin-tabs button.active {
            background: #fff;
            border: 1px solid #ccc;
            border-bottom: 1px solid #fff;
            position: relative;
            bottom: -1px;
        }
        .ptest-tab-content {
            display: none;
            padding: 20px 0;
        }
        .ptest-tab-content.active {
            display: block;
        }
        .ptest-questions-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .ptest-questions-table th,
        .ptest-questions-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .ptest-questions-table th {
            background: #f5f5f5;
            font-weight: 600;
        }
        .ptest-week-checkboxes label {
            display: inline-block;
            margin-right: 10px;
        }
        .ptest-add-form {
            background: #f9f9f9;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 20px 0;
        }
        .ptest-form-row {
            margin-bottom: 15px;
        }
        .ptest-form-row label {
            display: inline-block;
            width: 150px;
            font-weight: 600;
        }
        .ptest-form-row input[type="text"],
        .ptest-form-row textarea,
        .ptest-form-row select {
            width: calc(100% - 160px);
            max-width: 600px;
        }
        .ptest-form-row textarea {
            height: 80px;
        }
        .ptest-disc-mapping {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            max-width: 600px;
        }
        .ptest-disc-mapping label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .ptest-disc-mapping input {
            width: 100%;
        }
        .ptest-mbti-fields {
            display: none;
        }
        .ptest-disc-fields {
            display: none;
        }
        .ptest-question-text {
            max-width: 400px;
        }
        .button-delete {
            color: #a00;
        }
        .button-delete:hover {
            color: #dc3232;
        }
    </style>

    <div class="ptest-admin-tabs">
        <button class="ptest-tab-btn active" data-tab="manage">Manage Questions</button>
        <button class="ptest-tab-btn" data-tab="add">Add Question</button>
        <button class="ptest-tab-btn" data-tab="settings">Settings</button>
        <button class="ptest-tab-btn" data-tab="data">Data Management</button>
    </div>

    <!-- Manage Questions Tab -->
    <div id="tab-manage" class="ptest-tab-content active">
        <h2>Question Configuration</h2>
        <p>Configure which questions appear in which weeks. Check the box to enable a question for that week.</p>

        <form method="post" action="">
            <?php wp_nonce_field('mfsd_ptest_update'); ?>
            
            <table class="ptest-questions-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Order</th>
                        <th>Question</th>
                        <th>W1</th>
                        <th>W2</th>
                        <th>W3</th>
                        <th>W4</th>
                        <th>W5</th>
                        <th>W6</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $q): ?>
                    <tr>
                        <td><?php echo esc_html($q['id']); ?></td>
                        <td><strong><?php echo esc_html($q['q_type']); ?></strong></td>
                        <td><?php echo esc_html($q['q_order']); ?></td>
                        <td class="ptest-question-text"><?php echo esc_html($q['q_text']); ?></td>
                        <?php for ($w = 1; $w <= 6; $w++): ?>
                        <td>
                            <input type="checkbox" 
                                   name="questions[<?php echo $q['id']; ?>][w<?php echo $w; ?>]" 
                                   <?php checked($q['w' . $w], 1); ?>>
                        </td>
                        <?php endfor; ?>
                        <td>
                            <a href="?page=mfsd-ptest-admin&delete_question=<?php echo $q['id']; ?>&_wpnonce=<?php echo wp_create_nonce('mfsd_ptest_delete_' . $q['id']); ?>" 
                               class="button button-small button-delete"
                               onclick="return confirm('Are you sure you want to delete this question?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <p>
                <button type="submit" name="mfsd_ptest_update_weeks" class="button button-primary button-large">
                    Save Week Configuration
                </button>
            </p>
        </form>
    </div>

    <!-- Add Question Tab -->
    <div id="tab-add" class="ptest-tab-content">
        <h2>Add New Question</h2>

        <form method="post" action="" class="ptest-add-form">
            <?php wp_nonce_field('mfsd_ptest_add'); ?>

            <div class="ptest-form-row">
                <label for="q_type">Question Type:</label>
                <select name="q_type" id="q_type" required>
                    <option value="">-- Select Type --</option>
                    <option value="MBTI">MBTI (Myers-Briggs)</option>
                    <option value="DISC">DISC</option>
                </select>
            </div>

            <div class="ptest-form-row">
                <label for="q_order">Display Order:</label>
                <input type="number" name="q_order" id="q_order" value="<?php echo count($questions) + 1; ?>" required>
            </div>

            <div class="ptest-form-row">
                <label for="q_text">Question Text:</label>
                <textarea name="q_text" id="q_text" required></textarea>
            </div>

            <!-- MBTI-specific fields -->
            <div id="mbti-fields" class="ptest-mbti-fields">
                <h3>MBTI Configuration</h3>
                
                <div class="ptest-form-row">
                    <label for="mbti_axis">Axis:</label>
                    <select name="mbti_axis" id="mbti_axis">
                        <option value="1">1 - Extraversion/Introversion (E/I)</option>
                        <option value="2">2 - Sensing/Intuition (S/N)</option>
                        <option value="3">3 - Thinking/Feeling (T/F)</option>
                        <option value="4">4 - Judging/Perceiving (J/P)</option>
                    </select>
                </div>

                <div class="ptest-form-row">
                    <label for="mbti_letter">Letter:</label>
                    <select name="mbti_letter" id="mbti_letter">
                        <option value="E">E - Extraversion</option>
                        <option value="I">I - Introversion</option>
                        <option value="S">S - Sensing</option>
                        <option value="N">N - Intuition</option>
                        <option value="T">T - Thinking</option>
                        <option value="F">F - Feeling</option>
                        <option value="J">J - Judging</option>
                        <option value="P">P - Perceiving</option>
                    </select>
                </div>
            </div>

            <!-- DISC-specific fields -->
            <div id="disc-fields" class="ptest-disc-fields">
                <h3>DISC Contribution Mapping</h3>
                <p>Enter how much this question contributes to each DISC dimension (usually 0 or 1):</p>
                
                <div class="ptest-disc-mapping">
                    <div>
                        <label for="disc_d">D (Dominance):</label>
                        <input type="number" step="0.1" name="disc_d" id="disc_d" value="0">
                    </div>
                    <div>
                        <label for="disc_i">I (Influence):</label>
                        <input type="number" step="0.1" name="disc_i" id="disc_i" value="0">
                    </div>
                    <div>
                        <label for="disc_s">S (Steadiness):</label>
                        <input type="number" step="0.1" name="disc_s" id="disc_s" value="0">
                    </div>
                    <div>
                        <label for="disc_c">C (Conscientiousness):</label>
                        <input type="number" step="0.1" name="disc_c" id="disc_c" value="0">
                    </div>
                </div>
            </div>

            <div class="ptest-form-row">
                <label>Enable for Weeks:</label>
                <div class="ptest-week-checkboxes">
                    <?php for ($w = 1; $w <= 6; $w++): ?>
                    <label>
                        <input type="checkbox" name="w<?php echo $w; ?>" checked>
                        Week <?php echo $w; ?>
                    </label>
                    <?php endfor; ?>
                </div>
            </div>

            <p>
                <button type="submit" name="mfsd_ptest_add_question" class="button button-primary button-large">
                    Add Question
                </button>
            </p>
        </form>
    </div>

    <!-- Settings Tab -->
    <div id="tab-settings" class="ptest-tab-content">
        <h2>Plugin Settings</h2>

        <form method="post" action="">
            <?php wp_nonce_field('mfsd_ptest_settings'); ?>

            <div class="ptest-add-form">
                <h3>AI Summary Settings</h3>
                
                <div class="ptest-form-row">
                    <label>
                        <input type="checkbox" name="cache_ai_summaries" <?php checked($cache_ai, '1'); ?>>
                        <strong>Cache AI Summaries</strong>
                    </label>
                    <p style="margin-left: 25px; color: #666; font-size: 14px;">
                        When enabled, AI summaries are saved to the database and reused when students view their results.<br>
                        When disabled, AI summaries are regenerated fresh every time a student views their results.<br>
                        <em>Note: Disabling this will use more AI credits but ensures summaries reflect the latest context.</em>
                    </p>
                </div>

                <p>
                    <button type="submit" name="mfsd_ptest_save_settings" class="button button-primary button-large">
                        Save Settings
                    </button>
                </p>
            </div>
        </form>
    </div>

    <!-- Data Management Tab -->
    <div id="tab-data" class="ptest-tab-content">
        <h2>Data Management & Testing Tools</h2>

        <!-- Current Data Summary -->
        <div class="ptest-add-form" style="background: #f0f8ff; border-left: 4px solid #2271b1;">
            <h3>Current Data Summary</h3>
            <?php
            global $wpdb;
            $ans_table = $wpdb->prefix . 'mfsd_ptest_answers';
            $res_table = $wpdb->prefix . 'mfsd_ptest_results';
            
            $total_answers = $wpdb->get_var("SELECT COUNT(*) FROM $ans_table");
            $total_results = $wpdb->get_var("SELECT COUNT(*) FROM $res_table");
            $unique_users = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $res_table");
            
            echo '<p><strong>Total Answers:</strong> ' . number_format($total_answers) . '</p>';
            echo '<p><strong>Total Results:</strong> ' . number_format($total_results) . '</p>';
            echo '<p><strong>Unique Users:</strong> ' . number_format($unique_users) . '</p>';
            ?>
        </div>

        <!-- Clear Specific User Data -->
        <div class="ptest-add-form">
            <h3>Clear Specific User's Test Data</h3>
            <p>Clear test results for a specific user to allow them to retake the test.</p>
            
            <form method="post" action="" onsubmit="return confirm('Are you sure you want to clear this user\'s test data? This cannot be undone.');">
                <div class="ptest-form-row">
                    <label for="user_id_clear">User ID:</label>
                    <input type="number" name="user_id" id="user_id_clear" required min="1" placeholder="Enter user ID">
                </div>

                <div class="ptest-form-row">
                    <label for="week_clear">Week (optional):</label>
                    <select name="week" id="week_clear">
                        <option value="0">All Weeks</option>
                        <option value="1">Week 1 Only</option>
                        <option value="2">Week 2 Only</option>
                        <option value="3">Week 3 Only</option>
                        <option value="4">Week 4 Only</option>
                        <option value="5">Week 5 Only</option>
                        <option value="6">Week 6 Only</option>
                    </select>
                </div>

                <?php wp_nonce_field('mfsd_ptest_clear_user'); ?>
                
                <p>
                    <button type="submit" name="mfsd_ptest_clear_user_data" class="button button-secondary">
                        Clear User Data
                    </button>
                </p>
            </form>

            <!-- Helper: Find User ID -->
            <div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #f0ad4e; border-radius: 4px;">
                <strong>Need to find a User ID?</strong>
                <p style="margin: 10px 0 0 0;">
                    Go to <strong>Users → All Users</strong> in WordPress admin, hover over a username, 
                    and look at the URL in your browser's status bar. The user ID is the number after "user_id=".
                </p>
            </div>
        </div>

        <!-- View Recent Test Results -->
        <div class="ptest-add-form">
            <h3>Recent Test Completions</h3>
            <?php
            $recent_results = $wpdb->get_results("
                SELECT r.user_id, u.user_login, u.display_name, r.week_num, r.test_type, 
                       r.mbti_type, r.disc_primary, r.created_at
                FROM $res_table r
                LEFT JOIN {$wpdb->prefix}users u ON r.user_id = u.ID
                ORDER BY r.created_at DESC
                LIMIT 10
            ");

            if ($recent_results) {
                echo '<table class="ptest-questions-table">';
                echo '<thead><tr><th>User</th><th>Week</th><th>Type</th><th>MBTI</th><th>DISC</th><th>Date</th><th>Actions</th></tr></thead>';
                echo '<tbody>';
                foreach ($recent_results as $result) {
                    echo '<tr>';
                    echo '<td>' . esc_html($result->display_name ?: $result->user_login) . ' (ID: ' . $result->user_id . ')</td>';
                    echo '<td>Week ' . $result->week_num . '</td>';
                    echo '<td>' . esc_html($result->test_type) . '</td>';
                    echo '<td>' . esc_html($result->mbti_type ?: '-') . '</td>';
                    echo '<td>' . esc_html($result->disc_primary ?: '-') . '</td>';
                    echo '<td>' . date('M j, Y g:i a', strtotime($result->created_at)) . '</td>';
                    echo '<td>';
                    echo '<button type="button" class="button button-small" onclick="document.getElementById(\'user_id_clear\').value=' . $result->user_id . '; document.getElementById(\'week_clear\').value=' . $result->week_num . '; document.querySelector(\'[data-tab=data]\').click();">Clear This</button>';
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p style="color: #666; font-style: italic;">No test results yet.</p>';
            }
            ?>
        </div>

        <!-- DANGER ZONE: Clear All Data -->
        <div class="ptest-add-form" style="background: #fee; border-left: 4px solid #dc3232;">
            <h3 style="color: #dc3232;">⚠️ Danger Zone: Clear All Test Data</h3>
            <p><strong style="color: #a00;">WARNING:</strong> This will permanently delete ALL test results for ALL users. This cannot be undone!</p>
            
            <form method="post" action="" onsubmit="return confirm('⚠️ FINAL WARNING ⚠️\n\nThis will DELETE ALL test data for ALL users permanently!\n\nAre you absolutely sure?');">
                <?php wp_nonce_field('mfsd_ptest_clear_all'); ?>
                
                <div class="ptest-form-row">
                    <label for="confirm_clear">Type exactly <code>DELETE ALL DATA</code> to confirm:</label>
                    <input type="text" name="confirm_clear" id="confirm_clear" required 
                           placeholder="Type: DELETE ALL DATA" 
                           style="font-family: monospace; border-color: #dc3232;">
                </div>

                <p>
                    <button type="submit" name="mfsd_ptest_clear_all_data" class="button" 
                            style="background: #dc3232; color: white; border-color: #a00;">
                        🗑️ Clear All Test Data
                    </button>
                </p>
            </form>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Tab switching
        $('.ptest-tab-btn').on('click', function() {
            var tab = $(this).data('tab');
            $('.ptest-tab-btn').removeClass('active');
            $(this).addClass('active');
            $('.ptest-tab-content').removeClass('active');
            $('#tab-' + tab).addClass('active');
        });

        // Show/hide type-specific fields
        $('#q_type').on('change', function() {
            var type = $(this).val();
            $('.ptest-mbti-fields, .ptest-disc-fields').hide();
            
            if (type === 'MBTI') {
                $('.ptest-mbti-fields').show();
            } else if (type === 'DISC') {
                $('.ptest-disc-fields').show();
            }
        });
    });
    </script>
</div>