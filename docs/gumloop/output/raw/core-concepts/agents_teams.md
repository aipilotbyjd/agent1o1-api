> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Using Agents in Microsoft Teams

> Connect your Gumloop agents to Microsoft Teams channels so your team can chat with AI-powered assistants where they already work.

Connect your Gumloop agents to Microsoft Teams so anyone in a channel can interact with them using a simple @mention.

<div style={{position: "relative", paddingBottom: "56.25%", height: 0}}>
  <iframe src="https://player.vimeo.com/video/1199630125?h=2cb7812e91&badge=0&autopause=0&player_id=0&app_id=58479" frameBorder="0" allow="autoplay; fullscreen; picture-in-picture; clipboard-write; encrypted-media; web-share" referrerPolicy="strict-origin-when-cross-origin" style={{position: "absolute", top: 0, left: 0, width: "100%", height: "100%"}} title="Teams v1" />
</div>

<script src="https://player.vimeo.com/api/player.js" />

## Why Use Agents in Microsoft Teams?

Bringing agents to Teams transforms how your team learns and adopts AI automation:

<CardGroup cols={2}>
  <Card title="Shared Learning" icon="users">
    **Visibility by default**: Every interaction in a channel becomes a learning opportunity for the team. No more siloed knowledge, everyone sees how to use agents effectively.
  </Card>

  <Card title="Natural Integration" icon="comments">
    **Where work happens**: Teams already use Microsoft Teams for communication. Agents integrate seamlessly into existing workflows without requiring new tools.
  </Card>

  <Card title="Collaborative Usage" icon="people-group">
    **Team-wide access**: Instead of one person running automations, entire teams can leverage the same agent with consistent results.
  </Card>

  <Card title="Instant Adoption" icon="bolt">
    **Zero learning curve**: If your team knows how to @mention someone in Teams, they know how to use an agent. No training required.
  </Card>
</CardGroup>

<Info>**The Learning Effect**: When someone asks an agent a question in a channel, everyone sees the interaction. This passive learning accelerates team-wide adoption faster than any training session could.</Info>

## Prerequisites

Before connecting an agent to Microsoft Teams, make sure you have:

* A **Gumloop account** with an agent you want to deploy
* A **Microsoft 365 work or school account** (personal Microsoft accounts are not supported)
* Permission to install apps in your Microsoft Teams workspace

<Warning>Microsoft Teams integration requires a Microsoft 365 work or school account. Personal Microsoft accounts (e.g. outlook.com, hotmail.com) cannot be used because the Microsoft Graph API permissions are only available for enterprise tenants.</Warning>

## Adding an Agent to Microsoft Teams

<Steps>
  <Step title="Connect Your Microsoft Account (First-Time Only)">
    Navigate to your agent in Gumloop, then click **Microsoft Teams** under **External Channels** in the sidebar.

    <Frame>
      <img src="https://mintcdn.com/agenthub/HiF9PB-u-bQUmTCd/images/teams_external_channels_sidebar.png?fit=max&auto=format&n=HiF9PB-u-bQUmTCd&q=85&s=ab80730babf8b20c7d4902131f3a1ec9" alt="External Channels sidebar showing Email, Slack, Microsoft Teams, and Hosted Page options" style={{ maxWidth: '300px' }} width="494" height="362" data-path="images/teams_external_channels_sidebar.png" />
    </Frame>

    If you haven't connected a Microsoft Teams credential yet, you'll see a setup screen prompting you to connect. Click **Connect to Microsoft Teams** and follow the OAuth flow to authorize Gumloop with your Microsoft 365 account.

    This links your Microsoft identity to your Gumloop account so the agent can attribute messages to the correct user when someone @mentions it.

    <Info>You only need to connect your Microsoft account once. After that, you can add any of your agents to Teams channels.</Info>
  </Step>

  <Step title="Install the Gumloop App">
    Once connected, you'll see the setup instructions. Click the **Install** button to add the Gumloop app to your Microsoft Teams workspace.

    <Frame>
      <img src="https://mintcdn.com/agenthub/HiF9PB-u-bQUmTCd/images/teams_channel_setup.png?fit=max&auto=format&n=HiF9PB-u-bQUmTCd&q=85&s=018c27d493ad25f7828f0e8f74545d86" alt="Microsoft Teams channel setup page showing three steps: Install the Gumloop app, Use the Add Agent Command, and Start Chatting" width="1598" height="1042" data-path="images/teams_channel_setup.png" />
    </Frame>

    After clicking Install, Teams opens a dialog confirming the app was added. Choose the channel where you want the agent to respond and click **Go**.

    <Frame>
      <img src="https://mintcdn.com/agenthub/HiF9PB-u-bQUmTCd/images/teams_app_install_success.png?fit=max&auto=format&n=HiF9PB-u-bQUmTCd&q=85&s=6746de813e7c81fade325fda79fad34b" alt="Gumloop app successfully added to Microsoft Teams with channel selection dialog" style={{ maxWidth: '500px' }} width="1224" height="1548" data-path="images/teams_app_install_success.png" />
    </Frame>

    <Warning>Agents can only be added to **public (standard) channels**. Private channels are not supported.</Warning>
  </Step>

  <Step title="Add Your Agent Using the Compose Command">
    From the Teams compose menu in the channel where you installed the app, choose **Gumloop**, then select **Add Agent**. Paste the agent ID shown on the setup page and submit.

    <Tip>Click the copy button next to the agent ID on the setup page to copy it to your clipboard.</Tip>
  </Step>
