# Personality Profile Helper — `mfsd-personality-test`

Version 1.0.0 | 29 September 2026 | Status: **Ready to build: build this FIRST**

> A shared function in the Personality Test plugin (Who Am I, Week 1) that lets other plugins read a student's personality **without reading the tables themselves**.
> Plugin: `mfsd-personality-test` **v9.7.0 → v10.0.0** (other plugins now depend on it).
>
> **Used by:**
> - **Dream & Junk Jobs** (W2T2): fit bands, plot twists, careers chat
> - **Life Wheel** (W2T1): one optional personality line in the overall summary
> - **Dream Life** (W2T4): the "About {name}" context
>
> The content is taken from PERSONALITY_FIT_TECH_SPEC_v1_2_0 §2–3, which this file replaces as the build spec for the helper.

---

## 1. Why
- Dream Jobs read the old RAG table (`wp_mfsd_mbti_results.type4`) and so missed Who Am I results, or got a *different* type from the one on the student's avatar.
- The rich per-type data (`get_mbti_type_context()`) is private.
- Wealth & Happiness keeps its own copy.

One helper gives every plugin the same answer and keeps the 4-letter code out of the browser.

---

## 2. What the helper returns (MBTI only)

From `wp_mfsd_ptest_results`, taking the latest `week_num` row where `test_type IN ('MBTI','COMBINED')`:

| Data | Source | Show to student? |
|---|---|---|
| Type code, e.g. `ENFP` | `mbti_type` | ❌ Never. Server and prompt only |
| Character name + family (*The Campaigner, Diplomat*) | `get_mbti_type_context()` | ✅ |
| Avatar PNG | `assets/Avatars/` map | ✅ |
| Strengths, learning style, communication style | type context | ✅ as plain phrases |
| **Suggested careers** | type context `careers` | ✅ as "Jobs people like you often enjoy" |
| **Strength of each preference** (3–0 vs 2–1 per axis) | `mbti_details.raw_answers` | ✅ only as "strong" / "close to the middle", never letters |

With 3 questions per axis, a 2–1 split is close to the middle. The AI is told to go easy on those axes, so a slight preference can never be the only reason for a stretch verdict.

---

## 3. The helper

"Helper" here means one shared function added to the Personality Test plugin. Other plugins call it to get the student's personality instead of each reading the table themselves (the same pattern as `mfsd_get_task_status()`).

### 3.1 Signature
```php
/**
 * Student personality profile for use by other MFSD plugins.
 * Returns null when there is no usable result (not done, or Who Am I in test mode).
 * 'code' is for prompts only — never render it or return it over REST.
 */
function mfsd_get_personality_profile( int $user_id ): ?array
```
Returns:
```php
[
  'code'           => 'ENFP',          // prompt only
  'name'           => 'The Campaigner',
  'family'         => 'Diplomat',
  'avatar_url'     => '.../Avatars/Campaigner.png',
  'description'    => '...',
  'strengths'      => '...',
  'learning_style' => '...',
  'communication'  => '...',
  'careers'        => ['Journalism', 'Teaching', ...],   // split from the context string
  'axes'           => [
     'energy'    => ['lean' => 'people',   'strength' => 'strong'],  // E/I
     'info'      => ['lean' => 'ideas',    'strength' => 'slight'],  // S/N
     'decisions' => ['lean' => 'feelings', 'strength' => 'strong'],  // T/F
     'structure' => ['lean' => 'flexible', 'strength' => 'slight'],  // J/P
  ],
  'week'           => 1,
]
```

### 3.2 Test-mode rule (Mark: *"if it's in test mode and not saved then don't pull personality test data"*)
```php
function mfsd_ptest_is_test_mode(): bool {
    return get_option( 'mfsd_ptest_cache_ai_summaries', '1' ) !== '1';
}
```
- If `mfsd_ptest_is_test_mode()` is true, `mfsd_get_personality_profile()` **returns null**, even if older rows exist in `ptest_results`, because those rows are likely stale test data.
- It also exposes `mfsd_ptest_is_test_mode()` so the consuming plugins can tell apart **"not done yet"** (show the unlock card to the student) and **"test mode"** (show nothing to the student, and a notice to admins).
- The helper does **not** fall back to the legacy `mfsd_mbti_results` table. Who Am I is the single source of truth, which avoids the "two different types" problem.

### 3.3 Other changes in the plugin
- `get_mbti_type_context()` data moves to a `public static` accessor, so there's only one copy of it.
- Version: **9.7.0 → 10.0.0**, because other plugins now depend on it.

---

## 4. Platform rules this helper enforces
- **Myers-Briggs only.** DISC isn't used anywhere downstream.
- **The code is for prompts only.** It must never be rendered, returned over REST, put into a JS config, or put into a chatbot `context` attribute. Only `name`, `family`, `avatar_url`, `strengths`, `careers` and the `axes` leans (as words) may reach the browser.
- **Who Am I is the single source of truth.** There's **no fallback** to `wp_mfsd_mbti_results`.
- **Test mode** (`mfsd_ptest_cache_ai_summaries` ≠ `'1'`): the helper returns `null`. Consuming plugins hide personality features from students and show admins *"Personality data off: Who Am I is in test mode"*. The cache/save behaviour itself is **left as is** (Mark: needed for testing).

---

## 5. Testing
- A student with Who Am I completed gets the full profile, and the avatar URL matches the home widget and Quest Log.
- A student without Who Am I gets `null`.
- A student with only a legacy RAG type gets `null` (no fallback).
- Test mode with older rows present gets `null`, and `mfsd_ptest_is_test_mode()` returns true.
- Axis strength: a 3–0 split gives `strong`, a 2–1 split gives `slight` (check against `mbti_details.raw_answers`).
- `careers` comes back as an array with no empty entries.
- `get_mbti_type_context()` data is now read through the public static accessor, and Who Am I's own results screen is unchanged.
- Grep the output of every consuming page for `/\b[EI][SN][TF][JP]\b/`, "MBTI" and "Myers": zero hits.
- Version header and `const VERSION` both read `10.0.0`.

---

Personality Profile Helper Tech Spec v1.0.0 | 29 September 2026
