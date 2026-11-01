> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Notion Page Writer

This document outlines the functionality and characteristics of the Notion Page Writer node, which enables automated page creation and updating in Notion.

## Node Inputs

### Required Fields

* **Content**: Main text content for the page
* **Title**: Title for the page

### Optional Fields

* **Use Existing Notion Page**: Toggle between creating new or updating existing page
* **Select Database**: Choose database to create page in (required for new pages)

## Node Output

* **Notion Page Link**: URL to access the created or updated page

## Node Functionality

The Notion Page Writer node creates or modifies pages in Notion databases.

**Key features include**:

* New page creation
* Existing page updates
* Basic content formatting support
* Loop Mode for batch creation
* Secure authentication with Gumloop

## When To Use

The Notion Page Writer node is essential when you need to automate page creation or updates in Notion. Common use cases include:

* **Documentation Generation**: Create formatted documentation automatically
* **Content Publishing**: Write processed or generated content to Notion
* **Knowledge Base Building**: Automatically create reference pages
* **Note Creation**: Generate structured notes from other sources

**Some specific examples**:

* Creating meeting note templates with predefined structure
* Generating process documentation from existing content
* Building knowledge base articles from processed data
* Updating standard operating procedures with new content

## Important Considerations:

1. Requires Authentication with Notion - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Database and new page must be shared with Gumloop during authentication

In summary, the Notion Page Writer node streamlines the creation and updating of Notion pages, supporting both individual and batch operations for efficient content management.