</Steps>

## Using Your Agent in Teams

To interact with your agent, **@mention Gumloop** in any message:

```text theme={"dark"}
@Gumloop [your question or task]
```

You can `@Gumloop` in a **top-level message** to start a new thread, or inside an **existing thread** to continue the conversation.

<Warning>You must @mention **@Gumloop** in every message, including thread replies. Unlike Slack, there is no "respond to all messages in thread" mode. The agent only responds when explicitly mentioned.</Warning>

<Info>**One agent per channel.** Only one Gumloop agent can be active in a channel at a time. To switch agents, remove the current one first, then add the new agent using the compose command.</Info>

## Teams Commands

### Compose Extension Commands

Teams uses compose extension commands (accessed from the compose menu) instead of Slack-style slash commands.

| Command       | What It Does                                                |
| ------------- | ----------------------------------------------------------- |
| **Add Agent** | Add an agent to the current channel by pasting its agent ID |
| **Remove**    | Remove the active agent from the current channel            |
| **Active**    | Show which agent is currently active in this channel        |
| **Help**      | Show available commands and usage instructions              |

### Thread Commands

| Command | What It Does                                                                               |
| ------- | ------------------------------------------------------------------------------------------ |
| `!stop` | Stop the agent's current response and prevent further replies in this thread               |
| `!link` | Get a link to view the full conversation in Gumloop, including tool calls and credit usage |

## Credentials & Authentication

### How Authentication Works

When you interact with an agent in Teams, the agent uses **your personal default credentials**, not the agent creator's credentials. This ensures proper access control and data privacy.

