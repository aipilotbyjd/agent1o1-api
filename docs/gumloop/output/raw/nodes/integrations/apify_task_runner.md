> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Apify Task Runner

Gumloop's Apify Task Runner lets you run your Apify tasks directly inside Gumloop workflows. Scrape data with Apify, then process it with AI, send results via email, update spreadsheets, or connect to any of Gumloop's 100+ integrations.

Build workflows that automatically collect data from websites and deliver insights to your team through Slack, Gmail, Google Sheets, or wherever you need them.

## Node Inputs

### Required Field

* **Task**: Select the Apify task to execute

### Optional Fields

* **Maximum Run Time**: Time limit in minutes (default: 5)
* **Output Entries Count**: Number of results to retrieve (default: 10)
* **Output Fields**: Select specific data fields to extract

## Connect Apify with Gumloop

To use the Apify integration in Gumloop, you will need:

* [An Apify account](https://apify.com/)
* [A Gumloop account](https://www.gumloop.com/hub)
* At least one Apify task that has been run previously

## Step 1: Get your Apify API Key

First, you'll need to get your API key from Apify Console:

1. Navigate to [Apify Console Settings](https://console.apify.com/settings/integrations)
2. Copy your API token from the Integrations section

<div align="center">
  <img src="https://mintcdn.com/agenthub/BbHU4APLtf15VcpU/images/generate_apify_token.png?fit=max&auto=format&n=BbHU4APLtf15VcpU&q=85&s=ecafa8679932eeba1f0dd57370b655ab" alt="Generate Apify API token interface" width="800" data-path="images/generate_apify_token.png" />
</div>

## Step 2: Add Apify Credentials to Gumloop

Next, connect your Apify account to Gumloop:

1. Go to [Connectors page](https://www.gumloop.com/personal/connectors)
2. Search for "Apify" in the credentials list
3. Add your Apify API key from Step 1
4. Save the credential

<div align="center">
  <img src="https://mintcdn.com/agenthub/dn7emlKONFx9smnZ/images/apify_credential_gumloop.png?fit=max&auto=format&n=dn7emlKONFx9smnZ&q=85&s=3065c4ab43c4fdcf9541aab1c34daf61" alt="Gumloop credentials interface for Apify" width="800" data-path="images/apify_credential_gumloop.png" />
</div>

## Step 3: Add Apify Task Runner to Your Workflow

Now you can add the Apify Task Runner to your Gumloop pipeline:

1. [Open your Gumloop pipeline editor](https://www.gumloop.com/pipeline)
2. Search for "Apify Task Runner" in the Node Library
3. Drag and drop the node onto your canvas

<div align="center">
  <img src="https://mintcdn.com/agenthub/dn7emlKONFx9smnZ/images/apify_task_runner_node_library.png?fit=max&auto=format&n=dn7emlKONFx9smnZ&q=85&s=d06218525848ade9f2a09d8b964c7724" alt="Alt text" width="800" data-path="images/apify_task_runner_node_library.png" />
</div>

## Step 4: Create and Save Tasks in Apify

The Apify Task Runner node fetches tasks from your Saved Tasks in Apify Console. You'll need to create tasks from your Actors:

1. Navigate to your [Apify Actors](https://console.apify.com/actors)
2. Click on the Actor you want to use
3. Click "Create a task" next to the Run button
4. Configure your task settings and save

<div align="center">
  <img src="https://mintcdn.com/agenthub/dn7emlKONFx9smnZ/images/create_apify_task.png?fit=max&auto=format&n=dn7emlKONFx9smnZ&q=85&s=0e736d1c993d822c460944c33ea9d8e0" alt="Alt text" width="800" data-path="images/create_apify_task.png" />
</div>

**Important**: The Task Runner only displays tasks that have been saved in your Apify Console, not individual Actors.

## Step 5: Run Your Tasks

Before tasks appear in Gumloop, they must be executed at least once in Apify:

1. Go to your [Saved Tasks](https://console.apify.com/actors/tasks)
2. Click on the task you want to use
3. Click "Start" to execute it
4. Wait for the task to complete

<div align="center">
  <img src="https://mintcdn.com/agenthub/w1F7hfGEH4EChCiL/images/start_apify_task.png?fit=max&auto=format&n=w1F7hfGEH4EChCiL&q=85&s=0a6c0db456ae32e8c1d8d3a3c8b2fae7" alt="Alt text" width="800" data-path="images/start_apify_task.png" />
</div>

This step is required because Gumloop needs to understand the output structure of your task to properly configure data fields.

## Step 6: Configure Your Gumloop Workflow

Finally, configure the Apify Task Runner node in your Gumloop workflow:

1. Select your task from the dropdown menu
2. Choose the output fields you want to use
3. Configure maximum run time and output limits
4. Connect the node to other workflow components

<div align="center">
  <img src="https://mintcdn.com/agenthub/dn7emlKONFx9smnZ/images/apify_task_runner_node.png?fit=max&auto=format&n=dn7emlKONFx9smnZ&q=85&s=222e5302f7e4cbd02cd85b742200286c" alt="Alt text" width="800" data-path="images/apify_task_runner_node.png" />
</div>

## Example Workflow

Here's a simple example of how to use Apify with Gumloop:

**Web Scraping + AI Analysis + Email Report**

```text theme={"dark"}
Apify Task Runner → Ask AI → Combine Text → Gmail Sender
```

1. **Apify Task Runner**: Scrapes product prices from an e-commerce site
2. **Ask AI**: Analyzes price trends and identifies opportunities
3. **Combine Text**: Formats the analysis into a readable report
4. **Gmail Sender**: Emails the report to stakeholders

This workflow runs automatically and delivers actionable insights directly to your inbox.

## Best Practices

1. **Test in Apify First**: Always run your tasks in Apify Console before using in Gumloop
2. **Set Realistic Timeouts**: Match the timeout to your task's expected runtime
3. **Limit Output Size**: Only retrieve the data you need to optimize performance
4. **Use Specific Fields**: Select only the output fields required for your workflow
5. **Monitor Usage**: Keep track of both Apify and Gumloop credit consumption

## Native Apify Nodes for Popular Use Cases

For even more powerful and customizable automation, Gumloop offers native nodes for popular Apify use cases that provide enhanced functionality and easier configuration:

### Specialized Nodes Available

* **Instagram**
* **TikTok**
* **YouTube**
* **Google Maps**

### Why Use Native Nodes?

* **Pre-configured**: No need to manage Apify tasks or API keys
* **Enhanced Features**: Built-in data validation and formatting
* **Better Performance**: Optimized for Gumloop
* **Fully Customizable**: Easily customize what you want to do with the node using a prompt

### General MCP Tutorial to Create your Own Node

* [Create an MCP Node](https://www.gumloop.com/university/video/mcp-nodes)
* [MCP Nodes Best Practices](https://www.gumloop.com/university/video/mcp-nodes-best-practices)

## Important Considerations:

1. Requires Authentication with Apify - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Task must be run once on Apify platform first
3. Output format depends on entries count
4. Consider runtime limits for large tasks

In summary, the Apify Task Runner node streamlines web automation and scraping tasks through Apify's platform, making it ideal for data collection and monitoring workflows.
