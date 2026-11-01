> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Azure

> Manage Azure subscriptions, resources, and cloud infrastructure with AI-powered automation.

Azure is Microsoft's cloud platform. The Azure MCP server lets you inspect and manage your Azure resources through the Azure Resource Manager API using natural language.

## What Can It Do?

* **Explore subscriptions and resources** across regions and resource groups
* **Manage compute** including virtual machines, disks, snapshots, and AKS clusters
* **Work with storage** accounts, blob containers, and file shares
* **Inspect databases** such as Azure SQL, PostgreSQL, MySQL, Cosmos DB, and Redis
* **Operate App Service, Container Apps, and registries**
* **Query monitoring data** including metrics, activity logs, alerts, and Advisor recommendations
* **Review security and governance** with Key Vaults, RBAC role assignments, and Azure Policy

## Where to Use It

### In Agents (Recommended)

Add Azure as a tool to any agent. The agent can then inspect and manage your cloud resources conversationally, choosing the right actions based on context.

<video autoPlay muted loop playsInline className="w-full rounded-xl border border-pink-200 dark:border-pink-800" src="https://mintcdn.com/agenthub/B_VSlOOQHPvtlTyv/images/Connect_MCP_Tool_Agents.mp4?fit=max&auto=format&n=B_VSlOOQHPvtlTyv&q=85&s=f297b8516c66ea5697f7d5ef1cce934a" data-path="images/Connect_MCP_Tool_Agents.mp4" />

**To add an MCP tool to your agent:**

1. Open your agent's configuration
2. Click **Add tools** → **Connect an app with MCP**
3. Search for the integration and select it
4. Authenticate with your account

<Tip>
  You can control which tools your agent has access to. After adding an integration, click on it to enable or disable specific tools based on what your agent needs.
</Tip>

<Info>
  The Azure account you connect must have at least Reader access to the subscriptions you want to work with, and write permissions for any tool that creates, deletes, or changes power state.
</Info>

### In Workflows (Via Agent Node)

For automated pipelines, use an [Agent Node](/core-concepts/agent_node) with Azure tools. This gives you the flexibility of an agent within a deterministic workflow.

### As a Custom MCP Node

You can also create a standalone MCP node for a specific action. This generates a reusable node that performs one task, useful when you need the same operation repeatedly in workflows.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1084821932" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="MCP Nodes tutorial" />
</div>

**To create a custom MCP node:**

1. Go to your node library and search for the integration
2. Click **Create a node with AI**
3. Describe the specific action you want (e.g., "List all running virtual machines")
4. Test the node and save it for reuse

<Info>
  Custom MCP nodes are single-purpose by design. For tasks that require multiple steps or dynamic decision-making, use an agent instead.
</Info>

## Available Tools

### Subscription and Resource Tools

| Tool                      | Description                                             |
| ------------------------- | ------------------------------------------------------- |
| **List Subscriptions**    | List subscriptions the authenticated user has access to |
| **List Resource Groups**  | List resource groups in a subscription                  |
| **Get Resource Group**    | Get details of a resource group                         |
| **Create Resource Group** | Create or update a resource group                       |
| **Delete Resource Group** | Delete a resource group and all resources inside it     |
| **List Resources**        | List resources in a subscription or resource group      |
| **List Locations**        | List regions available to a subscription                |
| **List Deployments**      | List ARM template deployments in a resource group       |
| **Get Deployment**        | Get details of an ARM template deployment               |

### Compute Tools

| Tool                           | Description                                                          |
| ------------------------------ | -------------------------------------------------------------------- |
| **List Virtual Machines**      | List virtual machines in a subscription or resource group            |
| **Get Virtual Machine**        | Get a VM's configuration and optional runtime status                 |
| **Start Virtual Machine**      | Start a stopped or deallocated VM                                    |
| **Restart Virtual Machine**    | Restart a running VM                                                 |
| **Deallocate Virtual Machine** | Stop a VM and release its compute resources so compute billing stops |
| **Power Off Virtual Machine**  | Power off a VM while keeping it allocated and billed                 |
| **List VM Sizes**              | List VM sizes available in a region                                  |
| **List AKS Clusters**          | List AKS managed clusters in a subscription or resource group        |
| **Get AKS Cluster**            | Get details of an AKS managed cluster                                |
| **List Disks**                 | List managed disks in a subscription or resource group               |
| **List Snapshots**             | List disk snapshots in a subscription or resource group              |
| **Create Snapshot**            | Create a snapshot from a managed disk                                |

