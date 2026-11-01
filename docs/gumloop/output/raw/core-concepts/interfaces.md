> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Gumloop Interfaces

Transform your workflows into intuitive, user-friendly interfaces with Gumloop Interfaces. Instead of requiring users to navigate complex workflows, share your workflows as simple, customizable interfaces that anyone can easily use.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1059032733?h=2b651bb2a9" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="Gumloop Interfaces" />
</div>

## Why Gumloop Interfaces?

<CardGroup cols={2}>
  <Card title="The Problem" icon="circle-xmark" color="#ef4444">
    Without Interfaces, sharing workflows means users need to navigate complex workflows, understand technical implementation details, and handle data formats correctly
  </Card>

  <Card title="The Solution" icon="circle-check" color="#22c55e">
    Gumloop Interfaces creates simple interface designs, manages data processing behind the scenes, handles credentials seamlessly, and delivers results directly to users
  </Card>
</CardGroup>

## Creating an Interface

<video width="800" controls>
  <source src="https://storage.googleapis.com/agenthub-public/docs/interface3.mp4" type="video/mp4" />
</video>

<Steps>
  <Step title="Add Interface Node">
    In your workflow, click the "Add Interface" button on the top left panel. A new Interface node appears in your workflow.
  </Step>

  <Step title="Open Interface Editor">
    Hover over the Interface node and click 'Edit Interface' to access the configuration panel.
  </Step>

  <Step title="Configure Basic Details">
    Add your interface title and description to help users understand what the interface does.
  </Step>

  <Step title="Customize Visual Elements">
    **Choose an interface icon:**

    * Upload a custom image
    * Select an emoji

    **Add a header image:**

    * Generate with AI: Describe what you want and get unique images
    * Choose from galleries: Abstract, Nature, Gradients or Search on Unsplash
    * Upload your own
  </Step>
</Steps>

<Tip>Add detailed instructions in the AI image generation prompt to get the best output.</Tip>

## Understanding Interface Outputs

Different input types produce specific output formats in your workflow:

| Input Type  | Output in Workflow | Details                                                            |
| ----------- | ------------------ | ------------------------------------------------------------------ |
| Text Field  | Text string        | Any text input from single words to paragraphs                     |
| Number      | Numeric value      | Positive or negative numbers based on configuration                |
| Email       | Email string       | Validated email address format                                     |
| Date        | ISO datetime       | Standardized date format                                           |
| File Upload | File object        | Supports PDF, PNG, CSV, XLSX, DOCX, MP4, MP3 and more (Max: 200MB) |
| Dropdown    | Text string        | Value of the selected option                                       |
| Checkbox    | Boolean            | True when checked, False when unchecked                            |

<Note>When a field is optional and left empty, it passes an empty value to your workflow</Note>

## Interface Elements

<Tabs>
  <Tab title="Basic Inputs">
    <AccordionGroup>
      <Accordion title="Text Field" icon="font">
        **Purpose:** Collect written information

        **Features:**

        * Single or multi-line text entry
        * Set character limits for quality control
      </Accordion>

      <Accordion title="Number Input" icon="hashtag">
        **Purpose:** Collect numeric values

        **Features:**

        * Set minimum/maximum boundaries
        * Guide users with placeholder examples
      </Accordion>

      <Accordion title="Email Field" icon="envelope">
        **Purpose:** Collect valid email addresses

        **Features:**

        * Automatic format checking
        * Prevents invalid submissions
      </Accordion>

      <Accordion title="Date Picker" icon="calendar">
        **Purpose:** Collect dates consistently

        **Features:**

        * Visual calendar selection
        * Outputs dates in ISO format
      </Accordion>

      <Accordion title="File Upload" icon="upload">
        **Purpose:** Accept file submissions

        **Features:**

        * Handle multiple files
        * 200MB limit per file
        * Support for common file types like PDF, DOC/DOCX, XLS/XLSX, CSV, TXT, MP3, MP4, JPEG, PNG, etc.
      </Accordion>
    </AccordionGroup>
  </Tab>

  <Tab title="Interactive Elements">
    <AccordionGroup>
      <Accordion title="Select Dropdown" icon="list">
        **Purpose:** Present fixed choice options

        **Features:**

        * Easily add/remove choices
        * Set default selection

        **Returns:** Text of selected option
      </Accordion>

      <Accordion title="Checkbox" icon="square-check">
        **Purpose:** Yes/No selections

        **Features:**

        * Starts unchecked by default
        * Simple toggle interaction
      </Accordion>
    </AccordionGroup>
  </Tab>

  <Tab title="Structure">
    Organize your interface with:

    * **Headings:** Create clear sections
    * **Description Text:** Guide users with explanations
    * **Dividers:** Separate different parts visually

    <Tip>Drag and drop any element to reorder your interface layout</Tip>
  </Tab>
</Tabs>

## Input Configuration Options

Customize each input field with these settings to guide users effectively:

| Setting           | Description                      | Usage Example                                    |
| ----------------- | -------------------------------- | ------------------------------------------------ |
| Custom Field Name | The name shown above the input   | "Phone Number" instead of default "Text"         |
| Helper Text       | Guidance shown below the field   | "Include country code for international numbers" |
| Placeholder       | Example text in empty fields     | "+1 (555) 0123"                                  |
| Default Value     | Pre-filled starting value        | "United States" in a country dropdown            |
| Required/Optional | Whether the field must be filled | Mark email as required for contact information   |

