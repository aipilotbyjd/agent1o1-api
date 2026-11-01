> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Drive Folder Creator

This document outlines the functionality and characteristics of the Google Drive Folder Creator node, which enables you to create new folders in Google Drive directly from your Gumloop workflow.

## Node Inputs

### Required Fields

* **Folder Name**: The name you want to give your new folder

### Optional Fields

* **Parent Folder**: Where to create the folder
  * By default, uses a folder picker UI to select from your Drive
  * If left empty, creates folder in your Drive root

### Configure Inputs

You can make these parameters dynamic by enabling them in "Configure Inputs":

* **Folder Name**: Name for the new folder
* **Parent Folder**: Google Drive folder ID or link where the new folder will be created

## Dynamic Parent Folders

This node supports specifying parent folders dynamically:

1. Enable the "Parent Folder" input in "Configure Inputs"
2. Connect to a node that outputs either:
   * A folder link: `https://drive.google.com/drive/folders/FOLDER_ID`
   * A folder ID: `FOLDER_ID`
3. The new folder will be created inside the specified parent folder

```text theme={"dark"}
Valid parent folder link: https://drive.google.com/drive/folders/1xGA0zvylsPs2t8FoC5DP2YQgO3tptIxI
Valid parent folder ID: 1xGA0zvylsPs2t8FoC5DP2YQgO3tptIxI
```

> Note: The parent folder input can't be the folder name, it must either be the Drive folder URL or ID. By default, parent folder selection uses a folder picker UI unless configured as a dynamic input.

## Node Output

* **Folder URL**: The complete URL of the newly created folder

## Node Functionality

The Google Drive Folder Creator node creates new folders in Google Drive, with options for naming and placement.

**Key features include**:

* Simple folder creation with custom naming
* Ability to nest folders within existing structures
* Dynamic folder naming and placement
* Batch folder creation via Loop Mode
* Secure authentication with Gumloop

## Business Use Cases

### 1. Client Onboarding Automation

```text theme={"dark"}
Airtable Reader (New Clients) → Google Drive Folder Creator → Gmail Sender
Setup:
- Folder Name: "{client_name} - {project_type}"
- Parent Folder: Company's Client Projects folder
- Loop Mode: Enabled to create folders for each new client
Purpose: Automatically create organized client folders when new accounts are added to your CRM
```

### 2. Document Compliance Management

```text theme={"dark"}
Google Sheets Reader (Departments) → Google Drive Folder Creator → Google Docs Writer
Setup:
- Folder Name: "Compliance Docs - {department_name} - Q{quarter}"
- Nested structure for regulatory documentation
Purpose: Create quarterly compliance document repositories for each department
```

### 3. Sales Pipeline Document Organization

```text theme={"dark"}
HubSpot Contact Reader → Categorizer (Deal Stage) → Google Drive Folder Creator
Setup:
- Dynamic folder names based on deal stages
- Create hierarchical folder structure by customer segment
Purpose: Automatically organize sales materials based on pipeline stage and customer type
```

## Loop Mode Pattern

When using Loop Mode, the node creates multiple folders from a list - ideal for batch processing business data:

* Input a list of folder names (e.g., new client accounts, projects, departments)
* Optionally input a list of parent folder locations
* The node creates each folder in its corresponding parent location

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart LR
      A["CRM Data\n(New Client List)"] --> B["Google Drive Folder Creator\n(Loop Mode)"]
      C["Department-Specific\nParent Folders"] --> B
      B --> D["Organized client folders\nby department"]
  ```
</div>

## Important Considerations

1. Requires Authentication with Google - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Parent folders must exist prior to running the workflow
3. You must have permission to create folders in the specified parent location

In summary, the Google Drive Folder Creator node provides an easy way to create and organize folder structures in Google Drive, supporting both simple and complex organizational hierarchies for efficient business document management and workflow automation.
