#!/usr/bin/env python3
"""
Convert the extracted Digital Literacy course export into the structured
lesson-content package the app reads.

    source/digital-literacy-v3-extract.json
            |
            |  this script  (the only thing that writes the output)
            v
    content/digital-foundation/lessons.json  ->  data/content/LessonLibrary.kt

The export carries one raw text blob per PDF page. The app must never
render a wall of extracted text, so this script parses each page into the
typed content blocks the reading screen knows how to lay out — headings,
paragraphs, learning outcomes, callouts, lists.

Never hand-edit content/digital-foundation/lessons.json. Re-run this.

Lessons join to the 90-day curriculum by lesson code: the workbook titles
its days "Lesson 1A - Starting the Course", and build_workspace_content.py
writes those codes onto each task as `lessonCodes`.
"""

from __future__ import annotations

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
EXPORT = ROOT / "source" / "digital-literacy-v3-extract.json"
OUT = ROOT / "content" / "digital-foundation" / "lessons.json"

# Lines the PDF repeats as image credits and running heads. They carry no
# learning content and would render as stray sentences mid-lesson.
NOISE = re.compile(
    r"^(images?\s+©|screenshot\s+courtesy|used with permission|adapted (from|with)|"
    r"photo\s+©|source:\s*comptia)",
    re.I,
)
RUNNING_HEAD = re.compile(r"^(lesson\s+\d+[A-Z]|module\s+\d+)$", re.I)

# "1.1 Explain the basics of computing." - the certification objectives the
# lesson covers, listed under an "Objectives Covered" heading.
OBJECTIVE = re.compile(r"^\d+\.\d+\s+\S")

BULLET = re.compile(r"^\s*([•▪*]|o\s)\s*")
EXAMPLE_CUE = re.compile(r"^(for example|for instance|imagine)\b", re.I)
SECTION_MARKERS = {"objectives covered", "learning outcomes", "module overview", "module summary"}

# A heading ends a paragraph and starts a section. Kept deliberately strict:
# a false positive chops a sentence in half, which is worse on the page than
# a missed heading (that just reads as another paragraph).
MAX_HEADING_WORDS = 9
MAX_HEADING_CHARS = 60


def clean(text: str) -> str:
    return re.sub(r"\s+", " ", (text or "").replace(" ", " ")).strip()


def looks_like_heading(line: str, paragraph_open: bool) -> bool:
    """A short, unpunctuated, capitalised line that is not mid-sentence."""
    if paragraph_open:
        # Never break a paragraph that has not reached a full stop - a
        # wrapped line is not a heading just because it happens to be short.
        return False
    if not (2 <= len(line) <= MAX_HEADING_CHARS):
        return False
    if line.endswith("?"):
        # This textbook heads sections with questions ("What is Input?").
        # Narrow enough not to catch outcome questions, which are bulleted.
        return len(line.split()) <= 6 and line.split()[0].lower() in {
            "what", "why", "how", "when", "who", "where",
        }
    if line[-1] in ".,:;!":
        return False
    if not line[0].isupper():
        return False
    if len(line.split()) > MAX_HEADING_WORDS:
        return False
    # Table rows survive extraction as "Term Description" style pairs with
    # wide gaps; they are not section headings.
    if "  " in line:
        return False
    if re.search(r"\d{3,}", line):
        return False
    return True


