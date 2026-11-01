> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Agent Triggers

> Run your agents automatically on a schedule or in response to external events like emails, Slack messages, and more.

Agents can run autonomously without manual interaction. Set up **scheduled triggers** to run on a recurring or one-time basis, or create **event-based triggers** to fire your agent when something happens in an external service.

<iframe src="https://player.vimeo.com/video/1174818142?h=1358d901e9" width="100%" height="400px" frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="Run Triggers with your Agents" />

<Frame>
  <img src="https://mintcdn.com/agenthub/bbXbnqjgnQEuzBbZ/images/agent_task.png?fit=max&auto=format&n=bbXbnqjgnQEuzBbZ&q=85&s=6f9e8b0374cc3665c60b20d7de74d557" alt="Trigger type selector showing Scheduled Trigger and Event-Based Trigger options" width="890" height="326" data-path="images/agent_task.png" />
</Frame>

All triggers are managed from the **Triggers** section of your agent's configuration page. Click **+ Trigger** to create a new one.

<Tip>Need to monitor multiple services, apply custom logic, or watch an app without a pre-built trigger? Use [**Create With AI**](/core-concepts/ai_trigger_creation) to build custom triggers in natural language.</Tip>

***

## Scheduled Triggers

Schedule your agent to run automatically on a **recurring schedule** or as a **one-time trigger**. When a trigger fires, the agent receives a prompt you configure and processes it exactly as if you had typed it in the chat.

### Setting Up a Scheduled Trigger

Go to your agent's configuration page, find the **Triggers** section, and click **+ Trigger**. Choose **Scheduled Trigger** as the type, then fill in three fields:

* **Name**: A short label (e.g., "Daily Ticket Summary")
* **Schedule**: When the trigger should run (recurring cron or one-time)
* **Prompt**: The message your agent receives when the trigger fires

Click **Create** and the trigger is immediately active.

<Tabs>
  <Tab title="Recurring">
    Runs on a cron schedule until you pause or delete it. You don't need to know cron syntax: describe your schedule in plain language and the AI generates the expression.

    <img src="https://mintcdn.com/agenthub/WuMv2bE3jTJldGq-/images/scheduled_task_dialogue_recurring.png?fit=max&auto=format&n=WuMv2bE3jTJldGq-&q=85&s=49509066d8ef3304d1c3cda84b7ce448" alt="Create a Scheduled Trigger dialog showing the Recurring tab with name, AI-powered schedule field, cron expression, and prompt" style={{ maxHeight: '420px', border: '1px solid black', borderRadius: '8px' }} width="1198" height="1218" data-path="images/scheduled_task_dialogue_recurring.png" />

    <Info>Minimum interval is 1 minute. Timezones default to your browser's timezone.</Info>
  </Tab>

  <Tab title="One-time">
    Runs once and is **automatically deleted** after execution.

    <img src="https://mintcdn.com/agenthub/WuMv2bE3jTJldGq-/images/scheduled_task_dialogue_one_time.png?fit=max&auto=format&n=WuMv2bE3jTJldGq-&q=85&s=e34342706e49fa952b3edc64b322d1e6" alt="Create a Scheduled Trigger dialog showing the One-time tab with name, date/time picker, and prompt" style={{ maxHeight: '420px', border: '1px solid black', borderRadius: '8px' }} width="1222" height="1170" data-path="images/scheduled_task_dialogue_one_time.png" />

    * **Relative** ("In" mode): In 30 minutes, 2 hours, 3 days
    * **Absolute** ("At" mode): Pick a specific date and time
  </Tab>
</Tabs>

Once created, triggers appear in the **Triggers** section. Click to edit, or use the three-dot menu to enable, disable, or delete.

<img src="https://mintcdn.com/agenthub/gWPgylGIiekacbew/images/task_schedule_created.png?fit=max&auto=format&n=gWPgylGIiekacbew&q=85&s=521e001ed6797cca9941856ef0f6fc66" alt="Triggers section showing a created Ticket Summary trigger scheduled at 09:00 PM every 2 days" style={{ border: '1px solid black', borderRadius: '8px' }} width="916" height="238" data-path="images/task_schedule_created.png" />

