> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Generate Report

The Generate Report node transforms raw content and data into professionally formatted reports using AI. Perfect for creating polished business reports, documentation, and shareable content with minimal effort.

<div align="center">
  <img src="https://mintcdn.com/agenthub/58mCF7VRfgpUGu5i/images/generate_report_overview.png?fit=max&auto=format&n=58mCF7VRfgpUGu5i&q=85&s=651b9c5009ad0e79c459368a02853858" alt="Generate Report Node Overview" width="800" data-path="images/generate_report_overview.png" />
</div>

## Why Use Generate Report?

When you need to send well-formatted content to your team or clients, the Generate Report node saves you time by automatically creating professional-looking output in the right format for your destination. Whether you're sending HTML emails, writing to Google Docs, or posting to Slack, this node ensures your content looks great without manual formatting.

<CardGroup cols={3}>
  <Card title="HTML Reports" icon="envelope">
    Generate email-ready HTML with responsive design for Gmail and other email clients
  </Card>

  <Card title="Markdown Reports" icon="markdown">
    Create formatted documents for Google Docs, GitHub, or technical documentation
  </Card>

  <Card title="Slack Messages" icon="slack">
    Build native Slack Block Kit messages with rich formatting and visual hierarchy
  </Card>
</CardGroup>

## Node Inputs

### Required Fields

<AccordionGroup>
  <Accordion title="Report Content" icon="file-lines">
    The raw content or data you want to transform into a formatted report. This can include:

    * Analysis results from AI nodes
    * Data from spreadsheets or databases
    * Bullet points or unstructured notes
    * Any information you want professionally formatted

    **Example Input:**

    ```text theme={"dark"}
    Q4 revenue was $2.3M, up 15% from Q3. 
    Customer retention improved to 94%. 
    New client acquisition: 12 companies.
    ```
  </Accordion>
</AccordionGroup>

### Optional Fields

<AccordionGroup>
  <Accordion title="Report Format" icon="palette">
    Choose the output format based on where you'll use the report:

    * **HTML**: Best for emails via Gmail Sender or Send Email Notification
    * **Markdown**: Best for Google Docs, GitHub, or technical documentation
    * **Slack Block Kit**: Best for Slack messages via Slack Block Kit Sender

    **Default:** HTML
  </Accordion>

  <Accordion title="Extra Instructions" icon="wand-magic-sparkles">
    Customize the report's appearance and structure with specific instructions:

    **Styling Examples:**

    * "Use a color palette of #fb3c98 and #0190ff"
    * "Include a table for all numeric data"
    * "Use bullet points for lists"

    **Content Examples:**

    * "Focus on key metrics only"
    * "Keep the tone formal and professional"
    * "Include an executive summary at the top"

    <Info>Extra Instructions must be enabled in "More Options" to appear</Info>
  </Accordion>

  <Accordion title="Logo URL" icon="image">
    For HTML reports only. Add your company logo at the top of the email by providing a publicly accessible URL.

    **Requirements:**

    * Must be a public URL (not behind authentication)
    * Optimal size: 200-400px wide
    * Best formats: PNG or SVG for transparency
    * Square or horizontal logos work best

    <Info>Logo URL must be enabled in "More Options" and only appears when format is HTML</Info>
  </Accordion>
</AccordionGroup>

### More Options

<AccordionGroup>
  <Accordion title="Include Extra Instructions?" defaultOpen={false}>
    Toggle this on to show the Extra Instructions field where you can customize report generation with specific formatting or content preferences.

    **Default:** Off
  </Accordion>

  <Accordion title="Include Logo?" defaultOpen={false}>
    Toggle this on to show the Logo URL field for adding a company logo to HTML reports. Only available when Report Format is set to HTML.

    **Default:** Off
  </Accordion>
</AccordionGroup>

### AI Model Fallback

Under **Show More Options**, configure automatic fallback when your selected AI model is unavailable. **Fallback is enabled by default.**

<Frame>
  <img src="https://mintcdn.com/agenthub/pnvaw4qtuKQMFMni/images/ai_model_fallback_generate_report.png?fit=max&auto=format&n=pnvaw4qtuKQMFMni&q=85&s=4b4f77b89d1d8c93ba4f8f6b570eb8fb" alt="AI Model Fallback settings" width="500" data-path="images/ai_model_fallback_generate_report.png" />
