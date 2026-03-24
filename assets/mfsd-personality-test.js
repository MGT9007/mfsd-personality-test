(function () {
  console.log('MFSD_PTEST_CFG', window.MFSD_PTEST_CFG);
  const cfg = window.MFSD_PTEST_CFG || {};
  const root = document.getElementById("mfsd-ptest-root");
  if (!root) return;

  const chatSource = document.getElementById("mfsd-ptest-chat-source");

  let week = cfg.week || 1;
  console.log('Initial week from config:', week);
  
  let questions = [];
  let idx = 0;

  /* ================================================================
     Personality profiles lookup (student-facing — NO MBTI codes)
     ================================================================ */
  const PROFILES = {
    'ISTJ': { name: 'Logistician', family: 'Sentinels', familyColor: '#4298b4' },
    'ISFJ': { name: 'Defender',    family: 'Sentinels', familyColor: '#4298b4' },
    'ESTJ': { name: 'Executive',   family: 'Sentinels', familyColor: '#4298b4' },
    'ESFJ': { name: 'Consul',      family: 'Sentinels', familyColor: '#4298b4' },
    'INTJ': { name: 'Architect',   family: 'Analysts',  familyColor: '#88619a' },
    'INTP': { name: 'Logician',    family: 'Analysts',  familyColor: '#88619a' },
    'ENTJ': { name: 'Commander',   family: 'Analysts',  familyColor: '#88619a' },
    'ENTP': { name: 'Debater',     family: 'Analysts',  familyColor: '#88619a' },
    'INFJ': { name: 'Advocate',    family: 'Diplomats', familyColor: '#33a474' },
    'INFP': { name: 'Mediator',    family: 'Diplomats', familyColor: '#33a474' },
    'ENFJ': { name: 'Protagonist', family: 'Diplomats', familyColor: '#33a474' },
    'ENFP': { name: 'Campaigner',  family: 'Diplomats', familyColor: '#33a474' },
    'ISTP': { name: 'Virtuoso',    family: 'Explorers', familyColor: '#e4ae3a' },
    'ISFP': { name: 'Adventurer',  family: 'Explorers', familyColor: '#e4ae3a' },
    'ESTP': { name: 'Entrepreneur',family: 'Explorers', familyColor: '#e4ae3a' },
    'ESFP': { name: 'Entertainer', family: 'Explorers', familyColor: '#e4ae3a' }
  };

  const FAMILY_DESCRIPTIONS = {
    'Analysts':  'Intuitive and Thinking personality types, known for their rationality, impartiality, and intellectual excellence.',
    'Diplomats': 'Intuitive and Feeling personality types, known for their empathy, diplomatic skills, and passionate idealism.',
    'Sentinels': 'Observant and Judging personality types, known for their practicality and focus on order, security, and stability.',
    'Explorers': 'Observant and Prospecting personality types, known for their spontaneity, ingenuity, and flexibility.'
  };

  const FAMILY_MEMBERS = {
    'Analysts':  ['INTJ','INTP','ENTJ','ENTP'],
    'Diplomats': ['INFJ','INFP','ENFJ','ENFP'],
    'Sentinels': ['ISTJ','ISFJ','ESTJ','ESFJ'],
    'Explorers': ['ISTP','ISFP','ESTP','ESFP']
  };

  const DESCRIPTIONS = {
    'ISTJ': 'Practical and fact-minded individuals, whose reliability cannot be doubted.',
    'ISFJ': 'Very dedicated and warm protectors, always ready to defend their loved ones.',
    'INFJ': 'Quiet and mystical, yet very inspiring and tireless idealists.',
    'INTJ': 'Imaginative and strategic thinkers, with a plan for everything.',
    'ISTP': 'Bold and practical experimenters, masters of all kinds of tools.',
    'ISFP': 'Flexible and charming artists, always ready to explore and experience something new.',
    'INFP': 'Poetic, kind and altruistic people, always eager to help a good cause.',
    'INTP': 'Innovative inventors with an unquenchable thirst for knowledge.',
    'ESTP': 'Smart, energetic and very perceptive people, who truly enjoy living on the edge.',
    'ESFP': 'Spontaneous, energetic and enthusiastic people – life is never boring around them.',
    'ENFP': 'Enthusiastic, creative and sociable free spirits, who can always find a reason to smile.',
    'ENTP': 'Smart and curious thinkers who cannot resist an intellectual challenge.',
    'ESTJ': 'Excellent administrators, unsurpassed at managing things – or people.',
    'ESFJ': 'Extraordinarily caring, social and popular people, always eager to help.',
    'ENFJ': 'Charismatic and inspiring leaders, able to mesmerize their listeners.',
    'ENTJ': 'Bold, imaginative and strong-willed leaders, always finding a way – or making one.'
  };

  const el = (t, c, txt) => {
    const n = document.createElement(t);
    if (c) n.className = c;
    if (txt !== undefined) n.textContent = txt;
    return n;
  };

  /* ================================================================
     Initialise — show spinner immediately (Screen 0 fix)
     ================================================================ */
  (async function init() {
    showLoading('Loading Who Am I...');
    try {
      await renderIntro();
    } finally {
      hideLoading();
    }
  })();

  /* ── Status check ─────────────────────────────────────────────── */
  async function checkStatus() {
    console.log('Checking status for week:', week);
    try {
      const res = await fetch(cfg.restUrlStatus + "?week=" + encodeURIComponent(week), {
        method: 'GET',
        headers: { 'X-WP-Nonce': cfg.nonce || '', 'Accept': 'application/json' },
        credentials: 'same-origin'
      });
      if (res.ok) {
        const data = await res.json();
        console.log('Status response:', data);
        return data;
      }
    } catch (err) {
      console.error('Status check error:', err);
    }
    return { status: 'not_started', can_start: true };
  }

  /* ================================================================
     SCREEN 1 — Intro: "Who Am I" + AI message + question count
     ================================================================ */
  async function renderIntro() {
    console.log('renderIntro called, week =', week);
    
    const status = await checkStatus();
    
    if (status.status === 'completed') { await renderSummary(); return; }
    if (status.status === 'in_progress') {
      await loadQuestions();
      await resumeFromLastQuestion(status.last_question_id, status.answered_question_ids || []);
      return;
    }

    // Fetch AI intro
    const introRes = await fetch(cfg.restUrlIntro + "?week=" + encodeURIComponent(week), {
      method: 'GET',
      headers: { 'X-WP-Nonce': cfg.nonce || '', 'Accept': 'application/json' },
      credentials: 'same-origin'
    });

    let introData = { intro_message: "Welcome to Who Am I!" };
    if (introRes.ok) introData = await introRes.json();

    // Build intro card
    const wrap = el("div","rag-wrap");
    const card = el("div","rag-card");
    card.appendChild(el("h2","rag-title","Week " + week + " — Who Am I"));

    // AI intro message
    if (introData.intro_message) {
      const introBox = el("div","rag-ai-intro");
      introBox.style.cssText = "background:#fff8e6;border:1px solid #ffd966;border-left:4px solid #f0ad4e;padding:12px 14px;border-radius:6px;line-height:1.6;margin:12px 0;font-size:14px;color:#333;";
      introBox.textContent = introData.intro_message;
      card.appendChild(introBox);
    }

    // Question count
    if (introData.total_questions) {
      const infoBox = el("div","rag-sub");
      infoBox.style.cssText = "margin:12px 0;padding:10px;background:#f0f8ff;border-radius:6px;";
      infoBox.textContent = "You'll be answering " + introData.total_questions + " questions to discover which of 16 personality types you are.";
      card.appendChild(infoBox);
    }

    // Next button → goes to Screen 2 (families)
    const btn = el("button","rag-btn","Next");
    btn.onclick = () => renderFamilies();
    card.appendChild(btn);

    wrap.appendChild(card);
    root.replaceChildren(wrap);
  }

  /* ================================================================
     SCREEN 2 — Avatar image + Steve explains the 4 families
     ================================================================ */
  function renderFamilies() {
    const wrap = el("div","rag-wrap");
    const card = el("div","rag-card");
    card.appendChild(el("h2","rag-title","Meet the 16 Personality Types"));

    // Avatar image
    if (cfg.avatarImageUrl) {
      const avatarContainer = el("div","ptest-avatar-grid-container");
      avatarContainer.style.cssText = "margin:16px 0;text-align:center;";
      const avatarImg = document.createElement("img");
      avatarImg.src = cfg.avatarImageUrl;
      avatarImg.alt = "The 16 Personality Types";
      avatarImg.style.cssText = "max-width:100%;height:auto;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.1);";
      avatarContainer.appendChild(avatarImg);
      card.appendChild(avatarContainer);
    }

    // Steve Says — explains the families in his voice
    const steveBox = el("div","rag-ai");
    steveBox.style.cssText = "background:#fff8e6;border:1px solid #ffd966;border-left:4px solid #f0ad4e;padding:16px;border-radius:8px;line-height:1.7;margin:20px 0;font-size:14px;color:#333;white-space:normal;";

    const steveIntro = el("div","");
    steveIntro.style.cssText = "margin-bottom:14px;";
    steveIntro.textContent = "Steve says: Right, here's how it works! Every person fits into one of 4 personality families, and each family has 4 different personality types — that's 16 in total. Have a look at the characters above and see if any of them remind you of yourself. Here's what each family is all about:";
    steveBox.appendChild(steveIntro);

    const familyOrder = ['Analysts','Diplomats','Sentinels','Explorers'];
    const familyColors = { 'Analysts':'#88619a', 'Diplomats':'#33a474', 'Sentinels':'#4298b4', 'Explorers':'#e4ae3a' };

    const familySteve = {
      'Analysts': "The Analysts are the big thinkers — the Architect, the Logician, the Commander, and the Debater. If you love solving puzzles, asking \"why?\", and coming up with smart strategies, you might be one of these. They're known for their brainpower, their logic, and their ability to see things others miss.",
      'Diplomats': "The Diplomats are the heart of any group — the Advocate, the Mediator, the Protagonist, and the Campaigner. These are the people who really care about others, want to make the world a better place, and have incredible imagination. If you're the kind of person your friends come to when they need someone to listen, this might be your family.",
      'Sentinels': "The Sentinels are the ones who keep everything running smoothly — the Logistician, the Defender, the Executive, and the Consul. They're practical, reliable, and get things done. If you're the kind of person who keeps their promises, makes plans, and is always there when people need you, you could be a Sentinel.",
      'Explorers': "The Explorers are the adventurers — the Virtuoso, the Adventurer, the Entrepreneur, and the Entertainer. They love trying new things, living in the moment, and bringing energy wherever they go. If you're hands-on, spontaneous, and always up for something exciting, this could be your crew."
    };

    familyOrder.forEach(family => {
      const block = el("div","ptest-family-explain");
      block.style.cssText = "margin:12px 0;padding:12px 14px;border-radius:8px;border-left:4px solid " + familyColors[family] + ";background:rgba(255,255,255,0.6);";

      const fn = el("strong","");
      fn.style.cssText = "font-size:15px;color:" + familyColors[family] + ";display:block;margin-bottom:4px;";
      fn.textContent = family;
      block.appendChild(fn);

      const fd = el("span","");
      fd.style.cssText = "font-size:14px;color:#444;line-height:1.6;";
      fd.textContent = familySteve[family];
      block.appendChild(fd);

      steveBox.appendChild(block);
    });

    const steveClose = el("div","");
    steveClose.style.cssText = "margin-top:14px;font-weight:500;";
    steveClose.textContent = "So which family do YOU belong to? Let's find out! 👊";
    steveBox.appendChild(steveClose);

    card.appendChild(steveBox);

    // Start Test button
    const btn = el("button","rag-btn","Start Test");
    btn.style.cssText = "border:1px solid #111;background:#111;color:#fff;border-radius:10px;padding:10px 20px;cursor:pointer;font-size:15px;font-weight:600;transition:background 0.2s;";
    btn.onclick = async () => {
      showLoading('Loading your questions...');
      try {
        await loadQuestions();
        idx = 0;
        await renderQuestion();
      } catch(e) { hideLoading(); }
    };
    card.appendChild(btn);

    wrap.appendChild(card);
    root.replaceChildren(wrap);
  }

  /* ── Resume ───────────────────────────────────────────────────── */
  async function resumeFromLastQuestion(lastQuestionId, answeredIds) {
    let firstUnansweredIdx = -1;
    for (let i = 0; i < questions.length; i++) {
      if (!answeredIds.includes(parseInt(questions[i].id))) {
        firstUnansweredIdx = i;
        break;
      }
    }
    if (firstUnansweredIdx >= 0) { idx = firstUnansweredIdx; await renderQuestion(); }
    else { await renderSummary(); }
  }

  /* ── Load questions ───────────────────────────────────────────── */
  async function loadQuestions() {
    const url = cfg.restUrlQuestions + "?week=" + encodeURIComponent(week);
    try {
      const res = await fetch(url, {
        method: 'GET',
        headers: { 'X-WP-Nonce': cfg.nonce || '', 'Accept': 'application/json' },
        credentials: 'same-origin'
      });
      if (!res.ok) throw new Error("HTTP " + res.status);
      const data = await res.json();
      if (!data || !data.ok) throw new Error(data && data.error ? data.error : 'Failed');
      questions = data.questions || [];
    } catch (err) {
      console.error('Error loading questions', err);
      alert('Loading questions failed: ' + err.message);
      throw err;
    }
  }

  /* ================================================================
     QUESTION SCREEN — no "Type: MBTI" label
     ================================================================ */
  async function renderQuestion() {
    showLoading('Loading question...');
    
    const q = questions[idx];
    const wrap = el("div","rag-wrap");
    const card = el("div","rag-card");

    // Progress bar
    const totalQ = questions.length;
    const pct = (idx / totalQ) * 100;

    const pc = el("div","ptest-progress-container");
    pc.style.cssText = "margin:16px 0 20px;";

    const pl = el("div","ptest-progress-label");
    pl.style.cssText = "font-size:14px;color:#666;margin-bottom:8px;text-align:center;";
    pl.textContent = "Progress: " + idx + " of " + totalQ + " questions completed";
    pc.appendChild(pl);

    const outer = el("div","ptest-progress-outer");
    outer.style.cssText = "width:100%;height:24px;background:#e0e0e0;border-radius:12px;overflow:hidden;position:relative;";

    const inner = el("div","ptest-progress-inner");
    inner.style.cssText = "height:100%;background:linear-gradient(90deg,#4caf50,#8bc34a);border-radius:12px;width:" + pct + "%;transition:width 0.3s ease;";

    const pt = el("div","ptest-progress-text");
    pt.style.cssText = "position:absolute;top:0;left:0;right:0;bottom:0;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:12px;color:#333;";
    pt.textContent = Math.round(pct) + "%";

    outer.appendChild(inner);
    outer.appendChild(pt);
    pc.appendChild(outer);
    card.appendChild(pc);

    // Question header — NO "Type: MBTI" line
    card.appendChild(el("h2","rag-title","Question " + (idx + 1) + " of " + totalQ));
    card.appendChild(el("div","rag-qtext", q.q_text));

    // AI guidance
    try {
      const guidanceRes = await fetch(cfg.restUrlGuidance, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || '' },
        credentials: 'same-origin',
        body: JSON.stringify({ question_text: q.q_text, q_type: q.q_type })
      });
      if (guidanceRes.ok) {
        const gd = await guidanceRes.json();
        if (gd.guidance) {
          const gb = el("div","rag-ai-question");
          gb.textContent = gd.guidance;
          card.appendChild(gb);
        }
      }
    } catch(e) { console.error('Guidance fetch error', e); }

    // Answer options
    if (q.q_type === 'MBTI') renderMBTIOptions(card, q);
    else if (q.q_type === 'DISC') renderDISCOptions(card, q);

    // Chatbot with SteveGPT branding
    if (chatSource) {
      const chatWrap = el("div","rag-chatwrap");
      const chatClone = chatSource.cloneNode(true);
      chatClone.style.display = "block";
      chatClone.id = "mfsd-ptest-chat-" + idx;
      const chatMessages = chatClone.querySelectorAll('.mwai-conversation, .mwai-chat');
      chatMessages.forEach(function(msg) {
        const mc = msg.querySelector('.mwai-messages');
        if (mc) mc.innerHTML = '';
      });
      chatWrap.appendChild(chatClone);
      card.appendChild(chatWrap);

      // Override default AI greeting
      setTimeout(function() {
        var chatEl = document.getElementById("mfsd-ptest-chat-" + idx);
        if (chatEl) {
          var aiMsgs = chatEl.querySelectorAll('.mwai-ai, .mwai-reply');
          aiMsgs.forEach(function(m) {
            if (m.textContent.trim().match(/^Hi!?\s*How can I help/i)) {
              m.textContent = "Need a hand with this question? Just ask me and I'll help you out! - SteveGPT";
            }
          });
        }
      }, 500);
    }

    wrap.appendChild(card);
    root.replaceChildren(wrap);
    hideLoading();
  }

  /* ── MBTI answer buttons ──────────────────────────────────────── */
  function renderMBTIOptions(card, q) {
    const lights = el("div","rag-lights");
    [
      { label: "Red", value: "R", desc: "Disagree" },
      { label: "Amber", value: "A", desc: "Neutral" },
      { label: "Green", value: "G", desc: "Agree" }
    ].forEach(opt => {
      const btn = el("button","rag-light " + opt.label.toLowerCase());
      btn.style.cssText = "display:flex;flex-direction:column;align-items:center;gap:4px;";
      const ld = el("div","");
      ld.style.cssText = "font-size:16px;font-weight:600;";
      ld.textContent = opt.label;
      btn.appendChild(ld);
      const dd = el("div","");
      dd.style.cssText = "font-size:12px;opacity:0.9;";
      dd.textContent = opt.desc;
      btn.appendChild(dd);
      btn.onclick = () => handleMBTIAnswer(q, opt.value);
      lights.appendChild(btn);
    });
    card.appendChild(lights);
  }

  /* ── DISC answer buttons ──────────────────────────────────────── */
  function renderDISCOptions(card, q) {
    const sc = el("div","disc-scale-container");
    sc.style.cssText = "margin:20px 0;";
    const sl = el("div","disc-scale-label");
    sl.style.cssText = "text-align:center;margin-bottom:12px;font-weight:600;color:#555;font-size:16px;";
    sl.textContent = "How much do you agree with this statement?";
    sc.appendChild(sl);

    const lights = el("div","rag-lights");
    lights.style.cssText = "display:flex;gap:8px;justify-content:center;flex-wrap:wrap;";
    [
      { label: "Completely Disagree", value: 1, color: "#d9534f", emoji: "👎" },
      { label: "Somewhat Disagree",   value: 2, color: "#f0ad4e", emoji: "🤔" },
      { label: "Neutral",             value: 3, color: "#9e9e9e", emoji: "😐" },
      { label: "Somewhat Agree",      value: 4, color: "#5cb85c", emoji: "👍" },
      { label: "Completely Agree",     value: 5, color: "#4caf50", emoji: "💯" }
    ].forEach(opt => {
      const btn = el("button","rag-light disc-scale-btn");
      btn.style.cssText = "background:" + opt.color + ";color:white;border:none;border-radius:10px;padding:16px 12px;cursor:pointer;font-weight:600;font-size:13px;min-width:90px;transition:all 0.2s;white-space:pre-line;text-align:center;display:flex;flex-direction:column;align-items:center;gap:6px;";
      const em = el("span",""); em.style.cssText = "font-size:24px;"; em.textContent = opt.emoji; btn.appendChild(em);
      const lb = el("span",""); lb.style.cssText = "font-size:12px;line-height:1.3;"; lb.textContent = opt.label; btn.appendChild(lb);
      btn.onmouseover = () => { btn.style.transform = "translateY(-3px)"; btn.style.boxShadow = "0 4px 12px rgba(0,0,0,0.2)"; };
      btn.onmouseout = () => { btn.style.transform = "translateY(0)"; btn.style.boxShadow = "none"; };
      btn.onclick = () => handleDISCAnswer(q, opt.value);
      lights.appendChild(btn);
    });
    sc.appendChild(lights);
    card.appendChild(sc);
  }

  /* ================================================================
     Answer handlers — TWO-PHASE loading
     "Saving your answer..." → "Loading next question..."
     ================================================================ */
  async function handleMBTIAnswer(question, answerValue) {
    showLoading('Saving your answer...');
    try {
      const res = await fetch(cfg.restUrlAnswer, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || '' },
        credentials: 'same-origin',
        body: JSON.stringify({
          week: week, question_id: question.id, q_type: 'MBTI',
          answer: answerValue, axis: question.mbti_axis, letter: question.mbti_letter
        })
      });
      if (!res.ok) throw new Error('Failed to save answer');
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Failed');

      idx++;
      if (idx < questions.length) {
        updateLoadingText('Loading next question...');
        await renderQuestion();
      } else {
        updateLoadingText('Generating your results...');
        await renderSummary();
      }
    } catch (err) {
      hideLoading();
      console.error('Error saving MBTI answer:', err);
      alert('Error saving answer: ' + err.message);
    }
  }

  async function handleDISCAnswer(question, answerValue) {
    showLoading('Saving your answer...');
    try {
      const mapping = question.disc_mapping;
      const contribution = answerValue - 3;
      const res = await fetch(cfg.restUrlAnswer, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || '' },
        credentials: 'same-origin',
        body: JSON.stringify({
          week: week, question_id: question.id, q_type: 'DISC',
          answer: answerValue,
          d_contribution: mapping.D * contribution, i_contribution: mapping.I * contribution,
          s_contribution: mapping.S * contribution, c_contribution: mapping.C * contribution
        })
      });
      if (!res.ok) throw new Error('Failed to save answer');
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Failed');

      idx++;
      if (idx < questions.length) {
        updateLoadingText('Loading next question...');
        await renderQuestion();
      } else {
        updateLoadingText('Generating your results...');
        await renderSummary();
      }
    } catch (err) {
      hideLoading();
      console.error('Error saving DISC answer:', err);
      alert('Error saving answer: ' + err.message);
    }
  }

  /* ================================================================
     RESULTS SCREEN — tabbed, no MBTI codes, avatar + family name
     ================================================================ */
  async function renderSummary() {
    showLoading('Generating your results...');
    try {
      const summaryRes = await fetch(cfg.restUrlSummary, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce || '' },
        credentials: 'same-origin',
        body: JSON.stringify({ week: week })
      });
      const summaryData = await summaryRes.json();
      if (!summaryData || !summaryData.ok) {
        hideLoading();
        alert("Summary failed: " + (summaryData.error || 'Unknown error'));
        return;
      }
      hideLoading();

      const wrap = el("div","rag-wrap");
      const card = el("div","rag-card");
      card.appendChild(el("h2","rag-title","Who Am I (Part 1) — Your Results"));

      /* ── Personality header: family + character name ── */
      if (summaryData.mbti_type) {
        const profile = PROFILES[summaryData.mbti_type] || { name: 'Unique', family: 'Individual', familyColor: '#666' };
        const desc = DESCRIPTIONS[summaryData.mbti_type] || 'A unique personality!';

        const hs = el("div","ptest-result-header");
        hs.style.cssText = "margin:20px 0;padding:24px;border-radius:12px;text-align:center;background:linear-gradient(135deg," + profile.familyColor + "22," + profile.familyColor + "11);border:2px solid " + profile.familyColor + ";";

        const fl = el("div","");
        fl.style.cssText = "font-size:14px;font-weight:600;text-transform:uppercase;letter-spacing:1px;color:" + profile.familyColor + ";margin-bottom:8px;";
        fl.textContent = "You are part of the " + profile.family + " family";
        hs.appendChild(fl);

        const nl = el("div","");
        nl.style.cssText = "font-size:28px;font-weight:700;color:#2c3e50;margin-bottom:8px;";
        nl.textContent = "You are The " + profile.name;
        hs.appendChild(nl);

        const dl = el("div","");
        dl.style.cssText = "font-size:15px;color:#555;line-height:1.5;max-width:600px;margin:0 auto;";
        dl.textContent = desc;
        hs.appendChild(dl);

        const fdesc = el("div","");
        fdesc.style.cssText = "font-size:13px;color:#777;margin-top:12px;line-height:1.5;";
        fdesc.textContent = FAMILY_DESCRIPTIONS[profile.family] || '';
        hs.appendChild(fdesc);

        card.appendChild(hs);
      }

      /* ── Tabbed AI Summary ── */
      if (summaryData.ai_summary) {
        const sections = parseSummarySections(summaryData.ai_summary);

        if (sections.length > 1) {
          const tc = el("div","ptest-results-tabs");
          tc.style.cssText = "margin:24px 0;";

          const tnav = el("div","ptest-tab-nav");
          const tcont = el("div","ptest-tab-content-area");
          tcont.style.cssText = "padding:20px 0;";

          sections.forEach((sec, i) => {
            const tb = el("button","ptest-result-tab" + (i === 0 ? " active" : ""));
            tb.textContent = sec.title;
            tb.dataset.tabIndex = i;
            tb.onclick = function() {
              tnav.querySelectorAll('.ptest-result-tab').forEach(b => b.classList.remove('active'));
              tcont.querySelectorAll('.ptest-tab-panel').forEach(p => p.style.display = 'none');
              this.classList.add('active');
              tcont.querySelector('[data-panel="' + i + '"]').style.display = 'block';
            };
            tnav.appendChild(tb);

            const panel = el("div","ptest-tab-panel");
            panel.dataset.panel = i;
            panel.style.display = i === 0 ? 'block' : 'none';
            const pc = el("div","rag-ai");
            pc.style.cssText = "background:#f9fafc;border:1px dashed #cbd5e1;padding:18px;border-radius:8px;white-space:pre-line;line-height:1.7;";
            pc.textContent = sec.content;
            panel.appendChild(pc);
            tcont.appendChild(panel);
          });

          tc.appendChild(tnav);
          tc.appendChild(tcont);
          card.appendChild(tc);
        } else {
          const sb = el("div","rag-ai");
          sb.textContent = summaryData.ai_summary;
          card.appendChild(sb);
        }
      }

      /* ── DISC polar plot (if present) ── */
      if (summaryData.disc_scores) {
        const ds = el("div","ptest-disc-section");
        ds.style.cssText = "margin:20px 0;padding:20px;background:#f8f9fa;border-radius:8px;";
        const dt = el("h3","");
        dt.style.cssText = "margin:0 0 16px;color:#2c3e50;font-size:20px;text-align:center;";
        dt.textContent = "Your Communication Style";
        ds.appendChild(dt);

        const plotC = el("div","disc-plot-container");
        plotC.style.cssText = "display:flex;flex-direction:column;align-items:center;margin:20px 0;";
        const canvas = createDISCPolarPlot(summaryData.disc_scores);
        if (canvas) plotC.appendChild(canvas);
        ds.appendChild(plotC);

        const bd = el("div","disc-breakdown");
        bd.style.cssText = "display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:16px 0;";
        ['D','I','S','C'].forEach(letter => {
          const score = summaryData.disc_scores[letter];
          const bar = el("div","disc-bar");
          bar.style.cssText = "background:linear-gradient(to top,#4a90e2,#357abd);border-radius:8px;padding:12px 8px;color:white;text-align:center;display:flex;flex-direction:column;justify-content:space-between;min-height:120px;";
          const ld2 = el("div",""); ld2.style.cssText = "font-weight:600;font-size:16px;"; ld2.textContent = letter; bar.appendChild(ld2);
          const sd = el("div",""); sd.style.cssText = "font-size:24px;font-weight:bold;margin:8px 0;"; sd.textContent = Math.round(score) + "%"; bar.appendChild(sd);
          const nd = el("div",""); nd.style.cssText = "font-size:11px;opacity:0.9;"; nd.textContent = getDISCName(letter); bar.appendChild(nd);
          bd.appendChild(bar);
        });
        ds.appendChild(bd);
        card.appendChild(ds);
      }

      /* ── Chatbot ── */
      if (chatSource) {
        const cw = el("div","rag-chatwrap");
        const cc = chatSource.cloneNode(true);
        cc.style.display = "block";
        cc.id = "mfsd-ptest-chat-summary";
        cc.querySelectorAll('.mwai-conversation, .mwai-chat').forEach(function(msg) {
          const mc = msg.querySelector('.mwai-messages');
          if (mc) mc.innerHTML = '';
        });

        const cp = el("div","");
        cp.style.cssText = "margin:20px 0 10px;padding:15px;background:#fff9e6;border-left:4px solid #ffc107;border-radius:4px;";
        const cpt = el("p","");
        cpt.style.cssText = "margin:0;color:#856404;font-weight:600;";
        cpt.textContent = "Have questions about your results? Ask SteveGPT below!";
        cp.appendChild(cpt);
        card.appendChild(cp);
        cw.appendChild(cc);
        card.appendChild(cw);

        setTimeout(function() {
          var chatEl = document.getElementById("mfsd-ptest-chat-summary");
          if (chatEl) {
            chatEl.querySelectorAll('.mwai-ai, .mwai-reply').forEach(function(m) {
              if (m.textContent.trim().match(/^Hi!?\s*How can I help/i)) {
                m.textContent = "Hey! I'm SteveGPT. Want to know more about your personality type or what your results mean? Just ask! - SteveGPT";
              }
            });
          }
        }, 500);
      }

      wrap.appendChild(card);
      root.replaceChildren(wrap);
    } catch (err) {
      hideLoading();
      console.error('Summary error:', err);
      alert('Failed to load summary: ' + err.message);
    }
  }

  /* ── Parse AI summary into tabbed sections ──
     Expected: [SECTION:Title] content [SECTION:Title] content … */
  function parseSummarySections(text) {
    const regex = /\[SECTION:([^\]]+)\]/g;
    const parts = [];
    let lastIdx = 0, prevTitle = null, match;

    while ((match = regex.exec(text)) !== null) {
      if (prevTitle !== null) {
        const content = text.substring(lastIdx, match.index).trim();
        if (content) parts.push({ title: prevTitle, content: content });
      }
      prevTitle = match[1].trim();
      lastIdx = match.index + match[0].length;
    }
    if (prevTitle !== null) {
      const content = text.substring(lastIdx).trim();
      if (content) parts.push({ title: prevTitle, content: content });
    }
    if (parts.length === 0) return [{ title: 'Your Results', content: text }];
    return parts;
  }

  /* ── DISC polar plot ──────────────────────────────────────────── */
  function createDISCPolarPlot(scores) {
    const canvas = document.createElement('canvas');
    canvas.width = 400; canvas.height = 400; canvas.id = 'disc-polar-plot';
    const ctx = canvas.getContext('2d');
    const cx = 200, cy = 200, mr = 140;
    const segs = [
      { key:'D', label:'Dominance',        sa:-Math.PI/2, ea:0 },
      { key:'I', label:'Influence',         sa:0,          ea:Math.PI/2 },
      { key:'S', label:'Steadiness',        sa:Math.PI/2,  ea:Math.PI },
      { key:'C', label:'Conscientiousness', sa:Math.PI,    ea:3*Math.PI/2 }
    ];
    const cols = { D:'#2d5f8d', I:'#f9b234', S:'#c67a3c', C:'#3b5998' };

    ctx.strokeStyle='#e0e0e0'; ctx.lineWidth=1;
    for(let i=1;i<=4;i++){ctx.beginPath();ctx.arc(cx,cy,(mr/4)*i,0,2*Math.PI);ctx.stroke();}
    ctx.strokeStyle='#ccc';ctx.lineWidth=2;ctx.setLineDash([5,5]);
    ctx.beginPath();ctx.moveTo(cx,cy-mr);ctx.lineTo(cx,cy+mr);ctx.stroke();
    ctx.beginPath();ctx.moveTo(cx-mr,cy);ctx.lineTo(cx+mr,cy);ctx.stroke();
    ctx.setLineDash([]);

    segs.forEach(s=>{
      const p=scores[s.key]||0, fr=(p/100)*mr;
      ctx.fillStyle=cols[s.key];ctx.globalAlpha=0.6;
      ctx.beginPath();ctx.moveTo(cx,cy);ctx.arc(cx,cy,fr,s.sa,s.ea);ctx.closePath();ctx.fill();
      ctx.globalAlpha=1;ctx.strokeStyle=cols[s.key];ctx.lineWidth=2;
      ctx.beginPath();ctx.moveTo(cx,cy);ctx.arc(cx,cy,mr,s.sa,s.ea);ctx.closePath();ctx.stroke();
    });

    ctx.font='bold 14px Arial';ctx.fillStyle='#666';ctx.textAlign='center';
    ctx.fillText('Active',cx,cy-mr-15);ctx.fillText('Reflective',cx,cy+mr+25);
    ctx.save();ctx.translate(cx-mr-25,cy);ctx.rotate(-Math.PI/2);ctx.fillText('People Focus',0,0);ctx.restore();
    ctx.save();ctx.translate(cx+mr+25,cy);ctx.rotate(Math.PI/2);ctx.fillText('Task Focus',0,0);ctx.restore();

    segs.forEach(s=>{
      const p=scores[s.key]||0, ma=(s.sa+s.ea)/2;
      const lx=cx+mr*0.4*Math.cos(ma), ly=cy+mr*0.4*Math.sin(ma);
      ctx.font='bold 48px Arial';ctx.fillStyle='#333';ctx.textAlign='center';ctx.fillText(s.key,lx,ly+15);
      ctx.font='bold 16px Arial';ctx.fillStyle='#333';
      const llx=cx+(mr+50)*Math.cos(ma), lly=cy+(mr+50)*Math.sin(ma);
      ctx.fillText(s.label,llx,lly);
      ctx.font='bold 16px Arial';ctx.fillStyle=cols[s.key];
      const px=cx+mr*0.65*Math.cos(ma), py=cy+mr*0.65*Math.sin(ma);
      ctx.fillText(Math.round(p)+'%',px,py+5);
    });
    return canvas;
  }

  function getDISCName(l){ return {D:'Dominance',I:'Influence',S:'Steadiness',C:'Conscientiousness'}[l]||l; }

  /* ── Loading overlay helpers ──────────────────────────────────── */
  function showLoading(message) {
    const existing = document.querySelector(".rag-loading-overlay");
    if (existing) { const t = existing.querySelector(".rag-loading-text"); if (t) t.textContent = message||"Loading..."; return; }
    const ov = el("div","rag-loading-overlay");
    ov.appendChild(el("div","rag-spinner"));
    ov.appendChild(el("div","rag-loading-text", message||"Loading..."));
    document.body.appendChild(ov);
  }
  function updateLoadingText(msg) {
    const ov = document.querySelector(".rag-loading-overlay");
    if (ov) { const t = ov.querySelector(".rag-loading-text"); if (t) t.textContent = msg; }
  }
  function hideLoading() {
    const ov = document.querySelector(".rag-loading-overlay");
    if (ov) ov.remove();
  }

})();