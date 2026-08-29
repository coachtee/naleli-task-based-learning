#!/usr/bin/env python3
"""Generate the 10 new artboards for the Naleli Workspace canvas.

They share one style block (each .dc.html is standalone, so it repeats),
which is why this is generated rather than hand-written ten times: the
tokens below are lifted from the app's ui/theme/Color.kt so the mockup and
the shipping build cannot drift.
"""

import pathlib

HERE = pathlib.Path(__file__).resolve().parent

# Exact values from app/src/main/java/com/naleli/tbl/ui/theme/Color.kt
STYLE = """
    @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap');
    :root{
      --navy:#0A1140; --navy-raised:#141C55;
      --orange:#F05A00; --orange-dark:#C24700; --orange-tint:#FFEDE3;
      --canvas:#F8FAFC; --surface:#FFFFFF;
      --border:#E2E8F0; --slate:#64748B; --slate-surface:#F1F5F9;
      --green:#059669; --red:#DC2626; --purple:#7C3AED;
      --on-navy:#FFFFFF; --on-navy-soft:#B8BFCC;
    }
    *{box-sizing:border-box; margin:0; padding:0;}
    body{background:transparent;}
    .frame{
      width:390px; height:844px; border-radius:32px;
      border:1px solid var(--border); overflow:hidden; position:relative;
      font-family:'IBM Plex Sans',sans-serif;
      display:flex; flex-direction:column;
    }
    .frame-light{background:var(--canvas); color:var(--navy);}
    .frame-navy{background:var(--navy); color:var(--on-navy);}
    .scroll{flex:1; overflow:hidden; display:flex; flex-direction:column;}
    .t-display{font-family:'Plus Jakarta Sans',sans-serif; font-size:26px; font-weight:600; line-height:1.22;}
    .t-heading{font-family:'Plus Jakarta Sans',sans-serif; font-size:19px; font-weight:600; line-height:1.3;}
    .t-title{font-family:'Plus Jakarta Sans',sans-serif; font-size:15px; font-weight:600; line-height:1.35;}
    .t-body{font-size:14px; font-weight:400; line-height:1.62;}
    .t-read{font-size:14.5px; font-weight:400; line-height:1.72;}
    .t-label{font-size:11px; font-weight:600; letter-spacing:0.08em; text-transform:uppercase;}
    .t-small{font-size:12px; font-weight:400; line-height:1.5;}
    .btn{font-family:'Plus Jakarta Sans',sans-serif; font-size:13px; font-weight:600;
         letter-spacing:0.04em; border:none; border-radius:12px; padding:16px 20px;
         display:flex; align-items:center; justify-content:center; gap:8px; width:100%;}
    .btn-orange{background:var(--orange); color:#fff;}
    .card{background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:18px;}
    .dot{width:6px; height:6px; border-radius:50%; background:var(--orange); flex-shrink:0; margin-top:8px;}
"""

TEMPLATE = """<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <script src="./support.js"></script>
</head>
<body>
<x-dc>
<helmet>
  <style>{style}</style>
</helmet>
{markup}
</x-dc>
</body>
</html>
"""


def write(name: str, markup: str) -> None:
    (HERE / f"{name}.dc.html").write_text(
        TEMPLATE.format(style=STYLE, markup=markup.strip() + "\n")
    )


def steps_bar(active: int, total: int = 6) -> str:
    """The orientation's progress rail — filled through the current step."""
    segs = "".join(
        f'<div style="flex:1; height:3px; border-radius:2px; '
        f'background:{"var(--orange)" if i <= active else "rgba(255,255,255,0.22)"};"></div>'
        for i in range(total)
    )
    return f'<div style="display:flex; gap:6px; width:100%;">{segs}</div>'


def bullets(items, ink="var(--on-navy)", size="t-body") -> str:
    rows = "".join(
        f'<div style="display:flex; gap:12px; align-items:flex-start;">'
        f'<div class="dot"></div>'
        f'<div class="{size}" style="color:{ink};">{text}</div>'
        f"</div>"
        for text in items
    )
    return f'<div style="display:flex; flex-direction:column; gap:14px;">{rows}</div>'


