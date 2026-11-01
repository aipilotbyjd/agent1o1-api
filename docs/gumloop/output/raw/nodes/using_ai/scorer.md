> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Scorer

This document explains the Scorer node, which assigns numerical scores (0-100) to items based on custom criteria.

<Frame>
  <img src="https://mintcdn.com/agenthub/pnvaw4qtuKQMFMni/images/ai_model_fallback_scorer.png?fit=max&auto=format&n=pnvaw4qtuKQMFMni&q=85&s=41cb627fd048474bbafab0b6d8c90f1d" alt="AI Model Fallback settings" width="500" data-path="images/ai_model_fallback_scorer.png" />
</Frame>

## Node Inputs

### Required Fields

* **Item**: The content to be scored
* **Criteria**: Rules for scoring (e.g., "Clarity: 0-30, Grammar: 0-40, Relevance: 0-30")

### Optional Fields

* **Include Justification**: Get AI's reasoning for scores
* **Additional Context**: Extra guidance for scoring
* **Temperature**: Controls scoring consistency (0-1)
  * 0: More focused, consistent
  * 1: More creative, varied
* **Cache Response**: Save responses for reuse

### Show As Input

The node allows you to configure certain parameters as dynamic inputs. You can enable these in the "Configure Inputs" section:

* **item**: String
  * The text or item to be scored
  * Example: "Customer feedback response"

* **criteria**: String
  * The scoring criteria or rubric
  * Example: "Score based on clarity, politeness, and helpfulness"

* **Additional Context**: String
  * Extra information to help with scoring
  * Example: "This is feedback from a premium customer"

* **include\_justification**: Boolean
  * true/false to include explanation for the score
  * When enabled, provides reasoning for the assigned score

* **model\_preference**: String
  * Name of the AI model to use
  * Accepted values: "Claude 4.6 Sonnet", "Claude 4.5 Haiku", "GPT-5.5", "GPT-5.4", etc.

* **Cache Response**: Boolean
  * true/false to enable/disable response caching
  * Helps reduce API calls for identical inputs

* **Temperature**: Number
  * Value between 0 and 1
  * Controls scoring consistency

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

* **Score**: Numerical value between 0-100
* **Justification**: AI's scoring explanation (if enabled)

## Node Functionality

The Scorer node:

* Analyzes content against criteria
* Assigns numerical scores
* Provides scoring rationale
* Handles batch scoring
* Ensures consistent evaluation

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

## Common Use Cases

1. **Content Quality**:

```text theme={"dark"}
Criteria: 
- Writing clarity (0-30)
- Accuracy (0-40)
- Engagement (0-30)
```

2. **Support Responses**:

```text theme={"dark"}
Criteria:
- Politeness (0-25)
- Problem solving (0-50)
- Response time (0-25)
```

3. **Product Reviews**:

```text theme={"dark"}
Criteria:
- Detail level (0-30)
- Helpfulness (0-40)
- Objectivity (0-30)
```

## Loop Mode

```text theme={"dark"}
Input: List of items to score
Process: Score each against criteria
Output: Scores and justifications for each item
```

## Important Considerations

1. This node is billed by **token usage**, the same way agents are, so the cost of a run depends on the model you pick and how many input and output tokens it uses
2. Add your own provider API key on the [Connectors page](https://www.gumloop.com/personal/connectors) to run its AI calls for **no credit cost** (Pro plan or higher)
3. Define clear, measurable criteria for accurate output
4. Enable justification for transparency

In summary, the Scorer node helps quantify quality and performance using AI-powered assessment against your custom criteria.
