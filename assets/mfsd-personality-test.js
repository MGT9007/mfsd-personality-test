Strip hardcoded light-theme inline styles so the gamer CSS takes full control.
All logic, structure, and family colour references (used for avatars/family accents) stay identical.

──────────────────────────────────────────────────────────────────────
1. renderIntro() — Steve says intro box  (hardcoded yellow)
──────────────────────────────────────────────────────────────────────
FIND:
      ib.style.cssText = "background:#fff8e6;border:1px solid #ffd966;border-left:4px solid #f0ad4e;padding:12px 14px;border-radius:6px;line-height:1.6;margin:12px 0;font-size:14px;color:#333;";

REPLACE WITH:
      ib.style.cssText = "";


──────────────────────────────────────────────────────────────────────
2. renderIntro() — total questions info box  (hardcoded blue bg)
──────────────────────────────────────────────────────────────────────
FIND:
      info.style.cssText = "margin:12px 0;padding:10px;background:#f0f8ff;border-radius:6px;";

REPLACE WITH:
      info.style.cssText = "margin:12px 0;";


──────────────────────────────────────────────────────────────────────
3. renderFamilies() — steveBox inline styles  (yellow bg)
──────────────────────────────────────────────────────────────────────
FIND:
    steveBox.style.cssText = "background:#fff8e6;border:1px solid #ffd966;border-left:4px solid #f0ad4e;padding:16px;border-radius:8px;line-height:1.7;margin:20px 0;font-size:14px;color:#333;white-space:normal;";

REPLACE WITH:
    steveBox.style.cssText = "";


──────────────────────────────────────────────────────────────────────
4. renderFamilies() — steve intro paragraph  (hardcoded dark text)
──────────────────────────────────────────────────────────────────────
FIND:
    intro.style.cssText = "margin-bottom:14px;";

REPLACE WITH:
    intro.style.cssText = "margin-bottom:14px; font-family:var(--font-body);";


──────────────────────────────────────────────────────────────────────
5. renderFamilies() — Start Test button  (hardcoded black button)
──────────────────────────────────────────────────────────────────────
FIND:
    btn.style.cssText = "border:1px solid #111;background:#111;color:#fff;border-radius:10px;padding:10px 20px;cursor:pointer;font-size:15px;font-weight:600;transition:background 0.2s;";

REPLACE WITH:
    btn.style.cssText = "";


──────────────────────────────────────────────────────────────────────
6. renderQuestion() — progress label  (hardcoded grey)
──────────────────────────────────────────────────────────────────────
FIND:
    pl.style.cssText = "font-size:14px;color:#666;margin-bottom:8px;text-align:center;";

REPLACE WITH:
    pl.style.cssText = "margin-bottom:8px;text-align:center;";


──────────────────────────────────────────────────────────────────────
7. renderQuestion() — progress outer track  (hardcoded light grey)
──────────────────────────────────────────────────────────────────────
FIND:
    outer.style.cssText = "width:100%;height:24px;background:#e0e0e0;border-radius:12px;overflow:hidden;position:relative;";

REPLACE WITH:
    outer.style.cssText = "width:100%;height:24px;border-radius:12px;overflow:hidden;position:relative;background:rgba(0,212,255,0.08);";


──────────────────────────────────────────────────────────────────────
8. renderQuestion() — progress fill  (green gradient → cyan)
──────────────────────────────────────────────────────────────────────
FIND:
    inner.style.cssText = "height:100%;background:linear-gradient(90deg,#4caf50,#8bc34a);border-radius:12px;width:" + pct + "%;transition:width 0.3s ease;";

REPLACE WITH:
    inner.style.cssText = "height:100%;background:linear-gradient(90deg,#00D4FF,#33DDFF);box-shadow:0 0 8px rgba(0,212,255,0.4);border-radius:12px;width:" + pct + "%;transition:width 0.3s ease;";


──────────────────────────────────────────────────────────────────────
9. renderQuestion() — progress percentage text  (hardcoded dark)
──────────────────────────────────────────────────────────────────────
FIND:
    pt.style.cssText = "position:absolute;top:0;left:0;right:0;bottom:0;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:12px;color:#333;";

REPLACE WITH:
    pt.style.cssText = "position:absolute;top:0;left:0;right:0;bottom:0;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:11px;color:#0A0E1A;font-family:var(--font-heading);letter-spacing:0.04em;";


──────────────────────────────────────────────────────────────────────
10. renderSummary() — personality reveal header  (light tinted bg)
    NOTE: Keep the JS familyColor border and tint — that's intentional
    branding per family. Just ensure the inner text elements read correctly.
