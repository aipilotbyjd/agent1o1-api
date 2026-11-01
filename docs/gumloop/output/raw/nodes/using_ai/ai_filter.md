> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# AI Filter

This document explains the AI Filter node, which uses natural language conditions and AI to filter data by comparing two inputs.

## Node Inputs

### Required Fields

* **Filter By**: Main content to filter
* **Value**: The output you want to pass if the condition is met
* **Condition**: Natural language comparison rule

  Example: "Is the provided text in Spanish"

### Optional Fields

* **Output Blank Value**: Return blanks for non-matches
* **Temperature**: Controls decision consistency (0-1)
  * 0: More focused, consistent
  * 1: More creative, varied
* **Cache Response**: Save responses for reuse

### Show As Input

The node allows you to configure certain parameters as dynamic inputs. You can enable these in the "Configure Inputs" section:

* **condition**: String
  * Natural language comparison rule
  * Example: "Is the provided text in Spanish"
  * Example: "Does the text contain pricing information"

* **output\_blank\_value**: Boolean
  * true/false to control what happens with non-matches
  * When true, outputs blank for non-matching items
  * When false, skips non-matching items entirely

* **model\_preference**: String
  * Name of the AI model to use
  * Accepted values: "Claude 4.6 Sonnet", "Claude 4.5 Haiku", "GPT-5.5", "GPT-5.4", etc.

* **Cache Response**: Boolean
  * true/false to enable/disable response caching
  * Helps reduce API calls for identical inputs

* **Temperature**: Number
  * Value between 0 and 1
  * Controls decision consistency
  * Lower values (closer to 0) provide more consistent filtering results

When enabled as inputs, these parameters can be dynamically set by previous nodes in your workflow. If not enabled, the values set in the node configuration will be used.

## Node Output

* **Filtered Output**: Values that meet your condition

## Node Functionality

The AI Filter node:

* Compares paired values
* Uses natural language rules
* Evaluates matching criteria

## Available AI Models

Gumloop supports 30+ AI models across every major provider. Pick the model that fits your task in the node's model dropdown, and see [AI Models](/core-concepts/ai_models) for the full list.

<Info>Auto-Select uses third-party routing to choose models based on cost and performance. Not ideal when consistent behavior is required.</Info>

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

## Important Considerations

1. This node is billed by **token usage**, the same way agents are, so the cost of a run depends on the model you pick and how many input and output tokens it uses
2. Add your own provider API key on the [Connectors page](https://www.gumloop.com/personal/connectors) to run its AI calls for **no credit cost** (Pro plan or higher)
3. Values and Filter By lists must match in length
4. Write clear comparison conditions for accurate outputs
5. This node relies heavily on AI model performance, which may vary depending on the complexity of your filtering conditions. For more reliable and consistent filtering:
   * Use the [Filter node](https://docs.gumloop.com/nodes/flow_basics/filter) for straightforward comparisons and exact matching
   * Create a [custom node](https://docs.gumloop.com/nodes/custom_node_details) for complex filtering logic that needs to be precise and deterministic

In summary, the AI Filter node helps you filter content by comparing pairs of values using natural language rules, perfect for complex matching and filtering tasks where some flexibility in interpretation is acceptable. For mission-critical filtering that requires exact matching or complex logic, consider using the standard Filter node or creating a custom node.