<AccordionGroup>
  <Accordion title="For Existing Gumloop Users" icon="user-check">
    If your Microsoft 365 email matches your Gumloop account:

    1. The agent automatically uses your personal default credentials
    2. You have immediate access to all tools and workflows you're authorized to use
    3. No additional setup required

    **Example**: If the agent uses Gmail and Google Calendar, it will access your personal Gmail and Calendar using your authenticated credentials.
  </Accordion>

  <Accordion title="For Non-Gumloop Users" icon="user-plus">
    If you're not yet a Gumloop user, you'll see a signup prompt the first time you try to use an agent.

    **What happens**:

    1. Agent responds with a message asking you to sign up
    2. Click the link to create your Gumloop account using your Microsoft 365 email
    3. After signing up, authenticate with the services the agent needs
    4. Return to Teams and @Gumloop the agent again

    <Note>You must use the same email address for Microsoft 365 and Gumloop for the integration to work properly.</Note>
  </Accordion>

  <Accordion title="Missing Credentials" icon="key">
    If the agent needs a credential you haven't set up yet:

    1. The agent will notify you about the missing authentication
    2. Visit your [Connectors page](https://www.gumloop.com/personal/connectors)
    3. Authenticate with the required service
    4. Return to Teams and retry your request
  </Accordion>

  <Accordion title="Personal vs Team Agents" icon="building">
    <Tip>**Recommendation**: Always create agents in your personal space unless you need shared team apps or team collaboration features.</Tip>

    **Personal Agents** (created in personal space):

    * Anyone in the Teams channel can use the agent
    * Each user's request runs on their own personal credentials
    * Non-Gumloop users will be prompted to sign up
    * Best for most use cases

    **Team Agents** (created in a team):

    * **Access control**: Only members of that specific Gumloop team can use the agent
    * **App behavior**: If an MCP integration or app is set to use "team default," the team apps are used instead of personal apps
    * Non-team members will receive an access denied message

    Learn more about the differences between personal and team spaces in the [Organizations and Teams documentation](/core-concepts/teams#personal-vs-team).

    | Feature         | Personal Agent                     | Team Agent                                       |
    | --------------- | ---------------------------------- | ------------------------------------------------ |
    | Who can use it? | Anyone in Teams channel            | Only team members                                |
    | Apps used       | Always personal default            | Personal default OR team default (if configured) |
    | Best for        | General use, maximum accessibility | Team collaboration with shared apps              |
  </Accordion>
</AccordionGroup>

<Info>For a full walkthrough on configuring team apps with agents, see [Using Team Apps with Agents](/core-concepts/agents_slack#using-team-apps-with-agents).</Info>

### Data Privacy & Security

<CardGroup cols={2}>
  <Card title="Your Data Stays Private" icon="lock">
    When you use an agent with personal credentials, only your authenticated credentials are used. Other team members cannot access your personal data through the agent.
  </Card>

  <Card title="Controlled Access" icon="shield">
    Admin security controls and user roles in Gumloop apply to agents just like they do to workflows. You can only access what you're authorized to access.
  </Card>
</CardGroup>

## Current Limitations

The Microsoft Teams integration has a few differences compared to the [Slack integration](/core-concepts/agents_slack). These are mostly due to technical limitations in the Teams platform.

<CardGroup cols={2}>
  <Card title="No Private Channels" icon="lock">
    Agents can only be added to **public (standard) channels**. Private channels in Teams are not supported.
  </Card>

  <Card title="No File Uploads" icon="file-circle-xmark">
    Agents cannot receive or process file attachments sent in Teams messages. Text-only interactions are supported.
  </Card>

  <Card title="No Custom App Branding" icon="paintbrush">
    Unlike Slack, there is no custom app option. All agents respond as **@Gumloop**, so you cannot customize the bot name or avatar.
  </Card>

  <Card title="@Mention Always Required" icon="at">
    You must @mention **Gumloop** in every message, including thread replies. There is no "respond to all messages in thread" mode like in Slack.
  </Card>
</CardGroup>

## Best Practices & Troubleshooting

<Tabs>
  <Tab title="Best Practices">
    <AccordionGroup>
      <Accordion title="Choose the Right Channels" icon="hashtag">
        Deploy agents strategically to appropriate channels:

        **Supported channel types**:

        * Public (standard) channels
        * **Not supported**: Private channels, direct messages, or group chats

        **Example deployments**:

        * **Support**: Customer service agents with ticket triage capabilities
        * **Sales**: Lead research and enrichment agents
        * **Marketing**: Campaign planning and content strategy agents
        * **Data**: Data analysis and reporting agents
      </Accordion>

      <Accordion title="Set Channel Expectations" icon="megaphone">
        When adding an agent to a channel, post a message explaining what the agent can do, how to use it (`@Gumloop [your question]`), and a quick example. This reduces friction and encourages proper usage.
      </Accordion>

      <Accordion title="Use Descriptive Agent Names" icon="tag">
        Give agents clear names so your team knows what each one does:

        * "Support Ticket Assistant", "Sales Lead Researcher"
        * Not: "Agent 1", "My Bot"
      </Accordion>

      <Accordion title="Always @Mention the Agent" icon="at">
        Unlike Slack, Teams requires an @mention for every interaction, including thread replies. Remind your team that `@Gumloop` is needed each time they want the agent to respond.
      </Accordion>

      <Accordion title="Ensure Credentials Are Set Up" icon="key">
        Before deploying to a team channel: test with your own credentials, document which services team members need to authenticate with, and share the [Connectors page](https://www.gumloop.com/personal/connectors) link.
      </Accordion>

      <Accordion title="Combine with Event Triggers" icon="bolt">
        For maximum automation, combine agents in Teams with [event-based triggers](/core-concepts/agent_triggers). The agent handles ad-hoc questions in the channel while triggers automatically process events like new emails, tickets, or database changes.
      </Accordion>
    </AccordionGroup>
  </Tab>

  <Tab title="Troubleshooting">
    <AccordionGroup>
      <Accordion title="Agent Doesn't Respond" icon="circle-exclamation">
        1. Did you @Gumloop the agent in your message? (Required for every message, including thread replies)
        2. Is the Gumloop app installed in the channel?
        3. Has an agent been added using the Add Agent compose command?
        4. Is this a **public (standard) channel**? (Private channels are not supported)
        5. Have you authenticated with the required services?
      </Accordion>

      <Accordion title="Signup Prompt for Existing Users" icon="user-plus">
        Your Microsoft 365 email must match your Gumloop account email. If they're different, update one to match the other, then try again.
      </Accordion>

      <Accordion title="Authentication Errors" icon="key">
        Visit your [Connectors page](https://www.gumloop.com/personal/connectors), authenticate with the required service, and retry. For team agents, contact your team admin.
      </Accordion>

      <Accordion title="Can't Install the Gumloop App" icon="lock">
        Some organizations require admin approval for new Teams apps. Contact your Microsoft 365 admin to approve the Gumloop app, or ask them to add it to the organization's app catalog.
      </Accordion>

      <Accordion title="Agent Responses Too Slow" icon="clock">
        Optimize workflows to use fewer AI nodes, limit tools to only what's necessary, use faster models, or break complex tasks into smaller interactions.
      </Accordion>

      <Accordion title="Can't Find Agent ID" icon="magnifying-glass">
        Open your agent in Gumloop, navigate to **Microsoft Teams** under **External Channels**, and copy the agent ID from the setup page. Make sure you've connected your Microsoft account first.
      </Accordion>
    </AccordionGroup>
  </Tab>
</Tabs>

## Example Use Cases

<Tabs>
  <Tab title="Support Channel">
    **Agent**: Support Ticket Assistant

    **Channel**: Customer Support (public channel)

    **Common interactions**:

    ```text theme={"dark"}
    Team member: "@Gumloop Is ticket #12345 eligible for a refund?"
    Agent: [Checks ticket, reads policy, evaluates eligibility]

    Team member (in thread): "@Gumloop What about ticket #12346?"
    Agent: [Checks ticket #12346]

    Team member: "@Gumloop Pull the last 5 interactions with customer@email.com"
    Agent: [Searches CRM and email, returns history]
    ```

    **Why it works**: Support team gets instant access to information without leaving Teams. Every interaction teaches the team how to use the agent for similar queries.
  </Tab>

  <Tab title="Sales Channel">
    **Agent**: Lead Research Assistant

    **Channel**: Sales Team (public channel)

    **Common interactions**:

    ```text theme={"dark"}
    Team member: "@Gumloop Research this company: [LinkedIn URL]"
    Agent: [Enriches lead, checks CRM for activity, drafts outreach]

    Team member (in thread): "@Gumloop What's the best time to reach out?"
    Agent: [Analyzes contact data, suggests optimal timing]

    Team member: "@Gumloop What's the status of opportunities over $50k?"
    Agent: [Queries Salesforce, summarizes pipeline]
    ```

    **Why it works**: Sales reps get instant research without switching tools. Newer reps learn by watching experienced reps use the agent.
  </Tab>

  <Tab title="Data Channel">
    **Agent**: Data Analysis Assistant

    **Channel**: Data Team (public channel)

    **Common interactions**:

    ```text theme={"dark"}
    Team member: "@Gumloop Compare Q4 revenue vs Q3 by product line"
    Agent: [Runs query, generates comparison]

    Team member (in thread): "@Gumloop Now break it down by region"
    Agent: [Continues analysis with regional breakdown]

    Team member: "@Gumloop Flag any anomalies in yesterday's user signups"
    Agent: [Analyzes data, identifies outliers, reports findings]
    ```

    **Why it works**: Analysts get quick answers to data questions. Non-technical team members learn what data is available and how to access it.
  </Tab>
</Tabs>

## FAQ

<AccordionGroup>
  <Accordion title="Do I need a Microsoft 365 work or school account?" icon="microsoft">
    Yes. Microsoft Teams integration only works with Microsoft 365 work or school accounts. Personal Microsoft accounts (outlook.com, hotmail.com, live.com) are not supported because the Microsoft Graph API permissions required are only available for enterprise tenants.
  </Accordion>

  <Accordion title="Can I add multiple agents to the same channel?" icon="robot">
    Currently, one agent can be active per channel. To switch to a different agent, remove the current one and add the new agent using the Add Agent command.
  </Accordion>

  <Accordion title="Does my Teams email need to match my Gumloop email?" icon="envelope">
    Yes. The agent matches your Microsoft 365 email to your Gumloop account. If they don't match, you'll be prompted to connect your account.
  </Accordion>

  <Accordion title="Can I use the same agent in both Slack and Teams?" icon="comments">
    Yes. An agent can be deployed to Slack, Microsoft Teams, and other external channels simultaneously. Each channel operates independently.
  </Accordion>

  <Accordion title="Can I send files to the agent in Teams?" icon="file">
    No. File uploads are not currently supported in the Teams integration. The agent can only process text messages. If you need file processing, use the [Slack integration](/core-concepts/agents_slack) or the [Agent Email Inbox](/core-concepts/agents_email), both of which support attachments.
  </Accordion>

  <Accordion title="Can I use a custom bot name or avatar in Teams?" icon="paintbrush">
    No. Unlike Slack (which supports [Custom Slack Apps](/core-concepts/custom_slack_app)), all agents in Teams respond as **@Gumloop**. Custom branding is not available for the Teams integration.
  </Accordion>

  <Accordion title="Does the agent respond to all messages in a thread?" icon="message">
    No. You must @mention **Gumloop** in every message, including thread replies. The "respond to all messages in thread" mode available in Slack is not supported in Teams.
  </Accordion>

  <Accordion title="Is this different from the Microsoft Teams MCP integration?" icon="plug">
    Yes. This feature lets your team chat with a Gumloop agent **inside** Microsoft Teams channels. The [Microsoft Teams MCP server](/nodes/mcp/microsoft_teams) is the opposite: it lets an agent **use** Teams as a tool (send messages, create channels, manage meetings) from within Gumloop.
  </Accordion>

  <Accordion title="Do I need admin permissions to install the Gumloop app?" icon="shield">
    It depends on your organization's Teams policies. Some organizations require admin approval for new apps. If you can't install the app, contact your Microsoft 365 admin.
  </Accordion>

  <Accordion title="Can I refresh my Microsoft Teams connection?" icon="rotate">
    Yes. If your agent stops responding, go to the Microsoft Teams channel page in Gumloop and click **Refresh** to re-authenticate your connection.
  </Accordion>

  <Accordion title="How do I remove an agent from a Teams channel?" icon="trash">
    Open the compose menu in the channel, choose **Gumloop**, then select **Remove**. This clears the active agent from that channel. You can also check which agent is active by using the **Active** command from the same menu.
  </Accordion>
</AccordionGroup>

## Next Steps

<CardGroup cols={3}>
  <Card title="Build Your First Agent" icon="robot" href="/core-concepts/agents">
    Learn how to create and configure agents in Gumloop
  </Card>

  <Card title="Using Agents in Slack" icon="slack" href="/core-concepts/agents_slack">
    Deploy the same agents to Slack channels
  </Card>

  <Card title="Agent Email Inbox" icon="envelope" href="/core-concepts/agents_email">
    Give your agent a dedicated email address
  </Card>
</CardGroup>
