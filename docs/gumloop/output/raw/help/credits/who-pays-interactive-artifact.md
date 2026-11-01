> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Who Pays for an Interactive Artifact?

An **interactive artifact** is a generated page that can call tools or refresh data after it is opened (dashboards, live forms, script-connected HTML). Opening it can start a new billed run.

## Who pays

| Who opens it               | Who is billed       |
| -------------------------- | ------------------- |
| You                        | You / your org pool |
| A teammate in the same org | The org pool        |
| Someone outside the org    | Their account       |

The agent owner is not billed just because they created the file.

## What triggers a charge

Live artifacts run a sandbox script. That is billed at about **1 credit per 55 seconds**, with a **1 credit minimum** per run. Most loads cost 1 credit.

* First open of a live artifact
* Each refresh or action that runs the script again
* Each distinct session

Static files (PDF, CSV, PNG) do not keep billing after they are generated.

## If they run out of credits

The artifact stops making live calls. They see a credit error. They are not silently billed to the owner.

## Keep costs down

* Prefer static exports when the reader does not need live data.
* Avoid auto-refresh timers unless the data has to stay live.
* Each refresh creates a new sandbox. There is no state between loads.

## Related

<CardGroup cols={2}>
  <Card title="Agent Artifacts" icon="file" href="/core-concepts/agent_artifacts">
    How generated files work
  </Card>

  <Card title="Credits" icon="coins" href="/core-concepts/credits">
    Chat and compute pricing
  </Card>
</CardGroup>
