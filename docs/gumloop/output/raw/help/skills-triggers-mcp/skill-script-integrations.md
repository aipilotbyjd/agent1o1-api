> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Call Integrations from a Skill

A **skill script** is a skill that includes Python the agent can run in the [code sandbox](/core-concepts/agent_sandbox_and_secrets). Use it when a connector exists, but you need branching, batching, or cleanup the raw tools do not do well.

You do not need to write the script. Describe the outcome and let the agent draft the skill.

## When this helps

* Transform results before the next step (filter rows, reshape JSON)
* Call the same connector many times with custom logic
* Combine two connectors in one deterministic script

If you only need "send this Slack message," add the Slack connector. Do not wrap it in a skill.

## How to get the agent to write one

1. Add the connector on the agent first. See [Give an agent a connector](/help/using-agents/give-agent-access-to-a-connector).
2. Paste one of the prompts below.
3. Review the skill. It should use the Gumloop SDK for the connector, not raw HTTP.
4. Save it and attach it to this agent (and others, if you want).

## Example prompts

Copy one of these, then swap in your mailbox, sheet, or channel.

**Filter unread email into a table**

```
Create a skill that lists unread Gmail from the last 24 hours, keeps only messages from customers (not newsletters or internal @ourcompany.com), and writes a summary table with sender, subject, and a one-line next step.
```

**Batch Slack follow-ups**

```
Create a skill that reads a list of Slack user IDs, posts the same check-in message to each person as a DM, and returns who succeeded and who failed. Use the Slack connector already on this agent. Do not use raw HTTP.
```

**Sheets + Slack digest**

```
Create a skill that reads the "Leads" tab in our Google Sheet, keeps rows where Status is "New" and Score is over 70, and posts a Slack digest to #sales with name, company, and score. Use the Google Sheets and Slack connectors already on this agent.
```

**Retry-safe CRM update**

```
Create a skill that takes a list of Salesforce account IDs, fetches each account, and writes Last_Touched__c to today. Skip IDs that 404. Return a table of updated vs skipped. Use the Salesforce connector, not raw HTTP.
```

## After it writes the skill

* Keep the skill focused on one job.
* Tell the agent which connector account to use if several are connected.
* If the script fails, paste the error back and ask it to fix the skill.
* Skills do not have version history. Duplicate a working skill before a risky edit.

## FAQ

<AccordionGroup>
  <Accordion title="Should every Slack or Gmail action be a skill?">
    No. Add the connector and ask the agent in chat. Use a skill script only when you need branching, batching, or cleanup the raw tools do not do well.
  </Accordion>

  <Accordion title="The skill used requests / HTTP instead of the connector">
    Tell it: "Rewrite this to use the Gumloop SDK and the connector already on this agent. Do not call the API over HTTP."
  </Accordion>

  <Accordion title="Can I attach the same skill to other agents?">
    Yes, after you save it. Those agents still need the same connectors attached.
  </Accordion>
</AccordionGroup>

## Related

<CardGroup cols={2}>
  <Card title="Code Sandbox" icon="terminal" href="/core-concepts/agent_sandbox_and_secrets">
    Where skill scripts run
  </Card>

  <Card title="Agent Skills" icon="wand-magic-sparkles" href="/core-concepts/skills">
    How to attach a skill
  </Card>
</CardGroup>
