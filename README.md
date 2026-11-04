# Task Tracker

**Author:** Anushka Tyagi  
**Demo:** https://double-defined-punch-adjustments.trycloudflare.com

## Description

Task Tracker is a small, database-backed PHP web application for tracking learning tasks and daily progress. It lets you create and schedule tasks, run a server-authoritative timer, record work notes, attach code ZIP files for review, maintain a topic backlog, and export daily reports that can be handed to ChatGPT for analysis.
Data is stored in MySQL and uploaded files live on disk outside the web root. The app is built with plain PHP 8.2+ and PDO, with no framework, no build step, and no Composer dependencies. It is intended for local/personal use, prioritizing data integrity, recovery after sudden shutdown, and straightforward maintenance.

## Current Features

- **Dashboard** — tasks due today/overdue, the active timer, today's tracked
  hours, status counts, recent comments, and quick actions.
- **Tasks** — title, description, status (`pending`/`in_progress`/`completed`/
  `cancelled`), optional priority, category/tag, and deadline. Automatic
  created/updated timestamps and a stable task detail URL.
- **Completed tasks** — _Mark as done_ stops any running timer and moves the task
  to a separate **Completed** list. Completed tasks cannot be started and are
  excluded from active listings and Slow Performance tracking.
- **Timer** — green **Start** / red **Stop**. Only one timer can run globally,
  enforced atomically with a MySQL advisory lock and a transaction. The database
  is the source of truth: **Start** writes the session row immediately and
  **Stop** records the final duration. The browser clock only renders persisted
  state.
- **Timesheet** — per-task session history with local start/stop times, daily
  totals, total time, session count, and a date-range filter. Midnight-crossing
  sessions are split correctly.
- **Comments / work notes** — multiple notes per task, grouped by day, with kinds
  (`progress`, `blocker`, `decision`, `general`) and escaped output.
- **Per-task To-do tab** — Google Keep-style checklist with square checkboxes,
  inline add field, paste-to-split, drag-to-reorder, a _Marked as done_ section,
  and _Add to comment_ to move checked items into task comments.
- **ZIP attachments** — validated server-side (extension, MIME, ZIP signature,
  entry count, per-entry size, total size, compression ratio, encryption), stored
  under `storage/uploads/` with a random filename, and downloaded only through a
  controller. SHA-256, original name, and size are recorded.
- **Daily reports** — per-task time for a chosen day, that day's comments,
  attachments, and optional planning fields. Export as Markdown, JSON, or TXT.
- **Report submission** — submitting a day's report finalizes it and locks that
  day's timers; submitted reports are final.
- **Deadlines** — optional deadlines with a days-left/overdue indicator and a
  **Deadlines** tab that records every deadline change (initial, extended,
  cleared).
- **Slow Performance tracking** — tasks that miss their deadline two or more
  times are flagged, with a dedicated page, a resolve workflow requiring a reason,
  dashboard notices, and a downloadable Markdown report.
- **Topic backlog** — quick-capture topics with notes, category, priority, and
  status; convert an item into a prefilled task while preserving and linking the
  original backlog item.
- **Search and filtering** — by title, status, category, priority, and deadline.
- **Security** — PDO prepared statements everywhere, CSRF protection on all state-changing requests, escaped output, uploads outside the web root, and an optional `APP_PASSWORD` login for local use.

## Current Version

**2.1**

Latest migration: `006_task_todo_items.sql`.

Run `php database/migrate.php` after updating to apply pending migrations.
