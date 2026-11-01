> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Brain

> Brain is your company knowledge base for agents. Connect your tools once, and your agents can search everything your team knows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1209731699?h=3f0476b8c3&badge=0&autopause=0&player_id=0&app_id=58479" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture; clipboard-write; encrypted-media; web-share" referrerPolicy="strict-origin-when-cross-origin" title="Gumloop Brain" />
</div>

Your agents can already take actions through [connectors](/core-concepts/agents#connectors) and follow your processes through [skills](/core-concepts/skills#what-is-a-skill). What they cannot do out of the box is know what *your company* knows: your documents, your Slack threads, your past decisions, and where any of it lives.

**Brain** is where that knowledge lives.

You connect the tools your team already uses, Gumloop indexes them, and your agents can then search across all of it and answer from your real content, with citations, instead of guessing.

<Info>Brain is available on the **Pro** and **Enterprise** plans.</Info>

## How Brain fits with tools and skills

Brain is the third piece of what makes an agent useful. Each answers a different question:

| Piece                                               | Gives your agent                       | Answers                             |
| --------------------------------------------------- | -------------------------------------- | ----------------------------------- |
| [**Connectors**](/core-concepts/agents#connectors)  | The ability to take live actions       | "Send this Slack message"           |
| [**Skills**](/core-concepts/skills#what-is-a-skill) | Reusable instructions for a task       | "Draft outreach using our sequence" |
| **Brain**                                           | Searchable knowledge from your content | "What does our refund policy say?"  |

<Info>They work together. An agent might **search Brain** for your pricing policy, follow a **skill** to format a quote, then use a **connector** to email it.</Info>

The mental model for the rest of this page: **indexing a source makes knowledge available; attaching it to an agent makes that knowledge usable by that agent.**

## Where you access Brain

Open **Brain** from the left sidebar, or jump straight to a page:

<CardGroup cols={2}>
  <Card title="Personal Brain" icon="user" href="https://gumloop.com/personal/brain">
    Sources only you can see.
  </Card>

  <Card title="Organization Brain" icon="building" href="https://gumloop.com/settings/organization/brain">
    Sources shared across your company.
  </Card>
</CardGroup>

The **All**, **Mine**, and **Organization** tabs filter by scope, and **+ Source** adds one. To scope knowledge to a single agent, use its [Knowledge Sources](#giving-an-agent-knowledge) section.

## How access works

Brain has two layers of access control:

1. **Source scope** — who can *see and search the source* at all.
2. **[Document access](#document-access)** — for supported sources, whether individual documents are further gated by their *own permissions in the original system*.

### Source scope

Every source has a **scope** that decides who can see and search it. You choose the scope when you add a source, and can change it later.

<CardGroup cols={3}>
  <Card title="Personal" icon="user">
    Only you can see and search it.
  </Card>

  <Card title="Team" icon="users">
    Everyone on that [team](/core-concepts/teams) can see and search it.
  </Card>

  <Card title="Organization" icon="building">
    Everyone in your organization can see and search it, or only the teams you choose. Often set up once by an admin for the whole company.
  </Card>
</CardGroup>

<Info>**Your data stays yours.** Indexed content is used only to answer your own team's agents. The embedding provider Gumloop uses runs under a zero-data-retention policy, so your content is not retained by it or used to train third-party models, and Brain respects [incognito](/core-concepts/agents#incognito-mode) chats.</Info>

## Document access

By default, source scope is the only gate: anyone who can see a source can search everything indexed in it. For **Google Drive** and **Salesforce** sources, you can go further and have Brain honor each document's *own* permissions in that system. You set this when adding a source (and can change it later on the source's settings), under **Document access**:

<CardGroup cols={2}>
  <Card title="Original source" icon="lock">
    Only people with access to a document *in the original source* can retrieve its content through Brain. Permissions are checked per document.
  </Card>

  <Card title="Gumloop access" icon="users">
    Anyone with access to this source in Gumloop can retrieve any document indexed in it, regardless of the original system's permissions.
  </Card>
</CardGroup>

<Note>**Document access** appears only for sources that support it — **Google Drive** and **Salesforce** today. Other source types always use **Gumloop access**.</Note>

When **Original source** is set, Gumloop snapshots each document's permissions in the original system during sync and enforces them at search time, matched by the user's email in that system.

For **Google Drive**, a user only retrieves a document if they have access to the underlying Drive file, matched by the email on their connected Google account.

Three kinds of Drive grants are honored: **direct user access**, **organization/domain access** (matching your verified email domain), and **Google Group access** — the last one needs the directory connection described below. Documents shared only through **"anyone with the link"** are treated as *no access* and won't surface: Brain fails closed rather than over-share.

<Warning>With **Gumloop access**, Brain does not check the source's own per-document permissions. Anyone who can see the source can retrieve anything indexed in it — including files they could not open directly in the original system. Scope such sources to the audience you intend. See [sharing and permissions](/core-concepts/share_permissions#general-access).</Warning>

<Info>Changing a source's Document access setting queues a re-sync so permissions are re-snapshotted against the new mode.</Info>

### Google Group sharing on Drive sources

Most Drive files in a company are shared with a **Google Group** rather than person by person. Drive reports the group's address, not who belongs to it, so Brain needs a way to expand a group into its members. That is the job of the **Google Workspace directory** connection: one organization-level Google connection that reads your Workspace group memberships, read-only, so anyone in a group can retrieve the files shared with that group.

An organization admin connects it once at [Organization settings > General](https://www.gumloop.com/settings/organization/general), under **Google Workspace directory**, using an account allowed to read your Workspace directory. It then applies to every Drive source in the organization.

<Note>Because group-only files stay hidden without it, new **organization** and **team** Drive sources set to **Original source** ask for the connection before they can be added — the dialog links an admin straight to it, and switching **Document access** to **Gumloop access** removes the requirement. Personal Brain sources and existing Drive sources are unaffected.</Note>

<Warning>**Brain fails closed on group membership it cannot read.** Files shared only through a group stay hidden from that group's members when the directory is not connected, when the group lives outside your organization's directory, or when the connected Drive account can no longer read the file's permissions. Disconnecting the directory stops group expansion, so group-shared files can drop out of results for those members.</Warning>

### Salesforce record permissions

Salesforce sources can also use **Original source**. During each sync, Brain snapshots who can read every record and enforces it at search time, matching users by the email on their Salesforce user record.

<AccordionGroup>
  <Accordion title="What is mirrored" icon="lock">
    * **Record ownership**, plus the owner's **role hierarchy** where the object grants access upward
    * **Sharing** on the record: sharing rules, manual shares, and shares to public groups, queues, teams, and territories
    * **Organization-wide defaults**, for objects whose sharing model is public read
    * **"View All" and "View All Data"** granted through profiles and permission sets
    * **Knowledge articles**: data category visibility, so a user matches an article only when their profile can see every category on it
    * **Field-level security**: fields a user cannot read in Salesforce are kept out of what they can retrieve, so two people searching the same record can see different fields
  </Accordion>

  <Accordion title="Where Brain fails closed" icon="shield">
    Brain never widens access it cannot verify. A record's content stays limited to **View All Data** users when:

    * **Restriction rules** are active on the object — their criteria are not mirrored
    * A record is flagged **private**, in which case non-owner shares are ignored (for private activities, only the assignee and View All Data)
    * The object's sharing model cannot be read, or access is **controlled by a parent** record
    * A record has **no owner**, or a user who should match has **no email** on their Salesforce user record

    Knowledge articles with data categories are skipped entirely if the connected Salesforce account cannot use the Metadata API, since per-user category visibility is only available there. Reconnect the source with an account that can use it to index those articles.
  </Accordion>
</AccordionGroup>

<Info>Salesforce permissions are re-snapshotted on each sync, so grants you change in Salesforce take effect in Brain after the next sync.</Info>

### Syncing "Shared with me"

Drive's **Shared with me** can be picked as a sync root, and Brain walks it recursively like any other folder. Google does not expose permission lists for that content, so **Original source** cannot be enforced on it: a Shared with me source has to use **Gumloop access**, and Gumloop blocks the other combination when you add it. Everyone who can see the source can then retrieve everything indexed from it, so choose the source's scope accordingly.

## Adding a source

A **knowledge source** is a connection to a place your knowledge already lives. Gumloop reads from it, indexes the content, and keeps it in sync.

<Frame caption="Supported source types in the Add a source dialog.">
  <img src="https://mintcdn.com/agenthub/2H7g7Ms7OzDoNMQk/images/brain/add-a-source.webp?fit=max&auto=format&n=2H7g7Ms7OzDoNMQk&q=85&s=45e1df574374663ac485b0154f80b6b8" alt="Add a source dialog listing Notion, Google Drive, Slack, GitHub, Confluence, Zendesk, and File uploads." width="1136" height="984" data-path="images/brain/add-a-source.webp" />
</Frame>

<Info>**Don't see your app?** You can also sync almost any other app in Gumloop's connector library, no native source required. See [Build your own sources](#build-your-own-sources).</Info>

<Steps>
  <Step title="Open Add a source">
    On the **Brain** page (or an agent's Knowledge Sources section), click **+ Source** and pick a source type.
  </Step>

  <Step title="Name it and pick an account">
    Give the source a clear, descriptive name. This is how agents and your team will see it. Then choose the connected account Gumloop should use to read the content.

    <Frame caption="Naming a Google Drive source and choosing the account used to read it.">
      <img src="https://mintcdn.com/agenthub/2H7g7Ms7OzDoNMQk/images/brain/add-source-account.webp?fit=max&auto=format&n=2H7g7Ms7OzDoNMQk&q=85&s=33ed99d43ab76def34e94ad388d7d96f" alt="Google Drive source setup showing a Name field and an Account selector." width="1130" height="912" data-path="images/brain/add-source-account.webp" />
    </Frame>
  </Step>

  <Step title="Choose exactly what to sync">
    Narrow the source to only what you want indexed: specific drives, folders, channels, spaces, repositories, or (for Salesforce) objects. Everything you include inherits the source's scope, so pick with the audience in mind. For Google Drive you can also sync [**Shared with me**](#syncing-shared-with-me).

    <Frame caption="Selecting which Google Drive folders to sync.">
      <img src="https://mintcdn.com/agenthub/2H7g7Ms7OzDoNMQk/images/brain/add-source-folders.webp?fit=max&auto=format&n=2H7g7Ms7OzDoNMQk&q=85&s=149bf8de650edd5c1fcfee42e99fa691" alt="Google Drive source setup showing a drive selector and a checklist of folders to sync." style={{ maxWidth: '440px' }} width="1138" height="1688" data-path="images/brain/add-source-folders.webp" />
    </Frame>
  </Step>

  <Step title="Set Document access (supported sources)">
    For sources that support it (Google Drive and Salesforce today), choose how retrieval permissions work: **Original source** to honor each document's own permissions, or **Gumloop access** to let anyone with access to the source in Gumloop retrieve its content. See [Document access](#document-access) below.
  </Step>

  <Step title="Choose organization access (organization sources)">
    When you add a source to **Organization** Brain, a final step controls who in your org gets it: everyone organization-wide, or only specific [teams](/core-concepts/teams). Personal and team sources skip this step.
  </Step>

  <Step title="Add the source">
    Click **Add source**. Gumloop starts crawling and indexing.
  </Step>
</Steps>

### What gets indexed

Brain reads the text in your content. What that means per source:

| Source           | What gets indexed                                                                                                                                                                  |
| ---------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Notion**       | Full page content for the pages and databases you connect.                                                                                                                         |
| **Google Drive** | File contents from the drives and folders you pick, including Google Docs, Sheets, and Slides (converted for indexing). Folders organize results but are not documents themselves. |
| **Slack**        | Messages, including thread replies, from the public channels you choose.                                                                                                           |
| **GitHub**       | Files from the repository you connect.                                                                                                                                             |
| **Confluence**   | Pages from the Confluence space you connect.                                                                                                                                       |
| **Zendesk**      | Help Center articles from the categories and sections you pick, and support tickets, including each ticket's subject and full comment thread.                                      |
| **Salesforce**   | CRM records and Knowledge articles from the objects you pick. Each record becomes one document with its field values as text, grouped under its object.                            |
| **File uploads** | The files you upload: PDFs, Office files (`.docx`, `.pptx`, `.xlsx`), rich text, Markdown, JSON, XML, YAML, and plain text, up to about 400 MB each.                               |

<Warning>Brain does not run OCR on images, so text inside images or scanned pages is not indexed. Private Slack channels are not synced.</Warning>

### Salesforce sources

**Salesforce** is a native source that syncs *CRM records and Knowledge articles*, so agents can answer from accounts, opportunities, cases, custom objects, and your Knowledge base.

<Steps>
  <Step title="Pick the Salesforce account">
    Choose the connected Salesforce account Brain should read with. Everything Brain can index is limited to what that account can see, so use an account with the visibility you want indexed.
  </Step>

  <Step title="Choose which objects to sync">
    Salesforce is scoped by **object**, not by individual record. Under **Objects** you get a checklist of the objects available in your org — standard and custom — and every object starts selected. Uncheck the ones you don't want, or select only the specific objects you do want.

    Objects Salesforce doesn't expose for browsing and reporting, and internal plumbing tables (share, history, and feed objects), are not offered.
  </Step>

  <Step title="Set Document access">
    Pick **Original source** to mirror Salesforce record permissions per document, or **Gumloop access** to let anyone with the source retrieve anything indexed in it. See [Salesforce record permissions](#salesforce-record-permissions).
  </Step>
</Steps>

**How records are indexed**

* One document per record, grouped under its object, titled from the record's name, title, subject, or case number
* The record's field values are indexed as text with their field labels, and rich text fields are flattened to plain text
* Structural values with nothing to search — raw IDs, system timestamps, and compound fields — are left out of the text
* Each record links back to the record in Salesforce, so search results are clickable

<Note>You must select at least one object. A Salesforce source that includes nothing would index nothing, so Gumloop won't let you save it.</Note>

## Build your own sources

The source types above are purpose-built and give the best experience. For anything else, you can sync almost any app in Gumloop's [connector library](/core-concepts/agents#connectors), the same 100+ integrations your agents already use, even when there's no native source for it (Gmail, Linear, Intercom, HubSpot, Jira, and more).

Instead of a fixed form, a short **AI setup chat** inspects the app for you, proposes what's worth syncing, and turns it into the same picker-and-toggle experience as a native source. Nothing is added until you approve the plan.

<Steps>
  <Step title="Pick the app">
    In **Add a source**, search for the app. Apps without a native source appear under **Build your own**.

    <Frame caption="Apps without a native source appear under 'Build your own' in the Add a source dialog.">
      <img src="https://mintcdn.com/agenthub/RKQytzJkIBKEc_R4/images/brain/byo-add-source.webp?fit=max&auto=format&n=RKQytzJkIBKEc_R4&q=85&s=16813318ae85f86a3f54153533a8cea2" alt="Add a source dialog with 'Gmail' typed in the search box and a Gmail result listed under a 'Build your own' heading." style={{ maxWidth: '540px' }} width="1294" height="474" data-path="images/brain/byo-add-source.webp" />
    </Frame>
  </Step>

  <Step title="Connect an account and (optionally) say what you want">
    Choose the account Brain should read from, then optionally describe what to sync (for example, "our support emails"). Leave it empty and the setup chat suggests a sensible default.
  </Step>

  <Step title="Approve the proposed plan">
    An assistant explores the app, works out how its content is organized, and proposes what to sync. Review its summary and choose **Looks good** or **Request changes**.

    <Frame caption="The setup chat proposes what to sync and asks you to approve or request changes.">
      <img src="https://mintcdn.com/agenthub/RKQytzJkIBKEc_R4/images/brain/byo-setup-chat.webp?fit=max&auto=format&n=RKQytzJkIBKEc_R4&q=85&s=2a63cc4f6002979a996dc181bbb86eba" alt="Setup chat for a Gmail source showing the assistant's plan and a 'Ready to sync' card with 'Looks good' and 'Request changes' options." style={{ maxWidth: '420px' }} width="1220" height="1334" data-path="images/brain/byo-setup-chat.webp" />
    </Frame>
  </Step>

  <Step title="Choose exactly what to sync">
    On the selection screen, narrow the source to only what you want indexed, using the app's own concepts (labels, folders, channels, projects). Then click **Add source**.

    <Frame caption="The selection screen, built from the approved plan. Sync everything or hand-pick what to include.">
      <img src="https://mintcdn.com/agenthub/RKQytzJkIBKEc_R4/images/brain/byo-select.webp?fit=max&auto=format&n=RKQytzJkIBKEc_R4&q=85&s=624ff4b1fec98b32c9143a05efdefc81" alt="Selection screen for a Gmail source with a 'Sync by Labels' dropdown, 'Sync everything' vs 'Select what to sync' options, and a checklist of labels such as INBOX, SENT, and IMPORTANT." style={{ maxWidth: '420px' }} width="1298" height="1714" data-path="images/brain/byo-select.webp" />
    </Frame>
  </Step>
</Steps>

From there it behaves like any other source: it shows **Preparing** briefly, flips to **Active**, and appears in the sources list, [knowledge graph](#the-knowledge-graph), and [agent citations](#giving-an-agent-knowledge). It [stays in sync](#what-happens-after-you-add-a-source) automatically, and you can [re-sync, pause, edit, or delete it](#source-actions) like any source.

### The setup chat

The chat does the discovery so you don't have to:

* **It inspects the app** to learn how content is organized and which parts are worth making searchable.
* **It proposes a plan** as a plain-language summary of what you'll get, not a list of settings.
* **It may ask a question** when a choice is genuinely yours (which workspace, which username), always with options to pick from.
* **You approve or request changes.** **Looks good** moves you to the selection screen; **Request changes** tells it what to adjust, and it reworks the plan.

<Note>The setup chat is **read-only**. It only reads from the connected account to plan and sync the source, and never creates, edits, or deletes anything in your app.</Note>

<Tip>Give it a hint on the connect step for a more tailored plan, e.g. "just our #announcements channel" or "deals closed this year". Anything specific you mention is carried onto the selection screen so you don't re-enter it.</Tip>

### Choosing what to sync

The selection screen is built from the approved plan, so the exact controls depend on the app. You'll typically see:

| Control                  | What it does                                                                                                                |
| ------------------------ | --------------------------------------------------------------------------------------------------------------------------- |
| **Sync by**              | When an app can be organized more than one way, pick the dimension to sync by (for example, **Labels** vs. a search query). |
| **Sync everything**      | Index the whole source, including content added later.                                                                      |
| **Select what to sync**  | Hand-pick only the containers you check, such as specific labels, folders, or channels.                                     |
| **What to include**      | Toggle which content streams are indexed (bodies vs. comments, say). Core content is on by default.                         |
| **Search box / queries** | For content reachable only by an identifier or query (a username, a channel, a search), type the values to pull in.         |

Everything you include inherits the source's [scope](#source-scope), so pick with your audience in mind. The account works like any source: on **Team** Brain you can pick a team or personal account; on **Personal** and **Organization** Brain you pick a personal account.

### What you can't add this way

* **Apps with a native source** (Notion, Google Drive, Slack, GitHub, Confluence) use their dedicated setup instead and don't appear here.
* **Action-only and scraping platforms** (web-scraping, enrichment tools) have no content library to sync, so they're hidden.
* **Raw database and warehouse apps** aren't offered, because syncing them safely would need write-capable access that Brain never uses.
* **Empty accounts or missing permissions** — the setup chat tells you rather than creating an empty source, so you can switch accounts. If setup can't finish (including running out of [credits](#credits)) the source shows **Setup failed** with a short reason; fix the cause and set it up again.

## What happens after you add a source

Once a source is added, Gumloop takes over. You do not manage any of this:

<Steps>
  <Step title="Crawl and index">
    Gumloop reads the source, detects what is new or changed, and indexes the content for both semantic (meaning-based) and keyword search. The source moves to **Active** and its items show as **Indexed**.
  </Step>

  <Step title="Stay in sync">
    External sources re-sync automatically (by default about hourly for connectors like Google Drive), so answers reflect the latest version. If a document is deleted or moved out of a source's scope upstream, the next successful sync removes it from Brain too. File uploads do not auto-sync because there is no remote system to poll.
  </Step>
</Steps>

<Info>**How long until it is searchable?** Indexing time depends on the source's size, from a minute or two for a small source to longer for large ones. The **Status** column shows progress and flips to Active once items are indexed and ready to search.</Info>

<Info>If your admins have already set up **Organization** sources, they appear under the Organization tab and are ready to search right away. You do not need to add anything yourself to start benefiting from Brain.</Info>

## Managing your sources

### The sources list

On the Brain page, each source shows an at-a-glance summary:

| Column       | What it shows                                        |
| ------------ | ---------------------------------------------------- |
| **Name**     | The source name, grouped by source type.             |
| **Docs**     | How many documents are indexed.                      |
| **Activity** | How often the source has been searched recently.     |
| **Access**   | Who can see and search it (its scope).               |
| **Status**   | The current sync status (Active, Paused, and so on). |

The **Overview** panel summarizes total sources and recent activity across everything you can see.

### A source's detail page

Click any source to open it. You get:

* The list of items it contains, each with its status and last-updated time.
* An **Overview** panel: status, document count, activity, last sync, and who added it.
* **Search this source** to preview what is indexed, and **Edit** to change what it syncs.

<Frame caption="A source detail page: indexed items on the left, an Overview panel and knowledge graph on the right.">
  <img src="https://mintcdn.com/agenthub/2H7g7Ms7OzDoNMQk/images/brain/source-detail.webp?fit=max&auto=format&n=2H7g7Ms7OzDoNMQk&q=85&s=4fd30a255cdbd240e337c634a783fd08" alt="Confluence source detail page listing indexed items with Access, Status, and Updated columns, and an Overview panel showing Status, Documents, Activity, Last synced, and Added by." width="2388" height="1376" data-path="images/brain/source-detail.webp" />
</Frame>

### Source actions

Open the **⋮** menu on any source for its management actions:

<Frame caption="The per-source actions menu.">
  <img src="https://mintcdn.com/agenthub/2H7g7Ms7OzDoNMQk/images/brain/source-actions.webp?fit=max&auto=format&n=2H7g7Ms7OzDoNMQk&q=85&s=b5acb0c8b49275043618a49cdfc5d9fc" alt="Source context menu with Re-sync, Pause, Share, Rename, and Delete options." style={{ maxWidth: '520px' }} width="1546" height="592" data-path="images/brain/source-actions.webp" />
</Frame>

<AccordionGroup>
  <Accordion title="Re-sync" icon="rotate">
    Force an immediate sync to pull the latest content without waiting for the next scheduled run.
  </Accordion>

  <Accordion title="Pause" icon="pause">
    Stop syncing and stop any running indexing work. Already-indexed content stays searchable. Resume any time.
  </Accordion>

  <Accordion title="Control access" icon="share">
    Change who can see and search the source. For organization sources this controls whether it's available org-wide or only to specific teams; you can also change a source's [Document access](#document-access) from its settings.
  </Accordion>

  <Accordion title="Rename" icon="pen">
    Change the display name your team and agents see. Renaming does not re-index anything.
  </Accordion>

  <Accordion title="Delete" icon="trash">
    Remove the source and all of its indexed chunks from Brain. This cannot be undone.
  </Accordion>
</AccordionGroup>

### Statuses

| Status           | Meaning                                                                                                                                                 |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Active**       | The source is connected and syncing normally.                                                                                                           |
| **Preparing**    | A newly added [build-your-own source](#build-your-own-sources) is being wired up before its first sync. Flips to Active on its own.                     |
| **Setup failed** | A build-your-own source couldn't be set up (for example, an empty account or out of credits). Shows a short reason; fix it and set the source up again. |
| **Paused**       | Syncing is stopped until you resume it.                                                                                                                 |
| **Syncing**      | A sync is currently running.                                                                                                                            |
| **Indexed**      | An item has been processed and is searchable.                                                                                                           |
| **Failed**       | The last sync hit an error. Re-sync or check the connected account.                                                                                     |
| **Partial**      | The last sync finished, but some items did not index.                                                                                                   |

## The knowledge graph

Every Brain view includes a **knowledge graph**: an interactive 3D map of everything you have indexed. Each point is a piece of your knowledge, clustered by source and colored so you can see how your Slack, Drive, Confluence, and other content group and connect. Click **View knowledge graph** or **Expand** to open it full screen and drag to explore.

<Frame caption="The expanded knowledge graph. Each cluster is a source; each point is indexed content.">
  <img src="https://mintcdn.com/agenthub/2H7g7Ms7OzDoNMQk/images/brain/knowledge-graph.webp?fit=max&auto=format&n=2H7g7Ms7OzDoNMQk&q=85&s=61c1f93556229b26c3e2398b621dfb2f" alt="A 3D knowledge graph showing thousands of points clustered around Slack, Google Drive, Confluence, and file-upload source icons." width="2520" height="1348" data-path="images/brain/knowledge-graph.webp" />
</Frame>

## Giving an agent knowledge

Adding a source to Brain makes it available. To let a specific agent *use* it, attach it in that agent's configuration.

<Frame caption="The Knowledge Sources section in an agent's configuration.">
  <img src="https://mintcdn.com/agenthub/2H7g7Ms7OzDoNMQk/images/brain/agent-knowledge-sources.webp?fit=max&auto=format&n=2H7g7Ms7OzDoNMQk&q=85&s=dfdac584a55129348eb0ead7b817e81f" alt="An agent configuration panel titled Knowledge Sources with the prompt 'Give your agent knowledge: Attach Company Brain sources so this agent can search them, down to the exact files or folders within.'" style={{ maxWidth: '520px' }} width="804" height="434" data-path="images/brain/agent-knowledge-sources.webp" />
</Frame>

<Steps>
  <Step title="Open Knowledge Sources">
    In the agent's configuration, find the **Knowledge Sources** section and click **+ Source**.
  </Step>

  <Step title="Attach sources">
    Pick from your Personal, Team, and Organization sources, or **Upload files** to add knowledge straight to this agent. You can drill into a source to attach only the exact files or folders that are relevant, so the agent searches a focused set.

    <Frame caption="Attaching Brain sources to an agent, grouped by scope.">
      <img src="https://mintcdn.com/agenthub/2H7g7Ms7OzDoNMQk/images/brain/agent-add-knowledge.webp?fit=max&auto=format&n=2H7g7Ms7OzDoNMQk&q=85&s=0f471110b051fd1883683051bbe4b77b" alt="Add knowledge source dialog with an Upload files option and sources grouped under Personal, Team, and Organization." style={{ maxWidth: '520px' }} width="1134" height="1430" data-path="images/brain/agent-add-knowledge.webp" />
    </Frame>
  </Step>
</Steps>

Once a source is attached, the agent gets two built-in tools automatically:

* **Search Company Brain** runs a hybrid search across the attached sources and returns the most relevant snippets. In chat this shows as *Searching Company Brain*.
* **Read document** fetches the full text of a specific document when a snippet is not enough. In chat this shows as *Reading document*.

The agent decides when to search. When you ask about internal knowledge, it searches Brain, cites what it found, and can open a full document for more context.

## Prompting an agent to use Brain

Once knowledge is attached, you use the agent normally. It reaches for Brain when a question is about your internal content, often running several searches at once and citing the sources it used.

<Frame caption="An agent answering from Brain: it runs multiple searches, then cites the source it used.">
  <img src="https://mintcdn.com/agenthub/2H7g7Ms7OzDoNMQk/images/brain/agent-brain-answer.webp?fit=max&auto=format&n=2H7g7Ms7OzDoNMQk&q=85&s=02ba9d8754cd786c9e19431006c532ae" alt="An HR-Bot chat answering a password policy question by searching the Gumloop Policies source across three queries and returning a cited answer." width="1260" height="1204" data-path="images/brain/agent-brain-answer.webp" />
</Frame>

Prompts that work well:

* "According to our internal docs, what is our refund window?"
* "Find the launch retro notes and summarize the top three action items."
* "What did we decide about pricing in the Slack thread last quarter?"
* "Search our knowledge base for the onboarding checklist and turn it into an email."

<Tip>Nudge the agent toward Brain when you want a grounded answer: phrases like "according to our docs," "search our knowledge base," or "what do we know about" make it clear you want a cited answer from your content, not a general one.</Tip>

## Your agents' artifacts

Beyond the sources you connect, Gumloop can index the [artifacts](/core-concepts/agent_artifacts#how-artifacts-work) your agents produce, the files they generate for you in chat, so agents can search and reuse past work instead of starting from scratch.

* Artifacts are indexed at the **Personal** (your artifacts) or **Team** (a project's artifacts) level.
* Only the newest version of each artifact is indexed, and it updates in place when a new version is produced.
* Agents find them with the same **Search Company Brain** and **Read document** tools, so "pull up the deck we made last week" works like any other knowledge lookup.

## Searching Brain programmatically

Brain search isn't limited to agent chats. The same hybrid search is available anywhere you build, scoped to the sources the authenticated user can access:

<CardGroup cols={2}>
  <Card title="REST API" icon="code" href="/api-reference/brain/search">
    `POST /brain/search` — search from any language over HTTP.
  </Card>

  <Card title="Python SDK" icon="python" href="/api-reference/sdk/python#company-brain">
    `client.brain.search("...")` returns ranked results.
  </Card>

  <Card title="CLI" icon="terminal" href="/cli/brain">
    `gumloop brain search "..."` from your shell.
  </Card>

  <Card title="MCP server" icon="plug" href="/mcp-server/overview">
    The `search_brain` tool, callable from any MCP client.
  </Card>
</CardGroup>

Each of these accepts a query, an optional result `limit` (1–50), and an optional `source_type` filter, and returns ranked snippets with their title, source, URL, relevance score, and owner metadata.

## Credits

Brain usage consumes Gumloop credits:

* **Indexing** is charged as content is processed, so most of the cost lands when you first add a source and when its content changes.
* **Searching** is charged per query, and an agent's Brain searches are billed inside that agent's run.

Bigger sources and heavier search usage cost more. See [Credits](/core-concepts/credits#what-you-pay-for) for how credits work across the platform.

## FAQ

<AccordionGroup>
  <Accordion title="What's the difference between Brain and Skills?">
    **Brain** is knowledge your agents can *search* (documents, messages, files). [**Skills**](/core-concepts/skills#what-is-a-skill) are instructions that teach an agent *how to do a task* your way. Use Brain for "what do we know about X," and skills for "here's our process for doing X."
  </Accordion>

  <Accordion title="My app isn't in the list of source types. Can I still sync it?">
    Usually yes. Beyond the native source types, you can [build your own source](#build-your-own-sources) from almost any app in Gumloop's [connector library](/core-concepts/agents#connectors). An AI setup chat inspects the app, proposes what to sync, and you approve it, no technical configuration needed. Apps that already have a native source, and action-only or raw-database apps, are the exceptions.
  </Accordion>

  <Accordion title="Can building my own source change anything in my app?">
    No. Both the setup chat and ongoing sync are strictly **read-only**. They only read content to index it and never create, edit, or delete anything in the connected app.
  </Accordion>

  <Accordion title="How is Brain different from connecting an app like Google Drive as a tool?">
    A connector lets an agent take live actions and fetch specific items on demand. Brain pre-indexes your content so the agent can do fast, semantic **search across everything** at once, with citations, instead of navigating a tool call by call.
  </Accordion>

  <Accordion title="How long until my content is searchable?">
    It depends on the source's size, from a minute or two for a small source to longer for large ones. The source shows **Active** and its items show **Indexed** once they are ready.
  </Accordion>

  <Accordion title="If I delete a document in the source, does it leave Brain?">
    Yes. When a document is deleted or moved out of a source's scope upstream, the next successful sync removes it from Brain. To remove an entire source, use **Delete**.
  </Accordion>

  <Accordion title="Is my data used to train models?">
    No. Your indexed content is used only to answer your own team's agents, and the embedding provider Gumloop uses runs under a zero-data-retention policy, so your content is not retained by it or used to train third-party models.
  </Accordion>

  <Accordion title="Who can see the sources I add?">
    It depends on the source's **scope**. Personal sources are visible only to you, Team sources to that [team](/core-concepts/teams), and Organization sources to everyone in your org (or only the teams you pick). Anyone who can see a source can search everything indexed in it, unless you set the source's [Document access](#document-access) to **Original source** (Google Drive), which additionally honors each document's own permissions.
  </Accordion>

  <Accordion title="Can Brain respect my Google Drive file permissions?">
    Yes. When you add a Google Drive source, set **[Document access](#document-access)** to **Original source**. Brain then snapshots each file's Drive permissions on every sync and only lets a user retrieve a document if they have access to it in Drive. Google Group sharing is honored too, once an admin connects the [Google Workspace directory](#google-group-sharing-on-drive-sources); files shared only via "anyone with the link" stay private in Brain.
  </Accordion>

  <Accordion title="What can I upload as a file source?">
    Common document formats such as PDFs, Office files (`.docx`, `.pptx`, `.xlsx`), rich text, Markdown, JSON, XML, YAML, and plain text, up to about 400 MB per file. Unsupported types are rejected, and Brain does not read text inside images.
  </Accordion>

  <Accordion title="Can agents use files they created themselves?">
    Yes. Gumloop can index your agents' [artifacts](/core-concepts/agent_artifacts#how-artifacts-work) at the Personal or Team level, so an agent can search and reuse past outputs. Only the newest version of each artifact is indexed.
  </Accordion>

  <Accordion title="Can I search Brain myself, not just through an agent?">
    Yes. Each source has a **Search this source** box, and the Brain page has a global search so you can find and preview content directly.
  </Accordion>

  <Accordion title="What plans include Brain?">
    Brain is available on the **Pro** and **Enterprise** plans.
  </Accordion>
</AccordionGroup>

## Next steps

<CardGroup cols={2}>
  <Card title="Agents Overview" icon="robot" href="/core-concepts/agents">
    Build and configure agents that use your knowledge.
  </Card>

  <Card title="Agent Skills" icon="graduation-cap" href="/core-concepts/skills">
    Teach agents how to do tasks your way, on top of what they know.
  </Card>
</CardGroup>
