# MFSD Personality Test — Technical Specification v1.0

**Plugin directory:** `mfsd-personality-test/`
**Shortcode(s):** `[mfsd_personality_test]`
**Version:** 9.5.0
**Author:** MisterT9007
**Purpose:** The "Who Am I (Part 1)" activity in the My Future Self Foundation Course. Students aged 12-14 answer 12 MBTI either/or questions (3 per axis) and 8 DISC 5-point scale questions, then receive a personalised personality reveal with avatar image, a 5-tab AI-generated Steve summary, and an interactive DISC polar plot. The plugin is week-aware (weeks 1-6) and integrates the SteveGPT chatbot widget throughout for real-time guidance. Completion triggers the `mfsd-ordering` course integration.

---

## File Structure

```
mfsd-personality-test/
├── mfsd-personality-test.php         Bootstrap, singleton, DB install, REST API, scoring
├── admin-page.php                    Tabbed WP admin UI
├── assets/
│   ├── mfsd-personality-test.js      Vanilla-JS frontend (IIFE)
│   ├── mfsd-personality-test.css     Gamer-only theme styles
│   ├── personality-avatars.jpg       16-type overview image (families screen)
│   └── Avatars/
│       ├── Logistician.png
│       ├── Defender.png
│       ├── ... (16 type avatar PNG files)
│       └── Mediatorv3.png
└── techspecs/
    └── PersonalityTest_TechSpec_v1.0.md
```

---

## Database Schema

All tables are created via `dbDelta()` in `MFSD_Personality_Test::install()`.

### `wp_mfsd_ptest_questions`
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| q_order | INT | Display order |
| q_type | ENUM('MBTI','DISC') | |
| q_text | TEXT | Full question |
| mbti_axis | CHAR(1) NULL | 1=E/I, 2=S/N, 3=T/F, 4=J/P |
| mbti_letter | CHAR(1) NULL | Letter option A maps to |
| mbti_letter_b | CHAR(1) NULL | Letter option B maps to |
| mbti_option_a_text | TEXT NULL | Display text for option A |
| mbti_option_b_text | TEXT NULL | Display text for option B |
| disc_mapping | JSON NULL | `{"D":1,"I":0,"S":0,"C":0}` contribution weights |
| w1–w6 | TINYINT(1) | Whether question appears in each week |

**Seed Data — 12 MBTI questions (all w1-w6 enabled):**

| Q# | Axis | Option A → Letter | Option B → Letter |
|---|---|---|---|
| 1 | E/I | Home alone → I | Out with others → E |
| 2 | E/I | Something to be avoided → I | Something to be enjoyed → E |
| 3 | E/I | Private and reserved → I | Outgoing and social → E |
| 4 | S/N | Facts, details and step by step → S | Concepts, theories and the big picture → N |
| 5 | S/N | Actual and practical → S | Things that can only be imagined → N |
| 6 | S/N | Realistic and common sense → S | Imaginative and my own way → N |
| 7 | T/F | Heart, feelings and emotions → F | Head, reasoning and logic → T |
| 8 | T/F | Close and personable → F | Somewhat distant and objectively → T |
| 9 | T/F | Too emotional → F | Too cold → T |
| 10 | J/P | Liberating → J | Restricting → P |
| 11 | J/P | Structured and planned → J | Unstructured and spontaneous → P |
| 12 | J/P | Control my environment → J | Go with the flow → P |

**Seed Data — 8 DISC questions (all w1-w6 enabled, orders 13-20):**
Pure single-dimension mappings (e.g., D:1 I:0 S:0 C:0). Statements test assertiveness (D), sociability (I), stability preference (S), and detail-orientation (C).

### `wp_mfsd_ptest_answers`
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| user_id | BIGINT UNSIGNED | WP user ID |
| week_num | TINYINT | 1-6 |
| question_id | BIGINT UNSIGNED | FK to questions |
| q_type | ENUM('MBTI','DISC') | |
| answer | VARCHAR(10) | 'A' or 'B' for MBTI; '1'-'5' for DISC |
| mbti_axis | CHAR(1) NULL | Copied from question |
| mbti_letter | CHAR(1) NULL | The chosen MBTI letter |
| d_contribution / i_contribution / s_contribution / c_contribution | FLOAT NULL | Pre-computed DISC contributions |
| created_at | DATETIME | |
| KEY | (user_id, week_num), (user_id, question_id) | |