</Frame>

When an error occurs (rate limits, provider outages, timeouts), the system retries based on severity, then falls back to the next model. Fallback models are always from different providers for true redundancy.

| Error Type    | Retries Before Fallback |
| ------------- | ----------------------- |
| Rate Limit    | 2                       |
| Provider 5xx  | 1                       |
| Network Error | 0 (immediate)           |
| Timeout       | 1                       |

**Default (Auto):** The system automatically selects fallback models based on your primary model, always choosing from different providers for true redundancy.

**Override:** Enable to manually select up to 2 fallback models with drag-and-drop priority.

<Warning>Disabling fallback means your node will fail if the primary model is unavailable.</Warning>

## Node Output

<AccordionGroup>
  <Accordion title="Report Content" icon="file-export">
    The complete, professionally formatted report ready to use:

    * **HTML**: Complete HTML that can be sent directly via email
    * **Markdown**: Rendered markdown with tables, headers, and formatting
    * **Slack Block Kit**: Valid JSON that can be posted directly to Slack's API

    All output is cleaned and ready to use without additional processing.
  </Accordion>
</AccordionGroup>

## How It Works

<Steps>
  <Step title="Analyze Your Content">
    The node examines your raw content to understand its structure and key information
  </Step>

  <Step title="Apply Professional Formatting">
    Based on your selected format, AI applies sophisticated formatting rules to create a polished report with proper hierarchy, styling, and layout
  </Step>

  <Step title="Generate Final Output">
    The formatted report is generated in your chosen format, ready to send to its destination
  </Step>
</Steps>

## Format Details

<Tabs>
  <Tab title="HTML">
    ### HTML Report Features

    Perfect for sending professional emails through Gmail Sender or Send Email Notification nodes.

    **Characteristics:**

    * Gmail-compatible table-based layout
    * Responsive design (mobile-friendly)
    * Inline CSS styling
    * Optional company logo placement
    * Professional color schemes

    **Best For:**

    * Weekly status reports to management
    * Customer-facing communications
    * Executive summaries
    * External stakeholder updates

    <Warning>
      When using with email nodes, remember to enable "Send as HTML" in the email node settings
    </Warning>
  </Tab>

  <Tab title="Markdown">
    ### Markdown Report Features

    Ideal for technical documentation and platforms that support markdown rendering.

    **Characteristics:**

    * Clean, readable structure
    * Tables for data visualization
    * Status indicators with emojis
    * Professional typography hierarchy
    * Executive summaries with key takeaways

    **Best For:**

    * Technical documentation
    * Google Docs (enable markdown in Google Doc Writer)
    * GitHub repositories
    * Internal team documentation
    * Project updates
  </Tab>

  <Tab title="Slack Block Kit">
    ### Slack Block Kit Features

    Creates native Slack messages with rich formatting through Slack Block Kit Sender.

    **Characteristics:**

    * Native Slack formatting
    * Header, section, and divider blocks
    * Context and field blocks for metadata
    * Text-only (no interactive buttons)
    * Scannable layout

    **Best For:**

    * Team announcements
    * Status updates
    * Daily/weekly digests
    * Project milestone notifications

    <Info>
      Output is valid JSON ready to use with Slack Block Kit Sender node
    </Info>
  </Tab>
</Tabs>

## Common Use Cases

<CardGroup cols={2}>
  <Card title="Weekly Status Reports" icon="chart-line">
    **Workflow Pattern:**

    ```text theme={"dark"}
    Google Sheets → Generate Report (HTML) → Gmail Sender
    ```

    Pull data from spreadsheets, format as professional HTML report, and email to stakeholders
  </Card>

  <Card title="Customer Analysis" icon="users">
    **Workflow Pattern:**

    ```text theme={"dark"}
    Data Source → Ask AI → Generate Report (HTML) → Gmail Sender
    ```

    Analyze customer data with AI, format findings into report, and send to account managers
  </Card>

  <Card title="Team Updates" icon="slack">
    **Workflow Pattern:**

    ```text theme={"dark"}
    Multiple Sources → Generate Report (Slack) → Slack Sender
    ```

    Combine information from various sources and post formatted update to Slack channel
  </Card>

  <Card title="Documentation" icon="book">
    **Workflow Pattern:**

    ```text theme={"dark"}
    Content Source → Generate Report (Markdown) → Google Doc Writer
    ```

    Transform content into formatted documentation and save to Google Docs with markdown enabled
  </Card>
