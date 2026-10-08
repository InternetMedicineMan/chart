# Reference Notes — lessons from the Node implementation

**Source:** Jerad Hill's `jerad-ops` repo (private, paid-member access), read on 2026-09-23.
**Purpose:** capture the decisions and edge cases that cost him months of real use, so my Laravel build starts from them. **No code was copied.** These are notes in my own words for my own build.
**Status of the source:** Fastify + Next.js 15 + Supabase monorepo, ~390 files, 45 SQL migrations, one user.

---

## 1. Parser: what's worth copying as *approach*

His parser is a single Claude call with a **static system prompt (prompt-cached)** plus a **fresh context block in the user message** on every request. Context is deliberately small: active projects (50), active domains, **all** people (500), active companies (200), open content items (50), plus `now_iso` and today's date in his timezone.

Worth taking:

- **Cache the instructions, never the context.** Putting the changing context in the user message keeps the cached prefix stable. My Section 9.2 already says this; his code confirms it's the right split.
- **All people, not just recent ones.** He explicitly widened this: a forwarded email's sender may have no recent activity but must still match. My scope says "people touched in the last 60 days" — **change this to all people** (a name list is cheap).
- **The model emits fuzzy phrases; the server resolves them.** Same rule I wrote. His matcher: exact match = 1.0, substring = 0.6–0.9 scaled by length ratio, otherwise word-overlap × 0.55, **accept at ≥ 0.5**. That's a good starting threshold for my `*_ref` resolver, and it's simple enough to implement in PHP in an afternoon (no fuzzy-search package needed at first).
- **Low reasoning effort is enough.** He runs the parse at low effort with a small token ceiling for voice (2K) and a much larger one for email captures (8K), since email bodies must be preserved. Worth mirroring: cheap by default, generous only where the input is genuinely long.
- **Strip stray code fences before parsing JSON.** He hit model regressions where fences came back. Cheap defensive step.
- **He skipped strict structured output** because a heterogeneous array of ~19 action types didn't validate cleanly, relying on prompt discipline plus a catch path instead. Worth knowing before I spend a day fighting schemas: validate *after* parsing, at the executor boundary, not at the model boundary.

### Routing rules he had to write (I'd have missed these)

His prompt carries an explicit decision tree for library-type captures, because the model kept confusing them:

1. Quote (attribution present) — optionally with a bundled thought in the same utterance, which becomes an annotation.
2. Annotation on an existing quote ("add a thought to that quote about…").
3. Reading response (reading context, no verbatim quote).
4. Meeting note (person + conversation framing).
5. Brainstorm (explicitly loose ideas).
6. **Own thought — the safe default when there's no framing at all.**
7. Journal entry (explicit journal framing).
8. Activity log (project + work verb + often a duration).

**The rule underneath it:** when unsure, write a note flagged `needs_review` rather than guessing or failing. "A note can be re-classified later; a lost thought can't be recovered." That matches my never-lose guarantee, and the `needs_review` flag is a nice touch my scope lacks.

Other prompt details worth stealing:

- **Reminder defaults, spelled out.** Due time + no mention of reminders → remind at the due moment. "No reminder" → empty. "15 minutes before" → converted to minutes. No due time → no reminders at all. Ambiguity here otherwise produces silent no-shows.
- **Priority defaults to lowest** unless the words say urgent. Prevents everything looking important.
- **Domain vs. project matching are siblings.** If a project is named, the domain is derived from it. If only a domain is named, use it. If neither, Inbox.
- **Titles under 100 characters, filler stripped.**

---

## 2. His attention engine (the thing my Section 8 is competing with)

He runs a rules engine once daily at 5am that writes rows into an `attention_items` table, each with a **score**, a **dedup key**, and a **time bucket**. The score maps to urgency: **≥ 80 high, ≥ 30 normal, below that low**. Items expire after 60 days.

The rules and thresholds he actually settled on:

| Rule | Fires when | Score |
|---|---|---|
| Birthday | ≤ 7 days out | 50, +50 within a day |
| Anniversary | ≤ 14 days out | 40, +40 within 3 days |
| Person fact with a date | ≤ 14 days out | 45, +40 within 3 days |
| Conversation follow-up due | ≤ 7 days out, including overdue | 60, +40 today, +80 overdue |
| Client review due | ≤ 14 days out | 50 |
| Task due soon | ≤ 3 days out | 55, +50 today, +100 overdue |
| **Silent client** | past that client's own check-in interval (**default 30 days**); never-contacted clients surface immediately | 30, ramping +20 per extra 2 weeks, capped |
| **Project stalled** | **no activity logged in 14+ days** | 40 |
| Content stuck with editor | **editor has held it ≥ 10 days**, measured from a hand-off timestamp | 45, ramping |
| **Task waiting on someone** | **waiting ≥ 7 days** | 30, ramping weekly |
| Ideas aging | **≥ 3 ideas untouched for 30+ days**, one item per week only | 25 |
| Domain stale | per-domain interval, with a per-domain off switch | varies |

Take-aways for my scope:

- **My cadence defaults were close but not identical.** His real numbers: 30 days for client silence, 14 for a stalled project, 7 for a stale wait, 10 for something sitting with a contractor. I'll adopt these as my starting values instead of guessing.
- **A score, not just a state.** Scoring lets a briefing show the five most pressing items across unlike categories. My scope sorts "by how far over cadence," which breaks down when comparing a birthday to an overdue wait. **Add a score.**
- **Dedup keys with a time bucket** (`rule:entity:2026-W39`) are how he stops the same nag reappearing daily while still letting it return next week. My scope says "dedupe by (type, subject, date)" — the **week bucket** is the better unit for slow-moving nags, and the day bucket for due tasks.
- **Roll-ups instead of N items.** "12 ideas aging" as one weekly item, not twelve. My briefing limits do this crudely; his approach is better.
- **A never-contacted client must surface, not be skipped** because it has no "last contact" date. Easy null-handling bug to write.
- **Per-item off switch.** Each domain can disable staleness. Without it, one permanently quiet area nags forever and you learn to ignore the whole panel.
- **One source of truth, two displays.** His Work page pulls the same attention rows the Today page shows, so a project can never look calm in one place and slipping in another. My scope should say this explicitly.

### Timezone bug worth pre-empting

He hit it more than once: timestamps stored in UTC, then sliced to a date, so **anything that happened in the evening counted as a day earlier**. The fix is to convert to the local timezone before taking the date, everywhere a "days since" is computed. In Laravel that means comparing with `->setTimezone($tz)->toDateString()`, never a raw substring of a UTC timestamp. Worth writing a single helper and using it in the state engine, so this is impossible to get wrong twice.

---

## 3. His Work page ("computed, not curated") in practice

- Everything is **one aggregation query set per page load**, not per-card queries: domains, projects, open tasks, in-flight content, attention flags, recent activity — then grouped in memory. Worth mirroring in Laravel with a handful of eager-loaded queries rather than N+1 per project.
- **A parent can never look calmer than its children.** A domain's status is the highest urgency among its projects, content and its own attention items. This was clearly learned the hard way.
- **Buckets per project:** open, overdue, due today, waiting, and "waiting too long" (≥ 7 days). The card shows the **oldest** waiting item, with who and how many days.
- **Recency wording:** "active today," "active 3d ago," "quiet 12d," "no activity yet." Plain and factual.
- **A near or past target date is a state by itself**, even with nothing open. Within 7 days counts as "due."
- **Milestone progress is weighted**, so a big milestone moves the bar more than a small one.
- **Retainer clients get a cycle position** (day 12 of 30, anchored to a day of the month) instead of a progress bar. If I take on retainer clients, that's the right display, and the month-length clamping is fiddly enough to be worth remembering.
- **Ordering is part of the contract:** flagged first, then by nearest target date; domains sorted by flagged first, then by volume of open work. Parked domains sort to the bottom.

