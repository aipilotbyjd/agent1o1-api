> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Usage Data Export

Usage Data Export allows enterprise organization administrators to export comprehensive data from their Gumloop organization. This feature supports both **one-time exports** and **continuous data drains** for analysis, compliance reporting, credit consumption tracking, and backup purposes.

Exports can be scoped to the **entire organization** or to your **personal data only**. See [Export Scopes](#export-scopes) for details.

## What You Can Do

<CardGroup cols={2}>
  <Card title="Track Credit Consumption" icon="chart-line">
    Monitor credit usage across teams and users over time
  </Card>

  <Card title="Compliance Reporting" icon="file-shield">
    Export data for audit trails and regulatory compliance
  </Card>

  <Card title="Usage Analytics" icon="chart-pie">
    Analyze workflow execution patterns and user activity
  </Card>

  <Card title="Data Backup" icon="cloud-arrow-down">
    Create backups of organizational workflow metadata
  </Card>

  <Card title="Billing Analysis" icon="dollar-sign">
    Understand cost allocation across teams and projects
  </Card>

  <Card title="Continuous Sync" icon="paper-plane">
    Set up data drains to continuously push data to external destinations
  </Card>
</CardGroup>

<Info>
  Navigate to your organization's data export settings at: [gumloop.com/settings/organization/data\_export](https://gumloop.com/settings/organization/data_export)
</Info>

The data export page has two tabs: **Exports** for one-time data downloads, and **Drains** for continuous data syncing.

## Data Types

Gumloop supports six types of data for both exports and drains:

| Data Type              | Description                                                                                                 |
| ---------------------- | ----------------------------------------------------------------------------------------------------------- |
| **Workflow Runs**      | Execution history including run IDs, credit costs, timestamps, and workflow metadata                        |
| **Agents**             | Agent configurations including names, descriptions, system prompts, and tool settings                       |
| **Agent Interactions** | Agent interaction data including timestamps, credit costs, trigger types, and message counts                |
| **Credit Logs**        | Credit usage logs including amounts, categories, and balances                                               |
| **Audit Logs**         | Security and compliance audit logs including user actions, authentication events, and configuration changes |
| **Gumstack**           | Gumstack MCP tool call activity including timestamps, statuses, and latency                                 |

<div align="center">
  <img src="https://mintcdn.com/agenthub/nKS0CZGdPlz8j8t3/images/data-drain-type-selection.png?fit=max&auto=format&n=nKS0CZGdPlz8j8t3&q=85&s=048ec853c4591dac4628d94ffb9a60d9" alt="Data Type Selection" width="800" data-path="images/data-drain-type-selection.png" />
</div>

## One-Time Exports

One-time exports let you download a snapshot of your organization's data for a specific date range as a CSV file.

### Export Process

<Steps>
  <Step title="Select Data Type" icon="list">
    Choose what data you want to export from the six available types.
  </Step>

  <Step title="Select Date Range" icon="calendar">
    Define the time period for your data extraction.

    <div align="center">
      <img src="https://mintcdn.com/agenthub/dn7emlKONFx9smnZ/images/data-export-date-range.png?fit=max&auto=format&n=dn7emlKONFx9smnZ&q=85&s=7e2e9b3cd21b643b9c41f79634ccbcf6" alt="Date Range Selection" width="600" data-path="images/data-export-date-range.png" />
    </div>

    **Features:**

    * **Billing Period Presets**: Quickly select a billing period from the dropdown
    * **Custom Date Range**: Pick specific start and end dates with the calendar picker
    * **Timezone Support**: Choose the timezone for date interpretation
  </Step>

  <Step title="Choose Teams" icon="folder">
    Select which teams to include in your export.

    <Note>This step applies to **Workflow Runs**, **Agents**, and **Agent Interactions** exports only. **Credit Logs**, **Audit Logs**, and **Gumstack** exports are scoped to the entire organization.</Note>

    <div align="center">
      <img src="https://mintcdn.com/agenthub/dn7emlKONFx9smnZ/images/data-export-workspace-selection.png?fit=max&auto=format&n=dn7emlKONFx9smnZ&q=85&s=ec4beeba48dba3e24fc773fa775db1bc" alt="Team Selection" width="700" data-path="images/data-export-workspace-selection.png" />
    </div>

    <AccordionGroup>
      <Accordion title="Organization Teams">
        * **Searchable List**: Find teams quickly using search functionality
        * **Individual Selection**: Choose specific teams with checkboxes
        * **Select All Toggle**: Bulk select all organization teams
      </Accordion>

      <Accordion title="Personal Spaces">
        * **Optional Toggle**: Include personal spaces of organization members
        * **Comprehensive Coverage**: When enabled, includes all user personal spaces in the export

        <div align="center">
          <img src="https://mintcdn.com/agenthub/dn7emlKONFx9smnZ/images/data-export-personal-selection.png?fit=max&auto=format&n=dn7emlKONFx9smnZ&q=85&s=7d0a949646fc91bce3568fe00fea9c49" alt="Personal Space Selection" width="700" data-path="images/data-export-personal-selection.png" />
        </div>
      </Accordion>
    </AccordionGroup>
  </Step>

  <Step title="Select Data Fields" icon="table">
    Choose which data categories and fields to include in your export.

    <div align="center">
      <img src="https://mintcdn.com/agenthub/dn7emlKONFx9smnZ/images/data-export-field-selection.png?fit=max&auto=format&n=dn7emlKONFx9smnZ&q=85&s=27943ad8a5b009781baed6d15c4ae10e" alt="Data Field Selection" width="700" data-path="images/data-export-field-selection.png" />
    </div>

    ### Field Presets

    | Preset      | Description                                  |
    | ----------- | -------------------------------------------- |
    | **Minimal** | Essential identifiers and timestamps only    |
    | **Default** | Recommended set of commonly used fields      |
    | **Full**    | All available fields including detailed data |
    | **Custom**  | Select specific fields manually              |

    ### Available Data Categories

    <Tabs>
      <Tab title="Team Info">
        | Field     | Description                    |
        | --------- | ------------------------------ |
        | Team ID   | Unique identifier for the team |
        | Team Name | Display name of the team       |
      </Tab>

      <Tab title="Workbook Info">
        | Field             | Description                        |
        | ----------------- | ---------------------------------- |
        | Workbook ID       | Unique identifier for the workbook |
        | Workbook Name     | Display name of the workbook       |
        | Created Timestamp | When the workbook was created      |
      </Tab>

      <Tab title="User Info">
        | Field      | Description                    |
        | ---------- | ------------------------------ |
        | User ID    | Unique identifier for the user |
        | User Email | Email address of the user      |
      </Tab>

      <Tab title="Run Info">
        | Field              | Description                            |
        | ------------------ | -------------------------------------- |
        | Run ID             | Unique identifier for the workflow run |
        | Credit Cost        | Number of credits consumed             |
        | Created Timestamp  | When the run started                   |
        | Finished Timestamp | When the run completed                 |
      </Tab>

      <Tab title="Credit Logs">
        | Category        | Fields                                                                                                                                           |
        | --------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
        | **User**        | User Email, Custom Role ID(s) (disabled by default), Custom Role Name(s) (disabled by default)                                                   |
        | **Log**         | Date, Category, Type, Name, Amount, Balance                                                                                                      |
        | **Identifiers** | Log ID, Correlation ID (run or interaction join key, disabled by default), Balance Scope (disabled by default), Project ID (disabled by default) |
      </Tab>
    </Tabs>
  </Step>
</Steps>

### Managing Exports

Monitor and manage all your previous export requests from the **Exports** tab.

<div align="center">
  <img src="https://mintcdn.com/agenthub/dn7emlKONFx9smnZ/images/data-export-history.png?fit=max&auto=format&n=dn7emlKONFx9smnZ&q=85&s=1ad9df8a678486c08134e1b402a322c0" alt="Export History" width="900" data-path="images/data-export-history.png" />
</div>

**What you'll see:**

* **Status Tracking**: Real-time status updates with colored badges
* **Date Range Display**: Clear indication of exported data timeframes
* **Creation Timestamps**: When each export was requested
* **Download Actions**: Direct CSV file download capability

<Tip>
  When your export completes successfully, you'll receive an email notification confirming that your export is ready for download.
</Tip>

### Downloaded Data Format

Exported data is provided as CSV files with:

* **Header Row**: Column names matching selected fields
* **Comma Separation**: Standard CSV format for easy import into analysis tools
* **Date Formatting**: ISO 8601 timestamp format

***

## Data Drains

Data Drains let you continuously push organization data to an external destination. Unlike one-time exports, drains run automatically in the background and sync new data as it becomes available.

<div align="center">
  <img src="https://mintcdn.com/agenthub/nKS0CZGdPlz8j8t3/images/data-drain-overview.png?fit=max&auto=format&n=nKS0CZGdPlz8j8t3&q=85&s=23bc1b1b89790f3647d52243b2f9e040" alt="Data Drains Overview" width="900" data-path="images/data-drain-overview.png" />
</div>

### How Drains Work

Once created, a drain continuously monitors your selected data type for new records. When new data appears, it is automatically batched and delivered to your configured destination. The drain keeps track of what has already been synced using an internal cursor, so you never receive duplicate data.

Key behaviors:

* **Automatic syncing**: New data is pushed to your destination without manual intervention
* **Adaptive polling**: Sync frequency adjusts based on data volume, from every 15 seconds during high activity to up to 10 minutes during quiet periods
* **Crash-safe cursor**: The sync cursor advances after each successfully delivered batch, so no data is lost if a delivery fails mid-cycle
* **Automatic error handling**: If a drain encounters 3 consecutive failures, it is automatically paused with an "Error" status

### Creating a Drain

To create a new drain, switch to the **Drains** tab and click **+ Add Drain**. The drain creation wizard walks you through the following steps:

<Steps>
  <Step title="Select Data Type" icon="list">
    Choose the type of data you want to continuously sync. Available types are: Workflow Runs, Agents, Agent Interactions, Credit Logs, Audit Logs, and Gumstack.
  </Step>

  <Step title="Configure Scope" icon="calendar">
    Depending on the data type, configure filtering options for the drain:

    * **Workflow Runs, Agents, Agent Interactions**: Select which teams to include and optionally include personal workspaces
    * **Credit Logs**: Optionally filter by credit log category
    * **Audit Logs, Gumstack**: No additional scope configuration needed (this step is skipped)

    <Note>Unlike one-time exports, drains do not require a date range. They start syncing from the time the drain is created and continue forward.</Note>
  </Step>

  <Step title="Select Fields" icon="table">
    Choose which fields to include in the synced data. The same field presets are available as for one-time exports: Minimal, Default, Full, or Custom.

    <div align="center">
      <img src="https://mintcdn.com/agenthub/nKS0CZGdPlz8j8t3/images/data-drain-field-selection.png?fit=max&auto=format&n=nKS0CZGdPlz8j8t3&q=85&s=5f1437c3fb2c00d09bfd5c701d029643" alt="Drain Field Selection" width="800" data-path="images/data-drain-field-selection.png" />
    </div>
  </Step>

  <Step title="Configure Destination" icon="paper-plane">
    Set up where data should be delivered. You'll need to provide a **drain name** and select a **destination type**.

    <div align="center">
      <img src="https://mintcdn.com/agenthub/nKS0CZGdPlz8j8t3/images/data-drain-destination.png?fit=max&auto=format&n=nKS0CZGdPlz8j8t3&q=85&s=b173059b33781c2eb17c7af37af29617" alt="Drain Destination Configuration" width="800" data-path="images/data-drain-destination.png" />
    </div>

    Gumloop supports three destination types:

    <Tabs>
      <Tab title="Custom Endpoint">
        Push data to any HTTP endpoint you control.

        **Configuration:**

        * **Endpoint Authentication**: Connect your HTTP credentials (URL, authorization header, and optional signing secret)
        * **Format**: Choose between **JSON** (default) or **OTLP** (OpenTelemetry Log Protocol)

        **Payload format (JSON):**

        ```json theme={"dark"}
        {
          "source": "gumloop",
          "drain_id": "abc123",
          "drain_name": "My Drain",
          "data_type": "credit_logs",
          "records": [
            { "user_email": "user@example.com", "amount": -5, "category": "Workflow Runs", ... }
          ]
        }
        ```

        See the [Drain Payload Reference](#drain-payload-reference) for the full record semantics.

        **Headers included:**

        * `Content-Type: application/json`
        * `X-Gumloop-Drain-Id`: The drain's unique identifier
        * `X-Gumloop-Data-Type`: The data type being synced
        * `Authorization`: Your configured authorization header (if set)
        * `X-Gumloop-Signature`: HMAC-SHA256 signature of the request body (if a signing secret is configured)

        <Tip>
          Use the signing secret to verify that incoming requests are genuinely from Gumloop. Compute an HMAC-SHA256 of the raw request body using your signing secret and compare it with the `X-Gumloop-Signature` header.
        </Tip>
      </Tab>

      <Tab title="Amazon S3">
        Push data as JSON files to an S3 bucket.

        **Configuration:**

        * **AWS Credentials**: Connect your AWS access key and secret
        * **Bucket**: The name of your S3 bucket
        * **Path Prefix** (optional): A folder path prefix for the uploaded files (e.g., `exports/gumloop`)

        Files are organized by date with the path pattern:

        ```text theme={"dark"}
        {prefix}/{YYYY}/{MM}/{DD}/{drain_id}_{HHMMSS}_{microseconds}.json
        ```
      </Tab>

      <Tab title="Datadog">
        Push data as logs to Datadog.

        **Configuration:**

        * **Datadog API Key**: Connect your Datadog API key credentials

        Logs are sent to Datadog's Log Intake API with:

        * **Source**: `gumloop`
        * **Service**: `gumloop.{data_type}` (e.g., `gumloop.credit_logs`)
        * **Tags**: `drain_id:{id},drain_name:{name}`

        <Tip>
          Supported Datadog sites include `datadoghq.com`, `datadoghq.eu`, `us3.datadoghq.com`, `us5.datadoghq.com`, `ap1.datadoghq.com`, and `ddog-gov.com`.
        </Tip>
      </Tab>
    </Tabs>
  </Step>

  <Step title="Review" icon="check">
    Review all your settings before creating the drain. When you click **Create Drain**, Gumloop runs a preflight check to verify the destination is reachable and properly configured. If the check passes, the drain is created and begins syncing immediately.
  </Step>
</Steps>

### Managing Drains

The **Drains** tab shows all your configured drains with the following information:

| Column          | Description                               |
| --------------- | ----------------------------------------- |
| **Name**        | Drain name and destination type icon      |
| **Status**      | Current status: Active, Paused, or Error  |
| **Data Type**   | The type of data being synced             |
| **Last Synced** | When data was last successfully delivered |
| **Created**     | When the drain was first set up           |
| **Actions**     | Delete the drain                          |

### Drain Statuses

| Status     | Description                                                                                                                              |
| ---------- | ---------------------------------------------------------------------------------------------------------------------------------------- |
| **Active** | The drain is running normally and syncing data                                                                                           |
| **Paused** | The drain has been paused                                                                                                                |
| **Error**  | The drain encountered 3 consecutive delivery failures and was automatically paused. Check your destination configuration and credentials |

<Warning>
  Deleting a drain is permanent. If you delete a drain and create a new one, the new drain will start syncing from the current time, not from where the previous drain left off.
</Warning>

***

## Drain Payload Reference

Use this section if you are building a system on top of drain output — a usage warehouse, a cost monitor, or an anomaly detector.

### Payload envelope

Every delivery (one S3 object, or one HTTP POST body) is a single JSON document:

```json theme={"dark"}
{
  "source": "gumloop",
  "drain_id": "abc123",
  "drain_name": "My Drain",
  "data_type": "credit_logs",
  "records": [ { "...": "one object per record" } ]
}
```

For Datadog destinations there is no envelope: each record is sent as its own log, with the record JSON in the log `message` and the drain identity in the tags.

| Destination     | Records per delivery |
| --------------- | -------------------- |
| Amazon S3       | 5,000                |
| Custom Endpoint | 1,000                |
| Datadog         | 1,000                |

<Note>
  **There is no fixed record schema.** The keys of each record are exactly the fields you selected when creating the drain, plus that data type's timestamp field, which is always included. Two drains on the same data type with different field selections produce different record shapes.
</Note>

### Timestamps

Credit log timestamps are ISO 8601 with a `T` separator (`2026-08-19T17:18:04.512+00:00`). Timestamps on workflow, agent, and agent interaction records are rendered with a space separator (`2026-08-19 17:18:04.512000+00:00`). All values are UTC. Parse both forms.

### Delivery model

Each drain tracks a cursor on exactly one timestamp column:

| Data Type          | Cursor field             |
| ------------------ | ------------------------ |
| Workflow Runs      | `pl_run_created_ts`      |
| Agents             | `agent_created_ts`       |
| Agent Interactions | `interaction_created_ts` |
| Credit Logs        | `timestamp`              |
| Audit Logs         | `timestamp`              |
| Gumstack           | `created_at`             |

Every sync selects records newer than the cursor in ascending order, delivers them, then advances the cursor. **Each record is delivered once and is never re-sent or updated.**

<Warning>
  **Workflow Runs and Agent Interactions are cursored on creation time, but their credit cost accumulates while the run or session is still going.** If a sync fires while a run is in flight, that record is delivered with the cost it had at that moment, and the corrected total is never re-sent.

  For cost reporting, use the **Credit Logs** drain: those rows are written at the moment each charge happens and are never modified afterwards.
</Warning>

### Cost fields by data type

<Tabs>
  <Tab title="Credit Logs">
    One record per individual charge — this is the ledger that the credit numbers in the Gumloop UI are computed from.

    | Field                                           | Meaning                                                                                 |
    | ----------------------------------------------- | --------------------------------------------------------------------------------------- |
    | `log_id`                                        | Unique id of the ledger row                                                             |
    | `timestamp`                                     | When the charge was recorded (UTC)                                                      |
    | `amount`                                        | Signed credits. **Deductions are negative**; grants and manual adjustments are positive |
    | `balance`                                       | Credit balance after this row, in the scope given by `balance_scope`                    |
    | `balance_scope`                                 | `organization` or `user`                                                                |
    | `category`                                      | Human-readable category label (see below)                                               |
    | `type`                                          | Human-readable type label (see below)                                                   |
    | `name`                                          | Display name of the workflow or agent that was charged                                  |
    | `correlation_id`                                | Event key — the workflow run, agent interaction, or operator run the charge belongs to  |
    | `project_id`                                    | Team the charge is attributed to                                                        |
    | `user_email`                                    | Member who incurred the charge                                                          |
    | `permission_group_id` / `permission_group_name` | The member's custom role(s), `; `-separated                                             |

    <Note>
      Some rows are accounting entries rather than usage: credit resets and credit cap adjustments carry no `amount`. Exclude them from consumption totals.
    </Note>
  </Tab>

  <Tab title="Workflow Runs">
    One record per workflow run.

    | Field                                      | Meaning                                               |
    | ------------------------------------------ | ----------------------------------------------------- |
    | `credit_cost`                              | Credits charged for **that run only**                 |
    | `run_id`                                   | Matches `correlation_id` on the run's credit log rows |
    | `pl_run_created_ts` / `pl_run_finished_ts` | When the run started and finished                     |

    Subflow (child) runs arrive as their own records with their own `credit_cost`, so summing every record gives the full total without double counting.

    If the drain was created with the `hide_child_runs` option, child runs are omitted and each parent record's `credit_cost` instead includes its children's cost. Don't mix results from the two configurations.
  </Tab>

  <Tab title="Agent Interactions">
    One record per agent interaction.

    | Field              | Meaning                                                                          |
    | ------------------ | -------------------------------------------------------------------------------- |
    | `credit_cost`      | Total credits: model inference + tools + workflows + compute time + platform fee |
    | `llm_credit_cost`  | Model inference portion only                                                     |
    | `tool_credit_cost` | Tool and MCP call portion                                                        |
    | `flow_credit_cost` | Portion from workflows the agent ran                                             |
    | `interaction_id`   | Matches `correlation_id` on the interaction's credit log rows                    |

    Compute time and platform fee are included in `credit_cost` but have no dedicated fields. To break them out, read the `Compute Time` and `Platform Fee` rows from a Credit Logs drain.
  </Tab>

  <Tab title="Gumstack">
    Gumstack records describe tool call activity (`tool_name`, `server_id`, `status`, `latency_ms`, `error`, and the calling user or agent) and contain **no cost fields**.

    The credits for those calls appear in Credit Logs as `Gumstack Tool Call` and `guMCP Tool Call` rows.
  </Tab>
</Tabs>

Agents and Audit Logs records contain no cost fields.

### Reconciling drain data with the UI

<Steps>
  <Step title="Sum credits from Credit Logs, not from run records">
    Add up `amount` on Credit Logs records for the period. Deductions are negative, so consumption is the absolute value of the negative rows. Records without an `amount` are not usage.
  </Step>

  <Step title="Keep only usage categories">
    Consumption is the sum of `Workflow Runs`, `Agent Chats`, `AI Utilities`, `Company Brain`, `Custom Node Development`, and `External MCP Call`. `Credit Reset`, `Manual Adjustment`, `Credit Cap Adjustment`, and `Gifted Credits` are balance bookkeeping, not spend.
  </Step>

  <Step title="Group by correlation_id to match the UI's rows">
    A drain delivers one record per charge, while the credit usage view in the UI shows one row per event with the charges summed. Grouping records by `correlation_id` reproduces those rows.

    <Note>
      `Custom Trigger Check` rows are the exception: their `correlation_id` is the owning agent rather than a single event, and the UI groups them per calendar day.
    </Note>
  </Step>

  <Step title="Watch the boundaries">
    Organization-scoped drains deliver only organization-scope rows, so per-member credit cap entries never double count your totals. Also note that no credit log data exists before **February 11, 2026** — a window that starts earlier will report less than the UI shows for the same range.
  </Step>
</Steps>

<Tip>
  A practical monitoring setup: use a Credit Logs drain as your cost table, and Workflow Runs, Agent Interactions, and Agents drains as the dimension tables you join to it on `run_id` / `interaction_id` = `correlation_id`. Treat the `credit_cost` fields on those records as a cross-check, never as the source of truth.
</Tip>

### Category and type labels

Drain records contain the human-readable labels, not the internal values. The `category_filter` option on the API, however, expects the internal value (for example `agent_run`).

<AccordionGroup>
  <Accordion title="Categories" icon="tags">
    | Internal value                           | Label in records        |
    | ---------------------------------------- | ----------------------- |
    | `pipeline_run`                           | Workflow Runs           |
    | `agent_run`                              | Agent Chats             |
    | `ai_utility`                             | AI Utilities            |
    | `company_brain`                          | Company Brain           |
    | `custom_node_rd`                         | Custom Node Development |
    | `external_gumcp_call`                    | External MCP Call       |
    | `credit_limit_reset`                     | Credit Reset            |
    | `manual_adjustment`                      | Manual Adjustment       |
    | `permission_group_credit_cap_adjustment` | Credit Cap Adjustment   |
    | `checklist_item_completion`              | Gifted Credits          |
  </Accordion>

  <Accordion title="Types" icon="list">
    | Internal value                 | Label in records     |
    | ------------------------------ | -------------------- |
    | `initial_pipeline_run`         | Initial Run          |
    | `ai_node_cost`                 | AI Node              |
    | `node_cost`                    | Node Cost            |
    | `step`                         | Chat Output          |
    | `compaction`                   | Chat Summarization   |
    | `compute_time`                 | Compute Time         |
    | `platform_fee`                 | Platform Fee         |
    | `image_generation`             | Image Generation     |
    | `transcription`                | Audio Transcription  |
    | `gumcp_tool_call`              | guMCP Tool Call      |
    | `gumstack_tool_call`           | Gumstack Tool Call   |
    | `bigquery_query`               | Analytics Query      |
    | `custom_trigger_check`         | Custom Trigger Check |
    | `interactive_artifact_execute` | Interactive Artifact |
    | `interaction_evaluation`       | Chat Evaluation      |
    | `model_router`                 | Model Routing        |
    | `brain_indexing`               | Brain Indexing       |
    | `brain_query`                  | Brain Search         |
    | `test_run`                     | Test Run             |
    | `external_client`              | External Client      |
  </Accordion>
</AccordionGroup>

***

## Export Scopes

Every export (one-time or drain) runs in one of two scopes:

| Scope                      | What it returns                                                         | Who can use it                                          |
| -------------------------- | ----------------------------------------------------------------------- | ------------------------------------------------------- |
| **Organization** (default) | All data across selected teams and personal spaces in your organization | Organization admins with the Export Data permission     |
| **Personal**               | Only rows where you are the creator or runner                           | Any organization member with the Export Data permission |

### Organization Scope

This is the default behavior. Organization-scoped exports let admins select specific teams, include personal spaces, and filter by entity IDs. All existing exports use this scope.

### Personal Scope

Personal exports return only your own data: workflow runs you triggered, agents you created, and interactions from your sessions. This is useful for individual compliance requests or personal usage auditing.

**How it works:**

* The export filters rows by your user ID automatically. You do not need to select teams or workspaces.
* `start_date`, `end_date`, and `export_fields` are required.
* Team/workspace selection parameters (`workspace_ids`, `include_all_workspaces`) are ignored.
* Supported for **Workflow Runs**, **Agents**, **Agent Interactions**, and **Credit Logs**. Not supported for Audit Logs or Gumstack exports.

**Via the API**, set `export_scope` to `"personal"` in your request body:

```json theme={"dark"}
{
  "export_scope": "personal",
  "data_type": "workflows",
  "start_date": "2025-01-01T00:00:00",
  "end_date": "2025-06-01T00:00:00",
  "export_fields": ["run_id", "credit_cost", "pl_run_created_ts", "pl_run_finished_ts", "flowbook_name"]
}
```

<Info>
  Personal exports require the **Enterprise** plan. The `export_scope` parameter defaults to `"organization"` when omitted, preserving backward compatibility.
</Info>

***

## API Integration

For programmatic access to data export functionality, use the following endpoints:

<AccordionGroup>
  <Accordion title="Create Data Export" icon="plus">
    **Endpoint**: `POST /export_data`

    **Request Body:**

    ```json theme={"dark"}
    {
      "user_id": "string",
      "export_fields": [
        "workbook_id",
        "workbook_name", 
        "user_email",
        "run_id",
        "credit_cost",
        "pl_run_created_ts",
        "pl_run_finished_ts"
      ],
      "start_date": "2024-01-01T00:00:00",
      "end_date": "2024-12-31T23:59:59",
      "include_personal_workspaces": false,
      "workspace_ids": ["workspace_id_1", "workspace_id_2"],
      "entity_ids": ["workbook_id_1", "workbook_id_2"]
    }
    ```

    **Optional Parameters:**

    * `export_scope`: `"organization"` (default) or `"personal"`. Personal scope returns only your own data and requires the Enterprise plan.
    * `data_type`: The type of data to export (`"workflows"`, `"agents"`, `"agent_interactions"`, `"credit_logs"`, `"audit_logs"`, or `"gumstack"`). Defaults to `"workflows"`.
    * `entity_ids`: Filter the export to specific entities. For workflow exports, provide workbook IDs. For agent exports, provide agent IDs. Not applicable for credit log exports.

    <Note>
      **Credit log exports** work differently from workflow and agent exports:

      * Team/workspace selection parameters (`workspace_ids`, `include_personal_workspaces`) are **not applicable**
      * Credit log exports are always scoped to the entire organization
      * Use `category_filter` to filter by a specific credit log category (e.g., `"PIPELINE_RUN"`, `"AGENT_RUN"`)
      * Use `permission_group_filter` to filter credit logs by a specific [custom role](/enterprise-features/user_groups) (legacy parameter name retained for API compatibility)
    </Note>
  </Accordion>

  <Accordion title="Check Export Status" icon="circle-check">
    **Endpoint**: `GET /export_status`

    **Query Parameters:**

    * `user_id` (required): User requesting the status
    * `data_export_id` (required): Export job identifier
    * `download` (optional): Set to `true` to download the file if completed
  </Accordion>

  <Accordion title="List Data Drains" icon="list">
    **Endpoint**: `GET /data-drains`

    **Query Parameters:**

    * `organization_id` (required): Your organization ID

    **Response:** Returns an array of drain objects with their current status, configuration, and sync timestamps.
  </Accordion>

  <Accordion title="Create Data Drain" icon="plus">
    **Endpoint**: `POST /data-drains`

    **Request Body:**

    ```json theme={"dark"}
    {
      "name": "My Credit Logs Drain",
      "drain_type": "custom_endpoint",
      "data_type": "credit_logs",
      "destination_config": {
        "format": "json"
      },
      "export_fields": ["user_email", "timestamp", "category", "type", "name", "amount", "balance"],
      "secret_id": "your_credential_secret_id",
      "organization_id": "your_org_id"
    }
    ```

    **Required Parameters:**

    * `organization_id`: Your organization ID
    * `name`: A display name for the drain
    * `drain_type`: `"custom_endpoint"`, `"s3"`, or `"datadog"`
    * `data_type`: `"workflows"`, `"agents"`, `"agent_interactions"`, `"credit_logs"`, `"audit_logs"`, or `"gumstack"`
    * `destination_config`: Configuration object specific to the destination type
    * `export_fields`: Array of field IDs to include in the synced data

    **Optional Parameters:**

    * `secret_id`: The credential secret ID for authenticating with the destination
    * `project_ids`: Filter to specific team/workspace IDs
    * `include_personal_workspaces`: Include personal workspaces in scope
    * `entity_ids`: Filter to specific entity IDs
    * `category_filter`: Filter credit logs by category
    * `event_type_filter`: Filter audit logs by event type
    * `start_ts`: ISO 8601 timestamp to start syncing from (defaults to current time)

    <Note>
      When creating a drain, Gumloop performs a **preflight check** to verify the destination is reachable. If the check fails, the drain will not be created and an error will be returned.
    </Note>
  </Accordion>

  <Accordion title="Delete Data Drain" icon="trash">
    **Endpoint**: `DELETE /data-drains/{drain_id}`

    **Query Parameters:**

    * `organization_id` (required): Your organization ID
  </Accordion>
</AccordionGroup>

<Card title="Complete API Documentation" icon="book" href="https://docs.gumloop.com/api-reference/organization/export-data">
  View the full API reference for detailed endpoint specifications and examples
</Card>

## Related Resources

<CardGroup cols={2}>
  <Card title="Audit Logging" icon="shield" href="https://docs.gumloop.com/enterprise-features/audit_logging">
    Learn about security and compliance audit logging
  </Card>

  <Card title="Custom Roles" icon="users" href="https://docs.gumloop.com/enterprise-features/user_groups">
    Learn about managing user permissions and roles
  </Card>

  <Card title="Organizations and Teams" icon="building" href="https://docs.gumloop.com/core-concepts/teams">
    Understand how to structure your Gumloop organization
  </Card>

  <Card title="Insights" icon="chart-pie" href="https://docs.gumloop.com/enterprise-features/organization_insights">
    View organization-wide usage analytics and dashboards
  </Card>
</CardGroup>
