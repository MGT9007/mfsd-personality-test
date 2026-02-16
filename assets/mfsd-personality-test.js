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

  const el = (t, c, txt) => {
    const n = document.createElement(t);
    if (c) n.className = c;
    if (txt !== undefined) n.textContent = txt;
    return n;
  };

  // Initialize
  (async function init() {
    await renderIntro();
  })();

  // Check test status
  async function checkStatus() {
    console.log('Checking status for week:', week);
    try {
      const res = await fetch(cfg.restUrlStatus + "?week=" + encodeURIComponent(week), {
        method: 'GET',
        headers: {
          'X-WP-Nonce': cfg.nonce || '',
          'Accept': 'application/json'
        },
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

  async function renderIntro() {
    console.log('renderIntro called, week =', week);
    
    const status = await checkStatus();
    
    // If completed, go to summary
    if (status.status === 'completed') {
      await renderSummary();
      return;
    }
    
    // If in progress, resume
    if (status.status === 'in_progress') {
      await loadQuestions();
      await resumeFromLastQuestion(status.last_question_id, status.answered_question_ids || []);
      return;
    }

    // Get AI intro message
    const introRes = await fetch(cfg.restUrlIntro + "?week=" + encodeURIComponent(week), {
      method: 'GET',
      headers: {
        'X-WP-Nonce': cfg.nonce || '',
        'Accept': 'application/json'
      },
      credentials: 'same-origin'
    });

    let introData = { intro_message: "Welcome to your personality test!" };
    if (introRes.ok) {
      introData = await introRes.json();
    }

    // Show intro screen
    const wrap = el("div","rag-wrap");
    const card = el("div","rag-card");
    card.appendChild(el("h2","rag-title","Week " + week + " — Personality Test"));

    // AI intro message
    if (introData.intro_message) {
      const introBox = el("div","rag-ai-intro");
      introBox.style.cssText = "background: #fff8e6; border: 1px solid #ffd966; border-left: 4px solid #f0ad4e; padding: 12px 14px; border-radius: 6px; line-height: 1.6; margin: 12px 0; font-size: 14px; color: #333;";
      introBox.textContent = introData.intro_message;
      card.appendChild(introBox);
    }

    // Test info
    if (introData.total_questions) {
      const infoBox = el("div","rag-sub");
      infoBox.style.cssText = "margin: 12px 0; padding: 10px; background: #f0f8ff; border-radius: 6px;";
      
      let info = "You'll be answering " + introData.total_questions + " questions.\n";
      if (introData.mbti_count > 0) {
        info += "• " + introData.mbti_count + " MBTI questions\n";
      }
      if (introData.disc_count > 0) {
        info += "• " + introData.disc_count + " DISC questions\n";
      }
      infoBox.textContent = info;
      card.appendChild(infoBox);
    }

    const btn = el("button","rag-btn","Begin Test");
    btn.onclick = async () => {
      await loadQuestions();
      idx = 0;
      await renderQuestion();
    };
    card.appendChild(btn);

    wrap.appendChild(card);
    root.replaceChildren(wrap);
  }

  async function resumeFromLastQuestion(lastQuestionId, answeredIds) {
    console.log('=== RESUME FUNCTION START ===');
    console.log('Last question ID:', lastQuestionId);
    console.log('Answered question IDs:', answeredIds);
    
    let firstUnansweredIdx = -1;
    for (let i = 0; i < questions.length; i++) {
      const questionId = parseInt(questions[i].id);
      const isAnswered = answeredIds.includes(questionId);
      
      if (!isAnswered) {
        firstUnansweredIdx = i;
        console.log('Found first unanswered question at index:', firstUnansweredIdx);
        break;
      }
    }
    
    if (firstUnansweredIdx >= 0) {
      idx = firstUnansweredIdx;
      await renderQuestion();
    } else {
      await renderSummary();
    }
  }

  async function loadQuestions() {
    console.log('Loading questions for week:', week);
    const url = cfg.restUrlQuestions + "?week=" + encodeURIComponent(week);

    try {
      const res = await fetch(url, {
        method: 'GET',
        headers: {
          'X-WP-Nonce': cfg.nonce || '',
          'Accept': 'application/json'
        },
        credentials: 'same-origin'
      });

      if (!res.ok) throw new Error("HTTP " + res.status);

      const data = await res.json();
      console.log('Questions response:', data);
      if (!data || !data.ok) throw new Error(data && data.error ? data.error : 'Failed');

      questions = data.questions || [];
      console.log('Loaded ' + questions.length + ' questions');
    } catch (err) {
      console.error('Error loading questions', err);
      alert('Loading questions failed: ' + err.message);
      throw err;
    }
  }

  async function renderQuestion() {
    showLoading('Loading question...');
    
    const q = questions[idx];
    const wrap = el("div","rag-wrap");
    const card = el("div","rag-card");

    // Progress bar
    const totalQuestions = questions.length;
    const completedCount = idx;
    const progressPercent = (completedCount / totalQuestions) * 100;

    const progressContainer = el("div", "ptest-progress-container");
    progressContainer.style.cssText = "margin: 16px 0 20px 0;";
    
    const progressLabel = el("div", "ptest-progress-label");
    progressLabel.style.cssText = "font-size: 14px; color: #666; margin-bottom: 8px; text-align: center;";
    progressLabel.textContent = "Progress: " + completedCount + " of " + totalQuestions + " questions completed";
    progressContainer.appendChild(progressLabel);
    
    const progressBarOuter = el("div", "ptest-progress-outer");
    progressBarOuter.style.cssText = "width: 100%; height: 24px; background: #e0e0e0; border-radius: 12px; overflow: hidden; position: relative;";
    
    const progressBarInner = el("div", "ptest-progress-inner");
    progressBarInner.style.cssText = "height: 100%; background: linear-gradient(90deg, #4caf50, #8bc34a); border-radius: 12px; width: " + progressPercent + "%; transition: width 0.3s ease;";
    
    const progressText = el("div", "ptest-progress-text");
    progressText.style.cssText = "position: absolute; top: 0; left: 0; right: 0; bottom: 0; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 12px; color: #333;";
    progressText.textContent = Math.round(progressPercent) + "%";
    
    progressBarOuter.appendChild(progressBarInner);
    progressBarOuter.appendChild(progressText);
    progressContainer.appendChild(progressBarOuter);
    
    card.appendChild(progressContainer);

    // Question header
    card.appendChild(el("h2","rag-title","Question " + (idx + 1) + " of " + totalQuestions));
    card.appendChild(el("div","rag-pos","Type: " + q.q_type));
    card.appendChild(el("div","rag-qtext", q.q_text));

    // Get AI guidance for question
    const guidanceRes = await fetch(cfg.restUrlGuidance, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': cfg.nonce || ''
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        question_text: q.q_text,
        q_type: q.q_type
      })
    });

    if (guidanceRes.ok) {
      const guidanceData = await guidanceRes.json();
      if (guidanceData.guidance) {
        const guidanceBox = el("div","rag-ai-question");
        guidanceBox.textContent = guidanceData.guidance;
        card.appendChild(guidanceBox);
      }
    }

    // Render appropriate answer options based on question type
    if (q.q_type === 'MBTI') {
      renderMBTIOptions(card, q);
    } else if (q.q_type === 'DISC') {
      renderDISCOptions(card, q);
    }

    // Add chatbot
    if (chatSource) {
      const chatWrap = el("div","rag-chatwrap");
      const chatClone = chatSource.cloneNode(true);
      chatClone.style.display = "block";
      chatClone.id = "mfsd-ptest-chat-" + idx; // Unique ID per question
      
      // Clear any existing conversation state from the cloned chatbot
      const chatMessages = chatClone.querySelectorAll('.mwai-conversation, .mwai-chat');
      chatMessages.forEach(function(msg) {
        // Remove any existing messages to start fresh
        const msgContainer = msg.querySelector('.mwai-messages');
        if (msgContainer) {
          msgContainer.innerHTML = '';
        }
      });
      
      chatWrap.appendChild(chatClone);
      card.appendChild(chatWrap);
    }

    wrap.appendChild(card);
    root.replaceChildren(wrap);
    
    hideLoading();
  }

  function renderMBTIOptions(card, q) {
    const lights = el("div","rag-lights");
    
    const options = [
      { label: "Red", value: "R", color: "#d9534f", desc: "Disagree" },
      { label: "Amber", value: "A", color: "#f0ad4e", desc: "Neutral" },
      { label: "Green", value: "G", color: "#5cb85c", desc: "Agree" }
    ];

    options.forEach(opt => {
      const btn = el("button", "rag-light " + opt.label.toLowerCase());
      btn.style.cssText = "display: flex; flex-direction: column; align-items: center; gap: 4px;";
      
      const labelDiv = el("div", "");
      labelDiv.style.cssText = "font-size: 16px; font-weight: 600;";
      labelDiv.textContent = opt.label;
      btn.appendChild(labelDiv);
      
      const descDiv = el("div", "");
      descDiv.style.cssText = "font-size: 12px; opacity: 0.9;";
      descDiv.textContent = opt.desc;
      btn.appendChild(descDiv);
      
      btn.onclick = () => handleMBTIAnswer(q, opt.value);
      lights.appendChild(btn);
    });

    card.appendChild(lights);
  }

  function renderDISCOptions(card, q) {
    const scaleContainer = el("div", "disc-scale-container");
    scaleContainer.style.cssText = "margin: 20px 0;";
    
    const scaleLabel = el("div", "disc-scale-label");
    scaleLabel.style.cssText = "text-align: center; margin-bottom: 12px; font-weight: 600; color: #555; font-size: 16px;";
    scaleLabel.textContent = "How much do you agree with this statement?";
    scaleContainer.appendChild(scaleLabel);
    
    const lights = el("div", "rag-lights");
    lights.style.cssText = "display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;";
    
    const options = [
      { label: "Completely\nDisagree", value: 1, color: "#d9534f", emoji: "👎" },
      { label: "Somewhat\nDisagree", value: 2, color: "#f0ad4e", emoji: "🤔" },
      { label: "Neutral", value: 3, color: "#9e9e9e", emoji: "😐" },
      { label: "Somewhat\nAgree", value: 4, color: "#5cb85c", emoji: "👍" },
      { label: "Completely\nAgree", value: 5, color: "#4caf50", emoji: "💯" }
    ];
    
    options.forEach(opt => {
      const btn = el("button", "rag-light disc-scale-btn");
      btn.style.cssText = `
        background: ${opt.color};
        color: white;
        border: none;
        border-radius: 10px;
        padding: 16px 12px;
        cursor: pointer;
        font-weight: 600;
        font-size: 13px;
        min-width: 90px;
        transition: all 0.2s;
        white-space: pre-line;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 6px;
      `;
      
      const emoji = el("span", "");
      emoji.style.cssText = "font-size: 24px;";
      emoji.textContent = opt.emoji;
      btn.appendChild(emoji);
      
      const label = el("span", "");
      label.style.cssText = "font-size: 12px; line-height: 1.3;";
      label.textContent = opt.label.replace('\n', ' ');
      btn.appendChild(label);
      
      btn.onmouseover = () => {
        btn.style.transform = "translateY(-3px)";
        btn.style.boxShadow = "0 4px 12px rgba(0,0,0,0.2)";
      };
      btn.onmouseout = () => {
        btn.style.transform = "translateY(0)";
        btn.style.boxShadow = "none";
      };
      btn.onclick = () => handleDISCAnswer(q, opt.value);
      
      lights.appendChild(btn);
    });
    
    scaleContainer.appendChild(lights);
    card.appendChild(scaleContainer);
  }

  async function handleMBTIAnswer(question, answerValue) {
    showLoading('Saving your answer...');
    
    try {
      const payload = {
        week: week,
        question_id: question.id,
        q_type: 'MBTI',
        answer: answerValue,
        axis: question.mbti_axis,
        letter: question.mbti_letter
      };
      
      const res = await fetch(cfg.restUrlAnswer, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': cfg.nonce || ''
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });
      
      if (!res.ok) throw new Error('Failed to save answer');
      
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Failed');
      
      hideLoading();
      
      // Move to next question or summary
      idx++;
      if (idx < questions.length) {
        await renderQuestion();
      } else {
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
      const contribution = answerValue - 3; // Convert 1-5 scale to -2 to +2
      
      const payload = {
        week: week,
        question_id: question.id,
        q_type: 'DISC',
        answer: answerValue,
        d_contribution: mapping.D * contribution,
        i_contribution: mapping.I * contribution,
        s_contribution: mapping.S * contribution,
        c_contribution: mapping.C * contribution
      };
      
      const res = await fetch(cfg.restUrlAnswer, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': cfg.nonce || ''
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
      });
      
      if (!res.ok) throw new Error('Failed to save answer');
      
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || 'Failed');
      
      hideLoading();
      
      // Move to next question or summary
      idx++;
      if (idx < questions.length) {
        await renderQuestion();
      } else {
        await renderSummary();
      }
      
    } catch (err) {
      hideLoading();
      console.error('Error saving DISC answer:', err);
      alert('Error saving answer: ' + err.message);
    }
  }

  async function renderSummary() {
    console.log('=== renderSummary START ===');
    
    showLoading('Generating your results...');

    try {
      // Generate summary
      const summaryRes = await fetch(cfg.restUrlSummary, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'X-WP-Nonce': cfg.nonce || ''
        },
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
      
      card.appendChild(el("h2","rag-title","Week " + week + " Results"));

      // MBTI Results
      if (summaryData.mbti_type) {
        const mbtiSection = el("div", "ptest-mbti-section");
        mbtiSection.style.cssText = "margin: 20px 0; padding: 20px; background: #e8f4fd; border-radius: 8px; border: 2px solid #4a90e2;";
        
        const mbtiTitle = el("h3", "");
        mbtiTitle.style.cssText = "margin: 0 0 12px 0; color: #2c3e50; font-size: 20px;";
        mbtiTitle.textContent = "Your MBTI Type: " + summaryData.mbti_type;
        mbtiSection.appendChild(mbtiTitle);
        
        const mbtiDesc = el("p", "");
        mbtiDesc.style.cssText = "margin: 0; line-height: 1.6; color: #555;";
        mbtiDesc.textContent = getMBTIDescription(summaryData.mbti_type);
        mbtiSection.appendChild(mbtiDesc);
        
        card.appendChild(mbtiSection);
      }

      // DISC Results with Polar Plot
      if (summaryData.disc_scores) {
        const discSection = el("div", "ptest-disc-section");
        discSection.style.cssText = "margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;";
        
        const discTitle = el("h3", "");
        discTitle.style.cssText = "margin: 0 0 16px 0; color: #2c3e50; font-size: 20px; text-align: center;";
        discTitle.textContent = "Your DISC Profile: " + summaryData.disc_scores.primary;
        discSection.appendChild(discTitle);
        
        // Create polar plot
        const plotContainer = el("div", "disc-plot-container");
        plotContainer.style.cssText = "display: flex; flex-direction: column; align-items: center; margin: 20px 0;";
        
        const canvas = createDISCPolarPlot(summaryData.disc_scores);
        if (canvas) {
          plotContainer.appendChild(canvas);
        }
        
        discSection.appendChild(plotContainer);
        
        // Add score breakdown
        const breakdown = el("div", "disc-breakdown");
        breakdown.style.cssText = "display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin: 16px 0;";
        
        ['D', 'I', 'S', 'C'].forEach(letter => {
          const score = summaryData.disc_scores[letter];
          const bar = el("div", "disc-bar");
          bar.style.cssText = "background: linear-gradient(to top, #4a90e2, #357abd); border-radius: 8px; padding: 12px 8px; color: white; text-align: center; display: flex; flex-direction: column; justify-content: space-between; min-height: 120px;";
          
          const letterDiv = el("div", "");
          letterDiv.style.cssText = "font-weight: 600; font-size: 16px;";
          letterDiv.textContent = letter;
          bar.appendChild(letterDiv);
          
          const scoreDiv = el("div", "");
          scoreDiv.style.cssText = "font-size: 24px; font-weight: bold; margin: 8px 0;";
          scoreDiv.textContent = Math.round(score) + "%";
          bar.appendChild(scoreDiv);
          
          const nameDiv = el("div", "");
          nameDiv.style.cssText = "font-size: 11px; opacity: 0.9;";
          nameDiv.textContent = getDISCName(letter);
          bar.appendChild(nameDiv);
          
          breakdown.appendChild(bar);
        });
        
        discSection.appendChild(breakdown);
        card.appendChild(discSection);
      }

      // AI Summary
      if (summaryData.ai_summary) {
        const summaryBox = el("div","rag-ai");
        summaryBox.textContent = summaryData.ai_summary;
        card.appendChild(summaryBox);
      }

      // Add context-aware chatbot for questions about results
      if (chatSource) {
        const chatWrap = el("div","rag-chatwrap");
        const chatClone = chatSource.cloneNode(true);
        chatClone.style.display = "block";
        chatClone.id = "mfsd-ptest-chat-summary"; // Unique ID for summary
        
        // Clear any existing conversation state from the cloned chatbot
        const chatMessages = chatClone.querySelectorAll('.mwai-conversation, .mwai-chat');
        chatMessages.forEach(function(msg) {
          // Remove any existing messages to start fresh
          const msgContainer = msg.querySelector('.mwai-messages');
          if (msgContainer) {
            msgContainer.innerHTML = '';
          }
        });
        
        // Add a helpful prompt above the chatbot
        const chatPrompt = el("div","");
        chatPrompt.style.cssText = "margin: 20px 0 10px 0; padding: 15px; background: #fff9e6; border-left: 4px solid #ffc107; border-radius: 4px;";
        const promptText = el("p","");
        promptText.style.cssText = "margin: 0; color: #856404; font-weight: 600;";
        promptText.textContent = "💬 Have questions about your results? Ask SteveGTP below!";
        chatPrompt.appendChild(promptText);
        card.appendChild(chatPrompt);
        
        chatWrap.appendChild(chatClone);
        card.appendChild(chatWrap);
      }

      wrap.appendChild(card);
      root.replaceChildren(wrap);

      console.log('=== renderSummary END ===');
    } catch (err) {
      hideLoading();
      console.error('Summary error:', err);
      alert('Failed to load summary: ' + err.message);
    }
  }

  function createDISCPolarPlot(scores) {
    const canvas = document.createElement('canvas');
    canvas.width = 400;
    canvas.height = 400;
    canvas.id = 'disc-polar-plot';
    
    const ctx = canvas.getContext('2d');
    const centerX = canvas.width / 2;
    const centerY = canvas.height / 2;
    const maxRadius = 140;
    
    // Define the 4 quadrants for DISC
    const segments = [
      { key: 'D', label: 'Dominance', startAngle: -Math.PI / 2, endAngle: 0, traits: ['Direct', 'Results-focused', 'Decisive'] },
      { key: 'I', label: 'Influence', startAngle: 0, endAngle: Math.PI / 2, traits: ['Enthusiastic', 'Optimistic', 'Outgoing'] },
      { key: 'S', label: 'Steadiness', startAngle: Math.PI / 2, endAngle: Math.PI, traits: ['Patient', 'Supportive', 'Stable'] },
      { key: 'C', label: 'Conscientiousness', startAngle: Math.PI, endAngle: 3 * Math.PI / 2, traits: ['Analytical', 'Precise', 'Systematic'] }
    ];
    
    const colors = {
      'D': '#2d5f8d',
      'I': '#f9b234',
      'S': '#c67a3c',
      'C': '#3b5998'
    };
    
    // Draw concentric circles (grid)
    ctx.strokeStyle = '#e0e0e0';
    ctx.lineWidth = 1;
    for (let i = 1; i <= 4; i++) {
      ctx.beginPath();
      ctx.arc(centerX, centerY, (maxRadius / 4) * i, 0, 2 * Math.PI);
      ctx.stroke();
    }
    
    // Draw axes
    ctx.strokeStyle = '#ccc';
    ctx.lineWidth = 2;
    ctx.setLineDash([5, 5]);
    
    ctx.beginPath();
    ctx.moveTo(centerX, centerY - maxRadius);
    ctx.lineTo(centerX, centerY + maxRadius);
    ctx.stroke();
    
    ctx.beginPath();
    ctx.moveTo(centerX - maxRadius, centerY);
    ctx.lineTo(centerX + maxRadius, centerY);
    ctx.stroke();
    
    ctx.setLineDash([]);
    
    // Draw filled segments
    segments.forEach(seg => {
      const percent = scores[seg.key] || 0;
      const fillRadius = (percent / 100) * maxRadius;
      
      ctx.fillStyle = colors[seg.key];
      ctx.globalAlpha = 0.6;
      ctx.beginPath();
      ctx.moveTo(centerX, centerY);
      ctx.arc(centerX, centerY, fillRadius, seg.startAngle, seg.endAngle);
      ctx.closePath();
      ctx.fill();
      ctx.globalAlpha = 1.0;
      
      ctx.strokeStyle = colors[seg.key];
      ctx.lineWidth = 2;
      ctx.beginPath();
      ctx.moveTo(centerX, centerY);
      ctx.arc(centerX, centerY, maxRadius, seg.startAngle, seg.endAngle);
      ctx.closePath();
      ctx.stroke();
    });
    
    // Add labels
    ctx.font = 'bold 14px Arial';
    ctx.fillStyle = '#666';
    ctx.textAlign = 'center';
    
    ctx.fillText('Active', centerX, centerY - maxRadius - 15);
    ctx.fillText('Reflective', centerX, centerY + maxRadius + 25);
    
    ctx.save();
    ctx.translate(centerX - maxRadius - 25, centerY);
    ctx.rotate(-Math.PI / 2);
    ctx.fillText('People Focus', 0, 0);
    ctx.restore();
    
    ctx.save();
    ctx.translate(centerX + maxRadius + 25, centerY);
    ctx.rotate(Math.PI / 2);
    ctx.fillText('Task Focus', 0, 0);
    ctx.restore();
    
    // Draw segment labels and percentages
    ctx.textAlign = 'center';
    segments.forEach(seg => {
      const percent = scores[seg.key] || 0;
      
      const midAngle = (seg.startAngle + seg.endAngle) / 2;
      const letterX = centerX + (maxRadius * 0.4) * Math.cos(midAngle);
      const letterY = centerY + (maxRadius * 0.4) * Math.sin(midAngle);
      
      ctx.font = 'bold 48px Arial';
      ctx.fillStyle = '#333';
      ctx.fillText(seg.key, letterX, letterY + 15);
      
      ctx.font = 'bold 16px Arial';
      ctx.fillStyle = '#333';
      const labelAngle = (seg.startAngle + seg.endAngle) / 2;
      const labelX = centerX + (maxRadius + 50) * Math.cos(labelAngle);
      const labelY = centerY + (maxRadius + 50) * Math.sin(labelAngle);
      ctx.fillText(seg.label, labelX, labelY);
      
      ctx.font = 'bold 16px Arial';
      ctx.fillStyle = colors[seg.key];
      const pctX = centerX + (maxRadius * 0.65) * Math.cos(midAngle);
      const pctY = centerY + (maxRadius * 0.65) * Math.sin(midAngle);
      ctx.fillText(Math.round(percent) + '%', pctX, pctY + 5);
    });
    
    return canvas;
  }

  function getMBTIDescription(type) {
    const descriptions = {
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
    return descriptions[type] || 'A unique personality type!';
  }

  function getDISCName(letter) {
    const names = {
      'D': 'Dominance',
      'I': 'Influence',
      'S': 'Steadiness',
      'C': 'Conscientiousness'
    };
    return names[letter] || letter;
  }

  function showLoading(message) {
    const overlay = el("div", "rag-loading-overlay");
    const spinner = el("div", "rag-spinner");
    const text = el("div", "rag-loading-text", message || "Loading...");
    overlay.appendChild(spinner);
    overlay.appendChild(text);
    document.body.appendChild(overlay);
  }

  function hideLoading() {
    const overlay = document.querySelector(".rag-loading-overlay");
    if (overlay) overlay.remove();
  }

})();