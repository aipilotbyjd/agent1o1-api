> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Human in the Loop

> Keep humans in control of your AI agents with tool approval settings, app rules, and the Ask Question ability.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1201661318?h=72e15205e9&badge=0&autopause=0&player_id=0&app_id=58479" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture; clipboard-write; encrypted-media; web-share" referrerPolicy="strict-origin-when-cross-origin" allowFullScreen title="Human in the Loop" />
</div>

Human in the Loop lets you decide **exactly** when your agent needs to pause and ask for permission before taking an action. Instead of blindly trusting every tool call, you can require approval for sensitive operations like sending emails, creating repositories, or deleting records.

Your agent works autonomously on low-risk tasks, while you stay in control of the high-stakes ones.

***

## How It Works

<Steps>
  <Step title="Agent pauses">
    When the agent tries to use a tool that requires approval, it stops and shows you exactly what it wants to do: the tool, the arguments, and the intent.
  </Step>

  <Step title="You get notified">
    You receive an approval request via in-app notification, Slack DM, or directly in the chat thread.
  </Step>

  <Step title="You approve or reject">
    If approved, the agent continues from where it left off. If rejected, it acknowledges the rejection and adjusts its approach.
  </Step>
</Steps>

The agent never executes a gated tool call without your explicit go-ahead.

***

## Setting Up Approval Controls

