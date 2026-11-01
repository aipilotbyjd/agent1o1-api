> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Analytics

> Run reports, manage GA4 properties, and send server-side events with AI-powered analytics automation.

Google Analytics 4 is Google's web and app analytics platform. The Google Analytics MCP server lets you run reports, manage accounts and properties, configure custom dimensions, metrics, and conversions, and send server-side events using natural language.

## What Can It Do?

* **Run reports** for users, sessions, pageviews, and other metrics, including real-time data
* **Check report compatibility** to see which dimensions and metrics can be combined
* **Manage accounts and properties** including create, update, delete, and data retention settings
* **Configure data streams** for web, iOS, and Android
* **Manage custom dimensions, metrics, and conversion (key) events**
* **Link Google Ads and Firebase** projects to your GA4 properties
* **Export and download audiences** for downstream activation
* **Send server-side events** via the Measurement Protocol with auto-fetched API secrets

## Where to Use It

### In Agents (Recommended)

Add Google Analytics as a tool to any agent. The agent can then explore your analytics data and configuration conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your Google account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Google Analytics tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "Run a 30-day report of users by country")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Reporting

| Tool                    | Description                                                                    |
| ----------------------- | ------------------------------------------------------------------------------ |
| **Run Report**          | Run 1-5 GA4 reports and get back users, sessions, pageviews, and other metrics |
| **Run Realtime Report** | Get real-time GA4 data for events in the last 30 minutes                       |
| **Check Compatibility** | Check which dimensions and metrics can be combined in a report                 |
| **Get Metadata**        | List all dimensions and metrics available for reports on a property            |
| **Run Access Report**   | See who accessed a GA4 account or property's data and when                     |

### Audiences

| Tool                       | Description                                             |
| -------------------------- | ------------------------------------------------------- |
| **Create Audience Export** | Start an export of users in a GA4 audience              |
| **List Audience Exports**  | List audience exports for a GA4 property                |
| **Get Audience Export**    | Check the status of a GA4 audience export               |
| **Query Audience Export**  | Download the users from a completed GA4 audience export |

### Accounts

| Tool                                     | Description                                                             |
| ---------------------------------------- | ----------------------------------------------------------------------- |
| **List Account Summaries**               | List your GA4 accounts along with the websites and apps tracked in each |
| **List Accounts**                        | List your GA4 accounts                                                  |
| **Get Account**                          | Get details of a GA4 account                                            |
| **Update Account**                       | Rename a GA4 account or change its region                               |
| **Delete Account**                       | Move a GA4 account to the trash                                         |
| **Get Account Data Sharing Settings**    | See how a GA4 account shares data with Google products and support      |
| **Search Account Change History Events** | See recent changes made in a GA4 account (who edited what)              |

### Properties

| Tool                                 | Description                                                      |
| ------------------------------------ | ---------------------------------------------------------------- |
| **List Properties**                  | List the websites and apps (properties) tracked in a GA4 account |
| **Get Property**                     | Get details of a GA4 property                                    |
| **Create Property**                  | Create a new GA4 property to track a website or app              |
| **Update Property**                  | Update a GA4 property's name, timezone, currency, or industry    |
| **Delete Property**                  | Move a GA4 property to the trash                                 |
| **Acknowledge User Data Collection** | Confirm Google's user-data-collection terms for a GA4 property   |
| **Get Data Retention Settings**      | See how long a GA4 property keeps user and event data            |
| **Update Data Retention Settings**   | Change how long a GA4 property keeps user and event data         |

### Data Streams

| Tool                   | Description                                                          |
| ---------------------- | -------------------------------------------------------------------- |
| **List Data Streams**  | List the web, iOS, and Android data streams for a GA4 property       |
| **Get Data Stream**    | Get details of a GA4 data stream                                     |
| **Create Data Stream** | Add a new website, iOS app, or Android app data stream to a property |
| **Update Data Stream** | Update a GA4 data stream's name or settings                          |
| **Delete Data Stream** | Delete a GA4 data stream                                             |

### Custom Dimensions & Metrics

