> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Confluence Page Reader

This document outlines the functionality and usage of the Confluence Page Reader node, which enables automated extraction of content from Confluence pages.

## Node Inputs

* **Page URL**: The URL of the Confluence page to read
  * Format: `https://your-domain.atlassian.net/wiki/spaces/SPACE/pages/123456/Page+Title`
  * Must be a valid Confluence page URL
  * The page must be accessible with your credentials

## Node Output

* **Page Content**: The full text content of the Confluence page.

## Node Functionality

The Confluence Page Reader node extracts content from specified Confluence pages, making it available for further processing in your workflow.

**Key features include**:

* Full page content extraction
* Support for formatted content
* Loop Mode for batch processing
* Secure authentication

## When to Use

The Confluence Page Reader node is particularly valuable when you need to:

* **Documentation Processing**: Extract and analyze technical documentation
* **Knowledge Base Migration**: Move content to other platforms
* **Content Analysis**: Analyze documentation for completeness or accuracy
* **Automated Updates**: Keep other systems in sync with Confluence content

## Example Workflows

### 1. Product Requirements Tracking

```text theme={"dark"}
Confluence Page Reader (PRD Pages) → Ask AI → Airtable Writer
Purpose: 
- Track feature requirements across multiple product lines
- Identify dependencies between features
- Monitor requirement changes over time
- Generate status reports for stakeholders
```

### 2. Customer Support Knowledge Base Maintenance

```text theme={"dark"}
Confluence Page Reader (Support Docs) → AI Categorizer → Slack Message Sender
Purpose:
- Flag outdated product information
- Identify gaps in support documentation
- Alert support team about critical updates
- Ensure consistent customer support responses
```

### 3. Compliance Documentation Management

```text theme={"dark"}
Confluence Page Reader (Policy Pages) → Ask AI → Email Sender
Purpose:
- Monitor policy updates and changes
- Ensure regulatory compliance
- Alert stakeholders about policy updates
- Track policy review deadlines
```

### 4. Technical Documentation Synchronization

```text theme={"dark"}
Confluence Page Reader (API Docs) → Ask AI → Jira Issue Creator
Purpose:
- Keep API documentation in sync with code
- Create tickets for outdated documentation
- Track documentation coverage
- Coordinate updates across development teams
```

## Common Use Cases

1. **Knowledge Base Management**
   * Extract documentation for analysis
   * Update external knowledge bases
   * Track documentation changes
   * Generate documentation reports

2. **Content Migration**
   * Move content to new platforms
   * Create backups
   * Synchronize documentation
   * Convert to different formats

3. **Documentation Analysis**
   * Check for outdated information
   * Analyze documentation coverage
   * Verify technical accuracy
   * Ensure consistency

4. **Team Communication**
   * Share documentation updates
   * Create documentation summaries
   * Generate team reports

## Setup and Authentication

* Authorize with Confluence on the [Connectors page](https://www.gumloop.com/personal/connectors)

## Working with Content

### Content Processing Tips

1. Use 'Ask AI' node to clean or format extracted content
2. Consider chunking large pages using the Chunk Text node before AI processing
3. Use Find and Replace node to standardize formatting

### Example: Processing Technical Documentation

```text theme={"dark"}
Input URL: https://your-company.atlassian.net/wiki/spaces/TECH/pages/123456/API+Documentation

Workflow:
1. Confluence Page Reader extracts content
2. Ask AI analyzes documentation completeness
3. Results stored in Notion or Airtable
```

## Related Nodes

* [Text Formatter](https://docs.gumloop.com/nodes/text_manipulation/text_formatter): For cleaning, formatting or truncating content
* [Chunk Text](https://docs.gumloop.com/nodes/text_manipulation/chunk_text): For breaking large content into smaller pieces
* [Ask AI](https://docs.gumloop.com/nodes/using_ai/ask_ai): For analyzing documentation (use with File Reader)
* [Extract Data](https://docs.gumloop.com/nodes/using_ai/extract_data): For pulling specific information

## Troubleshooting

1. **"Page not found" error**
   * Verify URL format
   * Check page permissions
   * Confirm credentials are correct

2. **"Authentication failed" error**
   * Verify Confluence credentials
   * Clear cookies, refresh & try again

In summary, the Confluence Page Reader node is a powerful tool for automating documentation processes, enabling content migration, and facilitating documentation analysis. When combined with other nodes, it creates robust workflows for managing and processing Confluence content effectively.
