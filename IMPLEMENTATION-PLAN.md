# Chart implementation plan

Prepared October 7, 2026 from `SCOPE.md`, `REFERENCE-NOTES.md`, and the existing application. This is the build order and progress record; checked items below are implemented, with device/setup gaps stated explicitly. The original scope remains the authority, with the owner's new requirements for a minimal landing page, login-only access, a thumbnail/PWA icon, and responsive app navigation incorporated below.

Chart should make it easy to get a thought out of your head and see what needs your attention. The first working release must prove reliable capture and computed work states before expanding into email, relationships, the library, or content production.

## Current progress

Foundation implemented October 7, 2026: minimal landing/login, owner-only authentication, required two-factor enrollment, protected package routes, retired signup/marketing/billing/admin routes, persistent responsive shell, and static PWA shell/icons. The owner confirmed successful local and production login and 2FA on October 8.

Minimal work records implemented October 8, 2026: owned domains and system Inbox, projects and lifecycle, tasks with due dates/times and priority, completion/reopening, soft deletion/recovery, ideas and Someday projects, minimal people storage for upcoming waits, and timezone settings. Bench, project detail, manual Intake, Ideas and work settings are available with desktop/sidebar and phone/bottom navigation. The initial Briefing shows capped due/overdue tasks and real counts. It does not yet include Top 3, cadence states, waits, Calendar or observations.

The owner approved Appendix A domains with Ministry under Personal. `php artisan chart:setup` initializes them idempotently without resetting edits or re-creating deleted starter domains. The new migration and setup have been applied to local MySQL. Production still needs the new code, `php artisan migrate --force`, `php artisan chart:setup`, built assets and refreshed route/config caches.

Production auth recovery: rebuilding configuration/restarting PHP fixed stale owner settings. The subsequent 2FA failure was an invalid MAC on the stored secret; re-enrolling the owner on production fixed it. Keep production's APP_KEY stable across releases. `chart:owner <email> --check` now reports owner/account alignment without changing credentials.

Validation: 42 PHP tests passed (220 assertions); 17 optional starter tests skipped because their features are disabled. Four service-worker privacy/fallback tests passed. Client and SSR production builds succeeded, route cache compiled successfully, and PHP formatting/diff checks passed. Browser verification covered the actual landing/login and an isolated test owner's two-factor/recovery login, phone navigation, settings and persistent desktop sidebar collapse. The preview database is separate from the real account.

Work-record validation: 59 PHP tests passed (472 assertions), with the same 17 disabled starter-feature skips. Client/SSR builds, route-cache compilation, Pint and diff checks passed. The migration also ran successfully on local MySQL. An isolated browser account verified quick task entry, project creation, Inbox-to-project movement, completion/reopening, idea saving and the mobile More menu. A phone-width Bench search overflow was found and fixed; the verified content width is 390px at a 390px viewport. Screenshots were saved outside the repository, and preview data was not added to the owner's database.

Remaining foundation acceptance: real iPhone installation, Android install if available, and production deployment. The service worker caches only static assets and a non-sensitive fallback. The new Intake capture composer has the device outbox described below; the older manual quick-add forms still require a network connection.

## Capture foundation — October 8, 2026

The owner approved OpenAI with standard API billing, a replaceable parser, usage tracking, and building storage/recovery before connecting a paid key. The existing Larafast social-content helper remains untouched; capture uses its own bounded Responses API adapter with strict structured output and `store: false`. The configurable starting model is `gpt-6.1-sol`; account availability and live quality are not verified yet.

Implemented in this checkpoint:

- Owner/session/2FA-protected `POST /captures` returns `202` only after persisting original text, a user-scoped UUID request key, the original capture timestamp, and timezone. Changed words/time cannot reuse a request key. No page view makes an AI call.
- Dedicated asynchronous `captures` queue, durable attempt/lease state, scheduled recovery after lost dispatch or worker interruption, three automatic parse attempts, one linked Inbox fallback, and conservative replacement of that fallback only if it has not changed.
- One action registry supplies prompt descriptions and the output schema. Initially supports creating tasks, ideas, and active/Someday projects. Server validation and owned fuzzy-reference resolution send ambiguity, unsupported actions, malformed dates, untraceable excerpts, and uncovered words to review. Exact repeated proposals within a dump are merged.
- Independently executable items, per-item review/retry, action audit/snapshots, and seven-day undo that preserves subsequent edits and project children. Pending siblings remain recoverable if processing is interrupted during manual review.
- Searchable Intake history and capture detail with original-word highlighting, factual filing outcomes, review controls, and per-attempt model/token usage. Tasks, ideas and projects with middling confidence display “Check this.” Manual editing/keeping clears that flag. Captures waiting over 48 hours surface on Chart.
- Native IndexedDB outbox in the open app. Stable IDs/timestamps survive retries and session expiry; entries are removed only after a `202` with a capture ID. Owner ID is checked server-side before replay. Pending words are inspectable in Intake; replay occurs on app open, visibility return, or reconnect. A cold offline launch still shows the static reconnect page; this is not full offline launch support.