### `wp_mfsd_ptest_results`
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| user_id | BIGINT UNSIGNED | |
| week_num | TINYINT | |
| test_type | ENUM('MBTI','DISC','COMBINED') | One row per type |
| mbti_type | CHAR(4) NULL | e.g. 'INFJ' |
| mbti_details | JSON NULL | `{raw_answers: [...]}` |
| disc_d_score / disc_i_score / disc_s_score / disc_c_score | FLOAT NULL | Percentage scores |
| disc_primary | VARCHAR(20) NULL | e.g. 'D' |
| disc_details | JSON NULL | Full scores object |
| ai_summary | LONGTEXT NULL | Full Steve AI text with `[SECTION:]` markers |
| created_at / updated_at | DATETIME | |
| UNIQUE | (user_id, week_num, test_type) | `REPLACE` used for upsert |

### `wp_options` Settings
| Option | Default | Notes |
|---|---|---|
| mfsd_ptest_cache_ai_summaries | '1' | Cache AI summaries; skip regeneration if cached |
| mfsd_ptest_course_management | 1 | Enable mfsd-ordering integration |
| mfsd_stevegpt_map_who_am_i_chatbot | 'chatbot_69eb7ca000e67' | Chatbot widget ID |
| mfsd_stevegpt_map_who_am_i_intro | 'chatbot_69fb3aee4a0be' | Intro message chatbot |
| mfsd_stevegpt_map_who_am_i_guidance | 'chatbot_69fb3b7fa0fa2' | Per-question guidance chatbot |
| mfsd_stevegpt_map_who_am_i_summary | 'chatbot_69fb3bf9a176a' | Summary generation chatbot |

---

## Assessment Flow

```
[Student] Page load (week detected from page title)
    → checkStatus() → GET /mfsd-ptest/v1/status?week=N
        → 'completed'    → jump to renderSummary()
        → 'in_progress'  → loadQuestions() → resumeFromLastQuestion()
        → 'not_started'  → renderIntro()

[Screen 1] Intro
    → GET /mfsd-ptest/v1/intro?week=N
    → Steve AI opening message (Steve Sallis voice, 2-3 sentences, no MBTI/DISC terms)
    → "Next" button → renderFamilies()

[Screen 2] Families Overview
    → Static screen showing personality-avatars.jpg (overview image)
    → Steve explains 4 families (Analysts/Diplomats/Sentinels/Explorers)
    → FAMILY_STEVE descriptions from JS constants
    → "Start Test" → loadQuestions() (GET /questions?week=N) → renderQuestion()

[Screen 3+] Question Loop (20 questions: 12 MBTI + 8 DISC)
    → Each question screen fetches AI guidance via POST /question-guidance
    → MBTI questions: two large tap-target buttons (option A / option B)
        → Click → POST /answer with {q_type:'MBTI', answer:'A'|'B', letter, axis}
    → DISC questions: 5-point colour-coded scale (👎👎🤔😐👍💯)
        → Click → POST /answer with {q_type:'DISC', answer:1-5, d/i/s/c_contribution}
        → contribution = mapping[letter] × (answer_value - 3)
    → SteveGPT chatbot widget shown on each question for real-time help
    → Progress bar shows idx/totalQ %
    → After last question → renderSummary()

[Screen 4] Personality Reveal
    → POST /summary → calculate_mbti() + calculate_disc() + call_ai(build_summary_prompt())
    → Shows: family label (coloured), 160px circular avatar image, "You are The [Name]"
    → Description + family description
    → "View My Summary" → renderSummaryDetail()

[Screen 5] Detailed Summary (tabbed)
    → parseSummarySections() parses [SECTION:Title] markers from AI response
    → 5 tabs: Who You Are | Your Strengths | Learning & School | Friends & Relationships | Your Growth Edge
    → DISC polar plot (400×400 canvas) + 4 score bars
    → SteveGPT chatbot (moved, not cloned) for post-test conversation
    → Navigation: "View My Badges" + "Return to Course"
```

