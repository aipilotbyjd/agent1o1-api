> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Agent Skills

> Skills are reusable knowledge packs that teach your agents how to do specific work, and get better over time.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1182864971?h=3d8a29f978&badge=0&autopause=0&player_id=0&app_id=58479" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture; clipboard-write; encrypted-media; web-share" referrerPolicy="strict-origin-when-cross-origin" allowFullScreen title="Gumloop Skills" />
</div>

Skills teach your agents how to do specific work **your way**, and get better at it over time.

## What is a skill?

A **skill** is a reusable set of instructions (and optionally templates and scripts) that teaches an agent how to do a specific task.

Think of a skill as a **playbook** your agent can pull out when it needs it:

<CardGroup cols={3}>
  <Card title="Example: outreach emails" icon="handshake">
    A step-by-step outreach process, your templates, and follow-up rules.
  </Card>

  <Card title="Example: support triage" icon="message">
    An escalation checklist, severity rules, and response templates.
  </Card>

  <Card title="Example: weekly reporting" icon="chart-line">
    Which KPIs to include, the exact format, and optional scripts to compute metrics.
  </Card>
</CardGroup>

<Info>Skills can feel like **memory**, because the agent can update them after you give feedback. Technically, it is not remembering your conversation. It is updating a shared playbook that gets reused in future conversations.</Info>

<Tip>**This is the superpower:** when your agent makes a mistake and you correct it, the agent can update the relevant skill so it does it right next time.</Tip>

<Accordion title="See a 15-second example" icon="sparkles">
  > **You:** "Reply to this customer email. Keep it short and friendly."<br />
  > **Agent:** *\[replies, but uses the wrong sign-off]*<br />
  > **You:** "We never sign off with 'Best'. Use 'Thanks' instead."<br />
  > **Agent:** "Got it. I updated the relevant skill so future replies use 'Thanks'."
</Accordion>

## Why Skills?

When you set up an agent, you typically give it two things:

<CardGroup cols={2}>
  <Card title="System Prompt" icon="scroll">
    Defines who the agent is. "You're a sales assistant. Be professional." Always active, always loaded.
  </Card>

  <Card title="Tools" icon="wrench">
    Gives the agent hands. Gmail, Salesforce, Slack, etc. The raw ability to take actions.
  </Card>
</CardGroup>

That gets you an agent that **can** send emails and update your CRM. But it doesn't know **your** outreach process, **your** email templates, or **your** follow-up rules. It's winging it every time.

<Tip>**Skills fill that gap.** A skill is a set of instructions, templates, and even executable scripts that teach your agent exactly how to do a specific task. Your agent can read, follow, improve, and even create skills on its own.</Tip>

## The New Employee Analogy

The simplest way to understand skills is to imagine you just hired someone new.

| What you give them                             | Agent equivalent  | What it does                                                          |
| ---------------------------------------------- | ----------------- | --------------------------------------------------------------------- |
| **Job description & company handbook**         | **System prompt** | Defines who they are, their tone, and universal rules. Always active. |
| **Software logins** (Gmail, Salesforce, Slack) | **Tools**         | Gives them the raw ability to take actions.                           |
| **Training materials & SOPs**                  | **Skills**        | Teaches them your specific processes, templates, and best practices.  |

Without skills, your agent has access to email and CRM but improvises every time. It might send a decent email, but it won't follow your specific outreach sequence, use your templates, or log things the way you want.

**Skills are the difference between "an AI that can send emails" and "an AI that follows our exact outreach process."**

## When Do You Need a Skill?

Not everything needs to be a skill. Here's a quick guide:

<Tabs>
  <Tab title="System Prompt">
    **Put it in the system prompt** if it applies to **every single conversation:**

    * Agent personality and tone
    * Universal guardrails ("Never share pricing without approval")
    * Response format rules

    The system prompt is always loaded in full. Keep it short and universal.
  </Tab>

  <Tab title="Tool">
    **Use a tool** if it's a **simple, one-off action:**

    * "What's on my calendar today?"
    * "Send this message on Slack"
    * "Look up this contact in Salesforce"

    Tools give the agent the raw ability to interact with external services.
  </Tab>

  <Tab title="Skill">
    **Create a skill** when you have:

    * A **multi-step process** the agent should follow every time
    * **Templates** or specific formats the agent should use
    * **Domain knowledge** that's too long for the system prompt
    * **Instructions that only apply sometimes** (skills load on demand, saving tokens)
    * Work that **multiple agents** need to do the same way
  </Tab>
</Tabs>

<Info>**Rule of thumb:** If your instructions are over 200 words and don't apply to every single conversation, they belong in a skill, not the system prompt.</Info>

| Scenario                                             | System Prompt | Tool | Skill |
| ---------------------------------------------------- | ------------- | ---- | ----- |
| "Be friendly and professional"                       | ✅             |      |       |
| "Send this email for me"                             |               | ✅    |       |
| "Draft outreach emails using our 5-step sequence"    |               |      | ✅     |
| "Generate a weekly sales report with specific KPIs"  |               |      | ✅     |
| "Never delete customer data without confirmation"    | ✅             |      |       |
| "Triage support tickets using our escalation matrix" |               |      | ✅     |

## How Agents Use Skills

Here's what happens behind the scenes:

<Warning>**Skill-Tool Dependencies:** If a skill is designed to use a specific integration (e.g., Salesforce, Gmail) but that integration is not connected to the agent, the skill will load successfully but fail at execution. There is no automatic dependency enforcement — make sure any integration a skill depends on is also connected to the agent.</Warning>

<Steps>
  <Step title="Conversation starts">
    Your agent loads up and sees a list of all attached skills, but only the **names and descriptions** (not the full instructions). This keeps things fast and lightweight.
  </Step>

  <Step title="You ask something">
    You send a message like "Draft an outreach email to the VP of Sales at Acme Corp."
  </Step>

  <Step title="Agent finds the right skill">
    The agent scans the skill descriptions and finds `email-outreach-playbook` is relevant. It reads the full skill from the sandbox.
  </Step>

  <Step title="Agent follows the instructions">
    Now the agent has the complete playbook in context. It follows the step-by-step process: researches the prospect, personalizes the email using your template, and drafts it exactly the way you want.
  </Step>

  <Step title="Conversation ends">
    If the agent made any changes to skills during the conversation (more on that below), those changes are automatically saved.
  </Step>
</Steps>