### Writing Good Trigger Prompts

The prompt is what your agent receives when the trigger fires. Be specific:

| ✅ Good                                                                                           | ❌ Bad                 |
| ------------------------------------------------------------------------------------------------ | --------------------- |
| "Give me a summary of all the Zendesk tickets created in the last two days."                     | "Do the usual thing." |
| "Check my Gmail for unread client emails, summarize them, and post to #client-updates in Slack." | "Check emails."       |

<Tip>If your agent has a relevant [skill](/core-concepts/skills) for the task, reference it in the prompt. The agent will load the skill and follow its instructions for consistent results.</Tip>

### Self-Scheduling

Your agent can create and manage its own triggers during a conversation. Just tell it what you need:

| What you say                                       | What happens                                   |
| -------------------------------------------------- | ---------------------------------------------- |
| "Remind me to check emails every weekday at 9 AM"  | Agent creates a recurring scheduled trigger    |
| "In 30 minutes, check if the deployment succeeded" | Agent creates a one-time delayed trigger       |
| "Show me my active schedules"                      | Agent lists all its triggers                   |
| "Pause the daily email check"                      | Agent disables the trigger without deleting it |

Agents can only manage their own schedules, not those of other agents.

***

## Event-Based Triggers

Event-based triggers fire your agent when something happens in an external service. Instead of checking manually, your agent reacts automatically to new emails, messages, database changes, and more.

When a trigger fires, the event data (e.g., the email body, the Slack message) is injected into a prompt template you write. The agent processes it as if you had typed it in the chat.

### Supported Integrations

<CardGroup cols={3}>
  <Card title="Gmail" icon="envelope">
    New email in a label
  </Card>

  <Card title="Slack" icon="slack">
    New channel message
  </Card>

  <Card title="Slack Reaction" icon="face-smile-plus">
    Emoji reaction on a message
  </Card>

  <Card title="Microsoft Teams" icon="microsoft">
    New channel message
  </Card>

  <Card title="Google Drive" icon="google-drive">
    New file in a folder
  </Card>

  <Card title="Google Sheets" icon="table">
    New or updated row
  </Card>

  <Card title="Google Calendar" icon="calendar">
    Upcoming event
  </Card>

  <Card title="Notion" icon="book">
    New or updated page
  </Card>

  <Card title="Airtable" icon="table-cells">
    New or updated record
  </Card>

  <Card title="Zendesk" icon="headset">
    New ticket or comment
  </Card>

  <Card title="Salesforce" icon="cloud">
    New or updated record in any object
  </Card>

  <Card title="Linear" icon="clipboard-list">
    New or updated issue
  </Card>

  <Card title="Jira" icon="clipboard-list">
    New issue created
  </Card>

  <Card title="Monday.com" icon="table-columns">
    Item, subitem, update, or column change on a board
  </Card>

  <Card title="Parallel Web Monitor" icon="globe">
    Web changes matching a query
  </Card>
</CardGroup>

<Info>Also supported: **Google Forms**, **Typeform**, **HubSpot**, **incident.io**, and **Jira**. Some integrations (Gmail, Slack, Slack Reaction, Teams, Drive, Forms, Typeform, Zendesk, Monday.com, Parallel Web Monitor) are **real-time** and fire within seconds. Others (Sheets, Calendar, Notion, Airtable, HubSpot, incident.io, Salesforce, Linear, Jira) use **polling** and check for changes approximately every 60 seconds.</Info>

### Setting Up an Event-Based Trigger

Go to your agent's configuration page, find the **Triggers** section, and click **+ Trigger**. Choose **Event-Based Trigger** as the type. Then follow three steps:

<Steps>
  <Step title="Select a Trigger">
    Choose which integration event should fire your agent.

    <Frame>
      <img src="https://mintcdn.com/agenthub/WQjFReX3Evlw7BFh/images/select_event_trigger.png?fit=max&auto=format&n=WQjFReX3Evlw7BFh&q=85&s=c7e0ba99cdbaabaed4db3349b5eb6f8e" alt="Select Trigger dialog showing available integrations: Google Drive, Google Sheets, Google Calendar, Gmail, Slack, Teams, Notion, and Airtable" width="2158" height="1054" data-path="images/select_event_trigger.png" />
    </Frame>
  </Step>

  <Step title="Configure the Integration">
    Connect your credentials and set up the specific parameters for your trigger. For example, with Gmail you can choose which label to monitor, whether to mark emails as read, and whether to read HTML content.

    <Frame>
      <img src="https://mintcdn.com/agenthub/WQjFReX3Evlw7BFh/images/setup_event_trigger.png?fit=max&auto=format&n=WQjFReX3Evlw7BFh&q=85&s=a5cd0bd97e8648b008a7d3469b53a45e" alt="Gmail trigger setup with credential selection, label filter, and options for Mark as Read and Read as HTML" width="2160" height="718" data-path="images/setup_event_trigger.png" />
    </Frame>

    <Info>Your agent uses the credentials of the person who created the trigger. Make sure you've connected the required app on your [Connectors page](https://www.gumloop.com/personal/connectors) before setting up the trigger.</Info>
  </Step>

  <Step title="Write a Prompt Template">
    Define what your agent should do when the trigger fires. Use **template variables** (shown as badges) to inject event data into your prompt.

    <Frame>
      <img src="https://mintcdn.com/agenthub/66M6srYbC0Oj5XyU/images/prompt_event_trigger.png?fit=max&auto=format&n=66M6srYbC0Oj5XyU&q=85&s=a4953c4dabbadc4481b40d998db6f4d1" alt="Prompt template editor showing trigger name and a prompt with template variables like Email Body, Attached File Name, and more" width="2150" height="944" data-path="images/prompt_event_trigger.png" />
    </Frame>

    Template variables are specific to each integration. For Gmail, you get variables like `Email Body`, `Subject`, `Sender`, and `Attached File Name`. For Slack, you get `Message`, `Sender Name`, `Channel Name`, etc.
  </Step>
</Steps>

### Prompt Templates

When an event-based trigger fires, the event data is injected into your prompt template before the agent receives it.

**How it works:**

1. You write a prompt template with placeholder variables (shown as badges in the UI)
2. When the trigger fires, each variable is replaced with the actual event data
3. The agent receives the fully resolved prompt and acts on it

**Example:**

Your template:

```text theme={"dark"}
New email from {Sender}: Subject: {Subject}
{Email Body}
Please categorize this email and draft a response if it's from a client.
```

When fired, your agent sees:

```text theme={"dark"}
New email from john@acme.com: Subject: Q1 Budget Review
Hi, can we schedule a meeting to discuss the Q1 budget numbers?
Please categorize this email and draft a response if it's from a client.
```

<Tip>You can also toggle **Pass Raw Data** to send the entire event payload as JSON instead of using template variables. This is useful when you want the agent to decide what's relevant.</Tip>

### Managing Active Triggers

All your triggers (both scheduled and event-based) appear in the **Triggers** section of your agent's configuration. From here you can edit, deactivate, or delete any trigger.

Deactivating every trigger is the supported way to switch an agent off: the agent stops running on its own, but its configuration, connectors, and triggers stay intact and you can re-enable them at any time. Do not use `is_active: false` on the [agents API](/api-reference/agents/update-agent) or `gumloop agents update --inactive` for this — that retires the agent instead of pausing it.

<Frame>
  <img src="https://mintcdn.com/agenthub/bbXbnqjgnQEuzBbZ/images/active_triggers.png?fit=max&auto=format&n=bbXbnqjgnQEuzBbZ&q=85&s=baf0caacb153a7b181bd7e7ff0f6ae96" alt="Triggers list showing active triggers including Slack Events, Incoming Email, and AI Trigger Editing & Creation with Edit, Deactivate, and Delete options" width="908" height="412" data-path="images/active_triggers.png" />
</Frame>

### Integration Details