---

## Key Flows

### MBTI Scoring (`calculate_mbti`)

```
For each of 4 axes (1=E/I, 2=S/N, 3=T/F, 4=J/P):
    Count occurrences of each letter from mbti_answers
    axis_map = {1:[E,I], 2:[S,N], 3:[T,F], 4:[J,P]}
    Winner = letter with count >= opponent (ties go to first letter: E/S/T/J)
Concatenate 4 winners → 4-letter MBTI type
```

3 questions per axis → majority (≥2) wins. The answer record stores the chosen letter directly (not just A/B), so scoring is a simple count.

### DISC Scoring (`calculate_disc`)

```
For each DISC answer:
    contribution = mapping[letter] × (answer_value - 3)
    (answer=1 → contribution=-2, 3 → 0, 5 → +2 for a mapping weight of 1)
Sum all D/I/S/C contributions
Normalize to percentages (sum=100)
primary = dimension with highest %
```

8 questions, each targeting one dimension (mapping = `{D:1, I:0, S:0, C:0}` etc.). The contribution calculation is performed client-side and stored in the answer record.

### Week Detection

```php
if (is_page()) {
    $title = get_the_title();
    if (preg_match('/Week\s*([1-6])/i', $title, $m)) {
        $week = (int) $m[1];
    }
}
```

The `week` value is localized to JS as `cfg.week`. Questions are filtered by the `wN` column for the detected week. The task slug for mfsd-ordering is `personality_test_week_N`.

### Resume Logic

If `status = 'in_progress'`, the status endpoint returns `answered_question_ids`. The JS finds the first question ID not in that array and renders from there. If all questions are answered but no results row exists, it jumps to `renderSummary()`.

### AI Summary Caching

When `mfsd_ptest_cache_ai_summaries = '1'`:
1. `POST /summary` first checks for an existing COMBINED result row with non-empty `ai_summary`
2. If found, returns cached values (no AI call)
3. If not found or `force_regenerate=true`, recalculates MBTI/DISC and calls `call_ai()`
4. Result stored via `$wpdb->replace()` (upsert) for all three test_type rows

### SteveGPT Chatbot Context Injection

The shortcode reads the user's existing COMBINED result. If found, it constructs a context string:
```
"Student context for the Who Am I personality quiz. Personality type: [nickname] ([group] family). [description] Communication style: [disc name] - [characteristics]. Use this context to personalise your responses to this student."
```
Characters `"`, `[`, `]`, newlines are stripped (shortcode attribute safety). This is passed as the `context` attribute to `[stevegpt_chatbot]`, enabling the chatbot to give personalised responses. The rendered chatbot HTML is placed in a hidden `#mfsd-ptest-chat-source` div; JS moves it into each question screen and the summary screen.

### AI Fallback

If `call_ai()` returns null or a response shorter than 200 characters, `generate_fallback_summary()` produces a structured plain-text summary using the pre-computed MBTI type context data. The fallback uses the same `[SECTION:Title]` format so the tab parser works identically.

---

## AJAX / REST Endpoints

**Namespace:** `mfsd-ptest/v1`
**Authentication:** `is_user_logged_in()` (all routes via `check_permission()`)
**Nonce:** `X-WP-Nonce` header, created via `wp_create_nonce('wp_rest')`, localized as `cfg.nonce`

