> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Drive File Reader

This document outlines the functionality and characteristics of the Google Drive File Reader node, which enables automated retrieval of files from Google Drive.

## Node Inputs

### File Selection (Choose one method)

* **Select File**: Choose a file directly from Google Drive
* **Use Link**: Option to use a direct Google Drive URL

## Node Output

* **File**: The file object that can be:
  * Passed directly to file operation nodes (PDF Reader, File Reader, etc.)
  * Sent to communication nodes as attachments (Slack, Gmail, etc.)
  * Used with the AI `Analyze Image` and `Analyze Video` nodes for image/video files

## Working with File Outputs

The node's output can be connected directly to various other nodes:

### File Operations

* PDF Reader: Process PDF files directly
* File Reader: Extract content from text files

### Communication

* Slack Message Sender: Share files in Slack channels
* Gmail Sender: Send files as email attachments
* Discord Message Sender: Share files in Discord

### AI Processing

Note: For text-based AI processing, files must first be processed by appropriate reader nodes:

* Text files → [File Reader](https://docs.gumloop.com/nodes/file_operations/file_reader)
* PDFs → [PDF Reader](https://docs.gumloop.com/nodes/pdf/pdf_reader)
* Google Docs → [Google Docs Reader](https://docs.gumloop.com/nodes/integrations/gdocs_reader)
* Google Sheets → [Google Sheets Reader](https://docs.gumloop.com/nodes/integrations/gsheets_reader)

Only [Analyze Image](https://docs.gumloop.com/nodes/using_ai/analyze_image) and [Analyze Video](https://docs.gumloop.com/nodes/using_ai/analyze_video) nodes can process the relevant media file objects directly.

## Node Functionality

The Google Drive File Reader node provides automated access to files stored in Google Drive.

**Key features include**:

* Support for multiple file formats
* Loop mode to process multiple files
* Direct file selection or URL input
* Secure authentication with Gumloop

## Example Workflows

### 1. Document Analysis Pipeline

```text theme={"dark"}
Google Drive File Reader 
→ File Reader/PDF Reader/Google Docs Reader 
→ Ask AI 
→ Notion Page Writer

Purpose: Analyze and summarize document contents

Note: File content must be extracted before AI processing
```

### 2. File Distribution

```text theme={"dark"}
Google Drive File Reader 
→ Slack Message Sender

Purpose: Share documents with team members
```

### 3. Data Analysis

```text theme={"dark"}
Google Drive File Reader 
→ File Reader
→ Extract Data 
→ Airtable Writer

Purpose: Process and store data from excel files
```

### 4. Image Analysis

```text theme={"dark"}
Google Drive File Reader 
→ Analyze Image 
→ Categorizer

Purpose: Process and categorize images
```

## When to Use

The Google Drive File Reader node is particularly valuable in scenarios requiring processing of files from Google Drive. Common use cases include:

* **Document Analysis**: Extract and analyze text from essays or research papers
* **Invoice Processing**: Process invoices stored as PDFs to extract payment information
* **Report Compilation**: Access financial reports for analysis
* **Dataset Processing**: Extract data from spreadsheets containing customer feedback
* **Media Processing**: Analyze images or videos stored in Drive

**Some specific examples**:

* Reading financial statements to extract spending patterns
* Processing student assignments
* Extracting product information from specification sheets
* Analyzing system performance logs
* Processing images for content moderation

## Important Considerations

1. Requires Authentication with Google - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. You must have access to the file
3. Text-based AI nodes (eg. Ask AI, Extract Data, Categorizer, etc) require content extraction first using the file operation nodes.

In summary, the Google Drive File Reader node simplifies access to files in Google Drive, providing a file object that can be seamlessly integrated with other nodes for comprehensive file processing workflows.
