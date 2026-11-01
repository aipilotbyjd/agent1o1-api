> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Teams Message Sender

The **Teams Message Sender** node sends messages to Microsoft Teams channels, with support for thread replies and batch messaging.

<Warning>
  **Enterprise Accounts Only**: Teams nodes only work with Microsoft 365 work or school accounts. Personal Microsoft accounts cannot send messages to Teams channels because the Microsoft Graph API permissions required (ChannelMessage.Send) are only available for enterprise tenants. See [Why Enterprise Only?](#why-enterprise-only) for more details.
</Warning>

## Authentication

<Steps>
  <Step title="Connect Your Account">
    Go to the [Connectors page](https://www.gumloop.com/personal/connectors) and connect your Microsoft Teams work/school account. You'll be prompted to sign in with your organization's Microsoft 365 credentials.
  </Step>

  <Step title="Grant Permissions">
    Approve the requested permissions when prompted. The node requires access to send channel messages and view team information.
  </Step>

  <Step title="Select Team and Channel">
    Choose the Team and Channel from the dropdown menus. Only teams you're a member of and channels you have access to will appear.
  </Step>
</Steps>

## Node Configuration

### Required Inputs

| Parameter   | Description                                                           |
| ----------- | --------------------------------------------------------------------- |
| **Team**    | The Teams team to send to. Select from teams you're a member of.      |
| **Channel** | The channel within the selected team. Appears after selecting a team. |
| **Message** | The text content of your message. Supports plain text.                |

### Thread Replies

To reply within an existing thread instead of posting a new message:

<Steps>
  <Step title="Enable Thread Reply">
    Toggle **Reply In Thread?** in the node settings under "Show more options".
  </Step>

  <Step title="Provide Thread ID">
    Connect the **Thread ID** input to a Thread ID output from a Teams Message Reader node, or provide a known thread ID.
  </Step>
</Steps>

<Tip>
  Thread IDs from Teams Message Reader can be used directly with Teams Message Sender to create conversational workflows that respond to specific messages.
</Tip>

## Output

| Output               | Description                                                                                                              |
| -------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| **Posted Thread ID** | The unique identifier of the sent message. Use this to reply to the message in subsequent workflow steps or future runs. |

## Batch Mode

The Teams Message Sender supports Loop Mode for sending multiple messages efficiently.

### Sending Multiple Messages

Connect a list to the Message input to send messages in batch:

```text theme={"dark"}
Create List -> Teams Message Sender (Loop Mode)
```

Each item in the list will be sent as a separate message to the configured channel.

### Sending to Multiple Threads

Combine with Loop Mode on both Message and Thread ID inputs to reply to multiple threads:

```text theme={"dark"}
Teams Message Reader -> Teams Message Sender (Loop Mode)
```

This pattern is useful for:

* Automated responses to multiple conversations
* Bulk notifications to existing threads
* Processing and responding to support requests

## Common Use Cases

### Automated Notifications

Send alerts and updates to your team channel:

```text theme={"dark"}
[Trigger Source] -> Ask AI (Format Message) -> Teams Message Sender
```

* Monitor external systems and post updates
* Send daily/weekly summaries
* Alert on important events

### Conversation Response Bot

Respond to messages in Teams channels:

```text theme={"dark"}
Teams Message Reader -> Ask AI -> Teams Message Sender
```

* **Teams Message Reader**: Get incoming messages with Thread IDs
* **Ask AI**: Generate appropriate responses
* **Teams Message Sender**: Reply to the original thread using Thread ID

<Warning>
  When building response bots, use "Ignore Bot Messages" in the Teams Message Reader to prevent infinite loops where your automation responds to its own messages.
</Warning>

### Cross-Platform Notifications

Bridge communications between platforms:

```text theme={"dark"}
Slack Message Reader -> Teams Message Sender
```

* Sync important messages between Slack and Teams
* Notify Teams channels of Slack activity
* Create unified communication workflows

### Scheduled Reports

Post regular updates to channels:

```text theme={"dark"}
Google Sheets Reader -> Ask AI (Summarize) -> Teams Message Sender
```

* Daily standup summaries
* Weekly metrics reports
* Automated status updates

## Important Considerations

1. **Channel Membership**: You must be a member of the channel to send messages. Private channels require an invite.
2. **Message Format**: Messages are sent as plain text. For rich formatting, consider using the Microsoft Teams MCP node.
3. **Rate Limits**: Microsoft Graph API has rate limits. When sending many messages, the node handles rate limiting automatically with retries.
4. **Thread Replies**: When replying to a thread, the Thread ID must be valid and from the same channel.
5. **Message Length**: Teams supports messages up to 28KB in size. Longer messages may be truncated.

## Why Enterprise Only?

Microsoft Teams is designed as an enterprise collaboration platform integrated with Microsoft 365. The Microsoft Graph API endpoints for sending channel messages require specific permissions that are only available for work and school accounts:

**Required Permission**: `ChannelMessage.Send`

This permission requires:

* A Microsoft 365 business or education subscription
* An Azure AD tenant (automatically created with Microsoft 365)
* User authentication with delegated permissions

Personal Microsoft accounts (outlook.com, hotmail.com, live.com) do not have:

* Access to Teams workspaces (Teams is not available for personal accounts)
* An Azure AD tenant to support delegated permissions
* The underlying infrastructure that supports channel message APIs

If you're seeing authentication errors, verify that you're signing in with your organization's work or school account (typically your corporate email), not a personal Microsoft account.

## Advanced Teams Features

<Info>
  Need more advanced Teams capabilities like sending rich cards, managing channels, or working with adaptive cards? Use the [Microsoft Teams MCP node](/nodes/mcp/microsoft_teams) to create custom Teams integrations with natural language prompts.
</Info>

<Tip>
  Want your team to chat with an agent directly inside a Teams channel? See [Using Agents in Microsoft Teams](/core-concepts/agents_teams).
</Tip>