def orientation(name: str, step: int, eyebrow: str, title: str, body: str, middle: str, closing: str, cta: str) -> None:
    write(
        name,
        f"""
<div class="frame frame-navy">
  <div style="padding:22px 24px 0; display:flex; flex-direction:column; gap:14px;">
    <div style="display:flex; justify-content:space-between; align-items:center;">
      <div class="t-small" style="color:var(--on-navy-soft);">{step + 1} of 6</div>
      {'<div class="t-small" style="color:var(--on-navy-soft);">Skip</div>' if step < 5 else ''}
    </div>
    {steps_bar(step)}
  </div>

  <div class="scroll" style="padding:34px 24px 0; gap:16px;">
    <div class="t-label" style="color:var(--orange);">{eyebrow}</div>
    <div class="t-display" style="color:var(--on-navy);">{title}</div>
    <div class="t-body" style="color:var(--on-navy-soft); font-size:15px;">{body}</div>
    <div style="height:8px;"></div>
    {middle}
    <div style="height:8px;"></div>
    <div class="t-title" style="color:var(--on-navy); font-size:16px; line-height:1.5;">{closing}</div>
  </div>

  <div style="padding:16px 24px 26px;">
    <button class="btn btn-orange">{cta}</button>
  </div>
</div>
""",
    )


# ---------------------------------------------------------------- orientation

orientation(
    "OrientWelcome", 0, "WELCOME",
    "You are not here to finish a course",
    "Naleli Workspace is a place of work, not a playlist. Over the next 90 days you will "
    "build three things at once — what you know, what you can do, and the evidence that "
    "proves it.",
    bullets([
        "Knowledge you can explain in your own words",
        "Practical skill you have actually performed",
        "Evidence a real employer can look at",
    ]),
    "Finishing is not the goal. Being able to do the work is.",
    "CONTINUE",
)

orientation(
    "OrientDigitalOps", 1, "YOUR FIELD",
    "What is Digital Operations?",
    "Every organisation runs on digital tools, information and processes — files that must "
    "be found, data that must be right, systems that must stay secure, messages that must "
    "reach the right person.",
    bullets([
        "<strong style=\"font-weight:600;\">Tools</strong> — the software people work in every day",
        "<strong style=\"font-weight:600;\">Systems</strong> — the devices and networks that connect them",
        "<strong style=\"font-weight:600;\">Information</strong> — the files, records and data an organisation depends on",
        "<strong style=\"font-weight:600;\">Processes</strong> — the steps that keep work consistent",
    ]),
    "Digital Operations is the work of keeping all four running well. It is a profession, "
    "and it is what you are training for.",
    "CONTINUE",
)

FLOW_STEPS = ["Learn", "Practise", "Complete tasks", "Build evidence",
              "Demonstrate competence", "Build professional portfolio"]
flow_rows = []
for i, label in enumerate(FLOW_STEPS):
    flow_rows.append(
        f'<div style="display:flex; align-items:center; gap:12px;">'
        f'<div style="width:8px; height:8px; border-radius:50%; background:var(--on-navy-soft); flex-shrink:0;"></div>'
        f'<div class="t-body" style="color:var(--on-navy); font-size:15px;">{label}</div>'
        f"</div>"
    )
    if i != len(FLOW_STEPS) - 1:
        flow_rows.append(
            '<div style="width:8px; display:flex; justify-content:center;">'
            '<div style="width:2px; height:16px; background:rgba(184,191,204,0.35);"></div>'
            "</div>"
        )
orientation(
    "OrientJourney", 2, "THE JOURNEY",
    "What happens during and after",
    "Each day moves through the same sequence. Nothing is skipped, and each stage produces "
    "something the next one needs.",
    '<div style="display:flex; flex-direction:column; gap:0;">' + "".join(flow_rows) + "</div>",
    "By Day 90 the portfolio is not a certificate. It is a body of work.",
    "CONTINUE",
)