| Tool                        | Description                                                                      |
| --------------------------- | -------------------------------------------------------------------------------- |
| **List Custom Dimensions**  | List the custom dimensions defined on a GA4 property                             |
| **Get Custom Dimension**    | Get details of a GA4 custom dimension                                            |
| **Create Custom Dimension** | Create a custom dimension that captures an event parameter or user property      |
| **Update Custom Dimension** | Update or archive a GA4 custom dimension                                         |
| **List Custom Metrics**     | List the custom metrics defined on a GA4 property                                |
| **Get Custom Metric**       | Get details of a GA4 custom metric                                               |
| **Create Custom Metric**    | Create a custom metric from a numeric event parameter (currency, duration, etc.) |
| **Update Custom Metric**    | Update or archive a GA4 custom metric                                            |

### Conversions (Key Events)

| Tool                 | Description                                                 |
| -------------------- | ----------------------------------------------------------- |
| **List Key Events**  | List the conversion events set up on a GA4 property         |
| **Get Key Event**    | Get details of a GA4 conversion event                       |
| **Create Key Event** | Mark an event as a conversion in GA4                        |
| **Update Key Event** | Update how a GA4 conversion is counted or its default value |
| **Delete Key Event** | Remove a conversion from GA4                                |

### Product Links

| Tool                       | Description                                                 |
| -------------------------- | ----------------------------------------------------------- |
| **List Google Ads Links**  | List the Google Ads accounts linked to a GA4 property       |
| **Create Google Ads Link** | Link a Google Ads account to a GA4 property                 |
| **Update Google Ads Link** | Change the ads-personalization setting on a Google Ads link |
| **Delete Google Ads Link** | Unlink a Google Ads account from a GA4 property             |
| **List Firebase Links**    | List the Firebase projects linked to a GA4 property         |
| **Create Firebase Link**   | Link a Firebase project to a GA4 property                   |
| **Delete Firebase Link**   | Unlink a Firebase project from a GA4 property               |

### Measurement Protocol

| Tool                           | Description                                                                                                                                                                          |
| ------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Measurement Protocol Event** | Send, validate, or send-and-validate a server-side event to GA4 via Measurement Protocol. Auto-fetches the API secret (creates one if none exist) and stream identifiers using OAuth |

## Example Prompts

Use these with your agent or in the Agent Node:

**Run a report:**

```text theme={"dark"}
Show me total users and sessions for example.com over the last 30 days, broken down by country
```

**Real-time monitoring:**

```text theme={"dark"}
How many active users are on my site right now and what pages are they viewing?
```

**Check compatibility:**

```text theme={"dark"}
Can I combine the "sessionDefaultChannelGroup" dimension with the "totalRevenue" metric in a report?
```

**Inspect a property:**

```text theme={"dark"}
List all properties in my Marketing GA4 account and show me each property's data retention settings
```

**Manage custom dimensions:**

```text theme={"dark"}
Create a custom dimension on the example.com property that captures the "plan_tier" user property
```

**Manage conversions:**

```text theme={"dark"}
List all conversion events on the example.com property and mark "signup_completed" as a conversion
```

**Audience export:**

```text theme={"dark"}
Start an audience export for the "Engaged Users" audience on example.com, then download the users once it's ready
```

**Send a server-side event:**

```text theme={"dark"}
Send a "purchase" event with value=49.99 and currency=USD to GA4 for client_id 12345 via Measurement Protocol
```

## Troubleshooting

| Issue                                    | Solution                                                                                                                                                            |
| ---------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Authentication failed                    | Verify your Google account has access to the GA4 property you're querying                                                                                           |
| Property not found                       | Use the property's numeric ID (e.g., `properties/123456789`) when prompting                                                                                         |
| Real-time report empty                   | Real-time data only covers the last 30 minutes. Try [Run Report](#reporting) for historical data                                                                    |
| Measurement Protocol event not appearing | Use the validate mode first to confirm the event payload is well-formed before sending                                                                              |
| Permission denied on update or delete    | These tools require analytics edit access; confirm your Google account has the right role on the property                                                           |
| Tool not available                       | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                                 |
| Unexpected results                       | The agent may chain multiple tools (e.g., listing accounts first, then properties, then running a report). Review the agent's reasoning to understand its approach. |

<Tip>
  Agents are smart enough to chain multiple API calls together. For example, asking "What were my top 10 pages last week on example.com?" will look up the property ID first, then run the report. If results seem off, check the agent's step-by-step reasoning.
</Tip>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Google Analytics MCP server](https://www.gumloop.com/mcp/ganalytics) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
