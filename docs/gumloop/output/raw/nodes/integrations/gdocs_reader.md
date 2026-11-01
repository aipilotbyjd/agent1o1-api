> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Docs Reader

This document outlines the functionality and characteristics of the Google Docs Reader node, which enables automated content extraction from Google Docs.

## Node Inputs

### Document Selection (Choose one method)

* **Use Link or ID**: Option to use a document URL or ID
  * **Link Format**: `https://docs.google.com/document/d/[document-id]`
  * **ID Format**: Just the document ID from the URL

### Tab Configuration Options

* **Read All Tabs?**: Toggle to control tab reading behavior
  * When enabled (Yes): Reads content from all tabs in the document
  * When disabled (No): Reads content only from the selected tab
* **Tabs**: Dropdown to select specific tabs to read (only visible when "Read All Tabs?" is disabled)

## Node Output

* **Document Content**: Text content from the Google Doc
  * When reading all tabs: Content from all tabs combined
  * When reading a specific tab: Content from only the selected tab
* **Document Title**

## Node Functionality

The Google Docs Reader node extracts text content from Google Docs documents with flexible tab handling options.

**Key features include**:

* Tab-specific content extraction
* Loop Mode for processing multiple documents
* Complete text content extraction
* Secure authentication with Gumloop

## When To Use

The Google Docs Reader node is particularly valuable in scenarios requiring automated document processing. Common use cases include:

* **Content Migration**: Transfer document content to other platforms
* **Document Analysis**: Feed content into AI analysis tools
* **Tab-Specific Processing**: Work with specific sections of large documents
* **Documentation Management**: Automated documentation handling

### Tab Reading Examples

1. **Reading All Tabs**: Useful when you need the complete document content
   ```text theme={"dark"}
   Google Docs Reader (Read All Tabs: Yes) → Summarizer → Slack Message Sender
   ```
   *Perfect for creating summaries of entire documents*

2. **Reading Specific Tabs**: Ideal when you need to process distinct document sections
   ```text theme={"dark"}
   Google Docs Reader (Read All Tabs: No, Tab: "Financial Data") → Extract Data → Google Sheets Writer
   ```
   *Great for extracting structured data from specific document sections*

## Important Considerations

1. Requires Authentication with Google - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. You must have access to the document you want to read
3. Only extracts text content (no formatting or images)
4. Tab selection is only available when "Read All Tabs?" is set to No
5. Documents without tabs will simply return the complete content

## Loop Mode Pattern

```text theme={"dark"}
Input: List of document URLs
Process: Read content from each document
Output: List of document contents
```

In summary, the Google Docs Reader node provides reliable access to Google Docs content with flexible tab handling options, making it ideal for automated document processing and content extraction workflows that require either complete document content or tab-specific information.
