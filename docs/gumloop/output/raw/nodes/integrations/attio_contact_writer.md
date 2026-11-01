> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Attio Contact Writer

The Attio Contact Writer node enables automated creation of new contact records in your Attio CRM. This integration allows you to create contacts through automated workflows.

## Node Configuration

### Input Fields

Input fields correspond to your Attio CRM contact configuration. Common examples include:

* Person Name
* Person Email Address
* Company
* Job Title
* Phone
* Social Media Links

Additional fields will match your custom Attio CRM configuration.

## Example Use Cases

### 1. Lead Generation Pipeline

```plaintext theme={"dark"}
LinkedIn Profile Scraper → Extract Data → Attio Contact Writer
```

This workflow:

1. Scrapes contact information from LinkedIn
2. Uses AI to structure the data
3. Creates new contact records in Attio

### 2. Form Submission Processing

```plaintext theme={"dark"}
Typeform Submission Reader → Ask AI → Extract Data → Attio Contact Writer
```

This workflow:

1. Collects form submission data
2. Processes and validates information
3. Creates new contacts in Attio

### 3. Event Attendee Management

```plaintext theme={"dark"}
CSV Reader → Enrich Contact Information → Attio Contact Writer
```

This workflow:

1. Reads event registration data
2. Enriches contact information
3. Creates contact records for new attendees

## Setup Requirements

* Attio Authentication: Configure via [Connectors page](https://www.gumloop.com/personal/connectors)

## Example Workflow: Automated Lead Capture

```plaintext theme={"dark"}
LinkedIn Profile Scraper → Enrich Contact Information or Perplexity Search → Extract Data → Attio Contact Writer  
```

This workflow automates lead capture:

1. **Data Collection**
   * Scrapes LinkedIn profiles
   * Gathers professional information

2. **Data Enrichment**
   * Enriches with additional contact data
   * Verifies email addresses

3. **AI Processing**
   * Formats contact information
   * Generates personalized notes

4. **Contact Creation**
   * Creates new contact records
   * Sets all available fields

## Integration Tips

1. **With AI Nodes**
   * Use Extract Data to structure information
   * Use Categorizer node to label the contact appropriately
   * Use Perplexity Search to enrich contact information

2. **With Web Scraping**
   * Gather contact information from websites
   * Extract social media profiles
   * Collect professional background

3. **With Database Nodes**
   (eg. Airtable Reader, Monday.com Reader, Notion Database Reader)
   * Import contacts from other platforms
   * Migrate contact databases
   * Create contact records from spreadsheets

Note: This node creates new contacts only and does not update existing contacts in Attio.
