> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Analyze Image

This document explains the Analyze Image node, which uses AI vision to extract information and insights from images.

## Node Inputs

### Required Fields

* **Image File**: Upload image or PDF (JPG, PNG, GIF, WEBP or PDF)
* **Prompt**: Question or instruction for analysis. Be detailed here for accurate output

### Optional Fields

* **Use Link**: Enable to use direct image URLs
  * Only supports publicly accessible media links (e.g., [https://example.com/image.jpg](https://example.com/image.jpg))
  * Does not support Google Drive, Dropbox, or other file-sharing links
  * URL must point directly to the image file
* **Temperature**: Controls analysis creativity (0-1)
  * 0: More focused, consistent
  * 1: More creative, varied
* **Cache Response**: Save responses for reuse

### Show As Input

The node allows you to configure certain parameters as dynamic inputs. You can enable these in the "Configure Inputs" section:

* **Use Link**: Boolean
  * true/false to use image URL instead of file upload
  * When enabled, allows input of publicly accessible image URLs
  * Remember: Only direct media links are supported

* **Prompt**: String
  * The specific question or instruction for analyzing the image
  * Example: "Describe the main objects in this image"

* **image\_model\_preference**: String
  * Name of the AI model to use for image analysis
  * Accepted values: "GPT-5.5", "GPT-5.4", "Claude 4.6 Sonnet", etc.

* **Cache Response**: Boolean
  * true/false to enable/disable response caching
  * Helps reduce API calls for identical inputs

* **Temperature**: Number
  * Value between 0 and 1
  * Controls analysis consistency and creativity

When enabled as inputs, these parameters can be dynamically set by previous nodes in your workflow. If not enabled, the values set in the node configuration will be used.

## Node Output

* **Analysis**: AI's detailed response about the image

## Node Functionality

The Analyze Image can:

* Processes images with AI vision
* Extracts text from images
* Generates descriptions
* Answers queries about content
* Identifies objects and scenes
* Can read image-based PDFs

## Available AI Models

Vision-capable models for image analysis:

Gumloop supports 30+ AI models across every major provider. Pick the model that fits your task in the node's model dropdown, and see [AI Models](/core-concepts/ai_models) for the full list.

## AI Model Selection Guide

Balance quality, speed, and cost when choosing a model:

* Smaller, faster models cost less per token and respond quicker, which suits everyday tasks like classification, short answers, and simple analysis.
* Larger frontier models deliver higher quality on complex reasoning, coding, and detailed or long-form analysis, at a higher cost and slower response.

Additional selection factors:

* Task complexity and required accuracy
* Response time requirements
* Cost considerations
* Consistency needs across runs
* Specialized knowledge requirements

For more detailed information on AI models with advanced reasoning capabilities, you can refer to:

* [Anthropic Models Overview](https://docs.anthropic.com/en/docs/models-overview)
* [Anthropic Extended Thinking Documentation](https://docs.anthropic.com/en/docs/build-with-claude/extended-thinking)
* [OpenAI Reasoning Guide](https://platform.openai.com/docs/guides/reasoning)
* [OpenAI GPT-5 Models](https://openai.com/index/gpt-5/)

## Common Use Cases

1. **Text Extraction**:

```text theme={"dark"}
Prompt: "Extract all text visible in this image"
Use: Scanning documents, reading signs
```

2. **Visual Description**:

```text theme={"dark"}
Prompt: "Describe this image in detail"
Use: Accessibility, content cataloging
```

3. **Object Detection**:

```text theme={"dark"}
Prompt: "List all objects in this image"
Use: Inventory, scene analysis
```

## Important Considerations

1. The Analyze Image node is billed by **token usage**, the same way agents are. The cost of a run depends on the model you pick and the size of the image and prompt, so there are no fixed per-run tiers. Smaller, faster vision models cost less per token than frontier models.
2. Add your own provider API key on the [Connectors page](https://www.gumloop.com/personal/connectors) to run this node's AI calls for **no credit cost** (Pro plan or higher).

In summary, the Analyze Image node helps extract meaning and information from images using powerful AI vision models.
