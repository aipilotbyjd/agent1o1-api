> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Slides Reader

This document outlines the functionality and characteristics of the Google Slides Reader node, which enables automated data extraction from Google Slides presentations.

## Node Inputs

### Presentation Selection

* **Presentation Link**: The URL of your Google Slides presentation
  * Example: "[https://docs.google.com/presentation/d/1abc123def456/edit](https://docs.google.com/presentation/d/1abc123def456/edit)"

### Configuration Options

* **Slide Information**: Specify which slides to extract from the presentation
  * Format: Numbers, ranges, or combinations (e.g., "1,3,5-8")
  * Example: "2-5,7,10-12" to extract slides 2,3,4,5,7,10,11,12
  * Leave blank to extract all slides

## Node Output

The Google Slides Reader node produces the following outputs, all in list format (one item per slide):

* **Slide Contents**: List of text content extracted from each slide
* **Slide IDs**: List of unique identifiers for each slide
* **Slide Numbers**: List of numerical positions of each slide in the presentation
* **Slide Thumbnails**: List of preview images of each slide
* **Speaker Notes**: List of speaker notes attached to slides
* **Image URLs**: List of links to images used in the presentation

> **Important**: All outputs are in list format, even if you only extract a single slide.

## Node Functionality

The Google Slides Reader node provides automated access to Google Slides presentation content with flexible filtering capabilities.

**Key features include**:

* Text extraction from slides
* Image URL retrieval
* Speaker notes access
* Selective slide processing
* Secure authentication with Gumloop

## When To Use

The Google Slides Reader node is particularly valuable in scenarios requiring automated extraction and processing of presentation content. Common use cases include:

* **Content Repurposing**: Transform presentations into other formats
* **Analysis**: Extract and analyze presentation content
* **Training Material Processing**: Process educational slide decks
* **Marketing Content Management**: Extract visuals and messaging from brand presentations
* **Knowledge Management**: Index and categorize internal presentations

**Some specific examples**:

* Generating blog posts from presentation content
* Extracting key points for meeting summaries
* Creating transcripts from speaker notes
* Analyzing images in presentations

## Example Workflows

### 1. Presentation to Blog Post Converter

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart LR
      A["Google Slides Reader"] --> |"Slide Contents<br/>Speaker Notes"| B["Join List Items"]
      B --> C["Ask AI<br/>(Blog Generation)"]
      C --> D["Ghost Blog Writer"]
  ```
</div>

**Setup:**

* Extract all slides from the presentation
* Use Join List Items to combine all slide content and speaker notes
* Use Ask AI to transform the presentation content into a well-structured blog post
* Publish the content with Ghost Blog Writer

**Purpose:** Repurpose presentations as blog content without manual reformatting

### 2. Presentation Image Analysis

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart LR
      A["Google Slides Reader"] --> B["Image URLs"]
      
      subgraph C["Error Shield"]
          B --> D["Analyze Image"]
      end
      
      C --> E["Extract Data"]
  ```
</div>

**Setup:**

* Use Google Slides Reader to get image URLs from slides
* Wrap the Analyze Image node with Error Shield
* Connect Image URLs output to the Error Shield
* Use Extract Data to structure the image analysis results

**Why Error Shield is necessary:**
Not all slides contain images, which would cause the Analyze Image node to fail when processing empty image URLs. The Error Shield prevents these failures from stopping your entire workflow, allowing successful analyses to continue while safely handling slides without images.

**Purpose:** Extract and analyze data from charts, diagrams, and other visuals in presentations

### 3. Training Material Extractor

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart LR
      A["Google Slides Reader"] --> B["Speaker Notes"]
      B --> C["Summarizer"]
      C --> D["Generate File"]
  ```
</div>

**Setup:**

* Extract Speaker Notes from training presentations
* Use Summarizer to condense the key learning points
* Generate PDF or text files with the training summaries

**Purpose:** Convert slide-based training into concise reference materials

## Processing Multiple Presentations

The Google Slides Reader node itself doesn't directly support Loop Mode. However, you can still process multiple presentations using a subflow approach:

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart LR
      A["Google Sheets Reader<br/>(URLs)"] --> B["Subflow<br/>(Loop Mode ON)"]
      
      subgraph C["Inside Subflow"]
      D["Input"] --> E["Google Slides Reader"] --> F["Output"]
      end
  ```
</div>

**Steps to implement:**

1. **Create a simple subflow:**
   * Add an Input node to receive a URL
   * Add the Google Slides Reader node and connect it to the input
   * Add an Output node to return the slide data
   * Save this subflow

2. **In your main workflow:**
   * Add a node that outputs presentation URLs (like Google Sheets Reader)
   * Add your saved subflow
   * Enable Loop Mode on the subflow node
   * The subflow will now process each URL individually

This approach lets you process multiple presentations in sequence while maintaining proper data types in your workflow.

## Important Considerations

1. **Authentication Required**: You must authenticate with Google - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)

2. **Permissions Matter**: You must have access to the presentations you're trying to read

3. **Image Processing**:
   * Image URLs can be further processed using the [Analyze Image node](https://docs.gumloop.com/nodes/using_ai/analyze_image) for content analysis
   * **Tip**: When passing Image URLs to the Analyze Image node, use a subflow or Error Shield node because not all slides may contain images, which could cause errors

## Example Implementation: Sales Presentation Analysis

This example demonstrates how to extract and analyze content from sales presentations:

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart TD
      A["Google Slides Reader"] --> |"Slide Contents<br/>Speaker Notes"| B["Combine Text<br/>(Format with labels)"]
      B --> C["Join List Items<br/>(Merge all slides)"]
      C --> D["Extract Data"]
      D --> F["Airtable Writer"]
  ```
</div>

1. **Configuration**:
   * Presentation Link: Your sales deck URL
   * Slide Information: "5-15" (focusing on the main content slides)

2. **Processing Workflow**:
   ```text theme={"dark"}
   Google Slides Reader → Combine Text → Join List Items → Extract Data → Airtable Writer
   ```

3. **Data Processing Steps**:
   * Use Combine Text to format each slide with proper labels (e.g., "Slide 3 - Product Features: {slide_content}")
   * Use Join List Items to merge all formatted slides into one comprehensive text block
   * Use Extract Data to pull out structured information from the combined content
   * Store results in Airtable for team reference

4. **Benefits of This Approach**:
   * Preserves slide context with proper labeling
   * Allows AI to see relationships between slides
   * Enables more accurate data extraction by providing complete context
   * Creates a structured database of presentation content

This workflow automatically extracts valuable information from sales presentations and organizes it for better team access and analysis.

In summary, the Google Slides Reader node provides powerful capabilities for extracting and utilizing content from Google Slides presentations, enabling you to repurpose and analyze presentation content in your automation workflows.
