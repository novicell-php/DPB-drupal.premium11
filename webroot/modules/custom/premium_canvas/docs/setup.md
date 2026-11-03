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
- The **Canvas demo** component group includes section, heading, text, image,
  button, columns, card, hero, and image-text components. Columns stack on small
  screens and offer equal or asymmetric widths. Cards accept additional content,
  heroes accept calls to action, and image-text accepts headings, text, and buttons.
  Image fields use the existing media library, including its alternative text.
  Images support captions and natural, landscape, or square proportions. Heroes
  use an H1; cards use H3. Choose heading levels for surrounding content accordingly.
  Buttons support site paths, HTTP(S), anchors, mailto, and tel links. Use secondary
  buttons on dark sections. These components display existing images; they do not
  generate image assets or invoke AI when rendered.
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

Prompt logging is disabled. Text and tool calling have been verified using a
small GPT-5.4 nano request; model defaults still use GPT-5.4.

## Existing media in Canvas AI

The `premium_canvas_ai` submodule adds a read-only `find_image_media` tool to the
Drupal Canvas Page Agent. It searches published image media by name, filename,
or media ID, checks access, and returns at most ten matches. It does not download
or generate images. Try selecting an image component and asking: "Use the uploaded
optimus.png image in this component." Ambiguous matches should be clarified.

The demo enables Canvas's bundled `canvas_dev_ai` workflow. Its native page
agent is the default; the Tools menu also offers code-component creation.
Upstream marks this workflow experimental and unsuitable for production sites.
Canvas itself uses the original Composer package, with no Canvas patches or
custom editor bundle.

The custom module listens to AI Agents' tool execution events. Before Canvas
reads the current layout or validates an edit, it restores media IDs from the
accessible Canvas Page draft. It only converts matching component UUIDs, types,
and rendered values; stale values, placeholders, and explicit selections are
left alone. This allows caption-only edits to retain their image without asking
the model to reconstruct IDs from rendered image URLs. Nested slots and JSON or
multipart requests are supported. Successful partial edits also carry their
unchanged media IDs back to the editor so its widget values remain valid. This
integration is scoped to Canvas Pages.

Deploy with the normal Composer install and configuration import, then rebuild
caches. Hard-refresh an already open Canvas editor and start a new conversation
so old agent instructions are discarded. Existing placeholders are not
automatically replaced; select the intended component and ask the assistant to
attach the media again.

Run the integration regression checks on a configured demo site with a published
image media item:

```sh
ddev drush php:script modules/custom/premium_canvas/modules/premium_canvas_ai/tests/media-context.php
```

The checks create and remove a temporary page, draft, and media reference. They
make no AI requests and preserve existing content. Re-run them when updating
Canvas or AI Agents because the integration uses Canvas's draft/model APIs.

The local site was in maintenance mode before Canvas was installed. Log in to
review it; the setup does not change maintenance mode.