---

## 4. Capture path: where his design is weaker than mine

His watch capture posts a transcript to a webhook guarded by a shared secret, then **parses and executes inline** and returns the results. Three consequences:

1. **A parse failure returns an error and the words are gone** unless the watch retries. There's no row holding the raw transcript first.
2. **No idempotency**, so a retry can double-file everything.
3. **The watch waits for the whole AI round-trip**, which is the reason his shortcut can feel slow.

My Section 9.8 (save first, fall back to the Inbox, idempotency key, async with a quick confirmation) is a genuine improvement, and this repo is the evidence for why it matters. **Keep it.**

Also worth noting:

- He ended up adding **server-side audio transcription** (browser records audio, server transcribes with Whisper, then parses) because the browser's built-in speech recognition was unreliable — in his case partly network filtering. This confirms my Phase 2 audio path is a real need, not a nicety, and that the in-app mic shouldn't depend on browser speech recognition.
- His widget is a **Scriptable script** pulling a weighted-random quote every few hours from a secret-gated endpoint. That's a far cheaper path to a quote widget than the native Android app he later built — worth doing on iPhone in an hour.
- He has a **generic webhook ingest** endpoint for outside systems. He called this over-built in his v1 write-up, but with my automation work it's plausibly useful later. Still: don't build it before something needs it.

---

## 5. Schema details worth adopting

- **`waiting` is a task status**, not a separate flag, with `waiting_on` and `waiting_since`. Simple and it makes the "waiting too long" rule trivial.
- **Content items carry a `holder` and `holder_since`** ("me" or "editor"). The hand-off timestamp is what makes "stuck for 10 days" measurable. Generalize this: **any work item should record when the ball changed hands**, not just its current state.
- **A manual "mark shipped" stamp on a domain**, so work done outside the system (a newsletter published elsewhere) still resets that area's cadence. This is the same need my "extra touch target" solves, and his is the simpler mechanism. Consider supporting both: a tap, and a task that touches a domain.
- **Per-client check-in interval** on the client record, defaulting to 30 days. Already in my scope; his default is confirmed.
- **Per-domain stale on/off plus a day count**, rather than one global rule.
- **Ideas carry a `reviewed_at` stamp**, separate from created date, so "aging" means "not looked at," not "old." Worth adding to my Ideas screen.
- **`needs_review` flag** on anything the parser wasn't sure about. Cheaper than a separate triage queue for low-stakes items.
- **Parked domains**: a domain can be set aside without being deleted or archived. Mine has `archived_at` only. A "parked" state that keeps it visible but silent is better.
- 45 incremental migrations, and his own README warns that partial migration runs are what break fresh deploys. For a single-user app, **keep a squashed schema file alongside the migrations** and make that the fresh-install path.

---

## 5a. Checked: there is no public website in his implementation

I went looking for it, because it seemed likely. There isn't one.

- Every page in his web app sits behind an auth gate — the middleware covers everything except static assets, and the only unauthenticated page is sign-in.
- No `is_public`, `visibility`, or public-slug column anywhere in the schema, and no RSS or sitemap route.
- The only things reachable without a login are **token-gated endpoints**, not pages: the capture webhook and a quote endpoint for the home-screen widget.
- His actual publishing happens **off** the dashboard, on Substack. The evidence is a feature he had to build *because* of that: a manual "mark shipped" stamp on a domain, so writing published elsewhere still resets that area's cadence. If the dashboard published his notes itself, that button wouldn't need to exist.

**This settled the question: publishing is now out of scope for my build too** (SCOPE.md Section 10). A public side would have been unprecedented, expensive, and a permanent leak risk, serving an audience goal rather than the "what's my move" goal. If I ever want a personal site, it's a separate project, and the clean seam is a read-only export.

What his repo *does* give me here is the piece worth keeping: the content pipeline for tracking work I publish **elsewhere** — stages, hand-off timestamps, follow-up tasks, and the manual "mark shipped" stamp that resets an area's cadence.