<Tabs>
  <Tab title="App-Level Settings">
    The most common approach. When you add a connector (like GitHub, Gmail, or Slack) to your agent, you can configure its **Approval Settings** to control when the agent needs permission.

    Open your agent's configuration, find the connector in the **Connectors** list, and click the approval icon next to it.

    <Frame>
      <img src="https://mintcdn.com/agenthub/-bEDK0ObJtbSKcBz/images/human-in-the-loop/approval-settings-dropdown.png?fit=max&auto=format&n=-bEDK0ObJtbSKcBz&q=85&s=60c39f2564eba17fae8f97420905d89c" alt="Approval Settings dropdown showing Always allow, Ask each time, Ask for writes/deletes, and Custom options" style={{ maxWidth: '340px' }} width="782" height="906" data-path="images/human-in-the-loop/approval-settings-dropdown.png" />
    </Frame>

    | Mode                       | What it does                                                                       |
    | -------------------------- | ---------------------------------------------------------------------------------- |
    | **Always allow**           | The agent can use all tools from this app without asking. This is the default.     |
    | **Ask each time**          | Every tool call requires your approval, whether it reads or writes data.           |
    | **Ask for writes/deletes** | Read-only tools run freely. Write and delete operations require approval.          |
    | **Custom**                 | Set approval requirements on a per-tool basis. You control each tool individually. |

    <Tip>**"Ask for writes/deletes"** is a great default for most apps. It lets your agent gather information freely while keeping you in the loop for any changes.</Tip>
  </Tab>

  <Tab title="Per-Tool Custom">
    When you select **Custom** as the approval mode, you unlock per-tool controls. Open the app's detail view to see the **Tool Management** section.

    <Frame>
      <img src="https://mintcdn.com/agenthub/-bEDK0ObJtbSKcBz/images/human-in-the-loop/github-app-detail-view.png?fit=max&auto=format&n=-bEDK0ObJtbSKcBz&q=85&s=c174374ad0b0a10caeaec262d5b31b10" alt="GitHub app detail view showing Tool Management with Read-only tools and Write/delete tools groups" style={{ maxWidth: '420px' }} width="1232" height="1514" data-path="images/human-in-the-loop/github-app-detail-view.png" />
    </Frame>

    Tools are grouped by risk level: **Read-only tools** (fetch data) and **Write/delete tools** (modify or create data). Each group can be set to **Always allow** or **Custom**.

    When a group is set to Custom, you configure each individual tool:

    <Frame>
      <img src="https://mintcdn.com/agenthub/-bEDK0ObJtbSKcBz/images/human-in-the-loop/per-tool-approval-custom.png?fit=max&auto=format&n=-bEDK0ObJtbSKcBz&q=85&s=48bb7746fbf7e3d07d0476bdbba80837" alt="Per-tool approval settings showing individual tools with Always allow, Ask each time, and Never allow options" style={{ maxWidth: '420px' }} width="1226" height="1154" data-path="images/human-in-the-loop/per-tool-approval-custom.png" />
    </Frame>

    | Icon          | Mode              | What it does                 |
    | ------------- | ----------------- | ---------------------------- |
    | ✓ (checkmark) | **Always allow**  | Runs without asking.         |
    | ✋ (hand)      | **Ask each time** | Always requires approval.    |
    | 🚫 (block)    | **Never allow**   | Completely blocked from use. |

    For example, you might allow "Add Comment To Issue" freely, require approval for "Create Repository", and block "Delete Repository" entirely.
  </Tab>

  <Tab title="App Rules (CEL)">
    App Rules let you define **conditional** approval requirements using [CEL expressions](https://cel.dev/) that evaluate the actual arguments of each tool call. For example: "Require approval only when the email recipient is outside my company domain."

    You can create rules in two ways:

    <AccordionGroup>
      <Accordion title="Via the agent's chat" icon="message">
        Ask your agent to create a rule in natural language. The agent translates it into a CEL condition and presents it for your approval.

        <Frame>
          <img src="https://mintcdn.com/agenthub/-bEDK0ObJtbSKcBz/images/human-in-the-loop/app-rules-creation-chat.png?fit=max&auto=format&n=-bEDK0ObJtbSKcBz&q=85&s=f73bb0424c8c5c16f9d75295fe718d25" alt="Agent creating an approval rule for non-gumloop.com email recipients via chat, showing the CEL condition" style={{ maxWidth: '450px' }} width="1598" height="1068" data-path="images/human-in-the-loop/app-rules-creation-chat.png" />
        </Frame>
      </Accordion>

      <Accordion title="Via the app config UI" icon="gear">
        Open the app's detail view and use the **Rules** section to create rules manually.

        <Frame>
          <img src="https://mintcdn.com/agenthub/-bEDK0ObJtbSKcBz/images/human-in-the-loop/gmail-app-rules-config.png?fit=max&auto=format&n=-bEDK0ObJtbSKcBz&q=85&s=926918136874f45431009345dead007a" alt="Gmail app configuration showing the Rules section with an active 'Approve external email recipients' rule" style={{ maxWidth: '450px' }} width="1218" height="878" data-path="images/human-in-the-loop/gmail-app-rules-config.png" />
        </Frame>
      </Accordion>
    </AccordionGroup>

    <Info>App Rules require the **App Rules Creation** ability to be enabled on your agent if you want the agent to create rules via chat. You can always create rules manually through the config UI. For a deeper dive, see the [App Rules documentation](/enterprise-features/app-policies/app-rules).</Info>
  </Tab>

  <Tab title="Ask Question">
    The **Ask Question** ability is different from the approval settings above. Instead of gating tool calls, it lets your agent proactively ask you a question when it needs input to continue.

    <Frame>
      <img src="https://mintcdn.com/agenthub/lcH25nmDEudmdJ8l/images/human-in-the-loop/ask-human-ability.png?fit=max&auto=format&n=lcH25nmDEudmdJ8l&q=85&s=b88107003627c451a7c86007563b2abf" alt="Agent Abilities section showing the Ask Question toggle set to ON" style={{ maxWidth: '340px' }} width="782" height="648" data-path="images/human-in-the-loop/ask-human-ability.png" />
    </Frame>

    When enabled, the agent can present you with structured choice cards (toggle groups, multi-select options) to gather your preference before proceeding. Useful for decisions like "Which template should I use?" or "Which of these three options do you prefer?"

    Toggle this on in your agent's **Abilities** section.

    <Tip>**Ask Question** is great for open-ended tasks where the agent might need clarification. It lets the agent pause naturally and ask for your input rather than guessing.</Tip>
  </Tab>
</Tabs>

***

## Where You Get Notified

When an agent pauses for approval, you are notified in multiple places so you never miss it.

<Tabs>
  <Tab title="In the Chat">
    The approval card appears directly in the conversation with the tool name, intent, arguments, and **Approve** / **Reject** buttons. Use **⌘ + Enter** to quickly approve.

    <Frame>
      <img src="https://mintcdn.com/agenthub/-bEDK0ObJtbSKcBz/images/human-in-the-loop/approval-card-web-email.png?fit=max&auto=format&n=-bEDK0ObJtbSKcBz&q=85&s=406f8efe0b43aa7d623f50dbb9adc305" alt="Approval card in the web chat showing an email send request with To, Subject, and Body fields, plus Reject and Approve buttons" style={{ maxWidth: '450px' }} width="1664" height="1130" data-path="images/human-in-the-loop/approval-card-web-email.png" />
    </Frame>

    The card includes:

    * **Tool name and icon** at the top (e.g. "Approve Send Email" with the Gmail icon)
    * **Intent** describing what the agent is trying to do
    * **Display fields** showing the key arguments (e.g. To, Subject, Body)
    * **Reject** and **Approve** buttons
    * **"Don't ask again for this tool"** checkbox to remember your decision for future calls
  </Tab>

  <Tab title="Notification Bell">
    The in-app notification bell lights up with a badge count. Approve or reject directly from the inbox without opening the conversation.

    <Frame>
      <img src="https://mintcdn.com/agenthub/-bEDK0ObJtbSKcBz/images/human-in-the-loop/notification-bell-inbox.png?fit=max&auto=format&n=-bEDK0ObJtbSKcBz&q=85&s=6c78b9b329d68402a1c01577ac2366c1" alt="Notification bell inbox showing two pending approval requests from the Doc Updater agent with Approve buttons" style={{ maxWidth: '380px' }} width="866" height="1034" data-path="images/human-in-the-loop/notification-bell-inbox.png" />
    </Frame>

    Quickly approve or reject from here. The inbox also has a **Resolved** tab to review past decisions.
  </Tab>

  <Tab title="Tasks Page">
    The [Tasks page](https://www.gumloop.com/personal/tasks?status=approval_required) has an **Approval Required** filter showing every pending approval across all your agents in one place.

    <Frame>
      <img src="https://mintcdn.com/agenthub/-bEDK0ObJtbSKcBz/images/human-in-the-loop/tasks-approval-required.png?fit=max&auto=format&n=-bEDK0ObJtbSKcBz&q=85&s=f06f9a881221fa5fb6073365ca5fd7ed" alt="Tasks page filtered by Approval Required status, showing a pending email send task from the Brain agent" style={{ maxWidth: '550px' }} width="2438" height="724" data-path="images/human-in-the-loop/tasks-approval-required.png" />
    </Frame>

    A centralized queue for everything that needs your attention, especially useful when you have multiple agents running.
  </Tab>

  <Tab title="Slack">
    For Slack-connected agents, approval buttons appear directly in the Slack thread. You get **Approve**, **Reject**, and **Open in Gumloop** buttons right in Slack.

    <Frame>
      <img src="https://mintcdn.com/agenthub/-bEDK0ObJtbSKcBz/images/human-in-the-loop/approval-card-slack.png?fit=max&auto=format&n=-bEDK0ObJtbSKcBz&q=85&s=e0294a51ea98cc33018b323f4e560ea3" alt="Slack thread showing an approval request with Approve, Reject, and Open in Gumloop buttons" style={{ maxWidth: '380px' }} width="826" height="690" data-path="images/human-in-the-loop/approval-card-slack.png" />
    </Frame>

    If you are not watching the thread, you also receive a **Slack DM** so nothing gets missed. Click **Open in Gumloop** to see the full context in the web app.
  </Tab>
</Tabs>

***

## Example Walkthroughs

<AccordionGroup>
  <Accordion title="Approving a GitHub tool call" icon="code-branch" defaultOpen>
    <Steps>
      <Step title="Configure the app">
        Open your agent's config, find GitHub in the Apps list, and set the approval mode to "Ask each time" (or "Ask for writes/deletes" if you only want to gate mutations).
      </Step>

      <Step title="Ask your agent to do something">
        For example: *"Please create a PR on the docs repo to change the title of the agents skills doc page to just 'Skills'."*
      </Step>

      <Step title="The agent pauses for approval">
        Before executing the GitHub API call, it shows you an approval card with the details:

        <Frame>
          <img src="https://mintcdn.com/agenthub/-bEDK0ObJtbSKcBz/images/human-in-the-loop/approval-card-web-github.png?fit=max&auto=format&n=-bEDK0ObJtbSKcBz&q=85&s=ca2efdc8c21a0e7717a6daf86937e519" alt="Approval card for a GitHub tool call showing the intent, query, and Don't ask again checkbox with Reject and Approve buttons" style={{ maxWidth: '450px' }} width="1644" height="1016" data-path="images/human-in-the-loop/approval-card-web-github.png" />
        </Frame>
      </Step>

      <Step title="Review and decide">
        Check the arguments. If everything looks right, click **Approve**. If something is off, click **Reject** and optionally provide a reason.
      </Step>

      <Step title="The agent continues">
        After approval, the agent picks up right where it left off and completes the task.
      </Step>
    </Steps>
  </Accordion>

  <Accordion title="Creating a conditional rule via chat" icon="shield-check">
    <Steps>
      <Step title="Enable the App Rules Creation ability">
        Toggle it on in your agent's Abilities section.
      </Step>

      <Step title="Ask your agent to create a rule">
        For example: *"Create a human approval rule for any email that is sent to users without the gumloop.com domain."*
      </Step>

      <Step title="The agent builds the rule">
        It generates the appropriate CEL expression and shows you the rule details:

        <Frame>
          <img src="https://mintcdn.com/agenthub/-bEDK0ObJtbSKcBz/images/human-in-the-loop/app-rules-creation-chat.png?fit=max&auto=format&n=-bEDK0ObJtbSKcBz&q=85&s=f73bb0424c8c5c16f9d75295fe718d25" alt="Agent creating a Gmail approval rule with a CEL condition that checks To/CC/BCC recipients for non-gumloop.com domains" style={{ maxWidth: '450px' }} width="1598" height="1068" data-path="images/human-in-the-loop/app-rules-creation-chat.png" />
        </Frame>
      </Step>

      <Step title="Approve the rule">
        Once you approve, the rule is live. You can see it in the app's config:

        <Frame>
          <img src="https://mintcdn.com/agenthub/-bEDK0ObJtbSKcBz/images/human-in-the-loop/gmail-app-rules-config.png?fit=max&auto=format&n=-bEDK0ObJtbSKcBz&q=85&s=926918136874f45431009345dead007a" alt="Gmail app configuration showing the newly created approval rule for external email recipients" style={{ maxWidth: '450px' }} width="1218" height="878" data-path="images/human-in-the-loop/gmail-app-rules-config.png" />
        </Frame>
      </Step>

      <Step title="The rule takes effect immediately">
        Whenever the agent sends email to a non-gumloop.com address, it pauses for approval. Internal emails go through without interruption.

        <Frame>
          <img src="https://mintcdn.com/agenthub/T-M-W3m3rg3OSvXk/images/human-in-the-loop/rule-effect-approval-email.png?fit=max&auto=format&n=T-M-W3m3rg3OSvXk&q=85&s=02452dc76fd5937b799c65e82b4b9883" alt="Approval card showing the agent pausing to approve sending an email to an external recipient after the rule takes effect" style={{ maxWidth: '450px' }} width="1664" height="1130" data-path="images/human-in-the-loop/rule-effect-approval-email.png" />
        </Frame>
      </Step>
    </Steps>
  </Accordion>
</AccordionGroup>

***

## What Happens When You Reject

When you reject a tool call:

* The agent receives the rejection (and your optional reason).
* It does **not** execute the tool.
* It continues the conversation, often suggesting an alternative approach or asking for clarification.

Rejections are non-destructive. The agent simply adapts and tries a different path.

***

## Approval Modes at a Glance

| Mode                       | Scope             | Best for                                                  |
| -------------------------- | ----------------- | --------------------------------------------------------- |
| **Always allow**           | App-level         | Trusted, low-risk apps (e.g. read-only integrations)      |
| **Ask each time**          | App-level         | High-sensitivity apps where every action matters          |
| **Ask for writes/deletes** | App-level         | Most apps, letting reads flow freely and gating mutations |
| **Custom**                 | Per-tool          | Fine-grained control over individual tools within an app  |
| **App Rules**              | Conditional (CEL) | Context-dependent approvals based on tool arguments       |
| **Ask Question**           | Ability           | Agent-initiated questions for gathering user input        |

***

## FAQ

<AccordionGroup>
  <Accordion title="Does the agent time out while waiting for approval?">
    No. When an agent pauses for approval, it saves its full state. It can wait indefinitely for your response. When you approve or reject, it resumes exactly where it left off.
  </Accordion>

  <Accordion title="Can I approve from Slack without opening the web app?">
    Yes. If your agent is connected to Slack, approval buttons appear directly in the Slack thread and in a Slack DM. You can approve or reject right from Slack. You can also click "Open in Gumloop" to see the full context in the web app.
  </Accordion>

  <Accordion title="What does &#x22;Don't ask again for this tool&#x22; do?">
    When you check this box before approving, the agent remembers your decision for that specific tool. Future calls to the same tool will be auto-approved without asking. This preference is saved to the agent's configuration. You can always change it later in the app's Tool Management settings.
  </Accordion>

  <Accordion title="Can I set different approval requirements for different team members?">
    Approval settings are configured at the agent level and apply to all users interacting with that agent. For organization-wide policies that apply across all agents, use [App Rules](/enterprise-features/app-policies/app-rules) in your organization settings.
  </Accordion>

  <Accordion title="What's the difference between App Rules and Approval Settings?">
    **Approval Settings** are simple toggles: always allow, ask each time, ask for writes, or custom per-tool. They apply based on the tool's risk category (read vs. write).

    **App Rules** are conditional. They use CEL expressions to inspect the actual arguments of a tool call and decide whether to require approval. For example, "only require approval when the email recipient is outside my domain" is something only App Rules can do.

    Both can work together. Approval Settings act as the baseline, and App Rules add conditional overrides on top.
  </Accordion>

  <Accordion title="Where can I see all pending approvals across my agents?">
    The [Tasks page](https://www.gumloop.com/personal/tasks?status=approval_required) has an **Approval Required** filter that shows every pending approval across all your agents in one place. You can also use the notification bell in the top-right corner of the app for a quick glance.
  </Accordion>

  <Accordion title="What happens if I reject a tool call?">
    The agent does not execute the rejected tool. It acknowledges the rejection, and if you provided a reason, it takes that into account. The agent then continues the conversation, often adjusting its approach or asking for clarification.
  </Accordion>

  <Accordion title="Can my agent create App Rules on its own?">
    Yes, if you enable the **App Rules Creation** ability in your agent's Abilities section. The agent can translate natural-language instructions into CEL conditions. The rule creation itself also requires your approval before it takes effect.
  </Accordion>

  <Accordion title="Does Human in the Loop work with sandbox (code execution) tools?">
    Yes. When an agent needs to run code in the sandbox (Python or shell), the approval card shows a preview of the code it wants to execute. You can review the exact script before approving. If the code makes MCP tool calls (e.g., sending an email via Gmail), those nested calls are listed in the approval card as well.
  </Accordion>
</AccordionGroup>
