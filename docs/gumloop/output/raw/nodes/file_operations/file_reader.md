> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# File Reader

This document explains the File Reader node, which extracts text from various file types.

## Node Inputs

### Required Fields

* **File Name**: Upload file or select existing file

  Supported formats:

  * PDF (.pdf)
  * Word (.doc, .docx)
  * Excel (.xls, .xlsx)
  * Text (.txt)
  * CSV (.csv)
  * HTML (.html)
  * JSON (.json)

### Optional Fields

* **Use Link**: Enable to read from URL instead of upload

## Node Output

* **File Contents**: Extracted text content

## Node Functionality

The File Reader node:

* Extracts text from files
* Handles multiple formats
* Preserves text formatting
* Works with URLs or uploads
* Supports batch processing

## Common Use Cases

1. **Document Processing**:

```text theme={"dark"}
Input: Contracts.pdf
Output: Text content
Use: Contract analysis
```

2. **Data Import**:

```text theme={"dark"}
Input: data.csv
Output: Comma-separated text
Use: Data preparation
```

3. **Web Content**:

```text theme={"dark"}
Input: webpage.html
Output: Page text
Use: Content analysis
```

In summary, the File Reader node helps convert various file types into usable text content for your workflow.