---

## 6. What he built and I should not

- **The nightly scoring / shutdown module.** Retired, flagged off, and scheduled for deletion after 30 days of not missing it. Four tables' worth of work. My scope already omits it.
- **Health tracking** (9 tables, partly unused).
- **Reusable checklist templates** — tables exist, flow never wired.
- **A generic capture table with no consumer.**
- **Separate domains per content channel.** He ended up with several, and his own notes question whether one Content domain with channel tags would have been better. My `sphere` + domain model avoids this if I keep the count low.

A striking process detail: he keeps a **backlog file with a reason and an earliest date** per deferred item, and the governing rule that something leaves the list "when its absence actually hurts, not because it's listed." That's the same two-week rule in my Section 14, and worth adopting as an actual file in my repo.

---

## 7. Things he's still fighting (avoid inheriting)

- **A parser action implemented but missing from the prompt**, so it's unreachable by voice. Lesson: **the prompt's action list and the executor's action list must be generated from one source**, or tested against each other. In Laravel that's a test asserting every action enum appears in the prompt text.
- **Client-side filtering with a 2,000-row ceiling** in the library. Fine at his size, but it's the kind of thing that silently drops old matches later. Paginate on the server from the start.
- **Deploy fragility:** the framework's build peaked over a gigabyte of RAM and the server was killed until it was upgraded; the process manager needed hand-holding after every deploy. A Laravel app on a VPS avoids most of that class of problem — a point in favor of the stack choice, not a warning.

---

## 8. Net changes to make in SCOPE.md

| # | Change | Why |
|---|---|---|
| 1 | Parser context: send **all** people, not just recent | Forwarded emails and long-quiet contacts must still match |
| 2 | Add a **score** to observations, with the mapping ≥80 high / ≥30 normal | Lets the briefing rank unlike items against each other |
| 3 | Adopt his **tested thresholds** as my starting cadences: client 30d, project stalled 14d, wait aging 7d, hand-off stuck 10d, ideas aging 3 items × 30d | Beats guessing; he tuned these over months |
| 4 | Dedup keys get a **time bucket** (week for slow nags, day for due tasks) | Stops daily repeats without hiding a recurring problem |
| 5 | Add **roll-up items** ("12 ideas aging") instead of N separate rows | Keeps the briefing to its limits |
| 6 | Add `needs_review` to captured items the parser wasn't sure about | Lighter than full triage for low-stakes captures |
| 7 | Add `reviewed_at` to ideas; "aging" means unreviewed, not old | Otherwise everything ages forever |
| 8 | Add **parked** state to domains, and a per-domain quiet on/off switch | Prevents permanent nagging and teaching myself to ignore the panel |
| 9 | Record **when the ball changed hands** on waits and hand-offs (`waiting_since`, and the same for content) | Makes "stuck for N days" measurable at all |
| 10 | State explicitly: **one source of truth, two displays** — the Work page and the briefing read the same rows | He had to fix this; I can just build it |
| 11 | Add a **timezone helper rule**: never derive a date from a UTC timestamp without converting first | His most repeated bug |
| 12 | Add a **test that every parser action appears in the prompt** | His live bug today |
| 13 | Fuzzy matching: exact 1.0 / substring 0.6–0.9 / word overlap ×0.55, **accept ≥ 0.5**, else triage | A working starting point instead of tuning blind |
| 14 | Keep a squashed `schema.sql` next to migrations as the fresh-install path | His README's own warning |
| 15 | Add a `BACKLOG.md` with a reason and earliest date per deferred item | Makes the honesty pass a habit with a paper trail |
| 16 | Phase 4: iPhone quote widget via Scriptable (an hour), not a native app | Same result, far less work |

**Also decided:** the publishing module is **cut** (see 5a above and SCOPE.md Section 10).

**Not changing:** my capture-first/never-lose flow, ideas and someday projects, and Apple-first shortcut design. His implementation is weaker on capture reliability, and the rest doesn't exist in his version.
