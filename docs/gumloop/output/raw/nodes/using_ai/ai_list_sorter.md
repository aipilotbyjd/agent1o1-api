> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# AI List Sorter

This document explains the AI List Sorter node, which helps you sort lists using AI when regular filter conditions aren't enough. It's great for tasks like sorting emails by importance or ranking tasks by complexity.

## Node Inputs

### Required Fields

* **Input List**: The items you want to sort. Can be any list containing text like:
  * Customer reviews
  * Support tickets
  * Product descriptions
  * Tasks
  * Emails

* **Ordering Criteria Prompt**: Tell the AI how to sort your items. Be specific:
  * Example: "Sort these reviews by how urgent they are, considering if the customer is angry and how serious the problem is"
  * Example: "Order these product ideas by potential profit, looking at market size and development costs"
  * Example: "Rank these tasks by how complex they are and how long they'll take"

### Optional Fields

* **Sort Order**: Choose how to order items
  * Descending (Default): Highest ranked first
  * Ascending: Lowest ranked first

* **Choose AI Model**: Pick which AI to use. This node is billed by token usage, so the cost depends on the model you pick and how many input and output tokens it uses. Adding your own provider API key runs its AI calls at no credit cost.

* **Temperature**: Controls how the AI makes decisions (0-1)
  * 0: More consistent sorting
  * 1: More varied sorting

* **Cache Response**: If active, the AI response for the input will be stored for the future. This can improve performance and cost for repeated requests as the stored response can be used instead of making a new request each time.

### Show As Input

Make the following node parameters dynamic inputs by enabling them in "Configure Inputs":

* **Ordering Criteria Prompt**: Text (String)
  * Your sorting instructions
  * Example: "Sort by urgency level"

* **Sort Orderr**: Text (String)
  * "Ascending" or "Descending"

* **Model Preference**: Text (String)
  * Which AI model to use
  * Options: "Claude 4.6 Sonnet", "Claude 4.5 Haiku", "GPT-5.5", "GPT-5.4", etc.

* **Temperature**: Text/Number
  * Between 0-1
  * Controls sorting consistency

* **Cache Response**: Boolean
  * true/false to save responses
  * Saves credits on repeated sorts for the same inputs and parameters

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

## Examples

### Support Ticket Sorting

```text theme={"dark"}
Input List:
- "Can't check out, getting error 404"
- "Need password reset help"
- "Charged twice for subscription"
- "Want dark mode feature"

Ordering Criteria: "Sort by urgency and money impact. Problems with payments or buying are most important."

Output:
1. "Charged twice for subscription"
2. "Can't check out, getting error 404"
3. "Need password reset help"
4. "Want dark mode feature"
```

### Sales Lead Prioritization

```text theme={"dark"}
Input List:
- "Fortune 500 company, interested in enterprise plan, needs response by EOW"
- "Startup looking for basic tier, currently using competitor"
- "Current customer wanting to upgrade to business plan"
- "Large retail chain requesting custom integration demo"

Ordering Criteria: "Sort by deal size potential, urgency, and likelihood to close. Prioritize enterprise deals and existing customers."

Output:
1. "Fortune 500 company, interested in enterprise plan, needs response by EOW"
2. "Current customer wanting to upgrade to business plan"
3. "Large retail chain requesting custom integration demo"
4. "Startup looking for basic tier, currently using competitor"
```

## Tips for Best Results

### Making Your Sorts Better

* The node works best of shorter lists. For large lists, its ideal to break the list into [chunks](https://docs.gumloop.com/nodes/text_manipulation/chunk_text) and run the node in Loop mode.
* Write clear sorting instructions
* Use Loop Mode for multiple lists
* Start with lower temperature (0.2-0.4) for consistent results
* Cache responses when sorting similar items often

### Writing Good Instructions

Tell the AI exactly what matters when sorting:

```text theme={"dark"}
"Sort these feature requests by:
1. How many users want it
2. How hard it is to build
3. How many users might quit if we don't build it
4. How much money it could make
Put features that fix core product issues at the top"
```

## Common Problems and Fixes

### Getting Different Orders Each Time

\-- Problem: Same input gives different sorting order

\-- Fix: Lower the temperature or make your instructions more specific

### Bad Sorting Results

\-- Problem: AI isn't sorting how you want

\-- Fix:

* Make your instructions clearer

* Try a better AI model

* Break down your sorting rules into simple points

## Loop Mode

When you turn this on:

1. Each list gets sorted separately
2. Lists stay organized the same way
3. You get back multiple sorted lists

## In Summary

The AI List Sorter node helps sort lists using AI when normal filtering isn't enough. It's most effective for:

* Shorter lists
* Complex sorting criteria
