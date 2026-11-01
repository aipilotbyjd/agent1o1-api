> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Run Log

The Run Log is your command center for understanding how your workflows execute. It provides real-time insights into every step of your automation, from execution status to detailed input/output data, helping you quickly identify issues and optimize performance.

<Info>
  **Important:** Your workbook must be saved to track run history. Unsaved workbooks will show the current run log, but previous runs may not be recorded until saved.
</Info>

***

## How to Access the Run Log

There are two ways to access the Run Log depending on where you are in Gumloop:

<Tabs>
  <Tab title="From a Run Link" icon="link">
    ### When You're on a Run Link

    You can identify if you're on a run link by checking your URL - it will contain a `run_id` parameter.

    **Example URL:**

    ```text theme={"dark"}
    https://www.gumloop.com/pipeline?workbook_id=YOUR_WORKBOOK_ID&run_id=YOUR_RUN_ID
    ```

    When you're on a run link:

    * The **Current Run** panel appears on the right-hand side
    * It automatically displays the execution details for that specific run
    * You'll see all node statuses, execution times, and outputs

    <div align="center">
      <img src="https://mintcdn.com/agenthub/UEOVDJ1y2kjjmhFE/images/run_link_current_run.png?fit=max&auto=format&n=UEOVDJ1y2kjjmhFE&q=85&s=41debda245d74002595f8f130a8a1d7d" alt="Current Run panel on run link" width="1110" height="1206" data-path="images/run_link_current_run.png" />
    </div>
  </Tab>

  <Tab title="From Previous Runs" icon="clock-rotate-left">
    ### When You're on a Workflow Page

    If you're not on a run link and want to view previous executions:

    <Steps>
      <Step title="Open your workflow">
        Navigate to the workflow you want to inspect
      </Step>

      <Step title="Go to Previous Runs section">
        Look for the "Previous Runs" section in your workflow interface

        <div align="center">
          <img src="https://mintcdn.com/agenthub/RtYKu3CdLhtqf3By/images/previous_runs_section.png?fit=max&auto=format&n=RtYKu3CdLhtqf3By&q=85&s=ad26f8ccc449744c262c07d50dff02a6" alt="Previous Runs Section" width="744" height="454" data-path="images/previous_runs_section.png" />
        </div>
      </Step>

      <Step title="Click on a run">
        Select any previous run from the list to open its Run Log

        <div align="center">
          <img src="https://mintcdn.com/agenthub/UEOVDJ1y2kjjmhFE/images/select_previous_run.png?fit=max&auto=format&n=UEOVDJ1y2kjjmhFE&q=85&s=f095c92cbefda846227f5c7b868b63e7" alt="Select Previous Run" width="928" height="752" data-path="images/select_previous_run.png" />
        </div>
      </Step>
    </Steps>

    <Tip>
      Previous runs are only available for saved workbooks. Make sure to save your workbook to preserve run history.
    </Tip>
  </Tab>
</Tabs>

***

## What You'll See in the Run Log

Once you've accessed the Run Log, here's what information is available:

<CardGroup cols={2}>
  <Card title="Execution Status" icon="circle-check">
    Real-time status indicators for each node showing success, errors, or running state
  </Card>

  <Card title="Performance Metrics" icon="stopwatch">
    Time taken by each node and total workflow execution time
  </Card>

  <Card title="Credit Tracking" icon="coins">
    Credit costs per node and total credits consumed by the workflow
  </Card>

  <Card title="Data Inspection" icon="magnifying-glass">
    Complete access to inputs and outputs for every node
  </Card>
</CardGroup>

***

## Run Log Components

### Execution Status Indicators

Each node displays a clear status indicator to help you quickly identify execution results:

<Tabs>
  <Tab title="Success" icon="circle-check">
    **Green checkmark** - Node executed successfully without errors

    This means the node completed its task and passed data to the next node as expected.

    <div align="center">
      <img src="https://mintcdn.com/agenthub/RtYKu3CdLhtqf3By/images/node_status_success.png?fit=max&auto=format&n=RtYKu3CdLhtqf3By&q=85&s=9f0ecaea0c2f30c2c10e65cc7ef9da49" alt="Node status indicators" width="300" data-path="images/node_status_success.png" />
    </div>
  </Tab>

  <Tab title="Error" icon="circle-xmark">
    **Red X mark** - Node failed during execution

    Click on the node to view detailed error messages and troubleshoot the issue.

    <div align="center">
      <img src="https://mintcdn.com/agenthub/RtYKu3CdLhtqf3By/images/node_status_failed.png?fit=max&auto=format&n=RtYKu3CdLhtqf3By&q=85&s=e09dd415a60445be7f35dc5a10fbf021" alt="Node status indicators" width="300" data-path="images/node_status_failed.png" />
    </div>
  </Tab>

  <Tab title="Running" icon="clock">
    **Animated clock** - Node is currently executing

    The node is actively processing. Wait for completion or monitor progress.

    <div align="center">
      <img src="https://mintcdn.com/agenthub/RtYKu3CdLhtqf3By/images/node_status_running.png?fit=max&auto=format&n=RtYKu3CdLhtqf3By&q=85&s=c8d0bdb3751a093cbadfd269ed08a894" alt="Node status indicators" width="300" data-path="images/node_status_running.png" />
    </div>
  </Tab>
</Tabs>

### Time and Credit Information

Monitor the performance and cost of your workflow execution:

**Per Node:**

* **Execution time**: Duration each node took to run (e.g., "6.18s")
* **Credit cost**: Number of credits consumed by that specific node

**Workflow Summary:**
Located at the bottom of the Run Log, you'll find:

* **Total execution time**: Cumulative time for the entire workflow
* **Total credits consumed**: Sum of all node credit costs

<div align="center">
  <img src="https://mintcdn.com/agenthub/UEOVDJ1y2kjjmhFE/images/run_log_summary.png?fit=max&auto=format&n=UEOVDJ1y2kjjmhFE&q=85&s=e06145a173ee7f16aed1c0778356e245" alt="Run Log Summary" width="400" data-path="images/run_log_summary.png" />
</div>

<Tip>
  Use these metrics to identify bottlenecks and optimize your workflow's performance and credit efficiency.
</Tip>

***

## Inspecting Inputs and Outputs

One of the most powerful debugging features in the Run Log is the ability to inspect exactly what data flows between nodes.

### How to View Data

<Steps>
  <Step title="Open the modal">
    Click the **"See all inputs and outputs"** button for any node in the Run Log

    <div align="center">
      <img src="https://mintcdn.com/agenthub/UEOVDJ1y2kjjmhFE/images/run_log_inputs.png?fit=max&auto=format&n=UEOVDJ1y2kjjmhFE&q=85&s=7d3eaba3dec12b27f16b50183c850263" alt="Inputs and Outputs button" width="300" data-path="images/run_log_inputs.png" />
    </div>
  </Step>

  <Step title="Review the data">
    A modal appears displaying:

    * **All input data** the node received from previous nodes
    * **All output data** the node produced for downstream nodes

    <div align="center">
      <img src="https://mintcdn.com/agenthub/Zv6nVl5TV-0ByJrK/images/input_output_modal.png?fit=max&auto=format&n=Zv6nVl5TV-0ByJrK&q=85&s=a945d03a2ab856bfd4b8b3635b21bb43" alt="Inputs and Outputs Modal" width="900" data-path="images/input_output_modal.png" />
    </div>
  </Step>
</Steps>

### Understanding Data Display

The modal shows data according to its type, with visual icons to help you quickly identify what you're looking at:

| Data Type  | Icon                 | Display Format              | Use Case                                 |
| ---------- | -------------------- | --------------------------- | ---------------------------------------- |
| **Text**   | <Icon icon="t" />    | Plain text format           | Verify text content and formatting       |
| **List**   | <Icon icon="list" /> | Expandable with item counts | Check list length and individual entries |
| **File**   | <Icon icon="file" /> | File names and metadata     | Confirm file processing and names        |
| **Object** | Plain text           | Structured display          | Inspect complex data structures          |

