> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Evaluations

> Automatically grade every agent interaction against your criteria, collect structured data, and tag conversations for reporting.

Evaluations give you **automated quality assurance** for your agents. After every interaction completes, an AI evaluator analyzes the full conversation transcript and produces a structured report: a grade, per-criterion pass/fail results with rationales, sentiment analysis, applied tags, and extracted data points.

Think of it as having a QA analyst reviewing every single conversation your agent has, 24/7, without you lifting a finger.

## Where to Find Evaluations

Open your agent and click **Evaluations** in the left-hand sidebar.

<Frame>
  <img src="https://mintcdn.com/agenthub/0J0xDs1-MXq4qCdR/images/core-concepts/evaluations/sidebar-navigation.png?fit=max&auto=format&n=0J0xDs1-MXq4qCdR&q=85&s=2b1b29b112a15cde479996b2d276bfaf" alt="Agent sidebar showing the Evaluations menu item" style={{ maxWidth: '280px' }} width="496" height="514" data-path="images/core-concepts/evaluations/sidebar-navigation.png" />
</Frame>

***

## The Building Blocks

Before jumping into setup, here's the mental model. Evaluations have four building blocks:

<CardGroup cols={2}>
  <Card title="Criteria" icon="list-check">
    **Quality rules** the evaluator checks pass/fail on every conversation. Like a QA checklist.
  </Card>

  <Card title="Tags" icon="tags">
    **Labels** applied for categorization and filtering. Like folders in your inbox.
  </Card>

  <Card title="Data Points" icon="table">
    **Structured values** extracted from conversations. Like columns in a spreadsheet.
  </Card>

  <Card title="Sentiment" icon="face-smile">
    **Emotional tone** of the interaction. A customer satisfaction thermometer.
  </Card>
</CardGroup>

**Criteria** answer: "Did the agent do what it was supposed to do?" Each one is a yes/no check.

**Tags** answer: "What kind of conversation was this?" Use them to filter and find patterns.

**Data Points** answer: "What specific facts or values came up?" They pull structured data out of unstructured conversation.

**Sentiment** answers: "How did the user feel?" Optionally let negative sentiment affect the grade.

***

## How Evaluations Work

<Steps>
  <Step title="Interaction Completes">
    Your agent finishes a conversation (reaches the "completed" state). Incognito chats and internal system interactions are never evaluated.
  </Step>

  <Step title="Transcript is Built">
    The evaluator constructs a role-tagged transcript of the entire conversation, including user messages, agent responses, tool calls, and results. Long transcripts are automatically trimmed.
  </Step>

  <Step title="AI Evaluator Runs">
    A single structured-output LLM call analyzes the transcript against your configured criteria, tags, data points, and sentiment settings.
  </Step>

  <Step title="Grade is Computed">
    The overall grade is determined deterministically based on which criteria failed, the action set on each failed criterion, call outcome, and sentiment.
  </Step>

  <Step title="Results Stored & Alerts Fired">
    Results are persisted. If the grade is **Notify**, the notification channels you enabled (Slack DM to the agent owner, email to your recipient list) fire immediately.
  </Step>
</Steps>

<AccordionGroup>
  <Accordion title="When does the evaluation fire?">
    The evaluation fires automatically shortly after the interaction reaches the "completed" state.

    * For a simple back-and-forth that ends naturally, the evaluation runs once the chat is marked complete.
    * The evaluation covers the **entire transcript** up to that point.
    * There is a short debounce period after the last message so the system doesn't prematurely evaluate a chat that's still active.
  </Accordion>

  <Accordion title="What happens if I keep chatting after the evaluation ran?">
    If you continue a conversation after an evaluation has already run:

    * The new messages extend the transcript, and the interaction re-enters an active state.
    * Once the conversation reaches the "completed" state again, a **new evaluation automatically runs** covering the full updated transcript.
    * The new evaluation replaces the previous result. You don't need to manually re-run it.
    * You always see the most recent evaluation result for any given interaction.
  </Accordion>

  <Accordion title="Do I have to manually re-run it, or is it automatic?">
    Fully automatic. As long as evaluations are enabled, the system handles re-evaluation whenever conversations continue and complete again. You only need to run manually if you want to backfill old conversations or re-evaluate after changing your criteria.
  </Accordion>
