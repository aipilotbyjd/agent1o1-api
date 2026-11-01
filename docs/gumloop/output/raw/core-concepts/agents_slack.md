> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Using Agents in Slack

Connect your Gumloop agents to Slack channels so your entire team can interact with AI-powered assistants in the tools they use every day.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1126339449?h=2ec6a337a0" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="Using Agents in Slack" />
</div>

## Why Use Agents in Slack?

Bringing agents to Slack transforms how your team learns and adopts AI automation:

<CardGroup cols={2}>
  <Card title="Shared Learning" icon="users">
    **Visibility by default**: Every interaction in a channel becomes a learning opportunity for the team. No more siloed knowledge—everyone sees how to use agents effectively.
  </Card>

  <Card title="Natural Integration" icon="comments">
    **Where work happens**: Teams already use Slack for communication. Agents integrate seamlessly into existing workflows without requiring new tools.
  </Card>

  <Card title="Collaborative Usage" icon="people-group">
    **Team-wide access**: Instead of one person running automations, entire teams can leverage the same agent with consistent results.
  </Card>

  <Card title="Instant Adoption" icon="bolt">
    **Zero learning curve**: If your team knows how to @mention someone in Slack, they know how to use an agent. No training required.
  </Card>
</CardGroup>

<Info>**The Learning Effect**: When someone asks an agent a question in a channel, everyone sees the interaction. This passive learning accelerates team-wide adoption faster than any training session could.</Info>

## Adding an Agent to Slack

