> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Microsoft OneLake File Writer

This document outlines the functionality and characteristics of the Microsoft OneLake File Writer node, which enables you to save files to Microsoft Fabric's OneLake data storage.

## Node Inputs

### Required Fields

* **File Content**: The content you want to save to OneLake (text, data, structured content)

### Required Parameters

* **Lakehouse URL**: The URL to your Microsoft Fabric Lakehouse
  * Format: `https://app.fabric.microsoft.com/groups/[workspace-id]/lakehouses/[lakehouse-name]`
  * Example: `https://app.fabric.microsoft.com/groups/12345abcd-ef67-89gh-ijkl/lakehouses/marketing-analytics`

* **Destination Folder**: Path within your Lakehouse where the file will be saved
  * Example: `reports/quarterly/q2` or `processed-data/customers`
  * Can include nested folders (folders will be created if they don't exist)

### Optional Parameters

* **File Name**: Name for the saved file (with extension)
  * Default: Uses a system-generated name if not specified
  * Example: `analysis-report.csv` or `customer-data.json`

* **Overwrite Existing**: Whether to replace files with the same name
  * Options: True (overwrite) or False (keep both)
  * Default: False

## Node Output

* **OneLake URL**: The URL to access the stored file
  * Can be used to share access to the file or for further processing in Microsoft Fabric

## Node Functionality

The Microsoft OneLake File Writer node allows you to write files directly to Microsoft Fabric's OneLake data lake storage. This integration enables seamless data flows between Gumloop automations and your organization's Microsoft Fabric environment.

**Key features include**:

* Direct connection to Microsoft Fabric OneLake storage
* Support for various file formats
* Automatic folder creation
* Integration with Microsoft's data ecosystem
* Secure authentication via Microsoft credentials

## When to Use

The Microsoft OneLake File Writer node is particularly valuable in scenarios requiring integration with Microsoft Fabric analytics tools. Common use cases include:

* **Data Pipeline Integration**: Save processed data for use in Microsoft Fabric's analytics tools
* **Report Generation**: Store automated reports in your organization's central data repository
* **Content Archive**: Preserve important AI-generated content in your corporate data lake
* **Analytics Preparation**: Prepare and structure data for Power BI and other Microsoft analytics tools

**Some specific examples**:

* Storing AI-processed customer feedback for later analysis
* Archiving auto-generated reports in your organization's data lake
* Saving data extraction results for team access through Microsoft Fabric
* Creating structured datasets for immediate use in Power BI dashboards

## Example Workflow: Customer Feedback Analysis

```text theme={"dark"}
Gmail Reader → Extract Data → Ask AI → Microsoft OneLake File Writer
```

This workflow:

1. Collects customer feedback emails
2. Extracts key information
3. Analyzes sentiment and topics with AI
4. Saves the structured analysis to your OneLake storage

**OneLake File Writer Configuration**:

* Lakehouse URL: Your team's analytics lakehouse URL
* Destination Folder: `customer-insights/feedback-analysis/weekly`
* File Name: `feedback-analysis-{date}.json`

## Loop Mode Pattern

When used in Loop Mode, the OneLake File Writer can process multiple content items, saving each as a separate file:

```text theme={"dark"}
Airtable Reader → Ask AI (Loop Mode) → OneLake File Writer (Loop Mode)
```

This pattern:

1. Reads multiple records from Airtable
2. Processes each with AI individually
3. Saves each result as a separate file in OneLake
4. Creates a directory of related but individual files

## Data Organization Best Practices

For optimal management of your OneLake storage:

1. **Hierarchical Folder Structure**:
   ```text theme={"dark"}
   /department/project/data-type/year/month/
   ```
   Example: `/marketing/campaign-analysis/social-metrics/2025/04/`

2. **Consistent File Naming**:

   * Include dates in ISO format (YYYY-MM-DD)
   * Add descriptive prefixes
   * Use consistent extensions

   Example: `twitter-sentiment-2025-04-12.json`

3. **Metadata Management**:
   * Consider including metadata in your files
   * Standardize metadata fields across files
   * Include source information, processing details, and timestamps

4. **Access Pattern Consideration**:
   * Organize by how data will be accessed and by whom
   * Group related data that will be analyzed together
   * Consider Power BI and other tool access patterns

## Authentication Requirements

1. **Microsoft Fabric Credentials**: Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. **Proper Permissions**: Ensure your authenticated account has:
   * Write access to the specified Lakehouse
   * Permissions to create folders if necessary
   * Appropriate data access rights within your organization

## Important Considerations

1. **URL Format**: Ensure your Lakehouse URL follows the correct format
2. **File Size Limits**: Be aware of any size limitations in your Microsoft Fabric environment
3. **File Format Compatibility**: Ensure the format is compatible with your intended Fabric tools
4. **Workspace Permissions**: Verify appropriate sharing settings in your Fabric workspace
5. **Authentication Required**: Set up Microsoft Fabric credentials in the [Connectors page](https://www.gumloop.com/personal/connectors)

## Troubleshooting

| Issue                 | Possible Cause                 | Solution                                              |
| --------------------- | ------------------------------ | ----------------------------------------------------- |
| Authentication Failed | Invalid or expired credentials | Refresh your Microsoft credentials in Gumloop         |
| Permission Denied     | Insufficient access rights     | Check your permissions in Microsoft Fabric            |
| Invalid URL           | Incorrect Lakehouse URL format | Verify the URL format in your Microsoft Fabric portal |
| Folder Not Found      | Mistyped destination path      | Check for typos in your folder path                   |

In summary, the Microsoft OneLake File Writer node creates a seamless bridge between your Gumloop automations and Microsoft Fabric's analytics ecosystem, enabling centralized storage of your workflow outputs for advanced analysis and sharing across your organization.
