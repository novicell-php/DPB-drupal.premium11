# Canvas page alternatives

Canvas 1.12 is installed alongside Layout Builder. Normal **Basic page** content
(`page`) can retain its Layout Builder layout and link an independently editable
Canvas Page. Existing pages use Layout Builder by default.

1. Open a page's **Canvas alternative** tab and choose **Create Canvas alternative**.
   This creates an unpublished starter using the title and body or description.
   It does not convert Layout Builder blocks or styling and does not invoke AI.
2. Edit and publish the alternative in Canvas. Use **Preview Layout Builder** and
   **Preview Canvas** to compare them without changing the page presentation.
3. On the normal page edit form, open **Page presentation** and select
   **Published presentation: Canvas**. Save/publish through the existing editorial
   workflow. The original node URL and its Layout Builder sections are retained.
4. Select **Layout Builder** to switch back. A missing, empty, or unpublished
   Canvas alternative automatically falls back to Layout Builder. Teasers,
   navigation items, and search-result displays retain their existing behavior.

The Canvas reference and selector are revisioned and translatable. Alternatives
are linked to the latest editorial revision without publishing pending changes.
The linked Canvas Page has its own publication lifecycle: publishing edits to
an already active alternative updates its presentation independently of node
moderation. Content changes are not synchronized between the two versions.

The selector controls Drupal-rendered pages. The separate decoupled frontend
and its Transform API rendering have not been changed; they need their own
Canvas rendering integration before using the switch.

- Use `/admin/content/pages` for Canvas Pages, including the existing playground.
  The **Editor** role can create, edit, delete, and publish Canvas Pages.
- The theme supplies **Canvas section**, **Canvas heading**, and **Canvas text**
  components in the Canvas demo group. A section accepts nested content.
- Canvas AI is not enabled; it is a separate follow-up task.

The local site was in maintenance mode before Canvas was installed. Log in to
review it; the setup does not change maintenance mode.