<Info>**Key insight:** Skills are only loaded when needed. If you have 20 skills attached to an agent but the user asks a simple question, the agent doesn't waste time or tokens loading irrelevant skills. This is fundamentally different from a system prompt, which is always loaded in full.</Info>

## Built-in System Skills

Every Gumloop team comes with two built-in system skills that are automatically available to your agents:

<CardGroup cols={2}>
  <Card title="skill-creator" icon="sparkles">
    Powers the **Create With AI** flow. When skill creation is enabled on an agent and you click **Create With AI**, the agent uses this skill to guide you through skill creation step by step — naming, scoping, writing instructions, and validating the result.
  </Card>

  <Card title="gumcp-client" icon="plug">
    Enables agents to call your connected integrations (Gmail, Salesforce, Slack, Google Sheets, etc.) **directly from Python scripts** in the sandbox. This is what makes it possible to build skills that combine custom logic with real tool calls — e.g., a script that reads from Sheets, processes the data, and posts a summary to Slack.
  </Card>
</CardGroup>

<Info>Both system skills are **read-only** — they are maintained by Gumloop and updated automatically. You cannot edit or delete them. The `skill-creator` skill is only active when skill creation is enabled on the agent (via the **Skill Editing & Creation** toggle in Tools).</Info>

## Creating Skills

There are three ways to create a skill. Pick whichever feels most natural.

<Warning>
  **Naming Rules:** Skill names are validated by the backend and must follow these constraints:

  * Lowercase letters, digits, and hyphens only
  * No spaces, underscores, or uppercase letters
  * Maximum 64 characters
  * Cannot start or end with a hyphen
  * No consecutive hyphens

  ✅ `my-cool-skill` · ❌ `My_Skill`, `my skill`, `mySkill`
</Warning>

<Frame>
  <img src="https://mintcdn.com/agenthub/sLdxg5h_Tfdz2jJ1/images/skills_page.png?fit=max&auto=format&n=sLdxg5h_Tfdz2jJ1&q=85&s=5e7b22bac9ad617445c1fba58621ab0d" alt="Skills page showing the three creation methods: Create With AI, Upload Files, and Write Skill Instructions" width="1547" height="371" data-path="images/skills_page.png" />
</Frame>

<AccordionGroup>
  <Accordion title="Create With AI (Recommended)" icon="sparkles">
    The easiest way to get started. Click **Create Skill** → **Create With AI**, and a chat opens where the AI walks you through skill creation step by step.

    You describe what you want the skill to do, and the AI:

    1. Helps you nail down the scope and name
    2. Writes the instructions based on your description
    3. Adds any necessary scripts or reference files
    4. Validates everything to make sure it's properly formatted

    **Best for:** Anyone who isn't sure where to start, or wants a quick way to turn ideas into skills.
  </Accordion>

  <Accordion title="Write Skill Instructions" icon="pen">
    A simple form where you fill in three fields: **Name**, **Description**, and **Instructions**. Click **Create** and you're done.

    <Frame>
      <img src="https://mintcdn.com/agenthub/c-VvuM9eiDk84Uw6/images/add_a_skill.png?fit=max&auto=format&n=c-VvuM9eiDk84Uw6&q=85&s=0be76454a45f97846cfd5c022f3e00a7" alt="The Write Skill Instructions form showing name, description, and instructions fields" width="1550" height="1035" data-path="images/add_a_skill.png" />
    </Frame>

    **Best for:** Simple skills that are just instructions (no scripts or extra files needed). Great for brand voice guidelines, email templates, response formatting rules, etc.
  </Accordion>

  <Accordion title="Upload Files" icon="upload">
    Upload a `.md`, `.zip`, or `.skill` file containing a properly formatted `SKILL.md`. If you're uploading a `.zip`, it can include scripts, references, and assets alongside the `SKILL.md`.

    <Frame>
      <img src="https://mintcdn.com/agenthub/c-VvuM9eiDk84Uw6/images/agent_upload_skill_file.png?fit=max&auto=format&n=c-VvuM9eiDk84Uw6&q=85&s=35aeaaa7a1889559d2cb552468ffaf58" alt="Upload Skill File" width="1024" height="1230" data-path="images/agent_upload_skill_file.png" />
    </Frame>

    **Best for:** Technical users with existing documentation, SOPs, or code they want to package as a skill.
  </Accordion>
</AccordionGroup>

## What's Inside a Skill?

At its core, a skill is a folder containing a `SKILL.md` file with instructions your agent can read. Some skills also include helper scripts, reference docs, and templates.

```text theme={"dark"}
email-outreach-playbook/
  SKILL.md              ← Required: the instructions
  scripts/              ← Optional: executable code
    personalize_email.py
  references/           ← Optional: detailed docs
    email_templates.md
    prospect_research_checklist.md
  assets/               ← Optional: templates and files
    signature_template.html
```

<Tip>Most skills only need the `SKILL.md` file. You can always add scripts and references later as the skill grows.</Tip>

### Why This Structure Exists

The directory structure isn't just organizational — it reflects how the agent uses each part:

| Component         | What it's for                                                  | How the agent uses it                                                                                                  |
| ----------------- | -------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| **`SKILL.md`**    | Instructions, decision logic, context                          | Loaded directly into the agent's context window. Keep it under **500 lines** for token efficiency and focus.           |
| **`references/`** | Detailed documentation, lookup tables, API specs               | Read **on demand** when the agent needs specific details. Keeps `SKILL.md` lean.                                       |
| **`scripts/`**    | Deterministic logic — calculations, data transforms, API calls | **Executed in the sandbox** and the output is used in the agent's response. Never left to the agent to reason through. |
| **`assets/`**     | Static files used in output — templates, images                | Referenced by instructions or scripts when producing output.                                                           |

<Info>
  **Rule of thumb:** If the agent needs to *reason* through something (decisions, steps, context), it belongs in `SKILL.md` instructions. If the agent needs to *calculate or process* something and the result must be correct every time, it belongs in a `scripts/` file.

  The 500-line soft limit on `SKILL.md` is enforced as a validation warning — skills exceeding it still work, but you should move detailed content to `references/` to keep the agent's context focused.
</Info>

### The SKILL.md File

Every skill starts with a short header (called "frontmatter") that tells the agent what the skill does and when to use it, followed by the actual instructions:

