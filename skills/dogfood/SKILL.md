---
name: dogfood
description: "Exploratory QA of web apps: find bugs, evidence, reports."
version: 1.0.0
platforms: [linux, macos, windows]
metadata:
  hermes:
    tags: [qa, testing, browser, web, dogfood]
    related_skills: []
---

# Dogfood: Systematic Web Application QA Testing

## Overview

This skill guides you through systematic exploratory QA testing of web applications using the browser toolset. You will navigate the application, interact with elements, capture evidence of issues, and produce a structured bug report.

## Prerequisites

- Browser toolset must be available (`browser_navigate`, `browser_snapshot`, `browser_click`, `browser_type`, `browser_vision`, `browser_console`, `browser_scroll`, `browser_back`, `browser_press`)
- A target URL and testing scope from the user

## Inputs

The user provides:
1. **Target URL** — the entry point for testing
2. **Scope** — what areas/features to focus on (or "full site" for comprehensive testing)
3. **Output directory** (optional) — where to save screenshots and the report (default: `./dogfood-output`)

## Workflow

Follow this 5-phase systematic workflow:

### Phase 1: Plan

1. Create the output directory structure:
   ```
   {output_dir}/
   ├── screenshots/       # Evidence screenshots
   └── report.md          # Final report (generated in Phase 5)
   ```
2. Identify the testing scope based on user input.
3. Build a rough sitemap by planning which pages and features to test:
   - Landing/home page
   - Navigation links (header, footer, sidebar)
   - Key user flows (sign up, login, search, checkout, etc.)
   - Forms and interactive elements
   - Edge cases (empty states, error pages, 404s)

### Phase 2: Explore

For each page or feature in your plan:

1. **Navigate** to the page:
   ```
   browser_navigate(url="https://example.com/page")
   ```

2. **Take a snapshot** to understand the DOM structure:
   ```
   browser_snapshot()
   ```

3. **Check the console** for JavaScript errors:
   ```
   browser_console(clear=true)
   ```
   Do this after every navigation and after every significant interaction. Silent JS errors are high-value findings.

4. **Take an annotated screenshot** to visually assess the page and identify interactive elements:
   ```
   browser_vision(question="Describe the page layout, identify any visual issues, broken elements, or accessibility concerns", annotate=true)
   ```
   The `annotate=true` flag overlays numbered `[N]` labels on interactive elements. Each `[N]` maps to ref `@eN` for subsequent browser commands.

5. **Test interactive elements** systematically:
   - Click buttons and links: `browser_click(ref="@eN")`
   - Fill forms: `browser_type(ref="@eN", text="test input")`
   - Test keyboard navigation: `browser_press(key="Tab")`, `browser_press(key="Enter")`
   - Scroll through content: `browser_scroll(direction="down")`
   - Test form validation with invalid inputs
   - Test empty submissions

6. **After each interaction**, check for:
   - Console errors: `browser_console()`
   - Visual changes: `browser_vision(question="What changed after the interaction?")`
   - Expected vs actual behavior

### Phase 3: Collect Evidence

For every issue found:

1. **Take a screenshot** showing the issue:
   ```
   browser_vision(question="Capture and describe the issue visible on this page", annotate=false)
   ```
   Save the `screenshot_path` from the response — you will reference it in the report.

2. **Record the details**:
   - URL where the issue occurs
   - Steps to reproduce
   - Expected behavior
   - Actual behavior
   - Console errors (if any)
   - Screenshot path

3. **Classify the issue** using the issue taxonomy (see `references/issue-taxonomy.md`):
   - Severity: Critical / High / Medium / Low
   - Category: Functional / Visual / Accessibility / Console / UX / Content

### Phase 4: Categorize

1. Review all collected issues.
2. De-duplicate — merge issues that are the same bug manifesting in different places.
3. Assign final severity and category to each issue.
4. Sort by severity (Critical first, then High, Medium, Low).
5. Count issues by severity and category for the executive summary.

### Phase 5: Report

Generate the final report using the template at `templates/dogfood-report-template.md`.

The report must include:
1. **Executive summary** with total issue count, breakdown by severity, and testing scope
2. **Per-issue sections** with:
   - Issue number and title
   - Severity and category badges
   - URL where observed
   - Description of the issue
   - Steps to reproduce
   - Expected vs actual behavior
   - Screenshot references (use `MEDIA:<screenshot_path>` for inline images)
   - Console errors if relevant
3. **Summary table** of all issues
4. **Testing notes** — what was tested, what was not, any blockers

Save the report to `{output_dir}/report.md`.

## Tools Reference

| Tool | Purpose |
|------|---------|
| `browser_navigate` | Go to a URL |
| `browser_snapshot` | Get DOM text snapshot (accessibility tree) |
| `browser_click` | Click an element by ref (`@eN`) or text |
| `browser_type` | Type into an input field |
| `browser_scroll` | Scroll up/down on the page |
| `browser_back` | Go back in browser history |
| `browser_press` | Press a keyboard key |
| `browser_vision` | Screenshot + AI analysis; use `annotate=true` for element labels |
| `browser_console` | Get JS console output and errors |

