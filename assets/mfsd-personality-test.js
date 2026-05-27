(function () {
  'use strict';

  const cfg          = window.MFSD_PTEST_CFG || {};
  const root         = document.getElementById('mfsd-ptest-root');
  if (!root) return;

  const isParentView = cfg.role === 'parent' && cfg.studentId > 0;

  const chatSource    = document.getElementById('mfsd-ptest-chat-source');
  const summarySource = document.getElementById('mfsd-ptest-summary-chat-source');

  let week      = cfg.week || 1;
  let questions = [];
  let idx       = 0;
  let summaryCache = null;

  /* ================================================================
     PROFILES — student-facing only, no MBTI codes shown
     ================================================================ */
  const PROFILES = {
    ISTJ:{name:'Logistician', family:'Sentinels',  familyColor:'#4298b4'},
    ISFJ:{name:'Defender',    family:'Sentinels',  familyColor:'#4298b4'},
    ESTJ:{name:'Executive',   family:'Sentinels',  familyColor:'#4298b4'},
    ESFJ:{name:'Consul',      family:'Sentinels',  familyColor:'#4298b4'},
    INTJ:{name:'Architect',   family:'Analysts',   familyColor:'#88619a'},
    INTP:{name:'Logician',    family:'Analysts',   familyColor:'#88619a'},
    ENTJ:{name:'Commander',   family:'Analysts',   familyColor:'#88619a'},
    ENTP:{name:'Debater',     family:'Analysts',   familyColor:'#88619a'},
    INFJ:{name:'Advocate',    family:'Diplomats',  familyColor:'#33a474'},
    INFP:{name:'Mediator',    family:'Diplomats',  familyColor:'#33a474'},
    ENFJ:{name:'Protagonist', family:'Diplomats',  familyColor:'#33a474'},
    ENFP:{name:'Campaigner',  family:'Diplomats',  familyColor:'#33a474'},
    ISTP:{name:'Virtuoso',    family:'Explorers',  familyColor:'#e4ae3a'},
    ISFP:{name:'Adventurer',  family:'Explorers',  familyColor:'#e4ae3a'},
    ESTP:{name:'Entrepreneur',family:'Explorers',  familyColor:'#e4ae3a'},
    ESFP:{name:'Entertainer', family:'Explorers',  familyColor:'#e4ae3a'}
  };

  const AVATAR_FILES = {
    ISTJ:'Logistician.png',  ISFJ:'Defender.png',     ESTJ:'Executive.png',    ESFJ:'Consul.png',
    INTJ:'Architect.png',    INTP:'Logician.png',      ENTJ:'Commander.png',    ENTP:'Debater.png',
    INFJ:'Advocate.png',     INFP:'Mediatorv3.png',    ENFJ:'Protagonist.png',  ENFP:'Campaigner.png',
    ISTP:'Virtuoso.png',     ISFP:'Adventurer.png',    ESTP:'Entrepreneur.png', ESFP:'Entertainer.png'
  };

  const FAMILY_DESCRIPTIONS = {
    Analysts: 'Intuitive and Thinking personality types, known for their rationality, impartiality, and intellectual excellence.',
    Diplomats:'Intuitive and Feeling personality types, known for their empathy, diplomatic skills, and passionate idealism.',
    Sentinels:'Observant and Judging personality types, known for their practicality and focus on order, security, and stability.',
    Explorers:'Observant and Prospecting personality types, known for their spontaneity, ingenuity, and flexibility.'
  };

  const DESCRIPTIONS = {
    ISTJ:'Practical and fact-minded individuals, whose reliability cannot be doubted.',
    ISFJ:'Very dedicated and warm protectors, always ready to defend their loved ones.',
    INFJ:'Quiet and mystical, yet very inspiring and tireless idealists.',
    INTJ:'Imaginative and strategic thinkers, with a plan for everything.',
    ISTP:'Bold and practical experimenters, masters of all kinds of tools.',
    ISFP:'Flexible and charming artists, always ready to explore and experience something new.',
    INFP:'Poetic, kind and altruistic people, always eager to help a good cause.',
    INTP:'Innovative inventors with an unquenchable thirst for knowledge.',
    ESTP:'Smart, energetic and very perceptive people, who truly enjoy living on the edge.',
    ESFP:'Spontaneous, energetic and enthusiastic people – life is never boring around them.',
    ENFP:'Enthusiastic, creative and sociable free spirits, who can always find a reason to smile.',
    ENTP:'Smart and curious thinkers who cannot resist an intellectual challenge.',
    ESTJ:'Excellent administrators, unsurpassed at managing things – or people.',
    ESFJ:'Extraordinarily caring, social and popular people, always eager to help.',
    ENFJ:'Charismatic and inspiring leaders, able to mesmerize their listeners.',
    ENTJ:'Bold, imaginative and strong-willed leaders, always finding a way – or making one.'
  };

  const FAMILY_STEVE = {
    Analysts: "The Analysts are the big thinkers — the Architect, the Logician, the Commander, and the Debater. If you love solving puzzles, asking \"why?\", and coming up with smart strategies, you might be one of these. They're known for their brainpower, their logic, and their ability to see things others miss.",
    Diplomats:"The Diplomats are the heart of any group — the Advocate, the Mediator, the Protagonist, and the Campaigner. These are the people who really care about others, want to make the world a better place, and have incredible imagination. If you're the kind of person your friends come to when they need someone to listen, this might be your family.",
    Sentinels:"The Sentinels are the ones who keep everything running smoothly — the Logistician, the Defender, the Executive, and the Consul. They're practical, reliable, and get things done. If you're the kind of person who keeps their promises, makes plans, and is always there when people need you, you could be a Sentinel.",
    Explorers:"The Explorers are the adventurers — the Virtuoso, the Adventurer, the Entrepreneur, and the Entertainer. They love trying new things, living in the moment, and bringing energy wherever they go. If you're hands-on, spontaneous, and always up for something exciting, this could be your crew."
  };

  const FAMILY_COLORS = {Analysts:'#88619a', Diplomats:'#33a474', Sentinels:'#4298b4', Explorers:'#e4ae3a'};

  /* ================================================================
     HELPERS
     ================================================================ */
  function el(tag, cls, txt) {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (txt !== undefined) n.textContent = txt;
    return n;
  }

  function showLoading(msg) {
    let ov = document.querySelector('.rag-loading-overlay');
    if (ov) {
      const t = ov.querySelector('.rag-loading-text');
      if (t) t.textContent = msg || 'Loading...';
      return;
    }
    ov = el('div', 'rag-loading-overlay');
    ov.appendChild(el('div', 'rag-spinner'));
    ov.appendChild(el('div', 'rag-loading-text', msg || 'Loading...'));
    document.body.appendChild(ov);
  }

  function hideLoading() {
    const ov = document.querySelector('.rag-loading-overlay');
    if (ov) ov.remove();
  }

  function updateLoadingText(msg) {
    const ov = document.querySelector('.rag-loading-overlay');
    if (ov) {
      const t = ov.querySelector('.rag-loading-text');
      if (t) t.textContent = msg;
    }
  }

  async function apiFetch(url, options) {
    const res = await fetch(url, Object.assign({
      credentials: 'same-origin',
      headers: {'X-WP-Nonce': cfg.nonce || '', 'Accept': 'application/json'}
    }, options));
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return res.json();
  }

  /* ================================================================
     INIT
     ================================================================ */
  async function init() {
    showLoading('Loading Who Am I...');
    try {
      await renderIntro();
    } catch (e) {
      console.error('Init error:', e);
      hideLoading();
      root.innerHTML = '<div class="rag-wrap"><div class="rag-card"><p class="rag-error-msg">Failed to load. Please refresh the page.</p></div></div>';
    }
  }

  init();

  /* ================================================================
     STATUS CHECK
     ================================================================ */
  async function checkStatus() {
    try {
      let url = cfg.restUrlStatus + '?week=' + encodeURIComponent(week);
      if (isParentView) url += '&student_id=' + encodeURIComponent(cfg.studentId);
      return await apiFetch(url);
    } catch (e) {
      console.error('Status check error:', e);
      return {status: 'not_started'};
    }
  }

  /* ================================================================
     SCREEN 1 — Intro / Steve's opening message
     ================================================================ */
  async function renderIntro() {
    const status = await checkStatus();

    if (status.status === 'completed') {
      await renderSummary();
      return;
    }

    // Parent view — student hasn't finished yet; show a simple placeholder.
    if (isParentView) {
      hideLoading();
      const wrap = el('div', 'rag-wrap');
      const card = el('div', 'rag-card');
      card.appendChild(el('h2', 'rag-title', 'Who Am I — Results'));
      card.appendChild(el('p', 'rag-msg', "This student hasn't completed the Who Am I quiz yet."));
      wrap.appendChild(card);
      root.replaceChildren(wrap);
      return;
    }

    if (status.status === 'in_progress') {
      await loadQuestions();
      await resumeFromLastQuestion(status.last_question_id, status.answered_question_ids || []);
      return;
    }

    let introData = {intro_message: 'Welcome to Who Am I!', total_questions: null};
    try {
      introData = await apiFetch(cfg.restUrlIntro + '?week=' + encodeURIComponent(week));
    } catch (e) {
      console.error('Intro fetch error:', e);
    }

    const wrap = el('div', 'rag-wrap');
    const card = el('div', 'rag-card');

    card.appendChild(el('h2', 'rag-title', 'Week ' + week + ' — Who Am I'));

    if (introData.intro_message) {
      const ib = el('div', 'rag-ai-intro');
      ib.textContent = introData.intro_message;
      card.appendChild(ib);
    }

    if (introData.total_questions) {
      const info = el('div', 'rag-sub');
      info.style.margin = '12px 0';
      info.textContent = "You'll be answering " + introData.total_questions + " questions to discover which of 16 personality types you are.";
      card.appendChild(info);
    }

    const btn = el('button', 'rag-btn', 'Next');
    btn.onclick = function () { renderFamilies(); };
    card.appendChild(btn);

    wrap.appendChild(card);
    root.replaceChildren(wrap);
    hideLoading();
  }

  /* ================================================================
     SCREEN 2 — Families overview
     ================================================================ */
  function renderFamilies() {
    const wrap = el('div', 'rag-wrap');
    const card = el('div', 'rag-card');

    card.appendChild(el('h2', 'rag-title', 'Meet the 16 Personality Types'));

    if (cfg.avatarImageUrl) {
      const ac  = el('div', '');
      ac.style.cssText = 'margin:16px 0;text-align:center;';
      const img = document.createElement('img');
      img.src   = cfg.avatarImageUrl;
      img.alt   = 'The 16 Personality Types';
      img.style.cssText = 'max-width:100%;height:auto;border-radius:12px;';
      ac.appendChild(img);
      card.appendChild(ac);
    }

    const steveBox = el('div', 'rag-ai-intro');

    const intro = el('div', '');
    intro.style.marginBottom = '14px';
    intro.textContent = "Steve says: Right, here's how it works! Every person fits into one of 4 personality families, and each family has 4 different personality types — that's 16 in total. Have a look at the characters above and see if any of them remind you of yourself. Here's what each family is all about:";
    steveBox.appendChild(intro);

    ['Analysts', 'Diplomats', 'Sentinels', 'Explorers'].forEach(function (family) {
      const block = el('div', 'ptest-family-explain');
      block.style.cssText = 'margin:12px 0;padding:12px 14px;border-radius:8px;border-left:4px solid ' + FAMILY_COLORS[family] + ';';
      const fn = el('strong', '');
      fn.style.cssText = 'font-size:14px;color:' + FAMILY_COLORS[family] + ';display:block;margin-bottom:4px;';
      fn.textContent = family;
      block.appendChild(fn);
      const fd = el('span', '');
      fd.textContent = FAMILY_STEVE[family];
      block.appendChild(fd);
      steveBox.appendChild(block);
    });

    const close = el('div', '');
    close.style.cssText = 'margin-top:14px;font-weight:500;';
    close.textContent = "So which family do YOU belong to? Let's find out!";
    steveBox.appendChild(close);
    card.appendChild(steveBox);

    const btn = el('button', 'rag-btn', 'Start Test');
    btn.onclick = async function () {
      showLoading('Loading your questions...');
      try {
        await loadQuestions();
        idx = 0;
        await renderQuestion();
      } catch (e) {
        console.error('Load questions error:', e);
        hideLoading();
      }
    };
    card.appendChild(btn);

    wrap.appendChild(card);
    root.replaceChildren(wrap);
  }

  /* ================================================================
     LOAD QUESTIONS + RESUME
     ================================================================ */
  async function loadQuestions() {
    const data = await apiFetch(cfg.restUrlQuestions + '?week=' + encodeURIComponent(week));
    if (!data || !data.ok) throw new Error(data && data.error ? data.error : 'Failed to load questions');
    questions = data.questions || [];
  }

  async function resumeFromLastQuestion(lastQId, answeredIds) {
    let first = -1;
    for (let i = 0; i < questions.length; i++) {
      if (!answeredIds.includes(parseInt(questions[i].id))) {
        first = i;
        break;
      }
    }
    if (first >= 0) {
      idx = first;
      await renderQuestion();
    } else {
      await renderSummary();
    }
  }

  /* ================================================================
     QUESTION SCREEN
     ================================================================ */
  async function renderQuestion() {
    showLoading('Loading question...');

    const q      = questions[idx];
    const totalQ = questions.length;
    const pct    = Math.round((idx / totalQ) * 100);

    const wrap = el('div', 'rag-wrap');
    const card = el('div', 'rag-card');

    // Progress bar
    const pc = el('div', 'ptest-progress-container');
    pc.style.cssText = 'margin:16px 0 20px;';

    const pl = el('div', '');
    pl.style.cssText = 'margin-bottom:8px;text-align:center;font-family:var(--font-heading);font-size:12px;text-transform:uppercase;letter-spacing:0.04em;';
    pl.textContent = 'Progress: ' + idx + ' of ' + totalQ + ' questions completed';
    pc.appendChild(pl);

    const outer = el('div', '');
    outer.style.cssText = 'width:100%;height:24px;border-radius:12px;overflow:hidden;position:relative;background:rgba(0,212,255,0.08);';

    const inner = el('div', '');
    inner.style.cssText = 'height:100%;background:linear-gradient(90deg,#00D4FF,#33DDFF);box-shadow:0 0 8px rgba(0,212,255,0.4);border-radius:12px;width:' + pct + '%;transition:width 0.3s ease;';

    const pt = el('div', '');
    pt.style.cssText = 'position:absolute;top:0;left:0;right:0;bottom:0;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#0A0E1A;font-family:var(--font-heading);letter-spacing:0.04em;';
    pt.textContent = pct + '%';

    outer.appendChild(inner);
    outer.appendChild(pt);
    pc.appendChild(outer);
    card.appendChild(pc);

    card.appendChild(el('h2', 'rag-title', 'Question ' + (idx + 1) + ' of ' + totalQ));
    card.appendChild(el('div', 'rag-qtext', q.q_text));

    // AI guidance
    try {
      const gd = await apiFetch(cfg.restUrlGuidance, {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || ''},
        body: JSON.stringify({question_text: q.q_text, q_type: q.q_type})
      });
      if (gd && gd.guidance) {
        const gb = el('div', 'rag-ai-question');
        gb.textContent = gd.guidance;
        card.appendChild(gb);
      }
    } catch (e) {
      // guidance is optional — silently skip
    }

    if (q.q_type === 'MBTI') {
      renderMBTIEitherOr(card, q);
    } else if (q.q_type === 'DISC') {
      renderDISCOptions(card, q);
    }

    // Chatbot — MOVE (don't clone) to preserve event listeners
    if (chatSource) {
      const cw = el('div', 'rag-chatwrap');

      chatSource.style.display = 'block';
      chatSource.id = 'mfsd-ptest-chat-' + idx;
      chatSource.removeAttribute('data-conversation-id');

      // Stamp the current question into data-context on the stevegpt container so SteveGPT
      // includes it in the system prompt. Must target .stevegpt-chatbot-container (not the
      // outer wrapper) and use setAttribute so jQuery's attr() picks it up on each send.
      var chatbotEl = chatSource.querySelector('.stevegpt-chatbot-container');
      if (chatbotEl) {
        if (!chatbotEl.dataset.baseContext) {
          chatbotEl.dataset.baseContext = chatbotEl.getAttribute('data-context') || '';
        }
        var baseCtx = chatbotEl.dataset.baseContext;
        var questionCtx = 'The student is currently working on this personality question: "' + q.q_text + '"';
        chatbotEl.setAttribute('data-context', baseCtx ? baseCtx + ' ' + questionCtx : questionCtx);
      }

      // Clear direct-child messages only — :scope > avoids gutting the typing indicator
      const msgWrap = chatSource.querySelector('.stevegpt-chat-messages');
      if (msgWrap) {
        const msgs = msgWrap.querySelectorAll(':scope > .stevegpt-message');
        msgs.forEach(function (m, i) { if (i > 0) m.remove(); });
        const greeting = msgWrap.querySelector('.stevegpt-message-text');
        if (greeting) greeting.textContent = "Need a hand with this question? Just ask me and I'll help you out!";
      }

      cw.appendChild(chatSource);
      card.appendChild(cw);

      // Re-focus input so Enter key works immediately after the chatbot is moved
      setTimeout(function () {
        var inp = chatSource.querySelector('.stevegpt-input-field');
        if (inp) inp.focus();
      }, 150);
    }

    wrap.appendChild(card);
    root.replaceChildren(wrap);
    hideLoading();
  }

  /* ── MBTI Either/Or buttons ── */
  function renderMBTIEitherOr(card, q) {
    const container = el('div', 'ptest-either-or');

    const btnA = el('button', 'ptest-either-btn');
    btnA.textContent = q.mbti_option_a_text || 'Option A';
    btnA.onclick = function () { handleMBTIAnswer(q, 'A', q.mbti_letter); };
    container.appendChild(btnA);

    const orDiv = el('div', 'ptest-either-or-divider');
    orDiv.textContent = 'or';
    container.appendChild(orDiv);

    const btnB = el('button', 'ptest-either-btn');
    btnB.textContent = q.mbti_option_b_text || 'Option B';
    btnB.onclick = function () { handleMBTIAnswer(q, 'B', q.mbti_letter_b); };
    container.appendChild(btnB);

    card.appendChild(container);
  }

  /* ── DISC 5-point scale ── */
  function renderDISCOptions(card, q) {
    const sc = el('div', 'disc-scale-container');
    const sl = el('div', 'disc-scale-label');
    sl.textContent = 'How much do you agree with this statement?';
    sc.appendChild(sl);

    const lights = el('div', 'rag-lights');
    lights.style.cssText = 'display:flex;gap:8px;justify-content:center;flex-wrap:wrap;';

    var opts = [
      {label:'Completely Disagree', value:1, color:'#d9534f', emoji:'👎'},
      {label:'Somewhat Disagree',   value:2, color:'#f0ad4e', emoji:'🤔'},
      {label:'Neutral',             value:3, color:'#555566', emoji:'😐'},
      {label:'Somewhat Agree',      value:4, color:'#5cb85c', emoji:'👍'},
      {label:'Completely Agree',    value:5, color:'#4caf50', emoji:'💯'}
    ];

    opts.forEach(function (opt) {
      const btn = el('button', 'rag-light disc-scale-btn');
      btn.style.cssText = 'background:' + opt.color + ';color:white;border:none;border-radius:10px;padding:16px 12px;cursor:pointer;font-weight:600;font-size:13px;min-width:90px;white-space:pre-line;text-align:center;display:flex;flex-direction:column;align-items:center;gap:6px;transition:all 0.2s;';
      const em = el('span', '');
      em.style.fontSize = '24px';
      em.textContent = opt.emoji;
      btn.appendChild(em);
      const lb = el('span', '');
      lb.style.cssText = 'font-size:12px;line-height:1.3;';
      lb.textContent = opt.label;
      btn.appendChild(lb);
      btn.onmouseover = function () { btn.style.transform = 'translateY(-3px)'; btn.style.boxShadow = '0 4px 12px rgba(0,0,0,0.3)'; };
      btn.onmouseout  = function () { btn.style.transform = 'translateY(0)'; btn.style.boxShadow = 'none'; };
      btn.onclick = function () { handleDISCAnswer(q, opt.value); };
      lights.appendChild(btn);
    });

    sc.appendChild(lights);
    card.appendChild(sc);
  }

  /* ================================================================
     ANSWER HANDLERS
     ================================================================ */
  async function handleMBTIAnswer(question, choice, chosenLetter) {
    showLoading('Saving your answer...');
    try {
      const data = await apiFetch(cfg.restUrlAnswer, {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || ''},
        body: JSON.stringify({
          week:        week,
          question_id: question.id,
          q_type:      'MBTI',
          answer:      choice,
          letter:      chosenLetter,
          axis:        question.mbti_axis
        })
      });
      if (!data || !data.ok) throw new Error(data && data.error ? data.error : 'Save failed');
      idx++;
      if (idx < questions.length) {
        updateLoadingText('Loading next question...');
        await renderQuestion();
      } else {
        updateLoadingText('Generating your results...');
        await renderSummary();
      }
    } catch (e) {
      console.error('MBTI answer error:', e);
      hideLoading();
      alert('Error saving answer: ' + e.message);
    }
  }

  async function handleDISCAnswer(question, answerValue) {
    showLoading('Saving your answer...');
    try {
      const mapping      = question.disc_mapping;
      const contribution = answerValue - 3;
      const data = await apiFetch(cfg.restUrlAnswer, {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || ''},
        body: JSON.stringify({
          week:           week,
          question_id:    question.id,
          q_type:         'DISC',
          answer:         answerValue,
          d_contribution: mapping.D * contribution,
          i_contribution: mapping.I * contribution,
          s_contribution: mapping.S * contribution,
          c_contribution: mapping.C * contribution
        })
      });
      if (!data || !data.ok) throw new Error(data && data.error ? data.error : 'Save failed');
      idx++;
      if (idx < questions.length) {
        updateLoadingText('Loading next question...');
        await renderQuestion();
      } else {
        updateLoadingText('Generating your results...');
        await renderSummary();
      }
    } catch (e) {
      console.error('DISC answer error:', e);
      hideLoading();
      alert('Error saving answer: ' + e.message);
    }
  }

  /* ================================================================
     RESULTS SCREEN 1 — Personality reveal
     ================================================================ */
  async function renderSummary() {
    showLoading(isParentView ? 'Loading results...' : 'Generating your results...');
    try {
      const summaryBody = {week: week};
      if (isParentView) summaryBody.student_id = cfg.studentId;
      const sd = await apiFetch(cfg.restUrlSummary, {
        method:  'POST',
        headers: {'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || ''},
        body:    JSON.stringify(summaryBody)
      });

      if (!sd || !sd.ok) {
        hideLoading();
        alert('Summary failed: ' + (sd && sd.error ? sd.error : 'Unknown error'));
        return;
      }

      summaryCache = sd;
      hideLoading();

      // For first-time students PHP had no stored summary at render time, so stamp it now.
      // render_prompt() template tokens: {personality_type}, {disc_style}, {ai_summary}
      if (summarySource && sd.ai_summary) {
        var sumBotEl = summarySource.querySelector('.stevegpt-chatbot-container');
        if (sumBotEl && !sumBotEl.getAttribute('data-context')) {
          var p = (sd.mbti_type && typeof PROFILES !== 'undefined' && PROFILES[sd.mbti_type]) ? PROFILES[sd.mbti_type] : null;
          var personalityType = p ? 'The ' + p.name + ' (' + p.family + ' family)' : (sd.mbti_type || '');
          var discStyle = sd.disc_primary || '';
          sumBotEl.setAttribute('data-context',
            'Student\'s Who Am I Results:\n\nPersonality type: ' + personalityType +
            '\nCommunication style: ' + discStyle +
            '\n\n=== WHO AM I SUMMARY ===\n' + sd.ai_summary
          );
        }
      }

      const wrap = el('div', 'rag-wrap');
      const card = el('div', 'rag-card');
      card.appendChild(el('h2', 'rag-title', 'Who Am I (Part 1) — Your Results'));

      if (sd.mbti_type) {
        var p    = PROFILES[sd.mbti_type] || {name:'Unique', family:'Individual', familyColor:'#666'};
        var desc = DESCRIPTIONS[sd.mbti_type] || 'A unique personality!';

        const hs = el('div', 'ptest-result-header');
        hs.style.cssText = 'margin:20px 0;padding:24px;border-radius:12px;text-align:center;background:linear-gradient(135deg,' + p.familyColor + '22,' + p.familyColor + '11);border:2px solid ' + p.familyColor + ';';

        const fl = el('div', '');
        fl.style.cssText = 'font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:' + p.familyColor + ';margin-bottom:12px;font-family:var(--font-heading);';
        fl.textContent = 'You are part of the ' + p.family + ' family';
        hs.appendChild(fl);

        var avatarFile = AVATAR_FILES[sd.mbti_type];
        if (cfg.avatarsBaseUrl && avatarFile) {
          var avatarWrap = document.createElement('div');
          avatarWrap.style.cssText = 'width:160px;height:160px;margin:0 auto 16px;border-radius:50%;background:' + p.familyColor + ';display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,0.3);overflow:hidden;';
          var avatarImg = document.createElement('img');
          avatarImg.src = cfg.avatarsBaseUrl + avatarFile;
          avatarImg.alt = 'The ' + p.name;
          avatarImg.style.cssText = 'width:140px;height:140px;object-fit:contain;';
          avatarImg.onerror = function () { avatarWrap.style.display = 'none'; };
          avatarWrap.appendChild(avatarImg);
          hs.appendChild(avatarWrap);
        }

        const nl = el('div', '');
        nl.style.cssText = 'font-size:28px;font-weight:700;margin-bottom:8px;font-family:var(--font-heading);';
        nl.textContent = 'You are The ' + p.name;
        hs.appendChild(nl);

        const dl = el('div', '');
        dl.style.cssText = 'font-size:15px;line-height:1.5;max-width:600px;margin:0 auto;font-family:var(--font-body);';
        dl.textContent = desc;
        hs.appendChild(dl);

        const fd = el('div', '');
        fd.style.cssText = 'font-size:13px;margin-top:12px;line-height:1.5;font-family:var(--font-body);';
        fd.textContent = FAMILY_DESCRIPTIONS[p.family] || '';
        hs.appendChild(fd);

        card.appendChild(hs);
      }

      const btn = el('button', 'rag-btn', 'View My Summary');
      btn.style.cssText = 'display:block;margin:20px auto 0;';
      btn.onclick = function () { renderSummaryDetail(); };
      card.appendChild(btn);

      wrap.appendChild(card);
      root.replaceChildren(wrap);

    } catch (e) {
      console.error('Summary error:', e);
      hideLoading();
      alert('Failed to load summary: ' + e.message);
    }
  }

  /* ================================================================
     RESULTS SCREEN 2 — Tabbed Steve summary + DISC
     ================================================================ */
  function renderSummaryDetail() {
    var sd = summaryCache;
    if (!sd) return;

    const wrap = el('div', 'rag-wrap');
    const card = el('div', 'rag-card');

    // Mini header
    if (sd.mbti_type) {
      var p2 = PROFILES[sd.mbti_type] || {name:'Unique', family:'Individual', familyColor:'#666'};
      const mini = el('div', '');
      mini.style.cssText = 'display:flex;align-items:center;gap:12px;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid rgba(0,212,255,0.15);';

      var af = AVATAR_FILES[sd.mbti_type];
      if (cfg.avatarsBaseUrl && af) {
        var aw = document.createElement('div');
        aw.style.cssText = 'width:48px;height:48px;border-radius:50%;background:' + p2.familyColor + ';display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;';
        var ai = document.createElement('img');
        ai.src = cfg.avatarsBaseUrl + af;
        ai.alt = p2.name;
        ai.style.cssText = 'width:40px;height:40px;object-fit:contain;';
        ai.onerror = function () { aw.style.display = 'none'; };
        aw.appendChild(ai);
        mini.appendChild(aw);
      }

      const miniText = el('div', '');
      const mt1 = el('div', '');
      mt1.style.cssText = 'font-size:18px;font-weight:700;font-family:var(--font-heading);';
      mt1.textContent = 'The ' + p2.name;
      miniText.appendChild(mt1);
      const mt2 = el('div', '');
      mt2.style.cssText = 'font-size:13px;color:' + p2.familyColor + ';font-weight:600;font-family:var(--font-heading);text-transform:uppercase;letter-spacing:0.04em;';
      mt2.textContent = p2.family + ' family';
      miniText.appendChild(mt2);
      mini.appendChild(miniText);
      card.appendChild(mini);
    }

    card.appendChild(el('h2', 'rag-title', 'What Steve Says About You'));

    // Tabbed AI summary
    if (sd.ai_summary) {
      var sections = parseSummarySections(sd.ai_summary);
      if (sections.length > 1) {
        const tc    = el('div', 'ptest-results-tabs');
        tc.style.margin = '16px 0';
        const tnav  = el('div', 'ptest-tab-nav');
        const tcont = el('div', 'ptest-tab-content-area');
        tcont.style.padding = '20px 0';

        sections.forEach(function (sec, i) {
          const tb = el('button', 'ptest-result-tab' + (i === 0 ? ' active' : ''));
          tb.textContent = sec.title;
          tb.dataset.tabIndex = i;
          tb.onclick = function () {
            tnav.querySelectorAll('.ptest-result-tab').forEach(function (b) { b.classList.remove('active'); });
            tcont.querySelectorAll('.ptest-tab-panel').forEach(function (p) { p.style.display = 'none'; });
            tb.classList.add('active');
            tcont.querySelector('[data-panel="' + i + '"]').style.display = 'block';
          };
          tnav.appendChild(tb);

          const panel = el('div', 'ptest-tab-panel');
          panel.dataset.panel = i;
          panel.style.display = (i === 0) ? 'block' : 'none';
          const pc2 = el('div', 'rag-ai');
          pc2.style.cssText = 'white-space:pre-line;line-height:1.7;';
          pc2.textContent = sec.content;
          panel.appendChild(pc2);
          tcont.appendChild(panel);
        });

        tc.appendChild(tnav);
        tc.appendChild(tcont);
        card.appendChild(tc);
      } else {
        const sb = el('div', 'rag-ai');
        sb.textContent = sd.ai_summary;
        card.appendChild(sb);
      }
    }

    // DISC section
    var hasDisc = sd.disc_scores && (sd.disc_scores.D > 0 || sd.disc_scores.I > 0 || sd.disc_scores.S > 0 || sd.disc_scores.C > 0);
    if (hasDisc) {
      const ds = el('div', 'ptest-disc-section');
      ds.style.cssText = 'margin:20px 0;padding:20px;';

      const dt = el('h3', '');
      dt.style.cssText = 'margin:0 0 16px;font-size:20px;text-align:center;';
      dt.textContent = 'Your Communication Style';
      ds.appendChild(dt);

      const plotC  = el('div', 'disc-plot-container');
      var canvas   = createDISCPolarPlot(sd.disc_scores);
      if (canvas) plotC.appendChild(canvas);
      ds.appendChild(plotC);

      const bd = el('div', 'disc-breakdown');
      ['D', 'I', 'S', 'C'].forEach(function (letter) {
        var score = sd.disc_scores[letter];
        const bar = el('div', 'disc-bar');
        const l2  = el('div', ''); l2.textContent = letter;           bar.appendChild(l2);
        const s2  = el('div', ''); s2.textContent = Math.round(score) + '%'; bar.appendChild(s2);
        const n2  = el('div', ''); n2.textContent = getDISCName(letter); bar.appendChild(n2);
        bd.appendChild(bar);
      });
      ds.appendChild(bd);
      card.appendChild(ds);
    }

    // Summary chatbot — dedicated widget, separate from the question chatbot
    if (summarySource) {
      const cp  = el('div', 'rag-ai-question');
      const cpt = el('p', '');
      cpt.style.cssText = 'margin:0;font-weight:600;';
      cpt.textContent = 'Have questions about your results? Ask SteveGPT below!';
      cp.appendChild(cpt);
      card.appendChild(cp);

      const cw = el('div', 'rag-chatwrap');
      summarySource.style.display = 'block';
      summarySource.id = 'mfsd-ptest-chat-summary';

      const sumMsgWrap = summarySource.querySelector('.stevegpt-chat-messages');
      if (sumMsgWrap) {
        const sumMsgs = sumMsgWrap.querySelectorAll(':scope > .stevegpt-message');
        const firstMsg = sumMsgs[0] || null;
        sumMsgs.forEach(function (m) { if (m !== firstMsg) m.remove(); });
      }

      cw.appendChild(summarySource);
      card.appendChild(cw);

      setTimeout(function () {
        var inp = document.querySelector('#mfsd-ptest-chat-summary .stevegpt-input-field');
        if (inp) inp.focus();
      }, 500);
    }

    // Navigation buttons
    const navWrap   = el('div', 'ptest-nav-actions');
    const badgesBtn = document.createElement('a');
    badgesBtn.className   = 'rag-btn secondary';
    badgesBtn.href        = cfg.urlBadges || 'https://mfsd.me/badges/';
    badgesBtn.textContent = 'View My Badges';
    const courseBtn = document.createElement('a');
    courseBtn.className   = 'rag-btn';
    courseBtn.href        = cfg.urlCourse || 'https://mfsd.me/about/parent-portal-home/?course_id=1';
    courseBtn.textContent = 'Return to Course';
    navWrap.appendChild(badgesBtn);
    navWrap.appendChild(courseBtn);
    card.appendChild(navWrap);

    wrap.appendChild(card);
    root.replaceChildren(wrap);
  }

  /* ================================================================
     UTILITIES
     ================================================================ */
  function parseSummarySections(text) {
    var regex = /\[SECTION:([^\]]+)\]/g;
    var parts = [];
    var lastIdx = 0;
    var prevTitle = null;
    var match;
    while ((match = regex.exec(text)) !== null) {
      if (prevTitle !== null) {
        var c = text.substring(lastIdx, match.index).trim();
        if (c) parts.push({title: prevTitle, content: c});
      }
      prevTitle = match[1].trim();
      lastIdx   = match.index + match[0].length;
    }
    if (prevTitle !== null) {
      var c2 = text.substring(lastIdx).trim();
      if (c2) parts.push({title: prevTitle, content: c2});
    }
    return parts.length === 0 ? [{title: 'Your Results', content: text}] : parts;
  }

  function createDISCPolarPlot(scores) {
    var canvas = document.createElement('canvas');
    canvas.width = 400; canvas.height = 400; canvas.id = 'disc-polar-plot';
    var ctx = canvas.getContext('2d');
    var cx = 200, cy = 200, mr = 140;

    var segs = [
      {key:'D', label:'Dominance',       sa:-Math.PI/2,      ea:0},
      {key:'I', label:'Influence',        sa:0,               ea:Math.PI/2},
      {key:'S', label:'Steadiness',       sa:Math.PI/2,       ea:Math.PI},
      {key:'C', label:'Conscientiousness',sa:Math.PI,         ea:3*Math.PI/2}
    ];
    var cols = {D:'#2d5f8d', I:'#f9b234', S:'#c67a3c', C:'#3b5998'};

    // Grid rings
    ctx.strokeStyle = 'rgba(0,212,255,0.2)';
    ctx.lineWidth   = 1;
    for (var i = 1; i <= 4; i++) {
      ctx.beginPath();
      ctx.arc(cx, cy, (mr / 4) * i, 0, 2 * Math.PI);
      ctx.stroke();
    }

    // Axis lines
    ctx.strokeStyle = 'rgba(0,212,255,0.15)';
    ctx.lineWidth   = 2;
    ctx.setLineDash([5, 5]);
    ctx.beginPath(); ctx.moveTo(cx, cy - mr); ctx.lineTo(cx, cy + mr); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(cx - mr, cy); ctx.lineTo(cx + mr, cy); ctx.stroke();
    ctx.setLineDash([]);

    // Segments
    segs.forEach(function (s) {
      var pct = scores[s.key] || 0;
      var fr  = (pct / 100) * mr;
      ctx.fillStyle   = cols[s.key];
      ctx.globalAlpha = 0.65;
      ctx.beginPath(); ctx.moveTo(cx, cy); ctx.arc(cx, cy, fr, s.sa, s.ea); ctx.closePath(); ctx.fill();
      ctx.globalAlpha = 1;
      ctx.strokeStyle = cols[s.key];
      ctx.lineWidth   = 2;
      ctx.beginPath(); ctx.moveTo(cx, cy); ctx.arc(cx, cy, mr, s.sa, s.ea); ctx.closePath(); ctx.stroke();
    });

    // Axis labels
    ctx.font      = 'bold 14px Arial';
    ctx.fillStyle = '#E8EAF0';
    ctx.textAlign = 'center';
    ctx.fillText('Active',     cx,          cy - mr - 15);
    ctx.fillText('Reflective', cx,          cy + mr + 25);
    ctx.save(); ctx.translate(cx - mr - 25, cy); ctx.rotate(-Math.PI / 2); ctx.fillText('People Focus', 0, 0); ctx.restore();
    ctx.save(); ctx.translate(cx + mr + 25, cy); ctx.rotate( Math.PI / 2); ctx.fillText('Task Focus',   0, 0); ctx.restore();

    // Segment labels
    segs.forEach(function (s) {
      var pct = scores[s.key] || 0;
      var ma  = (s.sa + s.ea) / 2;
      ctx.font      = 'bold 48px Arial';
      ctx.fillStyle = '#E8EAF0';
      ctx.textAlign = 'center';
      ctx.fillText(s.key, cx + mr * 0.4 * Math.cos(ma), (cy + mr * 0.4 * Math.sin(ma)) + 15);
      ctx.font      = 'bold 16px Arial';
      ctx.fillStyle = '#E8EAF0';
      ctx.fillText(s.label, cx + (mr + 50) * Math.cos(ma), cy + (mr + 50) * Math.sin(ma));
      ctx.fillStyle = cols[s.key];
      ctx.fillText(Math.round(pct) + '%', cx + mr * 0.65 * Math.cos(ma), (cy + mr * 0.65 * Math.sin(ma)) + 5);
    });

    return canvas;
  }

  function getDISCName(l) {
    return {D:'Dominance', I:'Influence', S:'Steadiness', C:'Conscientiousness'}[l] || l;
  }

})();