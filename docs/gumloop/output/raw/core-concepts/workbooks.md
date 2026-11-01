> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Workflows

A workflow is your canvas for building automations in Gumloop. It's where you drag and drop nodes, connect them together, and create powerful automations. Each workbook can contain multiple workflows organized as tabs at the bottom of the screen.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1123291192?h=9c80aff3cd" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="Getting started with Gumloop" />
</div>

## What is a Workbook?

A workbook is a canvas where you chain multiple nodes together to build your automation. You drag nodes from the library onto the canvas, connect them in sequence, and create workflows that process data, use AI, and integrate with external services.

<CardGroup cols={1}>
  <Card title="Visual Canvas" icon="palette">
    Drag and drop nodes, then connect them with edges to define the workflow of data through your automation
  </Card>

  <Card title="Multiple Workflows in Tabs" icon="layer-group">
    Each tab at the bottom is a separate workflow. Keep related workflows together in one workbook (like how Excel has multiple sheets in one file)
  </Card>

  <Card title="Version Control" icon="clock-rotate-left">
    Track run history and save [checkpoints](https://docs.gumloop.com/core-concepts/checkpoint_history) to preserve working versions
  </Card>
</CardGroup>

Every automation you create in Gumloop lives in a workbook. When you open a workflow, you're working on this canvas where nodes connect together to form your automation logic.

## Workbook Navigation & Controls

Navigate your workbook canvas efficiently with these controls:

### Basic Navigation

<Tabs>
  <Tab title="Zooming">
    * **Scroll wheel**: Zoom in and out
    * **Trackpad**: Use pinch gestures to zoom or two finger swipe
    * **Keyboard**: `Cmd/Ctrl + 0` to fit entire workflow in view
  </Tab>

  <Tab title="Panning">
    * **Click and drag**: Click on empty canvas space and drag to move your view
    * Works like Google Maps or Figma
  </Tab>

  <Tab title="Selecting">
    * **Single node**: Click to select
    * **Multiple nodes**: Hold `Shift` and drag to select a group
    * **Deselect**: Click on empty canvas space
  </Tab>
</Tabs>

### Canvas Control Buttons

Located in the bottom right corner of your canvas, you'll find these helpful controls:

<div align="center">
  <img src="https://mintcdn.com/agenthub/hrwvapjs9dt_JqnG/images/canvas_controls.png?fit=max&auto=format&n=hrwvapjs9dt_JqnG&q=85&s=174613a50f40d014f2f04242e2042531" alt="Canvas control buttons" width="100" data-path="images/canvas_controls.png" />
</div>

<AccordionGroup>
  <Accordion title="Fit View" icon="maximize">
    Automatically adjusts the zoom and position to show your entire workflow on screen. Perfect for getting a bird's-eye view of complex workflows.
  </Accordion>

  <Accordion title="Auto Format" icon="wand-magic-sparkles">
    Automatically arranges your nodes in a clean, organized layout. Use this when your canvas gets messy or after making significant changes.
  </Accordion>

  <Accordion title="Expand All Nodes" icon="expand">
    Expands all nodes simultaneously, making it easy to review your entire workflow's logic.
  </Accordion>

  <Accordion title="Collapse All Nodes" icon="compress">
    Collapses all node nodes to clean up your canvas and focus on the workflow structure.
  </Accordion>

  <Accordion title="Snap to Grid" icon="grid">
    Toggles grid snapping to help align nodes precisely. Great for creating visually organized workflows.
  </Accordion>
</AccordionGroup>

## Understanding Tabs and Subflows

The tabs you see at the bottom of your workbook are actually individual **subflows**. This is important to understand:

<div align="center">
  <img src="https://mintcdn.com/agenthub/tX41SZaayXs0n5SN/images/workbook_tabs.png?fit=max&auto=format&n=tX41SZaayXs0n5SN&q=85&s=610e1aac16683a2af086711af1fa27c6" alt="Workbook tabs showing subflows" width="900" data-path="images/workbook_tabs.png" />
</div>

* Each tab represents a complete workflow that can run independently
* Tabs can also be used as reusable components (subflows) within other workflows
* The main workflow is just one tab, and you can create additional tabs for modular organization

<Info>
  To learn more about how subflows work and how to use them effectively, see the [Subflows documentation](https://docs.gumloop.com/core-concepts/subflows).
</Info>

### Managing Tabs

<Steps>
  <Step title="Create a new tab">
    Click the plus `+` icon in the bottom bar to add a new workflow to your workbook
  </Step>

  <Step title="Right-click for options">
    Right-click on any tab to rename, duplicate, or delete workflows within your workbook
  </Step>

  <Step title="Name descriptively">
    Give each tab a clear name that indicates its purpose for easy navigation
  </Step>
</Steps>

<Tip>
  Use descriptive names that clearly indicate each workflow's purpose. This becomes especially helpful as your workbook grows in complexity.
</Tip>

## Converting Nodes into Subflows

As your workflows grow complex, you can easily convert groups of nodes into modular subflows:

<Steps>
  <Step title="Select nodes">
    Hold `Shift` and drag to select the nodes you want to group
  </Step>

  <Step title="Click 'Make Subflow'">
    Click the "Make Subflow" button in the bottom bar
  </Step>

  <Step title="Automatic setup">
    The selected nodes move to a new tab, and inputs/outputs are automatically configured
  </Step>
</Steps>

<div align="center">
  <img src="https://mintcdn.com/agenthub/jPoPAi23OST-yycZ/images/subflow_example.png?fit=max&auto=format&n=jPoPAi23OST-yycZ&q=85&s=2ea5dbcc6aaec9d590a95cbd8f07c293" alt="Converting selected nodes to a subflow" width="1000" data-path="images/subflow_example.png" />
</div>

This feature is invaluable when building large automations. Break your workflow into logical chunks, test each piece independently, and reuse components across different workflows.

## Running and Debugging

Click the **Run** button in the top right corner to execute your workflow. A run report appears showing real-time progress through each node.

<div align="center">
  <img src="https://mintcdn.com/agenthub/jPoPAi23OST-yycZ/images/run_button.png?fit=max&auto=format&n=jPoPAi23OST-yycZ&q=85&s=2fb6a6f5edfad82d49c9a05535355162" alt="Run Button" width="1000" data-path="images/run_button.png" />
</div>

### Resume Button for Faster Iteration

<div align="center">
  <img src="https://mintcdn.com/agenthub/EoceKXD2pz0-p_kN/images/resume_button.png?fit=max&auto=format&n=EoceKXD2pz0-p_kN&q=85&s=5eb95e3be0a1cb3808228de2be34fefb" alt="Resume Button" width="1000" data-path="images/resume_button.png" />
</div>

<Card title="Pro Tip: Use Resume to Save Time" icon="play">
  Instead of re-running an entire workflow, use the **Resume** button (`Cmd/Ctrl + Shift + Enter`) to jump to a specific node and continue from there. This saves credits and dramatically speeds up development when you're iterating on specific parts of your workflow.
</Card>

### Viewing Run History

Track and debug workflow executions directly from the canvas:

<Steps>
  <Step title="Save your workbook">
    Ensure your workbook is saved to enable run history tracking
  </Step>

  <Step title="Click 'Previous Runs'">
    Click the "Previous Runs" icon in the top right corner
  </Step>

  <Step title="Review execution details">
    View current runs, past executions, success/failure status, and detailed node information
  </Step>
</Steps>

<div align="center">
  <img src="https://mintcdn.com/agenthub/EoceKXD2pz0-p_kN/images/previous_run_tab.png?fit=max&auto=format&n=EoceKXD2pz0-p_kN&q=85&s=089fcf594f4d2ee082f3d18f19e48242" alt="Previous runs interface" width="600" data-path="images/previous_run_tab.png" />
</div>

The run log is invaluable for debugging and understanding how your workflows perform over time.

<Card title="Run Log Documentation" icon="list" href="https://docs.gumloop.com/core-concepts/run_log">
  Learn more about debugging with the run log
</Card>

## Sharing Workbooks

Share your workbooks with team members, collaborators, or external stakeholders using Gumloop's sharing system. You can control exactly who has access and what they can do.

<div align="center">
  <img src="https://mintcdn.com/agenthub/Ou5YXeNsVPaI0uce/images/flow_general_access.png?fit=max&auto=format&n=Ou5YXeNsVPaI0uce&q=85&s=ccac8fcb5ee16ca1cd7b4edd3083b59b" alt="Workbook share dialog showing General Access and user sharing options" width="600" data-path="images/flow_general_access.png" />
</div>

### Sharing with Specific Users

Add individual users by email and assign them a role:

<div align="center">
  <img src="https://mintcdn.com/agenthub/Ou5YXeNsVPaI0uce/images/flow_user_share_access.png?fit=max&auto=format&n=Ou5YXeNsVPaI0uce&q=85&s=b7bd89fa7e75c21365e7c67c91890cab" alt="Workbook share dialog showing Editor and Viewer role options" width="600" data-path="images/flow_user_share_access.png" />
</div>

| Role       | What They Can Do                                                                                        |
| ---------- | ------------------------------------------------------------------------------------------------------- |
| **Editor** | View, edit, and run the workbook. Can also manage sharing settings.                                     |
| **Viewer** | View the workbook and its configuration, but cannot edit or run it. Can make a copy to their own space. |

### General Access

Control broader access with General Access settings:

| Level                | Who Gets Access                                                              |
| -------------------- | ---------------------------------------------------------------------------- |
| **Restricted**       | Only explicitly added users and the owner (personal workbooks only)          |
| **Team**             | All members of the team the workbook belongs to (team workbooks only)        |
| **Organization**     | All members of your organization                                             |
| **Anyone with link** | Anyone, including people without a Gumloop account (capped at Viewer access) |

You can also set the **role** for General Access (e.g., give your entire organization Viewer or Editor access).

<Info>
  For workbooks in a team, the minimum General Access level is **Team**. For personal workbooks, the default is **Restricted**. Learn more in the [Share Permissions documentation](/core-concepts/share_permissions).
</Info>

## Triggering Workbooks

Automate your workbooks to run on schedules or in response to events:

<Steps>
  <Step title="Click 'Add Trigger'">
    Click the "Add Trigger" button in the top navigation bar

    <div align="center">
      <img src="https://mintcdn.com/agenthub/hrwvapjs9dt_JqnG/images/add_trigger.png?fit=max&auto=format&n=hrwvapjs9dt_JqnG&q=85&s=3c0623fde76c1d63d7251ce7f83c348a" alt="Trigger Button" width="1000" data-path="images/add_trigger.png" />
    </div>
  </Step>

  <Step title="Choose trigger type">
    Select from time-based schedules, webhooks, or service-specific triggers (Gmail, Slack, etc.)
  </Step>

  <Step title="Configure and activate">
    Set your parameters and enable the trigger to start automated execution
  </Step>
</Steps>

<AccordionGroup>
  <Accordion title="Time-Based Triggers" icon="clock">
    Schedule your workbook to run hourly, daily, weekly, or on custom intervals. Great for regular reports or data syncs.
  </Accordion>

  <Accordion title="Webhook Triggers" icon="link">
    Start your workbook when external services send HTTP requests. Perfect for real-time integrations with other tools.
  </Accordion>

  <Accordion title="Service-Specific Triggers" icon="plug">
    Trigger on events from integrated services like Gmail (new emails), Slack (new messages), Google Sheets (new rows), and more.
  </Accordion>
</AccordionGroup>

<Card title="Triggers Documentation" icon="bolt" href="https://docs.gumloop.com/core-concepts/workflow_triggers">
  Explore all trigger options and configuration details
</Card>

## Managing Workbooks

### Moving Workbooks Between Teams

<Tabs>
  <Tab title="Move to Team">
    1. Go to the [Hub](https://www.gumloop.com/hub)
    2. Click the three dots (⋮) next to the workbook name
    3. Select "Move to Team"
    4. Choose the destination team

    Useful for sharing templates or workflows within your organization.
  </Tab>

  <Tab title="Duplicate Workbook">
    1. Go to the [Hub](https://www.gumloop.com/hub)
    2. Click the three dots (⋮) next to the workbook name
    3. Select "Duplicate"
    4. A copy appears in your current space

    Perfect for creating backups or variations of existing workflows.
  </Tab>
</Tabs>

<div align="center">
  <img src="https://mintcdn.com/agenthub/tX41SZaayXs0n5SN/images/workbook_settings.png?fit=max&auto=format&n=tX41SZaayXs0n5SN&q=85&s=bc056705df631d22abe84e093096aaf7" alt="Workbook Settings" width="1000" data-path="images/workbook_settings.png" />
</div>

## Keyboard Shortcuts

Speed up your workflow building with these essential shortcuts:

| Shortcut                   | Action                 |
| -------------------------- | ---------------------- |
| `Cmd/Ctrl + S`             | Save workbook          |
| `Cmd/Ctrl + Enter`         | Run workflow           |
| `Cmd/Ctrl + Shift + Enter` | Resume run             |
| `Cmd/Ctrl + B`             | Toggle node menu       |
| `Cmd/Ctrl + 0`             | Fit view to canvas     |
| `Shift + Drag`             | Select multiple nodes  |
| `@` symbol                 | Reference node outputs |

<Tip>
  Type `@` in any text field to bring up a menu of available node outputs. This is much faster than dragging badges manually.
</Tip>

## Best Practices

<AccordionGroup>
  <Accordion title="Save Frequently" icon="floppy-disk">
    Use `Cmd/Ctrl + S` often as you build. Regular saving ensures run history is tracked and prevents data loss. Consider creating [checkpoints](https://docs.gumloop.com/core-concepts/checkpoint_history) before major changes.
  </Accordion>

  <Accordion title="Use Descriptive Names" icon="tag">
    Name your workbooks, tabs, and even individual nodes clearly. Include version numbers or dates when relevant (e.g., "Customer Onboarding v2" or "Q4 2024 Reports").
  </Accordion>

  <Accordion title="Break Into Subflows" icon="cubes">
    If a workflow has more than 10-15 nodes, consider splitting it into subflows. This improves maintainability, enables reusability, and makes testing easier.
  </Accordion>

  <Accordion title="Test Incrementally" icon="vial">
    Use the Resume button to test specific parts of your workflow without re-running everything. This saves time and credits during development.
  </Accordion>

  <Accordion title="Use Auto Format" icon="wand-magic-sparkles">
    After making significant changes, click the Auto Format button to clean up your canvas layout. A well-organized canvas is easier to understand and maintain.
  </Accordion>

  <Accordion title="Set Up Alerts" icon="bell">
    For critical workflows, configure [alerts](https://docs.gumloop.com/core-concepts/alerts) to notify you via email if errors occur. This helps you catch issues quickly in production.
  </Accordion>
</AccordionGroup>

## Related Documentation

<CardGroup cols={2}>
  <Card title="Subflows" icon="diagram-nested" href="https://docs.gumloop.com/core-concepts/subflows">
    Learn how to create and use modular, reusable workflows
  </Card>

  <Card title="Triggers" icon="bolt" href="https://docs.gumloop.com/core-concepts/workflow_triggers">
    Automate your workbooks with schedules and event-driven triggers
  </Card>

  <Card title="Run Log" icon="list" href="https://docs.gumloop.com/core-concepts/run_log">
    Debug and monitor your workflow executions
  </Card>

  <Card title="Checkpoint History" icon="clock-rotate-left" href="https://docs.gumloop.com/core-concepts/checkpoint_history">
    Save and restore versions of your workflows
  </Card>
</CardGroup>

***

Workbooks provide the foundation for all your automation work in Gumloop. Master the canvas, leverage subflows effectively, and use the tools provided to build maintainable, production-ready workflows.