orientation(
    "OrientMethod", 3, "THE METHOD",
    "What is Naleli Task-Based Learning?",
    "You will not only consume content. Every day, you:",
    bullets([
        "Understand a concept",
        "Practise the skill",
        "Complete a realistic workplace-style task",
        "Submit evidence of what you produced",
        "Demonstrate competence against clear criteria",
    ]),
    "The task is not a test at the end of the learning. The task is the learning.",
    "CONTINUE",
)

principle = """
<div style="background:rgba(255,255,255,0.06); border-radius:16px; padding:22px;
            display:flex; flex-direction:column; gap:14px;">
  <div class="t-title" style="color:var(--on-navy); font-size:17px; font-weight:500;">Knowledge tells you what.</div>
  <div class="t-title" style="color:var(--on-navy); font-size:17px; font-weight:500;">Practice helps you understand how.</div>
  <div class="t-title" style="color:var(--orange); font-size:17px; font-weight:600;">Tasks allow you to demonstrate that you can do it.</div>
</div>
"""
orientation(
    "OrientWhy", 4, "THE REASON",
    "Why we learn this way",
    "Watching a video does not make you able to do something. Passing a quiz does not "
    "either. Both can be true while the actual work is still beyond you.",
    principle,
    "So we assess what you produced, not what you watched.",
    "CONTINUE",
)

orientation(
    "OrientPortfolio", 5, "YOUR EVIDENCE",
    "Your portfolio builds itself",
    "Every task you complete and submit becomes part of your professional record. Nothing "
    "is thrown away when a day ends.",
    bullets([
        "Your files stay on your device, under your control",
        "Assessed work is recorded against the skill it demonstrates",
        "Your portfolio grows from real work, never from a score",
    ]),
    "When someone asks what you can do, you will be able to show them.",
    "BEGIN MY LEARNING JOURNEY",
)

# ------------------------------------------------------------------- lesson

LESSON_HEADER = """
  <div style="background:var(--navy); padding:18px 20px 20px; display:flex; flex-direction:column; gap:0;">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" style="margin-bottom:10px;">
      <path d="M15 5l-7 7 7 7" stroke="#FFFFFF" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <div class="t-label" style="color:var(--orange);">LESSON 1B</div>
    <div class="t-display" style="color:var(--on-navy); font-size:23px; margin-top:5px;">Exploring a World of Tech</div>
    <div class="t-small" style="color:var(--on-navy-soft); margin-top:5px;">Module 1 — Introduction to Tech</div>
    <div style="height:6px; border-radius:4px; background:rgba(255,255,255,0.22); margin-top:15px; overflow:hidden;">
      <div style="width:34%; height:100%; background:var(--orange);"></div>
    </div>
    <div class="t-small" style="color:var(--on-navy-soft); margin-top:6px;">34% read</div>
  </div>
"""


def qrow(q: str, a: str) -> str:
    return (
        '<div style="display:flex; flex-direction:column; gap:3px;">'
        f'<div class="t-small" style="color:var(--slate); font-weight:500;">{q}</div>'
        f'<div class="t-body" style="color:var(--navy);">{a}</div>'
        "</div>"
    )


write("LessonReader", f"""
<div class="frame frame-light">
  {LESSON_HEADER}
  <div class="scroll" style="padding:16px 20px 0; gap:14px;">
    <div class="card" style="display:flex; flex-direction:column; gap:12px;">
      <div class="t-label" style="color:var(--orange);">WHY YOU ARE READING THIS</div>
      {qrow("What am I learning?", "Foundations")}
      {qrow("Why does it matter?", "Builds your foundations skill — part of Learn the Role, day 2 of 90.")}
      {qrow("What proves I can do it?", "Workplace-style output + short explanation")}
    </div>

    <div style="background:var(--orange-tint); border:1px solid rgba(240,90,0,0.25);
                border-radius:16px; padding:16px; display:flex; flex-direction:column; gap:10px;">
      <div style="display:flex; align-items:center; gap:8px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9V16h7v-2.1A6 6 0 0 0 12 3Z"
                stroke="#F05A00" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <div class="t-label" style="color:var(--orange);">WHAT YOU SHOULD BE ABLE TO ANSWER</div>
      </div>
      {bullets([
        "How does technology impact my daily life?",
        "How could technology impact my future career?",
        "What is a computer system?",
      ], ink="var(--navy)", size="t-small")}
    </div>
  </div>
</div>
""")

