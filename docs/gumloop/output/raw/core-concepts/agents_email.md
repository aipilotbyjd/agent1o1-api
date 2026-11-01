> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Agent Email Inbox

> Give your agent a dedicated email address so anyone can interact with it by sending an email.

<CardGroup cols={2}>
  <Card title="Dedicated Inbox" icon="inbox">
    Each agent gets a unique `@gumloopagents.com` email address that routes messages straight to the agent.
  </Card>

  <Card title="Threaded Conversations" icon="comments">
    Email replies stay threaded. The agent maintains full conversation history across multiple email exchanges.
  </Card>

  <Card title="Works with Attachments" icon="file">
    Send files as email attachments and the agent can process them just like it would in chat.
  </Card>

  <Card title="Control Access Anytime" icon="toggle-on">
    Update the email alias or disable the inbox whenever the agent no longer needs it.
  </Card>
</CardGroup>

***

## Enabling the Email Inbox

<Steps>
  <Step title="Open the Email Inbox Page">
    Navigate to your agent, then click **Email Inbox** under **External Channels** in the sidebar.

    <Frame>
      <img src="https://mintcdn.com/agenthub/mnfow9PWbrLlj-Uu/images/email_inbox_sidebar.png?fit=max&auto=format&n=mnfow9PWbrLlj-Uu&q=85&s=6fd9426d255b2903b8a2633f1f97c8cc" alt="Email Inbox option under External Channels in the agent sidebar" style={{ maxHeight: '400px' }} width="504" height="466" data-path="images/email_inbox_sidebar.png" />
    </Frame>

    You will see a page describing the feature and its benefits.

    <Frame>
      <img src="https://mintcdn.com/agenthub/mnfow9PWbrLlj-Uu/images/email_inbox_setup.png?fit=max&auto=format&n=mnfow9PWbrLlj-Uu&q=85&s=aa4555912e5701c2e27cb9a2de977a74" alt="Email Inbox setup page with Enable Email Inbox button" width="1788" height="1136" data-path="images/email_inbox_setup.png" />
    </Frame>
  </Step>

  <Step title="Enable the Inbox">
    Click **Enable Email Inbox**. Gumloop automatically generates a unique email alias based on your agent's name. The alias follows the format:

    ```text theme={"dark"}
    your-agent-name@gumloopagents.com
    ```

    <Frame>
      <img src="https://mintcdn.com/agenthub/mnfow9PWbrLlj-Uu/images/email_inbox_enabled.png?fit=max&auto=format&n=mnfow9PWbrLlj-Uu&q=85&s=dede1778f99aa0da24d0aa9cef9b15d7" alt="Email Inbox enabled showing the generated email address with copy and edit buttons" width="1764" height="798" data-path="images/email_inbox_enabled.png" />
    </Frame>

    You can copy the address using the copy button, or click the pencil icon to customize the alias.
  </Step>

  <Step title="Start Sending Emails">
    Send an email to your agent's address from any email client. The agent processes the message and replies directly to the sender's inbox.

    <Frame>
      <img src="https://mintcdn.com/agenthub/mnfow9PWbrLlj-Uu/images/email_inbox_chat.png?fit=max&auto=format&n=mnfow9PWbrLlj-Uu&q=85&s=2796f6a49ad4149058647afcd769a732" alt="Email conversation showing a user message and the agent's response inside the Gumloop chat interface" width="1594" height="914" data-path="images/email_inbox_chat.png" />
    </Frame>

    All email interactions also appear in the agent's chat history inside Gumloop, so you can review conversations from either place.
  </Step>
</Steps>

***

## Customizing the Email Alias

You can change the email alias at any time:

1. Open the **Email Inbox** page for your agent
2. Click the **pencil icon** next to the current address
3. Type your preferred alias and press **Enter** (or click the checkmark)

**Alias rules:**

* 3 to 64 characters
* Alphanumeric characters and hyphens only
* Must start and end with a letter or number

When you change the alias, the old address stops working immediately and the new address becomes active. Any emails sent to the old alias after the change will not be delivered.

<Warning>Changing the alias means anyone using the old address will need to update it. Share the new address with your team after making changes.</Warning>

***

## How It Works Behind the Scenes

When someone sends an email to your agent's address, here is what happens:

1. **Email received**: The inbound email arrives at `gumloopagents.com` and is routed to Gumloop's email processing service.
2. **Agent lookup**: Gumloop looks up which agent is registered to the email alias.
3. **Sender verification**: Gumloop verifies the message's DKIM signature against the sender's domain and matches the sender's email to a Gumloop account. The sender must have a Gumloop account, and their domain must have DKIM configured. Messages without a valid DKIM signature are rejected. See [Sender Domain Authentication (DKIM)](#sender-domain-authentication-dkim) for setup details.
4. **Permission check**: Gumloop verifies that the sender has permission to use the agent (same access control as the chat interface).
5. **Thread resolution**: If the email is a reply, Gumloop matches it to an existing conversation using email headers (`References`, `In-Reply-To`). New emails start a fresh conversation.
6. **Attachment processing**: Any file attachments are decoded and stored securely, then passed to the agent as file inputs.
7. **Agent execution**: The agent processes the message using the same AI engine as the chat interface, with access to all configured tools, integrations, and workflows.
8. **Reply sent**: The agent's response is formatted as an HTML email and sent back to the sender (and any CC'd recipients). File outputs from the agent are included as attachments.
9. **Conversation persisted**: The full exchange is saved to the agent's interaction history, visible in both the Gumloop UI and future email threads.

<Info>The email channel uses the same AI agent engine, tools, and credentials as the chat interface. There is no difference in agent capabilities between email and chat.</Info>

***

## Sender Domain Authentication (DKIM)

Gumloop verifies the **DKIM signature** of every inbound email so it can confirm that the sender's domain actually authorized the message. Without this check, anyone could spoof an email from a teammate's address and gain access to that user's agents and credentials.

If the sender's domain does not have DKIM configured, the email is rejected. The sender usually receives a generic bounce from their mail provider that looks like an "Address not found" or "domain couldn't be found" error, even though the real cause is missing DKIM on their own outbound mail.

### When This Affects You

* **Personal accounts** on Gmail, Outlook, iCloud, and similar consumer providers already have DKIM enabled by default. No action is needed.
* **Workspace and corporate domains** (custom domains hosted on Google Workspace, Microsoft 365, or another provider) need DKIM enabled by an administrator before they can email a Gumloop agent.

### Setting Up DKIM on Your Domain

Ask your IT administrator to enable DKIM signing for the sending domain. Both major providers have step-by-step guides:

<CardGroup cols={2}>
  <Card title="Google Workspace" icon="google" href="https://knowledge.workspace.google.com/admin/security/set-up-dkim">
    Generate a DKIM key in the Admin console, add the provided TXT record to DNS, then turn on signing.
  </Card>

  <Card title="Microsoft 365 / Outlook" icon="microsoft" href="https://learn.microsoft.com/en-us/defender-office-365/email-authentication-dkim-configure">
    Add the two CNAME records for your custom domain in DNS, then enable DKIM in the Microsoft Defender portal.
  </Card>
</CardGroup>

DNS changes can take up to 48 hours to propagate. Once DKIM is active and outbound mail is signed, emails to your agent will go through.

<Info>If you administer your own mail server, configure DKIM using your provider's documentation and confirm a `DKIM-Signature` header is present on outbound messages. Gumloop accepts any valid DKIM signature where the signing domain (`d=`) matches the sender's domain.</Info>

### Confirming DKIM is the Cause of a Bounce

If an email to your agent bounced and you're not sure why, open the bounce message and view the original headers:

* **Gmail**: open the message, click the three-dot menu, then **Show original**.
* **Outlook**: open the message, click **File > Properties** (desktop) or the three-dot menu and **View message source** (web).

Look at the `Authentication-Results` header. A line like `dkim=none` or `dkim=fail` (or no DKIM line at all) confirms the sender's domain is not signing outbound mail.

***

## Threading and Conversation History

The email inbox supports full conversation threading:

* **New emails** start a new conversation with the agent.
* **Replies** (using your email client's reply button) continue the existing conversation. The agent has access to the full thread history.
* **CC recipients** are preserved. When the agent replies, it CCs everyone who was on the original email.
* **Subject lines** are included as context for new conversations, helping the agent understand the topic.

The agent uses standard email headers (`References` and `In-Reply-To`) to track threads, so threading works correctly across Gmail, Outlook, Apple Mail, and other email clients.

***

## Credentials and Authentication

Email interactions use the same credential model as the chat interface:

* The agent uses the **sender's personal credentials** for any integrations (Gmail, Salesforce, etc.)
* If the sender has not authenticated with a required service, the agent will notify them in the reply
* Team credential settings (personal vs. team default) apply the same way as in chat

<Info>The sender's email address must match their Gumloop account email. If someone sends an email from an address not associated with a Gumloop account, they will receive an error reply asking them to sign up.</Info>

***

## Concurrency and Rate Limits

Email agent interactions follow the same concurrency limits as other agent channels:

* If you have too many agent interactions running simultaneously, the email will receive a reply explaining that the request was rate-limited
* **Enterprise users** benefit from automatic queuing: instead of being rejected, their messages are queued and processed in order. The sender receives a notification with their queue position.
* If a message takes too long to process (exceeds the time limit), the sender receives an error reply

***

## Disabling the Email Inbox

To turn off the email inbox:

1. Open the **Email Inbox** page for your agent
2. Click the red **Disable** button

Once disabled, any emails sent to the agent's address will no longer be delivered. You can re-enable the inbox at any time.

***

## Permissions

Managing the email inbox requires **Editor access** to the agent. Viewers and "Use Only" users cannot enable, disable, or change the email alias.

Organization admins can also restrict email inbox access using **policy controls**. If email inboxes have been restricted by your admin, you will see a message indicating this on the setup page.

***

## Troubleshooting

<AccordionGroup>
  <Accordion title="My email bounced with 'Address not found' or 'domain couldn't be found'" icon="circle-exclamation">
    This almost always means the sender's domain does not have DKIM signing enabled. The bounce message from your mail provider can be misleading: `gumloopagents.com` exists and is reachable, but Gumloop rejected the message because the DKIM signature could not be verified.

    **Fix**: Ask your IT administrator to enable DKIM on the sender's domain. See [Sender Domain Authentication (DKIM)](#sender-domain-authentication-dkim) for the Google Workspace and Microsoft 365 setup guides.

    To confirm this is the cause, open the bounce email, view the original headers, and look for `dkim=` in the `Authentication-Results` header. A `dkim=none` or missing DKIM signature confirms the issue.
  </Accordion>

  <Accordion title="The sender received a 'sign up for Gumloop' reply" icon="user-plus">
    The sender's email address must match an existing Gumloop account. Either invite them to your organization or have them sign up at [gumloop.com](https://gumloop.com) using the same email address they're sending from.
  </Accordion>

  <Accordion title="The agent didn't reply to my email" icon="message">
    Check the agent's chat history in Gumloop. If the email made it through, the conversation will appear there. If not, the email was rejected before it reached the agent. Common causes:

    * DKIM is not configured on the sender's domain (see above).
    * The sender's email does not match a Gumloop account.
    * The sender does not have permission to use the agent.
    * The email inbox has been disabled or restricted by an admin policy.
  </Accordion>
</AccordionGroup>

***

## FAQ

<AccordionGroup>
  <Accordion title="Who can send emails to the agent?">
    Anyone with a Gumloop account and read access to the agent can send emails to it. The sender's email address must match the email on their Gumloop account, and the sender's domain must have DKIM configured.
  </Accordion>

  <Accordion title="Can I use my own custom domain?">
    Not currently. All agent email addresses use the `@gumloopagents.com` domain. Custom domain support may be added in the future.
  </Accordion>

  <Accordion title="What happens if I send an email from an address not linked to Gumloop?">
    You will receive an error reply asking you to sign up for a Gumloop account at [gumloop.com](https://gumloop.com).
  </Accordion>

  <Accordion title="Are email attachments supported?">
    Yes. File attachments are processed and made available to the agent. The agent can also send files back as attachments in its reply.
  </Accordion>

  <Accordion title="How does CC work?">
    If you CC other people on your email to the agent, the agent's reply will also CC those recipients. This makes it easy to keep team members in the loop.
  </Accordion>

  <Accordion title="Can the agent send emails proactively?">
    No. The email inbox is inbound-only. The agent only replies to emails it receives. It cannot initiate email conversations on its own. If you need outbound email capabilities, use the Gmail or email MCP integrations as agent tools.
  </Accordion>

  <Accordion title="What email format does the agent reply in?">
    The agent replies in HTML format with clean formatting, including support for headings, lists, code blocks, and tables. The reply also includes a plain-text fallback for email clients that do not render HTML.
  </Accordion>

  <Accordion title="Can I have the same agent on both Slack and email?">
    Yes. An agent can be connected to Slack, email, and the Gumloop chat interface simultaneously. Each channel maintains its own conversation threads.
  </Accordion>

  <Accordion title="What happens to old threads if I change the alias?">
    Existing conversation threads are preserved in the agent's history. However, replies to those threads using the old email address will no longer be delivered. You would need to start a new email thread using the new address.
  </Accordion>

  <Accordion title="Is there a limit on email size or attachment count?">
    Standard email size limits apply. Very large attachments may fail to process. For best results, keep individual attachments under 25 MB.
  </Accordion>

  <Accordion title="Why does my agent need DKIM on my domain?">
    Gumloop uses DKIM signature verification to confirm that emails really came from the sender's domain. Without it, anyone could spoof a teammate's address and access their agents and credentials. Personal Gmail and Outlook accounts already have DKIM. Custom workspace domains need an admin to enable it. See [Sender Domain Authentication (DKIM)](#sender-domain-authentication-dkim).
  </Accordion>
</AccordionGroup>
