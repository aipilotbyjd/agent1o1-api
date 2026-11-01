> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Skills vs App Rules

These are not interchangeable.

|                 | Skills                                   | App rules                                       |
| --------------- | ---------------------------------------- | ----------------------------------------------- |
| Purpose         | Teach a process                          | Constrain a tool call                           |
| Runs as         | Extra instructions or a script           | A block, tag, or approval check                 |
| Who writes them | Anyone who can edit the agent or skill   | Agent editors, or org admins for org-wide rules |
| Example         | "Always file the note in this Notion DB" | "Block Slack `chat.delete`"                     |

## Use a skill when

* You want a playbook, template, or style guide
* The agent should call tools, but *in a certain way*
* Several agents should share the same process

## Use an app rule when

* A tool should never run, or should wait for a human
* You need the same guardrail on many agents
* Compliance requires a hard block, not a suggestion

App rules can be scoped to one agent or to the organization. See [App Rules](/enterprise-features/app-policies/app-rules) and [Human in the Loop](/core-concepts/human_in_the_loop).

## FAQ

<AccordionGroup>
  <Accordion title="Can a skill replace an app rule?">
    No. A skill can *ask* the agent not to delete Slack messages. An app rule can *stop* the delete call. Use both when the process and the guardrail matter.
  </Accordion>
</AccordionGroup>

## Related

<CardGroup cols={2}>
  <Card title="App Rules" icon="shield" href="/enterprise-features/app-policies/app-rules">
    Org and agent guardrails
  </Card>

  <Card title="Human in the Loop" icon="hand" href="/core-concepts/human_in_the_loop">
    Approvals and Ask Question
  </Card>
</CardGroup>
