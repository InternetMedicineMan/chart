# Chart — Personal Operations System — SCOPE

**Owner:** Jason Diehl
**Version:** 2.0 (first version under this owner, v2 because it starts from lessons learned in someone else's v1 and v2)
**Date:** September 2026
**Status:** Pre-build — scope for Claude Code kickoff

> **Lineage.** This project was inspired by Jerad Hill's *Personal Operations Dashboard* build guide (v1.0) and his *Dashboard 2.0* write-up, and informed by a read-through of his reference implementation (private repo, paid-member access, personal use only). This is an independent document written for a different stack and different goals. It borrows lessons, not text or code. It is not affiliated with or endorsed by him. Items marked *(Ref. note N)* point to `REFERENCE-NOTES.md`, which records what that read-through changed and why.

---

## 0. How to Use This Document

1. Put this file at the root of the repo as `SCOPE.md`.
2. Start Claude Code in the repo and paste the kickoff prompt from **Section 15**.
3. Build one phase at a time. Don't start a phase until the previous one has passed its **use gate**, meaning you've actually used it for the stated number of days.
4. When this document and reality disagree, change the document first, then the code. Record the change in **Section 17 (Decision Log)**.

---

## 1. What This Is

This is a single-user, entirely private operations system: tasks, projects, domains, calendar, clients and people, notes, quotes, books, journal, content, and voice capture. It answers one question: **what's my move right now?**

It is called **Chart**, and it runs at `chart.internetmedicineman.com` — behind a login, installed on my iPhone as a PWA. `ops.internetmedicineman.com` redirects to it as a memorable alias.

### 1.1 Name & Vocabulary

**Chart** — a patient chart and a dashboard chart at once, which fits the Internet Medicine Man persona and gives the whole system a vocabulary. The name is a label on the door, **not a persona**: Chart never speaks in the first person, has no character, and gives no advice (Section 2, principle 6). If I build a conversational agent later, that's where a personality belongs, under its own name.

| Term | What it means |
|---|---|
| **Chart** | The system itself; also the Briefing, its home screen |
| **Intake** | Capture, and the triage queue for anything unclear |
| **Bench** | The Work screen — projects and areas with work in progress |
| **Rounds** | Reviewing what's gone quiet or is waiting |
| **Vitals** | Cadence and state signals (quiet, stalled, waiting too long) |

"Chart it" is the spoken capture phrase. "What's on the bench" is the question the Work screen answers.

**No public operational content.** The only public page beyond authentication is a minimal Chart identity page at `/`, with one sign-in button. No tasks, people, notes, counts, or other user data appear there. Nothing captured here is published, shared, or readable without logging in. If I later want a personal site or a blog, it will be a **separate project with its own repo and its own domain**, not a face bolted onto this one. Turbowebs stays my client-facing professional site and is likewise out of scope.

### What This Is Not

- **Not a coach.** It doesn't grade the day, score habits, or give advice. It shows facts and I decide what to do with them.
- **Not a SaaS (for now).** Single user, and no signup, billing, or onboarding flows. See Section 17 for the reasoning and how to revisit it.
- **Not a website or a CMS.** Beyond the minimal identity/sign-in page there are no public content pages, publishing flow, or feeds. See Section 10.
- **Not a Trello board.** Nothing gets dragged. Status is computed, not maintained by hand.

---

## 2. Design Principles

These rules settle arguments during the build. When a feature idea conflicts with one of them, the principle wins unless the Decision Log says otherwise.

1. **Subtract before you add.** A feature has to remove friction from a problem I actually have and keep having. An immediate need is not an ongoing benefit.
2. **Computed, not curated.** Progress, recency, overdue counts, and who has the ball are derived from what already happened (tasks, activity, messages, dates). I never keep a status field up to date as a separate job.
3. **Status first, tasks second.** Every project view shows its state (my move, waiting, or quiet) before its task list.
4. **Capture on the page, configuration behind Edit.** Pages show what I need right now. Fields, dropdowns, and settings live behind an Edit action. Pages shouldn't look like forms.
5. **Capture freely, display sparingly.** Capturing anything is cheap. The briefing has hard limits on how much it shows.
6. **Observations state facts, never advice.** "Acme: 34 days since last contact (cadence 30)" is fine. "You should call Acme" is not.
7. **Private, period.** No operational data is public. Only the minimal identity page, authentication/recovery entry routes, static PWA assets, health check, and appropriately token-gated APIs are reachable without a session.
8. **Show the content.** In the library, journal entries with photos show the photos, and quotes show the quote. No paperclip icons over walls of text.
9. **What breaks if I leave it alone?** Ask this before any restructuring. If the answer is "almost nothing," make the small fix.
10. **Explain it back.** Before building a new feature, have Claude restate the problem in plain language. If the restatement reads like a passing annoyance, don't build it.

---

## 3. Stack & Architecture

### 3.1 Stack

| Concern | Choice | Notes |
|---|---|---|
| Framework | Laravel (current stable) on the **Larafast** starter kit (VILT) | Billing and marketing pages stay dormant |
| Front end (app) | Vue 3 + Inertia + Tailwind | Installable PWA |
| Database | MySQL 8 | JSON columns for flexible payloads |
| Queue / cache | Redis + Horizon | Parsing, sync, and nightly jobs run on queues |
| Scheduler | Laravel Scheduler | Calendar sync, observations, resurfacing, cleanup |
| Realtime (optional) | Laravel Reverb | Live updates between phone and laptop. Skip until needed. |
| Auth | Fortify / Jetstream with 2FA (TOTP) | One user. Registration disabled after seeding. |
| Files | Flysystem → S3-compatible (R2, S3, Spaces) | Photos, receipts, journal scans. One private bucket; served through signed URLs only. |
| AI | Anthropic API via Laravel HTTP client or PHP SDK | Larger model for parsing and vision, small model for cheap jobs. Prompt caching on. |
| Google | Socialite + Google API client | Calendar (two-way), Gmail (Phase 2) |
| Push | Web Push channel and/or Pushover | Reminders and approvals |
| Book data | Open Library API | Covers and metadata |
| Hosting | VPS with Forge/Ploi (or existing host) | One site at `chart.internetmedicineman.com` (+ `ops.` redirect), wildcard cert |

### 3.2 PWA Requirements

- `manifest.webmanifest` (`name` and `short_name`: **Chart**), icons, `display: standalone`, theme colors.
- A service worker (e.g. `vite-plugin-pwa`) that caches the app shell and static assets.
- **Offline capture queue:** if the network is down, captures go into IndexedDB and are sent automatically when the connection returns. Show a pending count badge. Reading data offline is not required.
- Test installation on iOS Safari during **Phase 1**, not later.

### 3.2a iPhone PWA Checklist (Larafast VILT)

**Install & look native**
- `vite-plugin-pwa` alongside `laravel-vite-plugin`. The service worker must control `/`: serve `sw.js` from the web root (or send `Service-Worker-Allowed: /`), not from `/build/`.
- `apple-touch-icon`, `apple-mobile-web-app-capable`, `apple-mobile-web-app-status-bar-style`, `viewport-fit=cover`, and startup images.
- Respect safe areas (`env(safe-area-inset-*)`) on the bottom tab bar and header. Disable double-tap zoom on controls and use 16px+ inputs so iOS doesn't zoom in.
- Strip Larafast's marketing and billing pages from the app domain. The PWA opens straight to the Briefing.

**Feel fast**
- Inertia persistent layouts (the tab bar never re-renders), link prefetching, deferred props for the slow Briefing blocks, and optimistic UI for completing tasks.
- A top progress bar only when a request takes longer than ~250 ms.

**Auth**
- The installed app has its **own cookie storage**, separate from Safari, so you log in once inside the app. Use a long-lived "remember me" session.
- Handle 419 (expired CSRF) quietly: refresh the token and retry, never show an error page.
- Face ID later via passkeys (WebAuthn) if the password gets annoying.

**Offline & sync**
- Cache the app shell plus an offline fallback screen. Inertia pages themselves need the network.
- The capture queue lives in IndexedDB. iOS has **no Background Sync**, so the queue sends when the app opens, becomes visible again, or comes back online.

**Push**
- Web Push (VAPID) works only for the **installed** app (not in a Safari tab), and the permission prompt must come from a tap (a "Turn on reminders" button in Settings).

**In-app voice**
- Don't depend on the Web Speech API in the installed app; it's unreliable on iOS. The primary in-app path is the **iOS keyboard dictation mic** on a big autofocused capture box (free and reliable). Optional later: record with `MediaRecorder` and send it through the audio path (9.9).
- The Watch/Siri shortcut (9.5) doesn't involve the PWA at all.

**Known iOS gaps and workarounds**

| Gap | Workaround |
|---|---|
| No share-sheet target for web apps | A "Send to Capture" Shortcut in the share sheet posts to `/api/capture` |
| No home-screen widgets from a PWA | A Scriptable or Shortcuts widget, or a native wrapper later |
| iOS may clear site storage for unused web apps | The server is the source of truth. IndexedDB holds only the unsent queue. |
| No background tasks | Server-side scheduler + push |

**Escape hatch:** if PWA limits start to hurt (widgets, deeper Siri integration), wrap the same app with Capacitor or NativePHP Mobile. The backend and API don't change.

### 3.3 Layers

```
CAPTURE       in-app mic · typed quick-add · iOS/Watch Shortcut · Android HTTP Shortcut
              · email forward · offline queue
                    │
PROCESSING    /api/capture → captures table → ParseCapture job (Claude)
              → ActionExecutor → notifications (with undo) / triage queue
                    │
STATE ENGINE  WorkStateResolver (my move / waiting / quiet) · cadence checks
              · nightly observations · resurfacing picker
                    │
PRESENTATION  App (Inertia PWA): Briefing · Work · Project · People · Library
              · Content · Capture Inbox · Ideas · Settings
```

### 3.4 Routing

- **One Laravel app, one subdomain, private by default.** The public entry allowlist is the minimal `/` page, login, password recovery/reset, two-factor challenge, necessary CSRF-cookie plumbing, and a health check. Manifest, icons, service worker and the non-sensitive offline fallback are public static assets. Capture and widget APIs require scoped bearer tokens (or an authorized session where specified). Account/security settings permit the designated owner to complete 2FA; operational routes require confirmed enrollment. Package routes are included in the private boundary.
- A test asserts that the set of unauthenticated routes is exactly that list, so a public route can't appear by accident (Section 13).

---

## 4. Core Concepts

### 4.1 Hierarchy

```
Sphere (personal | work)         ← a label on a domain, not a table
  └─ Domain                      ← ongoing areas of responsibility (plus the Inbox)
       ├─ Project                ← finite outcome OR ongoing client engagement
       │    ├─ Milestone
       │    └─ Task (+ subtasks)
       └─ Task (directly under the domain, no project needed)
```

- **Inbox** is a system domain. Any task captured without a clear home goes there. It can't be deleted.
- **Sphere** captures the "two silos" idea (personal vs. work) as a single column. The briefing and Work page can filter by it. There's no extra layer to maintain.
- **There are no "Areas."** Ongoing responsibilities are Domains. Finite outcomes are Projects.
- A **Project** has a `type`: `target_date` (has a finish line) or `ongoing` (a client retainer or recurring engagement with a cadence).

### 4.2 The Three Work States

Every domain, project, and person with a cadence is in exactly one state at any moment. The state is **computed and never stored as a source of truth** (a cached copy is fine).

| State | Meaning | Computed when |
|---|---|---|
| **My move** | I'm holding the ball | There are open tasks assigned to me that are due, overdue, or in today's top 3. Or it's a target-date project with no waiting items. |
| **Waiting** | Someone else is holding the ball | There's an open **wait** (a task or project marked waiting on a person) and the expected response date hasn't passed |
| **Quiet** | Nobody has touched it for too long | `now − last_touched_at > cadence_days`. An overdue wait also becomes Quiet, labeled "wait overdue." |

Precedence: **Quiet > My move > Waiting.** A stale item always surfaces, even if I thought someone else had it.

### 4.3 Touches

Many kinds of events count as a **touch**, which resets `last_touched_at` on the target:

- Completing a task touches its project, its domain, and any **extra touch target** set on the task.
- Logging activity touches the subject it's logged against.
- Logging an interaction touches the person (and their company's project, if linked).
- Marking a content item published touches its domain.

**Extra touch targets** exist for a specific case: a recurring task like "Write this week's article" can touch a domain directly without a content-pipeline item. Writing doesn't always need a pipeline.

### 4.4 Daily Focus

- **Top 3:** each evening or morning I choose up to three tasks for the day. Everything else waits until the top 3 are done.
- **Tomorrow's focus:** one optional line of text. No checklist, no score, no shutdown ritual.

---

## 5. Screens

### 5.1 Briefing (Today)

It answers "what's my move right now?" Hard limits on how much shows keep it readable.

| Block | Content | Limit |
|---|---|---|
| Header | Date, tomorrow's-focus line from last night (if any) | — |
| Now / Next | Current and next calendar events | 3 |
| Top 3 | Today's chosen tasks. Tap to complete. | 3 |
| My move | Overdue and due-today tasks not in the top 3, grouped by project | 7 (+N more) |
| Gone quiet | Domains, projects, and people past their cadence, highest score first (Section 8.2) | 5 |
| Waiting on | Open waits with the person's name and days waiting | 5 |
| Captured | Inbox count · captures needing a decision (flagged if >48h) · new ideas this week, each a link | — |
| Resurfaced | One quote, journal entry, or note | 1 |

On wide screens (laptop, foldable), blocks sit in two columns. On a phone, they stack in the order above.

### 5.2 Work — "Bench"

- A list of domains grouped by sphere, each showing its projects as **state rows**: name · state chip · last touched · open or overdue counts · hours this month.
- **No drag and drop, no manual statuses.** Filters: sphere, state, domain.
- Tapping a row opens the Project view.

### 5.3 Project View

Top to bottom:

1. **State strip:** state chip, who has the ball, days since last touch, cadence, hours this month, milestone progress (computed from milestone weights).
2. **Quick capture:** a text or mic field that adds tasks, activity, or notes to this project.
3. **Tasks:** open (my move / waiting), then completed (collapsed).
4. **Activity:** recent log entries.
5. **Edit** (behind a button): name, type, target date, cadence, domain, linked people, and milestones.

### 5.4 People

- A list of clients and people with a state chip (quiet or ok) and "last contact N days ago."
- Person view: facts (anniversaries, kids, preferences), interaction timeline, linked projects, open waits, and a quick "log interaction" action.

### 5.5 Library

A visual, scrollable collection:

- **Journal:** a photo-first feed. Entries with images show them large, and text entries show an excerpt.
- **Quotes:** card layout with source and annotation count.
- **Books:** a cover grid (reading / finished / want to read).
- **Notes:** a feed with a type label.

### 5.6 Content

- A pipeline list for pieces that need structure (mainly video): Idea → Outline → Production → Editing → Published → Follow-ups → Done.
- The detail view shows follow-up tasks created from templates.
- Articles don't have to go through the pipeline (see Touches, 4.3).

### 5.7 Intake (capture inbox)

- Pending captures that need a decision (ambiguous matches, parse failures), shown with the candidate matches as buttons.
- Untriaged Inbox tasks, with one-tap moves to a domain or project.
- Capture history: every capture with its original text and where each piece went (Section 9.7).

### 5.8 Ideas

- Quick thoughts (newest first) and Someday projects, with one-tap conversion (Section 9.10).

### 5.9 Settings

- Domains (including sphere and cadence), capture tokens, Google connection, email rules, notification channels, parser test bench (Section 9.6), and data export.

### 5.10 Navigation

- On mobile, a bottom tab bar: **Chart · Bench · Intake (mic) · Library · More**.
- On desktop, a left sidebar with the same items plus People, Content, Ideas, and Settings.

---

## 6. Build Phases

Each phase ends with a working tool. **Use gate** = the number of days of real daily use required before starting the next phase.

### Phase 1 — The Spine *(use gate: 7 days)*

- Project scaffold, auth + 2FA, single user seeded, registration disabled
- PWA shell + install test on iOS and Android
- Domains (with sphere and cadence), system Inbox, Projects (target_date / ongoing), Milestones
- Minimal `people` table (name, relationship, company) so waits have someone to point at. Full People UI comes in Phase 3.
- Tasks: due date/time, priority, subtasks, recurrence (RRULE), reminders, waits, extra touch target
- Touches + `WorkStateResolver` (Section 8)
- Daily focus: top 3 + tomorrow's-focus line
- Briefing, Work, and Project view
- Activity log with minutes
- **Capture pipeline (the #1 feature, see Section 9):**
  - In-app voice + typed capture → parser → executor → notifications with **Undo**
  - Capture tokens (hashed, scoped, rate-limited, revocable)
  - **Apple Watch / iPhone / Mac one-button capture** via one iCloud-synced Shortcut, with a spoken confirmation
  - `capture:import` command for everything captured to Apple Notes during Phase 0 (Section 9.5a)
  - **Brain-dump mode:** one long recording is split into many items (Section 9.7)
  - **Ideas** (quick thoughts) and **Someday projects**, so non-tasks have a home that doesn't clutter the briefing
  - Capture Inbox triage screen
  - The never-lose guarantee (Section 9.8)
- Google Calendar two-way sync (every 15 minutes)
- Notifications feed + Web Push/Pushover reminders
- Basic nightly observations: quiet items, overdue waits, over-booked tomorrow

**Done when:** I can run a full day from the briefing, tap my watch and dump five unrelated things in one recording and find each one filed correctly (or waiting in triage), and see a quiet project show up by itself.

> **Build-order note:** inside Phase 1, build the capture endpoint + watch shortcut **early** (right after tasks, domains, and Inbox exist), even before the briefing is polished. Capturing starts clearing my head on day one, and anything that isn't organized yet lands in the Inbox.

### Phase 2 — More Capture Surfaces *(use gate: 7 days)*

- Offline capture queue in the PWA (IndexedDB)
- Audio-upload path for long recordings (Section 9.9), if dictation quality isn't good enough
- Desktop quick capture: a Mac keyboard shortcut (Shortcuts app or Raycast) that posts to the same endpoint
- Email forward-to-capture (inbound address → capture)
- Gmail watcher, **approval-first**: proposes tasks and attachment routing, and I approve. A rule becomes automatic only after N approvals with no edits (`confidence_state`).

**Done when:** a capture made with no signal gets sent later, and a forwarded email becomes a task.

### Phase 3 — People & Client Cadence *(use gate: 10 days)*

- People, facts, interactions
- Per-person cadence, which feeds the Quiet state on the briefing
- Link people to projects. Waits reference people.
- Parser: `log_interaction`, `create_person_fact`
- Upcoming date-based facts (anniversaries, birthdays) surface 14 days ahead

**Done when:** a client who's past their check-in rhythm shows up on the briefing without me thinking of them.

### Phase 4 — Library, Journal & Resurfacing *(use gate: 14 days)*

- Notes (typed), Quotes + annotations, Books (Open Library), Journal (typed, voice, and photo)
- Media attachments (polymorphic), EXIF stripped on upload, thumbnails
- Journal photo OCR via Claude Vision (queued, editable transcription)
- Photo-first library views
- Resurfacing engine: one item a day on the briefing, weighted, no repeats within a window, "boost" and "less of this" responses
- Tags (shared across library types)
- **iPhone quote widget via Scriptable** — a home-screen widget pulling a weighted-random quote from a token-scoped read-only endpoint (`/api/widget/quote`), refreshed every few hours. About an hour's work, no app to build or install. *(Ref. note 16)*

**Done when:** I return to the library because I want to, not because I have to.

### Phase 5 — Content Pipeline *(use gate: n/a)*

- Content items + stages, with who holds the ball and since when
- Per-channel templates that create follow-up tasks when something reaches "published"
- A manual "mark shipped" action, so work published elsewhere still resets that area's cadence

**Done when:** marking a video published creates its follow-up tasks automatically.

### Later / Parked (don't build without passing the Section 14 test)

- Health, Inventory, Routines/habits
- Generic webhook ingestion
- Android quote widget app (native), iOS Scriptable widget
- Evening review / coaching mode (the idea is parked, see Section 16)
- Multi-tenant / product version (see Decision D3)
- Readwise/Kindle import

---

## 7. Data Model

### 7.1 Conventions

- MySQL 8, `bigint` auto-increment PKs, `created_at`/`updated_at` on every table, soft deletes on user content.
- Enums are stored as `varchar` and backed by PHP enums.
- Tags use a polymorphic `tags` / `taggables` pair (no array columns in MySQL).
- Attachments use a polymorphic `media` table.
- Every table that holds user data has a `user_id` FK. It's single-user today, but this makes a future tenancy retrofit (Decision D3) a scoping change, not a data migration. All queries go through model scopes.
- Timezone: stored in UTC. The user's timezone lives in `app_settings` (default `America/Chicago`). **All date maths goes through the timezone helper in Section 8.1** — never a raw UTC date substring.
- **Hand-off timestamps:** anywhere the ball can change hands (a waiting task, a project with a client, a content item with an editor), the row records *who holds it* and *since when*. "Stuck for N days" is unmeasurable without it, and a status alone doesn't give it. *(Ref. note 9)*
- Migrations are incremental, but a **squashed `infrastructure/schema.sql` is kept alongside them and is the fresh-install path**. Partially applied migration chains are how a new environment ends up missing tables. *(Ref. note 14)*

### 7.2 Core (Phase 1)

```
domains
  id, user_id, name, slug, sphere (personal|work), description,
  cadence_days (nullable), quiet_enabled (bool, default true),  -- per-domain off switch
  is_inbox (bool), parked (bool, default false),   -- parked = visible, never flagged
  sort_order, last_touched_at, last_shipped_at,    -- manual "I shipped something" stamp
  archived_at

projects
  id, user_id, domain_id, name, slug, description,
  type (target_date|ongoing), target_date (nullable), cadence_days (nullable),
  lifecycle (someday|active|parked|done|dropped),  -- the only manual field; state is computed.
                                            -- someday = captured project idea: never on the briefing,
                                            -- no cadence, reviewed from the Ideas screen
                                            -- parked = real but set aside: visible, never flagged
  quiet_enabled (bool, default true),
  holder (me|other), holder_person_id (nullable), holder_since,   -- who has the ball, since when
  last_touched_at, reviewed_at (nullable), completed_at

milestones
  id, project_id, title, weight (int, default 1), due_date, completed_at, sort_order

tasks
  id, user_id, domain_id (NOT NULL → Inbox by default), project_id (nullable),
  milestone_id (nullable), parent_task_id (nullable),
  title, notes, priority (1–4), due_date, due_time,
  recurrence_rule (RRULE, nullable), reminder_offsets (json, minutes),
  waiting_on_person_id (nullable), waiting_since, wait_expected_by,
  touch_target_type, touch_target_id,        -- extra touch target (polymorphic, nullable)
  source (manual|voice|shortcut|email|observation|template),
  needs_review (bool, default false),        -- parser wasn't confident; shows a "check this" chip
  capture_id (nullable), completed_at

activity_logs
  id, user_id, subject_type, subject_id,     -- project | domain | person | content_item
  entry, minutes (nullable), source, occurred_at

daily_plans
  id, user_id, plan_date (unique), top_task_ids (json, max 3),
  tomorrow_focus (varchar 280, nullable)

calendar_events
  id, user_id, google_event_id, google_calendar_id, etag,
  title, starts_at, ends_at, all_day, location, attendees (json),
  project_id (nullable), synced_at, origin (google|app)

calendar_sync_states
  id, user_id, google_calendar_id, sync_token, last_synced_at

notifications_feed
  id, user_id, type, title, body, subject_type, subject_id, url,
  status (unread|read|dismissed), undo_payload (json, nullable), undone_at

captures
  id, user_id, raw_text, source (in_app|ios|watch|mac|android|email|offline_queue),
  device_label, capture_token_id (nullable), client_captured_at,
  mode (single|dump),                        -- dump = long brain-dump recording
  audio_path (nullable), transcript_provider (device|stt_service),   -- Phase 2 audio path
  status (received|transcribing|parsed|executed|partially_executed|needs_triage|failed),
  parsed (json), candidates (json), error, model, input_tokens, output_tokens,
  item_count, executed_at

capture_items                                -- one row per item split out of a capture
  id, capture_id, sequence, excerpt (the words this item came from),
  action_type, status (executed|needs_triage|undone|failed),
  target_type, target_id, confidence

capture_tokens                               -- Phase 1 (needed for the watch shortcut)
  id, user_id, label, device_name, token_hash, scopes (json: ["capture:write"]),
  rate_limit_per_hour, last_used_at, revoked_at

notes                                        -- created in Phase 1 for Ideas (kind=thought);
                                             -- full columns and library UI in Phase 4 (7.5).
                                             -- Phase 1 columns: id, user_id, body, kind,
                                             -- needs_review, reviewed_at, created_at
                                             -- reviewed_at = last time I actually looked at it;
                                             -- "aging" means unreviewed, not merely old

action_logs
  id, user_id, capture_id (nullable), action_type, target_type, target_id,
  payload (json), status (ok|failed|undone|awaiting_approval),
  triggered_by (capture|rule|schedule|user), executed_at

observations
  id, user_id, rule_type, subject_type, subject_id,
  title, body, suggested_action, data (json),
  score (int), urgency (low|normal|high),    -- derived: >=80 high, >=30 normal
  dedup_key (unique: "rule:subject:bucket"), roll_up_count (nullable),
  observed_on, resolved_at, dismissed_at, snoozed_until, acted_on_at, expires_at

app_settings
  id, user_id, key, value (json)
```

### 7.3 Email Capture (Phase 2)

```
inbound_emails
  id, user_id, message_id, from, subject, received_at, body_text,
  attachments (json), capture_id (nullable), status

email_rules
  id, user_id, name, match (json), action_type, action_params (json),
  confidence_state (proposing|trusted|disabled), approvals_count, edits_count
```

### 7.4 People (table created in Phase 1; cadence, facts, and interactions used from Phase 3)

```
people
  id, user_id, name, relationship (client|colleague|friend|family|ministry|other),
  company, email, phone,
  cadence_days (nullable, default 30 for clients),   -- per-person check-in rhythm
  quiet_enabled (bool, default true),
  birthday, anniversary,
  last_touched_at (null = never contacted → surfaces immediately, not skipped),
  next_review_at (nullable), notes, archived_at

person_project (pivot)
  person_id, project_id, role

person_facts
  id, person_id, fact_type (anniversary|birthday|family|preference|follow_up|other),
  value, relevant_on (date, nullable), recurs_yearly (bool),
  source_type, source_id

interactions
  id, person_id, kind (call|email|meeting|message|visit|other),
  summary, occurred_at, source
```

### 7.5 Library (Phase 4)

```
notes
  id, user_id, body (markdown), kind (thought|reading_response|meeting|brainstorm|sermon|other),
  source_reference, project_id, person_id, quote_id (all nullable),
  needs_review (bool), reviewed_at (nullable),
  resurface_weight (decimal, default 1.0), last_surfaced_at

books
  id, user_id, title, author, isbn, open_library_key, cover_url,
  status (want|reading|finished|abandoned), format (physical|ebook|audio),
  started_on, finished_on, rating (1–5), summary

quotes
  id, user_id, book_id (nullable), text, page, chapter,
  source_kind (book|article|podcast|conversation|sermon|scripture|other),
  source_reference, source_author, added_via (voice|manual|import),
  resurface_weight, last_surfaced_at

quote_annotations
  id, quote_id, body, context (on_capture|on_revisit|on_surface), annotated_at

journal_entries
  id, user_id, entry_date, body, transcription, transcription_status,
  source (typed|voice|photo), journal_book_id (nullable),
  extracted (json), resurface_weight, last_surfaced_at

journal_books
  id, user_id, number, started_on, ended_on, notes

media
  id, user_id, mediable_type, mediable_id, disk, path, mime, bytes,
  width, height, caption, taken_at, ocr_text, sort_order

tags / taggables
  tags: id, user_id, name, slug
  taggables: tag_id, taggable_type, taggable_id

resurfacings
  id, user_id, item_type, item_id, surfaced_on, response (none|boost|less|opened)
```

### 7.6 Content (Phase 5)

```
content_items
  id, user_id, domain_id, title, channel, kind (video|article|short|podcast|newsletter),
  stage (idea|outline|production|editing|published|follow_ups|done),
  holder (me|other), holder_person_id (nullable), holder_since,   -- who has the ball, since when
  target_publish_date (nullable), reviewed_at (nullable),         -- for ideas aging
  outline_md, url, published_at, archived_at, parent_id, derivative_kind

content_templates
  id, user_id, channel, trigger_stage, derivative_kind,
  task_title_template, due_offset_days, active
```

---

## 8. State Engine

### 8.1 `WorkStateResolver`

Input: a domain, project, or person. Output: `{ state, reason, since, holder }`.

```
if lifecycle is done/dropped → excluded
if cadence_days set AND now - last_touched_at > cadence_days → QUIET ("N days, cadence C")
if any open wait with wait_expected_by < today              → QUIET ("wait overdue: <person>")
if any open task (not waiting) due ≤ today OR in top 3      → MY_MOVE
if any open wait                                            → WAITING (<person>, N days)
if type = target_date AND open tasks exist                  → MY_MOVE
else                                                        → OK (not shown on briefing)
```

- Results are cached per subject for 10 minutes and cleared on any touch.
- Items without a cadence never become Quiet from inactivity alone.
- **Parked** domains and projects are skipped entirely: still visible, never flagged.
- **One source of truth, two displays.** The Briefing and the Work page both read these same rows. A project can never look calm in one place and slipping in the other. *(Ref. note 10)*
- **Never derive a date from a UTC timestamp.** All "days since" maths goes through one helper that converts to the app timezone first (`->setTimezone($tz)->toDateString()`). A raw UTC date substring counts anything done in the evening as a day earlier. Every cadence, wait, and hand-off calculation uses that helper — no exceptions. *(Ref. note 11)*

### 8.2 Nightly Observations (02:00 local)

Each observation carries a **score**, and the Briefing shows the highest-scoring ones within its limits. Scores let unlike things (a birthday, an overdue wait, a silent client) be ranked against each other.

| Observation | Fires when | Base score | Escalation |
|---|---|---|---|
| Task due soon / overdue | ≤ 3 days out | 55 | +50 today, +100 overdue |
| Wait aging | waiting ≥ 7 days | 30 | +20 per extra week |
| Client / person silent | past that person's own cadence (default 30 days); **never-contacted surfaces immediately** | 30 | +20 per extra 2 weeks, capped at +40 |
| Project stalled | no activity logged in 14+ days | 40 | — |
| Hand-off stuck | someone else has held it ≥ 10 days (measured from `waiting_since`) | 45 | +15 per extra week |
| Domain quiet | past that domain's cadence, if its quiet switch is on | 40 | scaled by how far over |
| Person date (birthday, anniversary, fact) | 7 / 14 / 14 days out | 40–50 | +40 within 3 days |
| Ideas aging | **≥ 3 ideas unreviewed for 30+ days** — one roll-up item per week | 25 | — |
| Inbox backlog | untriaged older than 7 days, over a threshold | 20 | — |
| Tomorrow's load | meeting hours vs. free focus time (factual line only) | 20 | — |

- **Urgency mapping:** score ≥ 80 = high, ≥ 30 = normal, below = low. *(Ref. note 2)*
- **Dedup key = `rule:subject:bucket`**, where the bucket is the **week** for slow-moving nags (quiet, stalled, waiting, ideas) and the **day** for due tasks. A weekly bucket means a nag can return next week without repeating daily. *(Ref. note 4)*
- **Roll-ups, not N rows.** Anything that would produce a list ("12 ideas aging," "5 untriaged captures") emits one item whose count is the signal. *(Ref. note 5)*
- Observations auto-resolve when the condition clears, and expire after 60 days.
- **Every cadence rule has a per-subject off switch.** One permanently quiet area that nags forever teaches me to ignore the whole panel. *(Ref. note 8)*

### 8.3 Starting Thresholds

These are not guesses. They're the values the Node implementation settled on after months of daily use *(Ref. note 3)*. Start here and tune in week 1.

| Kind | Default |
|---|---|
| Active client (ongoing) — check-in cadence | 30 days, per client |
| Slower client | 60–90 days |
| Project stalled (no activity) | 14 days |
| Wait aging (someone else has the ball) | 7 days |
| Hand-off stuck (contractor, editor, vendor) | 10 days |
| Ideas aging | 3+ ideas unreviewed for 30 days |
| Work domains | 7 days |
| Personal / ministry domains | 14 days |
| Weekly writing (via extra touch target) | 8 days |
| Inbox | none (backlog shown as a count instead) |

---

## 9. Capture & Parsing

### 9.1 Flow

1. Text arrives at `POST /api/capture` (session auth **or** a bearer capture token) with `{ text, source, device_label?, captured_at? }`.
2. A `captures` row is written **before anything else happens**, so the words are safe even if everything after this fails. The endpoint returns `202`. Shortcuts use `?wait=1` to hold for up to 8 seconds and get a `spoken_confirmation`. If parsing takes longer, the response is "Saved. Sorting it now." and the result arrives as a push notification.
3. The `ParseCapture` job builds a **fresh context** (never a stale cached one) and calls the configured parser provider (OpenAI initially; D28).
4. The `ActionExecutor` validates each action against a schema, resolves matches, and writes the records.
5. Each executed action creates a notification with an **Undo** payload.
6. Ambiguous or low-confidence results → `needs_triage` → Capture Inbox. Nothing is guessed.

### 9.2 Context Sent With Each Parse

- Current local date/time and timezone
- Domains (id, name, sphere), active projects (id, name, domain), open milestones for active projects
- **All people** (id, name, company), not just recent ones — a long-quiet contact or a forwarded email's sender has to be matchable too. A name list stays cheap into the thousands. *(Ref. note 1)*
- Today's top 3 and the 20 most recent open tasks (for "mark that done" style references)
- Recent books and quotes (Phase 4+), active content items (Phase 5)
- Capture source

The static instructions go in a cached system block. The dynamic context above is sent uncached on every call.

### 9.3 Action Contract

The parser returns JSON only:

```json
{
  "actions": [ { "type": "...", "confidence": 0.0, "...": "..." } ],
  "needs_triage": false,
  "candidates": [],
  "spoken_confirmation": "Added 2 tasks to Acme."
}
```

| Action | Key fields | Phase |
|---|---|---|
| `create_task` | title, domain_ref?, project_ref?, due?, priority?, parent_ref?, reminders?, waiting_on_ref? | 1 |
| `complete_task` | task_ref | 1 |
| `set_top3` | task_refs[] | 1 |
| `set_tomorrow_focus` | text | 1 |
| `set_waiting` | task_ref \| project_ref, person_ref, expected_by? | 1 |
| `log_activity` | subject_ref, entry, minutes? | 1 |
| `create_project` | name, domain_ref, type, target_date?, lifecycle (active\|someday) | 1 |
| `capture_idea` | body, related_refs?, tags? → `notes` (kind=thought) | 1 |
| `complete_milestone` | project_ref, milestone_ref | 1 |
| `create_event` | title, start, end, location?, attendees? | 1 |
| `log_interaction` | person_ref, kind, summary | 3 |
| `create_person_fact` | person_ref, fact_type, value, relevant_on?, recurs_yearly? | 3 |
| `create_note` | body, kind, refs?, tags? | 4 |
| `create_quote` | text, book_ref?, page?, source_*?, tags? | 4 |
| `annotate_quote` | quote_ref, body | 4 |
| `create_journal_entry` | body, date? | 4 |
| `boost_item` / `less_of_item` | item_type, item_ref | 4 |
| `update_content_item` | item_ref, stage?, url?, holder? | 5 |

**The action list is generated, not typed twice.** The prompt's action list is rendered from the same enum the executor validates against, and a test asserts every action appears in the prompt. An action that exists in code but not in the prompt is unreachable by voice and fails silently — that bug is live in the Node implementation today. *(Ref. note 12)*

Rules:

- One utterance can contain several actions. Each action includes `excerpt`, the words it came from, so triage and undo can show what was said.
- **Task vs. idea vs. someday project:** something I have to *do* → task. Something I'm *thinking about* → idea. "I want to build / start / someday…" → someday project, unless I say to start it now.
- `*_ref` values are fuzzy text references. **The server resolves them, not the model.** Scoring: exact match = 1.0; one string contains the other = 0.6–0.9 scaled by length ratio; otherwise the share of query words (3+ letters) found in the target × 0.55. **Accept at ≥ 0.5**; two candidates within 0.1 of each other → triage. Plain string work, no search package needed at first. *(Ref. note 13)*
- No domain or project given → Inbox.
- Relative dates resolve in the user's timezone. "This weekend" = Saturday.
- Any action with `confidence < 0.6` → triage. Between 0.6 and 0.8, file it but set **`needs_review`**, which shows a small "check this" chip on the item rather than holding it back. Losing a thought is worse than mis-filing one. *(Ref. note 6)*
- **Unframed capture defaults to an idea, never to nothing.** No task verb, no project, no date → `capture_idea`, not a failed parse.
- Unparseable input → `{ "error": "...", "actions": [] }` → the raw text is saved as an Inbox task so nothing is lost.

### 9.4 Model Routing

- Default parse: OpenAI initially, using a configurable model and replaceable provider adapter (D28). Validate quality on Appendix B before accepting the model; do not select it based on a chat subscription tier.
- Resurfacing picks, OCR cleanup, and observation text: the small model.
- Journal photo OCR: the vision-capable model.
- Prompt caching is on for the static system block. Only user-triggered events make API calls, never page loads.

### 9.5 One-Button Capture (Watch, Phone, Mac)

The goal is **tap → talk → done** in under 10 seconds, with no app to open.

**How it works:** the watch or phone does the speech-to-text itself (free, on-device dictation), then sends *text* to the app. The app's AI sorts and files it. No audio is uploaded in the default path.

**iOS / Apple Watch Shortcut ("Chart It")**

1. **Dictate Text** (language: English, stop listening: *After Pause* for quick items, *On Tap* for brain dumps; see 9.7)
2. **Get Contents of URL**
   - `POST https://chart.internetmedicineman.com/api/capture?wait=1`
   - Headers: `Authorization: Bearer <capture token>`, `Content-Type: application/json`
   - JSON body: `{ "text": <Dictated Text>, "source": "watch", "device_label": <Device Name>, "captured_at": <Current Date> }`
3. **Get Dictionary Value** `spoken_confirmation`
4. **Speak Text** (or **Show Notification** on the watch)
5. **If the request fails:** append the text to a local note ("Capture Outbox") and say "Saved offline." A second shortcut, "Send Outbox," retries later.

**Ways to trigger it:**
- A watch-face complication
- The Action Button (on Watch Ultra or recent iPhones)
- "Hey Siri, capture"
- A lock-screen or home-screen widget on the phone
- A menu-bar or keyboard shortcut on the Mac

Make two versions:
- **"Chart It":** quick, stops after a pause
- **"Brain Dump":** keeps listening until you tap, and sends `mode: "dump"`

**Primary device: Apple Watch** (paired iPhone + Mac). The design is Apple-first. The same shortcut syncs through iCloud to the watch, phone, and Mac, so it's built once.

**Apple-specific notes:**
- Turn on **Show on Apple Watch** in the shortcut's details. Test it on the watch over Wi-Fi/LTE *and* when it's only connected through the phone.
- Watch shortcuts time out quicker than the phone's, so the endpoint must answer within ~8 seconds (hence `?wait=1` with the fallback reply).
- Add the **Shortcuts complication** to the main watch face with "Chart It" as the single tap target. Put "Brain Dump" on a second face or in the Shortcuts app.
- Store the capture token in the shortcut as a **Text** action at the top, so it's easy to rotate.
- **Siri phrases:** "Chart it" and "Brain dump" (short, and hard to mishear). Test both from the watch before building anything on them.

**Android (not needed now)** — if ever needed, the HTTP Shortcuts app can POST to the same endpoint.

### 9.5a Phase 0 — Start Capturing Today

Don't wait for the app to start clearing your head.

1. Make the watch shortcut now, but point it at **Append to Note** ("Capture Outbox" in Apple Notes). Add a timestamp line before each entry.
2. Use it daily while Phase 1 is being built.
3. Phase 1 includes `php artisan capture:import outbox.txt`, which feeds each timestamped entry through the normal parser. Nothing captured before launch is lost.
4. At launch, swap the shortcut's action to **Get Contents of URL**. Everything else about the shortcut, and your habit, stays the same.

**Endpoint requirements (built in Phase 1):**
- Accepts a bearer capture token *or* a session
- The capture row is written before parsing
- Idempotent on `(token, captured_at, text hash)`, so a watch retry never creates duplicates
- Returns `{ capture_id, status, item_count, spoken_confirmation }`
- The confirmation is short and specific: "Got it. Two tasks, one idea." or "Got it. One needs a decision."

### 9.6 Parser Test Bench

- `tests/parser/fixtures.yaml` holds utterances with their expected actions (see Appendix B).
- `php artisan parser:eval` runs the fixtures against the live model and reports the differences.
- The Settings page has a "try an utterance" box with a dry run (nothing gets executed).
- Run the eval before changing the prompt.
- A unit test asserts **every action in the executor's enum appears in the prompt text** (Section 9.3), so the two can't drift.
- A unit test covers the timezone helper with an evening timestamp, since an off-by-one day there quietly breaks every cadence.

### 9.7 Brain-Dump Mode

For when my head is full: one long recording, many unrelated things.

- Triggered by the "Brain Dump" shortcut (`mode: "dump"`) or automatically when the text is longer than ~60 words.
- The parser **splits first, then classifies.** It breaks the text into separate items, then decides for each one: task, idea, someday project, event, waiting-on, person fact, or activity.
- Each item becomes a `capture_items` row, and each is executed or sent to triage **on its own**. One confusing item never blocks the others.
- Filler ("um, also, oh and another thing") is ignored. Repeated items within one dump are merged.
- Confirmation: "Got it. Six items: four tasks, one idea, one needs a decision."
- The capture detail view shows the original text with each part highlighted and linked to where it went. That makes a quick "yep, that's right" check possible.

### 9.8 The Never-Lose Guarantee

Clearing my head only works if I trust the system completely. So:

1. **The words are saved first.** Raw text is stored before any AI call.
2. **Failure falls back to the Inbox, never to nothing.** If parsing fails, times out, or the API is down, the raw text becomes an Inbox task titled with its first ~8 words and a link to the capture. The parse is retried automatically up to 3 times.
3. **Offline still captures.** The shortcut writes to a local outbox. The PWA writes to IndexedDB.
4. **Everything is undoable.** Each filed item has Undo in the notifications feed for 7 days.
5. **Nothing goes stale in silence.** Captures stuck in triage for more than 48 hours show on the briefing. Inbox and Ideas counts are always visible.
6. **Capture history is kept.** Every capture is searchable, with its transcript and where each piece went.

### 9.9 Audio Path (Phase 2, optional)

On-device dictation is fast and free but can garble long rambles or names. If that becomes a real problem (the two-week rule applies):

- A second shortcut records audio (**Record Audio**) and posts the file to `POST /api/capture/audio`.
- The app stores it privately and a queued job sends it to a speech-to-text service (Whisper-class or similar; pick at build time based on price and accuracy).
- The transcript then goes through the same parser as always. `transcript_provider = stt_service`.
- The audio is kept for 30 days for re-transcription, then deleted.
- A custom vocabulary list (names of people, projects, and clients) is sent to the speech service when supported, and always sent to the parser.

Trade-off: slower (upload + transcription) and a small per-minute cost, but better with long dumps and proper names.

### 9.10 Ideas & Someday

These give thoughts a place to land so they stop taking up space in my head, without turning into tasks that nag.

- **Ideas** (`notes`, kind=thought): shown on an **Ideas** screen, newest first, one tap to turn into a task, project, or note. They never appear on the briefing, except the occasional resurfaced one (Phase 4).
- **Someday projects** (`projects.lifecycle = someday`): listed under Ideas → Someday. **Start** moves one to active and asks for a domain and cadence.
- **Keep / Archive** on each idea stamps `reviewed_at`. "Aging" then means *unreviewed*, not merely old, so an idea I've deliberately kept stops nagging. *(Ref. note 7)*
- **Weekly glance (optional, no score):** when 3+ ideas have gone 30 days unreviewed, the briefing shows one roll-up line ("12 ideas aging — review"), at most once a week. I review when I choose. No ritual, no checklist.

---

## 10. Out of Scope: Publishing and Public Content

Publishing and public content were considered and deliberately cut. The owner-approved exception is a minimal Chart identity page at `/` with one sign-in button, no user data and no signup. Recorded here so publishing is not rediscovered as a good idea in six months.

**What was considered:** marking notes, quotes, journal excerpts and books as public so they'd appear on a personal site served by this same app, with feeds, tag pages and redaction of other people's names.

**Why it's out:**

- It serves a different goal. This system exists to get things out of my head and tell me my next move. A website is an audience project, and mixing the two means the audience project quietly sets the priorities.
- It's the expensive kind of feature: public templates, SEO, feeds, image handling, name redaction, and a permanent leak risk between private rows and public pages.
- The reference implementation has no public side at all, and its author publishes on a separate platform instead. There's no evidence this belongs inside the dashboard.
- A website also wants to be designed, themed and measured. None of that work makes my day easier.

**What happens instead:** if I want a personal site, it's a **separate project** — its own repo, its own domain, its own scope document. If it ever needs content from here, the clean seam is a **read-only export** (a one-off dump, or a token-gated endpoint the other site pulls from), not public routes inside this app. That seam is a day's work later and costs nothing now.

**The one piece that stays** is the content pipeline (Phase 5): tracking something I publish *elsewhere* from idea to shipped, with follow-up tasks and a "mark shipped" stamp that resets the area's cadence. That's operations, not publishing.

## 11. Integrations

| Integration | Phase | Notes |
|---|---|---|
| Google Calendar | 1 | Incremental sync with sync tokens every 15 minutes. App-created events are pushed right away. Watch channel is optional later. |
| Web Push / Pushover | 1 | Task reminders, approval requests |
| Inbound email | 2 | Forwarding address (Mailgun/Postmark inbound or a Gmail label) |
| Gmail watcher | 2 | Read-only scopes plus Drive file create for attachment routing. Approval-first. |
| Open Library | 4 | Book search and covers |
| Claude Vision | 4 | Journal and document OCR |

---

## 12. Cost Estimate (monthly, rough)

| Item | Estimate |
|---|---|
| VPS (2–4 GB) | $6–24 |
| Server management (Forge/Ploi), if not already paid for | $0–12 |
| Object storage (R2/S3) | $0–2 |
| Anthropic API (≈15–20 captures/day + OCR + small jobs) | $5–15 |
| Inbound email service | $0–15 |
| Domains | ~$2 amortized |
| **Total** | **~$15–70**, the low end if existing hosting is reused |

Claude plan costs for building with Claude Code are separate. These figures are estimates and should be checked against current pricing before committing.

---

## 13. Security & Privacy

- 2FA required. Registration disabled. Session timeout on the app domain.
- **The hostname is not a security layer.** Certificates issued for a specific name appear in public Certificate Transparency logs, so use a **wildcard certificate** for the domain and rely on 2FA, login rate limiting and a rate-limited capture endpoint. An access gate in front of the host (e.g. Cloudflare Access) is an optional second door.
- Capture tokens: 64+ random characters, stored hashed, limited to `capture:write`, rate-limited, revocable, and shown only once.
- **Operational data is authenticated.** A test enumerates all app and package routes and verifies the exact entry-route allowlist from Section 3.4. Token-protected APIs are checked separately when implemented. Any new public route fails that test.
- Private media is served through signed, short-lived URLs only.
- Gmail and Drive actions are logged in `action_logs`. Destructive actions are never automatic.
- Nightly encrypted database backup + weekly media backup to separate storage. Restore tested once per quarter.
- Data export (JSON + media zip) from Settings.

---

## 14. Build Guardrails (the "honesty pass" rules)

Before adding anything not in the current phase:

1. **Explain it back:** ask Claude to restate the problem in two sentences. Is it a recurring problem or a passing annoyance?
2. **Two-week rule:** I've felt the gap for at least two weeks of real use.
3. **Subtraction check:** could removing or simplifying something solve this instead?
4. **Maintenance check:** will I still want to maintain this in six months?
5. **What breaks if I leave it?** If the answer is "almost nothing," make the smallest fix and move on.

Deferred items go in **`BACKLOG.md`**, each with *what*, *why it's deferred*, and *the earliest sensible date*. The governing rule: an item leaves that list **when its absence actually hurts**, not because it's written down. Retiring a feature means flagging it off and keeping its data for 30 days before deleting anything. *(Ref. note 15)*

Every quarter, do an **honesty pass**: list each screen and module, and mark it *earning its place*, *tolerated friction*, or *there because I'm proud of it*. Remove or simplify the last two.

---

## 15. Claude Code Kickoff Prompt

```
Read SCOPE.md in full before doing anything.

We are building "Chart" — a single-user, fully private personal operations
system on Laravel + Vue 3 + Inertia + MySQL, using my existing starter kit.
It runs at chart.internetmedicineman.com behind a login, installed as an
iPhone PWA. The only public identity page is the minimal sign-in landing
page; there is no public operational content — see Section 10. Use the vocabulary in
Section 1.1 for screen names (Chart, Intake, Bench, Rounds, Vitals).

Working rules:
- Build in the phase order in SCOPE.md Section 6. Do not start a later phase
  until I say the current phase has passed its use gate.
- Use the data model in Section 7. Ask before adding or changing tables.
- Status is computed (Section 8). Never add manual status fields except
  projects.lifecycle.
- Before building anything not listed for the current phase, restate the
  problem back to me in two sentences and wait for confirmation (Section 14).
- Apply the explicit entry-route allowlist and private operational boundary
  in Sections 3.4 and 13, including package routes.
- Write feature tests for the state engine, the action executor, and the
  authenticated-routes assertion.
- Commit after each working piece with a clear message.
- Explain non-obvious decisions in code comments.
- When SCOPE.md doesn't fit reality, stop, flag it, and propose a change to
  SCOPE.md first.

First steps:
1. Summarize SCOPE.md back to me in under 15 bullets.
2. Inspect the starter kit and list what already exists (auth, 2FA, billing,
   teams) and what we'll disable or ignore.
3. Confirm hosting, domains (app. vs root), storage bucket, and Google Cloud
   project setup, with step-by-step instructions for anything missing.
4. Propose the Phase 1 build order as a checklist, then begin.
```

---

## 16. Parked Ideas (kept, not built)

- **Evening review / coaching mode.** A nightly check-in with scores and a weekly recap works best when someone else reviews it and guides you. Used alone every day, it can turn into grading myself. Possible future: Claude as an adaptive reviewer that I call up when I want it, never on a schedule. Keep notes in `docs/parked/coaching.md`.
- **Two-silo restructure.** Handled by the `sphere` column. Revisit only if the sphere filter isn't enough.
- **Native widget app.** Only if the Scriptable widget in Phase 4 proves too limited. The script does the same job in an hour instead of a weekend. *(Ref. note 16)*
- **Multi-tenant product.** See D3.

---

## 17. Decision Log

| # | Decision | Why | Revisit when |
|---|---|---|---|
| D1 | Laravel/VILT + MySQL instead of Next.js/Supabase | My main stack. Every required feature (PWA, auth, storage, realtime, queues, OAuth) has a mature Laravel equivalent. Easier to maintain long-term. | Never, unless offline-first becomes essential |
| D2 | Single-user | It's a personal system, and building for myself first shows what's actually valuable | After Phase 4 + 60 days of use |
| D3 | No multi-tenant now, but `user_id` on all data | Building for myself first avoids guessing at what others would want. The column keeps the option open cheaply. | If others ask for it repeatedly *and* the Section 14 test passes |
| D4 | **No public operational content.** Runs on one private subdomain; a minimal identity/sign-in page is permitted by D24. A personal site, if ever, is a separate project (Section 10) | Different goal, large surface, permanent leak risk, and unproven — the reference implementation has none either | Only via a read-only export seam |
| D5 | Domains → Projects, Inbox from day one, `sphere` label instead of a silo layer | Lets me split personal/work without a structural rebuild | If sphere filtering proves insufficient |
| D6 | Three computed states (my move / waiting / quiet) drive the briefing | Matches how client work and most other work actually behaves | — |
| D7 | No scores, shutdown ritual, or habit grades. Top 3 + optional tomorrow's-focus line instead. | Subtraction over addition | See Section 16 |
| D8 | Tasks can touch a domain directly | Writing that doesn't need the content pipeline still resets cadence | — |
| D11 | One-button watch/phone capture ships in **Phase 1**, built early | Getting thoughts out of my head is the main reason for this system | — |
| D12 | Device dictation by default, server-side audio transcription optional | Free and instant covers most captures. Add audio only if accuracy hurts. | After 2 weeks of watch use |
| D13 | Ideas and Someday projects are separate from tasks | Thoughts need a home that doesn't nag or crowd the briefing | — |
| D14 | Never-lose guarantee: save the text first, fall back to Inbox | Trust is what lets me stop holding things in my head | — |
| D15 | Apple-first capture (Apple Watch + iPhone + Mac via one iCloud-synced shortcut); Android deferred | That's the hardware I use | If the device changes |
| D16 | Phase 0: capture to Apple Notes now, import at launch | Build the habit before the app exists | At Phase 1 launch |
| D22 | Named **Chart**, at `chart.internetmedicineman.com`, on the persona domain rather than the LLC domain | The LLC domain may be rebranded or restructured; the persona domain is permanent. Clients never see this anyway. | — |
| D23 | A place name, not an assistant persona | A persona invites conversational features and advice, which this system deliberately doesn't give. A future agent can carry a personality under its own name. | — |
| D18 | Thresholds, scoring, dedup buckets and roll-ups adopted from a working implementation rather than invented | Months of someone else's tuning, free. See REFERENCE-NOTES.md. | Week 1 of real use |
| D19 | `needs_review` instead of blocking triage for middling confidence | A mis-filed item is recoverable; a withheld one gets forgotten | — |
| D20 | Parked state on domains and projects, plus a per-subject quiet switch | Permanent nagging trains me to ignore the panel | — |
| D17 | Installable iPhone PWA on Larafast (VILT) | Installability, push, and offline capture are front-end features the stack doesn't limit. The iOS gaps are the same on any stack. | If widgets or share-sheet become a real need → native wrapper |
| D24 | Minimal Chart identity page at `/` with one sign-in link; no registration or public data | Owner approved October 7, 2026. Preserve a simple entrance without a marketing site | If redirecting `/` directly to login becomes preferable |
| D25 | Approved C/chart icon; persistent desktop sidebar and mobile bottom navigation | Owner approved icon and implementation checklist October 7, 2026 | After real-device use |
| D26 | Owner identity is configured by user ID; only that account may authenticate or use an existing session | Prevent other starter/demo accounts from accessing Chart; existing real accounts are preserved | If single-user scope changes |
| D27 | Use Appendix A starter domains, with Ministry & Church under Personal | Owner confirmed October 8, 2026; domains remain editable in Settings | After real use |
| D28 | OpenAI first for capture, with standard API billing, replaceable parser and usage tracking | Owner approved October 8, 2026 after distinguishing ChatGPT/Claude subscriptions from API billing. Build and test durable capture before connecting a paid key | After real capture evaluation; provider choice remains revisable |

---

## 18. Open Questions (settle at kickoff)

1. Hosting: new VPS or existing server? Which management tool?
2. Inbound email provider for forward-to-capture?
3. Which Google calendars sync two-way vs. read-only?
4. Should ministry work be its own sphere or a domain under personal?

---

## Appendix A — Starter Domains (edit before seeding)

| Domain | Sphere | Cadence |
|---|---|---|
| Inbox *(system)* | — | — |
| Wondercide | work | 7 |
| Turbowebs & Clients | work | 7 |
| Writing | personal | 8 |
| Ministry & Church | personal | 14 |
| Family | personal | 14 |
| Personal / Home | personal | 14 |

Aim for 5–9 domains. Clients belong under Turbowebs as `ongoing` projects with their own cadence, not as separate domains.

## Appendix B — Parser Regression Starters

| Utterance | Expected |
|---|---|
| "Add a task to renew the SSL cert" | `create_task` → Inbox |
| "Remind me Friday at 3 to send Acme the invoice" | `create_task` → Acme project, due Fri 15:00, reminder |
| "Waiting on Mike for the logo files, should hear by Thursday" | `set_waiting` → person Mike, expected Thu |
| "Log 45 minutes on the church platform" | `log_activity` → project, 45 min |
| "My top three tomorrow are the report, the Acme call, and the PBE songs" | `set_top3` (or triage if ambiguous) |
| "Tomorrow's focus is shipping the capture endpoint" | `set_tomorrow_focus` |
| "Talked to Sarah, her anniversary is August 12th" | `log_interaction` + `create_person_fact` (recurring) |
| "Save a quote from Deep Work page 47: …" | `create_quote` → book match |
| "Wrote this week's article" | `complete_task` → recurring writing task (touches domain) |
| "Schedule lunch with Dan next Tuesday at noon" | `create_event` |
| "Mark the reviews thing done" (two matches) | triage with candidates |
| "Idea: a card game version of the memory verses" | `capture_idea` |
| "Someday I want to build a drone mapping service" | `create_project` lifecycle=someday |
| *Brain dump:* "Okay, call the plumber about the leak, uh, waiting on Chris for the server quote, idea for a sermon on patience, and I need to renew the domain by the 30th, oh and Sarah's birthday is June 3rd" | 5 items: `create_task` (plumber) · `set_waiting` (Chris) · `capture_idea` · `create_task` due 30th · `create_person_fact` (before Phase 3: saved as an idea) |
| *(API down)* anything | raw text → Inbox task, retried later, spoken "Saved. I'll sort it shortly." |

## Appendix C — After Each Phase

1. Did I use it every day of the use gate?
2. Did I capture by voice more than by typing?
3. Did the briefing surface something I'd otherwise have missed?
4. Did anything feel like a form or a chore? → put it behind Edit or remove it.
5. Is the Inbox growing without triage? → make it more prominent, or accept that it's a backlog.

## Appendix D — Repo Docs to Add After Phase 1

- `README.md`: setup and deploy notes
- `CHANGELOG.md`
- `BACKLOG.md`: deferred items with a reason and an earliest date (Section 14)
- `infrastructure/schema.sql`: the squashed schema, kept current, used for fresh installs
- `docs/runbook.md`: backups, restore, rotating tokens, re-authing Google
- `docs/parked/`: ideas from Section 16
- `tests/parser/fixtures.yaml`

---

*End of SCOPE v2.0*