def parse_page(text: str, skip_title: str | None) -> list[dict]:
    """One page of extracted text -> typed content blocks."""
    blocks: list[dict] = []
    paragraph: list[str] = []
    bullets: list[str] = []
    pending_objectives: list[str] = []
    mode = "body"

    def flush_paragraph():
        nonlocal paragraph
        if paragraph:
            blocks.append({"type": "paragraph", "text": clean(" ".join(paragraph))})
            paragraph = []

    def flush_bullets():
        nonlocal bullets
        if bullets:
            blocks.append({"type": "list", "items": [clean(b) for b in bullets]})
            bullets = []

    def flush_objectives():
        nonlocal pending_objectives
        if pending_objectives:
            blocks.append(
                {
                    "type": "keyConcept",
                    "title": "Objectives covered",
                    "items": [clean(o) for o in pending_objectives],
                }
            )
            pending_objectives = []

    for raw in text.split("\n"):
        line = clean(raw)

        if not line:
            flush_paragraph()
            flush_bullets()
            continue
        if NOISE.match(line) or RUNNING_HEAD.match(line):
            continue
        if skip_title and line.lower() == skip_title.lower():
            continue

        marker = line.lower().rstrip(":")
        if marker in SECTION_MARKERS:
            flush_paragraph()
            flush_bullets()
            flush_objectives()
            mode = {
                "objectives covered": "objectives",
                "learning outcomes": "outcomes",
            }.get(marker, "body")
            if mode == "body":
                blocks.append({"type": "heading", "text": line})
            continue

        if mode == "objectives":
            if OBJECTIVE.match(line):
                pending_objectives.append(line)
                continue
            flush_objectives()
            mode = "body"

        if mode == "outcomes":
            # The lesson's own learning_outcomes field is authoritative and
            # is emitted separately, so the page's repeat of it is dropped.
            # Questions wrap across lines, so stay in this mode until a line
            # that genuinely opens the body - dropping only the bullets left
            # their tails ("network?") stranded as body paragraphs.
            if (
                BULLET.match(line)
                or line.lower().startswith("as you study")
                or line.endswith("?")
                or line[0].islower()
            ):
                continue
            mode = "body"

        if BULLET.match(line):
            flush_paragraph()
            bullets.append(BULLET.sub("", line))
            continue

        # A lower-case line directly under a bullet is that bullet's wrapped
        # tail. Flushing the run here instead stranded 610 sentence-halves
        # as their own paragraphs.
        if bullets and not paragraph and line[:1].islower():
            bullets[-1] = clean(f"{bullets[-1]} {line}")
            continue

        flush_bullets()
        if looks_like_heading(line, paragraph_open=bool(paragraph)):
            blocks.append({"type": "heading", "text": line})
            continue

        paragraph.append(line)
        # A line ending a sentence closes the paragraph, which is what lets
        # the next short line be recognised as a heading.
        if line[-1] in ".!?":
            flush_paragraph()

    flush_paragraph()
    flush_bullets()
    flush_objectives()
    return blocks


def outcome_items(learning_outcomes: str | None) -> list[str]:
    """The lesson's own outcome questions, already bulleted in the export."""
    if not learning_outcomes:
        return []
    items = []
    for raw in learning_outcomes.split("\n"):
        line = clean(raw)
        if not line or line.lower().startswith("as you study"):
            continue
        items.append(BULLET.sub("", line))
    return items


def repair_fragments(blocks: list[dict]) -> list[dict]:
    """Undo the two ways PDF line-breaking strands half a sentence.

    A paragraph that does not reach a full stop is a wrapped line, so it
    absorbs the block after it. And a paragraph starting lower-case is the
    tail of the sentence above it - which means the "heading" before it was
    never a heading, just a short wrapped line, and the two belong together.
    """
    merged: list[dict] = []
    for block in blocks:
        prev = merged[-1] if merged else None
        if prev and block["type"] == "paragraph" and block["text"][:1].islower():
            if prev["type"] in ("paragraph", "heading"):
                text = prev.get("text", "")
                prev["type"] = "paragraph"
                prev["text"] = clean(f"{text} {block['text']}")
                prev.pop("items", None)
                continue
        if (
            prev
            and prev["type"] == "paragraph"
            and block["type"] == "paragraph"
            and not prev["text"].endswith((".", "!", "?", ":", '"'))
        ):
            prev["text"] = clean(f"{prev['text']} {block['text']}")
            continue
        merged.append(block)
    return promote_examples(merged)


def promote_examples(blocks: list[dict]) -> list[dict]:
    """The book introduces worked examples with "For example" / "Imagine".
    Those earn the example block type rather than reading as another
    paragraph, and a lead-in ending in a colon takes the list under it."""
    out: list[dict] = []
    take_next_list = False
    for block in blocks:
        # Table cells survive extraction as stray paragraphs of pure
        # punctuation or digits ("127", ">= 95"). They carry no prose.
        if block["type"] == "paragraph" and not any(c.isalpha() for c in block["text"]):
            continue
        if take_next_list and block["type"] == "list":
            out[-1]["items"] = block["items"]
            take_next_list = False
            continue
        take_next_list = False
        if block["type"] == "paragraph" and EXAMPLE_CUE.match(block["text"]):
            out.append({"type": "example", "title": "Example", "text": block["text"]})
            take_next_list = block["text"].endswith(":")
            continue
        out.append(block)
    return out


# A screen holds one idea, measured in reading length rather than block
# count: two short paragraphs and one long one are very different screens.
# ~820 characters is about a comfortable phone screenful at our body size.
MAX_PAGE_CHARS = 820
MIN_PAGE_CHARS = 220


