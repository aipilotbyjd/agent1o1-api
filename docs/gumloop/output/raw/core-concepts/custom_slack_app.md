> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Custom Slack App Integration

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1148809327?h=f96b623045" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="Custom Slack App Integration" />
</div>

Connect your own Slack app to a Gummie agent for custom branding, dedicated bot identity, and advanced enterprise deployments.

<Info>
  **This is an advanced feature.** For most users, we recommend using the [standard Gumloop Slack integration](https://docs.gumloop.com/core-concepts/agents_slack), which is simpler to set up and works great for the majority of use cases. Only use a custom Slack app if you have specific requirements for branding, multiple agents, or enterprise compliance.
</Info>

## When to Use a Custom Slack App

The standard Gumloop integration (`@Gumloop`) is the fastest way to get agents into Slack—it works out of the box with minimal setup. However, there are scenarios where connecting your own custom Slack app makes sense:

<CardGroup cols={2}>
  <Card title="Custom Branding" icon="palette">
    Give your agent a unique name and avatar that reflects its purpose—like "Sales Assistant" or "Support Bot" instead of "@Gumloop"
  </Card>

  <Card title="Multiple Agents, Same Workspace" icon="users">
    Run multiple agents with distinct identities in the same Slack workspace without confusion
  </Card>

  <Card title="Enterprise Compliance" icon="building">
    Meet IT security policies that require all Slack integrations to be owned and managed internally
  </Card>

  <Card title="White-Label Solutions" icon="tag">
    Build products or services with AI agents that appear under your brand, not Gumloop's
  </Card>
</CardGroup>

### Standard Integration vs Custom Slack App

| Aspect                    | Standard Integration                                                                                                                   | Custom Slack App                             |
| ------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------- |
| **Bot Identity**          | @Gumloop (shared)                                                                                                                      | Your custom name & avatar                    |
| **Setup Complexity**      | Simple (minutes)                                                                                                                       | Advanced (10-15 minutes)                     |
| **Slash Commands**        | ✅ `/gummie add`, `/gummie remove`, etc.                                                                                                | ❌ Not supported                              |
| **Direct Messages (DMs)** | ✅ Supported, but always answered by your personal [Gumball](/core-concepts/gumball#gumball-in-slack) — custom agents are channels only | ✅ Supported, answered by the connected agent |
| **Scope**                 | Per channel                                                                                                                            | Per custom app (works across all channels)   |
| **Multiple Agents**       | One per channel                                                                                                                        | One per custom app                           |
| **Best For**              | Most users                                                                                                                             | Enterprise, white-label, multi-agent setups  |

<Tip>
  **Start with the [standard integration first](https://docs.gumloop.com/core-concepts/agents_slack).** If you find you need custom branding or multiple agents later, you can always add a custom Slack app.
</Tip>

***

## Prerequisites

Before setting up a custom Slack app, ensure you have:

<Tabs>
  <Tab title="Slack Requirements" icon="slack">
    * Admin access to your Slack workspace (to create and install apps)
    * Permission to create Slack apps at [api.slack.com](https://api.slack.com/apps)
  </Tab>

  <Tab title="Gumloop Requirements" icon="circle-check">
    * An existing Gummie agent created in Gumloop
  </Tab>
</Tabs>

***

## Setup Options

Gumloop offers two ways to connect a custom Slack app to your agent:

<Tabs>
  <Tab title="New App (Recommended)" icon="wand-magic-sparkles">
    **Best for:** Users who don't have an existing Slack app and want the fastest setup experience.

    Gumloop provides a pre-configured manifest that automatically sets up all the required permissions and event subscriptions for you. This is the easiest path.
  </Tab>

  <Tab title="Existing App" icon="plug">
    **Best for:** Users who already have a Slack app created or need specific configurations not covered by the manifest.

    Connect an existing Slack app by providing its Client ID, Client Secret, and Signing Secret from Slack's API dashboard.
  </Tab>
</Tabs>

***

## Option 1: Create a New App (Recommended)

This guided workflow walks you through creating a new Slack app with all the correct settings pre-configured.

<Steps>
  <Step title="Open the Slack Connection Dialog">
    In your agent, click the **"Add to Slack"** button in the top bar.

    <div align="center">
      <img src="https://mintcdn.com/agenthub/d_0f-YSO7rlIpn_B/images/add_to_slack_button.png?fit=max&auto=format&n=d_0f-YSO7rlIpn_B&q=85&s=b93de730f061fdcdfed0a78a072a6c65" alt="Add to Slack button in the agent top bar" width="700" data-path="images/add_to_slack_button.png" />
    </div>

    In the connection dialog, select the **"Custom App"** tab.

    <div align="center">
      <img src="https://mintcdn.com/agenthub/25dtpk9m2FmEAsAB/images/custom_slack_app_dialog.png?fit=max&auto=format&n=25dtpk9m2FmEAsAB&q=85&s=ccae72a4fb5c4464d24856249d93ce61" alt="Custom App tab in the Add to Slack dialog showing New App and Existing App options" width="600" data-path="images/custom_slack_app_dialog.png" />
    </div>
  </Step>

  <Step title="Choose New App">
    A modal will appear asking how you'd like to connect your Slack app. Select **"New App"** to create a fresh Slack app with Gumloop's pre-configured settings.

    <div align="center">
      <img src="https://mintcdn.com/agenthub/x-kyDeRNfu_5Fgk2/images/custom_slack_app_choose_method.png?fit=max&auto=format&n=x-kyDeRNfu_5Fgk2&q=85&s=d8f179374fbac412525b83019cd2af6d" alt="Modal showing New App and Existing App options" width="600" data-path="images/custom_slack_app_choose_method.png" />
    </div>
  </Step>

  <Step title="Customize Your App">
    Enter the details for your custom Slack app:

    * **App Name**: The display name your bot will have in Slack (e.g., "Sales Assistant", "Support Bot")
    * **App Tag**: The @mention handle for your bot (e.g., `@salesassistant`)
    * **App Description** (optional): A description others will see when viewing your app in Slack

    <div align="center">
      <img src="https://mintcdn.com/agenthub/x-kyDeRNfu_5Fgk2/images/custom_slack_app_customize.png?fit=max&auto=format&n=x-kyDeRNfu_5Fgk2&q=85&s=6e8226401a44f4a788daa1e7674987fa" alt="Customization modal with App Name, App Tag, and App Description fields" width="600" data-path="images/custom_slack_app_customize.png" />
    </div>

    <Tip>Choose a descriptive name that reflects what your agent does. This is what your team will see when the bot responds in Slack.</Tip>

    Click **"Next"** when you're done.
  </Step>

  <Step title="Follow the Setup Guide">
    Gumloop provides a step-by-step setup guide with everything you need:

    <div align="center">
      <img src="https://mintcdn.com/agenthub/x-kyDeRNfu_5Fgk2/images/custom_slack_app_setup_guide.png?fit=max&auto=format&n=x-kyDeRNfu_5Fgk2&q=85&s=3c120bb20c41dc5335769a5410f47ba9" alt="Setup Guide showing 5 steps to create and configure the Slack app" width="600" data-path="images/custom_slack_app_setup_guide.png" />
    </div>

    **Here's what to do:**

    1. **Copy the Slack manifest JSON** — Click the **"Copy"** button. This manifest contains all the pre-configured permissions, scopes, and event subscriptions your app needs.

    2. **Create a new Slack app** — Click **"Open"** to go to [api.slack.com/apps](https://api.slack.com/apps), then:
       * Click **"Create New App"**
       * Choose **"From an app manifest"**
           <div align="center">
             <img src="https://mintcdn.com/agenthub/sn98DXlfn12zC-2p/images/slack_app_from_manifest.png?fit=max&auto=format&n=sn98DXlfn12zC-2p&q=85&s=b70f35c73a5763a554884a14dc18d9c8" alt="Slack App from Manifest Option" width="600" data-path="images/slack_app_from_manifest.png" />
           </div>
       * Select your Slack workspace

    3. **Paste the manifest JSON** — In the manifest editor, paste the JSON you copied from Gumloop. This automatically configures:

           <div align="center">
             <img src="https://mintcdn.com/agenthub/cH0BI8l6_qMFHrTJ/images/paste_slack_manifest.png?fit=max&auto=format&n=cH0BI8l6_qMFHrTJ&q=85&s=3269f7db0c3213531994c364562abd06" alt="Paste Gumloop's Slack Manifest Code here" width="600" data-path="images/paste_slack_manifest.png" />
           </div>

       * All required bot token scopes (including the `im:*` scopes needed for direct messages)
       * Event subscriptions with the correct webhook URL (including `message.im` for DMs)
       * Interactivity with the correct request URL
       * The App Home **Messages tab**, so users can DM your bot
       * Bot user settings

    4. **Complete installation in Slack** — Review the app settings and click **"Create"**, then **"Install to Workspace"** and authorize the app.

    5. **Return to Gumloop** — Come back to this page and click **"Next"** to continue.
  </Step>

  <Step title="Enter Your App Credentials">
    After creating your Slack app, you'll need to provide credentials from Slack's **Basic Information** page.

    <div align="center">
      <img src="https://mintcdn.com/agenthub/x-kyDeRNfu_5Fgk2/images/custom_slack_app_credentials.png?fit=max&auto=format&n=x-kyDeRNfu_5Fgk2&q=85&s=ef132f9d25faec6dceee92d1e748ad92" alt="Credential entry form requesting Client ID, Client Secret, and Signing Secret" width="600" data-path="images/custom_slack_app_credentials.png" />
    </div>

    **In your Slack app settings, go to "Basic Information" and copy:**

    * **Client ID** — Found under "App Credentials"
    * **Client Secret** — Click "Show" to reveal, then copy
    * **Signing Secret** — Click "Show" to reveal, then copy

    <div align="center">
      <img src="https://mintcdn.com/agenthub/x-kyDeRNfu_5Fgk2/images/custom_slack_app_tokens.png?fit=max&auto=format&n=x-kyDeRNfu_5Fgk2&q=85&s=1dcfa45fb1082448ce79d733bcc7e84a" alt="Credential entry form requesting Client ID, Client Secret, and Signing Secret" width="600" data-path="images/custom_slack_app_tokens.png" />
    </div>

    <Warning>Never share your Client Secret or Signing Secret publicly. Treat them like passwords.</Warning>

    Click **"Connect to Slack"** to complete the OAuth flow and authorize the connection.
  </Step>

  <Step title="Ensure Slack App is Installed to the Workspace">
    Make sure your Slack app is installed to your workspace. If you skipped this step earlier, go to your Slack app settings → **OAuth & Permissions** → **Install to Workspace**.

    <div align="center">
      <img src="https://mintcdn.com/agenthub/hZLThkwuUSXKXegU/images/install_custom_slack_app.png?fit=max&auto=format&n=hZLThkwuUSXKXegU&q=85&s=4990d472c2aa9fec27e55e033cfbac95" alt="Install Custom Slack App" width="600" data-path="images/install_custom_slack_app.png" />
    </div>
  </Step>

  <Step title="Invite Your Bot to Channels">
    Your custom bot needs to be invited to channels where you want it to work.

    In Slack, go to each channel and type:

    ```text theme={"dark"}
    /invite @YourBotName
    ```

    Replace `@YourBotName` with the App Tag you configured (e.g., `@salesassistant`).

    <div align="center">
      <img src="https://mintcdn.com/agenthub/x-kyDeRNfu_5Fgk2/images/custom_slack_app_invite_bot.png?fit=max&auto=format&n=x-kyDeRNfu_5Fgk2&q=85&s=5052b9b230d16df738c30cb0485c94d8" alt="Slack channel showing bot being invited with /invite command" width="600" data-path="images/custom_slack_app_invite_bot.png" />
    </div>

    Your custom Slack app agent is now ready! @mention your bot in a channel, or [send it a direct message](#direct-messages-dms), to start a conversation.
  </Step>
</Steps>

<Note>
  **Already connected a custom Slack app before direct messages were supported?** Existing apps won't receive DMs until you update them. See [Enabling DMs on an already-connected app](#enabling-dms-on-an-already-connected-app).
</Note>

***

## Option 2: Connect an Existing App

If you already have a Slack app or need custom configurations, you can connect it directly to Gumloop.

<Steps>
  <Step title="Open the Slack Connection Dialog">
    In your agent, click the **"Add to Slack"** button in the top bar, then select the **"Custom App"** tab in the connection dialog.

    <div align="center">
      <img src="https://mintcdn.com/agenthub/d_0f-YSO7rlIpn_B/images/add_to_slack_button.png?fit=max&auto=format&n=d_0f-YSO7rlIpn_B&q=85&s=b93de730f061fdcdfed0a78a072a6c65" alt="Add to Slack button in the agent top bar" width="700" data-path="images/add_to_slack_button.png" />
    </div>
  </Step>

  <Step title="Choose Existing App">
    Select **"Existing App"** to connect a Slack app you've already created.

    <div align="center">
      <img src="https://mintcdn.com/agenthub/x-kyDeRNfu_5Fgk2/images/custom_slack_app_choose_method.png?fit=max&auto=format&n=x-kyDeRNfu_5Fgk2&q=85&s=d8f179374fbac412525b83019cd2af6d" alt="Modal showing New App and Existing App options" width="600" data-path="images/custom_slack_app_choose_method.png" />
    </div>
  </Step>

  <Step title="Configure Your Slack App (If Not Already Done)">
    Before connecting, ensure your existing Slack app has the required configuration:

    <AccordionGroup>
      <Accordion title="Required Bot Token Scopes" icon="key">
        In your Slack app settings, go to **OAuth & Permissions** and add these scopes:

        | Scope               | Purpose                                           |
        | ------------------- | ------------------------------------------------- |
        | `app_mentions:read` | Receive @mentions of your bot                     |
        | `channels:history`  | Read messages in public channels                  |
        | `channels:read`     | List available channels                           |
        | `chat:write`        | Send messages as your bot                         |
        | `groups:history`    | Read messages in private channels                 |
        | `groups:read`       | List private channels                             |
        | `im:history`        | Read direct messages sent to your bot             |
        | `im:read`           | Detect direct message channels (required for DMs) |
        | `im:write`          | Open direct message channels to reply             |
        | `users:read`        | Look up user information                          |
        | `users:read.email`  | Match Slack users to Gumloop accounts             |
        | `files:read`        | Access file attachments                           |
        | `files:write`       | Attach generated files/images to messages         |
        | `reactions:write`   | Add emoji reactions to messages                   |

        <Tip>The `im:history`, `im:read`, and `im:write` scopes are what allow users to **direct message** your bot. If you leave them out, DMs won't reach your agent.</Tip>

        Optionally, add `assistant:write` to have your bot appear in Slack's **agents & assistants** dropdown (top right of the Slack client). It isn't required for DMs or mentions to work—see [Enabling DMs on an already-connected app](#enabling-dms-on-an-already-connected-app).
      </Accordion>

      <Accordion title="Required Event Subscriptions" icon="bell">
        In your Slack app settings, go to **Event Subscriptions**:

        1. Toggle **"Enable Events"** to ON
        2. Set the **Request URL** to:
           ```text theme={"dark"}
           https://api.gumloop.com/api/v1/external/slack/events
           ```
        3. Wait for verification (green checkmark)
        4. Under **"Subscribe to bot events"**, add:
           * `app_mention`
           * `message.channels`
           * `message.groups` (for private channels)
           * `message.im` (for direct messages)
        5. Save changes
      </Accordion>

      <Accordion title="Required App Home Configuration (for DMs)" icon="comment">
        To let users **direct message** your bot, you must expose the writable Messages tab. In your Slack app settings, go to **Features → App Home**:

        1. Under **Show Tabs**, enable the **Messages Tab**.
        2. Check **"Allow users to send Slash commands and messages from the messages tab"**.

        <div align="center">
          <img src="https://mintcdn.com/agenthub/ijuKwNxG7DS_uGP7/images/custom_slack_app_app_home_messages_tab.png?fit=max&auto=format&n=ijuKwNxG7DS_uGP7&q=85&s=fb2f462a5fd6044620c9ae50c0b9f6ec" alt="Slack App Home settings with the Messages Tab enabled and the 'Allow users to send Slash commands and messages from the messages tab' checkbox selected" width="600" data-path="images/custom_slack_app_app_home_messages_tab.png" />
        </div>

        Without this, Slack hides the message box in your bot's DM view and users can't message it directly.
      </Accordion>

      <Accordion title="Required Interactivity Configuration" icon="hand-pointer">
        In your Slack app settings, go to **Interactivity & Shortcuts**:

        1. Toggle **"Interactivity"** to ON
        2. Set the **Request URL** to:
           ```text theme={"dark"}
           https://api.gumloop.com/api/v1/external/slack/interactive
           ```
        3. Save changes

        This enables your custom Slack app to handle interactive components like button clicks, modal submissions, and form responses from your agent.
      </Accordion>

      <Accordion title="Install to Workspace" icon="download">
        Go to **OAuth & Permissions** and click **"Install to Workspace"** if you haven't already. Authorize the app when prompted.
      </Accordion>
    </AccordionGroup>
  </Step>

  <Step title="Enter Your App Credentials">
    Provide the credentials from your Slack app's **Basic Information** page:

    <div align="center">
      <img src="https://mintcdn.com/agenthub/x-kyDeRNfu_5Fgk2/images/custom_slack_app_credentials.png?fit=max&auto=format&n=x-kyDeRNfu_5Fgk2&q=85&s=ef132f9d25faec6dceee92d1e748ad92" alt="Credential entry form requesting Client ID, Client Secret, and Signing Secret" width="600" data-path="images/custom_slack_app_credentials.png" />
    </div>

    * **Client ID** — Found under "App Credentials"
    * **Client Secret** — Click "Show" to reveal, then copy
    * **Signing Secret** — Click "Show" to reveal, then copy

    Click **"Connect to Slack"** to complete the OAuth flow.
  </Step>

  <Step title="Assign to Your Agent and Invite to Channels">
    Select your custom Slack app credential from the dropdown and click **"Add"**.

    Then invite your bot to channels in Slack:

    ```text theme={"dark"}
    /invite @YourBotName
    ```
  </Step>
</Steps>

***

## Enabling DMs on an Already-Connected App

Direct message support was added after custom Slack apps first launched. If you connected your custom app **before** DMs were available, the missing piece is the App Home **Messages tab**—without it, Slack hides the message box so users can't DM your bot. The `message.im` event subscription and the `im:*` scopes were already part of Gumloop's manifest, so apps created with the **New App** flow already have them and there's nothing new to add.

Enable DMs with the one-time steps below.

<Steps>
  <Step title="Enable the App Home Messages tab">
    In your Slack app settings at [api.slack.com/apps](https://api.slack.com/apps), go to **Features → App Home** and, under **Show Tabs**:

    1. Enable the **Messages Tab**.
    2. Check **"Allow users to send Slash commands and messages from the messages tab"**.

    <div align="center">
      <img src="https://mintcdn.com/agenthub/ijuKwNxG7DS_uGP7/images/custom_slack_app_app_home_messages_tab.png?fit=max&auto=format&n=ijuKwNxG7DS_uGP7&q=85&s=fb2f462a5fd6044620c9ae50c0b9f6ec" alt="Slack App Home settings with the Messages Tab enabled and the 'Allow users to send Slash commands and messages from the messages tab' checkbox selected" width="600" data-path="images/custom_slack_app_app_home_messages_tab.png" />
    </div>
  </Step>

  <Step title="Reinstall the app to your workspace">
    Go to **Settings → Install App** (or **OAuth & Permissions**) and click **"Reinstall to Workspace"**, then authorize when prompted. This applies the App Home change so DMs start reaching your agent.

    <div align="center">
      <img src="https://mintcdn.com/agenthub/2MAQUjph00RSL1Hm/images/custom_slack_app_reinstall.png?fit=max&auto=format&n=2MAQUjph00RSL1Hm&q=85&s=abbc9a1104beffd2c82e999484363956" alt="Slack Install App settings showing the Bot User OAuth Token and the Reinstall to Workspace button" width="700" data-path="images/custom_slack_app_reinstall.png" />
    </div>

    <Note>You don't need to reconnect the credential in Gumloop. Your existing credential already has the scopes required to send DMs (`chat:write` and `im:write`), so once the Messages tab is enabled and the app is reinstalled, DMs work without re-authorizing.</Note>
  </Step>

  <Step title="(Existing apps only) Verify the DM event and scopes">
    If you connected an **existing app** you built yourself—rather than using Gumloop's **New App** manifest—confirm it's configured for DMs:

    * **Event Subscriptions → Subscribe to bot events** includes `message.im`.
    * **OAuth & Permissions → Bot Token Scopes** includes `im:history`, `im:read`, and `im:write`.

    If you add any of these, reinstall the app again so the changes take effect. Apps created with Gumloop's **New App** manifest already have all of this.
  </Step>

  <Step title="(Optional) Add the assistant:write scope to show your bot in Slack's agents list">
    Slack only lists an app in the **agents & assistants** dropdown (the icon in the top right of the Slack client) if the app has the `assistant:write` bot scope. This scope is **not** part of Gumloop's **New App** manifest, so apps created or re-created from the manifest lose their spot in that list until you add it back.

    To add it, go to **OAuth & Permissions → Bot Token Scopes**, add `assistant:write`, then reinstall the app to your workspace.

    <div align="center">
      <img src="https://mintcdn.com/agenthub/w_47NK746UwCehMK/images/custom_slack_app_agents_dropdown.png?fit=max&auto=format&n=w_47NK746UwCehMK&q=85&s=8bca9b404a6579030f2dba1ada0fa650" alt="Slack Agents dropdown listing custom Slack app bots" width="380" data-path="images/custom_slack_app_agents_dropdown.png" />
    </div>

    <Note>`assistant:write` is optional—DMs work without it. It only controls whether your bot appears in Slack's agents & assistants dropdown as a shortcut for starting a DM.</Note>
  </Step>
</Steps>

***

## Using Your Custom Slack App Agent

Once set up, interacting with your custom Slack app agent works similarly to the standard Gumloop integration, with a few key differences.

### Starting a Conversation

@mention your custom bot in any channel where it's been invited:

```text theme={"dark"}
@YourBotName What are the latest support tickets?
```

<div align="center">
  <img src="https://mintcdn.com/agenthub/x-kyDeRNfu_5Fgk2/images/custom_slack_app_interaction.png?fit=max&auto=format&n=x-kyDeRNfu_5Fgk2&q=85&s=cfe90ace7740b66ea3d5b4d8909c7f0d" alt="Interaction with Custom Slack App" width="600" data-path="images/custom_slack_app_interaction.png" />
</div>

The agent will:

1. Process your request using its configured tools and workflows
2. Respond in a thread to keep conversations organized
3. Continue the conversation within the thread based on your agent's settings

<Tip>**Thread commands work here too!** Type `!stop` to stop the agent's response, or `!link` to get a link to view the conversation in Gumloop.</Tip>

### Direct Messages (DMs)

Your custom bot can also be messaged directly—no channel or @mention required. Open a DM with your bot from the Slack sidebar (or the **Messages** tab on the app's profile) and just start typing:

```text theme={"dark"}
What are the latest support tickets?
```

Each top-level message you send starts a new conversation; reply within that message's thread to continue the same conversation.

<Note>A custom Slack app is the only way to DM a **custom** agent. DMs to the standard Gumloop app also work, but they always reach your personal [Gumball](/core-concepts/gumball#gumball-in-slack), and custom agents on that app stay in public and private channels.</Note>

<Info>
  DMs require the App Home **Messages tab** to be enabled and the `im:history`, `im:read`, and `im:write` scopes to be granted. Apps created with Gumloop's **New App** manifest have this configured automatically. If you connected an **existing app**—or linked a custom app before DM support shipped—follow [Enabling DMs on an already-connected app](#enabling-dms-on-an-already-connected-app).
</Info>

### Interactive Features

Custom Slack apps fully support Slack interactive components. Your agent can send messages with buttons, open modals, and collect form responses, just like the standard Gumloop integration.

Supported interactive components include:

* **Button clicks** — Respond to action buttons in agent messages
* **Modal submissions** — Collect structured input through Slack modals
* **Form responses** — Process multi-field form submissions from users

<Info>Interactive features require the **Interactivity Request URL** to be configured. If you used Gumloop's manifest to create your app, this is already set up. For existing apps, see the [Interactivity configuration step](#option-2-connect-an-existing-app) above.</Info>

### Key Differences from Standard Integration

<AccordionGroup>
  <Accordion title="No Slash Commands" icon="terminal">
    Custom Slack apps don't support `/gummie` slash commands. You cannot use:

    * `/gummie add` — Bot is added by inviting to channels instead
    * `/gummie remove` — Remove bot by kicking from channels
    * `/gummie active` — You know which bot is in a channel by its name
    * `/gummie help` — Not available

    **To manage your custom app agent:**

    * Add to channels: `/invite @YourBotName`
    * Remove from channels: `/kick @YourBotName` or remove via channel settings
  </Accordion>

  <Accordion title="Works Across All Channels" icon="arrows-left-right">
    Unlike the standard integration which is channel-specific, your custom Slack app works in **any channel where it's invited**. You don't need to "add" the agent to each channel—just invite the bot.
  </Accordion>

  <Accordion title="Distinct Bot Identity" icon="user">
    Your agent appears with whatever name and avatar you configured in Slack's app settings. Team members interact with `@YourBotName` instead of `@Gumloop`.
  </Accordion>
</AccordionGroup>

### Slack Preferences

All the same Slack preferences available for standard agents apply to custom Slack apps:

* **Thread Response Trigger**: Control whether the agent responds to all messages in a thread or only when @mentioned
* **Stream Reasoning**: Show or hide the agent's thought process
* **Hide Workflow Run Results**: Show or hide workflow execution details

Configure these in your agent's settings under **Slack Preferences**.

<div align="center">
  <img src="https://mintcdn.com/agenthub/OoxUgC5hWWI9DFcm/images/agent_slack_preference_all_messages.png?fit=max&auto=format&n=OoxUgC5hWWI9DFcm&q=85&s=5690f05c4e849f415c5115601c837759" alt="Slack preferences settings panel" width="700" data-path="images/agent_slack_preference_all_messages.png" />
</div>

## Choosing Which Bot the Agent Acts As

If an agent has more than one custom Slack app connected — say a workspace bot and a white-labeled "Sales Assistant" — you can pick which one it acts as when it uses Slack tools on its own.

Open your agent, go to **Connectors**, and click the **Slack** app. Under **Account** you'll find a **Bot Account** selector:

<div align="center">
  <img src="https://mintcdn.com/agenthub/fL01riboJsO5a1GK/images/slack_bot_account_selector.png?fit=max&auto=format&n=fL01riboJsO5a1GK&q=85&s=2a2885b0b61094dacf1fef69a066e67f" alt="Slack tool settings in an agent showing the Account selector and the Bot Account dropdown with Gumloop Bot selected and a custom Slack app listed below it" width="420" data-path="images/slack_bot_account_selector.png" />
</div>

* **Gumloop Bot** (the default) — the agent's Slack tool calls go out as Gumloop's bot.
* **Any custom Slack app connected to this agent** — listed by the name you gave the credential. Pick one and its bot becomes the identity the agent acts as.

Only one bot account can be selected at a time, and the change saves immediately. You need edit access to the agent to change it.

<Note>
  **When the agent is talking to you in Slack, the app you messaged always wins.** If someone @mentions your custom bot or DMs it, the agent replies as that app regardless of the Bot Account setting. The setting decides which bot to use when there's no inbound Slack app to infer it from — for example a scheduled run, a trigger, or a chat in Gumloop that calls a Slack tool.
</Note>

<Info>
  **Account vs Bot Account.** The **Account** selector above it controls which connected Slack *account's* credentials the agent uses for tool calls. **Bot Account** controls which *bot identity* it acts as. The Bot Account selector only appears for Slack, and only once at least one custom Slack app credential is connected to the agent.
</Info>

Publicly shared agents don't use a default bot account, so anonymous users of a public agent never act as your organization's bot.

***

## Credential Ownership Options

When creating your custom Slack app credential, you can choose who has access:

| Credential Type  | Who Can Use It           | Best For                    |
| ---------------- | ------------------------ | --------------------------- |
| **Personal**     | Only you                 | Testing, personal projects  |
| **Team**         | All team members         | Team-shared agents          |
| **Organization** | All organization members | Enterprise-wide deployments |

***

## Troubleshooting

<AccordionGroup>
  <Accordion title="Bot doesn't respond to messages" icon="circle-exclamation">
    **Check these items:**

    * **Is the Slack app installed to your workspace?** This is the most common issue. Go to your Slack app settings → **OAuth & Permissions** → **Install to Workspace**.

          <div align="center">
            <img src="https://mintcdn.com/agenthub/hZLThkwuUSXKXegU/images/install_custom_slack_app.png?fit=max&auto=format&n=hZLThkwuUSXKXegU&q=85&s=4990d472c2aa9fec27e55e033cfbac95" alt="Install Custom Slack App to Workspace" width="500" data-path="images/install_custom_slack_app.png" />
          </div>

    * Is the bot invited to the channel? (`/invite @YourBotName`)

    * Did you @mention the bot in your message?

    * Are the event subscriptions configured correctly in Slack?

    * Is the webhook URL verified? (`https://api.gumloop.com/api/v1/external/slack/events`)

    * Is the credential properly assigned to your agent in Gumloop?

    * Does the user have a Gumloop account with matching email?
  </Accordion>

  <Accordion title="Bot doesn't respond to direct messages (DMs)" icon="comment">
    **Check these items:**

    * **Is the App Home Messages tab enabled?** Go to your Slack app settings → **Features → App Home** → enable the **Messages Tab** and check **"Allow users to send Slash commands and messages from the messages tab"**.
    * Is `message.im` subscribed under **Event Subscriptions → Subscribe to bot events**?
    * Are the `im:history`, `im:read`, and `im:write` scopes granted under **OAuth & Permissions**?
    * **Did you connect this app before DM support shipped?** The App Home Messages tab was added to the manifest later, so older apps have it disabled. Enable the Messages tab and reinstall the app—no need to reconnect the credential in Gumloop. See [Enabling DMs on an already-connected app](#enabling-dms-on-an-already-connected-app).
  </Accordion>

  <Accordion title="Bot works in some channels but not others" icon="hashtag">
    **Check:**

    * Is the bot invited to the non-working channels?
    * For private channels, do you have `groups:history` and `groups:read` scopes configured?
    * Is there a different agent assigned via the standard integration in that channel?
  </Accordion>

  <Accordion title="Webhook URL verification fails in Slack" icon="globe">
    The URL `https://api.gumloop.com/api/v1/external/slack/events` should verify automatically.

    **If it fails:**

    * Check for typos in the URL
    * Ensure you're using HTTPS (not HTTP)
    * Try again after a few minutes (temporary network issues)
    * Contact Gumloop support if the issue persists
  </Accordion>

  <Accordion title="Buttons or modals not working" icon="hand-pointer">
    If interactive components (buttons, modals, form submissions) are not working:

    * **Is Interactivity enabled?** Go to your Slack app settings → **Interactivity & Shortcuts** → ensure the toggle is ON
    * **Is the Request URL correct?** It should be set to `https://api.gumloop.com/api/v1/external/slack/interactive`
    * **Is the Signing Secret correct?** Gumloop uses your app's Signing Secret to verify interactive payloads. Re-check the value in **Basic Information** → **App Credentials**
    * **Did you use the manifest?** If you created your app using Gumloop's manifest, interactivity is pre-configured. If you connected an existing app, you may need to add this manually.
  </Accordion>

  <Accordion title="Can't find credentials in Slack" icon="magnifying-glass">
    In your Slack app at [api.slack.com/apps](https://api.slack.com/apps):

    * **Client ID, Client Secret, Signing Secret**: Found in **Basic Information** → **App Credentials**
    * Click "Show" next to each secret to reveal it, then copy
  </Accordion>
</AccordionGroup>

***

## Important Limitations

<CardGroup cols={2}>
  <Card title="One Agent Per Custom App" icon="1">
    Each custom Slack app credential can only be assigned to one Gummie agent. Create separate Slack apps for each agent you need.
  </Card>

  <Card title="No Slash Commands" icon="terminal">
    Custom Slack apps don't support `/gummie` commands. Manage your bot by inviting/removing it from channels directly.
  </Card>

  <Card title="Credential Exclusivity" icon="lock">
    A custom Slack app credential cannot be shared between multiple agents. Assigning it to a second agent removes it from the first.
  </Card>

  <Card title="Manual Channel Management" icon="hand">
    Unlike the standard integration, you must manually invite your custom bot to each channel where you want it to work.
  </Card>
</CardGroup>

***

## Security Considerations

<AccordionGroup>
  <Accordion title="Credential Protection" icon="shield">
    Your Slack app's Client Secret and Signing Secret are stored securely in Gumloop and used to verify that incoming webhooks and interactive payloads are actually from Slack. Both event callbacks and interactive component actions (button clicks, modal submissions) are verified using your app's Signing Secret before processing. Never share these secrets or expose them in logs.
  </Accordion>

  <Accordion title="User Matching" icon="users">
    Users must have matching email addresses in Slack and Gumloop for the integration to work. This ensures proper authentication and prevents unauthorized access.
  </Accordion>

  <Accordion title="Permission Verification" icon="check-double">
    Before processing messages, Gumloop verifies:

    * The user has a valid Gumloop account
    * The user has permission to access the agent
    * For team agents, team membership is verified
  </Accordion>
</AccordionGroup>

***

## FAQ

<AccordionGroup>
  <Accordion title="Can I use both the standard @Gumloop bot and a custom app in the same workspace?">
    Yes! They operate independently. The standard bot uses channel-based mapping with `/gummie` commands, while custom apps use bot-based mapping with direct invites. You can have both active in different channels.
  </Accordion>

  <Accordion title="What's the difference between New App and Existing App setup?">
    **New App** uses a pre-configured manifest that automatically sets up all required permissions and events—it's faster and less error-prone. **Existing App** lets you connect a Slack app you've already created, but you're responsible for ensuring all scopes and events are configured correctly.
  </Accordion>

  <Accordion title="What happens if I delete my custom Slack app in Slack?">
    The credential in Gumloop will stop working immediately. You'll need to create a new app and set it up again.
  </Accordion>

  <Accordion title="How do I update my bot's name or avatar?">
    Update these in your Slack app settings at [api.slack.com/apps](https://api.slack.com/apps). Changes will reflect in Slack automatically—no changes needed in Gumloop.
  </Accordion>

  <Accordion title="Can I have multiple custom apps assigned to one agent?">
    Yes! This is useful when you want the same agent available in multiple Slack workspaces. Each workspace installation creates a separate credential, and you can assign all of them to a single agent.
  </Accordion>
</AccordionGroup>

***

## Next Steps

<CardGroup cols={3}>
  <Card title="Build Your First Agent" icon="robot" href="/core-concepts/agents">
    Learn how to create and configure agents in Gumloop
  </Card>

  <Card title="Standard Slack Integration" icon="slack" href="/core-concepts/agents_slack">
    Review the standard Slack setup if you haven't used it yet
  </Card>

  <Card title="Managing Credentials" icon="key" href="/core-concepts/credentials">
    Learn more about credential types and management
  </Card>
</CardGroup>