<AccordionGroup>
  <Accordion title="When to Use Input/Output Inspection" icon="lightbulb">
    This modal is invaluable for:

    * **Verifying data flow** between nodes is working correctly
    * **Checking text formatting** matches your expectations
    * **Ensuring list items** contain the expected values
    * **Confirming files** are being processed properly
    * **Debugging errors** by seeing exactly what data caused issues
  </Accordion>

  <Accordion title="Pro Debugging Tips" icon="wand-magic-sparkles">
    * Start debugging from the first failed node and work backwards
    * Compare inputs vs outputs to see how each node transforms data
    * Use inputs/outputs to understand why a node produced unexpected results
    * Check for empty inputs that might indicate issues with previous nodes
    * Look for the type icons to quickly understand data structure
  </Accordion>
</AccordionGroup>

***

## Working with Subflows

Subflows have special Run Log behavior that helps you debug complex, nested workflows.

### Navigation Between Parent and Subflow

When your workflow contains subflows, the Run Log provides two levels of visibility:

1. **Parent Workflow View**: Shows the subflow as a single node with overall status
2. **Subflow Detail View**: Shows individual node execution within the subflow

<Steps>
  <Step title="Click the subflow node">
    In the parent workflow's Run Log, click on any subflow node

    <div align="center">
      <img src="https://mintcdn.com/agenthub/UEOVDJ1y2kjjmhFE/images/subflow_node_run_log.png?fit=max&auto=format&n=UEOVDJ1y2kjjmhFE&q=85&s=a65c9ff959bd2867c45b933b99da3c93" alt="Subflow in Run Log" width="300" data-path="images/subflow_node_run_log.png" />
    </div>
  </Step>

  <Step title="Click 'View Run'">
    A "View Run" option appears - click it to see detailed subflow execution

    <div align="center">
      <img src="https://mintcdn.com/agenthub/UEOVDJ1y2kjjmhFE/images/subflow_node_view_run.png?fit=max&auto=format&n=UEOVDJ1y2kjjmhFE&q=85&s=4c43641ad9dc7ffa9e592061fd9a281a" alt="Subflow Run Button" width="300" data-path="images/subflow_node_view_run.png" />
    </div>
  </Step>

  <Step title="Return to parent">
    Use the workflow tabs at the bottom of the screen to navigate back to the parent workflow
  </Step>
</Steps>

<Check>
  **Why this matters:** This navigation lets you pinpoint exactly where issues occur in complex workflows and see how data flows through different levels of your automation.
</Check>

***

## Loop Mode Execution

When nodes run in Loop Mode, the Run Log enumerates each iteration, giving you visibility into how individual items are processed.

### Viewing Loop Mode Details

<div align="center">
  <img src="https://mintcdn.com/agenthub/UEOVDJ1y2kjjmhFE/images/run_log_loop_mode.png?fit=max&auto=format&n=UEOVDJ1y2kjjmhFE&q=85&s=ce0c69ac4e013ab06b0511bdc9024f6c" alt="Loop Mode in Run Log" width="300" data-path="images/run_log_loop_mode.png" />
</div>

<Tabs>
  <Tab title="How It Works" icon="repeat">
    **In Loop Mode:**

    1. Expand the node or subflow in the Run Log
    2. Each iteration is numbered (1, 2, 3, etc.)
    3. Click any iteration to view its specific inputs/outputs
    4. Monitor which items succeeded or failed individually
  </Tab>

  <Tab title="Common Issues" icon="triangle-exclamation">
    **List Size Mismatch Error**

    This is one of the most common Loop Mode errors. It happens when a node receives multiple list inputs of different lengths.

    <Card title="Fix List Size Mismatches" icon="wrench" href="/common_errors/list_size_mismatch">
      Learn how to diagnose and resolve list size mismatch errors
    </Card>
  </Tab>
</Tabs>

***

## Debugging with the Run Log

### Common Error Patterns