| Method | Route | Description |
|---|---|---|
| GET | `/questions` | Returns all questions enabled for `?week=N` (w1–w6 column filter), ordered by `q_order`. Includes `mbti_option_a_text`, `mbti_option_b_text`, `mbti_letter`, `mbti_letter_b`, `disc_mapping` (decoded JSON). |
| POST | `/answer` | Saves or updates a single answer. MBTI: stores `mbti_axis` and `mbti_letter` (the chosen letter). DISC: stores pre-computed `d/i/s/c_contribution` values. Upserts on (user_id, week_num, question_id). |
| GET | `/status` | Returns `{status, week, total_questions, answered_count, answered_question_ids, last_question_id}`. Status: `not_started` (0 answers), `in_progress` (answers but no results row), `completed` (results row exists). |
| GET | `/intro` | Fetches question count for the week; calls `call_ai()` for a 2-3 sentence intro message (Steve Sallis voice, no MBTI/DISC terms). Returns hardcoded fallback if AI fails. |
| POST | `/question-guidance` | Takes `question_text`; calls `SteveGPT_Chatbot::get(guidance_chatbot_id)->query()` using the guidance chatbot's own `render_prompt()`. Returns `guidance` text or a generic "just pick whichever feels like you" fallback. |
| POST | `/question-chat` | Takes `message` and `question_text`; builds a direct prompt (SteveGPT voice, no MBTI/DISC terms); calls `call_ai()`. Returns chatbot response or hardcoded fallback. |
| POST | `/summary` | Core endpoint. Optionally reads cache. Calls `calculate_mbti()`, `calculate_disc()`, `build_summary_prompt()`, `call_ai()`. Stores MBTI/DISC/COMBINED rows. Calls `mfsd_set_task_status()` on completion. Returns `{mbti_type, disc_scores, ai_summary, cached}`. |

---

## Admin Panel

Located at **WordPress Admin → Personality Test** (dashicons-smiley, position 30).

**Tab 1: Manage Questions**
- Lists all questions sorted by type then order
- Displays week checkboxes (w1–w6) per question, editable inline
- "Update Weeks" button saves changes with `check_admin_referer('mfsd_ptest_update')`
- Delete question link (nonce per ID: `mfsd_ptest_delete_{id}`)

**Tab 2: Add Question**
- Type selector: MBTI or DISC
- MBTI panel: question text, order, axis selector (1-4), Option A text + letter, Option B text + letter; axis auto-suggests letter pair
- DISC panel: question text, order, D/I/S/C weight fields (floats, stored as JSON)
- Week checkboxes w1-w6
- Submit with `check_admin_referer('mfsd_ptest_add')`

**Tab 3: Settings**
- "Cache AI summaries" checkbox (toggling `mfsd_ptest_cache_ai_summaries`)
- "Course management" toggle (toggling `mfsd_ptest_course_management`)
- Save with `check_admin_referer('mfsd_ptest_settings')`

**Tab 4: Data Management**
- Clear user data: WP user ID input + optional week filter → deletes from answers and results tables + mfsd_task_progress for weeks 1-6
- Clear all data: requires typing `DELETE ALL DATA` exactly → TRUNCATE on answers and results tables
- Danger Zone section

**Tab 5: AI Debug**
- Test AI call: input field for custom prompt, chatbot ID selector, test button → displays response
- MWAI status: checks if `$GLOBALS['mwai']` / `SteveGPT_Chatbot` are available
- Recent AI call log: reads `mfsd_ptest_ai_calls` transient (last 10 calls with status/duration/lengths)
- Recent results: queries results table ordered by updated_at desc

---

## SteveGPT Integration

**Method used:** `SteveGPT_Chatbot::get($chatbot_id)->query($prompt, $user_id)` via the `call_ai()` wrapper. Falls back to `null` on any exception.

**Four chatbot IDs** (configurable via wp_options):

| Chatbot | Option Key | Used For |
|---|---|---|
| `mfsd_stevegpt_map_who_am_i_chatbot` | `chatbot_69eb7ca000e67` | Widget embedded on question/summary screens; also used for `/question-chat` |
| `mfsd_stevegpt_map_who_am_i_intro` | `chatbot_69fb3aee4a0be` | `/intro` endpoint — opening Steve message |
| `mfsd_stevegpt_map_who_am_i_guidance` | `chatbot_69fb3b7fa0fa2` | `/question-guidance` endpoint — per-question hint; uses chatbot's own `render_prompt()` |
| `mfsd_stevegpt_map_who_am_i_summary` | `chatbot_69fb3bf9a176a` | `/summary` endpoint — generates the 5-section personality summary |

