# Changelog

All notable changes to this project are documented here after each update.

**Legend**

- `(*)` Modification
- `(+)` Addition
- `(!)` Bug Fixes

---

## Version 2.1

\+ Added per-task **To-do** tab backed by `task_todo_items` (migration `006`).
\+ Added Google Keep-style checklist with square checkboxes and an inline add field.
\+ Added paste-to-split: a multi-line paste creates one item per non-empty line.
\+ Added drag-to-reorder that saves the new order immediately.
\+ Added **Marked as done** section and **Add to comment** action.
\! Fixed To-do row layout and checkbox/text alignment.
\! Fixed the drag handle so dragging starts reliably from the icon.

## Version 2.0

\+ Added favicon to the browser tab.
\+ Added **Mark as done** to stop the timer and move the task to **Completed**.
\+ Added **Completed** list; completed tasks cannot be started.
\+ Excluded completed and cancelled tasks from **Slow Performance**.
\* Task list **Tracked** column now shows today's time only.
\* Timer is now server-authoritative; the database is the source of truth.
\* Removed browser heartbeat, `interrupted` status, and the recovery flow.
\* `timer_sessions.status` is now `ENUM('running','stopped')`.
\* Migration `005` cleans up open sessions and drops heartbeat columns.
\* `migrate.php` now strips `--` comments per statement instead of skipping it.
\* Updated README timer model and troubleshooting.
\! Fixed false "interrupted timer needs recovery" on healthy timers.
\! Fixed Stop reporting success for a session that was not running.
\! Fixed stale sessions blocking new tasks.

### Accepted trade-off

\* A session not stopped keeps counting offline time until it is stopped.

## Version 1.9

\! Fixed DB connection failure caused by a blank `DB_PASS` in `.env`.
\! Fixed blank `.env` values overriding the defaults.
\! Added actionable hints to database connection errors.
\* Removed the hardcoded database password from `config/config.php`.
\* Updated README troubleshooting and implementation plan §2.1 and §10.

## Version 1.8

\! Fixed same-day deadlines blocking the timer.
\! Fixed false Slow Performance entries from time-of-day comparisons.
\! Auto-removed Slow Performance entries below the threshold.
\! Unified `deadline passed` notices to the calendar-date rule.

## Version 1.7

\+ Added **Slow Performance** tracking for two or more missed deadlines.
\+ Added **Slow Performance** menu with unresolved and resolved tabs.
\+ Added resolve workflow that requires a reason for delay.
\+ Added persistent dashboard notice for unresolved issues.
\+ Added downloadable Markdown report for Slow Performance.
\+ Added migration `004` and the `slow_performance` table.

## Version 1.6

\* Grouped task comments by day with date/time separators.
\* Removed the **Kind** dropdown; new comments are stored as `general`.

## Version 1.5

\+ Blocked starting overdue tasks; Start is replaced by a `deadline passed` notice.
\+ Enforced the deadline guard server-side in `TimerController::start`.

## Version 1.4

\+ Added a Start/Stop button to each `/tasks` row with live tracked time.
\+ Added automatic task switching with a toast notification.
\+ Added toast notifications for start/stop/switch results.
\* Timer actions on `/tasks` now update in place via AJAX.
\* `TimerController` returns JSON for XHR requests.

## Version 1.3

\+ Added a days-left/overdue indicator beside each deadline.

## Version 1.2.1

\! Fixed the HTTP 500 by starting the MySQL server (connection refused, not an app bug).

## Version 1.2

\+ Added **Deadlines** tab with full deadline-change history (migration `003`).
\+ Added **Submit report** popup showing tasks, time, and comments.
\+ Added `.txt` report download on submit.
\+ Added `.txt` export from the Reports page and recent-reports list.
\* Submit report opens the popup and carries page notes into the submission.
\* Existing deadlines are recorded as the "Initial" history entry.

## Version 1.1

\+ Added **Timesheet** tab with session history, daily totals, and session count.
\+ Added a date-range filter to the timesheet.
\! Fixed the task page clock ticking when no timer is running.
\* Split the task page into **Overview** and **Timesheet** tabs.
\* Limited Overview's recent sessions to the last 5.

## Version 1.0

\+ Added **Submit report** button on the Dashboard and Daily Report page.
\+ Added `submitted_at` to `daily_reports` (migration `002`).
\* Added restrictions on start action for task once the report is submitted.
\* Hid Start and **Resume now** on the locked day.
\* Made submitted reports final and non-editable.
\* Blocked submission while a timer is running.
\* Added Report status **Draft / Saved / Submitted**.
\! Fixed "View report" appearing to do nothing.
\! Made the submitted/locked state obvious on the dashboard and report pages.
