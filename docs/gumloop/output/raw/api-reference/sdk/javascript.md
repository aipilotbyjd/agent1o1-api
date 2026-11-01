> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# JavaScript SDK

For convenience, we have created a Gumloop JavaScript SDK to more easily perform operations like starting an automation and retrieving outputs.

## Installation

```bash theme={"dark"}
npm install gumloop
```

## Usage

```typescript theme={"dark"}
import { GumloopClient } from "gumloop";

// Initialize the client
const client = new GumloopClient({
  apiKey: "your_api_key",
  userId: "your_user_id",
});

// Run a workflow and wait for outputs
async function runFlow() {
  try {
    const output = await client.runFlow("your_flow_id", {
      recipient: "example@email.com",
      subject: "Hello",
      body: "World",
    });

    console.log(output);
  } catch (error) {
    console.error("Workflow execution failed:", error);
  }
}

runFlow();
```

Optionally add a `project_id` when creating the client if running automations in a workspace:

```typescript theme={"dark"}
const client = new GumloopClient({
  apiKey: "your_api_key",
  userId: "your_user_id",
  projectId: "your_project_id"
});
```