<AccordionGroup>
  <Accordion title="Authentication Errors" icon="key">
    **How it appears:** "Failed to authenticate" or "Credentials not found"

    **Solution:**

    * Check your credentials in [Credentials settings](https://docs.gumloop.com/core-concepts/credentials)
    * Verify credentials haven't expired or been revoked
    * Re-authenticate the integration if necessary
  </Accordion>

  <Accordion title="Empty Input Errors" icon="inbox">
    **How it appears:** "No input provided" or "Input is required"

    **Solution:**

    * Inspect the previous node's output using the input/output modal
    * Ensure the previous node is outputting data as expected
    * Check for type mismatches between nodes
  </Accordion>

  <Accordion title="Timeout Errors" icon="clock">
    **How it appears:** "Request timed out" or "Execution exceeded time limit"

    **Solution:**

    * Break large operations into smaller chunks using Loop Mode
    * Add error handling with [Error Shield nodes](https://docs.gumloop.com/nodes/flow_basics/error_shield)
    * Consider using pagination for data source nodes
  </Accordion>
</AccordionGroup>

### Systematic Debugging Approach

<Steps>
  <Step title="Identify failed nodes">
    Scan the Run Log for nodes with error status indicators
  </Step>

  <Step title="Inspect inputs and outputs">
    Use the input/output modal to see exactly what data the node received and produced
  </Step>

  <Step title="Review error messages">
    Read the specific error information in the node's output
  </Step>

  <Step title="Trace data flow">
    Follow how data changes as it moves through your workflow to identify where issues begin
  </Step>

  <Step title="Check subflows">
    For complex workflows, drill down into subflow runs to isolate the exact location of issues
  </Step>

  <Step title="Compare with history">
    Reference previous successful runs to identify what changed
  </Step>
</Steps>

***

## Best Practices

<CardGroup cols={2}>
  <Card title="Test with Small Samples" icon="flask">
    Run workflows with limited data first to quickly identify and fix issues before processing large datasets
  </Card>

  <Card title="Use Error Shield" icon="shield-halved">
    Wrap critical nodes in [Error Shield nodes](https://docs.gumloop.com/nodes/flow_basics/error_shield) to see both success and failure paths
  </Card>

  <Card title="Monitor Credit Costs" icon="chart-pie">
    Track credit usage in the Run Log to optimize efficiency and reduce unnecessary costs
  </Card>

  <Card title="Save Your Workbook" icon="floppy-disk">
    Always save your workbook to preserve run history for future troubleshooting and comparison
  </Card>

  <Card title="Document Run Links" icon="link">
    Share run links with team members when asking for help or documenting issues
  </Card>

  <Card title="Review Regularly" icon="calendar-check">
    Check previous runs periodically to catch patterns in errors or performance degradation
  </Card>
</CardGroup>

***

## Quick Reference: Run Log Features

| Feature             | Purpose                          | How to Access                             |
| ------------------- | -------------------------------- | ----------------------------------------- |
| **Node Status**     | View success/error/running state | Automatically visible in Run Log          |
| **Execution Time**  | Monitor performance per node     | Shown on each node in Run Log             |
| **Credit Cost**     | Track credit usage               | Displayed on each node and in summary     |
| **Inputs/Outputs**  | Debug data flow                  | Click "See all inputs and outputs" button |
| **Subflow Details** | Inspect nested workflows         | Click subflow node → "View Run"           |
| **Loop Iterations** | View individual loop processing  | Expand loop mode node                     |
| **Run History**     | Compare past executions          | Click "Previous Runs" tab                 |
| **Run Links**       | Share for collaboration          | Available in run history                  |

***

## Need More Help?

<CardGroup cols={2}>
  <Card title="Contact Support" icon="comments" href="https://portal.usepylon.com/gumloop/forms/help">
    Need help? Reach out to us and we'll assist you.
  </Card>

  <Card title="Error Troubleshooting" icon="wrench" href="/common_errors/list_size_mismatch">
    Learn how to fix common errors like list size mismatches
  </Card>

  <Card title="Error Shield Node" icon="shield" href="/nodes/flow_basics/error_shield">
    Add error handling to your workflows for better debugging
  </Card>

  <Card title="Credentials Setup" icon="key" href="/core-concepts/credentials">
    Configure authentication for integrations
  </Card>
</CardGroup>

***

## Summary

The Run Log is your most powerful tool for understanding, debugging, and optimizing Gumloop workflows. By mastering its features - from status indicators to input/output inspection to subflow navigation - you can:

✅ **Quickly identify and resolve issues** with detailed error information\
✅ **Understand data flow** through your entire automation\
✅ **Optimize performance** by monitoring execution time and credit usage\
✅ **Collaborate effectively** by sharing run links with your team\
✅ **Track improvements** by comparing runs over time

<Tip>
  **Pro Tip:** Bookmark successful run logs as reference points. When something breaks, compare the current run with a known-good run to quickly identify what changed.
</Tip>