write("LessonBlocks", f"""
<div class="frame frame-light">
  <div style="background:var(--navy); padding:14px 20px;">
    <div class="t-label" style="color:var(--orange);">LESSON 1B</div>
    <div class="t-heading" style="color:var(--on-navy); margin-top:2px;">Exploring a World of Tech</div>
  </div>
  <div class="scroll" style="padding:22px 20px 0; gap:0;">
    <div class="t-heading" style="color:var(--navy); margin-bottom:8px;">Computers</div>
    <div class="t-read" style="color:var(--navy); margin-bottom:16px;">
      All computing devices contain a motherboard, a processor, and storage. One of the main
      differences between the devices we think of as &ldquo;computers&rdquo; as opposed to
      &ldquo;mobile devices&rdquo; is in the connection options.
    </div>

    <div class="t-heading" style="color:var(--navy); margin-bottom:8px;">Desktop Computers</div>
    <div class="t-read" style="color:var(--navy); margin-bottom:16px;">
      Desktops, also called personal computers, have been around for a long time and are a
      common sight in both school and work environments.
    </div>

    <div style="background:var(--slate-surface); border:1px solid rgba(10,17,64,0.18);
                border-radius:16px; padding:16px; margin-bottom:16px;
                display:flex; flex-direction:column; gap:10px;">
      <div style="display:flex; align-items:center; gap:8px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9V16h7v-2.1A6 6 0 0 0 12 3Z"
                stroke="#0A1140" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <div class="t-label" style="color:var(--navy);">OBJECTIVES COVERED</div>
      </div>
      {bullets([
        "1.1 Explain the basics of computing.",
        "2.1 Explain common computing devices and their purposes.",
      ], ink="var(--navy)", size="t-small")}
    </div>

    {bullets([
      "Consumer routers — best for small offices or homes",
      "Enterprise routers — stronger signal over larger areas",
    ], ink="var(--navy)", size="t-read")}
  </div>
</div>
""")

write("LessonMedia", f"""
<div class="frame frame-light">
  <div style="background:var(--navy); padding:14px 20px;">
    <div class="t-label" style="color:var(--orange);">LESSON 1B</div>
    <div class="t-heading" style="color:var(--on-navy); margin-top:2px;">Watch and practise</div>
  </div>
  <div class="scroll" style="padding:22px 20px 0; gap:16px;">

    <div style="background:var(--slate-surface); border:1px solid rgba(10,17,64,0.18);
                border-radius:16px; padding:16px; display:flex; flex-direction:column; gap:10px;">
      <div style="display:flex; align-items:center; gap:8px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="9" stroke="#0A1140" stroke-width="1.6"/>
          <path d="M10 8.5l6 3.5-6 3.5V8.5Z" stroke="#0A1140" stroke-width="1.6" stroke-linejoin="round"/>
        </svg>
        <div class="t-label" style="color:var(--navy);">WATCH</div>
      </div>
      <div class="t-body" style="color:var(--navy);">How a computer turns input into output</div>
      <div class="t-small" style="color:var(--slate);">4 min &middot; plays from your device, no data needed</div>
    </div>

    <div style="background:var(--orange-tint); border:1px solid rgba(240,90,0,0.25);
                border-radius:16px; padding:16px; display:flex; flex-direction:column; gap:10px;">
      <div style="display:flex; align-items:center; gap:8px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
          <rect x="4.5" y="3.5" width="15" height="17" rx="2" stroke="#F05A00" stroke-width="1.6"/>
          <path d="M8.5 9h7M8.5 13h7M8.5 17h4" stroke="#F05A00" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        <div class="t-label" style="color:var(--orange);">PRACTISE THIS</div>
      </div>
      <div class="t-body" style="color:var(--navy);">
        Use your own device to identify each part of a computer system, then label a
        screenshot of what you found.
      </div>
      <div class="t-small" style="color:var(--slate);">Produces: working file, screenshot or recorded result</div>
    </div>

    <div class="card" style="display:flex; flex-direction:column; gap:8px;">
      <div class="t-label" style="color:var(--orange);">PROVE YOU CAN DO IT</div>
      <div class="t-small" style="color:var(--slate);">Answer in your own words, without reopening the lesson.</div>
      {bullets([
        "What is a computer system?",
        "How does technology impact my daily life?",
      ], ink="var(--navy)", size="t-small")}
    </div>
  </div>
</div>
""")

