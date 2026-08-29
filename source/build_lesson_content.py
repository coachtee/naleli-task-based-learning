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