Queue choice: use Laravel's existing database driver for this foundation, configurable to Redis through `CHART_CAPTURE_QUEUE_CONNECTION`. This keeps the path asynchronous even when the starter's default queue is `sync`, without adding dependencies. Horizon and production worker supervision remain pending; this does not mark the full Redis/Horizon checklist item complete.

Schema additions implement the previously proposed durable capture identity/execution changes: captures, capture_items, capture_attempts, action_logs, jobs, and project needs_review. No existing work records are removed. The migration has run on local MySQL. AI is disabled by default; all verification used controlled responses, not paid API calls or real owner records.

Verification: 84 PHP tests passed (637 assertions), with 17 existing disabled starter-feature skips. All four service-worker privacy tests passed. Client and SSR builds, Pint, route-cache compilation/clear, and diff checks passed. An isolated SQLite/browser account verified real IndexedDB writes, offline replay, stable IDs across session expiry and reload, retention of composer text when device storage fails, triage resolution, undo, and a 390px layout with no horizontal overflow. Preview records stayed out of the owner's MySQL database. Live model behavior and iPhone/Watch testing remain unverified.

Activation/deployment:

1. Deploy source and built assets; run `php artisan migrate --force` (and `php artisan chart:setup` if the previous work-record release has not been initialized).
2. Configure `OPENAI_KEY` privately in the local/Forge environment, confirm API billing/model access, and enable `CHART_AI_ENABLED=true` only when ready to process saved captures. `CHART_AI_MODEL=gpt-6.1-sol`; `CHART_CAPTURE_QUEUE_CONNECTION=database` by default. Enabling processing lets recovery pick up previously saved captures with remaining attempts.
3. Supervise `php artisan queue:work database --queue=captures --sleep=1 --tries=1 --timeout=70`. Use `redis` instead of `database` when that connection is selected. Keep the queue retry_after above the worker timeout (existing setting: 90 seconds). Application-level retries are persisted on captures; the queue job itself uses one attempt.
4. Ensure Forge runs `php artisan schedule:run` every minute. `capture:recover` redispatches due/interrupted captures; it can also be run manually. Refresh production config and restart workers after environment/code changes (`php artisan config:cache`, `php artisan queue:restart`).
5. Evaluate real Appendix B examples before accepting model quality. Until then, local storage/execution/recovery are verified, but live classification, cost, latency and account access are not.

Still open in capture checkpoint 3: scoped watch tokens and `/api/capture?wait=1`, Shortcut setup/device tests, `capture:import`, live parser quality acceptance, full notifications feed/push, cold offline capture launch, and the remaining Phase 1 actions as their underlying records become available. Undo currently lives with each capture outcome. The full never-lose/device acceptance gate is not yet complete.

## Parser test bench — October 8, 2026

Settings now has a capture preview that makes one explicit API request and shows proposed filing, Check-this flags, or triage without creating captures or work records. It uses the same parser context, transcript coverage checks, validation and owned-reference resolver as real capture. It works while automatic sorting is disabled, requires owner login and confirmed 2FA, and is limited to five requests per minute. Preview text/results are not stored in Chart. Provider errors distinguish missing keys, authentication, model access, billing, throttling, connection failures and unusable responses without exposing provider messages or credentials.

`php artisan chart:ai-check` reports configuration presence without printing secrets or contacting OpenAI. It cannot establish worker health, account billing, or model availability.

`tests/parser/fixtures.yaml` contains 25 synthetic cases in JSON-compatible YAML: Appendix B starters, mixed/five-item dumps, repetition, ambiguous dates, invented-deadline protection, local evening dates and both DST transitions. The grader checks classification, specified fields, confidence and transcript coverage. Actual owned-reference resolution is exercised by preview feature tests. Deferred capabilities currently expect triage; those cases passing would not establish full Phase 1 acceptance.