</AccordionGroup>

***

## Setting Up Evaluations

Navigate to your agent's **Evaluations** tab in the sidebar. You'll see the settings panel:

<Frame>
  <img src="https://mintcdn.com/agenthub/GfPAtPqoWkLJQtkp/images/core-concepts/evaluations/evaluation-settings.png?fit=max&auto=format&n=GfPAtPqoWkLJQtkp&q=85&s=abb3a38c3e65124c1f78839dedf4f6eb" alt="Evaluation settings panel showing the enable toggle, model selector, sentiment analysis, and auto-tags options" style={{ maxWidth: '550px' }} width="1788" height="1090" data-path="images/core-concepts/evaluations/evaluation-settings.png" />
</Frame>

| Setting                        | What It Does                                                                          |
| ------------------------------ | ------------------------------------------------------------------------------------- |
| **Enable evaluations**         | Toggle to start automatically grading interactions                                    |
| **Default analysis model**     | Which LLM runs the evaluation. "Smartest" = most accurate but costs more per token    |
| **Sentiment analysis**         | Captures overall sentiment (Positive / Neutral / Negative). Optionally affects grade. |
| **Suggest tags automatically** | Lets the evaluator propose new tags beyond your predefined vocabulary                 |

<Tip>For sentiment, you can provide custom **guidance** (e.g., "Consider the customer's final message tone, not their initial frustration") and check "Negative sentiment affects the overall grade" to auto-downgrade negative experiences.</Tip>

***

## Configuring Criteria, Tags & Data Points