**Summary Prompt Structure (`build_summary_prompt`):**
1. Role: Steve Sallis, motivational teacher-coach for 12-14 year olds
2. MBTI section (internal reference only — code never shown to student): nickname, family, description, strengths, learning style, communication, careers, teen advice
3. DISC section: primary style, percentages, characteristics, motivations, challenges, learning preferences, teen strengths, advice
4. Task: write 5 sections using exact markers: `[SECTION:Who You Are]`, `[SECTION:Your Strengths]`, `[SECTION:Learning & School]`, `[SECTION:Friends & Relationships]`, `[SECTION:Your Growth Edge]`
5. Rules: start first section with 'Steve says:', second person, NEVER use 4-letter codes or 'MBTI'/'Myers-Briggs'/'DISC', age-appropriate

**AI Call Log:**
- `log_ai_call(status, msg, prompt_len, response_len, elapsed_ms)`
- Stored in WordPress transient `mfsd_ptest_ai_calls` (last 10, 1-day TTL)
- Viewable in Admin → AI Debug tab

**Fallback:**
- `/intro`: hardcoded "Steve says: Hey there, superstar!" message
- `/question-guidance`: "Just think about which option feels most like you, just go with it!"
- `/summary`: `generate_fallback_summary()` produces structured `[SECTION:]` text from static type context data

---

## Assets

### `mfsd-personality-test.css`
Registered with version `9.5.0` (`wp_register_style`). Enqueued when shortcode is on the page.

**Theme:** Gamer only — no corporate variant. Uses CSS variables from `.rag-wrap` (prefix inherited from the Weekly RAG plugin before renaming). Variables map to theme tokens (`--color-bg-page`, `--font-heading`, etc.) with dark-theme fallbacks.

**Key Components:**
- `.rag-wrap` — max-width container, plugin CSS variable scope
- `.rag-card` — dark surface card with border, padding, border-radius
- `.ptest-either-btn` — large tap targets (full-width), hover lift with cyan glow; rendered as two stacked options with "or" divider
- `.ptest-either-or-divider` — centered "or" separator
- `.disc-scale-btn` — 5 coloured buttons (red/orange/grey/light green/green) with emoji icon; hover transform
- `.ptest-progress-container` — progress bar with cyan gradient fill and percentage label
- `.ptest-result-tab` — tab navigation buttons, `.active` state
- `.ptest-tab-panel` — tab panel container (display:none by default)
- `.disc-breakdown` — 4-column grid for D/I/S/C score bars
- `#disc-polar-plot` — 400×400px HTML5 Canvas element
- `.ptest-nav-actions` — flex container for Badges + Course navigation buttons
- `.rag-ai-intro` — Steve opening message block (styled info box)
- `.rag-ai-question` — per-question AI guidance block
- `.rag-chatwrap` — chatbot widget wrapper

### `mfsd-personality-test.js`
IIFE, vanilla JS (no framework). Registered with version `9.5.0`, loaded in footer.

**Config object (`MFSD_PTEST_CFG`)** localized from PHP:
- `restUrlQuestions`, `restUrlAnswer`, `restUrlSummary`, `restUrlStatus`, `restUrlIntro`, `restUrlGuidance`, `restUrlQuestionChat`
- `nonce`, `week`, `avatarImageUrl`, `avatarsBaseUrl`, `urlBadges`, `urlCourse`

**Constants in JS:**

| Constant | Contents |
|---|---|
| `PROFILES` | 16 MBTI types → `{name, family, familyColor}` |
| `AVATAR_FILES` | 16 MBTI types → PNG filename in `assets/Avatars/` |
| `DESCRIPTIONS` | 16 MBTI types → one-line description |
| `FAMILY_DESCRIPTIONS` | 4 families → factual description |
| `FAMILY_STEVE` | 4 families → Steve-voice paragraph for families screen |
| `FAMILY_COLORS` | 4 families → hex colour (`#88619a`/`#33a474`/`#4298b4`/`#e4ae3a`) |

**State:** `week`, `questions[]`, `idx`, `summaryCache`