```yaml theme={"dark"}
---
name: email-outreach-playbook
description: Draft personalized cold outreach emails for sales prospects.
  Use when the user asks to contact or email a prospect.
---

## Overview
This skill guides the outreach process for cold prospects.

## Step 1: Research the Prospect
- Look up the prospect's company and role
- Note recent news or achievements to personalize the email

## Step 2: Draft the Email
- Use the greeting: "Hey {first_name},"
- Reference something specific about their company
- Keep it under 150 words
...
```

<Warning>
  The **description** is the most important part. Your agent uses it to decide whether to load the skill for the current task. Be specific about **what** the skill does and **when** to use it. A vague description means the agent might never find your skill.

  **Description constraints:** Maximum 1,024 characters. No angle brackets allowed.
</Warning>

<Accordion title="Optional frontmatter fields" icon="sliders">
  Beyond `name` and `description`, you can add these optional fields to your frontmatter:

  | Field                    | What it does                                                                                                                                                       | Example                                |
  | ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------- |
  | **`icon`**               | Sets the skill's icon in the UI. Uses [Lucide](https://lucide.dev) icon names in kebab-case.                                                                       | `icon: chart-line`                     |
  | **`color`**              | Sets the skill's accent color in the UI.                                                                                                                           | `color: Blue`                          |
  | **`related_server_ids`** | Links the skill to specific integrations, enabling integration-scoped discovery. When set, agents working with that integration are more likely to find the skill. | `related_server_ids: [gsheets, slack]` |

  Available colors: `Grey`, `Blue`, `Green`, `Orange`, `Red`, `Yellow`, `Teal`, `Pink`, `Purple`, `Bronze`, `Black`

  ```yaml theme={"dark"}
  ---
  name: weekly-sales-summary
  description: Generate a weekly sales performance summary from Google Sheets data.
  icon: chart-line
  color: Green
  related_server_ids: [gsheets, slack]
  ---
  ```

  <Info>**Server IDs** must match the actual integration identifiers, not display names. For example, Google Sheets is `gsheets`, not `google-sheets`. The agent can discover correct IDs by running `python3 /home/user/skills/gumcp-client/scripts/list_tools.py` in the sandbox.</Info>
</Accordion>

## Attaching Skills to Agents

Once you've created a skill, attach it to the agents that should use it.

<Frame>
  <img src="https://mintcdn.com/agenthub/c-VvuM9eiDk84Uw6/images/agent_add_skill.png?fit=max&auto=format&n=c-VvuM9eiDk84Uw6&q=85&s=3b4f668796ad2fba7dbb5d6adf2ca800" alt="The Skills section in agent configuration showing the + Skill button" width="988" height="422" data-path="images/agent_add_skill.png" />
</Frame>

<Steps>
  <Step title="Open agent configuration">
    Go to your agent's configuration page.
  </Step>

  <Step title="Find the Skills section">
    Scroll down to the **Skills** section.
  </Step>

  <Step title="Add a skill">
    Click **+ Skill** and select a skill from the list (or create a new one right there).
  </Step>
</Steps>

That's it. Your agent can now find and use that skill whenever it's relevant.

