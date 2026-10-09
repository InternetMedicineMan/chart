# Chart implementation plan

Prepared October 7, 2026 from `SCOPE.md`, `REFERENCE-NOTES.md`, and the existing application. This is the build order and progress record; checked items below are implemented, with device/setup gaps stated explicitly. The original scope remains the authority, with the owner's new requirements for a minimal landing page, login-only access, a thumbnail/PWA icon, and responsive app navigation incorporated below.

Chart should make it easy to get a thought out of your head and see what needs your attention. The first working release must prove reliable capture and computed work states before expanding into email, relationships, the library, or content production.

## Current progress

Latest checkpoint: voice/text capture now clears existing waits and sets today/tomorrow’s Top 3 and tomorrow’s focus, with review, stale-plan protection, notifications and seven-day undo. Basic recurring tasks and capture completion are implemented; missed dates are skipped on the original schedule, as approved by the owner. The owner has no pre-launch notes to import, so `capture:import` is deferred. Milestones/subtasks, extra touch targets, Calendar, observations and push alerts remain open.

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
2. Configure `OPENAI_KEY` privately in the local/Forge environment, confirm API billing/model access, and enable `CHART_AI_ENABLED=true` only when ready to process saved captures. `CHART_AI_MODEL=gpt-6-luna`; `CHART_CAPTURE_QUEUE_CONNECTION=database` by default. The selected model and live evaluation are recorded below. Enabling processing lets recovery pick up previously saved captures with remaining attempts.
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

## Live model selection — October 8, 2026

API billing is restored and live Responses API calls now work. The selected capture model is **GPT-6 Luna** (`gpt-6-luna`), updated in `config/chart.php`, `.env.example`, and the local environment. Automatic sorting remains disabled. The GPT-3.5/GPT-4 model references are in the unused Larafast content helper; Chart capture does not call it.

