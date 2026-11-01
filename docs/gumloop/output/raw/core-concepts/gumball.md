> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Gumball

> Your personal Gumloop agent — chat, a labeled inbox, a morning report, and a brief before every meeting.

Gumball is the personal agent that comes with every Gumloop account. Unlike [custom agents](/core-concepts/agents), which you build and share with your team, Gumball belongs to you: it is created automatically the first time you open it, it can search every [skill](/core-concepts/skills) you can access — including your teams' skills — and it uses your own [connectors](/core-concepts/credentials) to read and act in the apps you've connected.

<Frame>
  <img src="https://mintcdn.com/agenthub/mbx0_tJwKo9HZ-Bd/images/gumball/gumball_home.png?fit=max&auto=format&n=mbx0_tJwKo9HZ-Bd&q=85&s=5f6678cf2da10855018074b9ab83c37c" alt="Gumball home screen showing the chat box, the Daily Chew tab, and the right rail with Accounts, Triggers, and Personalization" width="2460" height="1386" data-path="images/gumball/gumball_home.png" />
</Frame>

On top of chat, Gumball runs three proactive features that work while you're away:

<CardGroup cols={3}>
  <Card title="Daily Chew" icon="sun">
    A short report from your connected apps, delivered on your schedule.
  </Card>

  <Card title="Smart Inbox" icon="inbox">
    Labels new email as it arrives in Gmail or Outlook, moves the noise out of your Inbox, and can draft replies for you.
  </Card>

  <Card title="Meeting Prep" icon="calendar-check">
    A context brief before each meeting on your calendar.
  </Card>
</CardGroup>

<Info>All three are **owner-only**. Gumball is a personal agent, so only you can configure or view its Smart Inbox, Daily Chew, and Meeting Prep — they can't be shared like a custom agent.</Info>

***

## What Gumball Comes With

The right-hand rail on the Gumball home screen is the control panel for everything below.