<Steps>
  <Step title="Authenticate with Slack (First-Time Only)">
    If you haven't connected Gumloop to your Slack workspace yet, visit the [Connectors page](https://www.gumloop.com/personal/connectors?provider=slack), click **Add Credential** for Slack, and follow the OAuth flow to authorize Gumloop.

    <img src="https://mintcdn.com/agenthub/jPoPAi23OST-yycZ/images/slack_credentials_page.png?fit=max&auto=format&n=jPoPAi23OST-yycZ&q=85&s=9bcc00c7d305840868f6f3ba3a0fe05e" alt="Gumloop credentials page showing Slack authentication" style={{ maxHeight: '400px', border: '1px solid black', borderRadius: '8px' }} width="1896" height="1830" data-path="images/slack_credentials_page.png" />
  </Step>

  <Step title="Connect Your Agent">
    Open your agent in Gumloop and click the **"Add to Slack"** button in the top bar.

    <img src="https://mintcdn.com/agenthub/d_0f-YSO7rlIpn_B/images/add_to_slack_button.png?fit=max&auto=format&n=d_0f-YSO7rlIpn_B&q=85&s=b93de730f061fdcdfed0a78a072a6c65" alt="Add to Slack button in the agent top bar" style={{ border: '1px solid black', borderRadius: '8px' }} width="498" height="480" data-path="images/add_to_slack_button.png" />

    This opens a dialog with three connection methods:

    <Tabs>
      <Tab title="New Channel">
        Creates a brand new Slack channel and connects your agent to it automatically.

        Enter a channel name, optionally toggle **Make Channel Private**, and click **Create & Connect**.

        <img src="https://mintcdn.com/agenthub/WuMv2bE3jTJldGq-/images/slack_connect_new_channel.png?fit=max&auto=format&n=WuMv2bE3jTJldGq-&q=85&s=78ec159f4d8b6b1d3bf7d937a029e643" alt="New Channel tab showing channel name input and private toggle" style={{ maxHeight: '420px', border: '1px solid black', borderRadius: '8px' }} width="1284" height="868" data-path="images/slack_connect_new_channel.png" />

        The Gumloop bot is added to the channel and your agent is ready to go.
      </Tab>

      <Tab title="Existing Channel">
        Connects your agent to a channel that already exists in your Slack workspace.

        Pick a channel from the dropdown and click **Connect**.

        <img src="https://mintcdn.com/agenthub/WuMv2bE3jTJldGq-/images/slack_connect_existing_channel.png?fit=max&auto=format&n=WuMv2bE3jTJldGq-&q=85&s=10c00129631423f640c95d4e3eb2b8f8" alt="Existing Channel tab showing channel dropdown selector" style={{ maxHeight: '420px', border: '1px solid black', borderRadius: '8px' }} width="1358" height="832" data-path="images/slack_connect_existing_channel.png" />
      </Tab>

      <Tab title="Slash Command">
        If you prefer to connect manually, this tab gives you a step-by-step guide: install the Gumloop Slack bot, copy the `/gummie add` command for your specific agent, and paste it in any Slack channel.

        <img src="https://mintcdn.com/agenthub/WuMv2bE3jTJldGq-/images/slack_connect_slash_command.png?fit=max&auto=format&n=WuMv2bE3jTJldGq-&q=85&s=6006f924921253976546793ee74580b1" alt="Slash Command tab showing manual setup steps with copy button" style={{ maxHeight: '420px', border: '1px solid black', borderRadius: '8px' }} width="1316" height="862" data-path="images/slack_connect_slash_command.png" />
      </Tab>
    </Tabs>

    <Info>**One agent per channel** with the standard @Gumloop bot. To switch agents, remove the current one first with `/gummie remove`. Need multiple agents in one channel? Use a [Custom Slack App](/core-concepts/custom_slack_app) for each additional agent.</Info>

    <Warning>Custom agents can be added to **public or private channels**, but **not to direct messages (DMs)**. DMs to the Gumloop app always go to your personal [Gumball](/core-concepts/gumball#gumball-in-slack) agent.</Warning>
  </Step>
</Steps>

## Using Your Agent in Slack

To interact with your agent, **@mention it** in any message:

```text theme={"dark"}
@Gumloop [your question or task]
```

You can `@Gumloop` in a **top-level message** to start a new thread, or inside an **existing thread** to continue the conversation. The agent responds wherever you tag it.

<img src="https://mintcdn.com/agenthub/hrwvapjs9dt_JqnG/images/agent_slack_interaction.png?fit=max&auto=format&n=hrwvapjs9dt_JqnG&q=85&s=180feffb0be755b379b7a9863fc58eec" alt="Agent interaction in Slack showing @Gumloop mention and threaded response" style={{ border: '1px solid black', borderRadius: '8px' }} width="1848" height="364" data-path="images/agent_slack_interaction.png" />

<Tip>**Need custom branding?** Connect your own Slack app so the agent appears with a custom name and avatar instead of "@Gumloop". See [Custom Slack App Integration](/core-concepts/custom_slack_app).</Tip>

### Direct Messages Go to Gumball

You can also **DM the Gumloop app** in your workspace — no channel, no `@mention`, and nothing to set up. Those DMs are answered by your own [Gumball](/core-concepts/gumball#gumball-in-slack), the personal agent that comes with every Gumloop account, running on your connectors and skills.

DMs never reach a custom agent. To direct message a custom agent under its own name and avatar, connect a [Custom Slack App](/core-concepts/custom_slack_app#direct-messages-dms) for it.

## Customizing Agent Behavior in Slack

These preferences are in your agent's settings under **Slack Preferences**. Changes take effect immediately for new conversations.

<img src="https://mintcdn.com/agenthub/ZKhze1LGTv-CmFWG/images/agent_slack_compact_progress.png?fit=max&auto=format&n=ZKhze1LGTv-CmFWG&q=85&s=db663408f4b0f560f4bbb1cebe8ca7b5" alt="Slack preferences settings panel" style={{ maxHeight: '350px', border: '1px solid black', borderRadius: '8px' }} width="1944" height="466" data-path="images/agent_slack_compact_progress.png" />

### Thread Response Trigger

Controls whether the agent responds to **every message** in a thread or **only when @mentioned**.

<Tabs>
  <Tab title="On All Messages">
    Agent responds to every thread reply, no @mention needed. Best for support channels and active collaboration where the agent should participate in every message.

    **Example**: You start with `@Gumloop analyze this customer`. Any follow-up like "What about their purchase history?" automatically triggers the agent.
  </Tab>

  <Tab title="Only on Mentions (Recommended)">
    Agent only responds when explicitly @mentioned. Your team can discuss in the thread without triggering the agent.

    **Example**: After `@Gumloop analyze this customer`, your team can discuss the results freely. The agent only responds again when someone `@Gumloop`s a follow-up.
  </Tab>
</Tabs>

### Hide Workflow Run Results

Hides the "View full workflow results" button and run metadata from threads. **Disabled by default** (details shown). Enable it for cleaner, more conversational threads when your team doesn't need workflow visibility.

### Compact Progress View

Shows a condensed progress summary instead of listing each tool call individually. **Enabled by default.** Keeps threads tidy when the agent uses many tools in a single response.

### Attribution Stamp

When your agent sends a message in Slack, an **attribution stamp** is automatically included beneath the message. This stamp shows the name of the agent that sent the message along with a **View conversation** link that takes you directly to the full conversation in Gumloop.

<div align="center">
  <img src="https://mintcdn.com/agenthub/aLkNVzSb33_L2qU1/images/slack_attribution_stamp.png?fit=max&auto=format&n=aLkNVzSb33_L2qU1&q=85&s=6fef778326b9eca998264f60837fa83a" alt="Slack message from Gumloop showing the attribution stamp with agent name and View conversation link" width="400" data-path="images/slack_attribution_stamp.png" />
</div>

This makes it easy for anyone in the channel to see which agent is responding and jump into the full Gumloop conversation for more details, tool call history, and credit usage.

<Info>Attribution stamps are enabled by default. You can disable them per agent in **Agent Settings > Slack Preferences**.</Info>

### Image Generation

When your agent uses the Image Generation tool, both the **image file** and a **link** are sent directly in the thread. Enable it by adding the **Image Generation** tool in your agent's **Tools** section.

<Frame>
  <img src="https://mintcdn.com/agenthub/5q_6ypD02AHmhnv6/images/agent_add_image_generation_tool.png?fit=max&auto=format&n=5q_6ypD02AHmhnv6&q=85&s=72af84f6623d7592146176673fe93962" alt="Add Image Generation Tool option in agent Tools section" width="898" height="370" data-path="images/agent_add_image_generation_tool.png" />
</Frame>

<Info>Image generation costs **30 credits per image** regardless of model (DALL-E 3, Gemini 2.5 Flash, Gemini 3 Pro, or GPT-Image-1). This is in addition to normal model costs.</Info>

## Credentials & Authentication

### How Authentication Works

When you interact with an agent in Slack, the agent uses **your personal default credentials** (unless team apps are configured), not the agent creator's credentials. This ensures proper access control and data privacy.

<AccordionGroup>
  <Accordion title="For Existing Gumloop Users" icon="user-check">
    If your Slack email matches your Gumloop account:

    1. The agent automatically uses your personal default credentials
    2. You have immediate access to all tools and workflows you're authorized to use
    3. No additional setup required

    **Example**: If the agent uses Gmail and Google Calendar, it will access your personal Gmail and Calendar using your authenticated credentials.
  </Accordion>

  <Accordion title="For Non-Gumloop Users" icon="user-plus">
    If you're not yet a Gumloop user, you'll see a signup prompt the first time you try to use an agent:

    <div align="center">
      <img src="https://mintcdn.com/agenthub/jPoPAi23OST-yycZ/images/slack_signup_prompt.png?fit=max&auto=format&n=jPoPAi23OST-yycZ&q=85&s=bc1f6dcf46e4ae6223c487ef01a235fb" alt="Gumloop signup prompt in Slack" width="600" data-path="images/slack_signup_prompt.png" />
    </div>

    **What happens**:

    1. Agent responds with a message asking you to sign up
    2. Click the link to create your Gumloop account using your Slack email
    3. After signing up, authenticate with the services the agent needs
    4. Return to Slack and @Gumloop the agent again

    <Note>You must use the same email address for Slack and Gumloop for the integration to work properly.</Note>

    <Tip>**Enterprise organizations can skip this entirely for specific agents.** An admin can register your Slack workspace and let its members message chosen agents with no Gumloop account, running on a shared organization service account. See [Organization Service Accounts for Slack Agents](/enterprise-features/slack_agent_access).</Tip>
  </Accordion>

  <Accordion title="Missing Credentials" icon="key">
    If the agent needs a credential you haven't set up yet:

    1. The agent will notify you about the missing authentication
    2. Visit your [Connectors page](https://www.gumloop.com/personal/connectors)
    3. Authenticate with the required service
    4. Return to Slack and retry your request

    **Example**: If an agent uses Google Calendar but you haven't authenticated, you'll get a message like:

    ```text theme={"dark"}
    ⚠️ I need access to Google Calendar to complete this task. 
    Please authenticate at: https://www.gumloop.com/personal/connectors
    ```
  </Accordion>

  <Accordion title="Personal vs Team Agents" icon="building">
    <Tip>**Recommendation**: Always create agents in your personal space unless you need shared team apps or team collaboration features.</Tip>

    **Personal Agents** (created in personal space):

    * Anyone in the Slack channel can use the agent
    * Each user's request runs on their own personal credentials
    * Non-Gumloop users will be prompted to sign up
    * Best for most use cases

    **Team Agents** (created in a team):

    * **Access control**: Only members of that specific Gumloop team can use the agent
    * **App behavior**: If an MCP integration or app is set to use "team default," the team apps are used instead of personal apps
    * Non-team members will receive an access denied message

    Learn more about the differences between personal and team spaces in the [Organizations and Teams documentation](/core-concepts/teams#personal-vs-team).

    ### Key Differences Summary

    | Feature         | Personal Agent                     | Team Agent                                       |
    | --------------- | ---------------------------------- | ------------------------------------------------ |
    | Who can use it? | Anyone in Slack channel            | Only team members                                |
    | Apps used       | Always personal default            | Personal default OR team default (if configured) |
    | Best for        | General use, maximum accessibility | Team collaboration with shared apps              |
  </Accordion>
</AccordionGroup>

### Using Team Apps with Agents

If you want team members to use your agent without requiring them to set up their own apps, you can configure the agent to use team apps. This is particularly useful when you want to share an agent with your team and have everyone use the same connected integrations.

<Note>**Pro or Enterprise plan required**: Teams are available on Pro and Enterprise plans only.</Note>

<Steps>
  <Step title="Create or Select a Team">
    First, ensure you have a team set up. On the [Home page](https://www.gumloop.com/hub), find the **Teams** section in the sidebar and click the **+** icon to create a new team.

    <Frame>
      <img src="https://mintcdn.com/agenthub/1Km17aHtxdoqe1vs/images/create_team.png?fit=max&auto=format&n=1Km17aHtxdoqe1vs&q=85&s=4e94f77bcc10c73960ffaa81f921a823" alt="Click the plus icon in the Teams sidebar section" width="502" height="260" data-path="images/create_team.png" />
    </Frame>

    Alternatively, you can set a **default team** that all organization members automatically join. Go to [Settings → Teams](https://www.gumloop.com/settings/organization/teams), click the three-dot menu next to a team, and select **Make Default**.

    <div align="center">
      <img src="https://mintcdn.com/agenthub/KMa3S5LrPuj3opZR/images/default-workspace.png?fit=max&auto=format&n=KMa3S5LrPuj3opZR&q=85&s=e403e0e34836c15384a7286e0864a96e" alt="Default team settings with Make Default option" width="700" data-path="images/default-workspace.png" />
    </div>

    Learn more about creating teams in the [Organizations and Teams documentation](/core-concepts/teams#creating-a-team).
  </Step>

  <Step title="Move the Agent to the Team">
    From the hub page, find your agent and click the three-dot menu (⋮). Select **"Move to Team"** and choose the destination team.

    <div align="center">
      <img src="https://mintcdn.com/agenthub/hWLfuhk6Hge-i6gd/images/agent_move_to_workspace.png?fit=max&auto=format&n=hWLfuhk6Hge-i6gd&q=85&s=bd8d5406a673dcd22edca032ed51e5bc" alt="Move agent to team option" width="500" data-path="images/agent_move_to_workspace.png" />
    </div>
  </Step>

  <Step title="Connect Team Apps">
    Team apps are shared integrations and API keys that all team members can use. To connect apps for your team, **right-click** on your team in the sidebar and select **Apps**.

    <Frame>
      <img src="https://mintcdn.com/agenthub/EokesKd56_c0JgOx/images/team_right_click.png?fit=max&auto=format&n=EokesKd56_c0JgOx&q=85&s=47bb516b29d47321133a572678524f75" alt="Right-click a team to access Apps" width="610" height="400" data-path="images/team_right_click.png" />
    </Frame>

    Click **Connect New App** to add the integrations your agent needs (e.g., Gmail, Google Drive, Salesforce).

    <Frame>
      <img src="https://mintcdn.com/agenthub/EokesKd56_c0JgOx/images/team_settings_credentials.png?fit=max&auto=format&n=EokesKd56_c0JgOx&q=85&s=a85f68a434b75a598c32df1cae7defdb" alt="Team Connectors page with Connect New App button" width="3024" height="1722" data-path="images/team_settings_credentials.png" />
    </Frame>

    Members of your team will be able to use the apps you connect here through workflows and agents. Learn more about connecting team apps in the [Organizations and Teams documentation](/core-concepts/teams#connecting-team-apps).
  </Step>

  <Step title="Configure Agent to Use Team Apps">
    In your agent's settings, switch each integration from "Use Personal Default" to **"Use Team Default"**. Click the three-dot menu next to each integration and select the team app option.

    <div align="center">
      <img src="https://mintcdn.com/agenthub/hWLfuhk6Hge-i6gd/images/agent_use_workspace_credentials.png?fit=max&auto=format&n=hWLfuhk6Hge-i6gd&q=85&s=e6230e9915360eaf9264a66c580e912d" alt="Switch agent to use team apps" width="500" data-path="images/agent_use_workspace_credentials.png" />
    </div>
  </Step>
</Steps>

<Warning>
  **Important**: When an agent is in a team, users invoking the agent must also be members of that team. Otherwise, they will not be able to use the team apps or invoke the agent. To avoid manually adding users, set a **default team** which automatically includes all organization members.
</Warning>

### Data Privacy & Security

<CardGroup cols={2}>
  <Card title="Your Data Stays Private" icon="lock">
    When you use an agent with personal credentials, only your authenticated credentials are used. Other team members cannot access your personal data through the agent.
  </Card>

  <Card title="Controlled Access" icon="shield">
    Admin security controls and user roles in Gumloop apply to agents just like they do to workflows. You can only access what you're authorized to access.
  </Card>
</CardGroup>

## Slack Commands

### Channel Commands

| Command                  | What It Does                                                       |
| ------------------------ | ------------------------------------------------------------------ |
| `/gummie add [agent-id]` | Add an agent to the current channel                                |
| `/gummie remove`         | Remove the agent from the current channel                          |
| `/gummie active`         | Show which agent is active in this channel                         |
| `/gummie help`           | Show all available commands                                        |
| `/invite @Gumloop`       | Add the Gumloop bot to the channel (required before `/gummie add`) |

### Thread Commands

| Command | What It Does                                                                                                                 |
| ------- | ---------------------------------------------------------------------------------------------------------------------------- |
| `!stop` | Stop the agent's current response and prevent further replies in this thread. The agent reacts with 👍 to confirm.           |
| `!link` | Get an ephemeral link (only visible to you) to view the full conversation in Gumloop, including tool calls and credit usage. |

<Info>With the standard @Gumloop bot, only one agent can be active per channel. To switch agents, run `/gummie remove` first. If you need **multiple agents in the same channel**, use a [Custom Slack App](/core-concepts/custom_slack_app) for each additional agent.</Info>

## Best Practices & Troubleshooting

<Tabs>
  <Tab title="Best Practices">
    <AccordionGroup>
      <Accordion title="Choose the Right Channels" icon="hashtag">
        Deploy agents strategically to appropriate channels:

        **Supported channel types**:

        * ✅ Public channels
        * ✅ Private channels
        * ❌ Direct messages (DMs) — DMs to the Gumloop app go to your personal [Gumball](/core-concepts/gumball#gumball-in-slack) instead

        **Example deployments**:

        * **#support**: Customer service agents with ticket triage capabilities
        * **#sales**: Lead research and enrichment agents
        * **#marketing**: Campaign planning and content strategy agents
        * **#data**: Data analysis and reporting agents
      </Accordion>

      <Accordion title="Set Channel Expectations" icon="megaphone">
        When adding an agent to a channel, post a message explaining what the agent can do, how to use it (`@Gumloop [your question]`), and a quick example. This reduces friction and encourages proper usage.
      </Accordion>

      <Accordion title="Use Descriptive Agent Names" icon="tag">
        * ✅ "Support Ticket Assistant", "Sales Lead Researcher"
        * ❌ "Agent 1", "My Bot"

        Team members can check which agent is active with `/gummie active`.
      </Accordion>

      <Accordion title="Configure Thread Response Mode" icon="gear">
        * **"On all messages"**: Best for support bots and active collaboration
        * **"Only on mentions"** (Recommended): Best for most use cases, lets teams discuss without triggering the agent
      </Accordion>

      <Accordion title="Monitor Usage Patterns" icon="chart-line">
        Watch which questions get asked most, where agents struggle, and which workflows are called most often. Use these insights to refine instructions, add tools, and improve accuracy. View all conversations in the agent's settings page.
      </Accordion>

      <Accordion title="Ensure Credentials Are Set Up" icon="key">
        Before deploying to a team channel: test with your own credentials, document which services team members need to authenticate with, and share the [Connectors page](https://www.gumloop.com/personal/connectors) link.
      </Accordion>

      <Accordion title="Combine with Workflow Triggers" icon="bolt">
        For maximum automation, combine agents with Slack triggers. The agent handles ad-hoc questions while a workflow trigger automatically processes every new message for logging.
      </Accordion>
    </AccordionGroup>
  </Tab>

  <Tab title="Troubleshooting">
    <AccordionGroup>
      <Accordion title="Agent Doesn't Respond" icon="circle-exclamation">
        1. ✅ Did you @Gumloop the agent in your message?
        2. ✅ Is Gumloop added to the channel? (`/invite @Gumloop`)
           3\. ✅ Is an agent added to this channel? (`/gummie active` to verify)
           4\. ✅ Is this a public or private channel? (DMs to the Gumloop app reach [Gumball](/core-concepts/gumball#gumball-in-slack), not this agent)
        3. ✅ Have you authenticated with the required services?
      </Accordion>

      <Accordion title="Agent Doesn't Respond in Threads" icon="message">
        Check thread response mode: if set to "Only on mentions," you must @mention the agent in each thread reply. Verify in Gumloop → Agent Settings → Slack → Thread Response Trigger.
      </Accordion>

      <Accordion title="Signup Prompt for Existing Users" icon="user-plus">
        Your Slack email must match your Gumloop account email. If they're different, update one to match the other, then try again.
      </Accordion>

      <Accordion title="Authentication Errors" icon="key">
        Visit your [Connectors page](https://www.gumloop.com/personal/connectors), authenticate with the required service, and retry. For team agents, contact your team admin.
      </Accordion>

      <Accordion title="Wrong Agent Responding" icon="robot">
        With the standard @Gumloop bot, only one agent can be active per channel. Run `/gummie active` to check, `/gummie remove` to clear, then `/gummie add [correct-agent-id]`. For multiple agents in one channel, use a [Custom Slack App](/core-concepts/custom_slack_app).
      </Accordion>

      <Accordion title="Can't Add Agent to Channel" icon="lock">
        * ✅ Custom agents work in public and private channels, **not DMs** (DMs go to [Gumball](/core-concepts/gumball#gumball-in-slack))
        * ✅ Run `/invite @Gumloop` in the channel first
        * ✅ Ensure you have permission to add apps to the channel
        * ✅ Verify you copied the complete agent ID
      </Accordion>

      <Accordion title="Agent Responses Too Slow" icon="clock">
        Optimize workflows to use fewer AI nodes, limit tools to only what's necessary, use faster models, or break complex tasks into smaller interactions.
      </Accordion>

      <Accordion title="Can't Find Agent ID" icon="magnifying-glass">
        Skip the ID entirely: open your agent and click the **"Add to Slack"** button in the top bar. The **New Channel** and **Existing Channel** tabs handle everything. For the manual method, use the **Commands** tab to copy the full command.
      </Accordion>
    </AccordionGroup>
  </Tab>
</Tabs>

## Example Use Cases

<Tabs>
  <Tab title="Support Channel">
    **Agent**: Support Ticket Assistant

    **Channel**: #customer-support (public channel)

    **Common interactions**:

    ```text theme={"dark"}
    Team member: "@Gumloop Is ticket #12345 eligible for a refund?"
    Agent: [Checks ticket, reads policy, evaluates eligibility]

    Team member (in thread): "What about ticket #12346?"
    Agent (if "On all messages"): [Automatically checks ticket #12346]
    Or (if "Only on mentions"): [Waits for @mention]

    Team member: "@Gumloop Pull the last 5 interactions with customer@email.com"
    Agent: [Searches CRM and email, returns history]
    ```

    **Why it works**: Support team gets instant access to information without leaving Slack. Every interaction teaches the team how to use the agent for similar queries.

    **Setup tip**: Use "On all messages" mode for support channels to maintain conversation flow.
  </Tab>

  <Tab title="Sales Channel">
    **Agent**: Lead Research Assistant

    **Channel**: #sales-team (public channel)

    **Common interactions**:

    ```text theme={"dark"}
    Team member: "@Gumloop Research this company: [LinkedIn URL]"
    Agent: [Enriches lead, checks CRM for activity, drafts outreach]

    Team member (in thread): "Thanks! What's the best time to reach out?"
    (With "Only on mentions" - no agent response, allows team discussion)

    Team member: "@Gumloop What's the status of opportunities over $50k?"
    Agent: [Queries Salesforce, summarizes pipeline]
    ```

    **Why it works**: Sales reps get instant research without switching tools. Newer reps learn by watching experienced reps use the agent.

    **Setup tip**: Use "Only on mentions" mode to allow sales discussions without agent interruption.
  </Tab>

  <Tab title="Data Channel">
    **Agent**: Data Analysis Assistant

    **Channel**: #data-team (private channel)

    **Common interactions**:

    ```text theme={"dark"}
    Team member: "@Gumloop Compare Q4 revenue vs Q3 by product line"
    Agent: [Runs BigQuery workflow, generates comparison]

    Team member (in thread): "@Gumloop Now break it down by region"
    Agent: [Continues analysis with regional breakdown]

    Team member: "@Gumloop Flag any anomalies in yesterday's user signups"
    Agent: [Analyzes data, identifies outliers, reports findings]
    ```

    **Why it works**: Analysts get quick answers to data questions. Non-technical team members learn what data is available and how to access it.

    **Setup tip**: Works in private channels too! Use "Only on mentions" for controlled analysis requests.
  </Tab>
</Tabs>

## Important Limitations

<CardGroup cols={2}>
  <Card title="One Agent Per Channel (Standard Bot)" icon="1">
    Only one @Gumloop agent per channel. For multiple agents in the same channel, use a [Custom Slack App](/core-concepts/custom_slack_app) for each.
  </Card>

  <Card title="DMs Reach Gumball Only" icon="comment">
    Custom agents work in public and private channels. DMs to the Gumloop app are answered by your personal [Gumball](/core-concepts/gumball#gumball-in-slack).
  </Card>

  <Card title="@Mention Required" icon="at">
    You must @mention the agent to invoke it. Thread behavior depends on your response mode setting.
  </Card>

  <Card title="Credentials Depend on Agent Type" icon="key">
    Personal agents use your personal default apps. Team agents may use team apps if configured.
  </Card>

  <Card title="Email Matching Required" icon="envelope">
    Your Slack email must match your Gumloop account email for the integration to work.
  </Card>
</CardGroup>

## Next Steps

<CardGroup cols={3}>
  <Card title="Build Your First Agent" icon="robot" href="/core-concepts/agents">
    Learn how to create and configure agents in Gumloop
  </Card>

  <Card title="Using Agents in Teams" icon="microsoft" href="/core-concepts/agents_teams">
    Deploy the same agents to Microsoft Teams channels
  </Card>

  <Card title="Browse Agent Templates" icon="grid" href="https://www.gumloop.com/agents">
    Explore pre-built agents you can deploy immediately
  </Card>
</CardGroup>
