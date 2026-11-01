> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Audit Logging

Audit Logging provides comprehensive tracking and monitoring of user actions across your Gumloop organization. This feature enables administrators to maintain security, compliance, and operational oversight by recording detailed logs of all significant activities within the platform.

## Overview

Audit logging automatically captures and stores detailed records of user activities, system events, and administrative actions within your organization.

<CardGroup cols={2}>
  <Card title="Security Monitoring" icon="shield-halved">
    Track unauthorized access attempts and suspicious activities
  </Card>

  <Card title="Compliance Requirements" icon="file-contract">
    Meet regulatory standards for data access and modification tracking
  </Card>

  <Card title="Operational Oversight" icon="chart-line">
    Monitor workflow executions and system usage patterns
  </Card>

  <Card title="Troubleshooting" icon="wrench">
    Investigate issues by reviewing historical activity patterns
  </Card>
</CardGroup>

<Info>
  Access audit logs at: [gumloop.com/settings/organization/audit-logging](https://gumloop.com/settings/organization/audit-logging)
</Info>

<div align="center">
  <img src="https://mintcdn.com/agenthub/dn7emlKONFx9smnZ/images/audit-logging-overview.png?fit=max&auto=format&n=dn7emlKONFx9smnZ&q=85&s=f928ab5c4a7564a97db77e3d1bbeba60" alt="Audit Logging Overview" width="900" data-path="images/audit-logging-overview.png" />
</div>

### Filtering by Event Type

You can filter audit logs by event type to quickly find specific activities. Use the **Event Type** dropdown to select from available event types, or search for a specific type.

<Frame>
  <img src="https://mintcdn.com/agenthub/5j7_6MWAeY7rv44A/images/audit_log_event_type_filter.png?fit=max&auto=format&n=5j7_6MWAeY7rv44A&q=85&s=428b8a676bba0a7157758344c39f2ec3" alt="Audit log Event Type filter dropdown showing event types like user_sign_in, credential_retrieval, credential_insertion, and more" width="1270" height="800" data-path="images/audit_log_event_type_filter.png" />
</Frame>

## Tracked Events

The audit logging system captures a comprehensive range of activities across the platform including:

<AccordionGroup>
  <Accordion title="Authentication Events" icon="key">
    * **User Sign-ins**: Records when users authenticate to the platform
    * **Session Management**: Tracks session creation and termination
  </Accordion>

  <Accordion title="Credential Management" icon="lock">
    * **Credential Creation** (`credential_insertion`): New API keys, OAuth connections, and service integrations
    * **Credential Modification** (`credential_modification`): Updates to existing authentication credentials
    * **Credential Deletion** (`credential_deletion`): Removal of credentials from the system
    * **Credential Retrieval** (`credential_retrieval`): Access to stored credentials for agent and workflow execution

    <Info>Credential events record which credential was touched and by whom. Secret values, OAuth codes, and tokens are never written to audit logs.</Info>
  </Accordion>

  <Accordion title="Team Operations" icon="folder">
    * **Team Creation**: New team setup and configuration
    * **Member Management**: Adding or removing users from teams
    * **Team Deletion**: Permanent removal of teams
    * **Team Renaming**: Changes to team names and metadata
  </Accordion>

  <Accordion title="Organization Management" icon="building">
    * **Member Addition/Removal**: Changes to organization membership
    * **Domain Configuration**: Updates to organization domain settings
    * **Metadata Updates**: Changes to organization settings and configuration
  </Accordion>

  <Accordion title="Slack Workspace Association" icon="slack">
    Records which Slack workspaces are linked to your organization, and who linked them.

    * **Workspace Registration** (`organization_slack_workspace_registration`): A Slack workspace was associated with the organization
    * **Workspace Removal** (`organization_slack_workspace_removal`): The association was removed

    Each entry includes `organization_id`, `slack_workspace_id`, `slack_workspace_name`, and the acting user.

    <Info>Only the relationship and the acting user are recorded. OAuth codes, tokens, and state parameters are never logged.</Info>
  </Accordion>

  <Accordion title="Custom Role Management" icon="users">
    * **Role Creation**: New [custom roles](/enterprise-features/user_groups) and access controls
    * **Member Changes**: Adding or removing users from a custom role
    * **Role Deletion**: Removal of custom roles
  </Accordion>

  <Accordion title="Workflow Operations" icon="diagram-project">
    * **Workflow Execution**: Workflow runs and automation triggers
    * **Workflow Termination**: Manual or automatic stopping of workflows
    * **Workflow Completion**: Successful workflow completions
    * **Run Retrieval**: Access to workflow execution results and logs
  </Accordion>

  <Accordion title="File Operations" icon="file">
    * **File Uploads** (`file_upload`): Documents and data uploaded to the platform
    * **File Downloads** (`file_download`): Access to stored files and documents
    * **File Deletion** (`file_deletion`): Removal of files from the system
    * **File Modification** (`file_modification`): Changes to a stored file, including [artifact domain hosting](/core-concepts/agent_artifacts#hosting-an-artifact-on-its-own-domain) configuration
  </Accordion>

  <Accordion title="Agent Lifecycle Events" icon="robot">
    Every log entry includes the `agent_id`, `agent_name`, the operation performed, and the `workspace_id` when the agent belongs to a team workspace.

    * **Agent Creation** (`agent_creation`): A new agent was created. Agents created by cloning carry `operation: clone` along with the `source_agent_id`.
    * **Agent Modification** (`agent_modification`): Changes to an existing agent, including `version_deploy` when a saved version is deployed
    * **Agent Deletion** (`agent_deletion`): An agent was deleted
    * **Agent Workspace Move** (`agent_workspace_move`): An agent moved between workspaces
  </Accordion>

  <Accordion title="Agent Trigger Events" icon="bolt">
    Trigger lifecycle changes are recorded as `agent_modification` events with one of these operations:

    | Operation              | Meaning                                        |
    | ---------------------- | ---------------------------------------------- |
    | `trigger_create`       | A new trigger added to an agent                |
    | `trigger_update`       | Changes to an existing trigger's configuration |
    | `trigger_activation`   | A previously disabled trigger re-enabled       |
    | `trigger_deactivation` | An active trigger disabled                     |
    | `trigger_delete`       | A trigger removed from an agent                |

    Each entry records `trigger_id`, `trigger_type`, `trigger_name`, whether the trigger is active, and a **`source`** field distinguishing who made the change:

    * `ui` — a person changed the trigger in Gumloop
    * `ai_agent` — the agent changed its own trigger using its schedule or integration-trigger tools
  </Accordion>

  <Accordion title="Agent Channel Events" icon="tower-broadcast">
    Enabling, updating, or disabling an agent's [external channels](/core-concepts/agents) is recorded as an `agent_modification` event.

    | Operation                                                                          | Meaning                                                                                    |
    | ---------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
    | `email_channel_enabled` / `email_channel_updated` / `email_channel_disabled`       | Changes to the agent's [email inbox](/core-concepts/agents_email)                          |
    | `hosting_channel_enabled` / `hosting_channel_updated` / `hosting_channel_disabled` | Changes to the agent's [hosted page](/core-concepts/hosted_pages), including alias changes |
    | `slack_channel_added` / `slack_channel_created` / `slack_channel_removed`          | Slack channels connected to or removed from the agent                                      |
    | `slack_bot_credentials_change`                                                     | The Slack bot credentials backing the agent changed                                        |
    | `slack_app_uninstalled`                                                            | The Slack app was uninstalled, disconnecting the agent                                     |
  </Accordion>

  <Accordion title="Agent Skill and Knowledge Events" icon="book">
    * **Skill Attach** (`skill_attach`) / **Skill Detach** (`skill_detach`): A skill linked to or removed from an agent
    * **Knowledge Source Attach / Update / Detach** (`knowledge_source_attach`, `knowledge_source_update`, `knowledge_source_detach`): Changes to the [Brain](/core-concepts/brain) sources an agent can search

    Entries include the `agent_id`, the `skill_id` or knowledge source identifier, and the workspace context.
  </Accordion>

  <Accordion title="Artifact Hosting" icon="globe">
    Serving an [artifact on its own domain](/core-concepts/agent_artifacts#hosting-an-artifact-on-its-own-domain) is recorded as a `file_modification` event.

    | Operation                | Meaning                               |
    | ------------------------ | ------------------------------------- |
    | `custom_domain_enabled`  | Hosting was turned on for an artifact |
    | `custom_domain_updated`  | The hosting alias was renamed         |
    | `custom_domain_disabled` | Hosting was turned off                |

    Entries include the artifact identifier, the filename, the hosting alias (and the previous alias on a rename), and the workspace context.
  </Accordion>
</AccordionGroup>

## Audit Log Data Structure

Each audit log entry contains comprehensive metadata and contextual information to provide complete visibility into platform activities.

### Example: Workbook Creation Log

<Accordion title="View Full Log Example" defaultOpen={false}>
  ```json theme={"dark"}
  {
    "flow_jsons": [
      [
        {
          "batch": false,
          "category": "Using AI",
          "dynamic_inputs": [],
          "dynamic_outputs": [],
          "id": "eEovMC652wM7Mvzo31G4xM",
          "input_errors": {},
          "inputs": {},
          "is_collapsed": false,
          "operator": "Ask AI",
          "parameter_errors": {
            "Azure Cognitive Services Account": "",
            "Azure Deployment": "",
            "Azure Resource Group": "",
            "Azure Subscription": "",
            "Cache Response": "",
            "Connect MCP Server?": "",
            "MCP Servers": "",
            "Maximum Tokens": "",
            "Reasoning Effort": "",
            "Temperature": "",
            "Thinking Tokens": "",
            "Use Function?": "",
            "azure_cognitive_services_map": "",
            "azure_deployment_map": "",
            "azure_resource_groups_map": "",
            "azure_subscription_map": "",
            "model_preference": "",
            "prompt": "",
            "servers_setup_map": ""
          },
          "parameter_input_errors": {},
          "parameter_inputs": {},
          "parameters": {
            "Azure Cognitive Services Account": null,
            "Azure Deployment": null,
            "Azure Resource Group": null,
            "Azure Subscription": null,
            "Cache Response": null,
            "Connect MCP Server?": null,
            "MCP Servers": null,
            "Maximum Tokens": null,
            "Reasoning Effort": null,
            "Temperature": null,
            "Thinking Tokens": null,
            "Use Function?": null,
            "azure_cognitive_services_map": null,
            "azure_deployment_map": null,
            "azure_resource_groups_map": null,
            "azure_subscription_map": null,
            "model_preference": null,
            "prompt": null,
            "servers_setup_map": null
          },
          "position": {
            "x": 945.508855591821,
            "y": 137.38119073725161
          },
          "version": "v0_11"
        }
      ]
    ],
    "pl_config_info": [
      {
        "pl_config_hash": "{ID}"
      }
    ],
    "saved_items_info": [
      {
        "saved_item_id": "{ID}",
        "saved_item_ref": "{ID}"
      }
    ],
    "user_email": "admin@gumloop.com",
    "workbook_id": "{ID}",
    "workbook_name": "New Workbook",
    "workspace_id": null
  }
  ```
</Accordion>

### Log Entry Components

<CardGroup cols={2}>
  <Card title="Core Event Data" icon="database">
    * Event ID (unique identifier)
    * Timestamp (ISO format)
    * Event Type (action categorization)
    * User ID (who performed the action)
  </Card>

  <Card title="Request Context" icon="network-wired">
    * Source IP Address
    * Session Information
    * Authentication context
  </Card>

  <Card title="Event Details" icon="circle-info">
    * Action-specific JSON data
    * Resource identifiers
    * Configuration parameters
  </Card>

  <Card title="Resource Information" icon="sitemap">
    * Team IDs
    * Workflow IDs
    * Affected entities
  </Card>
</CardGroup>

## API Access

Access audit logs programmatically using the REST API for integration with external monitoring, SIEM systems, or custom reporting tools.

### Quick Reference

<Steps>
  <Step title="Endpoint">
    ```text theme={"dark"}
    GET /api/v1/get_audit_logs
    ```
  </Step>

  <Step title="Required Parameters">
    * `organization_id` (string): Organization ID
    * `user_id` (string): Your user ID (admin required)
    * `start_time` (datetime): Start timestamp (ISO format)
    * `end_time` (datetime): End timestamp (ISO format)
  </Step>

  <Step title="Optional Parameters">
    * `event_type` (string): Filter logs by a specific event type (e.g., `"user_sign_in"`, `"credential_retrieval"`, `"flow_execution"`)
    * `page` (integer): Page number (default: 1)
    * `page_size` (integer): Records per page (default: 50)
  </Step>

  <Step title="Authentication">
    Include your API key in the Authorization header
  </Step>
</Steps>

### Example Request

```bash theme={"dark"}
curl --request GET \
  --url 'https://api.gumloop.com/api/v1/get_audit_logs?page=1&page_size=50&start_time=2025-01-01T00%3A00%3A00&end_time=2025-01-02T00%3A00%3A00&user_id=user_abc123&organization_id=org_xyz789' \
  --header 'Authorization: Bearer your_api_key_here'
```

<Tip>
  For complete API documentation and advanced usage, see: [Gumloop API Reference](https://docs.gumloop.com/api-reference/organization/get-audit-logs)
</Tip>

## Data Privacy and Security

<AccordionGroup>
  <Accordion title="Data Protection" icon="shield-halved">
    * **Encryption**: All audit logs are encrypted at rest and in transit using industry-standard protocols
    * **Access Control**: Logs are accessible only to organization administrators with proper authentication
    * **Data Isolation**: Organization audit logs are completely isolated from other organizations through strict multi-tenancy controls
  </Accordion>

  <Accordion title="Retention and Compliance" icon="calendar-check">
    * **Data Retention**: Audit logs are retained according to Enterprise agreement terms
    * **Compliance Standards**: Meets SOC2 Type II and GDPR requirements for audit trail management
    * **Data Export**: Full export capabilities for compliance audits and backup purposes
  </Accordion>
</AccordionGroup>

<Warning>
  Only users with the **Admin** [organization role](/core-concepts/organization_user_roles#admin) can access audit logs. Security, Manager, and other feature roles do not grant audit log access. Ensure appropriate role assignments to maintain security controls.
</Warning>

## Related Resources

<CardGroup cols={2}>
  <Card title="Custom Roles" icon="user-shield" href="/enterprise-features/user_groups">
    Configure granular permissions and access controls
  </Card>

  <Card title="Usage Data Export" icon="file-export" href="/organization_data_export">
    Export comprehensive platform usage data
  </Card>

  <Card title="Organizations and Teams" icon="building" href="/core-concepts/teams">
    Understand organizational structure
  </Card>

  <Card title="Security & Compliance" icon="shield-check" href="https://trust.gumloop.com/">
    View our security certifications
  </Card>
</CardGroup>
