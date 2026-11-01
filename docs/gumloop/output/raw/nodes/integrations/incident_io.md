> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Incident.io Incidents Reader

> Read and monitor incidents from incident.io with automated polling triggers

<div align="center">
  <img src="https://mintcdn.com/agenthub/ioE-MoOBVPgnhQFx/images/incident_io_incidents_reader.png?fit=max&auto=format&n=ioE-MoOBVPgnhQFx&q=85&s=096a50027129d25f4ce1a8fa95ee16e4" alt="Incident.io Incidents Reader node interface" width="600" data-path="images/incident_io_incidents_reader.png" />
</div>

The Incident.io Incidents Reader connects to your incident.io workspace to retrieve and monitor incidents. Use it to fetch incident data manually or set up automated workflows that trigger when new incidents are detected.

## How It Works

This node operates in two modes:

<CardGroup cols={2}>
  <Card title="Manual Mode" icon="hand">
    Fetch incidents on-demand with custom filters. Returns all matching incidents as lists for reporting and analysis.
  </Card>

  <Card title="Trigger Mode" icon="bell">
    Automatically polls incident.io every 5 minutes for new incidents. Triggers your workflow when incidents are detected.
  </Card>
</CardGroup>

## Setup

<Steps>
  <Step title="Get your API key">
    Navigate to your incident.io settings and generate an API key with permissions to read incidents.
  </Step>

  <Step title="Add the secret to Gumloop">
    Search for `Incident.io` on the [Connectors page](https://www.gumloop.com/personal/connectors) and save the API key.
  </Step>

  <Step title="Configure filters (optional)">
    Set Status, Severity, or Mode filters to narrow down which incidents you want to retrieve.
  </Step>
</Steps>

<Warning>
  Ensure your API key has sufficient permissions to read incidents via the incident.io v2 API.
</Warning>

## Configuration

### Filters

All filters support multiple selections and use case-insensitive matching:

<AccordionGroup>
  <Accordion title="Status" icon="traffic-light">
    Filter incidents by their current status:

    * **Triage** - Incident is being assessed
    * **Investigating** - Team is diagnosing the issue
    * **Fixing** - Solution is being implemented
    * **Monitoring** - Fix deployed, watching for stability
    * **Closed** - Incident resolved
  </Accordion>

  <Accordion title="Severity" icon="triangle-exclamation">
    Filter by incident severity level:

    * **Minor** - Low impact incidents
    * **Major** - Significant business impact
    * **Critical** - Severe, immediate attention required
  </Accordion>

  <Accordion title="Mode" icon="sliders">
    Filter by incident type:

    * **Standard** - Regular production incidents
    * **Retrospective** - Post-incident analysis
    * **Tutorial** - Training incidents
    * **Test** - Testing workflows
  </Accordion>
</AccordionGroup>

## Outputs

The node provides comprehensive incident data. Output format depends on the mode:

| Output Field     | Description                                  | Source Field           |
| ---------------- | -------------------------------------------- | ---------------------- |
| Incident ID      | Unique identifier                            | `id`                   |
| Name             | Incident title                               | `name`                 |
| Status           | Current status (Triage, Investigating, etc.) | `incident_status.name` |
| Severity         | Severity level (Minor, Major, Critical)      | `severity.name`        |
| Mode             | Incident type                                | `mode`                 |
| Created At       | Creation timestamp (ISO format)              | `created_at`           |
| Updated At       | Last update timestamp (ISO format)           | `updated_at`           |
| Summary          | Incident description                         | `summary`              |
| Permalink        | Direct link to incident                      | `permalink`            |
| Slack Channel ID | Associated Slack channel                     | `slack_channel_id`     |

<Info>
  **Manual mode** returns arrays (lists) for each field. **Trigger mode** returns single values for each detected incident.
</Info>

## Using as a Trigger

When enabled as a trigger, the node automatically monitors for new incidents:

<Tabs>
  <Tab title="How Polling Works">
    * Checks incident.io every 5 minutes
    * Applies Severity and Mode filters (Status is not used in polling)
    * Tracks processed incidents to avoid duplicates
    * Fires workflow once per new incident detected
  </Tab>

  <Tab title="Deduplication">
    The trigger maintains a list of processed incident IDs in its state. Only newly detected incidents trigger the workflow. Clearing the trigger state or changing filters may cause previously seen incidents to be treated as new.
  </Tab>
</Tabs>

### Common Trigger Use Cases

<CardGroup cols={2}>
  <Card title="Slack Notifications" icon="slack">
    Automatically post to Slack when critical incidents are created
  </Card>

  <Card title="Ticket Creation" icon="ticket">
    Create Jira or Linear tickets for new high-priority incidents
  </Card>

  <Card title="Team Alerts" icon="users">
    Send email or SMS alerts when specific types of incidents occur
  </Card>

  <Card title="Incident Dashboard" icon="chart-line">
    Update real-time dashboards when incident status changes
  </Card>
</CardGroup>

**Example trigger configuration:**

```text theme={"dark"}
Severity: Major, Critical
Mode: Standard
```

This will trigger your workflow for every new Major or Critical incident in Standard mode.

## Example Workflows

<AccordionGroup>
  <Accordion title="Daily Incident Report" icon="file">
    Run the node manually (no trigger) to fetch all incidents, filter by status (Investigating, Fixing), and pass the arrays to a reporting node. Perfect for daily standups or retrospectives.
  </Accordion>

  <Accordion title="Critical Incident Response" icon="siren">
    Enable as a trigger with Severity set to Critical. Connect to nodes that post to Slack, create a PagerDuty alert, and log to your incident tracking system.
  </Accordion>

  <Accordion title="Incident Analysis Pipeline" icon="magnifying-glass-chart">
    Fetch closed incidents weekly, extract patterns from summaries using AI, and generate insights about recurring issues.
  </Accordion>
</AccordionGroup>

## Error Handling

The node provides clear error messages for common issues:

| Error                          | Cause                               | Solution                                                 |
| ------------------------------ | ----------------------------------- | -------------------------------------------------------- |
| No incidents found             | incident.io returned zero incidents | Check your API permissions and ensure incidents exist    |
| No incidents match filters     | All incidents filtered out          | Adjust your Status, Severity, or Mode filters            |
| Failed to connect (HTTP error) | API request failed                  | Verify your API key and check incident.io service status |
| Empty response                 | API returned no data                | Contact incident.io support if persistent                |
| Invalid JSON                   | Response parsing failed             | Check API compatibility or contact support               |

<Note>
  In trigger mode, if no event is found in the webhook input, ensure the trigger is properly configured and the polling mechanism is active.
</Note>

## Tips

<Tip>
  Combine multiple filters to narrow results. For example, Status = "Investigating" + Severity = "Critical" gives you high-priority active incidents.
</Tip>

<Tip>
  When using as a trigger, start with broader filters and refine based on workflow volume. Too many triggers can overwhelm downstream systems.
</Tip>

<Tip>
  The Permalink output provides direct links to incidents in incident.io - perfect for including in notifications or tickets.
</Tip>
