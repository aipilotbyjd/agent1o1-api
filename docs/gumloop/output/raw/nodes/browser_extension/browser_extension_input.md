> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Browser Extension Input

The Browser Extension Input node allows you to perform actions on a web page captured by the Gumloop Chrome Extension and use it as input for your workflow. It works by capturing the content of the web page you are looking at, and sending it to Gumloop to perform a scraping or screenshotting action on.

This node requires the [Gumloop Chrome Extension](https://chromewebstore.google.com/detail/gumloop/hpkeijgchoedhhdfjdjlaimanpmbnhjm) to function properly.

## Node Inputs

This node accepts the following input for customization:

* **Action**: Define the action you want to take on the captured web page. Options include:
  * Scrape
  * Scrape source
  * Screenshot
  * Screenshot - full page
  * Get all URLs

## Node Outputs

The node generates the following outputs:

* **Action Output**: The result of the action performed on the captured web page. This is the scraped text in the case of "Scrape", URL to the screenshot in the case of "Screenshot", etc.
* **URL**: The URL of the captured web page.

## Node Functionality

The Browser Extension Input node serves as a bridge between the Gumloop Chrome Extension and your workflow. It allows you to perform various actions on a web page captured by the extension and use the results in your workflow.

### Actions

1. **Scrape**: Extracts the visible text content from the captured web page.
2. **Scrape source**: Retrieves the full HTML source of the captured web page.
3. **Screenshot**: Takes a screenshot of the visible area of the captured web page.
4. **Screenshot - full page**: Captures a full-page screenshot of the entire web page, including content beyond the visible viewport.
5. **Get all URLs**: Extracts all URLs (href attributes) found on the captured web page.

### When To Use

Use the Browser Extension Input node when you want to:

* Extract information from web pages captured by the Gumloop Chrome Extension.
* Incorporate web page content or screenshots into your workflow.
* Analyze or process web page data as part of your workflow.

This node is particularly useful for web scraping, data extraction, and automating web-based tasks that require interaction with specific web pages.

## More information

* [Video Tutorial](https://www.loom.com/share/6b343be195ba4a55a66ce26894b303f9)
