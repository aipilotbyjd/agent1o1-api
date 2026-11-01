> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Docs Writer

This document outlines the functionality and characteristics of the Google Docs Writer node, which enables automated document creation and editing in Google Docs.

## Node Inputs

### Document Settings

* **Title**: Name for the new document (required for new documents)
* **Content**: Text to be written to the document
* **Content Format**: Choose the format for your content
  * **Plain Text**: Unformatted text
  * **HTML**: Formatted content using HTML tags
  * **Markdown**: Formatted content using Markdown syntax

### Document Configuration

* **Use Existing Doc**: Toggle between creating a new document or editing an existing one
  * When enabled: Updates an existing Google Doc
  * When disabled: Creates a new Google Doc
* **Document Link/ID**: URL or document ID of the existing document (required if editing)
* **Folder**: Target Google Drive folder for new documents
  * When enabled as a configurable input, folder URL or ID can be used
* **Make Doc Public**: Option to make the document accessible to anyone with the link
  * When enabled: Sets sharing permissions to "Anyone with the link can view"
  * When disabled: Maintains default permissions (private to you)
  * **Note**: Valid Google Drive credentials must be present for this feature to work
* **Insert Content at Start of Document**: Controls content placement in existing documents
  * When enabled: Adds new content at the beginning of the document
  * When disabled: Appends new content to the end of the document

## Node Output

* **Doc Link**: URL to access the created or edited document
  * Can be used to send to stakeholders or connect to other nodes

## Content Formatting Support

### Important Limitations

* **Tables and images are not supported** in any content format
* Complex formatting may have inconsistent results

### Formatting Options

#### Plain Text

Basic text without formatting. Line breaks are preserved.

#### HTML

Supports basic HTML formatting:

* Headings (`<h1>`, `<h2>`, etc.)
* Text formatting (`<b>`, `<i>`, `<u>`, etc.)
* Lists (`<ul>`, `<ol>`, `<li>`)
* Paragraphs (`<p>`)

#### Markdown

Supports common Markdown syntax:

* Headings (`#`, `##`, etc.)
* Text formatting (`**bold**`, `*italic*`, etc.)
* Lists (`-`, `1.`, etc.)

### Formatting with AI

When using Markdown or HTML, consider using AI to format your content properly. Here's an example prompt you can use with the Ask AI node:

```text theme={"dark"}
Format the following content as clean, well-structured [Markdown/HTML] that can be rendered in Google Docs. 
Include appropriate headings, lists, and text formatting.
Focus on readability and professional presentation.
Do not include HTML backticks, code blocks, tables, or images as they are not supported.

Content to format:
{input}
```

## Common Use Cases

### 1. Automated Reporting

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart LR
      A["Google Sheets Reader"] --> B["Ask AI"] 
      B --> C["Google Docs Writer"]
  ```
</div>

Generate periodic reports (daily, weekly, monthly) with consistent formatting.

### 2. Documentation Management

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart LR
      A["File Reader"] --> B["Ask AI"] 
      B --> C["Google Docs Writer"]
  ```
</div>

Create customized documentation from templates or source files.

### 3. Knowledge Base Building

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart LR
      A["Website Scraper"] --> B["Ask AI"] 
      B --> C["Google Docs Writer"]
  ```
</div>

Continuously add new information to a centralized knowledge document.

### 4. Update Logs

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart LR
      A["Slack Message Reader"] --> B["Combine Text"] 
      B --> C["Google Docs Writer"]
  ```
</div>

Add new entries at the beginning of change logs or update records.

## Loop Mode Usage

When processing multiple items with Loop Mode:

* Output is a list of document links
* Creates or updates multiple documents in one operation
* Each item processes independently

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart LR
      A["Google Sheets Reader<br>(Multiple content items)"] --> B["Ask AI<br>(Format content, Loop Mode)"]
      B --> C["Google Docs Writer<br>(Loop Mode)"]
      C --> D["List of document links"]
  ```
</div>

## Important Considerations

1. **Authentication**: Requires Google account connection in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. **Permissions**:
   * You must have appropriate permissions to edit existing documents
3. **Content Limitations**:
   * Tables and images are not supported in any format
4. **Folder Access**:
   * When specifying a folder, you must have write access to it
   * When configured as an input, folder URL or ID can be passed from previous nodes
5. **Public Sharing**:
   * "Make Doc Public" sets view-only permissions and requires valid Google Drive credentials

## Troubleshooting

| Issue                       | Possible Solution                                            |
| --------------------------- | ------------------------------------------------------------ |
| Content formatting issues   | Try simplifying HTML/Markdown or switch to Plain Text format |
| Folder not found            | Use appropriate folder ID or check permissions               |
| Error when editing document | Verify document ID/link is correct and you have edit access  |

In summary, the Google Docs Writer node provides a powerful way to automate document creation and updates in Google Docs, with flexible formatting options and content placement control.