| Section             | What it does                                                                                                             |
| ------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| **Email**           | Gumball's own address, `gumball@gumloopagents.com`. Email it like a coworker and it replies.                             |
| **Inbox**           | Connect a Gmail or Outlook mailbox for [Smart Inbox](#smart-inbox).                                                      |
| **Calendar**        | Connect a Google Calendar or Outlook Calendar account for [Meeting Prep](#meeting-prep).                                 |
| **Slack**           | Opens your DM with the Gumloop Slack app, where you can chat with Gumball. See [Gumball in Slack](#gumball-in-slack).    |
| **Connectors**      | Every app Gumball can read and act in.                                                                                   |
| **Triggers**        | Schedules and event triggers that run Gumball automatically.                                                             |
| **Personalization** | The **Tone** and **Design** skills Gumball curates about how you like to be written to and how you like output laid out. |

Below the chat box are tabs for **Daily Chew**, **Smart Inbox**, **Tasks**, and **Artifacts** — the reports Gumball has produced, work it has suggested, and the [files](/core-concepts/agent_artifacts) it has created.

### Emailing Gumball

Gumball's email address is reserved and fixed — you can't rename it or hand it to another agent. Send it a request and it replies in the same thread using your connectors.

<Frame>
  <img src="https://mintcdn.com/agenthub/mbx0_tJwKo9HZ-Bd/images/gumball/gumball_email_thread.png?fit=max&auto=format&n=mbx0_tJwKo9HZ-Bd&q=85&s=b3923c510d8021594b4dccdac3aa6682" alt="Gmail thread where a user emails gumball and Gumball replies with a summary of assigned Linear tickets" width="2198" height="762" data-path="images/gumball/gumball_email_thread.png" />
</Frame>

<Tip>Gumball follows the same rules as any [agent email inbox](/core-concepts/agents_email) — including DKIM checks on the sending domain.</Tip>

### Gumball in Slack

You can talk to Gumball by **direct messaging the Gumloop Slack app** in your workspace. There is nothing to set up and no channel to create — find the Gumloop app in your Slack sidebar (or under **Apps**) and send it a message.

<Frame>
  <img src="https://mintcdn.com/agenthub/eDEF6m1dDuAVBqSB/images/gumball/gumball_slack_dm.png?fit=max&auto=format&n=eDEF6m1dDuAVBqSB&q=85&s=4b253b86c2a230c26490bb92b4c97318" alt="Slack DM with the Gumloop app where a user asks to fetch their latest emails and Gumball replies with progress" width="2036" height="588" data-path="images/gumball/gumball_slack_dm.png" />
</Frame>

The **Slack** row in the right rail links straight to that DM once you have connected a Slack account; click **Open**.

|                         | How it works in a DM                                                                                                                                                                             |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Which agent answers** | Always your own Gumball. [Custom agents](/core-concepts/agents) live in channels — DMs never reach them.                                                                                         |
| **Who you are**         | Gumball matches your Slack email to your Gumloop account, and runs on your connectors and skills, exactly like the web app. Without a matching Gumloop account you get a sign-up prompt instead. |
| **Conversations**       | Each top-level message you send starts a new conversation; reply in that message's thread to continue it.                                                                                        |
| **Everything else**     | Same Gumball — your skills, connectors, artifacts, and history are shared with the web app, and thread commands like `!stop` and `!link` work.                                                   |

<Info>Gumball DMs use your workspace's existing Gumloop Slack app. If it was installed before DMs were supported, Gumball replies asking a workspace admin to reconnect the Gumloop Slack app — reconnecting it from the [Connectors page](https://www.gumloop.com/personal/connectors?provider=slack) grants the extra direct-message permissions and nothing else changes.</Info>

<Tip>Want a DM with a **custom** agent and its own name and avatar? Use a [Custom Slack App](/core-concepts/custom_slack_app#direct-messages-dms).</Tip>

***

## Daily Chew

The Daily Chew is a short report about what happened across your connected apps, on the schedule you pick.

<Frame>
  <img src="https://mintcdn.com/agenthub/mbx0_tJwKo9HZ-Bd/images/gumball/daily_chew_setup.png?fit=max&auto=format&n=mbx0_tJwKo9HZ-Bd&q=85&s=e597196f5e57e7237df49034276d20ff" alt="Build your Daily Chew dialog with a connector search and an Enable Daily Chew button" style={{ maxWidth: '340px' }} width="1044" height="1566" data-path="images/gumball/daily_chew_setup.png" />
</Frame>

### Configuring It

<Frame>
  <img src="https://mintcdn.com/agenthub/mbx0_tJwKo9HZ-Bd/images/gumball/daily_chew_settings.png?fit=max&auto=format&n=mbx0_tJwKo9HZ-Bd&q=85&s=ba7f77590bdc33c6aa0e851431785c13" alt="Daily Chew settings with Time, Days, Connectors, Instructions, Model, and Delivery toggles" style={{ maxWidth: '340px' }} width="998" height="1690" data-path="images/gumball/daily_chew_settings.png" />
</Frame>

| Setting               | Notes                                                                                                                       |
| --------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| **Time** and **Days** | When the report runs, in your timezone. Both are required the first time you set it up.                                     |
| **Connectors**        | **All connectors** uses everything you have connected at run time. **Selected connectors** pins a specific list (up to 50). |
| **Instructions**      | What you want emphasised, up to 4,000 characters.                                                                           |
| **Model**             | Defaults to Gumball's model.                                                                                                |
| **Delivery**          | Reports always appear in Gumball. **Email** and **Slack DM** are optional extras.                                           |

<Tip>Leave **All connectors** selected if you keep adding tools — the report picks up new connectors automatically instead of needing to be re-configured.</Tip>

### What a Report Looks Like

Each report has a one-glance summary (two sentences, 40 words max) plus a full document made of up to four sections, in this order — sections with nothing to say are left out entirely:

`## Catch-up` · `## Priorities` · `## Schedule` · `## Next steps`

Facts in the document are attributed back to the app they came from. Routine calendar entries stay in the document rather than the summary, since your schedule is already on screen next to it.

### How It Behaves

* **Runs are read-only.** The Daily Chew can only use tools that read; anything that could write, send, or change something is blocked, including tools reached through another tool.
* **Sources fail independently.** If Slack is down, the rest of the report still gets written and Slack is simply marked as failed for that run.
* **A failed source doesn't lose your place.** Gumball only advances its "last read" marker for sources that actually succeeded, so the next run picks up what it missed.
* **First run looks back 16 hours.** After that, each run covers everything since the previous successful one.
* **Reports are never sent twice.** If a run is retried internally, you get one report, not two.
* **Pausing** stops it from running; **resuming** puts it back on schedule. Past reports stay in your history either way.

### Suggested Actions

Reports can end with suggested actions — but only for real work you own with a concrete outcome Gumball could take on. Someone mentioning you in a channel isn't a suggested action; a customer waiting on a document you promised is. Accept one and it opens as a task in chat; dismiss it and it goes away.

***

## Smart Inbox

Smart Inbox labels **new** email as it lands in your mailbox, using labels you define in plain English. It's not a separate inbox — everything happens as native tags in your real mailbox (Gmail labels, Outlook categories), so it works in the Gmail or Outlook app, on mobile, and in any client you already use.

<Info>Smart Inbox supports **Gmail** and **Microsoft Outlook**, and requires a **Pro** plan or above. Gumball watches **one mailbox at a time** — connecting a second one replaces the first.</Info>

### Turning It On

<Steps>
  <Step title="Open the Smart Inbox tab">
    From the Gumball home screen, open the **Smart Inbox** tab and click **Enable Smart Inbox**.

    <Frame>
      <img src="https://mintcdn.com/agenthub/mbx0_tJwKo9HZ-Bd/images/gumball/smart_inbox_tab.png?fit=max&auto=format&n=mbx0_tJwKo9HZ-Bd&q=85&s=482140ee419c01e2304156b087265ee3" alt="Smart Inbox tab showing the Connect your inbox empty state with an Enable Smart Inbox button" style={{ maxWidth: '620px' }} width="1704" height="966" data-path="images/gumball/smart_inbox_tab.png" />
    </Frame>
  </Step>

  <Step title="Pick the mailbox">
    Choose the connected **Gmail** or **Outlook** account Gumball should watch, then confirm.

    <Frame>
      <img src="https://mintcdn.com/agenthub/mbx0_tJwKo9HZ-Bd/images/gumball/smart_inbox_connect.png?fit=max&auto=format&n=mbx0_tJwKo9HZ-Bd&q=85&s=c5c5b793a3a37946cb9257084a88b777" alt="Connect your inbox dialog with a mailbox account selector and an Enable Smart Inbox button" style={{ maxWidth: '340px' }} width="1114" height="1612" data-path="images/gumball/smart_inbox_connect.png" />
    </Frame>

    Gumball seeds five built-in labels and starts classifying the next email that arrives.
  </Step>
</Steps>

<Warning>Enabling requires permission to **read email** and **modify messages** — on Gmail it also needs to list and create labels. Two capabilities are optional, and if your organization's [app rules](/enterprise-features/app-policies/app-rules) block them, labeling keeps working without them: **creating drafts** (you get reply suggestions in Gumball but no native draft) and, on Outlook, **archiving** (labels still apply, but mail stays in your Inbox).</Warning>

### Gmail vs. Outlook

Both mailboxes get the same classification, labels, and reply drafts. Three things behave differently because the providers themselves do.

|                                  | Gmail                                                                          | Outlook                                                                                                                 |
| -------------------------------- | ------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------- |
| **Native tag**                   | A Gmail label.                                                                 | An Outlook category, registered so it shows up in the **Categorize** menu with its colour.                              |
| **Renaming a label**             | The Gmail label is renamed too, so mail you already have follows the new name. | Outlook can't rename a category, so the new name applies to **future** mail only and older mail keeps the old category. |
| **Moving mail out of the Inbox** | Always available.                                                              | Needs the archive permission. Without it, labels apply and mail stays in your Inbox.                                    |

### Labels

You start with five built-in labels, and you can add your own up to a total of **25**.

| Built-in label     | Applied when                             |
| ------------------ | ---------------------------------------- |
| **Needs reply**    | You should reply.                        |
| **Time sensitive** | There's an explicit deadline or urgency. |
| **Waiting on you** | Progress requires your action.           |
| **FYI**            | Useful information, no action needed.    |
| **Low priority**   | Non-urgent, limited immediate value.     |

A label is just a name, a definition, and a colour. The definition is the instruction Gumball classifies against — write it the way you'd explain it to a new assistant.

<Frame>
  <img src="https://mintcdn.com/agenthub/mbx0_tJwKo9HZ-Bd/images/gumball/smart_inbox_new_label.png?fit=max&auto=format&n=mbx0_tJwKo9HZ-Bd&q=85&s=a0afa832c18d2f85a5d084f816a61ef6" alt="New label form with Name, Definition, and a colour picker" style={{ maxWidth: '340px' }} width="1228" height="1736" data-path="images/gumball/smart_inbox_new_label.png" />
</Frame>

### Keep In Inbox vs. Move Out

Every label sits in one of two groups, and the group decides where matching mail ends up.

<Frame>
  <img src="https://mintcdn.com/agenthub/mbx0_tJwKo9HZ-Bd/images/gumball/smart_inbox_labels_overview.png?fit=max&auto=format&n=mbx0_tJwKo9HZ-Bd&q=85&s=3178280ebfaf4121150b3c373480ab49" alt="Labels card with a Move these out of my Inbox column and a Keep these in my Inbox column" style={{ maxWidth: '620px' }} width="1762" height="916" data-path="images/gumball/smart_inbox_labels_overview.png" />
</Frame>

<Warning>**Keep wins.** A message leaves your Inbox only when *every* label Gumball applied to it is in the "Move these out" group. One "Keep" label anywhere on the message keeps it in your Inbox.</Warning>

The **Existing labels** toggle in **Manage** controls whether Gumball skips mail you've already organised: when it's on, any message that already carries one of your own labels or categories is left alone. Labels Gumball manages don't count for this.

<Frame>
  <img src="https://mintcdn.com/agenthub/mbx0_tJwKo9HZ-Bd/images/gumball/smart_inbox_labels_manage.png?fit=max&auto=format&n=mbx0_tJwKo9HZ-Bd&q=85&s=7d38bb9f2b724bd633be0363c57924a4" alt="Labels management panel showing the Existing labels toggle and the two label groups" style={{ maxWidth: '380px' }} width="1236" height="1226" data-path="images/gumball/smart_inbox_labels_manage.png" />
</Frame>

<Info>A message can receive at most **5** labels. Labels Gumball isn't confident about are dropped rather than guessed.</Info>

### Classification Happens Once

Gumball classifies each email **as it arrives**, and never re-runs on old mail.

* Editing a label's definition, colour, or group changes **future** email only.
* Turning a label off stops it being applied going forward; mail already labelled keeps its label.
* Deleting a custom label removes it from Gumball; the native label or category stays on the messages that already have it.
* Renaming a label renames the matching Gmail label, so your existing mail follows the new name. On Outlook the new name is used going forward and older mail keeps the old category.
* The five built-in labels can be disabled or edited, but not deleted.
* A label that shares a name with one of your own labels or categories is adopted rather than duplicated, and Gumball starts managing it.

### Reply Drafts

When Gumball decides a message needs a reply, it can write one straight into your mailbox as a native draft — so you open Gmail or Outlook, read it, edit it, and hit send.

<Frame>
  <img src="https://mintcdn.com/agenthub/mbx0_tJwKo9HZ-Bd/images/gumball/smart_inbox_settings.png?fit=max&auto=format&n=mbx0_tJwKo9HZ-Bd&q=85&s=54c2a3c5e33f564e72e0300c90544e8d" alt="Smart Inbox settings with Inbox account, Classification model, Drafting instructions, and Draft replies options" style={{ maxWidth: '340px' }} width="1146" height="1706" data-path="images/gumball/smart_inbox_settings.png" />
</Frame>

| Setting                   | What it controls                                                                                      |
| ------------------------- | ----------------------------------------------------------------------------------------------------- |
| **Inbox account**         | Which Gmail or Outlook mailbox Smart Inbox watches.                                                   |
| **Classification model**  | The model used to label email. Defaults to Gumball's own model.                                       |
| **Drafting instructions** | Standing instructions applied to every reply Gumball drafts. These outrank your Tone personalization. |
| **Draft replies**         | Whether a native draft is created in your mailbox at all.                                             |

**Draft replies** is an on/off choice, not a scale:

| Option                       | What happens                                                                                                                                     |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| **When confident** (default) | Gumball writes a native draft whenever it's confident in the reply it produced. Replies it isn't confident about stay as suggestions in Gumball. |
| **Off**                      | No drafts are created in your mailbox. Gumball skips reply generation altogether, so it doesn't cost you credits.                                |

Drafts are written from the thread itself plus up to three of your past replies to that sender, so they sound like you. When no draft is created, **the reply suggestion still appears in Gumball** — you just don't get a native draft, and you can accept the suggestion into chat instead.

<Note>If you configured this back when it offered several confidence levels, it now reads as **When confident**. Only an explicit **Off** is carried over.</Note>

<Warning>Gumball never overwrites a draft you've touched. If a newer message arrives in a thread where you edited Gumball's draft, it skips drafting entirely rather than replacing your text.</Warning>

<Info>Gumball only ever creates **drafts**. Smart Inbox cannot send email on your behalf.</Info>

### Disconnecting

Turning Smart Inbox off, or switching to a different mailbox, clears Gumball's record of what it labelled and its link to the native labels or categories. Those stay in your mailbox on the mail that already has them, and Gumball re-adopts them by name if you reconnect.

***

## Meeting Prep

Meeting Prep gives you a brief before a meeting starts: who's attending, what you've discussed with them before, what's still open, and what to raise.

### Turning It On

<Steps>
  <Step title="Connect a calendar">
    Pick the **Google Calendar** or **Outlook Calendar** account Gumball should watch, then click **Enable Meeting Prep**.

    <Frame>
      <img src="https://mintcdn.com/agenthub/mbx0_tJwKo9HZ-Bd/images/gumball/meeting_prep_setup.png?fit=max&auto=format&n=mbx0_tJwKo9HZ-Bd&q=85&s=62ed046ba48853309e1dd0474a7b64f5" alt="Prep every meeting dialog with a calendar account selector and an Enable Meeting Prep button" style={{ maxWidth: '340px' }} width="986" height="1594" data-path="images/gumball/meeting_prep_setup.png" />
    </Frame>
  </Step>

  <Step title="Choose what gets prepped">
    <Frame>
      <img src="https://mintcdn.com/agenthub/mbx0_tJwKo9HZ-Bd/images/gumball/meeting_prep_settings.png?fit=max&auto=format&n=mbx0_tJwKo9HZ-Bd&q=85&s=76b4983408238b68e1c7ea1a89db163d" alt="Meeting Prep settings with calendar account, automatic prep toggle, trigger mode, minutes before event, meeting scope, connectors, instructions, model, and delivery" style={{ maxWidth: '340px' }} width="548" height="1094" data-path="images/gumball/meeting_prep_settings.png" />
    </Frame>
  </Step>
</Steps>

| Setting                         | Notes                                                                                 |
| ------------------------------- | ------------------------------------------------------------------------------------- |
| **Prep meetings automatically** | When off, meetings still appear and you prep them one at a time with **Prepare now**. |
| **Calendar account**            | The connected Google Calendar or Outlook Calendar account.                            |
| **Calendar**                    | Which calendar on that account to watch.                                              |
| **Minutes Before Event**        | How far ahead of the meeting the brief is written.                                    |
| **Meetings to prep**            | **All meetings**, or **External meetings only**.                                      |
| **Connectors**                  | All connected apps, or a specific list.                                               |
| **Instructions**                | What research you want done and how you want it framed.                               |
| **Delivery**                    | In Gumball always; **Email** and **Slack DM** optional.                               |

<Info>**External meetings only** is the default. A meeting counts as external when at least one guest — other than you — has an email domain different from yours. Solo events and all-internal meetings are skipped.</Info>

### Prepping a Meeting Yourself

Upcoming meetings are listed on the Gumball home screen whether or not automatic prep is on.

<Frame>
  <img src="https://mintcdn.com/agenthub/mbx0_tJwKo9HZ-Bd/images/gumball/meeting_prep_upcoming.png?fit=max&auto=format&n=mbx0_tJwKo9HZ-Bd&q=85&s=339101328330c3900383629fc216d9ea" alt="Upcoming meetings list on the Gumball home screen" style={{ maxWidth: '420px' }} width="878" height="590" data-path="images/gumball/meeting_prep_upcoming.png" />
</Frame>

Open one and click **Prepare now** to generate a brief on demand.

<Frame>
  <img src="https://mintcdn.com/agenthub/mbx0_tJwKo9HZ-Bd/images/gumball/meeting_prep_prepare_now.png?fit=max&auto=format&n=mbx0_tJwKo9HZ-Bd&q=85&s=8bd118ebaecc99b4aba1f016d09a6aaa" alt="Meeting detail dialog showing time, location, guests, and Join meeting and Prepare now buttons" style={{ maxWidth: '560px' }} width="1252" height="798" data-path="images/gumball/meeting_prep_prepare_now.png" />
</Frame>

<Tip>**Prepare now** works even when automatic prep is turned off — turning it off just means Gumball won't fire on its own.</Tip>

### What a Brief Looks Like

A short summary (two to three sentences, 60 words max) plus a document with up to four sections, in this order, omitting any that are empty:

`## Attendees` · `## Prior context` · `## Open questions` · `## Talking points`

**Prior context** opens with a recap of your history with these people, then the relevant threads, decisions, and risks across every connected app — email, Slack, meeting notes, CRM activity — woven together rather than listed per tool. The meeting's time, location, and guest list aren't restated, since they're already on the card next to the brief.

Meeting Prep runs are read-only and handle source failures exactly like the Daily Chew.

<Info>Google Calendar and Outlook Calendar behave the same way here: same settings, same automatic and **Prepare now** paths, and the same brief.</Info>

***

## Availability and Permissions

| Feature          | Requirement                                                                               |
| ---------------- | ----------------------------------------------------------------------------------------- |
| **Gumball chat** | Included with every account. Gated by the `access_general_agent` organization permission. |
| **Smart Inbox**  | **Pro** plan or above, plus a connected Gmail or Outlook mailbox.                         |
| **Daily Chew**   | Any plan, with at least one connector.                                                    |
| **Meeting Prep** | Any plan, with a connected Google Calendar or Outlook Calendar account.                   |

Organization admins control access through [user roles](/core-concepts/organization_user_roles). Smart Inbox, Daily Chew, and Meeting Prep each have their own permission that cascades from Gumball access — turning off Gumball access for a role turns off all three.

<Warning>For **custom roles**, Smart Inbox, Daily Chew, and Meeting Prep are **restricted by default**. An admin has to grant them explicitly.</Warning>

If your access is removed while Smart Inbox is running, Gumball turns it off. If the restriction is later lifted, you need to re-enable it yourself — it doesn't resume on its own.

***

## FAQ

<AccordionGroup>
  <Accordion title="What's the difference between Gumball and a custom agent?" icon="robot">
    Gumball is personal and automatic — one per user, created for you, and it searches every skill you can access, including your teams' skills, instead of using a fixed list of attached skills. Custom agents are built by you, shared with your team, and only see the skills, tools, and instructions you configure on them. Gumball is also the only agent with Smart Inbox, Daily Chew, and Meeting Prep.
  </Accordion>

  <Accordion title="Can I share Gumball, or see a teammate's?" icon="users">
    No. Gumball is scoped to its owner. Everyone gets their own, and no one else can view or configure it. If you want a shared assistant, build a [custom agent](/core-concepts/agents).
  </Accordion>

  <Accordion title="How do I chat with Gumball in Slack?" icon="slack">
    DM the Gumloop app in your Slack workspace — find it in your sidebar or under **Apps** and start typing. No setup, no channel, no `@mention`. The **Slack** row on the Gumball home screen opens that DM for you.
  </Accordion>

  <Accordion title="Can I DM a custom agent instead of Gumball?" icon="comments">
    Not through the standard Gumloop app — DMs there always go to your own Gumball, and custom agents are added to channels. To DM a custom agent, connect a [Custom Slack App](/core-concepts/custom_slack_app#direct-messages-dms) for it.
  </Accordion>

  <Accordion title="Gumball says it needs updated Slack permissions. What now?" icon="key">
    Your workspace's Gumloop Slack app was installed before DM support and is missing the direct-message permissions. A workspace admin needs to reconnect the Gumloop Slack app from the [Connectors page](https://www.gumloop.com/personal/connectors?provider=slack). Channel agents keep working in the meantime.
  </Accordion>

  <Accordion title="Can I change Gumball's email address?" icon="envelope">
    No. `gumball@gumloopagents.com` is reserved for the personal agent and can't be renamed, disabled, or claimed by a custom agent.
  </Accordion>

  <Accordion title="Can the Daily Chew or Meeting Prep change something in my apps?" icon="lock">
    No. Both run read-only. Only tools that read data are available during a run — anything that writes, sends, or modifies is blocked, including write tools reached indirectly through another tool.
  </Accordion>

  <Accordion title="One of my apps was down during a run. Do I lose that day's updates?" icon="triangle-exclamation">
    No. Each source is tracked separately, and Gumball only advances its position for the sources that succeeded. The failed source is reported as failed for that run and its missed items show up in the next one.
  </Accordion>

  <Accordion title="What does the Daily Chew cover on its very first run?" icon="hourglass-start">
    The previous 16 hours. Every run after that covers everything since the last successful run.
  </Accordion>

  <Accordion title="I left the connector list empty. What gets included?" icon="plug">
    Everything you have connected at the time the report runs. That's the same as choosing **All connectors** — and it means newly connected apps are picked up automatically.
  </Accordion>

  <Accordion title="Does Smart Inbox work with Outlook?" icon="inbox">
    Yes. Connect either a Gmail or an Outlook mailbox. On Outlook your labels become Outlook **categories** instead of Gmail labels, and the only behavioural differences are that categories can't be renamed on existing mail and that moving mail out of your Inbox needs the archive permission. Gumball watches one mailbox at a time, so connecting Outlook replaces a connected Gmail account.
  </Accordion>

  <Accordion title="Will Smart Inbox label the email that's already sitting in my inbox?" icon="clock-rotate-left">
    No. Classification happens as email arrives, so only mail received after you enable it gets labelled. The same applies to changes: editing or adding a label affects future email, never mail that's already been classified.
  </Accordion>

  <Accordion title="I renamed a label — what happens to email that already had it?" icon="tag">
    On Gmail the rename is applied to the label itself, so existing mail keeps the label under its new name. On Outlook categories can't be renamed, so the new name is used for future mail and older mail keeps the old category. Definitions, colours, and group changes only affect new mail on either provider.
  </Accordion>

  <Accordion title="Why did a message stay in my Inbox even though it got a 'move out' label?" icon="arrow-right-to-bracket">
    Because another label on the same message was in the "Keep these in my Inbox" group. Keep always wins — a message only leaves your Inbox when every label applied to it says move.
  </Accordion>

  <Accordion title="Why didn't Gumball label an email at all?" icon="circle-question">
    Most often one of three reasons: the **Existing labels** toggle is on and you had already labelled or categorised that message yourself; none of your label definitions matched confidently enough; or the message arrived before Smart Inbox was enabled.
  </Accordion>

  <Accordion title="Can Smart Inbox send emails for me?" icon="paper-plane">
    No. It only creates drafts in your mailbox. Sending is always your action.
  </Accordion>

  <Accordion title="Why do I get a reply suggestion but no draft in my mailbox?" icon="pen">
    Either **Draft replies** is set to **Off**, Gumball wasn't confident in the reply it wrote, your organization's app rules block draft creation, or you had edited Gumball's previous draft in that thread and it declined to overwrite it. The suggestion is still there, and you can accept it into chat.
  </Accordion>

  <Accordion title="What happens to my labels if I disconnect my mailbox?" icon="link-slash">
    Gumball forgets what it labelled and drops its links to the native labels or categories. Those stay in your mailbox on the mail that already has them, and if you reconnect, Gumball re-adopts the ones with matching names.
  </Accordion>

  <Accordion title="Does Meeting Prep work with Outlook Calendar?" icon="calendar">
    Yes. Connect either a Google Calendar or an Outlook Calendar account, pick the calendar to watch, and everything else — automatic prep, **Prepare now**, meeting scope, and the brief itself — works the same way.
  </Accordion>

  <Accordion title="Why didn't Meeting Prep run for a meeting on my calendar?" icon="calendar-xmark">
    The most likely reason is **External meetings only** — a meeting is only external if at least one guest besides you has a different email domain. Also check that automatic prep is on and that the meeting starts further out than your **Minutes Before Event** setting. You can always use **Prepare now**.
  </Accordion>

  <Accordion title="Can I still prep a meeting with automatic prep switched off?" icon="hand-pointer">
    Yes. Turning it off only stops Gumball firing on its own. Meetings still appear on the home screen and **Prepare now** works normally.
  </Accordion>

  <Accordion title="Do reports get delivered twice if something is retried?" icon="rotate">
    No. Each run has a single delivery boundary, so an internal retry can't produce a duplicate report.
  </Accordion>

  <Accordion title="Where do reports go?" icon="inbox">
    Always into Gumball, where they're kept in history. **Email** and **Slack DM** are optional on top of that. Slack DMs are matched to you by your account email — if there's no email on your profile, the Slack DM is recorded as not sent and the report is still saved.
  </Accordion>

  <Accordion title="Why is there no suggested action even though the report mentioned something important?" icon="list-check">
    Suggested actions are deliberately narrow: they need work you own with a concrete outcome Gumball could deliver. Notifications, mentions, and general activity aren't enough on their own.
  </Accordion>

  <Accordion title="Do these features cost credits?" icon="coins">
    Yes. Classifying email, drafting replies, and generating reports use models and tools like any other agent activity. See [Credits](/core-concepts/credits).
  </Accordion>

  <Accordion title="My admin restricted Gumball and then unrestricted it. Why isn't Smart Inbox running?" icon="shield-halved">
    Smart Inbox is switched off when access is removed and does not resume automatically. Re-enable it from the Smart Inbox tab.
  </Accordion>
</AccordionGroup>
