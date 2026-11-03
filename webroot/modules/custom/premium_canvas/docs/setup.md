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
- Canvas AI uses the OpenAI provider with `gpt-5.4` for chat, tool calling,
  vision, JSON output, and structured responses. The Editor role can use the
  assistant; AI administration remains restricted to administrators.

## OpenAI credentials

Set `OPENAI_API_KEY` in your local `.env` file. The variable is listed, empty,
in `.env.dist`. DDEV already passes `.env` into the web container; after adding
or changing the key, run `ddev restart` and `ddev drush cr`.

The Drupal Key entity `openai_api` reads this environment variable at runtime.
Its exported configuration contains the variable name and no credential.
For other hosting environments, provide the same variable to PHP.

Provider settings are at `/admin/config/ai/providers/openai`; operation defaults
are at `/admin/config/ai/settings`. Open a linked Canvas alternative and use its
AI assistant to compose content. Creating the initial alternative still seeds
only the page title and body or description; it does not automatically convert
Layout Builder blocks or styling.

Prompt logging is disabled. A live connection and generated output still need
to be verified after supplying a key with access to the configured model.

The local site was in maintenance mode before Canvas was installed. Log in to
review it; the setup does not change maintenance mode.