### Storage Tools

| Tool                       | Description                                               |
| -------------------------- | --------------------------------------------------------- |
| **List Storage Accounts**  | List storage accounts in a subscription or resource group |
| **Get Storage Account**    | Get properties of a storage account                       |
| **Create Storage Account** | Create or update a storage account                        |
| **List Blob Containers**   | List blob containers in a storage account                 |
| **Create Blob Container**  | Create a blob container in a storage account              |
| **Delete Blob Container**  | Delete a blob container from a storage account            |
| **List File Shares**       | List file shares in a storage account                     |

### Database Tools

| Tool                              | Description                                                              |
| --------------------------------- | ------------------------------------------------------------------------ |
| **List SQL Servers**              | List Azure SQL servers in a subscription or resource group               |
| **List SQL Databases**            | List databases on an Azure SQL server                                    |
| **Get SQL Database**              | Get details of an Azure SQL database                                     |
| **Create SQL Database**           | Create a database on an Azure SQL server                                 |
| **Delete SQL Database**           | Delete a database from an Azure SQL server                               |
| **List SQL Elastic Pools**        | List elastic pools on an Azure SQL server                                |
| **List SQL Firewall Rules**       | List firewall rules on an Azure SQL server                               |
| **Create SQL Firewall Rule**      | Create or update a firewall rule on an Azure SQL server                  |
| **List PostgreSQL Servers**       | List PostgreSQL flexible servers in a subscription or resource group     |
| **List PostgreSQL Databases**     | List databases on a PostgreSQL flexible server                           |
| **List MySQL Servers**            | List MySQL flexible servers in a subscription or resource group          |
| **List MySQL Databases**          | List databases on a MySQL flexible server                                |
| **List Cosmos DB Accounts**       | List Cosmos DB accounts in a subscription or resource group              |
| **List Cosmos DB SQL Databases**  | List SQL API databases in a Cosmos DB account                            |
| **List Cosmos DB SQL Containers** | List containers in a Cosmos DB SQL API database                          |
| **List Redis Caches**             | List Azure Cache for Redis instances in a subscription or resource group |

### App Service and Container Tools

| Tool                          | Description                                                                                              |
| ----------------------------- | -------------------------------------------------------------------------------------------------------- |
| **List Web Apps**             | List App Service apps in a subscription or resource group (function apps appear with kind `functionapp`) |
| **Get Web App**               | Get details of an App Service app                                                                        |
| **Start Web App**             | Start an App Service app                                                                                 |
| **Stop Web App**              | Stop an App Service app                                                                                  |
| **Restart Web App**           | Restart an App Service app                                                                               |
| **List App Service Plans**    | List App Service plans in a subscription or resource group                                               |
| **List Container Apps**       | List Container Apps in a subscription or resource group                                                  |
| **List Container Registries** | List Container Registries in a subscription or resource group                                            |

### Monitoring Tools

| Tool                              | Description                                                       |
| --------------------------------- | ----------------------------------------------------------------- |
| **Query Metrics**                 | Query Azure Monitor metric values for a resource                  |
| **List Metric Definitions**       | List metric definitions available for a resource                  |
| **List Activity Logs**            | List Activity Log events in a subscription                        |
| **List Alerts**                   | List Azure Monitor alerts in a subscription                       |
| **List Advisor Recommendations**  | List Azure Advisor recommendations in a subscription              |
| **List Resource Health**          | List resource health availability statuses in a subscription      |
| **List App Insights Components**  | List Application Insights components in a subscription            |
| **List Log Analytics Workspaces** | List Log Analytics workspaces in a subscription or resource group |
| **List Log Analytics Tables**     | List tables in a Log Analytics workspace                          |

### Security and Governance Tools

| Tool                        | Description                                               |
| --------------------------- | --------------------------------------------------------- |
| **List Key Vaults**         | List Key Vaults in a subscription or resource group       |
| **Get Key Vault**           | Get details of a Key Vault                                |
| **Create Key Vault**        | Create or update a Key Vault                              |
| **List Role Assignments**   | List RBAC role assignments in a subscription              |
| **List Role Definitions**   | List RBAC role definitions available in a subscription    |
| **Create Role Assignment**  | Assign an RBAC role to a principal at a given scope       |
| **List Policy Assignments** | List Azure Policy assignments in a subscription           |
| **List Policy Definitions** | List Azure Policy definitions available in a subscription |
| **List Policy Exemptions**  | List Azure Policy exemptions in a subscription            |
| **List Compute Usage**      | List compute quota limits and current usage in a region   |
| **List Retail Prices**      | List Azure retail prices for services and SKUs            |

