> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Rate Limits

> Understand concurrency limits for workflows and agents, how they are calculated, and what happens when you hit them.

Rate limits control how many workflows and agent interactions can run **at the same time**. They do not limit how many you can run per day — only how many can be active simultaneously.

## Quick Reference

|                       | Workflows                         | Agents                                     |
| --------------------- | --------------------------------- | ------------------------------------------ |
| **Pro**               | 5 concurrent runs                 | 25 concurrent interactions                 |
| **Enterprise**        | 15 concurrent runs (customizable) | 100 concurrent interactions (customizable) |
| **When limit is hit** | Queued (Enterprise) or rejected   | Queued (Enterprise) or rejected            |
| **Scope**             | Organization-wide                 | Organization-wide                          |

<Info>Once a workflow run or agent interaction finishes, it frees up a slot for the next one.</Info>

***

## How Rate Limits Work

Every time you start a workflow or agent interaction, the system checks whether your organization has an available slot. If it does, the execution starts immediately. If all slots are in use, the request is either **queued** or **rejected** depending on your plan.

<Steps>
  <Step title="Request arrives">
    A workflow is triggered or an agent interaction starts — whether from the UI, API, Slack, Teams, or a scheduled trigger.
  </Step>

  <Step title="Slot check">
    The system checks how many executions are currently running across your organization against your concurrency limit.
  </Step>

  <Step title="Outcome">
    * **Under limit** — execution starts immediately
    * **At limit + Enterprise plan** — request is queued and starts automatically when a slot opens
    * **At limit + Pro** — request is rejected
  </Step>
</Steps>

***

## Workflow Rate Limits

Workflow rate limits control how many workflow runs can execute at the same time across your organization.

### Limits by Plan

| Plan       | Concurrent Workflow Runs           |
| ---------- | ---------------------------------- |
| Pro        | 5                                  |
| Enterprise | 15 (default, customizable per org) |

### What Counts Toward the Limit

<CardGroup cols={2}>
  <Card title="Counts" icon="circle-check">
    * Any workflow run that is currently **executing**
    * Runs triggered via UI, API, webhooks, or scheduled triggers
  </Card>

  <Card title="Does NOT Count" icon="circle-xmark">
    * Runs that have **finished** (completed, failed, or terminated)
    * Runs that are **queued** (waiting for a slot)
    * **Subflows** (nested workflow runs) — only the parent run counts
  </Card>
</CardGroup>

### How the Limit Is Determined

The system determines your organization's limit in this order:

1. **Custom organization limit** — If your org admin or account manager has set a custom concurrency limit, that value is used.
2. **Plan default** — Otherwise, the default for your subscription plan applies (see table above).
3. **Per-user cap (optional)** — Org admins can set a per-user limit on specific [custom roles](/enterprise-features/user_groups). This prevents any single user from using all the org's slots. When a user is in multiple custom roles, the most generous cap across their roles applies.

<Accordion title="Example: Per-User Caps Within an Organization">
  **Setup:**

  * Organization concurrency limit: 15
  * A user group has a per-user cap of 5

  **Result:**

  * The organization can have up to 15 workflow runs at once across all members
  * Any user in that group can have at most 5 of those 15 running at once
  * Both limits must be satisfied for a new run to start

  This lets admins prevent a single user from consuming the entire org's capacity.
</Accordion>

### When You Hit the Workflow Limit

<Tabs>
  <Tab title="Enterprise (Queued)">
    When an Enterprise organization hits the limit:

    1. The run is saved as **queued**
    2. When another run finishes, the queued run automatically starts
    3. Queued runs are processed in the order they were submitted

    <Info>The queue can hold a very large number of items. If the queue is full, the request is rejected — but this is extremely unlikely in practice.</Info>
  </Tab>

  <Tab title="Pro (Rejected)">
    When a Pro user hits the limit:

    1. The API returns **HTTP 429** (Too Many Requests)
    2. The run does not start or get queued
    3. You need to wait for an existing run to finish, then retry
  </Tab>
</Tabs>

***

## Agent Rate Limits

Agent rate limits control how many agent interactions can run at the same time across your organization. Agents have higher default limits than workflows because interactions tend to be conversational and can run longer.

### Limits by Plan

| Plan       | Concurrent Agent Interactions       |
| ---------- | ----------------------------------- |
| Pro        | 25                                  |
| Enterprise | 100 (default, customizable per org) |

### What Counts Toward the Limit

<CardGroup cols={2}>
  <Card title="Counts" icon="circle-check">
    * Any agent interaction that is currently **running** (actively processing a request or executing tools)
    * Interactions from all channels: web UI, Slack, Teams, API, and triggers
  </Card>

  <Card title="Does NOT Count" icon="circle-xmark">
    * Interactions that have **finished** (completed or failed)
    * Interactions that are **queued** (waiting for a slot)
    * Interactions that are **idle** (conversation open but agent is not actively working)
    * The **Pipeline Builder** AI assistant (the in-editor helper for building workflows)
    * The **Custom Operator Builder** AI assistant
    * The **App Policy** builder assistant
  </Card>
</CardGroup>

<Info>Builder assistants (Pipeline Builder, Custom Operator Builder, App Policy Builder) are excluded from agent rate limits so that building and editing workflows is never blocked by agent concurrency.</Info>

### How the Limit Is Determined

Agent limits are determined the same way as workflow limits:

1. **Custom organization limit** — A custom agent concurrency limit on the org, if set.
2. **Plan default** — Falls back to the plan-based default shown above.
3. **Per-user cap (optional)** — An optional cap on a [custom role](/enterprise-features/user_groups) that restricts how many interactions a single user can run. When a user is in multiple custom roles, the most generous cap across their roles applies.

### Automatic Slot Cleanup

If an agent interaction crashes or is abandoned without finishing properly, the slot it was using is **automatically freed** after approximately 2 hours. This means you will never get permanently stuck at your limit due to a stuck interaction. Under normal circumstances, slots are released immediately when an interaction finishes — the 2-hour cleanup is only a safety net.

***

## What Happens When You're Rate Limited

How the system communicates rate limiting depends on where the interaction comes from:

### Web UI

When chatting with an agent in the browser:

* **Enterprise**: The UI shows a "queued" indicator and waits up to **5 minutes** for a slot to open. If a slot opens, the interaction starts automatically. If not, you are notified.
* **Pro**: An error message is shown: *"Too many concurrent agent interactions. Please try again shortly."*

### API

When starting an agent or workflow via the API:

* **Enterprise**: If the limit is hit, the request is queued. The API returns the interaction with a `queued` status and a `queue_position` so you can track progress.
* **Pro**: The API returns **HTTP 429** (Too Many Requests).

<Accordion title="API Response Examples">
  **Queued (Enterprise):**

  ```json theme={"dark"}
  {
    "interaction_id": "abc123",
    "status": "queued",
    "queue_position": 3
  }
  ```

  **Rate Limited:**

  ```json theme={"dark"}
  {
    "error": "gummie_rate_limit"
  }
  ```
</Accordion>

### Slack

When an agent receives a Slack message:

* **Enterprise**: If queued, a message is posted in the thread: *"Your agent interaction is queued (position #N). It will be processed shortly."*
* **Pro**: An ephemeral message is sent: *"You have too many agent interactions running at the moment. Please try again shortly."*

### Microsoft Teams

Follows the same pattern as Slack — Enterprise users see a queued notification, others see an error message.

### Scheduled & Event-Based Triggers

* **Enterprise**: If the limit is hit, the triggered interaction is queued and will start when capacity is available.
* **Pro**: The trigger execution is skipped.

***

## How the Queue Works

When an Enterprise organization hits the rate limit, requests are placed in a queue instead of being rejected.

* When a running execution finishes and frees a slot, the next queued item starts automatically
* The user who freed the slot gets slight priority — their own queued items are checked first, then the org-wide queue
* Within each queue, items are processed in the order they were submitted (first in, first out)
* If a queued item can't start because of a per-user cap, it is moved to that user's individual queue (not dropped)

***

## Tips for Avoiding Rate Limits

<CardGroup cols={2}>
  <Card title="Stagger Your Triggers" icon="clock">
    If you're triggering many workflows at once (e.g., via webhooks), add small delays between them so they don't all compete for slots at the same time.
  </Card>

  <Card title="Keep Runs Short" icon="gauge-high">
    Shorter workflow runs and agent interactions free up slots faster. Optimize long-running workflows to reduce their total execution time.
  </Card>

  <Card title="Use Subflows" icon="diagram-project">
    Subflows (nested workflow runs) don't count against your concurrency limit. Break large workflows into a parent with subflows to make better use of your slots.
  </Card>

  <Card title="Upgrade Your Plan" icon="arrow-up">
    If you consistently hit limits, consider upgrading. Enterprise plans have the highest limits and also unlock automatic queuing so requests are never lost.
  </Card>
</CardGroup>

<Accordion title="Enterprise: Request a Custom Limit">
  Enterprise organizations can request custom concurrency limits that exceed the defaults. Contact your account manager or [support@gumloop.com](mailto:support@gumloop.com) to adjust:

  * **Workflow concurrency** — the org-wide limit on simultaneous workflow runs
  * **Agent concurrency** — the org-wide limit on simultaneous agent interactions
  * **Per-user caps via user groups** — restrict individual users within the org

  These are set at the organization level and apply to all members.
</Accordion>

***

## Summary

| Aspect                  | Workflows                                                  | Agents                                                     |
| ----------------------- | ---------------------------------------------------------- | ---------------------------------------------------------- |
| **What's limited**      | Concurrent workflow runs                                   | Concurrent agent interactions                              |
| **Limit scope**         | Organization-wide                                          | Organization-wide                                          |
| **Enterprise queuing**  | Yes (automatic)                                            | Yes (automatic)                                            |
| **Pro behavior**        | Rejected (HTTP 429)                                        | Rejected (error message or HTTP 429)                       |
| **Stuck slot recovery** | Periodic cleanup                                           | Automatic (\~2 hours)                                      |
| **Customizable limits** | Yes (Enterprise)                                           | Yes (Enterprise)                                           |
| **Per-user caps**       | Yes (via [custom roles](/enterprise-features/user_groups)) | Yes (via [custom roles](/enterprise-features/user_groups)) |

<CardGroup cols={2}>
  <Card title="Credits" icon="coins" href="/core-concepts/credits">
    Learn about credit costs for workflows and agents
  </Card>

  <Card title="Custom Roles" icon="users" href="/enterprise-features/user_groups">
    Configure custom roles and per-user limits
  </Card>
</CardGroup>