<Tabs>
  <Tab title="Criteria" icon="list-check">
    Criteria are the **quality rules** your evaluator checks every interaction against. Each one is a clear statement that's either true or false for a given conversation.

    **How to think about it:** Ask yourself, "If I were reviewing this conversation manually, what would I check for?" Each answer becomes a criterion.

    <Frame>
      <img src="https://mintcdn.com/agenthub/GfPAtPqoWkLJQtkp/images/core-concepts/evaluations/evaluation-criteria.png?fit=max&auto=format&n=GfPAtPqoWkLJQtkp&q=85&s=2360b38e2ff7aea3fb59e64a11737044" alt="Evaluation criteria table" style={{ maxWidth: '550px' }} width="1662" height="678" data-path="images/core-concepts/evaluations/evaluation-criteria.png" />
    </Frame>

    ### Adding a Criterion

    Click **+ Add criterion** and fill in:

    <Frame>
      <img src="https://mintcdn.com/agenthub/IVgCU_H9_o7TusR7/images/core-concepts/evaluations/add-criterion.png?fit=max&auto=format&n=IVgCU_H9_o7TusR7&q=85&s=141875d4561b1ee19c6c68dc55cbb7c2" alt="Add criterion form showing Name, Evaluation prompt, Type, and the Action dropdown with Flag and Notify options" style={{ maxWidth: '450px' }} width="1138" height="1720" data-path="images/core-concepts/evaluations/add-criterion.png" />
    </Frame>

    | Field                 | Description                                                                                                                                                       |
    | --------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- |
    | **Name**              | A short label (e.g., "Accuracy", "Stayed on Topic")                                                                                                               |
    | **Evaluation prompt** | A true/false statement describing the desired behavior. Be specific.                                                                                              |
    | **Type**              | Categorizes the criterion: Prohibited action, Prohibited words, Voice & tone, or Other                                                                            |
    | **Action**            | What happens when the criterion fails: **Flag** (flags the chat for review without notifying you) or **Notify** (notifies you the moment the evaluator spots one) |

    ### Types & Actions

    | Type                  | When to Use                                                            |
    | --------------------- | ---------------------------------------------------------------------- |
    | **Prohibited action** | Agent must NOT do something (e.g., don't offer unauthorized discounts) |
    | **Prohibited words**  | Agent must NOT say certain things (e.g., no profanity)                 |
    | **Voice & tone**      | Agent should communicate in a certain style                            |
    | **Other**             | Anything else (stayed on topic, provided accurate info, etc.)          |

    **Choosing an action:** Use **Notify** for rules that must never be broken (data leaks, compliance violations) so you're alerted right away. Use **Flag** for quality standards that matter but aren't urgent (tone issues, minor drifts); those chats are only marked for review.

    <Info>"Notify" alerts the people responsible for the agent, not the person chatting with it. Slack DMs go to the agent owner and emails go to the recipients you list under **Notifications**. End users never see evaluation results.</Info>

    <Accordion title="Example criteria for common use cases">
      | Use Case | Criterion                 | Prompt                                                                                       | Type              | Action |
      | -------- | ------------------------- | -------------------------------------------------------------------------------------------- | ----------------- | ------ |
      | Support  | Stayed on Topic           | "The agent stayed focused on resolving the customer's issue and did not go off on tangents." | Other             | Flag   |
      | Support  | Accuracy                  | "The agent provided factually correct information and did not hallucinate."                  | Other             | Notify |
      | Sales    | No Unauthorized Discounts | "The agent did not offer discounts not in the approved pricing sheet."                       | Prohibited action | Notify |
      | Sales    | Professional Tone         | "The agent maintained a professional, friendly tone throughout."                             | Voice & tone      | Flag   |
      | Helpdesk | No PII Disclosure         | "The agent did not reveal personal information of other employees."                          | Prohibited action | Notify |
      | Content  | Brand Voice               | "The content matches the brand's voice: confident, concise, and jargon-free."                | Voice & tone      | Flag   |
    </Accordion>

    <Tip>Write evaluation prompts as true/false statements. The evaluator returns "success" if it holds, "failure" if it doesn't, and "unknown" if there's not enough info to judge.</Tip>

    **Limit:** 30 criteria per agent.
  </Tab>

  <Tab title="Tags" icon="tags">
    Tags are **labels for categorization**. After analyzing a conversation, the evaluator applies tags whose description matches what happened. You can then filter your interactions list by tag.

    **How to think about it:** Tags are like labels in Gmail. They don't pass/fail anything. They just categorize. Ask yourself, "What categories would help me filter and find patterns in my conversations?"

    <Frame>
      <img src="https://mintcdn.com/agenthub/GfPAtPqoWkLJQtkp/images/core-concepts/evaluations/evaluation-tags.png?fit=max&auto=format&n=GfPAtPqoWkLJQtkp&q=85&s=a3380f8b5202a5373686a003f5389561" alt="Tags section showing AI_SLOP and OFF_COURSE tags" style={{ maxWidth: '550px' }} width="1772" height="568" data-path="images/core-concepts/evaluations/evaluation-tags.png" />
    </Frame>

    ### Adding a Tag

    Click **+ Add tag** and provide:

    | Field           | Description                                                                  |
    | --------------- | ---------------------------------------------------------------------------- |
    | **Name**        | Automatically uppercased to `UPPER_SNAKE_CASE`. Max 100 characters.          |
    | **Description** | Tells the evaluator when to apply this tag. Be specific. Max 500 characters. |

    ### How Tags Work

    * You define a vocabulary of tags with descriptions
    * The evaluator applies matching tags after analyzing the conversation
    * Tag names are normalized (e.g., "off course" → `OFF_COURSE`)
    * You can manually add/remove tags on any evaluated interaction
    * Use them to filter the interactions list

    <Accordion title="Example tags for common use cases">
      | Tag                  | Description                                                                          | Use Case             |
      | -------------------- | ------------------------------------------------------------------------------------ | -------------------- |
      | `ESCALATION_NEEDED`  | "Customer asked to speak with a human or the issue is too complex for the agent."    | Support triage       |
      | `UPSELL_OPPORTUNITY` | "Customer expressed interest in additional features beyond what they currently use." | Sales analytics      |
      | `AI_SLOP`            | "Agent's response contained filler phrases or generic AI-sounding language."         | Quality monitoring   |
      | `OFF_COURSE`         | "Agent deviated from the user's original question."                                  | Focus tracking       |
      | `POSITIVE_FEEDBACK`  | "User explicitly praised the agent or expressed satisfaction."                       | CSAT proxy           |
      | `TECHNICAL_ISSUE`    | "Conversation involved a bug report or system malfunction."                          | Issue categorization |
    </Accordion>

    <Tip>Enable "Suggest tags automatically" in Settings and the evaluator will also generate new tags for patterns it notices beyond your predefined vocabulary.</Tip>

    **Limit:** 50 tags per agent.
  </Tab>

  <Tab title="Data Points" icon="table">
    Data collection lets you **extract structured values** from every interaction. Define what to extract, and the evaluator pulls it out automatically.

    **How to think about it:** Imagine hiring someone to read every conversation and fill out a spreadsheet. Each column is a data point. You define the column name, value type, and extraction instructions.

    <Frame>
      <img src="https://mintcdn.com/agenthub/GfPAtPqoWkLJQtkp/images/core-concepts/evaluations/evaluation-data-collection.png?fit=max&auto=format&n=GfPAtPqoWkLJQtkp&q=85&s=86313be303a5eb743f7896755c1c5d0a" alt="Data collection section showing a Confidence Score data point" style={{ maxWidth: '550px' }} width="1702" height="462" data-path="images/core-concepts/evaluations/evaluation-data-collection.png" />
    </Frame>

    ### Adding a Data Point

    Click **+ Add data point** and configure:

    | Field           | Description                                                                  |
    | --------------- | ---------------------------------------------------------------------------- |
    | **Name**        | What you're extracting (e.g., "Confidence Score")                            |
    | **Type**        | Text, Boolean, Integer, or Number                                            |
    | **Description** | Extraction instructions. Be precise about what to look for and valid values. |

    ### Data Point Types

    | Type        | Returns      | Best For                            |
    | ----------- | ------------ | ----------------------------------- |
    | **Text**    | String       | Categories, summaries, reasons      |
    | **Boolean** | Yes / No     | Binary checks (was something done?) |
    | **Integer** | Whole number | Counts, quantities                  |
    | **Number**  | Decimal      | Scores, ratings, percentages        |

    <Accordion title="Example data points for common use cases">
      | Name                 | Type    | Description                                                                                   | Use Case                 |
      | -------------------- | ------- | --------------------------------------------------------------------------------------------- | ------------------------ |
      | Confidence Score     | Number  | "Rate 1-10 how confident the agent appeared based on hedging language vs. direct statements." | Quality scoring          |
      | Resolution Status    | Text    | "Values: resolved, unresolved, partial, or unknown."                                          | Support metrics          |
      | Handoff Requested    | Boolean | "Did the user ask to speak with a human?"                                                     | Escalation tracking      |
      | Number of Tool Calls | Integer | "Count distinct tools the agent used."                                                        | Efficiency analysis      |
      | Customer Intent      | Text    | "Summarize the customer's primary intent in 2-5 words."                                       | Intent classification    |
      | Response Quality     | Number  | "Rate 1-10 considering accuracy, completeness, and helpfulness."                              | Performance benchmarking |
    </Accordion>

    <Tip>Data points that return `null` mean the evaluator couldn't find the information in the transcript. This is expected for data points that don't apply to every conversation.</Tip>

    **Limit:** 40 data points per agent.
  </Tab>
</Tabs>

***

## Understanding Results

Once an interaction is evaluated, you can see the full results in the interaction detail view's **Overview** tab.

<Frame>
  <img src="https://mintcdn.com/agenthub/GfPAtPqoWkLJQtkp/images/core-concepts/evaluations/evaluation-result.png?fit=max&auto=format&n=GfPAtPqoWkLJQtkp&q=85&s=754da128d86d226e11a563db38be2b6d" alt="Evaluation result showing summary, grade, outcome, sentiment, criteria results, tags, and collected data" style={{ maxWidth: '550px' }} width="964" height="1714" data-path="images/core-concepts/evaluations/evaluation-result.png" />
</Frame>

### Grades

| Grade      | API Value         | Meaning             | What Triggers It                                                                                   |
| ---------- | ----------------- | ------------------- | -------------------------------------------------------------------------------------------------- |
| **Pass**   | `pass`            | Met all criteria    | No failures, call wasn't a failure, sentiment not negative                                         |
| **Flag**   | `needs_review`    | Flagged for review  | A criterion set to **Flag** failed, OR call outcome "failure", OR negative sentiment affects grade |
| **Notify** | `needs_attention` | Immediate attention | A criterion set to **Notify** failed (fires your enabled notification channels)                    |

<Info>When using the [Evaluations API](/api-reference/evaluations/list-evaluations), use the API values (`pass`, `needs_review`, `needs_attention`) for the `grade` query parameter, not the UI labels.</Info>

<Accordion title="Grade computation logic (deterministic)">
  The grade is computed deterministically (not by the LLM) after results come back:

  1. If any criterion set to **Notify** failed → **Notify**
  2. If any other criterion failed → **Flag**
  3. If overall **call outcome** was "failure" → **Flag**
  4. If **sentiment** is negative AND affects grade → **Flag**
  5. Otherwise → **Pass**
</Accordion>

### What's Shown in Results

* **Summary**: One or two sentence narrative of what happened
* **Grade**: Pass / Flag / Notify badge
* **Outcome**: Successful / Failed / Unknown
* **Sentiment**: Positive / Neutral / Negative (if enabled)
* **Tags**: Applied tags from your vocabulary + auto-generated
* **Criteria**: Per-criterion pass/fail with the evaluator's rationale
* **Collected data**: Extracted values for each data point

### Interactions List View

The interactions list includes an **Evaluation** column showing the grade and criteria pass rate at a glance (e.g., "Pass 3/3" or "Notify 1/3"). Lifecycle states also appear: Queued, Evaluating, or Failed.

***

## Running Evaluations Manually

Evaluations run automatically, but you can also trigger them manually from the **Chats** page.

<Steps>
  <Step title="Go to Chats">
    Navigate to your agent's **Chats** page from the left-hand sidebar.
  </Step>

  <Step title="Find the Interaction">
    Locate the chat you want to evaluate. The **Evaluation** column shows the current state.
  </Step>

  <Step title="Open the Actions Menu">
    Click the **three-dot menu** (⋮) on the right side of the interaction row.
  </Step>

  <Step title="Click Run Evaluation">
    Select **"Run evaluation"** from the dropdown. Results will appear shortly.
  </Step>
</Steps>

<Frame>
  <img src="https://mintcdn.com/agenthub/GfPAtPqoWkLJQtkp/images/core-concepts/evaluations/run-evaluation-menu.png?fit=max&auto=format&n=GfPAtPqoWkLJQtkp&q=85&s=ec84cf3846c88d73516959f3a3aa2b5d" alt="Interactions list showing the three-dot menu with Run evaluation option" style={{ maxWidth: '550px' }} width="2358" height="484" data-path="images/core-concepts/evaluations/run-evaluation-menu.png" />
</Frame>

You can also select multiple interactions for bulk evaluation.

**When to use manual runs:**

* Backfilling existing conversations after enabling evaluations
* Re-evaluating after changing your criteria/tags/data points
* Retrying a failed evaluation
* Spot-checking specific conversations on demand

<Info>Manual evaluations use the **current** configuration. If you've changed your setup, a re-run reflects the new rules.</Info>

***

## Notifications

Notifications fire only when a chat receives a **Notify** grade, i.e. a criterion whose action is **Notify** failed. Configure the channels in the **Notifications** section of the Evaluations tab.

| Channel                 | Who receives it                                                                                                                          |
| ----------------------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| **Slack notifications** | A Slack DM to the **agent owner**, with the agent's name, which criteria failed, and a link to the chat. Requires Slack to be connected. |
| **Email notifications** | An email to the recipients you list. Only addresses that already have access to the agent are delivered to. Up to 50 recipients.         |

<Frame>
  <img src="https://mintcdn.com/agenthub/IVgCU_H9_o7TusR7/images/core-concepts/evaluations/evaluation-notifications.png?fit=max&auto=format&n=IVgCU_H9_o7TusR7&q=85&s=98667d4c67c7c90effd8860cacd4c4f3" alt="Notifications section with Slack notifications and Email notifications toggles and a recipient input" style={{ maxWidth: '550px' }} width="1732" height="698" data-path="images/core-concepts/evaluations/evaluation-notifications.png" />
</Frame>

<Info>"You" in this UI means the agent's owner and the recipients you configure, never the end user chatting with the agent.</Info>

<Tip>Flag-grade chats are marked in the interactions list but never send a notification. Review them periodically by filtering for the Flag grade.</Tip>

***

## Credits and Costs

Evaluations are billed as **AI credits** under the "AI Utilities" category. Each evaluation is a single LLM call.

<Accordion title="What determines the cost?">
  Three factors:

  1. **Transcript length**: Longer conversations use more input tokens. A 5-message chat costs significantly less than a 50-message conversation.
  2. **Analysis model**: The model you select determines the per-token rate. "Smartest" costs more than faster alternatives.
  3. **Schema complexity**: More criteria, tags, and data points = more output tokens to generate.

  The evaluator checks that the user has sufficient credits before running. If credits are insufficient, the evaluation is skipped silently.
</Accordion>

### Where to See Credit Usage

View all evaluation credit usage on your **[Usage & Limits](https://www.gumloop.com/settings/organization/limits)** page. Filter by "AI Utilities" to see individual evaluation runs and their credit amounts.

<Frame>
  <img src="https://mintcdn.com/agenthub/GfPAtPqoWkLJQtkp/images/core-concepts/evaluations/credit-usage-logs.png?fit=max&auto=format&n=GfPAtPqoWkLJQtkp&q=85&s=6b9c4459975ae921a1005421827d7630" alt="Credit Usage Logs page filtered to AI Utilities showing Interaction Evaluation entries" style={{ maxWidth: '550px' }} width="2400" height="506" data-path="images/core-concepts/evaluations/credit-usage-logs.png" />
</Frame>

***

## Limits

| Resource                      | Maximum        |
| ----------------------------- | -------------- |
| Criteria per agent            | 30             |
| Tags per agent                | 50             |
| Data points per agent         | 40             |
| Tag name length               | 100 characters |
| Tag description length        | 500 characters |
| Email notification recipients | 50             |

***

## Exporting Evaluation Data

Every evaluation result, including grades, criteria outcomes, extracted data points, tags, and sentiment, is available through the [Evaluations API](/api-reference/evaluations/list-evaluations). You can use this to export evaluation data to any external system (spreadsheets, databases, BI tools, etc.).

### API

Use the **List evaluations** endpoint to pull all results for an agent:

```bash theme={"dark"}
curl 'https://api.gumloop.com/api/v1/agents/AGENT_ID/evaluations?page_size=100' \
  -H 'Authorization: Bearer YOUR_API_KEY'
```

Each result includes:

* `grade` (`pass` / `needs_review` = Flag / `needs_attention` = Notify)
* `criteria_results` with per-criterion pass/fail and rationale
* `data_results` with extracted values for each configured data point
* `applied_tags`
* `sentiment`
* `summary`

Paginate through all results using the `next_cursor` field. Filter by grade with `?grade=needs_attention`.

For a single evaluation, use the [Retrieve evaluation](/api-reference/evaluations/retrieve-evaluation) endpoint.

For aggregate metrics over time, use the [Get metrics](/api-reference/evaluations/get-metrics) endpoint.

<Tip>Build a Gumloop workflow that calls the evaluations API on a schedule and pushes results to Google Sheets, a database, or a webhook for automated reporting.</Tip>

### Python

```python theme={"dark"}
import csv
import requests

API_KEY = "YOUR_API_KEY"
AGENT_ID = "YOUR_AGENT_ID"

# Paginate through all evaluations
cursor = None
all_evaluations = []
while True:
    params = {"page_size": 100}
    if cursor:
        params["cursor"] = cursor
    response = requests.get(
        f"https://api.gumloop.com/api/v1/agents/{AGENT_ID}/evaluations",
        headers={"Authorization": f"Bearer {API_KEY}"},
        params=params,
    )
    response.raise_for_status()
    data = response.json()
    all_evaluations.extend(data["evaluations"])
    cursor = data.get("next_cursor")
    if not cursor:
        break

# Export data points to CSV
with open("eval_export.csv", "w", newline="") as f:
    writer = csv.writer(f)
    writer.writerow(["interaction_id", "grade", "sentiment", "summary"])
    for ev in all_evaluations:
        writer.writerow([ev["interaction_id"], ev["grade"], ev["sentiment"], ev["summary"]])
```

***

## FAQ

<AccordionGroup>
  <Accordion title="Which interactions get evaluated?">
    All completed interactions are evaluated, except:

    * **Incognito chats**: Never evaluated (privacy guarantee)
    * **Internal interactions**: Agent-to-agent feedback loops like reflections are excluded
    * **Non-terminal states**: Only interactions that reach "completed" are evaluated
  </Accordion>

  <Accordion title="Can I evaluate interactions from before I enabled evaluations?">
    Yes. Go to your agent's Chats page, click the three-dot menu on any interaction, and select "Run evaluation." You can also select multiple for bulk evaluation. They'll be graded against your current criteria.
  </Accordion>

  <Accordion title="What happens if I keep chatting after an evaluation already ran?">
    The interaction re-enters an active state. Once it completes again, a new evaluation runs automatically covering the full updated transcript. The new result replaces the previous one.
  </Accordion>

  <Accordion title="Does the evaluation fire only once per chat?">
    By default, yes. The evaluation fires once after the conversation completes (with a short debounce window to avoid premature evaluation). If the conversation is resumed and completes again, a new evaluation runs automatically covering the updated transcript.
  </Accordion>

  <Accordion title="What happens if the evaluation fails?">
    It's marked as "Failed" rather than showing a misleading pass. Doesn't affect metrics. You can manually re-run it from the Chats page, or it will be re-evaluated if the conversation continues.
  </Accordion>

  <Accordion title="How is the analysis model different from my agent's model?">
    It's a separate LLM call dedicated to evaluation. Doesn't affect your agent's behavior. You can choose a different model. The evaluator never sees your agent's system prompt directly, only the transcript and agent description/skills as context.
  </Accordion>

  <Accordion title="Does the evaluator see my agent's system prompt?">
    It receives your agent's **name**, **description**, and **available skills** as context. It does NOT receive the full system prompt. Transcript content is treated as data to analyze, never as instructions.
  </Accordion>

  <Accordion title="Can I set up evaluations at the organization level?">
    Currently configured per agent. Organization-level configs are planned for a future release.
  </Accordion>

  <Accordion title="What happens if I delete a criterion or tag?">
    Past evaluation results retain their original data. Deleting only affects future evaluations.
  </Accordion>

  <Accordion title="Can I edit tags on an evaluation result?">
    Yes. Manually add or remove tags using the tag editor in the evaluation detail view.
  </Accordion>

  <Accordion title="Why does my evaluation show 'Inconclusive'?">
    All criteria returned "unknown" (evaluator couldn't determine pass/fail). Typically means the conversation was too short or didn't touch on what your criteria check.
  </Accordion>

  <Accordion title="How much do evaluations cost in credits?">
    Each evaluation is billed based on tokens used. Cost depends on conversation length, model selected, and number of criteria/tags/data points configured. Track usage on your [Usage & Limits](https://www.gumloop.com/settings/organization/limits) page under "AI Utilities."
  </Accordion>

  <Accordion title="Can I export evaluation data?">
    Yes. The [Evaluations API](/api-reference/evaluations/list-evaluations) gives you full programmatic access to all evaluation results, including grades, criteria outcomes, extracted data points, tags, and sentiment. You can paginate through results and export them to any destination (CSV, Google Sheets, databases, etc.). See the [Exporting Evaluation Data](#exporting-evaluation-data) section above for examples.
  </Accordion>
</AccordionGroup>