- `php artisan parser:eval` lists cases without requests.
- `php artisan parser:eval --case=inbox_task --live` makes one controlled live request.
- `php artisan parser:eval --live` runs all cases; `--model=<id>` overrides only that run.
- Reports include model, prompt/fixture hashes, differences, duration and token usage in private `storage/app/private/parser-evals/` files. Evaluation uses only synthetic context and never writes work records. Provider failures stop the run; unattempted cases are counted separately.

The owner configured a local API key. Two single-case live attempts returned HTTP 429; safe error inspection identified billing/credits language. No usable model response was received, so model access, quality, latency and cost remain unverified. API billing must be resolved before rerunning the controlled case and then the fixture set. Automatic sorting remains disabled and production deployment/worker setup remains pending.

Verification: 99 PHP tests passed (751 assertions), with 17 existing disabled starter-feature skips; four service-worker checks, client/SSR builds, Pint, route-cache compilation/clear and diff checks passed. An isolated browser account verified missing-key messaging, text entry, disabled request submission and desktop/390px phone layout with no page errors. The initial resize check caught an in-progress sidebar transition; after waiting for the transition, the phone content had no horizontal overflow. No owner work records were changed by these checks.

## Initial audit

| Area | Finding | Treatment |
|---|---|---|
| Framework | Installed Laravel 13.8.0; Vue 3, Inertia 2, Tailwind 4 and DaisyUI are declared dependencies | Keep the existing stack and application structure |
| Authentication | Fortify/Jetstream password login, password recovery, optional TOTP setup, session management | Reuse; require owner access and completed 2FA enrollment for operational pages |
| Account creation | Registration enabled; Socialite callback and magic-link request both call `User::firstOrCreate` | Disable all three entry paths, including server routes |
| Teams | Feature disabled, but models, migrations, actions and navigation branches remain | Keep disabled; avoid unrelated destructive cleanup |
| Marketing and billing | Public blog, changelog, roadmap, coming-soon, sitemap, OG generator and checkout routes; package webhook routes; demo dashboard | Remove from the exposed route/UI surface; retire unused integrations without deleting existing data |
| Administration | Separate Filament admin login and resources for users, billing and content | Disable the panel for the first Chart release; it is an alternate login and account-management surface |
| Database seed | Seeder attempts hundreds of demo users/orders | Replace with an idempotent owner-and-domains setup; never run the current seeder as Chart setup |
| Navigation | Top navigation in `resources/js/Layouts/AppLayout.vue`; dashboard is a SaaS demonstration | Replace with the Chart shell and real operational views |
| PWA | No manifest, service worker or PWA build plugin in the inspected app entry points | Add during the foundation checkpoint |
| Chart functionality | No Chart domain/task/capture models or migrations in the source tree | Build Phase 1 incrementally |
| Background work | Redis configuration exists; scheduler is empty; Horizon is not a direct dependency | Configure actual asynchronous processing and worker supervision |
| Tests | Starter auth/account/billing tests; no isolated `.env.testing` present; example uses SQLite | Establish an explicitly isolated test database before running database-refresh tests |

The application boots and its route list currently contains 128 routes, including package routes. This is a source/route audit, not a production security assessment. `CLAUDE.md` lists older package versions than the installed application; use the lockfiles and installed versions for implementation decisions.

## Scope decisions before implementation

The landing page amendment is recorded in the scope and its decision log; the owner permitted either this small entrance or a direct login redirect. It is a branding-and-login exception, with no user records, signup, publishing, lead capture or pricing.

The following are proposed clarifications to apply before their dependent work:

| Issue | Proposed resolution |
|---|---|
| Offline capture appears in the general PWA requirements and never-lose guarantee, but Phase 2 lists its implementation | Move the minimal IndexedDB capture queue into Phase 1 so the initial installed app can meet the guarantee. Keep email and optional audio in Phase 2. This is a phase change to settle before building the queue |
| Three states in Section 4 versus `OK` in Section 8 | Treat Quiet, My move and Waiting as the three attention states, with a neutral OK result for healthy items. Excluded/parked is separate from attention state. Preserve Quiet > My move > Waiting precedence |
| Target-date projects with no tasks differ between Sections 4 and 8; domain roll-up is stronger in the reference notes | Define explicit test cases. Proposed: target-date work with open tasks is My move; near/overdue target dates surface factually even with no tasks. A domain inherits the highest attention state of eligible children |
| Quiet disabled, parked subjects, null touch dates and project-level hand-offs are underspecified | Decide whether quiet-off suppresses only cadence alerts; ensure parked parents suppress descendant alerts. Never-contacted people with cadence surface immediately. New projects/domains use a documented creation-date baseline. Include project `holder`/`holder_since` in wait rules |
| Capture idempotency is required but has no column/index | Add a user-scoped capture request key and unique constraint; derive a stable fallback from token, original capture time and text hash. Persist the same key and capture time through outbox retries. Session captures need equivalent protection |
| Retry and undo behavior needs durable per-item execution identity | Enforce uniqueness for capture items/executions and store reversible before/after effects. Track the fallback Inbox item so a later successful parse cannot leave duplicate work. Undo must not overwrite subsequent manual edits |
| Two-way Google sync lacks credential, deletion and conflict storage details | Add encrypted connected-account credentials, selected-calendar settings, and durable sync/conflict metadata before Calendar implementation; do not reuse the social-signup callback |
| Every user-data table should have `user_id`, but several child-table sketches omit it | Reconcile the convention and schema before migrations; enforce parent ownership and scoped relationships regardless. Use `(user_id, plan_date)` for daily-plan uniqueness |
| Push and recurrence require more durable state than the sketch includes | Specify push subscriptions, reminder delivery deduplication, recurrence occurrence identity and undo interactions before those features are built |
| Unauthenticated-route list omits necessary auth/PWA plumbing | Explicitly enumerate methods and paths for landing, login, 2FA challenge, password recovery, health and any necessary CSRF route; separately verify token-protected APIs and public static assets. Include package-registered routes in tests |
| Scriptable widget appears in Phase 4 and in the parked list | Keep the explicit Phase 4 deliverable; native widgets stay parked |

Schema changes above are proposals, not applied migrations. Dates require two treatments: actual timestamps stored in UTC, and date-only values retained as local calendar dates. All-day Google events should preserve their date boundaries rather than being shifted as timestamps. Test local midnight and daylight-saving transitions.

## Landing, login and navigation

The public `/` page should fit on one screen: Chart mark, name, a short description such as “Your personal operations system,” and one Sign in button. An authenticated visit can redirect to the Briefing. `/login` uses the same visual identity with email, password, remember-me and password recovery; TOTP/recovery codes remain part of sign-in. There are no social login buttons, magic-link signup, registration links or invite flows.

Use a persistent Inertia app layout with a shared navigation definition:

| Surface | Layout |
|---|---|
| Desktop | Left sidebar: Chart, Bench, Intake, then People, Library, Content, Ideas; Settings at the bottom. Small page header for title, context and notifications |
| Tablet | Collapsible sidebar, with content width determining the switch to phone navigation |
| Phone/PWA | Bottom tabs: Chart, Bench, Intake, Library, More. More contains People, Content, Ideas and Settings as those modules become available |
| Phase 1 | Show working destinations only. Use Chart, Bench, Intake and More on mobile until Library ships; put Ideas, notifications and Settings under More |
| Capture | A prominent capture action available throughout the shell. Intake opens with a large composer and accessible inbox/history views. Dictation uses the keyboard mic; do not imply the app is recording audio |

Add visible active states, icon labels, keyboard focus, generous touch targets, safe-area padding and content clearance above the bottom bar. Keep the capture composer usable with the software keyboard open. Give a stable route to each main view so Back, refresh and deep links work. Use state rows, factual timestamps and Edit panels rather than crowded forms or draggable boards.

Rounds and Vitals describe review and state information; they do not need extra top-level modules in Phase 1.

## Phase 1 build checklist

### 1. Private foundation and app shell

- [x] Record the authorized landing/icon/navigation amendment. Schema/behavior decisions remain scheduled before their dependent features.
- [x] Force tests onto an isolated in-memory SQLite database, confirm the frontend/SSR builds, and adapt starter tests to private single-owner access.
- [x] Remove registration GET/POST and social/magic account-creation routes. Disable unused public routes, billing webhooks, the admin panel, and public profile-photo handling.
- [x] Provision the owner securely without committed credentials and replace the demo seeder. Owner password/2FA setup remains a user step.
- [x] Seed the system Inbox and agreed domains with the minimal work records checkpoint.
- [x] Enforce owner authorization and confirmed 2FA on operational routes, with a restricted enrollment/recovery path to avoid lockout.
- [x] Build the simple landing page, focused login and persistent sidebar/bottom-navigation layout.
- [x] Add manifest, root-scoped service worker, static offline fallback and icons. Start the installed app at the authenticated Briefing route; login redirects back there.