</CardGroup>

## Loop Mode Support

Generate Report fully supports Loop Mode for batch processing. This is perfect when you need to create multiple reports from a list of items.

<Accordion title="Batch Report Generation Example">
  **Scenario:** Generate individual reports for each sales rep's monthly performance

  **Setup:**

  1. Load sales data for all reps (returns a list)
  2. Enable Loop Mode on Generate Report
  3. Connect sales data to Report Content
  4. Result: One formatted report per sales rep

  **Input (List):**

  ```text theme={"dark"}
  [
    "Rep: Alice, Sales: $50K, Deals: 12",
    "Rep: Bob, Sales: $45K, Deals: 10",
    "Rep: Carol, Sales: $62K, Deals: 15"
  ]
  ```

  **Output:** Three separate formatted reports, one for each rep
</Accordion>

## AI Model & Credit Costs

The Generate Report node uses AI completion to create formatted output. It is billed by **token usage**, the same way agents are, so the cost of a run depends on the model you pick and how many input and output tokens it uses. There are no fixed per-run tiers.

**Total Cost:** Base workflow execution (1 credit) + the AI model's token cost

* Smaller, faster models cost less per token than frontier models. See [AI Models](/core-concepts/ai_models).
* With **BYOK**, the node's AI calls cost **no credits** (Pro plan or higher).

<Info>
  You can configure your preferred AI model at the workflow or organization level. For most reports, a smaller, faster model gives excellent results at a lower cost.
</Info>

## Integration Patterns

### Sending HTML Emails

<div align="center">
  <img src="https://mintcdn.com/agenthub/58mCF7VRfgpUGu5i/images/generate_report_html_email_flow.png?fit=max&auto=format&n=58mCF7VRfgpUGu5i&q=85&s=a79d30c14e2cbb788991c368a7aa047a" alt="HTML Email Integration Workflow" width="800" data-path="images/generate_report_html_email_flow.png" />
</div>

<Steps>
  <Step title="Generate the Report">
    Connect your content to Generate Report with Format set to **HTML**
  </Step>

  <Step title="Connect to Email Node">
    Use either **Gmail Sender** or **Send Email Notification** node
  </Step>

  <Step title="Enable HTML Mode">
    In the email node settings, toggle on **"Send as HTML"**
  </Step>

  <Step title="Connect Report Content">
    Link the Report Content output to the email body input
  </Step>
</Steps>

### Writing to Google Docs

<div align="center">
  <img src="https://mintcdn.com/agenthub/Rk7KWL5fhItTF3xH/images/generate_report_google_docs_flow.png?fit=max&auto=format&n=Rk7KWL5fhItTF3xH&q=85&s=045b8ca55c2438cbd5e14aa99889b113" alt="Google Docs Integration Workflow" width="600" data-path="images/generate_report_google_docs_flow.png" />
</div>

<Steps>
  <Step title="Generate Markdown">
    Set Report Format to **Markdown** in Generate Report node
  </Step>

  <Step title="Connect to Google Doc Writer">
    Link Report Content to the content input
  </Step>

  <Step title="Enable Markdown">
    In Google Doc Writer node, enable **markdown rendering**
  </Step>
</Steps>

### Posting to Slack

<div align="center">
  <img src="https://mintcdn.com/agenthub/58mCF7VRfgpUGu5i/images/generate_report_slack_flow.png?fit=max&auto=format&n=58mCF7VRfgpUGu5i&q=85&s=bfd4db70ea0c30018d433f20e9a701f6" alt="Slack Integration Workflow" width="600" data-path="images/generate_report_slack_flow.png" />
</div>

