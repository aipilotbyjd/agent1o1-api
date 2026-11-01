> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Export data

> This endpoint allows enterprise organization administrators to create and initiate a comprehensive data export for their organization or specific workspaces.

The export supports four data types:
- **Workflow data** (`data_type: "workflows"`): Includes workflow runs, workbook details, user information, and other organizational data.
- **Agent data** (`data_type: "agents"`): Includes agent configurations, metadata, tools, and creator information.
- **Agent interaction data** (`data_type: "agent_interactions"`): Includes agent interaction data with timestamps, credit costs, trigger types, and message counts.
- **Credit log data** (`data_type: "credit_logs"`): Includes credit transaction history with charges, balances, categories, and user attribution.

The available `export_fields` depend on the selected `data_type`. See the field descriptions below for details.

**Scoping requirement:** For non-credit-log exports, at least one scoping parameter must be provided: `workspace_ids`, `include_all_workspaces`, `include_personal_workspaces`, or `entity_ids`. Requests that omit all scoping parameters will receive a `400` error.

**Note:** Credit log exports work differently from workflow and agent exports. When `data_type` is `"credit_logs"`, the following parameters are **not applicable** and will be ignored: `export_level`, `workspace_ids`, `include_all_workspaces`, `include_personal_workspaces`, and `entity_ids`. Credit log exports are always scoped to the entire organization. Use `category_filter` to filter by credit log category.




## OpenAPI

````yaml post /export_data
openapi: 3.0.0
info:
  title: Public API
  version: 1.0.0
servers:
  - url: https://api.gumloop.com/api/v1