Acceptance: login works for the owner, signup and alternate account-creation URLs cannot create a user, guest requests cannot read operational data, and the shell installs and navigates correctly on a real iPhone. Route tests include package routes and exercise behavior rather than only matching middleware names.

### 2. Minimal work records

- [x] Add owned domains, system Inbox, projects, basic tasks, minimal people for waits, ideas and timezone settings. Daily plans/Top 3 remain in checkpoint 4.
- [x] Implement basic create/edit/complete/move flows and valid domain/project relationships. Keep setup fields behind Edit. Deleted tasks can be restored; project moves also update deleted tasks so restored work stays in its project domain.
- [x] Add the shared timezone helper, owner-scoped reads/writes and local-date tests, including both daylight-saving transitions.

Acceptance: a task can be created, assigned to a valid project/domain, completed and recovered without cross-owner access; the Inbox cannot be deleted. This is the smallest data foundation needed to make capture useful.

### 3. Reliable capture as early as possible

- [ ] Add raw captures, items, hashed scoped capture tokens, action logs and notification/undo records.
- [x] Persist incoming words and their idempotency key before dispatching any AI job; recover if job dispatch itself fails after the save. Session capture is implemented; token capture follows.
- [ ] Configure Redis queues/Horizon and retries. Return a quick `202`, with a bounded `?wait=1` path and factual server-derived confirmation.
- [x] Build parser context, action schema, reference resolver and executor from one action registry. Initially advertise tasks, ideas, projects and explicit triage; retain unsupported material for review. Live model evaluation remains pending.
- [ ] Deliver in-app typed capture, watch/phone shortcuts, brain-dump splitting, triage, capture history and `capture:import`.
- [x] Build safe per-item retry and seven-day undo for the implemented creation actions. One failed item does not block successful siblings or execute them twice. Later mutation actions need their own reversible effects.
- [ ] Implement the minimal offline outbox if moved to Phase 1: pending count, stable request keys and replay on open/visibility/online. Retain unsent text through auth expiry and require the same owner to resume submission.

Acceptance: one dump containing five unrelated items produces five individually traceable outcomes. Repeated requests do not duplicate work. API errors leave recoverable Inbox content. A failed queue dispatch is recoverable. Revoked tokens fail; low-confidence or ambiguous matches go to triage. No confirmation claims “saved” before server storage or an actual local outbox write succeeds.

### 4. Complete the work model and computed state

- [ ] Add milestones, subtasks, priorities, due dates/times, waits and expected response dates, recurring tasks and extra touch targets.
- [ ] Add activity entries with minutes, touch propagation, Top 3 and tomorrow's-focus line.
- [ ] Build the shared `WorkStateResolver`, cache invalidation and parent roll-ups; use it in both Briefing and Bench.
- [ ] Implement recurrence and completion/undo together so retries and undo cannot create extra occurrences or false cadence resets.

Acceptance: completing a task touches its intended subjects; overdue waits outrank ordinary due work; parked/someday items stay out of attention lists; exactly three tasks can be selected for a day; state updates after edits, not only after touches. Date-boundary tests cover Chicago evening timestamps and DST.

### 5. Briefing, Bench and project views

- [ ] Replace the SaaS dashboard with capped Briefing blocks, including honest empty states.
- [ ] Build Bench filters, domain roll-ups, project state strips, tasks, activity and weighted milestone progress.
- [ ] Surface triage older than 48 hours, pending captures and Inbox/Ideas counts.
- [ ] Add the initial nightly observations with scores, day/week deduplication, snooze/dismissal, auto-resolution and expiry.
- [ ] Avoid N+1 queries with aggregate counts and eager loading. Paginate history from the start.

Acceptance: a quiet project surfaces automatically; the same project has the same state on Briefing and Bench; block limits and overflow links work; unavailable later-phase blocks remain absent. Rule-based observations do not require an AI call on page load.

### 6. Calendar, reminders and launch readiness

- [ ] Connect Google as an authenticated integration, store encrypted tokens and configure selected calendars as read-only or two-way.
- [ ] Implement initial/incremental sync, expired sync-token recovery, deleted/cancelled events, recurring-event instances and a documented conflict policy. Queue outbound writes with stable identities; undo affects only the exact action it reverses.
- [ ] Show Now/Next and tomorrow's meeting load; add notification feed and Web Push and/or Pushover reminders.
- [ ] Configure production HTTPS, scheduler, workers, private storage, encrypted backups and a restore procedure. Implement scoped data export.
- [ ] Run the parser fixtures and scenario checks, install on iPhone, test watch capture on available connection types, and record real-device results separately from automated tests.

