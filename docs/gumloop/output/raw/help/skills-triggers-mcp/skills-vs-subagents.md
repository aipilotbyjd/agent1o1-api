> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Skills vs Subagents

**Skills** teach this agent how to do a job. **Subagents** are other agents you hand work to.

|            | Skill                                | Subagent                                        |
| ---------- | ------------------------------------ | ----------------------------------------------- |
| What it is | A playbook, template, or script      | A separate agent with its own tools and context |
| Runs where | Inside the current chat              | In its own conversation                         |
| Best for   | Repeatable process, shared knowledge | Parallel work, specialists                      |

## Quick decision

| You want to…                               | Use                    |
| ------------------------------------------ | ---------------------- |
| Follow a step-by-step process              | Skill                  |
| Store templates or domain knowledge        | Skill                  |
| Reuse the same process on many agents      | Skill                  |
| Research three things at once              | Subagent (clone)       |
| Hand work to a specialist (Sales, Support) | Subagent (other agent) |
| Coordinate several specialists             | Subagent               |

Details: [Agent Skills](/core-concepts/skills) and [Subagents](/core-concepts/agents#subagents).

<Tip>
  If you are about to paste a long SOP into the agent instructions, make a skill. If you are about to give one agent every connector in the company, split specialists and call them as subagents.
</Tip>

## FAQ

<AccordionGroup>
  <Accordion title="When should I write a skill?">
    For a repeatable process. Example: "draft the weekly pipeline recap in this format."
  </Accordion>

  <Accordion title="When should I use a clone?">
    For parallel research. Example: "research these three competitors at once."
  </Accordion>

  <Accordion title="When should I invoke another named agent?">
    For cross-domain work. Example: a coordinator asks the CRM agent and the writing agent, then combines the answers.
  </Accordion>

  <Accordion title="Can I use both?">
    Yes. Put a skill on the coordinator that says *when* to call which subagent.
  </Accordion>
</AccordionGroup>

## Related

<CardGroup cols={2}>
  <Card title="Agent Skills" icon="wand-magic-sparkles" href="/core-concepts/skills">
    How skills attach and load
  </Card>

  <Card title="Skills vs app rules" icon="shield" href="/help/skills-triggers-mcp/skills-vs-app-rules">
    Playbooks vs guardrails
  </Card>
</CardGroup>
