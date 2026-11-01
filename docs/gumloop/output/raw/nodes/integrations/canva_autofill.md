> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Canva Autofill

This document explains the Canva Autofill node, which allows you to automatically populate Canva templates with your data.

> **Note**: Canva Autofill is only available for Canva Enterprise customers.

## Node Inputs

### Required Fields

* **Brand Template**: Select a Canva brand template from your connected account
* **Design Title**: Name for your newly created design
* **Credentials**: Your Canva account authentication information

### Show As Input

The node allows you to configure parameters as dynamic inputs. You can enable these in the "Configure Inputs" section:

* **Design Title**: String
  * The name of your new design
  * Example: "Q2 Sales Report"

## Node Output

* **Design URL**: Direct link to the completed design in Canva
* **Thumbnail URL**: URL to a preview image of the design
* **Design ID**: Unique identifier for the created design

## Node Functionality

The Canva Autofill node:

* Connects to your Canva Enterprise account
* Retrieves your brand templates
* Populates templates with data from your Gumloop workflow
* Creates new designs without manual editing
* Returns URLs for accessing the generated designs

## How It Works

### Template Data Fields

When you select a brand template, the node automatically detects all available data fields in that template. These fields will appear as inputs on the node that you can connect to outputs from previous nodes in your workflow.

Data fields in Canva templates can include:

* **Text fields**: Headings, paragraphs, labels, etc.
* **Image fields**: Background images, photos, logos, etc.
* **Chart data**: Values for graphs and visualizations

### Field Mapping

The node automatically maps your workflow data to the corresponding template fields:

1. **Text Fields**: Connect any text output from previous nodes
2. **Image Fields**: Connect image URLs or asset IDs
3. **Chart Data**: Connect structured data for populating charts

## Common Use Cases

### 1. Automated Marketing Materials

```text theme={"dark"}
Google Sheets Reader (campaign data) → Ask AI (generate copy) → Canva Autofill → Slack Message Sender
```

This workflow reads campaign data, generates optimized copy, creates marketing visuals, and shares them with your team.

### 2. Personalized Customer Communications

```text theme={"dark"}
Airtable Reader (customer data) → Extract Data → Canva Autofill → Gmail Sender
```

This workflow pulls customer information, extracts relevant data points, creates personalized visual content, and delivers it via email.

### 3. Social Media Content Creation

```text theme={"dark"}
RSS Feed Reader → Summarizer → Canva Autofill → Tweet
```

This workflow monitors news sources, creates concise summaries, generates branded graphics, and posts them to social media.

### 4. Data-Driven Reports

```text theme={"dark"}
Google Analytics Reader → Extract Data → Canva Autofill → Google Drive File Writer
```

This workflow extracts analytics data, processes key metrics, generates visual reports, and saves them to your shared drive.

## Setting Up Canva Templates

To use this node effectively, you first need to set up autofillable templates in Canva:

1. Create a design in Canva
2. Add elements you want to be dynamic (text, images, charts)
3. Open the Data Autofill app in Canva
4. Select elements and define them as data fields
5. Publish as a Brand Template

For detailed instructions on setting up templates, see [Canva's official documentation](https://www.canva.com/help/data-autofill/).

<div align="center">
  ```mermaid theme={"dark"}
  flowchart LR
      A["Create Template\nin Canva"] --> B["Define Data Fields\nusing Data Autofill App"]
      B --> C["Publish as\nBrand Template"]
      C --> D["Use Template\nin Gumloop"]
      D --> E["Connect Workflow Data\nto Template Fields"]
      E --> F["Generate\nFinal Design"]
  ```
</div>

## Authentication

To connect Gumloop to your Canva account:

1. Go to Gumloop's [Connectors page](https://www.gumloop.com/personal/connectors)
2. Click "Add New Credential"
3. Select "Canva" from the list
4. Follow the OAuth authentication process
5. Once connected, select your Canva credentials when configuring the node

## Important Considerations

1. **Enterprise Requirement**: Canva Autofill is only available for Canva Enterprise customers.
2. **Template Access**: You can only use brand templates that you have access to in your Canva account.
3. **Data Types**: Ensure your data matches the expected format for each template field (text for text fields, image URLs for image fields, etc.).
4. **Rate Limits**: The Canva API has rate limits that may affect high-volume automation.
5. **Field Names**: The field names shown in Gumloop match exactly what you defined in your Canva template.

## Technical Details

The Canva Autofill node uses Canva's official API to interact with your templates and generate designs. Behind the scenes, it:

1. Authenticates with your Canva account
2. Queries your available brand templates
3. Retrieves the template's dataset structure
4. Maps your input data to the template fields
5. Creates an autofill job in Canva
6. Monitors the job until completion
7. Returns the URLs and IDs of the completed design

For more technical information about the Canva API, see [Canva's Developer Documentation](https://www.canva.dev/docs/connect/autofill-guide/).

### Getting Help

If you encounter issues with the Canva Autofill node:

1. Check that your Canva Enterprise subscription is active
2. Verify that your templates are properly set up with data fields
3. Ensure your input data matches the expected format for each field
4. If problems persist, [reach out to us](https://portal.usepylon.com/gumloop/forms/help)

## Learn More

* [Canva Data Autofill Help Page](https://www.canva.com/help/data-autofill/)
* [Canva API Autofill Guide](https://www.canva.dev/docs/connect/autofill-guide/)
* [Canva Enterprise Features](https://www.canva.com/enterprise/)