Acceptance: Calendar changes round-trip without duplicates; read-only calendars cannot be written; reminders fire once; expired sessions preserve unsent capture; logout cannot reveal private data from the service-worker cache; restore and export succeed. Phase 1 is complete only when the full day can be run from Chart, not when the shell looks finished.

## PWA and icon delivery

Cache versioned static assets and a non-sensitive offline page. Do not cache authenticated HTML, Inertia responses, private media or capture API responses. Handle expired sessions/CSRF with bounded retries and durable capture keys. Service-worker updates must not discard an open draft or unsent queue.

The installed app's launch destination should be the Briefing; the browser-facing landing page remains separate. The manifest's `start_url` supplies the preferred launch URL. See [MDN start_url](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Manifest/Reference/start_url).

The first concept is saved at `public/images/chart-icon-concept-v1.png`: a light C-shaped chart mark with three colored bars on a dark blue field. The owner approved it. The shell includes 192px/512px PNGs, a maskable declaration with the mark inside the central safe zone, a 180px Apple touch icon and a 32px favicon. The browser renders them correctly; real-device installation remains to be checked. See [MDN manifest icons](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps/Manifest/Reference/icons).

Request push permission from a Settings button, after installation where required on iPhone. Test the actual installed app rather than assuming Safari-tab behavior is identical. See [WebKit Home Screen Web Push](https://webkit.org/blog/13878/web-push-for-web-apps-on-ios-and-ipados/).

## Later phases and use gates

| Phase | Work | Gate before advancing |
|---|---|---|
| 1 | Foundation, reliable capture, computed work views, Calendar and reminders | 7 days of real use |
| 2 | Email capture, approval-first Gmail, desktop convenience and optional uploaded audio; offline queue here only if not moved forward | 7 days |
| 3 | Full People views, facts, interactions and cadence | 10 days |
| 4 | Photo-first Library, books, quotes, journal/OCR, private media, resurfacing and Scriptable widget | 14 days |
| 5 | Content stages, hand-offs and follow-up task templates | No additional gate specified |

Advance only after the owner confirms the current use gate has passed. Do not expose placeholder modules as if they work. Keep Reverb, native wrappers, coaching, habits, publishing and multi-tenancy outside this build.

## Setup information needed at the relevant checkpoint

These do not block planning or local shell work. Do not paste secrets into chat; configure them locally or in the deployment environment.

- Owner account identity and whether a real owner already exists in the database.
- Existing VPS versus new hosting, management tool, Redis/worker availability, DNS/TLS for `chart.internetmedicineman.com` and the `ops.` redirect.
- Confirm the Appendix A domains and whether Ministry remains under the personal sphere; that is the proposed default.
- OpenAI API key/billing/model access and notification channel before live capture/reminder integration tests.
- Google Cloud project/OAuth setup and which calendars can be written, before Calendar sync.
- Private storage bucket and backup destination before production attachments/export.
- Inbound email provider can wait for Phase 2.

The first implementation slice is checkpoint 1 followed immediately by the minimal records and durable capture path. Visual polish should support that flow without delaying the first trusted capture.

## Icon generation record

Generated with the built-in image-generation tool, with an opaque background. Original retained; workspace copy is `public/images/chart-icon-concept-v1.png` (1254 × 1254 PNG). The final generation prompt was:

> Use case: logo-brand. Asset type: square application icon and thumbnail for Chart, a private personal operations system installed as an iPhone PWA. Create one polished, distinctive, minimal app icon. Chart means both a personal chart and an operations dashboard; it is a calm tool, not a character. Subject: a bold, elegant C-shaped chart/document mark, incorporating two or three simple horizontal chart lines as one coherent symbol. Composition: one centered symbol, optically balanced, plenty of negative space; all meaningful parts inside the central circle with radius 40 percent of the square width, suitable for maskable PWA crops. Style: refined contemporary utility app identity, clean geometric edges, exceptionally legible at small size, strong foreground/background contrast. Opaque full-bleed background all the way to square edges, square canvas, no pre-rounded outer corners, no outer frame, no device mockup, no text or wordmark, no additional badges, no medical cross, no mascot, no photographic objects, no busy details. Deliver only the icon artwork, high quality square image.