### Messaging Tools

| Tool                               | Description                                                     |
| ---------------------------------- | --------------------------------------------------------------- |
| **List Service Bus Namespaces**    | List Service Bus namespaces in a subscription or resource group |
| **List Service Bus Queues**        | List queues in a Service Bus namespace                          |
| **List Service Bus Topics**        | List topics in a Service Bus namespace                          |
| **List Service Bus Subscriptions** | List subscriptions on a Service Bus topic                       |
| **List Event Hub Namespaces**      | List Event Hubs namespaces in a subscription or resource group  |
| **List Event Hubs**                | List event hubs in an Event Hubs namespace                      |
| **List Event Grid Topics**         | List Event Grid topics in a subscription or resource group      |
| **List Event Grid Subscriptions**  | List Event Grid event subscriptions for a topic or subscription |

### Platform Service Tools

| Tool                              | Description                                                         |
| --------------------------------- | ------------------------------------------------------------------- |
| **List Recovery Services Vaults** | List Recovery Services vaults in a subscription or resource group   |
| **List Backup Items**             | List backup protected items in a Recovery Services vault            |
| **List Backup Jobs**              | List backup jobs in a Recovery Services vault                       |
| **List IoT Hubs**                 | List IoT hubs in a subscription or resource group                   |
| **List SignalR Services**         | List SignalR services in a subscription or resource group           |
| **List App Configuration Stores** | List App Configuration stores in a subscription or resource group   |
| **List Grafana Workspaces**       | List Managed Grafana workspaces in a subscription or resource group |
| **List Load Testing Resources**   | List Load Testing resources in a subscription or resource group     |
| **List Workbooks**                | List Azure Monitor workbooks in a subscription or resource group    |

## Example Prompts

Use these with your agent or in the Agent Node:

**Audit resources:**

```text theme={"dark"}
List all resource groups in my production subscription and what's running in each
```

**Check virtual machines:**

```text theme={"dark"}
Which virtual machines in the app-prod resource group are currently running?
```

**Save on costs:**

```text theme={"dark"}
Deallocate the staging-vm virtual machine and show me the Advisor cost recommendations
```

**Investigate an incident:**

```text theme={"dark"}
Show CPU metrics for app-prod-vm over the last 6 hours and any active alerts
```

**Review access:**

```text theme={"dark"}
List the role assignments in my production subscription
```

**Provision storage:**

```text theme={"dark"}
Create a storage account called datalakeprod in eastus with a blob container named raw
```

## Troubleshooting

| Issue                          | Solution                                                                                                                                               |
| ------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Empty subscription list        | The connected account needs at least Reader access to a subscription                                                                                   |
| Authorization errors on writes | Creating, deleting, or restarting resources requires Contributor-level permissions at that scope                                                       |
| Agent not finding a resource   | Give the subscription ID and resource group name so the agent can scope the lookup                                                                     |
| Unexpected results             | The agent may chain multiple tools (e.g., listing subscriptions first, then resource groups). Review the agent's reasoning to understand its approach. |
| Tool not available             | Verify the tool is [enabled in your agent's MCP configuration](/core-concepts/agents#tool-management-and-approvals)                                    |

<Warning>
  Tools like **Delete Resource Group**, **Delete SQL Database**, and **Deallocate Virtual Machine** change live infrastructure. Use [tool approvals](/core-concepts/agents#tool-management-and-approvals) to require a human check before destructive actions run.
</Warning>

## Need Help?

* [Agents documentation](/core-concepts/agents) for setup and best practices
* [Agent Node guide](/core-concepts/agent_node) for workflow integration
* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)
* Contact [support@gumloop.com](mailto:support@gumloop.com) for assistance

***

**Use this integration directly in Claude or Cursor.** Connect remotely via the [Azure MCP server](https://www.gumloop.com/mcp/azure) using credentials from your [Connectors page](https://www.gumloop.com/personal/connectors).
