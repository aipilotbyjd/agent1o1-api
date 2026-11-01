> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Organization Skills

> Centrally manage skills for your whole organization, and keep them in sync with a GitHub repository.

**Organization Skills** let admins manage a shared library of [skills](/core-concepts/skills) for the entire organization from one place, and control who can use them.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1212196745?h=0e7260f9ae" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="GitHub sync" />
</div>

There are two kinds of organization skills:

<CardGroup cols={2}>
  <Card title="Hosted skills" icon="cloud">
    Created and stored directly in Gumloop — with AI, by uploading files, or by
    writing instructions. Fully editable inside Gumloop.
  </Card>

  <Card title="GitHub-backed skills" icon="github">
    Imported from a GitHub repository and kept in sync automatically. The
    repository is the source of truth — you edit these in GitHub, not in Gumloop.
  </Card>
</CardGroup>

<Info>
  Organization Skills live under **Settings → Organization → Skills**
  ([gumloop.com/settings/organization/skills](https://gumloop.com/settings/organization/skills)).
</Info>

## Who can use it

<CardGroup cols={2}>
  <Card title="Plan" icon="gem">
    Available on **Pro** and **Enterprise** plans.
  </Card>

  <Card title="Role" icon="user-shield">
    Managing organization skills and sources requires the
    **Manage Organization Skills** permission, which belongs to the
    **Admin** role by default.
  </Card>
</CardGroup>

Members without this permission don't manage sources — they simply *use* the skills that have been shared with them or their team, just like any other skill.

## Connect a GitHub repository

GitHub-backed skills are imported from a repository your organization has authorized through the **Gumloop GitHub App**. Connecting a source is a short guided flow.

<Tip>
  **How skills are discovered:** every folder that contains a `SKILL.md` file
  becomes one skill. Gumloop scans the branch and folder path you select and
  imports each such folder as an organization skill.
</Tip>

<Steps>
  <Step title="Choose a repository">
    Pick the GitHub owner, then select a repository the Gumloop GitHub App can
    access. Repositories you haven't used before are authorized for your
    organization the first time you select them.

    <Frame>
      <img src="https://mintcdn.com/agenthub/HFWwGvl3UYhc-fTC/images/organization_skills/connect_choose_repository.png?fit=max&auto=format&n=HFWwGvl3UYhc-fTC&q=85&s=51478ef69ee775a90b223dbba05ed876" alt="Connect wizard step 1: choosing a GitHub repository from the owner dropdown" style={{ maxWidth: '560px', width: '100%', height: 'auto' }} width="2048" height="1572" data-path="images/organization_skills/connect_choose_repository.png" />
    </Frame>
  </Step>

  <Step title="Choose where skills live">
    Select a **branch** (defaults to the repository's default branch) and an
    optional **folder path**. Leave the folder path empty to scan the entire
    repository.

    <Frame>
      <img src="https://mintcdn.com/agenthub/HFWwGvl3UYhc-fTC/images/organization_skills/connect_choose_location.png?fit=max&auto=format&n=HFWwGvl3UYhc-fTC&q=85&s=4eac1baf3c5843a610e110841582d13a" alt="Connect wizard step 2: selecting a branch and optional folder path" style={{ maxWidth: '560px', width: '100%', height: 'auto' }} width="1800" height="866" data-path="images/organization_skills/connect_choose_location.png" />
    </Frame>
  </Step>

  <Step title="Set default access">
    Choose who can use skills imported from this source *by default*. You can
    grant one or more **teams** a role, and set **general access**:

    | General access   | Who can use imported skills       |
    | ---------------- | --------------------------------- |
    | **Restricted**   | Only members of the teams you add |
    | **Organization** | Everyone in your organization     |

    <Frame>
      <img src="https://mintcdn.com/agenthub/HFWwGvl3UYhc-fTC/images/organization_skills/connect_default_access.png?fit=max&auto=format&n=HFWwGvl3UYhc-fTC&q=85&s=cb7d854e3d28f40ed7d728ce19f27463" alt="Connect wizard step 3: setting default team and general access for imported skills" style={{ maxWidth: '560px', width: '100%', height: 'auto' }} width="1960" height="1188" data-path="images/organization_skills/connect_default_access.png" />
    </Frame>

    <Warning>
      **Restricted with no teams** means imported skills are locked up — nobody
      in the organization can use them until you grant team or organization
      access later.
    </Warning>
  </Step>

  <Step title="Preview and connect">
    Gumloop previews the source and shows how many `SKILL.md` folders it found
    on the selected commit. Confirming connects the source and kicks off the
    first sync.
  </Step>
</Steps>

<Note>
  Default access applies to skills the source imports **for the first time**. It
  seeds each new skill's access — you can still fine-tune access per skill
  afterward (see [Manage access](#manage-who-can-use-skills)).
</Note>

## How syncing works

Once a source is connected, Gumloop keeps its skills in sync with the repository.

<CardGroup cols={3}>
  <Card title="Initial sync" icon="download">
    Runs automatically right after you connect the source.
  </Card>

  <Card title="Automatic sync" icon="rotate">
    New commits pushed to the selected branch trigger a sync automatically.
  </Card>

  <Card title="Manual sync" icon="hand-pointer">
    Use **Sync now** on the source's Settings tab to re-import on demand.
  </Card>
</CardGroup>

Each sync reads the repository, validates the discovered skills, then adds, updates, or removes skills so Gumloop matches the repository. A sync moves through **queued → scanning → staging → applying → succeeded** (or **failed**).

### Source health

Each connected source shows a health status so you can spot problems at a glance:

| Status                      | Meaning                                                                     |
| --------------------------- | --------------------------------------------------------------------------- |
| **Healthy**                 | Latest sync succeeded and updates are flowing                               |
| **Importing**               | A sync is currently running                                                 |
| **Import failed**           | The most recent sync failed — check the history for details                 |
| **Branch missing**          | The selected branch no longer exists in the repository                      |
| **App access unavailable**  | The Gumloop GitHub App can no longer access the repository                  |
| **Webhook updates missing** | Automatic push updates aren't being received; use **Sync now** or reconnect |

## Manage a connected source

Opening a source shows its imported skills on the left and a detail panel with three tabs.

<Tabs>
  <Tab title="Overview">
    Status, number of skills, last synced time, the current commit, default
    access, who connected the source, and 30-day usage.

    <Frame>
      <img src="https://mintcdn.com/agenthub/HFWwGvl3UYhc-fTC/images/organization_skills/source_overview.png?fit=max&auto=format&n=HFWwGvl3UYhc-fTC&q=85&s=7598c6d338634fe1d4234e605c379037" alt="Source Overview tab showing status, skill count, last synced, commit, default access, and usage" style={{ maxWidth: '820px', width: '100%', height: 'auto' }} width="2424" height="1240" data-path="images/organization_skills/source_overview.png" />
    </Frame>
  </Tab>

  <Tab title="History">
    A record of sync runs with their status, commit, actor, and time. Each run
    is inspectable, and you can open the full history.

    <Frame>
      <img src="https://mintcdn.com/agenthub/HFWwGvl3UYhc-fTC/images/organization_skills/source_history.png?fit=max&auto=format&n=HFWwGvl3UYhc-fTC&q=85&s=fa278b13d33347fa78e2e471e9ef2c29" alt="Source History tab listing recent sync runs marked Succeeded with commit and actor" style={{ maxWidth: '520px', width: '100%', height: 'auto' }} width="898" height="996" data-path="images/organization_skills/source_history.png" />
    </Frame>
  </Tab>

  <Tab title="Settings">
    **Sync now**, **Open repository** on GitHub, edit **default access**, and
    **Disconnect** the source.

    <Frame>
      <img src="https://mintcdn.com/agenthub/HFWwGvl3UYhc-fTC/images/organization_skills/source_settings.png?fit=max&auto=format&n=HFWwGvl3UYhc-fTC&q=85&s=fba0f8d8f1054e2cf0e8caa4994eac42" alt="Source Settings tab with Sync now, Open repository, Default access, and Disconnect actions" style={{ maxWidth: '520px', width: '100%', height: 'auto' }} width="980" height="750" data-path="images/organization_skills/source_settings.png" />
    </Frame>
  </Tab>
</Tabs>

### Disconnecting a source

When you disconnect a GitHub-backed source, you choose what happens to its skills:

<CardGroup cols={2}>
  <Card title="Keep skills" icon="box-archive">
    The skills are kept and converted to hosted skills. Syncing stops, but the
    skills remain usable and become editable in Gumloop.
  </Card>

  <Card title="Remove skills" icon="trash">
    The skills imported by this source are removed from the organization.
  </Card>
</CardGroup>

## Hosted skills

Hosted skills are created and maintained directly in Gumloop — no repository required. On the Organization Skills page you can create them by:

* **Create with AI** — let AI generate a skill for you
* **Upload files** — upload a skill package (must include a `SKILL.md`)
* **Write skill instructions** — enter a name and description

<Info>
  Hosted skills are fully editable in Gumloop. **GitHub-backed skills are
  read-only in Gumloop** — edit them in the source repository and let a sync
  bring the changes in.
</Info>

## Manage who can use skills

Access can be managed for a whole source's default, or for individual skills.

**Per skill** — use a skill's menu to **Control Access** and choose which teams (and roles) can use it, or make it organization-wide.

**In bulk** — select multiple skills and **Share with teams** or **Share with organization** at once.

<Frame>
  <img src="https://mintcdn.com/agenthub/HFWwGvl3UYhc-fTC/images/organization_skills/bulk_share.png?fit=max&auto=format&n=HFWwGvl3UYhc-fTC&q=85&s=3d8259cf30b4ee9433e2db4bb111ff9f" alt="Selecting multiple organization skills and sharing them with teams or the whole organization in bulk" style={{ maxWidth: '640px', width: '100%', height: 'auto' }} width="1532" height="1408" data-path="images/organization_skills/bulk_share.png" />
</Frame>

Two roles control what a grant allows:

| Role         | What it allows                                                         |
| ------------ | ---------------------------------------------------------------------- |
| **Use only** | Members can attach and use the skill on agents, but not view its files |
| **Viewer**   | Members can also view the skill's files                                |

The access column marks each skill's visibility — a lock for **restricted** (team-only) skills and a building icon for **organization**-wide skills.

## Use organization skills in agents

Organization skills that are shared with a member appear alongside their own skills. To add one to an agent, open the agent's **Skills** panel, click **Add → Add Existing Skill**, and pick from the **Organization** section.

<Frame>
  <img src="https://mintcdn.com/agenthub/HFWwGvl3UYhc-fTC/images/organization_skills/agent_add_skill_menu.png?fit=max&auto=format&n=HFWwGvl3UYhc-fTC&q=85&s=9042951429d2090fe664c2f2deffd765" alt="Agent Skills panel Add menu with Create With AI, Upload Files, Write Skill Instructions, and Add Existing Skill" style={{ maxWidth: '440px', width: '100%', height: 'auto' }} width="802" height="810" data-path="images/organization_skills/agent_add_skill_menu.png" />
</Frame>

<Frame>
  <img src="https://mintcdn.com/agenthub/HFWwGvl3UYhc-fTC/images/organization_skills/agent_add_existing_skill.png?fit=max&auto=format&n=HFWwGvl3UYhc-fTC&q=85&s=192b8fb927c9bc25b2c3943e21edb8f9" alt="Add Existing Skill panel showing organization skills available to attach" style={{ maxWidth: '560px', width: '100%', height: 'auto' }} width="1028" height="564" data-path="images/organization_skills/agent_add_existing_skill.png" />
</Frame>

Attached skills show up in the agent's Skills list and are used automatically when relevant.

<Frame>
  <img src="https://mintcdn.com/agenthub/HFWwGvl3UYhc-fTC/images/organization_skills/agent_attached_skills.png?fit=max&auto=format&n=HFWwGvl3UYhc-fTC&q=85&s=0a1ff72460f1b2aee87b85460c11eac4" alt="Two organization skills attached to an agent" style={{ maxWidth: '500px', width: '100%', height: 'auto' }} width="900" height="300" data-path="images/organization_skills/agent_attached_skills.png" />
</Frame>

For more on how agents load and apply skills, see [Agent Skills](/core-concepts/skills).

## Limitations

* GitHub-backed skills are **read-only in Gumloop**; edit them in the repository.
* A source tracks **one branch and one folder path**. Point at a different branch or path by connecting another source.
* A new source can't **overlap** the branch and path of an existing source in the same repository.
* Skill discovery is driven entirely by `SKILL.md` — a folder without one is not imported.
* Managing sources and organization skills requires the **Manage Organization Skills** permission (Admin) on a **Pro** or **Enterprise** plan.

## FAQs

<AccordionGroup>
  <Accordion title="What makes a folder a skill?">
    Any folder containing a `SKILL.md` file, under the branch and folder path you
    selected for the source. Each such folder becomes one skill.
  </Accordion>

  <Accordion title="Do I have to sync manually?">
    No. Gumloop syncs automatically after you connect a source and whenever new
    commits land on the selected branch. **Sync now** is there for when you want
    to re-import immediately.
  </Accordion>

  <Accordion title="Can I edit a GitHub-backed skill in Gumloop?">
    No — those skills are read-only in Gumloop. Make changes in the source
    repository and a sync will bring them in. Hosted skills, by contrast, are
    fully editable in Gumloop.
  </Accordion>

  <Accordion title="What happens to skills if I disconnect a source?">
    You choose: **keep** them (they convert to hosted skills and stop syncing) or
    **remove** them from the organization.
  </Accordion>

  <Accordion title="Why can't anyone use the skills I just imported?">
    The source's default access is likely **Restricted** with no teams, which
    locks the skills up. Grant team or organization access from the source's
    default access settings or per skill.
  </Accordion>

  <Accordion title="What's the difference between Use only and Viewer?">
    **Use only** lets members attach and use the skill on agents without seeing
    its files. **Viewer** additionally lets them view the skill's files.
  </Accordion>

  <Accordion title="Who can manage organization skills and sources?">
    Members with the **Manage Organization Skills** permission — the **Admin**
    role by default — on a **Pro** or **Enterprise** plan. Everyone else can use
    the skills shared with them.
  </Accordion>
</AccordionGroup>