<Steps>
  <Step title="Generate Slack Blocks">
    Set Report Format to **Slack Block Kit** in Generate Report node
  </Step>

  <Step title="Connect to Slack Node">
    Use **Slack Block Kit Sender** (not regular Slack Sender)
  </Step>

  <Step title="Link Output">
    Connect Report Content to the blocks input
  </Step>
</Steps>

## Best Practices

<CardGroup cols={2}>
  <Card title="Content Preparation" icon="list-check">
    * Provide structured input when possible (bullet points, sections)
    * Include context about what the report is for
    * Specify key metrics or data points to highlight
  </Card>

  <Card title="Extra Instructions" icon="bullhorn">
    * Be specific: "Use blue (#0066cc) for headers" vs "use blue"
    * Include format preferences: "Use tables for data"
    * Specify tone if needed: "Professional and formal"
  </Card>

  <Card title="Format Selection" icon="layer-group">
    * **HTML**: Best for emails, formal reports, external sharing
    * **Markdown**: Best for documentation, GitHub, technical reports
    * **Slack Block Kit**: Best for team notifications, status updates
  </Card>

  <Card title="Logo Usage" icon="image">
    * Use square or horizontal logos (vertical may stretch)
    * Optimal size: 200-400px wide
    * Use PNG or SVG for transparency
    * Ensure URL is publicly accessible
  </Card>
</CardGroup>

## Troubleshooting

<AccordionGroup>
  <Accordion title="HTML output doesn't render correctly in email">
    **Cause:** Email node not set to send as HTML

    **Solution:** Enable "Send as HTML" toggle in your Gmail Sender or Send Email Notification node settings
  </Accordion>

  <Accordion title="Logo not appearing in HTML report">
    **Cause:** Logo URL is not publicly accessible or invalid

    **Solution:**

    1. Verify the URL works in a browser when not logged in
    2. Check that the URL points directly to an image file
    3. Ensure it's not behind any authentication
  </Accordion>

  <Accordion title="Markdown not formatting in Google Doc">
    **Cause:** Markdown rendering not enabled in Google Doc Writer

    **Solution:** Open the Google Doc Writer node settings and enable markdown rendering
  </Accordion>

  <Accordion title="Slack message fails to send">
    **Cause:** Using regular Slack Sender instead of Slack Block Kit Sender

    **Solution:** Use the **Slack Block Kit Sender** node specifically for Slack Block Kit formatted output
  </Accordion>

  <Accordion title="Report doesn't match my instructions">
    **Cause:** Extra Instructions may be too vague or conflicting

    **Solution:**

    1. Be more specific in your instructions
    2. Try using a more advanced AI model
    3. Test with different phrasings
  </Accordion>
</AccordionGroup>

## Tips for Great Reports

<Tip>
  **Start Simple, Then Customize**

  Begin with default settings to see what the node produces. Then add Extra Instructions to refine specific aspects you want to change.
</Tip>

<Tip>
  **Test Your Output First**

  Before using in production workflows:

  * For HTML emails: Send a test to yourself
  * For Slack: Test in a private channel
  * For Google Docs: Create a test document first
</Tip>

<Tip>
  **Consider Your Audience**

  Choose your format based on who will read it:

  * **HTML**: External stakeholders, executives
  * **Markdown**: Technical teams, developers
  * **Slack Block Kit**: Internal teams, quick updates
</Tip>

## Related Nodes

<CardGroup cols={2}>
  <Card title="Ask AI" icon="robot" href="https://docs.gumloop.com/nodes/using_ai/ask_ai">
    Generate content or analysis that feeds into Generate Report
  </Card>

  <Card title="Gmail Sender" icon="envelope" href="https://docs.gumloop.com/nodes/integrations/gmail_sender">
    Send HTML reports via email
  </Card>

  <Card title="Slack Block Kit Sender" icon="slack" href="https://docs.gumloop.com/nodes/integrations/slack_block_kit_sender">
    Post formatted reports to Slack channels
  </Card>

  <Card title="Google Doc Writer" icon="google" href="https://docs.gumloop.com/nodes/integrations/gdocs_writer">
    Write markdown reports to Google Docs
  </Card>
</CardGroup>
