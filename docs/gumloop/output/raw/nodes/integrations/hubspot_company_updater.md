> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# HubSpot Company Updater

The HubSpot Company Updater node allows you to automatically update company information in your HubSpot CRM. Whether you need to update a single company or process bulk updates, this node streamlines the process of maintaining accurate company data.

## What Does It Do?

Think of this node as your company information manager in HubSpot. Just like updating a contact card, you can modify various company details such as:

* Company name
* Website URL
* Industry
* Number of employees
* Annual revenue
* And many more custom properties

## Basic Configuration

#### Required Fields

* **Company Name**: The unique identifier for the company you want to update
  * Example: "Gumloop"

> Note: The company name must exist in your HubSpot CRM

#### Available Parameters to Update

> These input fields are based on your Hubspot configuration

**Example:**

* **Company Domain Name**: The company's domain
  * Example: "gumloop.com"
* **Create Date**: Date the record was created/updated
* **First Contact Create Date**: When the first contact was created
* **Lifecycle Stage**: Current stage in your business process
  * Example: "Customer"

## Real-World Automation Examples

### 1. AI-Powered Company Research & Enrichment

**Purpose:**\
Automatically research companies and update their HubSpot records with AI-enriched data

**Workflow:**

```text theme={"dark"}
HubSpot Company Reader -> Website Scraper -> Extract Data (AI) -> HubSpot Company Updater
```

* **HubSpot Company Reader**
  * Fetches companies with "Needs Research" status
* **Website Scraper**
  * Scrapes company website content
* **Extract Data (AI)**
  * Analyzes collected data
  * Structures company insights
* **HubSpot Company Updater**
  * Updates with enriched information:
    * Industry classification
    * Employee count range
    * Company description
    * Target market

> This automation helps maintain accurate company profiles with minimal manual intervention

### 2. Lead Qualification Pipeline

**Purpose:**\
Automatically qualify and update company records based on engagement and AI analysis

**Workflow:**

```text theme={"dark"}
HubSpot Engagement Reader ─┐
HubSpot Deal Reader ───────┼─> Extract Data (AI) -> Scorer (AI) -> If/Else -> HubSpot Company Updater      
```

* **HubSpot Engagement Reader & Deal Reader**
  * Track company interactions
  * Monitor deal progression
* **Extract Data (AI) & Scorer (AI)**
  * Analyze patterns
  * Calculate lead scores based on:
    * Engagement frequency
    * Deal potential
    * Company size
* **If/Else & Company Updater**
  * Route based on score
  * Update records with:
    * Lead score
    * Qualification status
    * Priority level
    * Next action date

### 3. Multi-Platform Company Data Sync

**Purpose:**\
Keep company information synchronized across HubSpot and other platforms

**Workflow:**

```text theme={"dark"}
HubSpot Company Reader ─┐
LinkedIn Scrape ┼─> Custom Filter Node -> HubSpot Company Updater
Salesforce Reader ──────┘
```

* **Data Collection Nodes**
  * HubSpot Company Reader: Fetches existing data
  * LinkedIn Scrape: Scrape current up to date data
  * Salesforce Reader: Gets CRM data
* **Processing & Update**
  * [Custom Filter Node](https://docs.gumloop.com/nodes/custom_node_details): Identifies significant changes
  * HubSpot Company Updater: Syncs changes across platforms
    * Company size
    * Industry updates
    * Latest news
    * Key personnel

**Loop Mode:** Enabled to process multiple companies

## Best Practices

1. **Verify Company Names**
   * Double-check company names before updates
   * HubSpot matches companies based on exact name matches
   * Ensure the company exists in your HubSpot CRM

2. **Batch Processing**
   * Enable Loop Mode for updating multiple companies
   * Prepare your input data in a list (array) format
   * Test with a single company first before batch updates

## Need Help?

* Need help? [Reach out to us](https://portal.usepylon.com/gumloop/forms/help)

## Important Considerations

1. Requires Authentication with HubSpot - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Properties must exist in HubSpot
3. All inputs are of list format when Loop mode is enabled