def block_chars(block: dict) -> int:
    return len(block.get("text", "")) + sum(len(i) for i in block.get("items", []))


def paginate(blocks: list[dict], lesson_code: str) -> list[dict]:
    """Flat blocks -> one-idea screens.

    A heading starts a new screen and names it; the blocks under it are that
    idea. A heading whose content runs past a screenful is continued onto
    another screen under the same title, and a screen too short to stand on
    its own is folded back into the one before it — otherwise a long lesson
    turns into sixty taps of Continue.
    """
    pages: list[dict] = []
    current: dict | None = None

    def start(title: str, stage: str = "understand"):
        nonlocal current
        current = {"pageId": "", "stage": stage, "title": title, "blocks": []}
        pages.append(current)

    for block in blocks:
        kind = block["type"]

        if kind == "heading":
            start(block["text"])
            continue

        # A worked example is the SEE stage: the learner is shown the idea
        # working, not told it again.
        if kind == "example":
            start(current["title"] if current else "In practice", stage="see")
            current["blocks"].append(block)
            current = None  # an example stands alone on its screen
            continue

        if current is None:
            start("In this lesson" if not pages else "")

        used = sum(block_chars(b) for b in current["blocks"])
        if used >= MAX_PAGE_CHARS and kind in ("paragraph", "list"):
            start(current["title"], stage=current["stage"])

        current["blocks"].append(block)

    # Fold a screen too short to stand alone back into the previous one,
    # as long as the result still fits a screenful.
    merged: list[dict] = []
    for page in pages:
        if not page["blocks"]:
            continue
        size = sum(block_chars(b) for b in page["blocks"])
        if merged and size < MIN_PAGE_CHARS:
            prev = merged[-1]
            if (
                prev["stage"] == page["stage"]
                and sum(block_chars(b) for b in prev["blocks"]) + size <= MAX_PAGE_CHARS
            ):
                prev["blocks"].extend(page["blocks"])
                continue
        merged.append(page)

    for i, page in enumerate(merged, start=1):
        page["pageId"] = f"{lesson_code}-p{i}"
    return merged


def summarise(blocks: list[dict]) -> str:
    """One sentence for the lesson's landing page — the first real sentence
    of the lesson's own opening, never invented here."""
    for block in blocks:
        if block["type"] == "paragraph":
            text = block["text"]
            stop = text.find(". ")
            return (text[: stop + 1] if stop > 40 else text).strip()
    return ""


# ---------------------------------------------------------------------------
# Try and Apply
#
# IMPORTANT — what is authored here versus derived from the course.
#
# 79 of the 90 workbook days carry the identical assignment sentence
# ("Imagine you are doing this for a real workplace user..."), so a concrete
# task cannot be read out of the source. Two things fill that gap:
#
#   DERIVED  - the numbered steps and success criteria, built from each
#              lesson's own learning-outcome questions. Those are real,
#              per-lesson, and written by the course author.
#   AUTHORED - the twenty workplace situations below, one per module, and
#              the practice framings. These are Naleli's framing, not the
#              source course's, and are the first thing to replace when a
#              subject lead writes real briefs.
#
# Both are overridable: hand-authored `practice` / `mission` objects placed
# on a lesson in the export win over anything generated here.
# ---------------------------------------------------------------------------

MODULE_SITUATIONS = {
    1: "You have just started at a small business that is putting its paperwork onto computers for the first time. The owner asks you to explain how the office equipment actually works.",
    2: "The office has hired someone who types with two fingers and is falling behind on capturing customer forms. You have been asked to help them work faster and more accurately.",
    3: "A colleague posted something about a customer on social media. The manager asks you to explain what staff may and may not put online.",
    4: "A manager needs reliable information for a decision by the end of the day and does not know which sources to trust.",
    5: "A staff member clicked a link in a suspicious email. The office needs to know what to do now and how to avoid it next time.",
    6: "Your team wants to use an AI assistant for routine writing, but nobody is sure what it may safely be used for.",
    7: "A shared office computer has become slow and disorganised, and nobody can find last month's files.",
    8: "The office is choosing software for a task it currently does by hand, and needs a recommendation it can afford.",
    9: "A customer has asked for a formal letter, and the draft that exists is untidy and hard to read.",
    10: "The owner keeps daily sales on paper and wants to know the totals without adding them up by hand each week.",
    11: "Your team must present its work to the owner next week and has only rough notes.",
    12: "Messages to customers are being missed because everyone uses a different app and nothing is written down.",
    13: "A computer in the office has stopped working and the owner wants to know whether to repair or replace it.",
    14: "Staff at one end of the building keep losing the network connection, and nobody knows why.",
    15: "The office loses work whenever a computer fails, and the owner has heard that files can be kept online.",
    16: "The business keeps customer records and needs to show it is protecting them properly.",
    17: "Customer details are spread across several spreadsheets, and the same customer appears three times with different spellings.",
    18: "A task in the office is repeated by hand every day, and someone has suggested it could be automated.",
    19: "The owner wants to improve one part of how the business runs but has no plan for doing it.",
    20: "You are preparing the evidence of your own capability for a real employer.",
}