Official [model comparison](https://developers.openai.com/api/docs/models/compare) and [Luna documentation](https://developers.openai.com/api/docs/models/gpt-6-luna) were checked on October 8: Luna is intended for focused tasks and supports structured outputs. Listed per-million-token input/output rates are $0.10/$0.50 for Luna, $2/$10 for GPT-6.1 Sol, and $10/$50 for GPT-6 Astra. Luna is the economical choice supported by these capture tests; this is not a claim that it matches Sol on every workload.

The initial Luna run passed 20/25. Live failures exposed underspecified excerpt coverage, fields inappropriate for ideas, and missing explicit handling of Library/focus requests. The action-registry prompt now requires complete verbatim spans including qualifiers and repeated wording, specifies idea-only fields, and preserves Library and daily-focus requests for triage. Server validation and fixture expectations were not relaxed. Five additional cases cover qualifiers, domain hints on ideas, another book quote, separated repetitions, and supported tasks mixed with Calendar requests.

Final results using the same prompt:

| Model/run | Passed | Input/output tokens | Estimated token cost | Median latency |
|---|---|---|---|---|
| Luna, all fixtures | 30/30 | 28,326 / 6,095 | $0.0058801 | 3.16 s |
| Luna, ten comparison cases from that run | 10/10 | Included above | $0.0022835 | 3.75 s |
| Sol, the same ten difficult cases | 10/10 | 9,465 / 1,621 | $0.03514 | 3.74 s |

These are single-run synthetic regression results, including five newly added examples. They are not an accuracy guarantee or a full Phase 1 acceptance test. Luna's longest request was 10.98 seconds. Costs use reported tokens and the listed standard text rates, not a billing invoice; caching, context growth and different captures change usage. No paid fallback or automatic model escalation was introduced. Sol remains available through `CHART_AI_MODEL` or an evaluation-only `--model=gpt-6.1-sol` override.

Private evidence in local `storage/app/private/parser-evals/`: Luna `20261008-154911-fa4a1fa2-6f9c-4f1c-9d2f-29a79f80caca.json`; Sol `20261008-154656-b2122af4-badc-4527-af68-83e28b429798.json`. Both use prompt hash `28f843138e5ba11d87b60e3aabfa1965a898e8db6cbdd9c846a2972e59a0567d`. All live inputs were synthetic; evaluations created no work records. Automated verification: 99 PHP tests passed, 751 assertions, 17 existing disabled-feature skips; Pint and diff checks passed. No frontend changes or dependencies were needed.

Production still needs deployment of these instructions and `CHART_AI_MODEL=gpt-6-luna` in Forge if the environment explicitly selects another model, followed by config cache refresh and worker restart. Next is supervised capture-worker/scheduler activation and real-use validation. Keep automatic sorting off until ready to process the saved backlog, since recovery may pick up earlier captures.

## Local capture activation — October 8, 2026

Automatic sorting is now enabled in the local environment with `gpt-6-luna`. Before activation, local MySQL had all migrations applied, a confirmed-2FA owner, zero captures, zero queued capture jobs and zero failed jobs. There was no backlog to process. The real local database was left free of verification records.

Live verification used a separate SQLite database and test owner, an actual database queue worker, and real OpenAI requests. Browser capture returned HTTP 202 in 44 ms for a single task and 18 ms for a mixed dump. The worker sorted both; the dump produced task, idea, Someday project and Calendar triage outcomes. Manual review filed the triage item, and undo reversed all six created records across those captures and the retry scenario. A capture saved while sorting was disabled successfully retried and replaced its untouched Inbox fallback. A fourth capture saved without dispatch was recovered by `schedule:run` → `capture:recover` and executed by the worker. All four API attempts completed on Luna, with zero failed queue jobs. Phone-width browser checks passed without page errors. Test server/worker processes were stopped afterward.

The actual local worker and scheduler were started from the project directory; several minutely recovery runs completed successfully:

```sh
php artisan queue:work database --queue=captures --sleep=1 --tries=1 --timeout=70
```

```sh
php artisan schedule:work
```

These are development processes, not installed macOS startup services. Restart them in separate terminals after they stop or after a reboot; keep Herd/MySQL running. Restart the worker after code/config changes. Production uses Forge supervision and cron instead.

### Forge rollout to apply

The owner requested settings and commands to apply manually; production has not been changed or verified by this activation checkpoint.

1. Push the local `master` commits to the repository connected to Forge (`git push origin master` from the local project), then deploy the latest application code through `45ce82d` (or later) and built client/SSR assets using the existing Forge deployment flow. Preserve the existing production `APP_KEY`, database configuration and owner account. Set the following in Forge's private environment, with a valid `OPENAI_KEY` added there privately:

   ```dotenv
   CHART_AI_MODEL=gpt-6-luna
   CHART_CAPTURE_QUEUE_CONNECTION=database
   CHART_AI_ENABLED=false
   ```

2. In `/home/forge/chart.internetmedicineman.com/current`, run:

   ```sh
   php artisan migrate --force
   php artisan chart:setup
   php artisan config:cache
   php artisan route:cache
   php artisan chart:ai-check
   php artisan schedule:list
   php artisan queue:failed
   ```

   `chart:ai-check` should show the key configured, Luna, database queue and sorting disabled. `schedule:list` should show `capture:recover` every minute. In Intake, review any saved/failed captures before activation: eligible earlier captures will be picked up automatically. Verify API access with Settings → Capture preview while sorting is still off; it makes one API call without filing records.

3. Create one Forge queue worker for this site: connection **database**, queue **captures**, processes **1**, sleep **1 second**, tries **1**, timeout **70 seconds**, and the same PHP version as the site. Its command is equivalent to:

   ```sh
   php /home/forge/chart.internetmedicineman.com/current/artisan queue:work database --queue=captures --sleep=1 --tries=1 --timeout=70
   ```

   Use Forge's supervised worker, not a command left running in an SSH session. The database queue's `retry_after` is 90 seconds; keep it longer than the 70-second worker/job timeout. Chart persists its own three parse attempts, so the queue job itself uses one attempt. See [Laravel queue workers](https://laravel.com/framework/docs/13.x/queues#supervisor-configuration).

4. Enable the site's Laravel scheduler in Forge, or create one scheduled job as user `forge`, every minute (`* * * * *`), with command:

   ```sh
   php /home/forge/chart.internetmedicineman.com/current/artisan schedule:run
   ```

   Keep only one scheduler entry for this site. See [Laravel scheduling](https://laravel.com/framework/docs/13.x/scheduling#running-the-scheduler).

5. Once preview, worker and scheduler are ready, change `CHART_AI_ENABLED=true` in Forge and run from `current`:

   ```sh
   php artisan config:cache
   php artisan queue:restart
   php artisan chart:ai-check
   ```

   Confirm Forge restarts the supervised worker. Capture a simple task through Intake, then a mixed dump. Check that sorting completes, review unsupported items, and try undo. Inspect `php artisan queue:failed` if the worker reports failure; capture-level errors also appear on the original capture. If sorting must be paused, set `CHART_AI_ENABLED=false`, rebuild config, and restart workers; new words remain saved in Inbox.

Deployment should continue running migrations, asset builds, config/route cache refreshes and `queue:restart` when new code is released. Production activation is complete only after the owner confirms these live checks on the deployed site.

## Device capture API and iPhone setup — October 8, 2026

The owner reports the previous Forge worker/scheduler rollout looks good. This is owner-confirmed production status, not direct server inspection. The next device test is **iPhone first, then Apple Watch**.

Implemented locally:

- `POST /api/capture` accepts a dedicated bearer capture token or the existing owner session. Tokens are 64 random characters plus a prefix, SHA-256 hashed in the database, write-only, owner-bound, and rejected if revoked or if the owner no longer has confirmed 2FA. Session requests retain the standard cookie/session/password-hash and request-forgery checks. This one endpoint has its own authentication boundary; other private/package routes stay behind owner login.
- Settings → Devices & Shortcuts creates and revokes tokens, shows device names and last accepted use, and reveals the secret only in the creation response. The plaintext is not stored in Inertia props/history, session flash, database, or browser storage. Capture details show their device label. A token grants no read or account-management access.
- Capture input accepts `text`, `source` (`ios`, `watch`, `mac`, `android`, `in_app`, `offline_queue`), optional `device_label`, ISO-8601 `captured_at` with timezone, UUID `request_key`, and `mode` (`single` or `dump`). Incoming words use the existing save-first queue and recovery path. Sources for email/audio integrations remain deferred.
- Stable request IDs are namespaced to the token. Without an explicit ID, the token, normalized capture timestamp and text hash identify retries. Without both an ID and a timestamp, each submission is new. Reusing an ID with different words or a different time is rejected. Token rotation changes the deduplication namespace: check Intake before replaying files created under an old token.
- `?wait=1` polls for at most an eight-second application wait budget; sorting remains in the queue. Completed results return current factual counts; unfinished work says “Saved. Sorting it now.” Pending execution is not mislabeled as a review decision. Every accepted response is HTTP 202. API successes/errors are JSON and no-store. Limits are 60 requests/minute per IP at the API boundary, 30/minute per owner, and 120/hour per token (including retries).
- Settings includes manual action-by-action guides for Chart It, Brain Dump, and Send Outbox. The iPhone guide saves an unchanged JSON file to an On My iPhone folder **before** making the request, then deletes only that file after a response with a capture ID. This avoids depending on an error handler after a failed Shortcuts network action. It is a setup guide, not an installed or signed Shortcut. [Apple web API guidance](https://support.apple.com/guide/shortcuts/apd2d448b2de/ios) and [Watch guidance](https://support.apple.com/en-ie/guide/shortcuts-mac/apd5888b0858/mac) informed the setup.

Verification: **116 PHP tests passed (891 assertions)**, with 17 existing disabled-feature skips; four service-worker privacy checks passed. Client/SSR builds, route-cache compilation/clear, Pint and diff checks passed. API tests cover real database-queue execution with a controlled model response, normalized retry identities, changed payload rejection, scope/owner/2FA/CSRF boundaries, revocation, rate limits, and bounded wait/completion/fallback responses. An isolated browser account verified owner/2FA login, token creation/hiding/reload privacy, cookie-free bearer capture, retries, revocation, device provenance and desktop/390px layouts without page errors. No paid API requests were needed for this slice and verification records stayed out of the real owner database.

The migration ran on local MySQL. The local capture worker was restarted with current code; the existing scheduler remains running. Real iPhone Shortcuts action names, file permissions, airplane-mode dictation/storage and replay must still be checked on the device. The iPhone's local folder cannot serve as a shared Watch outbox. Watch connectivity/offline testing, an installable Shortcut artifact, push notifications, and `capture:import` remain open; do not treat this as completion of the full never-lose capture gate.

Shortcut guide correction: the owner’s iPhone has no Generate UUID action. The guide now omits the optional request_key and uses the already-supported token + original timestamp + text identity for retries. It separates actions into numbered steps, explains how to name CaptureToken, and distinguishes typed text from selected output variables. After JSON conversion, the available Generate Hash action is used only to name the outbox file; Set Name must still receive CaptureJSON as the file contents. Real-device verification remains in progress.

Spoken-confirmation follow-up: the owner reports that Add to Chart now saves successfully, but says automatic sorting needs attention before captures subsequently appear in their proper projects. Local code now refreshes the capture before confirmation, distinguishes automatic retries from exhausted/disabled sorting, and reports active retries as sorting in progress. The capture pipeline and device API suites passed (42 tests, 301 assertions), including initial failure, retry, final failure and successful replacement of the Inbox fallback. This wording correction is not yet deployed. The production trigger remains unconfirmed: sorting history is needed to distinguish an AI retry from a web/queue configuration or dispatch issue. A normal eight-second wait timeout alone does not produce the original needs-attention response.

### Deploy this slice, then test on iPhone

Use the existing Forge release flow to deploy this code and build assets (`npm ci`, `npm run build` where the deployment normally builds). No new dependencies, API keys, worker definitions or scheduler entries are needed. Run the migration before activating the new release, then refresh caches and restart the supervised worker:

```sh
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan queue:restart
```

Keep the existing AI settings and stable APP_KEY. In the **production** app, open Settings → Devices & Shortcuts and create an iPhone token. Local and production tokens are separate; do not send a local token to production. Use the production URL shown there (`https://chart.internetmedicineman.com/api/capture?wait=1`) and follow the expanded Chart It and Send Outbox instructions. Keep the token out of chat and out of shared Shortcuts.

First verify one harmless online capture appears exactly once in Intake. Then check local file retention in airplane mode, reconnect and replay the unchanged file, and confirm no duplicate appears. Test Brain Dump next. Only after these pass, adapt the Shortcut for a separate Watch token and verify its available actions/connectivity. The new slice is not deployed by this local implementation.

## Project deletion and recovery — October 8, 2026

Project → Edit now includes Delete project with an explicit confirmation. Deletion uses the existing soft-delete column; no migration is required. Bench has a separate Project view → Recently deleted filter, with restore controls. Restoring preserves the project's original details and lifecycle, so Someday projects return to Ideas. Project trash respects ownership, domain/sphere/search filters and pagination independently of the task view.

Projects containing any tasks, including completed or soft-deleted tasks, cannot be deleted. Move their tasks first; restore deleted tasks before moving them. The delete transaction locks the project, matching manual and capture task creation so deletion cannot race a new assignment. Repeated delete/restore requests are harmless, and deleted projects cannot receive new tasks. No owner records were deleted during implementation. Capture-created test projects can also still be removed through Intake → capture → Undo filing within seven days, provided they have not changed or acquired tasks.

Validation: 50 relevant work-record, access-boundary and capture-pipeline tests passed (670 assertions). Client/SSR builds, route-cache compilation/clear, Pint and diff checks passed. An isolated browser account created a Someday project, cancelled and confirmed deletion, restored it to Ideas, and repeated deletion/restoration at 390px without overflow or page errors. Deploy source/built assets through the normal Forge flow and refresh the route cache before these controls appear in production.

## Device acceptance and capture notifications — October 8, 2026

The owner reports Add to Chart, Brain Dump and Send ChartOutbox working on iPhone, and successful captures from both Watch Chart and Watch Brain Dump. The Watch copies send JSON directly with a separate Watch token and `source=watch`; they do not use the iPhone's local file outbox. These are owner-reported device results, not agent-observed hardware tests. A Watch offline outbox, separate evidence of iPhone airplane-mode recovery, cold offline PWA launch and connection-type coverage remain open.

Implemented the next capture checkpoint:

- A private Notifications screen, desktop navigation entry, header bell with unread count, and mobile More entry. The paginated feed offers all/unread/dismissed views, mark read/unread, dismiss/restore and mark all read. It refreshes every 15 seconds while open, pausing during undo confirmation or a write.
- Every newly executed task, idea or project action writes exactly one notification in the same database transaction. Low-confidence filings are labeled “check this.” The feed links to the original capture and reuses the existing seven-day undo path, including snapshot and project-child protections. Undo from either Intake or Notifications updates the same notice; dismissing a notice never deletes a work record.
- One attention notice per capture covers failed sorting and unresolved items. Retry changes it to sorting, and successful resolution removes stale attention wording. Dismissal survives automatic retries. New outcomes do not depend on an AI request at page load.
- The feed starts with new processing after deployment. Earlier captures keep their existing history and undo controls in Intake; there is no historical notification backfill. Device push delivery, calendar reminders and observation producers remain separate pending work.

Verification: 65 PHP tests passed (685 assertions), including 13 new notification tests for filing atomicity, deduplication, retry/review transitions, owner and 2FA boundaries, pagination, read/dismiss behavior, and safe/expired undo. Client and SSR builds, Pint, route-cache compile/clear and diff checks passed. An isolated SQLite browser account verified desktop and 390px phone rendering, pagination, cancel/confirm undo, dismiss/restore, unread filtering, mark all read and mobile navigation, with no page errors or horizontal overflow. Synthetic preview records stayed out of the owner's database.

Local setup: the new `notifications_feed` table is migrated on local MySQL and the capture worker was restarted with current code. The scheduler was already running. Preview server stopped after verification. The local worker remains a development process, not a startup daemon.

Production rollout: deploy the code and built assets through the existing Forge process, run `php artisan migrate --force`, rebuild cached configuration as usual and run `php artisan queue:restart` after migrations. This checkpoint has not been pushed or deployed by the agent. After deploying, make one new capture and check the bell/Notifications page; older captures do not populate this new feed automatically.

At this checkpoint, `capture:import` was next. The owner subsequently confirmed there are no older notes to import; the following daily-planning checkpoint defers that command and advances the work model. Offline and push acceptance remain explicitly pending rather than treating the capture gate as fully complete.

## Daily Top 3 and tomorrow’s focus — October 8, 2026

The owner confirmed there are no pre-launch Apple Notes captures to import. Defer `capture:import` until there is an actual backlog to recover; no import code or command was retained. Continue Phase 1 with daily planning.

- The Chart page now leads with today’s Top 3, completion progress and yesterday’s focus line. Choose zero to three existing tasks in a searchable, paginated picker, reorder them, complete/reopen through the existing task controls, and save an optional 280-character focus line for tomorrow. Choosing tasks does not invent deadlines or create duplicate tasks.
- Daily plans belong to the owner and a local calendar date. Each new day starts with an empty Top 3; the previous day’s focus appears for that day only. The original plan remains stored. All date boundaries use the configured timezone, including DST transitions.
- Completed choices remain visible for that day. Deleted work, parked/archived domains and non-active projects cannot become new choices; previously selected unavailable tasks are identified and can be replaced. Completed tasks can stay in an existing plan, but cannot be newly added as open work.
- Server-side maximum/distinctness validation, ownership/2FA boundaries, one plan per owner/date and revision checks prevent extra choices and stale-tab overwrites. A screen left open across local midnight or a timezone change must reload before saving to a different date.
- This checkpoint provides manual planning. Voice `set_top3` / `set_tomorrow_focus` still go to review until their parser reference matching and reversible mutation actions ship. Computed work states, waits and activity/touch expansion remain next.

Verification: 41 relevant PHP tests passed (723 assertions), including 16 daily-planning cases covering ordering, completion, invalid choices, owner boundaries, pagination/search, focus carry-forward, Chicago evening dates, both DST changes and stale revisions. Client/SSR builds, Pint, route-cache compilation/clear and diff checks passed. An isolated browser account verified desktop and 390px phone selection, the three-choice limit, reorder, save focus, complete/reopen, searching beyond the first candidate page, replacing choices and canceling edits. No browser errors or horizontal overflow; synthetic preview work stayed out of the owner’s database.

Deployment: apply the `daily_plans` migration with the usual `php artisan migrate --force` after deploying code/assets. No new environment settings, scheduler entries or dependencies. The owner restarted local MySQL after the initial connection refusal; the migration then completed successfully. The isolated preview server was stopped. Nothing was pushed or deployed by the agent.

## Waiting on people and expected responses — October 8, 2026

- Tasks and projects now have a separate Waiting on control: choose an existing person or add a name, optionally set an expected response date, and return the work to “It’s my move.” Normalized duplicate names reuse the existing person; ambiguous duplicate names require an explicit choice. Full People editing remains a later phase.
- Wait dates are separate from task deadlines and project target dates. Date-only changes preserve the wait start; changing the person starts a new wait. Project hand-offs retain the timestamp when the ball returns to the owner. Completion closes waits, and reopening does not resurrect them. Revision checks reject stale hand-off forms.
- Briefing shows up to five active waits with person, local calendar days waiting and overdue response dates, plus a link to all waits. Bench has task/project Waiting filters; task rows and project views show the same wait details. Parked/archived domains and non-active projects stay out of the waiting attention block.
- Waiting tasks leave the due-work block and new Top 3 choices. Tasks already selected for today remain visible with their wait status. Clearing a wait makes an open task eligible again.
- The migration adds wait fields and extends existing capture snapshots with their default values so unchanged pre-upgrade captures retain safe undo and fallback replacement. A subsequent hand-off changes the snapshot and prevents undo from discarding newer work.
- This is manual waiting management. Voice `set_waiting`, reversible capture mutations, shared computed project/domain states, inherited state, activity/touches and nightly observations remain pending. No paid AI calls occur in this feature.

Verification: 94 relevant PHP tests passed (1,236 assertions), including 15 wait cases for date preservation, person matching, ownership/2FA, stale edits, completion, dashboard limits, inactive work, Top 3 integration, Chicago/DST dates and migration compatibility with existing capture undo/fallback snapshots. Client/SSR builds, Pint, route-cache compile/clear and diff checks passed. An isolated browser account verified desktop and 390px phone layouts, adding a person, overdue badges, Briefing/Bench filters, clearing a task wait, reusing a person for a project and returning the project to my move. No page errors or horizontal overflow. Synthetic records stayed out of the owner’s database; the preview server was stopped.

Deployment: local MySQL migration completed. Deploy code and built assets through Forge, then run `php artisan migrate --force` before `php artisan queue:restart`; rebuild configuration and route caches through the existing deployment flow. No new environment settings, dependencies or scheduler entries. Nothing was pushed or deployed by the agent.


## Computed project and domain states — October 9, 2026

- `WorkStateResolver` computes project and domain state from current owned records: cadence quiet first, overdue waits next, due/overdue or Top 3 tasks, open waits, then finite outcomes with open tasks. The fallback is “No immediate move,” following Section 8.1. Parked/archived domains and parked/Someday/done/dropped projects are excluded from attention.
- Domain state rolls up the highest urgency from its projects. Bench shows state, reason and recency on both domain headings and project rows, including domains without projects. State filters apply before project pagination; more urgent projects sort first, followed by target date/name. Project pages show a shared state strip before quick entry and tasks, including open/due/overdue/waiting task counts and the oldest wait.
- Briefing adds a capped five-item Gone quiet block and an overflow link. Direct overdue waits sort by lateness; cadence quiet sorts by how far past cadence it is. Parent summaries inherited solely from a project are omitted from this block to avoid repeating the same signal. These are current computed signals, not persisted nightly observations; notification scoring/deduplication/snooze remains future work.
- Date math uses local calendar dates, including Chicago evenings and both DST transitions. Expected response dates become late after their local date passes. Work without a recorded touch uses creation as its cadence baseline and says “No activity yet”; no activity is invented. The quiet switch suppresses inactivity warnings, while overdue responses still surface.
- Today’s Top 3 tasks are no longer duplicated in Briefing’s due-task block. Task completion already touches project/domain, and the next response reflects it. Task dates, wait changes, lifecycle, quiet/cadence settings, timezone changes and local midnight also affect the next read.
- Implementation choice: use one fresh seven-query aggregation set per page instead of a cross-request ten-minute cache. Task totals aggregate in SQL; only open wait details and subject summaries are loaded for resolution. This avoids stale results after edits, bulk updates or midnight without introducing an invalidation subsystem. The scope’s optional performance layer is explicitly pending rather than claiming cache invalidation is implemented. People state, activity/touch expansion, milestones and nightly observations remain later slices.

Verification: 74 relevant PHP tests passed (1,059 assertions), including 18 state cases for precedence, parent urgency, inactive work, cadence switches, creation baselines, finite outcomes, Top 3, completion/reopening, waits, timezone/DST, cross-page equality, pagination/limits, ownership and a fixed query count as projects grow. Client/SSR builds, Pint and diff checks passed. Isolated desktop and 390px phone browser checks verified the Gone quiet block, state filter, project strip, and transitions from overdue waiting to my move, calm after completion, and waiting again. No page errors or horizontal overflow. A read-only aggregation check passed on local MySQL without changing owner data.

Deployment: deploy code and built assets through the existing Forge flow. No migration, new dependencies, environment settings or scheduler entries are needed for this checkpoint. No push/deployment was performed by the agent. Activity logging and touch propagation are next; the isolated preview server was stopped after checks.

## Activity logging and touch history — October 9, 2026

- Log activity against an owned project or domain from Bench, a project page, or Activity history. Entries contain a note, optional 1–1,440 minutes and a local date/time in the configured timezone. History is paginated globally, by project, and by domain; domain history includes project activity recorded in that domain. Project pages show their recent activity below tasks.
- Activity can be edited or removed with stale-revision protection. A stable owner-scoped submission UUID makes identical retries idempotent and rejects changed reuse. Blank/oversized notes, invalid durations, future times, invalid spring-forward local times and stale timezone forms are rejected. The current time is offered by default; backdating records the actual selected time rather than touching the subject as if it happened now.
- Project activity touches the project and its domain; direct domain activity touches that domain. `work_touches` records the source of each touch, and `last_touched_at` is refreshed from the maximum remaining timestamp. Editing/removing activity cannot erase a later task completion or another entry. Existing touch dates are migrated as baselines without modifying the work record or capture snapshots. Task completion now writes through the same touch history; duplicate completion is a no-op, and reopening retains the historical completion touch.
- Monthly minutes aggregate using local-month boundaries converted to UTC. Project/domain state strips and Bench summaries show logged hours/minutes. A project move preserves the original domain on past activity; new entries use its current domain. Corrections cannot silently move an existing entry to a different subject. Active activity history prevents deleting its project/domain; closing the project remains available.
- All writes and touch updates share a transaction and owner lock. Preview/test records are isolated. No new paid model requests, dependencies or scheduler entries. Manual activity still requires a connection; voice `log_activity`, mutation undo through capture, extra task touch targets, People/content touches and full nightly observations remain pending. The state resolver now uses eight aggregate queries, including monthly totals.

Verification: 110 relevant PHP tests passed (1,390 assertions), including 11 activity cases for project/domain propagation, monthly totals, retries, stale edits, corrections/deletions, backdating, pre-upgrade baselines, project moves, task completion/reopening, owner/2FA boundaries, local dates/DST and pagination. Client/SSR builds, Pint, route-cache compile/clear and diff checks passed. Isolated desktop and 390px phone browser checks verified project/domain logging, live cadence/time updates, editing, history, cancel/confirm removal, and recency restoration without overflow or page errors. The preview server was stopped after checking.

Deployment: local MySQL migration completed. Deploy code and assets through Forge, run `php artisan migrate --force` before restarting workers with `php artisan queue:restart`, and refresh existing config/route caches through the normal deployment flow. The migration creates `activity_logs` and `work_touches` and preserves existing touch timestamps. No push or production deployment was performed by the agent.

## Capture activity and hand-offs — October 9, 2026

The existing phone, Watch and web capture paths now support `log_activity` and `set_waiting`; no Shortcut changes are needed. The parser uses the same configured GPT-6 Luna model and adds bounded active-task context. This does not implement completion, clearing waits, daily-plan voice commands, new-person creation, recurrence or the remaining Phase 1 actions.

- Activity records notes and optional stated minutes against an existing active project or available domain. Recording time is the default; an explicit date/time is interpreted in the capture’s original timezone. Future times and invalid DST times go to review. Project/domain touches and monthly totals use the actual occurrence time, including delayed outbox uploads.
- Waiting updates an existing open task or active project for an existing person, with an optional expected response date. Unique exact names (case/whitespace normalized) and confidence of at least 0.8 are required for automatic execution. Unknown, fuzzy, duplicate or missing names go to review. No new work or people are invented to satisfy a hand-off.
- Work changed at or after the recording time requires review before setting a wait. The review form shows current waiting people and submits the selected work’s wait revision; concurrent hand-off changes require a reload. The wait starts at recording time, while updates for the same person preserve its existing start.
- Capture review offers Activity and Waiting on, with explicit owned subject/person selectors. Activity links directly to its history entry. The Settings parser preview applies the same reference, confidence, timestamp and stale-work checks without writes.
- One transaction and owner lock cover execution, activity touches, audit and notifications. Existing per-item idempotence and mixed-dump recovery remain in place. `action_logs.before_snapshot` records the previous waiting fields; undo restores them without deleting the work. Activity undo removes that entry and recalculates its touches. Both enforce the seven-day window and refuse to overwrite later changes. Original capture text is retained.

Validation: 184 relevant PHP tests passed across the focused runs (2,004 assertions), including 29 capture-work cases for idempotence, mixed execution, ambiguity, ownership/availability, stale offline changes, review revisions, activity dates/timezones/DST, notifications, preview and undo. Client/SSR builds, Pint, route-cache compile/clear and diff checks passed. Isolated desktop and 390px browser checks verified reviewed hand-offs and activity, destinations, undo, retained original text/work, and monthly totals without overflow or page errors; the preview server was stopped. Existing DaisyUI `@property` optimizer warnings remain non-blocking.

Live synthetic evaluation: the full 36-case Luna run passed 35/36. Its remaining case said “by Thursday” on a Thursday; the model conservatively requested a date clarification instead of assuming today. That fixture now records on Wednesday, making the intended Thursday deadline unambiguous; its targeted rerun passed. Thus all current cases have passing evidence across these runs, not a second full-suite run or an accuracy guarantee. The application prompt and model were unchanged between runs. Reports: `storage/app/private/parser-evals/20261009-141648-8d82a979-0938-4034-b72e-339f44fff585.json` and `20261009-141722-4bf915bc-ad32-4320-9fb1-1646bc6ccba6.json`. No live evaluation wrote work records or sent owner capture content.

Deployment: the additive snapshot migration is applied to local MySQL. The local capture worker had stopped and was started with current code; the existing scheduler remains running. Deploy source and built assets through Forge, then run `php artisan migrate --force`, refresh the normal config/route caches, and run `php artisan queue:restart` so supervised workers load the new action registry. No environment or scheduler changes are required. No push or production deployment was performed.

## Task completion and basic recurrence — October 9, 2026

The owner approved **skipping missed dates while keeping the original schedule**. Manual completion and `complete_task` capture now share one transactional service. The task editor has Repeat controls for daily, weekly, monthly and yearly schedules, every 1–365 units, with an optional inclusive end date. Set a due date to anchor a repeat; change or stop it on the open occurrence.

- This release supports the date-based RRULE subset `FREQ`, `INTERVAL`, and `UNTIL`. Advanced `BYDAY`/ordinal patterns, `COUNT`, exceptions and voice creation/editing of recurrence remain deferred. Unsupported clauses are rejected, never silently discarded. Calendar-date generation preserves the anchor and timezone, skips impossible month dates/leap-day years and invalid local DST times, and does not drift after late completion. These semantics follow the supported portions of [RFC 5545 recurrence rules](https://www.rfc-editor.org/rfc/rfc5545#section-3.3.10); this is not a general iCalendar recurrence implementation.
- Completing a repeat creates at most one next occurrence after both its current due date and today in the stored schedule timezone. A late offline upload retains its original completion time but advances the next task past the upload day, preventing a backlog. The new occurrence copies task details and schedule, clears waits/completion, and does not inherit Top 3 membership. Ending the schedule creates no successor.
- A unique parent-occurrence key and owner/task locks prevent duplicate successors. Task revisions reject stale edits and completion commands; hand-offs, edits, project moves and deletion/restoration change the revision. Daily-plan writes share the owner lock so successor removal cannot race Top 3 selection.
- Capture requires a unique owned active/open task, confidence of at least 0.8 and an unchanged task since recording. Future recurring occurrences require explicit review, which also checks the selected task revision. Clear past-tense statements can resolve to a unique known task title. Ambiguous references, partial work and explicitly backdated completion remain in review. The original words, factual filing confirmation, notification and seven-day undo use the existing capture path; Shortcuts need no changes.
- Completion stores prior task/wait fields, touch records and the exact generated successor snapshot. Capture undo reopens the original, restores its wait, removes only its own completion touch and restores any earlier completion touch, preserving later activity. Manual reopen preserves historical touch, matching prior behavior. Either operation removes the generated next occurrence only if untouched and absent from every daily plan. Edited, completed, deleted or planned successors block reversal, preserving newer work. An already completed request is a no-op; replayed recordings cannot complete the generated future occurrence.
- The additive migration adds recurrence/revision columns and private completion history. Existing task action-log before/after snapshots and capture fallback snapshots are backfilled with column defaults, preserving pre-upgrade undo/recovery. A migration rollback test caught and corrected unique-index removal ordering. No new dependency, environment setting or scheduled job was introduced.

Validation: 218 relevant PHP tests passed across focused runs (2,181 assertions), including 34 recurrence/completion cases covering generation, retry, reopen/undo, stale changes, existing wait restoration, earlier/later touches, ownership, timezone changes, delayed uploads, calendar boundaries, migration compatibility and rollback. Client/SSR builds, Pint, route-cache compile/clear and diff checks passed. Desktop and 390px browser checks verified repeat interval setup, one future successor, reopen, phone capture review/completion/undo, completed-task navigation, stopping a repeat and no overflow/page errors. The browser test was resumed after replacing an early URL assertion with a navigation wait; no application defect was involved. The isolated preview server is stopped. Existing DaisyUI CSS optimizer warnings remain non-blocking.

Live evaluation: GPT-6 Luna passed **41/41** synthetic cases in one run, including completion, ambiguous completion, partial activity, unsupported backdating/voice recurrence setup and mixed dumps. Report: `storage/app/private/parser-evals/20261009-164440-927bb993-aa84-43d7-b3fc-caf8456a13e2.json`; prompt SHA-256 `9e3e32f7f0ec922d31a3d5eed1e9a935854a3da52cc899cbb56105586729d930`; 58,115 input and 10,978 output tokens. No owner data was sent for this evaluation and no work records were created. Synthetic results are regression evidence, not a real-device accuracy guarantee.

Deployment: local MySQL migration completed and the local capture worker was gracefully replaced with current code; the existing scheduler is unchanged. Deploy code and built assets through Forge, run `php artisan migrate --force`, refresh normal config/route caches, then `php artisan queue:restart`. No push or production deployment was performed. Next capture slices are clearing waits and daily planning; milestones/subtasks, extra touch targets and advanced recurrence remain open.

## Capture planning and clearing waits — October 9, 2026

- Added `clear_waiting` for an exact existing open task or active project. An explicitly named person must match the current hand-off. Clearing does not complete the task or log activity; a project returns to the owner at recording time. Absent/ambiguous waits, future timestamps and edits since recording require review. Reviewed changes require the current wait revision. Undo restores the prior hand-off while protecting later edits.
- Added `set_top3` for today or tomorrow: replace the full list in spoken order with zero to three unique existing, active, open tasks that are with the owner. Explicit clearing uses an empty list. Missing/ambiguous/unavailable references, overlong lists and additive requests without a complete replacement list go to review; no partial replacement or task creation occurs.
- Added `set_tomorrow_focus`, a 280-character line for tomorrow. This changes only the focus field and preserves Top 3. The focus is stored on today’s plan using the existing manual-planning convention. Setting today’s focus or clearing a focus through voice remains unsupported and goes to review.
- Plan changes use owner transaction locks, exact owned task resolution and revisions. Automatic filing refuses recordings from a previous local day or a different timezone, and plans changed at/after recording. Review shows today/tomorrow and requires an explicit current target and revision. Separate fields from one dump may apply together only while their saved snapshot remains current; repeated writes to the same field or intervening edits require review.
- Review forms show the current plan and preselect unique exact task matches. Clearing all choices requires an explicit checkbox. The Briefing shows tomorrow’s chosen task list; it becomes today’s list when the owner’s local day changes. Existing manual today/focus editing remains available.
- New actions participate in parser preview, capture history, truthful device confirmations, notifications and seven-day undo. Undo restores only the affected field, but conservatively requires the entire plan snapshot to remain unchanged; a later plan edit, including another field, blocks that older undo. A new empty plan retains its revision after undo to reject stale forms. Original words always remain saved.

Validation: 256 relevant PHP tests passed across focused runs (2,368 assertions), including 38 new planning/clear-wait tests covering atomic replacement, ownership, availability, ambiguity, stale captures/forms, mixed capture execution/retry, undo, future timestamps, Chicago evening dates and DST boundaries. Client/SSR builds, Pint, route-cache compilation/clear and diff checks passed. Isolated desktop and 390px phone browser checks verified reviewed clearing/undo, ordered Top 3, tomorrow’s list, focus/undo preserving tasks, no horizontal overflow and no page errors. Preview records stayed outside the owner’s database; the preview server was stopped. Existing DaisyUI CSS optimizer warnings remain non-blocking.

Live Luna evaluation: **48/48** synthetic cases passed. The first run passed 47/48; a pre-existing Someday example was incorrectly classified as an idea. The action description now distinguishes an explicit future intention to build/launch a named undertaking from a vague reflection; the full rerun passed without weakening expectations. Final report: `storage/app/private/parser-evals/20261009-185629-363c0c8a-cfd2-410d-a844-bf07876c8453.json`. Synthetic context only; no personal records sent.

Deployment: no new migration or Shortcut changes are needed for this checkpoint. Deploy code and built assets through Forge, refresh config/route caches, then `php artisan queue:restart`. The local capture worker was gracefully replaced with current code; scheduler configuration is unchanged. No push or production deployment was performed. Next work-model slice: milestones/subtasks and extra task touch targets, before the remaining Briefing observations and Calendar integration.


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

- [x] Add raw captures, items, hashed scoped capture tokens and action logs with per-item undo.
- [x] Add the in-app notification feed, read/dismiss controls and shared undo records for implemented capture actions. Push delivery and later reminder/observation producers remain in their own checkpoints.
- [x] Persist session/device capture words and their idempotency key before dispatching any AI job; recover if job dispatch itself fails after the save.
- [x] Return a quick `202`, with a bounded `?wait=1` path and factual server-derived confirmation, using the existing database queue and persisted retries.
- [ ] Configure Redis/Horizon if replacing the current database queue; production currently uses the owner-confirmed Forge worker/scheduler setup.
- [x] Build parser context, action schema, reference resolver and executor from one action registry. Initially advertise tasks, ideas, projects and explicit triage; retain unsupported material for review. Luna passed the live fixture evaluation; real use remains the acceptance gate.
- [x] Deliver in-app typed capture, online watch/phone shortcuts, brain-dump splitting, triage and capture history. Owner reports iPhone and Watch capture working.
- [ ] Deferred by owner: `capture:import` for pre-launch notes; there are no older notes to import. Revisit when a real import is needed.
- [x] Build safe per-item retry and seven-day undo for the implemented creation actions. One failed item does not block successful siblings or execute them twice. Activity and waiting actions now have their own reversible effects; later mutation actions still need theirs.
- [x] Implement the open-app IndexedDB outbox: pending count, stable request keys and replay on open/visibility/online. Retain unsent text through auth expiry and require the same owner to resume submission.
- [ ] Verify real-device Shortcut offline storage/replay and cold offline app launch separately.

Acceptance: one dump containing five unrelated items produces five individually traceable outcomes. Repeated requests do not duplicate work. API errors leave recoverable Inbox content. A failed queue dispatch is recoverable. Revoked tokens fail; low-confidence or ambiguous matches go to triage. No confirmation claims “saved” before server storage or an actual local outbox write succeeds.

### 4. Complete the work model and computed state

- [x] Add manual task/project waits and expected response dates, person selection, hand-off timestamps and stale-edit protection. Basic priorities and due dates/times were already implemented.
- [ ] Add milestones, subtasks, extra touch targets and advanced recurrence patterns.
- [x] Extend voice/text capture with reversible activity logging and waiting updates, exact existing-reference checks, manual review and stale-work protection.
- [x] Add manual daily Top 3 and tomorrow’s-focus line with local-date boundaries, completion progress and stale-edit protection.
- [x] Add manual activity entries with minutes, project/domain touch history and corrections, and monthly time totals.
- [x] Extend voice/text capture to clearing waits, daily Top 3 and tomorrow’s focus with reviewed, reversible mutations.
- [ ] Add extra task touch targets and later People/content propagation.
- [x] Build shared project/domain `WorkStateResolver` and parent roll-ups; use the same results in Briefing, Bench and project pages.
- [ ] Add a ten-minute cache with complete mutation/date invalidation if profiling warrants it; current reads compute fresh. Extend computed states to People when that phase ships.
- [x] Implement basic recurrence and voice/manual task completion with successor identity and safe undo; preserve later work and prevent duplicate occurrences or false cadence resets.
- [ ] Add advanced RRULE clauses, exception dates, and voice recurrence setup when needed.

Acceptance: completing a task touches its intended subjects; overdue waits outrank ordinary due work; parked/someday items stay out of attention lists; exactly three tasks can be selected for a day; state updates after edits, not only after touches. Date-boundary tests cover Chicago evening timestamps and DST.

### 5. Briefing, Bench and project views

- [ ] Replace the SaaS dashboard with capped Briefing blocks, including honest empty states.
- [x] Add Bench state filters, domain roll-ups and project state strips alongside existing tasks.
- [x] Add activity history and monthly time totals to Bench/project views.
- [ ] Add weighted milestone progress.
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
