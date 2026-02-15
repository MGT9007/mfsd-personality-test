<div class="wrap">
    <h1>Personality Test Admin</h1>
    
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