MODULE_PRACTICE = {
    1: ("your own phone or computer", "identify one part of the device and what it does",
        "I looked at my phone and found the screen. It is an output part, because it shows me what the phone has done."),
    2: ("a keyboard", "type a short paragraph without looking down",
        "I typed a paragraph without looking at my hands. I made four mistakes, mostly on the letters furthest from home row."),
    3: ("your own social media or messaging app", "check what a stranger can see about you",
        "I opened my profile while logged out. A stranger can see my full name and my photo, so I changed my photo to friends only."),
    4: ("a web browser", "search for one fact and check it against a second source",
        "I searched for the population of Katlehong and found two different numbers. I used the one from the official statistics site."),
    5: ("your own device settings", "check one security setting and improve it",
        "I checked my screen lock and found it was off. I turned on a PIN so nobody can open my phone if I lose it."),
    6: ("an AI chat tool, or paper if you have none", "write one clear instruction and judge the answer",
        "I asked for a short reply to a customer complaint. The first answer was too formal, so I asked again for simpler language."),
    7: ("your own device's file manager", "create a folder and save a file into it with a clear name",
        "I made a folder called Invoices and saved a file as 2026-03-invoice-mokoena. I can now find it without opening it."),
    8: ("your own device", "find one app you use and describe what job it does",
        "I use WhatsApp to send customers their order updates. Its job is communication, not storing records."),
    9: ("a word processor, or paper if you have none", "format a short piece of text so it reads professionally",
        "I put the heading in bold, left one blank line between paragraphs, and used the same font throughout."),
    10: ("a spreadsheet, or paper if you have none", "enter five numbers and calculate the total",
        "I entered five daily sales figures and used SUM to total them. The total updated by itself when I corrected one number."),
    11: ("presentation software, or paper if you have none", "lay out one slide with a title and three points",
        "I made a slide titled Weekly Sales with three points. I kept each point under eight words so it can be read from the back."),
    12: ("your email or messaging app", "write one clear, professional message",
        "I wrote to a customer with a clear subject line, one request, and a deadline. I read it once before sending."),
    13: ("your own device, or a picture of one", "identify one internal component and say what it does",
        "I found the RAM in a photo of an open laptop. It holds what the computer is working on right now."),
    14: ("your own device's network settings", "find the network you are connected to and note its details",
        "I am connected to a 2.4 GHz Wi-Fi network. My phone shows a full signal in the front room and one bar in the back."),
    15: ("any cloud storage you have", "save one file online and open it again",
        "I saved a document to Drive on my phone and opened it on a computer. The same file was there without emailing it."),
    16: ("your own device", "check one security setting and record what you found",
        "I checked which apps can use my location. Three did not need it, so I switched them off."),
    17: ("a spreadsheet or a written list", "organise five records so each field is separate",
        "I split one Name column into First Name and Surname. Now I can sort by surname without retyping anything."),
    18: ("paper or any coding tool you have", "write the steps of one everyday task in order",
        "I wrote out making tea in seven steps. Two of them repeat, which is where a loop would go."),
    19: ("paper", "write down one problem and one possible improvement",
        "Customers wait too long for quotes. A saved template would cut the writing time for each quote."),
    20: ("your own saved work", "choose one piece of work and say what it proves",
        "I chose my spreadsheet of monthly sales. It proves I can enter data accurately and calculate totals."),
}


def question_to_step(question: str) -> str:
    """An outcome question, restated as something to do rather than recall."""
    q = question.strip().rstrip("?")
    return f"Answer, for your situation: {q}?"


def build_practice(module_number: int, lesson_title: str) -> dict:
    tool, action, example = MODULE_PRACTICE.get(
        module_number,
        ("your own device", "try the skill once", "I tried it once and wrote down what happened."),
    )
    return {
        "goal": f"Practise {lesson_title.lower()} once, with guidance.",
        "steps": [
            f"Open {tool}.",
            f"Now {action}.",
            "Do it once yourself — do not only read about it.",
            "Write one or two sentences saying what you did and what happened.",
        ],
        "exampleAnswer": example,
    }


