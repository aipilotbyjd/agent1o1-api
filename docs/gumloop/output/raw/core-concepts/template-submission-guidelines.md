> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Community Template Submission Guidelines

Share your automations with the Gumloop community by publishing them as templates. This guide covers everything you need to know about creating, submitting, and managing templates in the marketplace.

<Info>
  Gumloop offers two types of automations: **Workflows** (visual, node-based automations) and **Agents** (AI-powered reasoning engines). Each has its own template creation process.
</Info>

## Quick Navigation

<CardGroup cols={3}>
  <Card title="Submit a Template" icon="upload" href="#creating-and-submitting-templates">
    Create and publish your first template
  </Card>

  <Card title="Edit Templates" icon="pen-to-square" href="#editing-published-templates">
    Update templates after publication
  </Card>

  <Card title="Delist Templates" icon="eye-slash" href="#delisting-templates">
    Remove templates from the marketplace
  </Card>
</CardGroup>

***

## Creating and Submitting Templates

The process for creating templates differs between Workflows and Agents. Choose the type that matches your automation:

<Tabs>
  <Tab title="Workflow Templates" icon="diagram-project">
    ### Submitting a Workflow Template

    Turn any workflow into a template with setup instructions that guide new users through configuration.

    <Steps>
      <Step title="Navigate to Your Workflow & Click on the Share Menu">
        Open the workflow you want to share as a template.

        Click the **Share** button in the top-right corner of your workflow.

        <div align="center">
          <img src="https://mintcdn.com/agenthub/gjrRV3kcKHQiK5hH/images/image-1-share-dialog.png?fit=max&auto=format&n=gjrRV3kcKHQiK5hH&q=85&s=7316af89fe47ad1fa843b73e5f62180b" alt="Share Workbook Dialog" width="800" data-path="images/image-1-share-dialog.png" />
        </div>
      </Step>

      <Step title="Create Template with Set-up Instructions">
        Select **Create Template with Set-up Instructions** from the options.

        <div align="center">
          <img src="https://mintcdn.com/agenthub/gjrRV3kcKHQiK5hH/images/image-2-share-dialog.png?fit=max&auto=format&n=gjrRV3kcKHQiK5hH&q=85&s=6bbe321ae8a245eee68d741d21bd1e34" alt="Share Workbook Dialog" width="800" data-path="images/image-2-share-dialog.png" />
        </div>
      </Step>

      <Step title="Review AI-Generated Setup">
        Gumloop automatically generates initial template information including:

        * Template title
        * Description
        * Setup checklist with basic steps
        * Required credentials detection

        This provides a starting point that you can customize.
      </Step>

      <Step title="Configure Template Details">
        Customize your template information to help users understand and implement your workflow.

        <div align="center">
          <img src="https://mintcdn.com/agenthub/cUqZYRfwMGRsM1up/images/image-3-template-form.png?fit=max&auto=format&n=cUqZYRfwMGRsM1up&q=85&s=703926974e2c62e2910913b4c4d8267d" alt="Template Creation Form" width="800" data-path="images/image-3-template-form.png" />
        </div>

        <AccordionGroup>
          <Accordion title="General Information" icon="circle-info">
            **Title**

            Create a clear, action-oriented title that describes what the template does. Include key integrations or tools used.

            Example: "Executive Escalations Email and Slack Automation"

            **Description**

            Provide a detailed overview using markdown formatting. Break your description into sections:

            * Who is this for?
            * What problem does it solve?
            * What does the workflow do?
            * How can users customize it?
            * Any requirements or prerequisites

            **Categories**

            Select at least one category that matches your template's primary use case.
          </Accordion>

          <Accordion title="Credentials Needed" icon="key">
            Review the automatically detected credentials required to run your template. These are identified based on the integrations used in your workflow.

            Users will be prompted to connect these services before using your template.
          </Accordion>

          <Accordion title="Set-up Checklist" icon="clipboard-check">
            Create a step-by-step guide that walks users through configuring your template.

            <div align="center">
              <img src="https://mintcdn.com/agenthub/cUqZYRfwMGRsM1up/images/image-4-setup-checklist.png?fit=max&auto=format&n=cUqZYRfwMGRsM1up&q=85&s=9246e5e33f5527a108033fa49e74c339" alt="Setup Checklist Configuration" width="800" data-path="images/image-4-setup-checklist.png" />
            </div>

            **For each step:**

            1. **Add a Clear Title**: Describe what the user needs to do (e.g., "Configure Email Trigger Node")

            2. **Provide Detailed Instructions**: Explain exactly what to configure and why. Don't assume users are familiar with your specific setup.

            3. **Link to Relevant Nodes**: Click **Select a node from the canvas** to highlight the specific node users should configure for this step. This helps users navigate complex workflows.

            4. **Include External Resources**: If users need to set up external tools (like copying a Google Sheet template), provide direct links.

            <Tip>
              Use the **Add Step** button to create additional checklist items. You can reorder steps by dragging them.
            </Tip>
          </Accordion>
        </AccordionGroup>
      </Step>

      <Step title="Submit for Review">
        Once you've configured all details, submit your template for marketplace review. All submissions go through a review process to ensure quality and security standards are met.

        <div align="center">
          <img src="https://mintcdn.com/agenthub/SG9k9CtExARWwfbJ/images/submit_template.png?fit=max&auto=format&n=SG9k9CtExARWwfbJ&q=85&s=ef0d202dc5ecb56915817fdbcb6254b7" alt="Submit Template" width="800" data-path="images/submit_template.png" />
        </div>

        <Info>
          Review the [submission requirements](#submission-requirements) below before submitting to ensure your template meets all guidelines.
        </Info>
      </Step>
    </Steps>
  </Tab>

  <Tab title="Agent Templates" icon="robot">
    ### Submitting an Agent Template

    Share your AI agent with the community by publishing it as a template.

    <Steps>
      <Step title="Navigate to Your Agent">
        Open the agent you want to share as a template.
      </Step>

      <Step title="Click Publish as Template">
        In the left sidebar, click the **Publish as Template** button located directly below the "New Chat" button.

        <div align="center">
          <img src="https://mintcdn.com/agenthub/cUqZYRfwMGRsM1up/images/image-5-agent-publish.png?fit=max&auto=format&n=cUqZYRfwMGRsM1up&q=85&s=06f8fe5caa67f290a2f346af9ce3cec6" alt="Agent Template Publishing" width="800" data-path="images/image-5-agent-publish.png" />
        </div>
      </Step>

      <Step title="Configure Template Details">
        Fill in the template information to help users understand your agent's capabilities.

        <AccordionGroup>
          <Accordion title="Basic Information" icon="circle-info">
            **Agent Name**

            Create a clear, descriptive name that explains what your agent does.

            **Description**

            Provide a detailed overview of your agent using markdown formatting. Explain:

            * Who is this agent for?
            * What problem does it solve?
            * What capabilities does it have?
            * How can users interact with it?

            **Categories**

            Select at least one category that matches your agent's primary purpose.
          </Accordion>

          <Accordion title="Instructions" icon="list-check">
            This is where you define your agent's behavior and capabilities. The instructions you've configured for your agent will be visible to users.

            Make sure your instructions are clear and explain the agent's role, available tools, and how it should respond to different types of requests.
          </Accordion>

          <Accordion title="Chat Replay" icon="comments">
            Showcase your agent's capabilities by adding example conversations to your template page. You can select up to 3 chat replays from your existing conversations with the agent.

            <div align="center">
              <img src="https://mintcdn.com/agenthub/XS_TiG4NSc_1PMWo/images/chat-replay-selection.png?fit=max&auto=format&n=XS_TiG4NSc_1PMWo&q=85&s=c9fdfc8f0bccc788d376cfa487571de1" alt="Chat Replay Selection" width="800" data-path="images/chat-replay-selection.png" />
            </div>

            Chat replays help potential users understand how your agent responds and what kinds of interactions they can expect. When users view your template, they'll see these conversations play back, demonstrating your agent in action.

            <div align="center">
              <img src="https://mintcdn.com/agenthub/XS_TiG4NSc_1PMWo/images/chat-replay-preview.png?fit=max&auto=format&n=XS_TiG4NSc_1PMWo&q=85&s=1caf55c6e88d91520e513a6d96dcc70d" alt="Chat Replay Preview" width="500" data-path="images/chat-replay-preview.png" />
            </div>

            To add a chat replay, browse your existing conversations and click the **+** button next to any chat you want to include. You can preview each replay before adding it to ensure it showcases your agent effectively.

            <Warning>
              Chat replays will be publicly visible on your template page. Do not include conversations containing sensitive information, personal data, API keys, or confidential content.
            </Warning>
          </Accordion>

          <Accordion title="Preview" icon="eye">
            Review how your agent template will appear to users in the marketplace. Check that all information is accurate and complete before submitting.
          </Accordion>
        </AccordionGroup>
      </Step>

      <Step title="Submit for Review">
        Once you've configured all details, submit your agent template for marketplace review.

        <Info>
          Review the [submission requirements](#submission-requirements) below before submitting to ensure your template meets all guidelines.
        </Info>
      </Step>
    </Steps>

    <Note>
      Unlike workflow templates, agent templates don't include node-based setup instructions since agents don't use a visual canvas. Focus on providing clear instructions and a comprehensive description.
    </Note>
  </Tab>
</Tabs>

***

## Editing Published Templates

Need to update a template after it's been published? You can make changes and submit them for review.

<Steps>
  <Step title="Open Your Published Template">
    Navigate to the workflow or agent that has been published as a template.
  </Step>

  <Step title="Click the Template Badge">
    In the top-right corner of your workflow or agent, you'll see a **Template** badge or icon. Click on it to open the template editor.

    <div align="center">
      <img src="https://mintcdn.com/agenthub/SG9k9CtExARWwfbJ/images/template-badge.png?fit=max&auto=format&n=SG9k9CtExARWwfbJ&q=85&s=69ca1dbaa3edadecccc23f310b24eef8" alt="Template Badge Location" width="800" data-path="images/template-badge.png" />
    </div>
  </Step>

  <Step title="Make Your Changes">
    The template editor will open, showing all your template configuration:

    * General Information (title, description, categories)
    * Credentials needed
    * Setup checklist (for workflows)
    * Agent instructions (for agents)

    <div align="center">
      <img src="https://mintcdn.com/agenthub/9NvX1uFKUQMQc6a_/images/edit-template.png?fit=max&auto=format&n=9NvX1uFKUQMQc6a_&q=85&s=17512cd68b38e768ef1d581a138ea7b7" alt="Edit Template Interface" width="800" data-path="images/edit-template.png" />
    </div>

    Update any fields you want to change. You can modify descriptions, add new setup steps, update categories, or refine instructions.
  </Step>

  <Step title="Submit Updated Template for Review">
    Once you've made your changes, click **Save & Submit Edits for Review** at the bottom of the template editor.

    Your updates will be sent to the Gumloop team for review.
  </Step>

  <Step title="Wait for Approval">
    The Gumloop team will review your changes to ensure they meet quality and security standards. Once approved, your updated template will go live on the marketplace.
  </Step>
</Steps>

<Note>
  **Important:** Users who have already cloned your template will not receive the updated version. Template updates only apply to new users who clone the template after your changes are approved. Each user gets their own independent copy when they use a template.
</Note>

***

## Delisting Templates

If you need to remove a template from the marketplace, you can delist it at any time.

<Steps>
  <Step title="Open Your Published Template">
    Navigate to the workflow or agent that has been published as a template.
  </Step>

  <Step title="Click the Template Badge">
    In the top-right corner, click the **Template** badge or icon to open the template editor.

    <div align="center">
      <img src="https://mintcdn.com/agenthub/SG9k9CtExARWwfbJ/images/template-badge.png?fit=max&auto=format&n=SG9k9CtExARWwfbJ&q=85&s=69ca1dbaa3edadecccc23f310b24eef8" alt="Template Badge Location" width="800" data-path="images/template-badge.png" />
    </div>
  </Step>

  <Step title="Delist the Template">
    At the bottom of the template editor, click **Save & Delist Template**.

    <div align="center">
      <img src="https://mintcdn.com/agenthub/8SdxK5csj7bAHh-j/images/save-delist.png?fit=max&auto=format&n=8SdxK5csj7bAHh-j&q=85&s=0b0114bf6b6bfa79d829d371a2f51e5e" alt="Save and Delist Template" width="800" data-path="images/save-delist.png" />
    </div>

    This will immediately remove your template from the marketplace.
  </Step>
</Steps>

<Info>
  Delisting a template removes it from the marketplace, but users who have already cloned your template will still have access to their copies. Delisting does not affect existing template clones.
</Info>

<Tip>
  If you want to relist a template later, you can follow the same process to edit your template and resubmit it for review.
</Tip>

***

## Submission Requirements

Before submitting your template, ensure it meets all quality, security, and documentation standards.

### Must-Have Requirements

<Warning>
  If your template doesn't follow these guidelines, we will immediately reject it.
</Warning>

<Tabs>
  <Tab title="Security Standards" icon="shield-check">
    **No Security Credentials**

    Don't store security credentials anywhere in the template (e.g., no hardcoded API keys in any custom nodes or agent instructions).

    **Remove Sensitive Information**

    Remove all sensitive or personally identifying information (e.g., remove real email addresses, personal data).
  </Tab>

  <Tab title="Quality Standards" icon="star">
    **No Low-Effort Templates**

    Don't submit low-effort or spammy templates. Templates should be thoughtful and add value to the marketplace.

    **Template Must Work**

    The template has to actually work. If the workflow doesn't run properly or the agent doesn't respond correctly, we will reject it.

    **Accurate Description**

    The description has to match what the automation actually does.
  </Tab>
</Tabs>

***

## Preparing Your Template

Before submitting, review these preparation checklists to ensure your template is ready:

<Tabs>
  <Tab title="Preparing a Workflow" icon="diagram-project">
    <AccordionGroup>
      <Accordion title="Add Context with Note Nodes" icon="note-sticky">
        The more context you provide about how your workflow operates, the better. Use Note nodes to explain:

        * Where users need to input their own information or authenticate services
        * What subflows, if/else operators, or custom nodes do
        * Any important logic or decision points
      </Accordion>

      <Accordion title="Generalize Your Workflow" icon="arrows-split-up-and-left">
        Make your template easier for others to use by removing personal specifics:

        * Check AI prompts for mentions of your company, coworkers, or specific strategies—reword them to be generic
        * Check custom node code for hardcoded API keys or references to internal systems
        * Add notes recommending where users should make their own customizations
      </Accordion>

      <Accordion title="Update All Nodes" icon="rotate">
        Hover over all nodes to check if they're upgraded to the latest versions. If a node needs updates, you'll see an option like "Upgrade Version (v28 to v31)" when hovering over the node.
      </Accordion>

      <Accordion title="Use Descriptive Names" icon="tag">
        Rename nodes and outputs to clearly describe their purpose:

        | Default Name   | Better Name                |
        | -------------- | -------------------------- |
        | "Gmail Sender" | "Send Weekly Brand Report" |
        | "Ask AI"       | "Generate Report Summary"  |
        | "Response"     | "Personalized Email Copy"  |
        | "Workflow 2"   | "Analyze SEO Keywords"     |
      </Accordion>

      <Accordion title="Clean Up Your Canvas" icon="broom">
        * Use auto-formatting to organize your layout
        * Group related logic into [subflows](https://docs.gumloop.com/core-concepts/subflows) when appropriate
        * Remove any unused or disconnected nodes
      </Accordion>
    </AccordionGroup>
  </Tab>

  <Tab title="Preparing an Agent" icon="robot">
    <AccordionGroup>
      <Accordion title="Review Connected Workflows" icon="diagram-project">
        When users copy an agent template, they also receive copies of any connected workflows. Ensure all connected workflows are appropriate for submission and follow the workflow preparation guidelines.
      </Accordion>

      <Accordion title="Remove Unnecessary Tools" icon="wrench">
        Agents perform best with only the tools they need. Having too many connected tools can negatively impact performance. Remove any tools or workflows that aren't essential to your agent's core functionality.
      </Accordion>

      <Accordion title="Templatize Your Instructions" icon="file-lines">
        Read through your AI instructions carefully. If there are hard-coded references to specific URLs, email addresses, or other personal details:

        * Rewrite instructions to remove sensitive information
        * Add a "Template Instructions" section listing all variables users need to customize
        * Make it clear where users should input their own information

        <Tip>
          Ask your agent for help! Try: "This agent is designed to be copied and used as a template by others. Do you have any suggestions for rewriting the AI Instructions to make it easier to use and customize as a template?"
        </Tip>
      </Accordion>

      <Accordion title="Add a Custom Icon" icon="image">
        While not required, a well-chosen icon helps your agent stand out in the marketplace and gives users a visual cue about its purpose.
      </Accordion>
    </AccordionGroup>
  </Tab>
</Tabs>

***

## General Guidelines

Creating great marketplace templates means building automations that solve real problems and can be easily adopted by others across the Gumloop community.

<CardGroup cols={2}>
  <Card title="Real-World Relevance" icon="bullseye">
    Make templates that are relevant to real-world use cases
  </Card>

  <Card title="Do Something New" icon="sparkles">
    Check out what's currently on the [marketplace](https://www.gumloop.com/templates) to get a sense of what's already been done
  </Card>

  <Card title="Provide Context" icon="circle-info">
    Provide as much context as possible. Assume that the user may be new to AI automation
  </Card>

  <Card title="Keep It Clean" icon="broom">
    Keep your automation clean and easy to understand. For workflows, use auto-formatting and subflows when needed
  </Card>
</CardGroup>

***

## Best Practices for the Template Description Page

The template description page is what potential users see when browsing the marketplace. Make it clear, comprehensive, and helpful.

### Creating Effective Titles

<CardGroup cols={2}>
  <Card title="Use Action Verbs" icon="bolt">
    The title should clearly describe the purpose of the template. Use action verbs (e.g. "Summarize," "Optimize," "Generate," "Analyze").
  </Card>

  <Card title="Include Key Features" icon="puzzle-piece">
    For workflows, include the most important integrations (e.g. Google Sheets, YouTube, Apollo). For agents, highlight key capabilities.
  </Card>
</CardGroup>

### Writing the Details Section

Use markdown formatting in the longform description (the "Details" section). Break up the description into sections:

<AccordionGroup>
  <Accordion title="Recommended Sections" icon="list">
    **Who is this for?**

    Describe the target audience.

    **What problem is this solving?**

    Explain the pain point this template addresses.

    **What does this automation do?**

    Provide an overview of how the workflow or agent operates.

    **How can I customize this?**

    For workflows, list configurable nodes and parameters. For agents, explain how users can modify instructions or behavior.

    **Requirements**

    Detail prerequisites and setup needs (credentials, external tools, etc.).
  </Accordion>

  <Accordion title="External Resources" icon="link">
    If your automation requires external setup (e.g. a formatted Google Sheet, an Airtable database), include a link to make a copy.

    If necessary, include links to external setup guides (e.g. Notion page, YouTube or Loom videos).
  </Accordion>
</AccordionGroup>

### Setting Up the Checklist (Workflows Only)

For workflow templates, include brief descriptions of steps a user would need to take to set up the template (e.g., "Make a copy of this Google Sheet," "Connect to this Slack channel," etc.)

<div align="center">
  <img src="https://mintcdn.com/agenthub/cUqZYRfwMGRsM1up/images/image-4-setup-checklist.png?fit=max&auto=format&n=cUqZYRfwMGRsM1up&q=85&s=9246e5e33f5527a108033fa49e74c339" alt="Setup Checklist Configuration" width="800" data-path="images/image-4-setup-checklist.png" />
</div>

<Tip>
  If a node is relevant to a particular step, make sure to select that node using the "Select a node from the canvas" button for that step.
</Tip>

***

## Bannable Offenses

<Danger>
  If your template violates these guidelines, we will ban you and suspend your account.
</Danger>

| Violation             | Description                                                                        |
| --------------------- | ---------------------------------------------------------------------------------- |
| **Malicious Content** | Do not attempt to upload anything malicious. We review all submissions thoroughly. |
| **Plagiarism**        | Do not plagiarize other people's work.                                             |
| **Legal Violations**  | Do not submit anything that could put you, others, or Gumloop at legal risk.       |

***

## Submission Checklist

Before submitting your template, verify it meets all requirements:

<Tabs>
  <Tab title="Workflow Templates" icon="diagram-project">
    <AccordionGroup>
      <Accordion title="Pre-Submission Review" icon="clipboard-check">
        **Content & Quality**

        * [ ] Template solves a real-world problem
        * [ ] Checked marketplace for similar templates
        * [ ] Workflow has been thoroughly tested and runs properly
        * [ ] Description accurately reflects functionality

        **Security**

        * [ ] All credentials and API keys removed
        * [ ] All sensitive/personally identifying information removed

        **Documentation**

        * [ ] Nodes renamed with descriptive names
        * [ ] Outputs renamed to reflect actual data
        * [ ] Note nodes added for context
        * [ ] Auto-formatting applied
        * [ ] Subflows used where appropriate

        **Template Page**

        * [ ] Title uses action verbs
        * [ ] Important integrations included in title
        * [ ] At least one category selected
        * [ ] Details section uses markdown formatting
        * [ ] All recommended sections included in description
        * [ ] External resource links provided where needed
        * [ ] Setup checklist completed with clear steps
        * [ ] Nodes linked to relevant setup steps
      </Accordion>
    </AccordionGroup>
  </Tab>

  <Tab title="Agent Templates" icon="robot">
    <AccordionGroup>
      <Accordion title="Pre-Submission Review" icon="clipboard-check">
        **Content & Quality**

        * [ ] Template solves a real-world problem
        * [ ] Checked marketplace for similar templates
        * [ ] Agent has been thoroughly tested and responds correctly
        * [ ] Description accurately reflects agent behavior

        **Security**

        * [ ] All credentials and API keys removed from instructions
        * [ ] All sensitive/personally identifying information removed

        **Documentation**

        * [ ] Agent instructions are clear and comprehensive
        * [ ] Agent capabilities and limitations are documented
        * [ ] Example interactions provided

        **Template Page**

        * [ ] Title uses action verbs
        * [ ] Key capabilities included in title
        * [ ] At least one category selected
        * [ ] Details section uses markdown formatting
        * [ ] All recommended sections included in description
        * [ ] Chat replays added to showcase agent capabilities (up to 3)
        * [ ] External resource links provided where needed
      </Accordion>
    </AccordionGroup>
  </Tab>
</Tabs>

***

## Related Resources

<CardGroup cols={2}>
  <Card title="Subflows Guide" icon="diagram-project" href="https://docs.gumloop.com/core-concepts/subflows">
    Learn how to use subflows for cleaner, more organized workflows
  </Card>

  <Card title="Browse Marketplace" icon="grid" href="https://www.gumloop.com/templates">
    Explore existing templates on the marketplace
  </Card>
</CardGroup>
