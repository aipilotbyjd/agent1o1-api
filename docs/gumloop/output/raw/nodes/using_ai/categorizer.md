> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Categorizer

This document explains the Categorizer node, which uses AI to classify text into custom categories.

<Frame>
  <img src="https://mintcdn.com/agenthub/eB2-lKaLpALJjdq7/images/ai_model_fallback_categorizer.png?fit=max&auto=format&n=eB2-lKaLpALJjdq7&q=85&s=09044d98b4833d35a99bf3c292bc4d30" alt="AI Model Fallback settings" width="500" data-path="images/ai_model_fallback_categorizer.png" />
</Frame>

## Node Inputs

### Required Fields

* **Input**: Text to categorize
* **Categories**: Define your classification groups:
  * **Category Name**: Label for the category
  * **Category Description**: Explain what belongs in this category

### Optional Fields

* **Include Justification**: Get AI's reasoning for selections
* **Additional Context**: Extra guidance for categorization
* **Temperature**: Controls AI decision-making (0-1)
  * 0: More focused, consistent
  * 1: More creative, varied
* **Cache Response**: Save responses for reuse

### Show As Input

The node allows you to configure certain parameters as dynamic inputs. You can enable these in the "Configure Inputs" section:

* **include\_justification**: Boolean
  * true/false to include explanation for category assignment

* **Additional Context**: String
  * Extra information to guide the categorization process
  * Example: "These items are different types of software bugs"

* **model\_preference**: String
  * Name of the AI model to use
  * Accepted values: "Claude 4.6 Sonnet", "Claude 4.5 Haiku", "GPT-5.5", "GPT-5.4", etc.

* **Cache Response**: Boolean
  * true/false to enable/disable response caching
  * Helps reduce API calls for identical inputs

* **Temperature**: Number
  * Value between 0 and 1
  * Controls categorization consistency

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

* **Selected Category**: Chosen category name
* **Justification**: AI's reasoning (if enabled)

## Node Functionality

The Categorizer node:

* Analyzes input text
* Matches to best category
* Provides reasoning (optional)
* Handles batch processing
* Supports custom categories

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

## Example Use Cases

1. **Sentiment Analysis**:

```text theme={"dark"}
Categories:
- Positive: "Expresses satisfaction or approval"
- Negative: "Shows dissatisfaction or criticism"
- Neutral: "States facts without emotion"
```

2. **Support Tickets**:

```text theme={"dark"}
Categories:
- Bug Report: "Technical issues or errors"
- Feature Request: "New functionality suggestions"
- Account Issue: "Login or access problems"
```

3. **Content Classification**:

```text theme={"dark"}
Categories:
- News: "Current events and reporting"
- Opinion: "Personal views and analysis"
- Tutorial: "How-to guides and instructions"
```

## Loop Mode

```text theme={"dark"}
Input: List of customer feedback
Process: Categorize each item
Output: Category per item + justifications
```

## Important Considerations

1. This node is billed by **token usage**, the same way agents are, so the cost of a run depends on the model you pick and how many input and output tokens it uses
2. Add your own provider API key on the [Connectors page](https://www.gumloop.com/personal/connectors) to run its AI calls for **no credit cost** (Pro plan or higher)
3. Write clear category descriptions for accurate outputs
4. Enable justification for important decisions
5. Use additional context for complex rules

## Additional Information

[Video Tutorial](https://www.youtube.com/watch?v=BfCqMUio_FI)

In summary, the Categorizer node helps organize text into meaningful groups using AI, with optional explanations for each decision.