──────────────────────────────────────────────────────────────────────
FIND:
        const nl = el("div",""); nl.style.cssText = "font-size:28px;font-weight:700;color:#2c3e50;margin-bottom:8px;";

REPLACE WITH:
        const nl = el("div",""); nl.style.cssText = "font-size:28px;font-weight:700;margin-bottom:8px;";


──────────────────────────────────────────────────────────────────────
11. renderSummary() — personality description  (hardcoded grey)
──────────────────────────────────────────────────────────────────────
FIND:
        const dl = el("div",""); dl.style.cssText = "font-size:15px;color:#555;line-height:1.5;max-width:600px;margin:0 auto;";

REPLACE WITH:
        const dl = el("div",""); dl.style.cssText = "font-size:15px;line-height:1.5;max-width:600px;margin:0 auto;";


──────────────────────────────────────────────────────────────────────
12. renderSummary() — family description  (hardcoded grey)
──────────────────────────────────────────────────────────────────────
FIND:
        const fd = el("div",""); fd.style.cssText = "font-size:13px;color:#777;margin-top:12px;line-height:1.5;";

REPLACE WITH:
        const fd = el("div",""); fd.style.cssText = "font-size:13px;margin-top:12px;line-height:1.5;";


──────────────────────────────────────────────────────────────────────
13. renderSummary() — "View My Summary" button  (hardcoded black)
──────────────────────────────────────────────────────────────────────
FIND:
      btn.style.cssText = "border:1px solid #111;background:#111;color:#fff;border-radius:10px;padding:12px 24px;cursor:pointer;font-size:16px;font-weight:600;transition:background 0.2s;display:block;margin:20px auto 0;";

REPLACE WITH:
      btn.style.cssText = "display:block;margin:20px auto 0;";


──────────────────────────────────────────────────────────────────────
14. renderSummaryDetail() — mini header border  (hardcoded light grey)
──────────────────────────────────────────────────────────────────────
FIND:
      mini.style.cssText = "display:flex;align-items:center;gap:12px;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #e0e0e0;";

REPLACE WITH:
      mini.style.cssText = "display:flex;align-items:center;gap:12px;margin-bottom:16px;padding-bottom:16px;";


──────────────────────────────────────────────────────────────────────
15. renderSummaryDetail() — mini personality name  (hardcoded dark)
──────────────────────────────────────────────────────────────────────
FIND:
      const mt1 = el("div",""); mt1.style.cssText = "font-size:18px;font-weight:700;color:#2c3e50;";

REPLACE WITH:
      const mt1 = el("div",""); mt1.style.cssText = "font-size:18px;font-weight:700;";


──────────────────────────────────────────────────────────────────────
16. renderSummaryDetail() — AI summary tab panel  (hardcoded light bg)
──────────────────────────────────────────────────────────────────────
FIND:
          pc2.style.cssText = "background:#f9fafc;border:1px dashed #cbd5e1;padding:18px;border-radius:8px;white-space:pre-line;line-height:1.7;";

REPLACE WITH:
          pc2.style.cssText = "white-space:pre-line;line-height:1.7;";


──────────────────────────────────────────────────────────────────────
17. renderSummaryDetail() — DISC section wrapper  (hardcoded light grey bg)
──────────────────────────────────────────────────────────────────────
FIND:
      const ds = el("div","ptest-disc-section"); ds.style.cssText = "margin:20px 0;padding:20px;background:#f8f9fa;border-radius:8px;";

REPLACE WITH:
      const ds = el("div","ptest-disc-section"); ds.style.cssText = "margin:20px 0;padding:20px;";


──────────────────────────────────────────────────────────────────────
18. renderSummaryDetail() — DISC section heading  (hardcoded dark)
──────────────────────────────────────────────────────────────────────
FIND:
      dt.style.cssText = "margin:0 0 16px;color:#2c3e50;font-size:20px;text-align:center;";

REPLACE WITH:
      dt.style.cssText = "margin:0 0 16px;font-size:20px;text-align:center;";


──────────────────────────────────────────────────────────────────────
19. renderSummaryDetail() — chatbot prompt box  (yellow bg)
──────────────────────────────────────────────────────────────────────
FIND:
      cp.style.cssText = "margin:20px 0 10px;padding:15px;background:#fff9e6;border-left:4px solid #ffc107;border-radius:4px;";

REPLACE WITH:
      cp.style.cssText = "margin:20px 0 10px;";


──────────────────────────────────────────────────────────────────────
20. renderSummaryDetail() — chatbot prompt text  (hardcoded dark)
──────────────────────────────────────────────────────────────────────
FIND:
      cpt.style.cssText = "margin:0;color:#856404;font-weight:600;";

REPLACE WITH:
      cpt.style.cssText = "margin:0;font-weight:600;";