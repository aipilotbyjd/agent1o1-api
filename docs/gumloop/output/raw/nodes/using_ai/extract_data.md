> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Extract Data

This document explains the Extract Data node, which uses AI to pull specific information from text content.

<Frame>
  <img src="https://mintcdn.com/agenthub/pnvaw4qtuKQMFMni/images/ai_model_fallback_extract_data.png?fit=max&auto=format&n=pnvaw4qtuKQMFMni&q=85&s=748e35b2cd82b146dbd4ebf4ea9a73d5" alt="AI Model Fallback settings" width="500" data-path="images/ai_model_fallback_extract_data.png" />
</Frame>

## Node Inputs

### Required Fields

* **Text**: Content to extract data from (documents, scraped website content, etc.)
* **Data Fields to Extract**: Define what you want to extract:
  * **Name**: Label for the data (e.g., "location")
  * **Type**: Format of the data (text/number/boolean)
  * **Description**: Help the AI understand what to extract

### Optional Fields

* **Extract List**: Enable to get multiple items instead of single values
* **Additional Context**: Extra information to guide the extraction
* **Temperature**: Controls AI creativity (0-1)
  * 0: More focused, consistent
  * 1: More creative, varied
* **Cache Response**: Save responses for reuse

### Show As Input

The node allows you to configure certain parameters as dynamic inputs. You can enable these in the "Configure Inputs" section:

* **Extract List?**: Boolean
  * true/false to enable/disable list extraction
  * When enabled, extracts data as a list of items

* **Additional Context**: String
  * Extra information to guide the extraction process
  * Example: "The text contains company names and their founding years"

* **model\_preference**: String
  * Name of the AI model to use
  * Accepted values: "Claude 4.6 Sonnet", "Claude 4.5 Haiku", "GPT-5.5", "GPT-5.4", etc.

* **Cache Response**: Boolean
  * true/false to enable/disable response caching
  * Helps reduce API calls for identical inputs

* **Temperature**: Number
  * Value between 0 and 1
  * Controls extraction consistency

When enabled as inputs, these parameters can be dynamically set by previous nodes in your workflow. If not enabled, the values set in the node configuration will be used.

### AI Model Fallback

Under **Show More Options**, configure automatic fallback when your selected AI model is unavailable. **Fallback is enabled by default.**

When an error occurs (rate limits, provider outages, timeouts), the system retries based on severity, then falls back to the next model. Fallback models are always from different providers for true redundancy.

| Error Type    | Retries Before Fallback |
| ------------- | ----------------------- |
| Rate Limit    | 2                       |
| Provider 5xx  | 1                       |
| Network Error | 0 (immediate)           |
| Timeout       | 1                       |

**Default (Auto):** The system automatically selects fallback models based on your primary model, always choosing from different providers for true redundancy.

**Override:** Enable to manually select up to 2 fallback models with drag-and-drop priority.

<Warning>Disabling fallback means your node will fail if the primary model is unavailable.</Warning>

## Node Output

* **Extracted Data Fields**: Single value or list based on your settings

## Node Functionality

The Extract Data node:

* Analyzes text using AI
* Finds specific information
* Returns structured data
* Handles single or multiple items
* Supports various data types

## Available AI Models

Gumloop supports 30+ AI models across every major provider. Pick the model that fits your task in the node's model dropdown, and see [AI Models](/core-concepts/ai_models) for the full list.

<Info>Auto-Select uses third-party routing to choose models based on cost and performance. Not ideal when consistent behavior is required.</Info>

## Example Use Cases

1. **Contact Information**:

```text theme={"dark"}
Extract: Email, Phone, Address
From: Company websites or documents
```

2. **Product Details**:

```text theme={"dark"}
Extract: Price, Features, Specifications
From: Product descriptions
```

3. **Data Extraction from Documents**:

```text theme={"dark"}
Extract: Date, invoice amount, vendor, address
From: Financial documents or invoices
```

## Important Considerations

1. This node is billed by **token usage**, the same way agents are, so the cost of a run depends on the model you pick and how many input and output tokens it uses
2. Add your own provider API key on the [Connectors page](https://www.gumloop.com/personal/connectors) to run its AI calls for **no credit cost** (Pro plan or higher)
3. Enable "Extract List" when you need multiple items
4. Be specific in your data descriptions for accurate outputs

In summary, the Extract Data node is your tool for pulling structured information from unstructured text, whether you need single values or lists of data.