**Screen functions:**
1. `init()` → calls `renderIntro()` after `checkStatus()`
2. `renderIntro()` — fetches intro message; if completed/in_progress, jumps to appropriate screen
3. `renderFamilies()` — static families overview with avatar image grid; uses `FAMILY_STEVE` constants
4. `loadQuestions()` + `resumeFromLastQuestion(lastQId, answeredIds)` — resume support
5. `renderQuestion()` — fetches AI guidance; renders MBTI either/or buttons or DISC 5-scale; includes chatbot clone
6. `renderMBTIEitherOr(card, q)` — two `.ptest-either-btn` elements with "or" divider
7. `renderDISCOptions(card, q)` — 5 `.disc-scale-btn` elements
8. `handleMBTIAnswer(question, choice, chosenLetter)` → POST `/answer`
9. `handleDISCAnswer(question, answerValue)` → contribution = `mapping[letter] × (value-3)` → POST `/answer`
10. `renderSummary()` → POST `/summary`; shows avatar, family, type name, description
11. `renderSummaryDetail()` → parses sections, renders tabs, DISC plot, moves chatbot

**DISC Polar Plot (`createDISCPolarPlot`):**
- 400×400 canvas, center (200,200), max radius 140
- 4 quadrant segments: D=top-right, I=bottom-right, S=bottom-left, C=top-left
- Each segment filled proportionally with family colour
- Axis labels: Active/Reflective (vertical), People Focus/Task Focus (horizontal)
- Letter + percentage labels at quadrant midpoints

**Parser (`parseSummarySections`):**
- Regex: `/\[SECTION:([^\]]+)\]/g`
- Extracts title + content between markers
- Returns `[{title, content}]` array
- Falls back to `[{title:'Your Results', content: fullText}]` if no markers found

**Chatbot handling:**
- Question screens: `chatSource.cloneNode(true)` with conversation cleared
- Summary screen: `chatSource` is **moved** (not cloned) to prevent duplicate widget containers
- Default greeting text replaced if it matches "How can I help" pattern

---

## Security

- **Nonce authentication:** All REST routes check `is_user_logged_in()` via `check_permission()`. The `X-WP-Nonce: wp_rest` header is set on every `apiFetch()` call.
- **Admin forms:** All admin form handlers use `check_admin_referer()` with action-specific nonces.
- **Input sanitization:** `sanitize_text_field()` / `sanitize_textarea_field()` for admin form strings; `(int)` casting for numeric params; `$wpdb->prepare()` for all queries with user input.
- **AI context stripping:** Before injecting personality results as a chatbot shortcode attribute, characters that could break the attribute are stripped: `"`, `[`, `]`, `\n`, `\r`.
- **Data clear confirmation:** Bulk "DELETE ALL DATA" action requires exact string confirmation to prevent accidental truncation.
- **Output escaping:** All admin output uses `esc_html()`. JS dynamic content uses `textContent` (not `innerHTML`) for all user-facing strings.

---

## Inter-Plugin Dependencies

| Plugin | Integration | Details |
|---|---|---|
| `mfsd-ordering` | Course gating + completion | Shortcode calls `mfsd_get_task_status($student_id, 'personality_test_week_N')`. If `locked` → returns locked message. If `available` → sets `in_progress`. `POST /summary` calls `mfsd_set_task_status($user_id, 'personality_test_week_N', 'completed')`. Per-week slug pattern enables independent tracking. |
| `stevegtp` | AI calls + chatbot widget | `call_ai()` calls `SteveGPT_Chatbot::get($chatbot_id)->query($prompt, $user_id)`. The `[stevegpt_chatbot]` shortcode is also rendered via `do_shortcode()` and embedded as a widget. Four separate chatbot IDs are used for different purposes. |
| `myfutureself-theme` | CSS variables | Styles reference `--font-heading`, `--font-body`, `--color-bg-page`, etc. with dark fallbacks. The `rag-` CSS class prefix is a legacy naming convention from the Weekly RAG plugin which shared these styles originally. |

---

## Version History

| Version | Changes |
|---|---|
| 9.5.0 | Current version. MBTI either/or format with `mbti_option_a_text`/`mbti_option_b_text` and `mbti_letter_b` columns. `calculate_mbti()` uses direct letter counting. `call_ai()` uses `SteveGPT_Chatbot::get()->query()`. Chatbot moved (not cloned) on summary screen to prevent duplicate containers. |
| < 9.5.0 | Earlier versions used different question format; MBTI may have used radio buttons; chatbot handling different. SteveGPT integration may have used older `$GLOBALS['mwai']` pattern. |