<Info>
  [Gumball](/core-concepts/gumball), your personal agent, needs no attaching — it discovers every skill you can access on its own. See [What Gumball can discover](#what-gumball-can-discover).

  **Gumball vs. Custom Agent skill behavior:** Gumball finds skills dynamically using semantic search, matching your request against skill descriptions. Custom agents only see skills explicitly attached to them (listed in the system prompt). A practical consequence: when an agent creates a new skill during a conversation, on a custom agent it gets auto-attached; on Gumball it is added to your library but not auto-attached to anything.
</Info>

### What Gumball can discover

Gumball searches everything you can reach, resolved from your actual access at the start of each conversation:

| Scope                  | Included                                                                           |
| ---------------------- | ---------------------------------------------------------------------------------- |
| **Your own skills**    | Every skill in the space you're chatting from                                      |
| **Your teams' skills** | Skills owned by any [team](/core-concepts/teams) you belong to                     |
| **Shared with you**    | Skills shared with you directly, with your organization, or with one of your teams |

If two skills have the same name, your own wins, then the more-used one. **Personalization** skills (Tone and Design) are the exception — they stay private to their author, so a teammate's writing style never leaks into your agent.

<Info>Discovery does not copy skills to you: you keep whatever [role](#sharing-roles) you were given. Gumball can edit a shared skill in place if you have edit access, and is blocked from editing it if you don't.</Info>

<Note>Gumball loads shared skills **on demand**. Only skills already pulled in are present in its sandbox, so a skill can appear there mid-conversation after a search finds it — the agent uses skill discovery, not a directory listing, to know what's available. Nothing to configure; it just means the full set does not have to be copied up front, however large your library gets.</Note>

## Skills That Improve Over Time

This is the most powerful part of skills, and what makes them fundamentally different from static instructions.

**Your agent can edit, improve, and create skills on its own.**

This behavior is controlled by the **Skill Editing & Creation** toggle in your agent's Tools configuration. It's **enabled by default**.

<Frame>
  <img src="https://mintcdn.com/agenthub/WuMv2bE3jTJldGq-/images/skills_editing_and_creation.png?fit=max&auto=format&n=WuMv2bE3jTJldGq-&q=85&s=693e7e37edd04bacc3cff04aed0e2931" alt="Skill Editing & Creation toggle enabled in agent tools" width="912" height="312" data-path="images/skills_editing_and_creation.png" />
</Frame>

* **Enabled (default):** The agent can create new skills, update existing ones, and fix its own mistakes. This is the recommended option because it lets the agent iterate, learn from corrections, and continuously improve.
* **Disabled:** The agent can still **read and use** skills you've already attached, but cannot create or modify skills. Choose this for tighter control over your skill library.

<Info>**This toggle is global** — it applies to all skills on the agent, not per-skill. There is no way to lock individual skills while keeping others editable. If you need certain skills to be immutable, disable the toggle entirely.</Info>

<Note>**Organizations can restrict skill creation.** On enterprise plans, admins can turn off **Skill creation** for a [custom role](/enterprise-features/user_groups#features-tab). For members of a restricted role, an agent that tries to save a *new* skill skips it and says skill creation has been restricted by their organization admin — even with the agent's own toggle enabled. Reading, using, and updating skills that already exist are unaffected.</Note>

<Tip>Keep this enabled to unlock the full compound learning effect described below. An agent that can refine its own playbooks gets better with every conversation.</Tip>

<Tabs>
  <Tab title="Fixing a template">
    > **You:** "Draft an outreach email to the VP at Acme Corp."<br />
    > **Agent:** *\[drafts email using the outreach-playbook skill]*<br />
    > **You:** "Actually, we don't use that greeting anymore. Use 'Hey \{first\_name}' instead."<br />
    > **Agent:** "Got it. I've updated the outreach playbook skill with the new greeting."

    Next time the agent drafts an outreach email, it uses the corrected greeting. **The fix is permanent.** It's saved back to the skill.
  </Tab>

  <Tab title="Learning from experience">
    > **Conversation 1:** You ask the agent to summarize your emails. It does a basic job.<br />
    > **You:** "Group them by sender."<br />
    > **Agent:** *\[creates an email-summary skill with the grouping rule]*<br /><br />
    > **Conversation 2:** You ask the agent to summarize your emails. It groups by sender automatically.<br />
    > **You:** "Also flag anything from my boss as urgent."<br />
    > **Agent:** *\[updates the skill to add the urgent-flagging rule]*<br /><br />
    > **Conversation 3:** You ask the agent to summarize your emails. It groups by sender AND flags your boss's emails. No corrections needed.
  </Tab>

  <Tab title="Creating a skill from scratch">
    > **You:** "Every Monday I need you to pull last week's support tickets from Zendesk, categorize them, and post a summary to #support-updates in Slack."<br />
    > **Agent:** "I'll create a skill to remember this workflow."<br />
    > *\[Creates a `weekly-support-summary` skill with the full process, automatically attached to this agent]*

    The agent now remembers this process for every future Monday.
  </Tab>
</Tabs>

<Info>**Version history:** You can view a full timeline of file-level changes (added, modified, removed) in the **Edits** tab on each skill's detail page. Each version shows what changed and when, along with which agent made the edit. This helps you track how a skill evolves over time. We still recommend manually copying your skill instructions before enabling free agent editing on critical skills.</Info>

<Info>**Concurrent edits:** If two conversations edit the same skill simultaneously, the last one to complete wins and overwrites the other. Teams with multiple users on shared agents should be aware of this race condition.</Info>

### The Compound Effect

Every correction, every new instruction, every "actually, do it this way" compounds over time:

| Timeline    | What the skill looks like                                                      |
| ----------- | ------------------------------------------------------------------------------ |
| **Day 1**   | A basic outreach skill with a simple template.                                 |
| **Week 1**  | Includes templates for 3 scenarios and an updated greeting.                    |
| **Month 1** | Has 10 templates, a research checklist, follow-up rules, and a scoring script. |
| **Month 6** | Essentially a senior salesperson's playbook.                                   |

This happens naturally through normal usage. You just tell the agent what to do differently, and it updates its own playbook.

<Tip>Think of skills as a **living knowledge base**. Every interaction is a chance for the agent to learn and get better. System prompts are static. Tools are static. Skills are alive.</Tip>

## FAQs

<AccordionGroup>
  <Accordion title="Do skills give my agent memory?" icon="copy">
    Skills can feel like memory because your agent can **update a skill** after you correct it.

    Technically, skills are not conversation memory. Skills are saved playbooks your agent can reuse later, and the agent still needs to **load the skill again** in future conversations when it is relevant.
  </Accordion>

  <Accordion title="Why did my agent not use a skill I created?" icon="bullseye">
    Skills are **loaded on demand**. Your agent sees skill names and descriptions first, then decides what to load based on relevance.

    Common fixes:

    * Make the skill **description** more specific about what it does and when to use it.
    * Make sure the skill is attached to the right agent. ([Gumball](/core-concepts/gumball) can access all skills in your space. Custom agents only see attached skills.)
  </Accordion>

  <Accordion title="Why did my skill update not save after I corrected the agent?" icon="crosshairs">
    A few edge cases can prevent changes from being saved:

    * **Invalid `SKILL.md` frontmatter**: If `SKILL.md` is missing required fields like `name` or `description`, or the YAML is malformed, the skill update is skipped and the previous version stays intact.
    * **Rename mismatch**: If the skill folder name and the `name:` field do not match, saving is skipped.
    * **Concurrent edits**: If two conversations edit the same skill at the same time, the last conversation to finish wins.
    * **Conversation ended abnormally**: Skill edits are saved at the end of a completed agent turn. If the conversation crashes, is interrupted, or ends before the agent's turn finishes, any skill changes made during that session may not have been saved. If you suspect this happened, check the skill and re-apply any corrections in a new conversation.

    You can review what changed in the **Edits** tab on the skill's detail page, which shows a file-level diff for each version.
  </Accordion>
</AccordionGroup>

## What Kinds of Skills Should You Build?

<CardGroup cols={2}>
  <Card title="Workflow Skills" icon="list-check">
    Multi-step business processes. Sales outreach sequences, onboarding checklists, content publishing workflows.
  </Card>

  <Card title="Knowledge Skills" icon="book">
    Domain expertise. Product features, pricing tiers, competitive positioning, company policies.
  </Card>

  <Card title="Template Skills" icon="file-lines">
    Consistent formatting. Email templates, report formats, Slack update structures, CRM field mapping.
  </Card>

  <Card title="Automation Skills" icon="code">
    Deterministic code the agent runs in the sandbox. Data transformations, metric calculations, invoice validation.
  </Card>
</CardGroup>

### Calling Integrations From Scripts

Your skill scripts can do more than local calculations — they can **call your connected integrations** (Google Sheets, Gmail, Slack, Salesforce, etc.) directly from Python using the built-in `gumcp-client` system skill.

In plain terms: you can write a Python script that reads data from one service, processes it, and writes the result to another — all in one go, without the agent needing to figure out each step.

<Info>**How it works under the hood:** The `gumcp-client` skill provides a Python `Client` class that's pre-installed in every agent sandbox. Your script imports it, creates a client with credentials that are already set up as environment variables, and calls tools using the `server__tool_name` format (e.g. `gsheets__read_spreadsheet`, `slack__send_message`). The agent just runs the script and uses the printed output.</Info>

<img src="https://mintcdn.com/agenthub/ln_NH6Ds5YLILxjJ/images/gumcp_client_tool_call.png?fit=max&auto=format&n=ln_NH6Ds5YLILxjJ&q=85&s=b7c1620e3b3e360519354c52113ab29c" alt="GuMCP client tool call screenshot" style={{ maxHeight: '500px', border: '1px solid black', borderRadius: '8px' }} width="1576" height="1414" data-path="images/gumcp_client_tool_call.png" />

<Accordion title="Example: Summarize a Google Sheet and post to Slack" icon="table">
  Say you want a skill that pulls sales data from a Google Sheet, computes totals, and gives the agent a clean summary to post. Here's how it works:

  **`SKILL.md`** tells the agent when and how:

  ```text theme={"dark"}
  ---
  name: weekly-sales-summary
  description: Generate a weekly sales summary from the team spreadsheet.
    Use when the user asks for sales numbers, weekly summary, or
    team performance update.
  related_server_ids: [gsheets, slack]
  ---

  ## Steps
  1. Run: python3 scripts/generate_summary.py
  2. Review the printed output
  3. Post the summary to the #sales Slack channel (or present to the user)
  ```

  **`scripts/generate_summary.py`** does the heavy lifting:

  ```python theme={"dark"}
  import json, os
  from gumcp_client import Client

  def get_client():
      return Client(
          user_id=os.getenv("GUMCP_USER_ID"),
          gumcp_api_key=os.getenv("GUMCP_API_KEY"),
          base_url=os.getenv("GUMCP_BASE_URL"),
      )

  with get_client() as client:
      # Read the raw data from your team spreadsheet
      raw = client.call_tool(
          "gsheets__read_spreadsheet",
          dict(spreadsheet_id="1ABC...", range="Sales!A1:F100")
      )
      data = json.loads(raw[0])
      rows = data.get("values", [])

      # Process with Python — exact math, no guessing
      total_revenue = sum(float(r[3]) for r in rows[1:])
      top_rep = max(rows[1:], key=lambda r: float(r[3]))

      print("Total revenue: $" + format(total_revenue, ",.2f"))
      print("Top performer: " + top_rep[0] + " ($" + format(float(top_rep[3]), ",.2f") + ")")
  ```

  The agent runs this script in its sandbox, gets the printed output, and uses it in its response. If you want it posted to Slack, the agent can do that as a separate step (or you could add the Slack post directly to the script).

  **Why this is useful:** The spreadsheet might have hundreds of rows. If the agent called the Google Sheets tool directly, all that raw data would land in the agent's context window, costing tokens and potentially getting truncated. With a script, the data stays in the sandbox — only the summary comes back.
</Accordion>

<Accordion title="Example: Read a Google Doc and extract action items" icon="file-lines">
  A skill that reads meeting notes from Google Docs and pulls out the to-do items:

  **`scripts/extract_action_items.py`:**

  ```python theme={"dark"}
  import json, os
  from gumcp_client import Client

  def get_client():
      return Client(
          user_id=os.getenv("GUMCP_USER_ID"),
          gumcp_api_key=os.getenv("GUMCP_API_KEY"),
          base_url=os.getenv("GUMCP_BASE_URL"),
      )

  with get_client() as client:
      raw = client.call_tool(
          "gdocs__read_document",
          dict(document_id="1XYZ...")
      )
      content = json.loads(raw[0])

      # Parse the document text with Python
      lines = content.get("text", "").split("\n")
      action_items = [l for l in lines if l.strip().startswith("- [ ]")]

      for item in action_items:
          print(item)
  ```

  The agent reads the printed output and can then create tasks in your project management tool, send reminders via Slack, or just present them to you.
</Accordion>

<Tip>**Server IDs aren't always obvious.** Google Sheets is `gsheets`, Google Docs is `gdocs`, Google Calendar is `gcalendar`, Google BigQuery is `gbigquery`. The agent can run `python3 /home/user/skills/gumcp-client/scripts/list_tools.py` in its sandbox to discover the exact IDs and available tools for your connected integrations.</Tip>

#### When to use scripts vs. direct tool calls

Your agent can already call integrations directly — "read this spreadsheet" or "send this Slack message" work fine as regular tool calls. **You don't need scripts for most integration tasks.** Both approaches have strengths:

|                   | Direct tool calls                                            | Scripts in the sandbox                                       |
| ----------------- | ------------------------------------------------------------ | ------------------------------------------------------------ |
| **Best for**      | Simple, one-off actions                                      | Multi-step processing, large data, exact calculations        |
| **Example**       | "Send this email", "Post to Slack"                           | "Read 500 rows from Sheets, compute totals, format a report" |
| **Agent sees**    | The full tool response — can reason about it, ask follow-ups | Only the printed output — data stays in the sandbox          |
| **Repeatability** | Agent decides approach each time                             | Script runs identically every time, saved in the skill       |
| **Token cost**    | Full response goes into context window                       | Data processed in sandbox, only summary enters context       |
| **Flexibility**   | Agent can adapt based on what it gets back                   | Logic is fixed in code — great for precision, less adaptive  |

**Start with direct tool calls.** They're simpler, and the agent can reason about the results conversationally. Move logic into a script when you hit one of these cases:

* The tool response is very large and you only need a subset (avoids flooding the agent's context)
* You need to chain multiple calls together (read → filter → compute → write) without round-tripping
* The calculation must be exact every time (Python math vs. the agent estimating)
* The same process should run identically across conversations — save it once, reuse everywhere

<Info>**Most skills don't need scripts or integration calls.** The majority of useful skills are just well-written instructions and templates. Integration scripts are a power feature for when you need them, not a starting point.</Info>

<Accordion title="See detailed examples for each skill type">
  **Workflow Skill: Sales outreach sequence**

  * Step 1: Research the prospect
  * Step 2: Personalize the email using a template
  * Step 3: Log the activity in Salesforce
  * Step 4: Set a follow-up reminder for 3 days later

  **Knowledge Skill: Product knowledge base**

  * Product features and pricing tiers
  * Common customer questions and answers
  * Competitive positioning

  **Template Skill: Email templates by scenario**

  * Cold outreach template
  * Follow-up template
  * Meeting request template
  * Thank you template

  **Automation Skill: Weekly analytics report**

  * `SKILL.md`: Report structure and formatting rules
  * `scripts/calculate_metrics.py`: Pulls data and computes KPIs
  * `assets/report_template.md`: The markdown template

  **Integration-Specific Skill: Salesforce deal management**

  * When to create vs update opportunities
  * Required fields for each stage
  * Naming conventions for deals
  * When to notify the team
</Accordion>

## Skill Examples (Inspiration)

If you want to see real skill folders, complete with `SKILL.md` files and supporting resources, browse these public examples:

* [Anthropic Skills examples repo](https://github.com/anthropics/skills/tree/main/skills)

<Info>If you have used Claude Skills before, the packaging concept is very similar: a folder with a `Skill.md` file and optional resources. Gumloop uses `SKILL.md` and adds agent-specific features like attaching skills to agents and letting agents improve skills over time. For reference, see [How to create custom Skills](https://support.claude.com/en/articles/12512198-how-to-create-custom-skills).</Info>

## Best Practices

<AccordionGroup>
  <Accordion title="Write specific descriptions" icon="bullseye">
    The description is how your agent decides whether to load a skill. Be specific about **what** it does and **when** to use it.

    ✅ **Good:** "Generate weekly sales performance reports from BigQuery data, formatted as Markdown tables with week-over-week comparisons. Use when the user asks for sales reports or weekly metrics."

    ❌ **Bad:** "Helps with reports."

    A vague description means the agent might never find your skill.
  </Accordion>

  <Accordion title="Keep skills focused" icon="crosshairs">
    One skill should do one thing well. Don't create a mega-skill called "everything" with 3,000 lines of instructions.

    Instead of one "sales-operations" skill, create:

    * `outreach-playbook` (email sequences)
    * `crm-logging` (Salesforce field mapping)
    * `deal-qualification` (scoring criteria)

    Focused skills are easier to maintain, share, and compose.
  </Accordion>

  <Accordion title="Include examples in your instructions" icon="lightbulb">
    Show the agent what good output looks like. Include sample inputs and expected outputs in your `SKILL.md`.

    ```text theme={"dark"}
    ## Example
    Input: "Draft an email to John at Acme Corp"
    Expected output:
    - Subject: Quick question about [specific thing]
    - Greeting: Hey John,
    - Body: 2-3 sentences, personalized
    - CTA: One clear ask
    ```

    Agents learn better from examples than abstract descriptions.
  </Accordion>

  <Accordion title="Use scripts for things that must be exact" icon="calculator">
    If the skill involves math, data formatting, or any logic that needs to be 100% correct every time, put it in a Python script in the `scripts/` directory. Don't rely on the agent to reason through calculations.

    **The pattern works like this:**

    1. **`SKILL.md`** describes *when* and *why* to run the script (e.g., "After collecting the sales data, run the commission calculator to compute payouts")
    2. **The script** handles the *how* — deterministic logic that produces the same correct result every time
    3. **The agent** executes the script in its sandbox, reads the output, and uses it in the response

    This separation means the agent handles context and judgment (which deals to include, how to present results) while the script handles precision (the actual math).

    **Example: Sales commission calculator**

    `SKILL.md` instructions:

    ```text theme={"dark"}
    ## Computing Commissions
    When the user asks for commission calculations:
    1. Collect the deal data (rep name, deal value, deal stage)
    2. Run: python3 scripts/calculate_commission.py --input deals.json
    3. Present the results in a table, highlighting any reps above quota
    ```

    `scripts/calculate_commission.py` handles the actual math:

    ```python theme={"dark"}
    # Tiered commission rates, quota thresholds, accelerators
    # — all encoded in code, not left to the agent
    ```

    Other good candidates for scripts: tax calculations, metric aggregations, data transformations, invoice validation, and any formula-driven logic.
  </Accordion>

  <Accordion title="Start simple, let the agent improve it" icon="seedling">
    You don't need to write the perfect skill on day one. Create a basic version with core instructions, then let the agent refine it through usage.

    Create a simple skill via "Write Skill Instructions" → use it a few times → give feedback → the agent updates the skill → repeat.
  </Accordion>

  <Accordion title="Don't duplicate the system prompt" icon="copy">
    If your system prompt says "Always be professional" and a skill says "Use casual language," the agent gets confused.

    **System prompt** = who the agent is (always active).

    **Skills** = how to do specific tasks (loaded on demand).

    Keep them separate and non-contradictory.
  </Accordion>

  <Accordion title="Keep SKILL.md under 500 lines" icon="file-lines">
    Skills exceeding 500 lines trigger a validation warning and consume significant tokens when loaded. Move detailed content to the `references/` directory instead — the agent can selectively read reference files as needed rather than loading everything at once.
  </Accordion>

  <Accordion title="Be mindful of skill scale" icon="gauge">
    All attached skills are listed in the agent's system prompt (roughly 50–100 tokens per skill). Attaching a very large number of skills to a single custom agent adds meaningful token overhead on every request. Keep attached skills focused and relevant; use [Gumball](/core-concepts/gumball) if you need every skill you can access, since it discovers them on demand instead of listing them in the prompt.
  </Accordion>

  <Accordion title="Avoid these common mistakes" icon="triangle-exclamation">
    * **Don't create catch-all mega-skills** with thousands of lines — break them into focused, single-purpose skills.
    * **Don't duplicate system prompt content in a skill** — if it applies to every interaction, it belongs in the system prompt.
    * **Don't create skills that just restate tool documentation** — skills should add your business logic and process, not explain how Gmail works.
    * **Don't put time-sensitive information in skills without labeling it** — "Q4 promotion ends Dec 31" will be wrong in January.
  </Accordion>
</AccordionGroup>

## Managing Skills

You can view, search, and manage all your skills from the **Skills** page in the sidebar. Each skill shows its name, description, connected apps, last edit time, and creator.

<Frame>
  <img src="https://mintcdn.com/agenthub/sLdxg5h_Tfdz2jJ1/images/skills_page.png?fit=max&auto=format&n=sLdxg5h_Tfdz2jJ1&q=85&s=5e7b22bac9ad617445c1fba58621ab0d" alt="The Skills page showing skill search, creation, and management" width="1547" height="371" data-path="images/skills_page.png" />
</Frame>

### Finding Skills

Use the tabs at the top of the Skills page to switch between views:

| Tab                | What It Shows                                                             |
| ------------------ | ------------------------------------------------------------------------- |
| **Mine**           | Skills you created                                                        |
| **Shared with me** | Skills that others have shared with you directly or via your organization |
| **Organization**   | All skills visible to your entire organization                            |

<Frame>
  <img src="https://mintcdn.com/agenthub/aLkNVzSb33_L2qU1/images/skills_shared_with_me.png?fit=max&auto=format&n=aLkNVzSb33_L2qU1&q=85&s=8ac34ba38933bbe57b8a42fda526aeb0" alt="Skills page showing the Shared with me tab" width="2438" height="384" data-path="images/skills_shared_with_me.png" />
</Frame>

### Popular Skills

At the top of the Skills page, a **Popular Skills** carousel highlights the most-used skills in your workspace. These are ranked automatically based on usage across your team — skills that are referenced, run, or viewed most frequently appear here. Click any card to open the skill's detail page.

<Frame>
  <img src="https://mintcdn.com/agenthub/SK3K6D_B07sbntsc/images/skills_popular.png?fit=max&auto=format&n=SK3K6D_B07sbntsc&q=85&s=079489fbdfa9000649b1d0763494ed13" alt="Popular Skills carousel showing the most-used skills in the workspace" width="1284" height="680" data-path="images/skills_popular.png" />
</Frame>

### Searching and Filtering

The Skills page includes a search bar and a **Filters** panel to help you find exactly what you need.

**Search** works server-side — type a query and results are filtered by skill name and description as you type (with a short debounce so it doesn't fire on every keystroke).

Click the **Filters** button to open the filter panel. Available filters:

<Frame>
  <img src="https://mintcdn.com/agenthub/SK3K6D_B07sbntsc/images/skills_filters.png?fit=max&auto=format&n=SK3K6D_B07sbntsc&q=85&s=3d0ead5f42a91b1d3fc9c15b03160eef" alt="Skills filter panel showing Sort by, Creator, App, In use by, and Not in use options" width="744" height="706" data-path="images/skills_filters.png" />
</Frame>

| Filter         | What it does                                                                                                                                         |
| -------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Sort by**    | Order results by **Newest**, **Oldest**, **Most Used**, or **Popular**. Defaults to Newest.                                                          |
| **Creator**    | Show only skills created by a specific team member.                                                                                                  |
| **App**        | Show only skills linked to a specific integration (e.g., Google Sheets, Slack). This uses the `related_server_ids` field in the skill's frontmatter. |
| **In use by**  | Show only skills attached to a specific agent.                                                                                                       |
| **Not in use** | Toggle to show only skills that are not currently attached to any agent — useful for cleanup.                                                        |

<Info>The **In use by** and **Not in use** filters are mutually exclusive. Enabling the "Not in use" toggle automatically clears any agent filter, and vice versa.</Info>

All filters are reflected in the URL as query parameters, so you can bookmark or share filtered views with your team. A badge on the Filters button shows the number of active filters, and you can clear them all at once with the **Clear Filters** button inside the panel.

### Skill Detail Page

Clicking on a skill opens its detail page, which has a left panel with metadata and a right panel showing file contents. The left panel is organized into four tabs:

<Frame>
  <img src="https://mintcdn.com/agenthub/SK3K6D_B07sbntsc/images/skill_detail_page.png?fit=max&auto=format&n=SK3K6D_B07sbntsc&q=85&s=fef05feba167238adf07c39210ccb34b" alt="Skill detail page showing the Files tab with SKILL.md, references, and scripts folders" width="624" height="478" data-path="images/skill_detail_page.png" />
</Frame>

<Tabs>
  <Tab title="Files">
    The default view. Shows the skill's file tree on the left, with `SKILL.md`, plus any `references/`, `scripts/`, and `assets/` folders. Click a file to preview its contents in the right panel. You can also download the skill from the header.

    #### Editing Files Directly

    You can edit any text file in a skill directly from the browser. Click the edit icon in the top-right corner of the file preview to switch to edit mode.

    <Frame>
      <img src="https://mintcdn.com/agenthub/K_k-tD3TARMHQhGo/images/skills/skill-file-edit.png?fit=max&auto=format&n=K_k-tD3TARMHQhGo&q=85&s=7bf73fc6531e4a7ba958383eb8c53d71" alt="Skill file editor showing the SKILL.md file open for editing with the edit toggle in the top-right corner" width="2496" height="1236" data-path="images/skills/skill-file-edit.png" />
    </Frame>

    The file viewer has three modes, toggled by the icons in the top-right:

    | Icon        | Mode              | What it does                                   |
    | ----------- | ----------------- | ---------------------------------------------- |
    | File icon   | **Preview**       | Read-only view of the file (default)           |
    | Pencil icon | **Edit**          | Edit the file contents directly                |
    | Diff icon   | **Local Changes** | See a diff of all unsaved changes across files |

    You can edit multiple files before saving. When you are ready, click **Commit** to save all your changes as a single new version. This creates a new entry in the Edits tab so you can always see what changed and roll back if needed.

    <Tip>Use direct editing for quick fixes to instructions, updating templates, or tweaking scripts. For larger rewrites, you might prefer re-uploading a `.zip` file with all your changes.</Tip>
  </Tab>

  <Tab title="Edits">
    A timeline of all file-level changes to the skill. Each entry shows what happened (file added, modified, or removed), which file was affected, when it happened, and who or which agent made the change. Click an entry to jump to that file in the preview.
  </Tab>

  <Tab title="Activity">
    A timeline of all usage events for the skill, including views, script runs, and edits. Each entry shows the action type, timestamp, and the user or agent involved. This gives you visibility into how actively a skill is being used and by whom.
  </Tab>

  <Tab title="Used By">
    Shows which **agents** and **people** are using this skill, along with usage counts. Agent entries link directly to the agent's configuration page. This is useful for understanding the impact of a skill before editing or deleting it.
  </Tab>
</Tabs>

### Usage Tracking

Gumloop automatically tracks usage metrics for every skill:

* **Usage count** — how many times the skill has been used (script runs, referenced in conversations)
* **View count** — how many times the skill has been opened or viewed
* **Last used** — when the skill was last used by any agent or user

These metrics power the **Popular Skills** carousel and the **Most Used** / **Popular** sort options. They also appear in the skill detail page's Activity and Used By tabs.

### Sharing Skills

<Tip>
  Admins can centrally manage a shared skill library for the whole
  organization — including keeping skills in sync with a GitHub repository — with
  [Organization Skills](/core-concepts/organization_skills).
</Tip>

You can share skills with specific people, your team, your organization, or anyone with the link. Each person you share with gets a **role** that controls what they can do with the skill.

To share a skill, go to the [Skills page](https://www.gumloop.com/personal/skills), find the skill you want to share, click the **three-dot menu** (⋮), and select **Share**. You can also click the **share icon** in the skill detail view header.

<Frame>
  <img src="https://mintcdn.com/agenthub/xUdp7XtV01LhnOnb/images/skill_share_dialog.png?fit=max&auto=format&n=xUdp7XtV01LhnOnb&q=85&s=a6495eaf705a3dce13a08ebe0bc004be" alt="Skill share dialog showing role options: Editor, Viewer, and Use Only" width="1448" height="1028" data-path="images/skill_share_dialog.png" />
</Frame>

#### Sharing Roles

Skills support four roles. Each role builds on the one below it:

| Role         | What they can do                                                                                                                                                                                                         |
| ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Owner**    | Full control. Can edit, delete, share with others, and manage general access. Cannot be removed.                                                                                                                         |
| **Editor**   | Can view the skill's files and source code, edit the skill, delete it, and manage sharing (add/remove Editors, Viewers, and Use Only users).                                                                             |
| **Viewer**   | Can view the skill's files, source code, and configuration. Can use the skill in agents. Cannot edit or delete.                                                                                                          |
| **Use Only** | Can use the skill in agents, but **cannot** see the skill's files, source code, or configuration. This is ideal for sharing a skill with people who should benefit from it without seeing your proprietary instructions. |

<Info>
  Every role (including Use Only) can **invoke** the skill, meaning it can be attached to and used by agents. The difference is what you can *see* and *change*.
</Info>

#### Sharing with Specific People

1. Open the Share dialog for the skill
2. Type an email address in the **Add people** field
3. Click the dropdown arrow on the **Share** button to choose a role (Editor, Viewer, or Use Only)
4. Click **Share**

The person will get access immediately. You can change their role or remove them at any time from the same dialog.

#### General Access

General Access controls who can access the skill *without* being explicitly added by email. The options depend on where the skill lives:

| Setting              | Who gets access                                                 |
| -------------------- | --------------------------------------------------------------- |
| **Restricted**       | Only people you've explicitly added (personal skills only)      |
| **Team**             | All members of the team the skill belongs to (team skills only) |
| **Organization**     | All members of your organization                                |
| **Anyone with link** | Anyone, including people without a Gumloop account              |

When you set General Access to Team, Organization, or Anyone, you also choose which **role** that audience gets (Editor, Viewer, or Use Only).

<Warning>
  **Team skills cannot be set to Restricted.** If a skill lives in a team, the minimum access level is Team. All team members will have access.
</Warning>

#### Requesting Access

If someone tries to open a skill they don't have access to, they'll see an **access gate** with a **Request Access** button. They can choose which role they'd like (Viewer, Editor, or Use Only). The skill owner or an editor receives a notification and can approve or deny the request with one click.

#### Examples

Here are some common sharing patterns:

<AccordionGroup>
  <Accordion title="Share a skill with your team for everyone to use" icon="users">
    Open the Share dialog, set **General Access** to **Team**, and pick the **Use Only** role. Everyone on the team can now use this skill in their agents, but they can't see or modify your instructions.
  </Accordion>

  <Accordion title="Let a colleague collaborate on a skill" icon="pencil">
    Open the Share dialog, add their email, and select **Editor**. They can now view the source, make edits, and even share it with others.
  </Accordion>

  <Accordion title="Share a skill publicly as a template" icon="globe">
    Set **General Access** to **Anyone with link** and select **Viewer**. Anyone with the link can see the skill's files and use it, but can't modify your original.
  </Accordion>

  <Accordion title="Protect proprietary logic while letting others benefit" icon="lock">
    Add specific users with the **Use Only** role. They can attach the skill to their agents and benefit from its instructions, but they'll never see the underlying files, prompts, or scripts.
  </Accordion>
</AccordionGroup>

#### Sharing FAQ

<AccordionGroup>
  <Accordion title="Where is the Share button?">
    On the [Skills page](https://www.gumloop.com/personal/skills), click the **three-dot menu** (⋮) on any skill you own or can manage, and select **Share**. You can also find the share icon in the header when viewing a skill's detail page.
  </Accordion>

  <Accordion title="Who can share a skill?">
    Only **Owners** and **Editors** can manage sharing. Viewers and Use Only users cannot add or remove others.
  </Accordion>

  <Accordion title="Can a Use Only user see my skill's source code or files?">
    No. Use Only users can **only** use the skill in agents. They cannot view the skill's files, instructions, or configuration. They see a message that says "Skill details unavailable" if they try to open the skill detail page.
  </Accordion>

  <Accordion title="Can I share a skill with someone outside my organization?">
    Yes. You can share with any Gumloop user by email. They don't need to be on your team or in your organization.
  </Accordion>

  <Accordion title="What happens if I remove someone's access?">
    They immediately lose access to the skill. If the skill was attached to one of their agents, the agent will no longer be able to load that skill.
  </Accordion>

  <Accordion title="Can I change someone's role after sharing?">
    Yes. Open the Share dialog, click the role label next to their name, and select a new role. The change takes effect immediately.
  </Accordion>

  <Accordion title="Can I transfer ownership of a skill?">
    Ownership is assigned at creation time. To transfer ownership, contact Gumloop support.
  </Accordion>
</AccordionGroup>

<AccordionGroup>
  <Accordion title="Downloading skill files" icon="download">
    You can download a skill's files directly from the Skills page. Click the three-dot menu on any skill and select **Download**.

    <Frame>
      <img src="https://mintcdn.com/agenthub/EzhNhqCE9eTp9eMB/images/download_skill_file.png?fit=max&auto=format&n=EzhNhqCE9eTp9eMB&q=85&s=bbbfa09c9f0bd4f0629033511419bc2e" alt="Three-dot menu on a skill showing the Download option" width="2370" height="446" data-path="images/download_skill_file.png" />
    </Frame>
  </Accordion>

  <Accordion title="Renaming and deleting" icon="trash">
    From the three-dot menu on any skill, you can rename or delete it (requires Editor or Owner role). Deleted skills are soft-deleted (marked inactive rather than permanently removed), so they can potentially be recovered by Gumloop support if needed.
  </Accordion>
</AccordionGroup>

## Next Steps

<CardGroup cols={2}>
  <Card title="Agents Overview" icon="robot" href="/core-concepts/agents">
    Learn how to build and configure agents with tools, instructions, and skills
  </Card>

  <Card title="Using Agents in Slack" icon="slack" href="/core-concepts/agents_slack">
    Deploy your skilled agents to Slack channels for team-wide access
  </Card>
</CardGroup>