## Tips

- **Always check `browser_console()` after navigating and after significant interactions.** Silent JS errors are among the most valuable findings.
- **Use `annotate=true` with `browser_vision`** when you need to reason about interactive element positions or when the snapshot refs are unclear.
- **Test with both valid and invalid inputs** — form validation bugs are common.
- **Scroll through long pages** — content below the fold may have rendering issues.
- **Test navigation flows** — click through multi-step processes end-to-end.
- **Check responsive behavior** by noting any layout issues visible in screenshots.
- **Don't forget edge cases**: empty states, very long text, special characters, rapid clicking.
- When reporting screenshots to the user, include `MEDIA:<screenshot_path>` so they can see the evidence inline.

## Painful false positives in body-text pattern matching

If you script per-route checks that scan `document.body.innerText` for "has_500_text" / "has_404_text" / "has_server_error_text" patterns, **always verify with a body snippet dump before reporting**. Real data can match:

- "Rp **7.500.000**" — quantity / currency formatter outputs four-digit groups separated by dots, contains the substring `500`.
- Stock counts: `cell value="500"` rows.
- HTTP status shown in a debug panel: "Most recent 5xx: 0" — matches "5xx".
- Port numbers in error toasts: "Connection refused on :5432" — matches any digit pattern.

Traps from this session:
- A `/sparepart` route flagged `has_500_text: true`. Actual content was healthy — flagged because the price column rendered `Rp 7.500.000`.

Safer pattern if you must detect server errors from the DOM:

```js
(()=>{
  // Look for the specific element Laravel/framework emits, not substring matches
  const excTitle = document.querySelector('.exception-message, .exception_title, [data-laravel-exception]');
  if (excTitle) return excTitle.innerText;
  // Check if a flash error toast is rendered (production apps render one)
  const flash = document.querySelector('.alert-danger, [role=alert].danger, .toast-error');
  if (flash && flash.offsetParent !== null) return flash.innerText;
  return null;
})()
```

If you must scan raw body text, match anchored phrases: `/Whoops|stack trace|Symfony\\Component|Debug\\bar|exception trace|internal server error/i` — all of these are unmistakable; digit-substring matches are not.

## Bot / brand-detection false positives in screenshots

When reviewing screenshots rendered headlessly via Browserbase / Browser Use Cloud, font-icon characters (FontAwesome, Material Icons) often render as empty squares or `?`. **Don't report "icons missing" as an app bug** — verify first by loading the same page in a real Chrome browser locally. If icons render fine in real Chrome but show as `?` in your headless screenshot, the issue is your screenshot stack, not the app.

Snapshot DOM text dump will report the icon classes (`<i class="fa fa-home"></i>`) even when the icon font fails to render — that's another signal that this is a rendering artifact, not a missing icon in the source.

## Per-route smoke sweep recipe (works with either browser_harness or browser_navigate)

For systematic coverage of every menu link:

```python
SCRIPT_TEMPLATE = '''
import time, json
goto_url("BASEURL" + "PATH")
wait_for_network_idle(20)
time.sleep(1.5)  # let any deferred script run
print("CHECKT|" + json.dumps({
    "path": "PATH",
    "title": js("document.title"),
    "url": js("location.href"),
    "bodyLen": js("document.body.innerText.length"),
    "snippet": js("(document.body.innerText||'').replace(/\\s+/g,' ').trim().slice(0, 280)"),
    # structured error lookups, not substring matches
    "hasExceptionEl": js("!!document.querySelector('.exception-message, .exception_title, [data-laravel-exception]')"),
    "hasFlashErrorEl": js("(()=>{const e=document.querySelector('.alert-danger, [role=alert].danger, .toast-error');return !!e && e.offsetParent!==null})()"),
}))
'''
```

Run for every menu link harvested from a sidebar sweep. Expect ~3-5 s/route. With 30+ routes that's a few minutes — run in background with `notify_on_complete=true` if available. Then classify each result:

| `bodyLen` | `title` looks legitimate | No exception/flash | Verdict |
|---|---|---|---|
| > 200 | yes | yes | ✅ healthy |
| < 200 | (login redirect, error, empty) | any | 🚩 investigate |
| any | "Not Found" / "404" / "500 Internal Server Error" | any | 🔴 broken route |
| any | (empty) | yes | 🚩 check redirect target |

## Reporting

Generate the final report using the template at `templates/dogfood-report-template.md`.

The report must include:
1. **Executive summary** with total issue count, breakdown by severity, and testing scope
2. **Per-issue sections** with:
   - Issue number and title
   - Severity and category badges
   - URL where observed
   - Description of the issue
   - Steps to reproduce
   - Expected vs actual behavior
   - Screenshot references (use `MEDIA:<screenshot_path>` for inline images)
   - Console errors if relevant
3. **Summary table** of all issues
4. **Testing notes** — what was tested, what was not, any blockers

Save the report to `{output_dir}/report.md`.