security: []
paths:
  /export_data:
    post:
      tags:
        - Organization
      summary: Export data
      description: >
        This endpoint allows enterprise organization administrators to create
        and initiate a comprehensive data export for their organization or
        specific workspaces.


        The export supports four data types:

        - **Workflow data** (`data_type: "workflows"`): Includes workflow runs,
        workbook details, user information, and other organizational data.

        - **Agent data** (`data_type: "agents"`): Includes agent configurations,
        metadata, tools, and creator information.

        - **Agent interaction data** (`data_type: "agent_interactions"`):
        Includes agent interaction data with timestamps, credit costs, trigger
        types, and message counts.

        - **Credit log data** (`data_type: "credit_logs"`): Includes credit
        transaction history with charges, balances, categories, and user
        attribution.


        The available `export_fields` depend on the selected `data_type`. See
        the field descriptions below for details.


        **Scoping requirement:** For non-credit-log exports, at least one
        scoping parameter must be provided: `workspace_ids`,
        `include_all_workspaces`, `include_personal_workspaces`, or
        `entity_ids`. Requests that omit all scoping parameters will receive a
        `400` error.


        **Note:** Credit log exports work differently from workflow and agent
        exports. When `data_type` is `"credit_logs"`, the following parameters
        are **not applicable** and will be ignored: `export_level`,
        `workspace_ids`, `include_all_workspaces`,
        `include_personal_workspaces`, and `entity_ids`. Credit log exports are
        always scoped to the entire organization. Use `category_filter` to
        filter by credit log category.
      operationId: exportData
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                user_id:
                  type: string
                  description: The ID of the user requesting the export.
                data_type:
                  type: string
                  enum:
                    - workflows
                    - agents
                    - agent_interactions
                    - credit_logs
                  default: workflows
                  description: >
                    The type of data to export. Use `"workflows"` to export
                    workflow run data, `"agents"` to export agent configuration
                    data, `"agent_interactions"` to export agent interaction
                    data, or `"credit_logs"` to export credit transaction
                    history. Defaults to `"workflows"`.
                category_filter:
                  type: string
                  description: >
                    **Only applicable when `data_type` is `"credit_logs"`.**
                    Optional filter to export only credit logs matching a
                    specific category (e.g., `"PIPELINE_RUN"`, `"AGENT_RUN"`,
                    `"CUSTOM_NODE_RD"`, `"EXTERNAL_GUMCP_CALL"`). When omitted,
                    all categories are included.
                export_level:
                  type: string
                  enum:
                    - workspace
                    - organization
                  default: organization
                  description: >
                    The scope of the export. Use `"organization"` to export
                    across the entire organization, or `"workspace"` to export
                    from a single workspace (requires exactly one ID in
                    `workspace_ids`). Defaults to `"organization"`. **Not
                    applicable when `data_type` is `"credit_logs"`.**
                export_fields:
                  type: array
                  items:
                    type: string
                  description: >
                    Array of fields to include in the export. The available
                    fields depend on the `data_type`.


                    **Workflow fields** (when `data_type` is `"workflows"`):

                    - `workbook_id` - The workbook identifier

                    - `workbook_name` - The workbook name

                    - `workbook_created_ts` - Workbook creation timestamp

                    - `user_id` - The user identifier

                    - `user_email` - The user's email address

                    - `workspace_id` - The workspace identifier

                    - `workspace_name` - The workspace name

                    - `run_id` - The flow run identifier

                    - `credit_cost` - Credits consumed by the run

                    - `pl_run_created_ts` - Flow run creation timestamp

                    - `pl_run_finished_ts` - Flow run completion timestamp

                    - `pipeline` - The full pipeline configuration (JSON)


                    **Agent fields** (when `data_type` is `"agents"`):

                    - `agent_id` - The agent identifier

                    - `agent_name` - The agent name

                    - `agent_description` - The agent description

                    - `agent_model` - The model used by the agent

                    - `agent_system_prompt` - The agent's system prompt

                    - `agent_created_ts` - Agent creation timestamp

                    - `agent_tools` - Tools configured for the agent (JSON)

                    - `agent_metadata` - Additional agent metadata (JSON)

                    - `creator_email` - Email of the user who created the agent

                    - `workspace_id` - The workspace identifier

                    - `workspace_name` - The workspace name


                    **Agent interaction fields** (when `data_type` is
                    `"agent_interactions"`):

                    - `interaction_id` - Unique identifier for the chat session

                    - `agent_id` - The agent identifier

                    - `agent_name` - The agent name

                    - `interaction_type` - Type of interaction (e.g., chat,
                    slack, api, triggered)

                    - `interaction_name` - Display name of the chat session

                    - `trigger_type` - For triggered interactions, the specific
                    trigger type (e.g., time_based,
                    polling_new_record_salesforce). Null for non-triggered
                    interactions.

                    - `interaction_created_ts` - Chat session creation timestamp

                    - `user_email` - Email of the user who initiated the chat

                    - `credit_cost` - Total credits consumed (LLM + tool + flow)

                    - `llm_credit_cost` - Credits consumed by LLM calls only

                    - `tool_credit_cost` - Credits consumed by tool calls

                    - `flow_credit_cost` - Credits consumed by pipeline runs

                    - `message_count` - Number of messages in the conversation

                    - `workspace_id` - The workspace identifier

                    - `workspace_name` - The workspace name


                    **Credit log fields** (when `data_type` is `"credit_logs"`):

                    - `user_email` - Email of the user associated with the
                    credit log entry

                    - `permission_group_id` - Custom role ID(s) the user belongs
                    to (semicolon-separated if multiple) (disabled by default)

                    - `permission_group_name` - Custom role name(s) the user
                    belongs to (semicolon-separated if multiple) (disabled by
                    default)

                    - `timestamp` - When the credit transaction occurred

                    - `category` - The category of the credit log (e.g.,
                    PIPELINE_RUN, AGENT_RUN)

                    - `type` - The specific type of credit charge

                    - `name` - Display name of the credit log entry

                    - `amount` - Number of credits charged or adjusted

                    - `balance` - Credit balance after the transaction

                    - `log_id` - Unique identifier for the credit log entry

                    - `correlation_id` - Join key to the related run or
                    interaction (disabled by default)

                    - `balance_scope` - Whether the balance is organization- or
                    user-scoped (disabled by default)

                    - `project_id` - The project identifier (disabled by
                    default)


                    Not all combinations of selected fields are guaranteed to
                    produce data for every row.
                start_date:
                  type: string
                  format: date-time
                  description: >-
                    Start date for the export in ISO 8601 format (e.g.,
                    `2025-01-01T00:00:00Z`).
                end_date:
                  type: string
                  format: date-time
                  description: >-
                    End date for the export in ISO 8601 format (e.g.,
                    `2025-12-31T23:59:59Z`).
                include_all_workspaces:
                  type: boolean
                  default: false
                  description: >-
                    Whether to include all workspaces in the organization. When
                    `true`, also sets `include_personal_workspaces` to `true`.
                    **Not applicable when `data_type` is `"credit_logs"`.**
                include_personal_workspaces:
                  type: boolean
                  default: false
                  description: >-
                    Whether to include personal workspaces in the export.
                    Ignored if `include_all_workspaces` is `true`. **Not
                    applicable when `data_type` is `"credit_logs"`.**
                workspace_ids:
                  type: array
                  items:
                    type: string
                  description: >-
                    An optional array of workspace IDs to include in the export.
                    When `export_level` is `"workspace"`, exactly one workspace
                    ID is required. Ignored if `include_all_workspaces` is
                    `true`. **Not applicable when `data_type` is
                    `"credit_logs"`.**
                entity_ids:
                  type: array
                  items:
                    type: string
                  description: >
                    An optional array of specific entity IDs to filter the
                    export. For workflow exports (`data_type: "workflows"`),
                    these are workbook IDs. For agent exports (`data_type:
                    "agents"`) and agent interaction exports (`data_type:
                    "agent_interactions"`), these are agent IDs. When provided,
                    only data for the specified entities will be included. **Not
                    applicable when `data_type` is `"credit_logs"`.**
              required:
                - user_id
                - export_fields
                - start_date
                - end_date
      responses:
        '200':
          description: Data export successfully initiated
          content:
            application/json:
              schema:
                type: object
                properties:
                  success:
                    type: boolean
                    description: Indicates whether the export was successfully initiated.
                  data_export_id:
                    type: string
                    description: >-
                      Unique identifier for the data export job. Use this with
                      the [Get export
                      status](/api-reference/organization/export-status)
                      endpoint to check progress and download the completed
                      export.
                  state:
                    type: string
                    enum:
                      - REQUESTED
                    description: >-
                      The initial state of the export job. Will be `REQUESTED`
                      upon creation.
                  created_ts:
                    type: string
                    format: date-time
                    description: Timestamp when the export was created (ISO 8601).
        '400':
          description: >-
            Bad request — missing required parameters, invalid date format,
            invalid `export_level` value, or missing scoping parameters (for
            non-credit-log exports, at least one of `workspace_ids`,
            `include_all_workspaces`, `include_personal_workspaces`, or
            `entity_ids` is required).
        '401':
          description: Unauthorized — missing or invalid API key.
        '403':
          description: >-
            Forbidden — insufficient permissions or the organization is not on
            the enterprise tier.
        '404':
          description: Organization or workspace not found.
        '500':
          description: Internal server error.
      security:
        - bearerAuth: []
components:
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      description: >-
        A personal API key or an [OAuth 2.0](/api-reference/oauth) access token.
        Personal API keys also require the `x-auth-key` header with your user
        ID.

````