flow5 = []
FIVE = ["Understand", "Practise", "Complete task", "Submit evidence", "Assessment"]
for i, label in enumerate(FIVE):
    active = i == 0
    flow5.append(
        f'<div style="display:flex; align-items:center; gap:11px;">'
        f'<div style="width:{"11" if active else "8"}px; height:{"11" if active else "8"}px; '
        f'border-radius:50%; background:{"var(--orange)" if active else "var(--on-navy-soft)"}; flex-shrink:0;"></div>'
        f'<div class="t-body" style="color:{"var(--orange)" if active else "var(--on-navy-soft)"}; '
        f'font-weight:{"600" if active else "400"};">{label}</div>'
        f"</div>"
    )
    if i != len(FIVE) - 1:
        flow5.append(
            '<div style="width:11px; display:flex; justify-content:center;">'
            '<div style="width:2px; height:13px; background:rgba(184,191,204,0.35);"></div></div>'
        )

write("TaskPrep", f"""
<div class="frame frame-light">
  <div style="background:var(--navy); padding:14px 20px;">
    <div class="t-label" style="color:var(--orange);">LESSON 1B</div>
    <div class="t-heading" style="color:var(--on-navy); margin-top:2px;">End of the reading</div>
  </div>
  <div class="scroll" style="padding:20px 20px 0; gap:14px;">

    <div style="background:var(--navy); border-radius:20px; padding:20px;
                display:flex; flex-direction:column; gap:14px;">
      <div class="t-label" style="color:var(--orange);">NOW PUT IT TO WORK</div>
      <div class="t-body" style="color:var(--on-navy-soft);">
        Reading tells you what. The work is how you show you can do it.
      </div>
      <div style="display:flex; flex-direction:column; gap:0;">{''.join(flow5)}</div>
      <button class="btn btn-orange" style="margin-top:4px;">START THE WORK</button>
    </div>

    <div class="t-small" style="color:var(--slate);">
      You will finish today with: Workplace-style output + short explanation
    </div>

    <div class="card" style="display:flex; align-items:center; gap:12px;">
      <div style="flex:1; display:flex; flex-direction:column; gap:3px;">
        <div class="t-label" style="color:var(--orange);">START HERE — THE LESSON</div>
        <div class="t-title" style="color:var(--navy);">Exploring a World of Tech</div>
        <div class="t-small" style="color:var(--slate);">Read this first, then work through the steps below.</div>
      </div>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
        <path d="M9 5l7 7-7 7" stroke="#64748B" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </div>
    <div class="t-small" style="color:var(--slate); text-align:center;">
      How the lesson is reached from the Task Workspace
    </div>
  </div>
</div>
""")

print("wrote 10 artboards:")
for f in sorted(HERE.glob("Orient*.dc.html")) + sorted(HERE.glob("Lesson*.dc.html")) + [HERE / "TaskPrep.dc.html"]:
    print(" ", f.name, f"{f.stat().st_size // 1024} KB")