def build_mission(module_number: int, lesson_title: str, outcomes: list[str]) -> dict:
    # The steps come from the lesson's own outcome questions, so the task is
    # about this lesson and no other. Capped so the mission stays a day's
    # work rather than an exam.
    focus = [question_to_step(q) for q in outcomes[:4]]
    steps = (
        ["Describe the situation above in one or two sentences, in your own words."]
        + focus
        + ["Say what you decided to do, and why."]
    )
    return {
        "situation": MODULE_SITUATIONS.get(module_number, MODULE_SITUATIONS[1]),
        "steps": [f"{i}. {t}" for i, t in enumerate(steps, start=1)],
        "successCriteria": [
            f"Every one of the {len(steps)} points above is answered.",
            "It is written about this situation, not copied from the lesson.",
            "Someone who did not attend could follow your explanation.",
        ],
        # The day's real deliverable is prepended by the app, which has it
        # from the workbook; naming it here would only ever be a guess.
        "submit": [
            "Three to five sentences explaining the decisions you made",
            "A file, screenshot or photo showing you did the work",
        ],
    }


def build():
    export = json.loads(EXPORT.read_text())
    pages = {p["page"]: p["text"] for p in export["pages"]}
    course = export["course"]

    lessons = []
    for module in course["modules"]:
        for lesson in module.get("lessons") or []:
            blocks: list[dict] = []

            outcomes = outcome_items(lesson.get("learning_outcomes"))
            if outcomes:
                blocks.append(
                    {
                        "type": "learningOutcomes",
                        "title": "What you should be able to answer",
                        "items": outcomes,
                    }
                )

            for page_number in lesson.get("content_pages") or []:
                page_text = pages.get(page_number)
                if not page_text:
                    continue
                blocks.extend(parse_page(page_text, skip_title=lesson.get("title")))

            # Media the export carries but the source PDF did not: kept as
            # real block types so a lesson gains a video by adding data,
            # never by changing Kotlin.
            media = lesson.get("media") or {}
            for video in media.get("videos") or []:
                blocks.append(
                    {
                        "type": "video",
                        "title": video.get("title") or "Watch",
                        "url": video.get("url") or "",
                        "caption": video.get("caption") or "",
                    }
                )
            for image in media.get("images") or []:
                blocks.append(
                    {
                        "type": "image",
                        "url": image.get("url") or "",
                        "caption": image.get("caption") or "",
                    }
                )

            blocks = repair_fragments(blocks)

            lessons.append(
                {
                    "lessonCode": lesson["lesson_code"],
                    "moduleNumber": module["module_number"],
                    "moduleTitle": module["title"],
                    "title": lesson["title"],
                    "summary": summarise(blocks),
                    "practice": lesson.get("practice")
                    or build_practice(module["module_number"], lesson["title"]),
                    "mission": lesson.get("mission")
                    or build_mission(module["module_number"], lesson["title"], outcomes),
                    "sourcePages": lesson.get("content_pages") or [],
                    "pages": paginate(blocks, lesson["lesson_code"]),
                }
            )

    package = {
        "programmeId": "digital-foundation",
        "courseTitle": course["title"],
        "provider": course.get("provider", ""),
        "generatedFrom": export["source"]["original_file"],
        "lessons": lessons,
    }

    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text(json.dumps(package, indent=2, ensure_ascii=False) + "\n")

    counts: dict[str, int] = {}
    for lesson in lessons:
        for page in lesson["pages"]:
            for block in page["blocks"]:
                counts[block["type"]] = counts.get(block["type"], 0) + 1
    page_count = sum(len(l["pages"]) for l in lessons)
    print(f"wrote {OUT.relative_to(ROOT)}")
    print(f"  lessons     : {len(lessons)}")
    print(f"  screens     : {page_count} (avg {page_count / max(len(lessons), 1):.1f} per lesson)")
    print(f"  blocks      : {sum(len(p['blocks']) for l in lessons for p in l['pages'])}")
    print(f"  size        : {OUT.stat().st_size / 1024:.0f} KB")
    for block_type, count in sorted(counts.items(), key=lambda kv: -kv[1]):
        print(f"    {block_type:18s} {count}")
    empty = [l["lessonCode"] for l in lessons if not l["pages"]]
    print(f"  lessons with no blocks: {empty or 'none'}")


if __name__ == "__main__":
    build()