<AccordionGroup>
  <Accordion title="Gmail" icon="envelope">
    **Fires when**: A new email arrives in the specified label. *(Real-time)*

    **Configuration**: Choose your Gmail credential, select a label to monitor (e.g., INBOX, a custom label), and optionally enable "Mark as Read" and "Read as HTML".

    **Available variables**: Email Body, Subject, Sender, Attached File Name, Date, Message ID, Thread ID

    <Warning>Due to Google API limitations, only one Gmail account can be monitored per credential. For multiple accounts, create separate triggers with different credentials.</Warning>
  </Accordion>

  <Accordion title="Slack" icon="slack">
    **Fires when**: A new message is posted in the specified channel. *(Real-time)*

    **Configuration**: Choose your Slack credential and select the channel to monitor. "Ignore Bot Messages" is enabled by default. You can also optionally ignore thread replies.

    **Available variables**: Message, Sender Name, Channel Name, Channel ID, Date, Thread ID, Attachment Names

    <Tip>"Ignore Bot Messages" is on by default to prevent infinite loops if your agent posts back to the same channel. We recommend keeping it enabled unless you specifically need to process bot messages.</Tip>
  </Accordion>

  <Accordion title="Slack Reaction" icon="face-smile-plus">
    **Fires when**: Someone reacts to a message with an emoji in the specified channel. *(Real-time)*

    **Configuration**: Choose your Slack credential and select the channel to watch. Optionally filter by specific emoji (e.g., `white_check_mark`, `thumbsup`). Leave the emoji filter empty to trigger on any reaction. "Ignore Reactions From Bots" is enabled by default. You can also include or exclude reactions on thread replies, and optionally read the full thread for additional context.

    **Available variables**: Message, Emoji, Reaction Count, Reacted By, Message Sender, Attachment Names, Thread ID, Thread Link, Channel Name, Channel ID, Date

    <Tip>"Ignore Reactions From Bots" is on by default. Reactions from Gumloop itself are always ignored regardless of this setting, preventing infinite loops if your agent reacts to messages in the same channel.</Tip>
  </Accordion>

  <Accordion title="Microsoft Teams" icon="microsoft">
    **Fires when**: A new message is posted in the specified Teams channel. *(Real-time)*

    **Configuration**: Choose your Teams credential, select the team and channel. Optionally ignore bot messages and thread replies.

    **Available variables**: Message, Sender Name, Channel Name, Channel ID, Date, Subject

    <Warning>Teams triggers only work with Microsoft 365 work or school accounts. Personal Microsoft accounts are not supported.</Warning>

    <Tip>Want your team to chat with an agent inside a Teams channel instead? See [Using Agents in Microsoft Teams](/core-concepts/agents_teams).</Tip>
  </Accordion>

  <Accordion title="Google Drive" icon="google-drive">
    **Fires when**: A new file is uploaded to the specified folder. *(Real-time)*

    **Configuration**: Choose your Google Drive credential and select the folder to watch.

    **Available variables**: File Name, File ID, File Type, Modified Date
  </Accordion>

  <Accordion title="Google Sheets" icon="table">
    **Fires when**: A new row is added or an existing row is updated, depending on your trigger mode. *(Polling, \~60s)*

    **Configuration**: Choose your Google Sheets credential, select the spreadsheet and worksheet. Pick a trigger mode: "Create Row" (new rows only) or "Create or Update Row" (new and modified rows).

    **Available variables**: All column values from the row that triggered the event
  </Accordion>

  <Accordion title="Google Calendar" icon="calendar">
    **Fires when**: An event is approaching on your calendar. *(Polling, \~60s)*

    **Configuration**: Choose your Google Calendar credential, select the calendar, and set how many minutes before the event the trigger should fire (default: 15 minutes).

    **Available variables**: Event Title, Start Time, End Time, Attendees, Description, Location
  </Accordion>

  <Accordion title="Notion" icon="book">
    **Fires when**: A new page is created or an existing page is updated in the specified database. *(Polling, \~60s)*

    **Configuration**: Choose your Notion credential and select the database to monitor.

    **Available variables**: All database property values from the page that triggered the event
  </Accordion>

  <Accordion title="Airtable" icon="table-cells">
    **Fires when**: A new record is created or an existing record is updated in the specified table. *(Polling, \~60s)*

    **Configuration**: Choose your Airtable credential, select the base, table, and optionally a view. Requires a "Last Modified Timestamp" field in your table.

    **Available variables**: All field values from the record that triggered the event
  </Accordion>

  <Accordion title="Zendesk" icon="headset">
    **Fires when**: Depending on your trigger mode: new ticket created, new comment added, ticket status changed, or ticket enters a view. *(Real-time)*

    **Configuration**: Choose your Zendesk credential and select a trigger mode. Optionally filter by ticket type, priority, or status.

    **Available variables**: Ticket ID, URL, Subject, Description, Status, Priority, Type, Requester Email, Assignee Email, Comments
  </Accordion>

  <Accordion title="Google Forms" icon="square-poll-horizontal">
    **Fires when**: A new form response is submitted. *(Real-time)*

    **Configuration**: Choose your Google credential and select the Google Form to monitor.

    **Available variables**: All submitted form field values
  </Accordion>

  <Accordion title="Typeform" icon="square-check">
    **Fires when**: A new form submission is received. *(Real-time)*

    **Configuration**: Choose your Typeform credential and select the form to monitor.

    **Available variables**: All submitted form field values
  </Accordion>

  <Accordion title="HubSpot" icon="hubspot">
    **Fires when**: New records appear in a specified HubSpot list. *(Polling, \~60s)*

    **Configuration**: Choose your HubSpot credential, select the list to monitor, and configure the object type.

    **Available variables**: All property values from the records that triggered the event
  </Accordion>

  <Accordion title="incident.io" icon="triangle-exclamation">
    **Fires when**: A new incident is detected. *(Polling, \~60s)*

    **Configuration**: Choose your incident.io credential. Optionally filter by severity (Minor, Major, Critical) and mode (Standard, Retrospective, Tutorial, Test).

    **Available variables**: Incident ID, Name, Status, Severity, Timestamps, Summary, Permalink, Slack Channel ID
  </Accordion>

  <Accordion title="Salesforce" icon="cloud">
    **Fires when**: A new record is created or an existing record is updated in the selected Salesforce object, depending on the trigger mode. *(Polling, \~60s)*

    **Configuration**: Choose your Salesforce credential, select the object to monitor (e.g., Lead, Contact, Opportunity, Account, Case, or any custom object), and choose a **Trigger Mode**:

    * **New Record**: Fires when a new record is created
    * **Updated Record**: Fires when an existing record is modified (newly created records are automatically excluded)

    **Available variables**: All fields from the selected Salesforce object (e.g., First Name, Last Name, Email, Company, etc.). The exact fields depend on the object type.

    <Info>The trigger uses a compound cursor based on the relevant timestamp field (`CreatedDate` or `LastModifiedDate`) and record `Id` to avoid duplicates. It fetches up to 5 records per poll. In Updated Record mode, each modification to a record triggers the agent again.</Info>
  </Accordion>

  <Accordion title="Linear" icon="clipboard-list">
    **Fires when**: A new issue is created or an existing issue is updated in the selected Linear team, depending on the trigger mode. *(Polling, \~60s)*

    **Configuration**: Choose your Linear credential, select the **Team** to monitor (required), and choose a **Trigger Mode**:

    * **New Issue**: Fires when a new issue is created
    * **Updated Issue**: Fires when an existing issue is modified (newly created issues are automatically excluded)

    Optionally add **Filters** to narrow which issues fire the trigger:

    * **Status**: Filter by issue status (e.g., In Progress, Done, Backlog)
    * **Project**: Filter by Linear project
    * **Priority**: Filter by priority level (e.g., Urgent, High, Medium, Low)
    * **Labels**: Filter by issue labels (matches issues with at least one of the selected labels)
    * **Assignee**: Filter by the team member assigned to the issue

    **Available variables**: Description, Identifier (e.g., "ENG-123"), Title, URL, Assignee, Status, Project, Labels

    <Info>The trigger fetches up to 5 issues per poll. In Updated Issue mode, each modification to an issue triggers the agent again. A Team must be selected — it is a required parameter.</Info>
  </Accordion>

  <Accordion title="Jira" icon="jira">
    **Fires when**: A new issue is created in the selected Jira project. *(Polling, \~60s)*

    **Configuration**: Choose your Jira credential, select the **Resource** (your Jira instance), the **Project** to monitor, and optionally apply filters to narrow which issues fire the trigger.

    **Filter options**:

    * **Standard Filters**: Filter by Status, Priority, Labels, Issue Type, Assignee, and Custom Fields
    * **JQL**: Write a custom Jira Query Language expression for advanced filtering
    * **Saved Filter**: Use an existing filter saved in your Jira instance

    **Available variables**: Issue ID, Key, Summary, and other issue fields based on the **Information to Read** selection

    <Info>The trigger fetches up to 5 new issues per poll. Uses a compound cursor based on the issue's `created` timestamp and issue key to avoid duplicates. You must connect your Jira account on the [Connectors page](https://www.gumloop.com/personal/connectors) before setting up the trigger.</Info>
  </Accordion>

  <Accordion title="Monday.com" icon="table-columns">
    **Fires when**: Something changes on a Monday.com board — an item, subitem, update, or column event, depending on the **Trigger Mode** you pick. *(Real-time via webhook)*

    **Configuration**: Choose your Monday.com credential, select the **Workspace** and **Board** to watch, then pick a **Trigger Mode**. Some modes ask for one more field:

    | Trigger Mode                | Extra field                                                                                                       |
    | --------------------------- | ----------------------------------------------------------------------------------------------------------------- |
    | **Specific Column Changed** | **Column** — the column to watch                                                                                  |
    | **Status Changed To**       | **Status Column**, plus an optional **Status Value**. Leave the value empty to fire on any change to that column. |
    | **Item Moved To Group**     | **Group** — the destination group                                                                                 |

    All 18 modes: Item Created, Item Name Changed, Any Column Changed, Specific Column Changed, Status Changed To, Item Moved To Group, Item Moved To Any Group, Item Deleted, Item Restored, Update Posted, Update Edited, Update Deleted, Column Created, Subitem Created, Subitem Name Changed, Any Subitem Column Changed, Subitem Deleted, and Update Posted On Subitem.

    **Available variables**: One variable per column on the selected board, named after the column title, plus the board's item name column (`Item`, `Task`, or whatever your board calls its items). Values come from the item behind the event.

    <Info>Modes where the item no longer exists — **Item Deleted**, **Subitem Deleted**, and **Column Created** — still fire, but the column variables come through empty. Use **Pass Raw Data** if you need the raw event payload for those.</Info>

    <Note>Subitem events are watched from the parent board you select, and their column values are read from the subitems board, so the variables you get are the subitem's own columns matched by title.</Note>

    <Warning>Monday.com triggers create a webhook on the board, so your connected account needs permission to manage webhooks on it. If you connected Monday.com before this trigger shipped, reconnect it on the [Connectors page](https://www.gumloop.com/personal/connectors) to grant the webhook permission, otherwise the trigger fails to save.</Warning>
  </Accordion>

  <Accordion title="Parallel Web Monitor" icon="globe">
    **Fires when**: Relevant changes are detected on the web matching your natural-language query. *(Real-time via webhook)*

    **Configuration**: No credentials required — works out of the box. Write a **Query** describing what you want to monitor and set the **Frequency** for how often to check (hourly, daily, or weekly).

    Unlike other triggers that watch a specific service, the Parallel Web Monitor uses AI to scan the entire web for material changes matching your query. This makes it ideal for open-ended monitoring like tracking competitors, news, or market changes.

    **Query examples**:

    * "OpenAI product announcements and API pricing changes"
    * "Acme Corp product launches, pricing changes, and new partnerships"
    * "YC Startups that have raised Series A funding"
    * "SEC rulings on cryptocurrency ETF applications"
    * "New job postings for VP of Engineering at FAANG companies"

    **Available variables**: Event Output, Event Date, Source URLs

    <Tip>Be specific in your query. "AI news" is too broad — "Anthropic and OpenAI model release announcements" will produce much more relevant results.</Tip>
  </Accordion>
</AccordionGroup>

***

## Good to Know

### File and Attachment Handling

When a trigger passes files or attachments to your agent, the agent **automatically receives and can process them**. This includes:

* **Email attachments** from Gmail triggers (PDFs, spreadsheets, images, etc.)
* **Slack file attachments** from Slack message triggers
* **Google Drive files** from Drive triggers (the actual file content, not just the metadata)
* **Teams attachments** from Microsoft Teams message triggers

Your agent can read text-based files (PDFs, CSVs, DOCX, etc.), parse images using vision capabilities, and use file contents as context for its response. No additional configuration is needed.

### Credentials

Triggers use the credentials of the **person who created the trigger**, not whoever is chatting with the agent. Make sure you've connected the required app on your [Connectors page](https://www.gumloop.com/personal/connectors) before setting up a trigger.

For team agents, if team credentials are configured, those can be used instead of personal credentials.

### Auto-Disable on Failure

All triggers (scheduled and event-based) automatically deactivate after **3 consecutive failed runs**. Common causes include expired credentials, deleted resources (channels, folders, databases), or permission changes.

When this happens, the trigger owner receives a notification through **email, Slack DM, and in-app**. The notification links straight to the most recent failed run so you can see what went wrong. You can dismiss it once you've handled it.

To fix: resolve the underlying issue, then re-enable the trigger from the Triggers section. Re-enabling **resets the failure count**, so a trigger won't be immediately disabled again by earlier failures — only failures that happen after you re-enable it count toward the next auto-disable.

### Overlapping Runs

If a previous trigger execution is still running when the next one fires, the new execution is **skipped**. This prevents duplicate work and ensures your agent finishes one task before starting the next.

### Credits

Each trigger execution costs credits based on model and tool usage, the same as a normal chat message. One-time scheduled triggers are automatically deleted after execution (whether they succeed or fail).

***

## FAQ

<AccordionGroup>
  <Accordion title="Can I change a recurring trigger to one-time (or vice versa)?" icon="arrows-rotate">
    No. Delete the existing trigger and create a new one with the desired type.
  </Accordion>

  <Accordion title="What model does the agent use for triggered runs?" icon="microchip">
    The same model configured for the agent. There's no separate model setting for triggered vs. interactive runs.
  </Accordion>

  <Accordion title="Are triggered runs visible in history?" icon="clock-rotate-left">
    Yes. They appear in the agent's run history as triggered executions.
  </Accordion>

  <Accordion title="Can my agent handle file attachments from triggers?" icon="file">
    Yes. When a trigger includes files (email attachments, Slack files, Google Drive uploads), the agent automatically receives them and can read text-based files, parse images, and use the content in its response.
  </Accordion>

  <Accordion title="Why is there a delay with some triggers?" icon="clock">
    Some integrations use **polling** instead of real-time webhooks. Polling-based triggers (Google Sheets, Notion, Airtable, Google Calendar, HubSpot, incident.io, Jira) check for changes approximately every 60 seconds. Real-time triggers (Gmail, Slack, Slack Reaction, Teams, Google Drive, Zendesk, Google Forms, Typeform, Monday.com, Parallel Web Monitor) fire within seconds.
  </Accordion>

  <Accordion title="Can one agent have multiple triggers?" icon="layer-group">
    Yes. You can add as many scheduled triggers and event-based triggers as you need to a single agent. Each trigger operates independently.
  </Accordion>

  <Accordion title="Can my agent create its own event-based triggers?" icon="robot">
    Yes! Agents can create and manage their own **scheduled triggers** and **custom AI triggers** during conversations. Just describe what you want to monitor and the agent builds it. See [Create Triggers With AI](/core-concepts/ai_trigger_creation) for details. Pre-built integration triggers must still be set up from the agent's configuration page.
  </Accordion>
</AccordionGroup>

***

## Related Documentation

<CardGroup cols={2}>
  <Card title="Create Triggers With AI" icon="sparkles" href="/core-concepts/ai_trigger_creation">
    Build custom multi-service triggers in natural language
  </Card>

  <Card title="Agents" icon="robot" href="/core-concepts/agents">
    Learn about building and configuring agents
  </Card>

  <Card title="Workflow Triggers" icon="bolt" href="/core-concepts/workflow_triggers">
    Trigger workflows (not agents) based on events
  </Card>

  <Card title="Agent Skills" icon="book-open" href="/core-concepts/skills">
    Teach your agent reusable processes
  </Card>
</CardGroup>