## Access & Security

<video width="800" controls>
  <source src="https://storage.googleapis.com/agenthub-public/docs/gumloop_interfaces_access.mp4" type="video/mp4" />
</video>

### Interface Access & Sharing

<Steps>
  <Step title="Configure Access Settings">
    Access 'Edit Interface Access' to control who can use your interface.

    **General Access options:**

    * **Restricted** (default): Only explicitly shared users can access
    * **Organization**: All members of your organization can access
    * **Anyone with link**: Accessible via shared URL to anyone, including unauthenticated users
  </Step>

  <Step title="Share Your Interface">
    Share the interface link directly from the node, or add specific users by email from the Share dialog
  </Step>

  <Step title="Control Workflow Visibility">
    Toggle "Allow workflow access from interface" to add a 'View Workflow' button that lets users see the underlying workflow (requires appropriate workflow permissions)
  </Step>
</Steps>

<Warning>
  **Important:** Interface access permissions are **separate** from workflow access permissions. This means:

  * A user can have access to use an interface without having access to view or edit the underlying workflow
  * Interface access and workflow access need to be configured separately
  * You can share an interface widely while keeping the workflow private, or vice versa
  * Making a workflow public does **not** automatically make its interfaces public (and vice versa)
</Warning>

### Authentication & Credentials

<AccordionGroup>
  <Accordion title="User Authentication" icon="user-lock">
    Users must sign in to Gumloop to use interfaces. This ensures secure access and proper credential management.
  </Accordion>

  <Accordion title="Service Credentials" icon="key">
    **Critical:** When building workflows with service nodes (Gmail, Google Sheets, Slack, etc.):

    * The workflow will use the interface user's credentials, not the workflow creator's
    * Users will need valid credentials for any services accessed in the workflow
    * **Example:** If your workflow includes a `Gmail Sender` node:
      * The email will be sent from the interface user's Gmail account, not yours
      * The interface user will need to connect their Gmail account when using the interface

    Credentials connect directly to services and nothing is stored by Gumloop.
  </Accordion>
</AccordionGroup>

### Usage & Credits

<CardGroup cols={2}>
  <Card title="Credit Deduction" icon="coins">
    Credits are deducted from the interface user's account/organization, not the creator's account. If the user belongs to an organization, credits are deducted from the organization's balance.
  </Card>

  <Card title="Output Display" icon="display">
    Interfaces show the same output as the workflow. You can use an 'Output' node to output any data in the interface once the workflow has successfully ran. Interface output supports markdown formatting.
  </Card>
</CardGroup>

### Interface Management

* Users with workflow edit access can modify the interface
* Interfaces work with both triggered and non-triggered workflows
* Interface inputs connect directly to workflow inputs

## Real-World Examples

<AccordionGroup>
  <Accordion title="In-depth Business Researcher" icon="magnifying-glass-chart">
    Research and analyze companies comprehensively with structured inputs:

    **Interface Structure:**

    **Company Name** (Text field)

    * Helper text: "Enter the company's full legal name"
    * Example: "Stripe, Inc."

    **Research Focus** (Dropdown)

    * Options: Company Overview, Market Position, Key Products/Services, Leadership Team, Financial Performance, Recent News
    * Helper text: "Select areas to focus the research on"

    **Competitor Analysis?** (Checkbox)

    * Helper text: "Include analysis of top 3 competitors"

    **Output Format** (Dropdown)

    * Options: PDF Report, Google Doc, Markdown
    * Default: Google Doc

    **Output:** Detailed research report with sources cited
  </Accordion>

  <Accordion title="Content Tone Adjuster" icon="pen-to-square">
    Rewrite content to match specific tones and styles:

    **Interface Structure:**

    **Content URL** (Text field)

    * Helper text: "Paste the link to the article or blog you want to use"

    **Target Tone** (Dropdown)

    * Options: Professional & Formal, Casual & Friendly, Technical & Detailed, Simple & Clear, Persuasive & Sales
    * Helper text: "Select the desired tone for your content"

    **Industry** (Dropdown)

    * Options: Technology, Healthcare, Finance, Education, etc.
    * Helper text: "Select your industry for contextual accuracy"

    **Brand Voice Guidelines** (File upload, optional)

    * Helper text: "Upload your brand guidelines (PDF, DOC)"

    **Output:** Rewritten content in desired tone with original/new comparison
  </Accordion>

  <Accordion title="Social Media Content Calendar Generator" icon="calendar-days">
    Create a comprehensive content calendar with guided inputs:

    **Interface Structure:**

    **Brand/Company Name** (Text field)

    * Helper text: "Enter your brand or company name"

    **Target Platform** (Dropdown)

    * Options: LinkedIn, Twitter, Instagram, Facebook

    **Content Period** (Dropdown)

    * Options: 1 week, 2 weeks, 1 month
    * Default: 1 week

    **Key Topics/Themes** (Text field)

    * Helper text: "Enter topics, products, or themes to focus on"
    * Example: "Product launches, Industry tips, Customer success stories"

    **Output:** Detailed content calendar in spreadsheet format with post suggestions
  </Accordion>
</AccordionGroup>

## Summary

Gumloop Interfaces takes complex workflows and makes them simple to use. Your team doesn't need to be tech-savvy - they just fill out a clean, simple interface and the automation handles everything else.
