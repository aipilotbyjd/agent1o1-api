> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Drive File Writer

This document outlines the functionality and characteristics of the Google Drive File Writer node, which enables automated file uploads to Google Drive.

## Node Inputs

### File Selection (Choose one method)

* **Use Link**: Option to upload a file from a URL
  * **Link**: URL of the file to upload (required if using link method)
* **Input File**: Receive file from previous node (when enabled in Configure Inputs)

### Configuration Options

* **Folder**: Target Google Drive folder for upload (optional)
* **File Name**: Name for the uploaded file
  * Leave blank to use original file name
  * Add extension if not included in original file name (e.g., .pdf, .csv)

### Configure Inputs

You can make these parameters dynamic by enabling them in "Configure Inputs":

* **file**: File to upload (from previous node output)
* **file\_name**: Name for the uploaded file
* **folder**: Google Drive folder ID or link

## Dynamic Drive Folders

This node supports specifying destination folders dynamically:

1. Enable the "Folder" input in "Configure Inputs"
2. Connect to a node that outputs either:
   * A folder link: `https://drive.google.com/drive/folders/FOLDER_ID`
   * A folder ID: `FOLDER_ID`
3. The file will be saved to the specified folder

```text theme={"dark"}
Valid folder link: https://drive.google.com/drive/folders/1xGA0zvylsPs2t8FoC5DP2YQgO3tptIxI
Valid folder ID: 1xGA0zvylsPs2t8FoC5DP2YQgO3tptIxI
```

> Note: The folder input can't be the folder name, it must either be the Drive folder URL or ID.

## Node Output

* **Drive URL**: The Google Drive URL of the uploaded file

## Node Functionality

The Google Drive File Writer node automates file uploads to Google Drive.

**Key features include**:

* Support for multiple file sources (URL, or node input)
* Flexible file naming options
* Dynamic folder destination selection
* Batch mode for multiple file uploads via Loop Mode
* Secure authentication with Gumloop

## Example Workflows

### 1. Automated Report Archiving

```text theme={"dark"}
Generate File → Google Drive File Writer
Setup:
- Input File: Generated PDF report
- File Name: "Report-{timestamp}.pdf"
- Folder: Reports folder
Purpose: Automatically archive generated reports with timestamps
```

### 2. Website Image Collection

```text theme={"dark"}
Website Scraper → Extract Data → Google Drive File Writer
Setup:
- Use Link: Yes
- Link: Image URLs from website
- Loop Mode: Enabled to process multiple images
- Dynamic folder based on image category
Purpose: Save images from websites into categorized folders
```

### 3. File Type Organization

```text theme={"dark"}
Zip File Reader → Categorizer → If-Else → Google Drive File Writer
Setup:
- Dynamic folder selection based on file type categorization
Purpose: Automatically sort uploaded files by their content type
```

## Loop Mode Pattern

When using Loop Mode, the node processes a list of files or links:

* Input a list of file links
* Optionally input a list of file names (must match the size of the file list)
* Optionally input a list of folder destinations
* The node uploads each file to its corresponding destination

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart LR
      A["Extract Image URLs\n(List of URLs)"] --> B["Google Drive File Writer\n(Loop Mode)"]
      C["Generate File Names\n(List of names)"] --> B
      D["Select Folders\n(List of folders)"] --> B
      B --> E["Multiple files\nuploaded to Drive"]
  ```
</div>

## Important Considerations

1. Requires Authentication with Google - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. In Loop Mode, ensure list sizes match to avoid errors

In summary, the Google Drive File Writer node provides a streamlined way to automate file uploads to Google Drive, supporting multiple file sources with flexible naming and organization options for efficient file management workflows.
