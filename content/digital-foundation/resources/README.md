# resources/ and ../downloads/

Read-only course-provided files a task can reference via its
`supportContent` array in `days/day-NN.json`. The student can open or copy
these to their device from within a task ("Files You Need"), but never
edits the shipped copy — they always create their own working copy.

- `resources/` — reference material (e.g. `Digital-Foundation-Glossary.csv`).
- `downloads/` — templates/worksheets meant to be copied and worked in
  (e.g. `Keyboarding-Practice-Sheet.txt`).

These sample files are Naleli-authored and safe to ship in the public
repository and the APK. They exist in V1 to prove the resource-file
open/copy pattern end-to-end for Days 1–7; the full per-day resource set
(spreadsheets, briefs, templates named in the workbook for Days 8–90) is
authored during the V1.1 content pass — see `docs/ROADMAP.md`. Do not add
any third-party copyrighted file here — see `/source/README.md`.
