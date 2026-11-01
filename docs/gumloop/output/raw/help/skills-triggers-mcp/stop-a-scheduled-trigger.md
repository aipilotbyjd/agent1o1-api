> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Stop a Scheduled Trigger

Open the agent, find the trigger, and deactivate or delete it. Deactivating keeps the config. Deleting removes it.

## On an agent

1. Open the [agent](https://www.gumloop.com/agents).
2. Open **Triggers**.
3. Choose **Deactivate** to pause it, or **Delete** if you do not need it again.

Deactivating every trigger is how you switch an agent off without retiring it. The agent, connectors, and trigger configs stay intact.

## On a workflow

Workflow schedules live on the workflow, not on an agent.

1. Open the workflow.
2. Open its trigger settings.
3. Turn the schedule off or remove it.

<Frame>
  <img src="https://mintcdn.com/agenthub/wBs_l4y1SVLfsNA7/images/help/5396676286_3.png?fit=max&auto=format&n=wBs_l4y1SVLfsNA7&q=85&s=a07e991a4e12815afccbeba10d8297eb" alt="Open workflow trigger settings" width="1568" height="734" data-path="images/help/5396676286_3.png" />
</Frame>

<Frame>
  <img src="https://mintcdn.com/agenthub/wBs_l4y1SVLfsNA7/images/help/5396676286_4.png?fit=max&auto=format&n=wBs_l4y1SVLfsNA7&q=85&s=4ce38d8a1a91d89528ba006c25442ae8" alt="Open the time trigger menu" width="1774" height="1030" data-path="images/help/5396676286_4.png" />
</Frame>

<Frame>
  <img src="https://mintcdn.com/agenthub/wBs_l4y1SVLfsNA7/images/help/5396676286_5.png?fit=max&auto=format&n=wBs_l4y1SVLfsNA7&q=85&s=26f8971c969ee97fdedaa0dc60685ee4" alt="Delete a workflow time trigger" width="1414" height="592" data-path="images/help/5396676286_5.png" />
</Frame>

## Good to know

* A deactivated trigger does not fire and does not poll.
* One-time scheduled triggers delete themselves after they run, success or fail.
* Deleting cannot be undone. Recreate it if you still need it.
* If a run is already in flight, stopping the trigger does not cancel that run. Cancel the run from history if you need to.

## Related

<CardGroup cols={2}>
  <Card title="Agent Triggers" icon="clock" href="/core-concepts/agent_triggers">
    Add a scheduled or event trigger
  </Card>

  <Card title="Workflow Triggers" icon="play" href="/core-concepts/workflow_triggers">
    Workflow schedules
  </Card>
</CardGroup>
