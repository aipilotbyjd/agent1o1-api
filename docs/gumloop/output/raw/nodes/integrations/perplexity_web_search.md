> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Perplexity Web Search

This node performs AI-powered web searches to retrieve current information with citations.

## Node Inputs

### Required Fields

* **Search Query**: The text to search for
* **Model**: Choose between Perplexity Sonar models:
  * Perplexity Sonar: Basic web search capabilities
  * Perplexity Sonar Pro: Enhanced search with advanced analysis
  * Perplexity Sonar Reasoning: Advanced reasoning and analysis
  * Perplexity Sonar Reasoning Pro: Premium model with superior reasoning capabilities

### Optional Fields

* **Temperature**: Controls response creativity (0-1, default: 1)
* **Cache Response**: Save responses for reuse

### Show As Input Options

You can expose these fields as inputs by clicking on `Configure Inputs`:

* Search Query
* Temperature

## Node Outputs

* **Response**: AI-generated answer based on search results
* **Citations**: List of source URLs

## Available Models

### Perplexity Sonar

* Best for: Basic searches and quick facts
* Features: Standard web search capabilities
* Use when: Need straightforward information quickly

### Perplexity Sonar Pro (Advanced)

* Best for: Detailed research and analysis
* Features: Enhanced search capabilities and deeper analysis
* Use when: Need comprehensive information with detailed insights

### Perplexity Sonar Reasoning (Advanced)

* Best for: Complex queries requiring logical analysis
* Features: Advanced reasoning capabilities and structured analysis
* Use when: Need logical deduction and thorough reasoning

### Perplexity Sonar Reasoning Pro (Advanced)

* Best for: Premium research requiring superior reasoning
* Features: Top-tier reasoning and analysis capabilities
* Use when: Need the most sophisticated analysis and insights

## Best Practices

1. **Model Selection Guide**:
   * Use Sonar for quick facts and simple queries
   * Use Sonar Pro for detailed research
   * Use Sonar Reasoning for complex analytical tasks
   * Use Sonar Reasoning Pro for highest quality analysis

2. **Query Construction**:
   * Be specific and clear
   * Include relevant timeframes
   * Use keywords effectively

3. **Temperature Usage**:
   * Low (0-0.3): Factual, consistent responses
   * Medium (0.4-0.7): Balanced analysis
   * High (0.8-1.0): Creative insights

## Common Use Cases

1. **Research Automation**:

```text theme={"dark"}
Query: "Latest developments in quantum computing 2024"
Model: Sonar Reasoning Pro
Use: Academic research, technology tracking
```

2. **Fact Verification**:

```text theme={"dark"}
Query: "Current market share of electric vehicles"
Model: Sonar
Use: Quick data verification
```

3. **Complex Analysis**:

```text theme={"dark"}
Query: "Impact of AI on healthcare systems"
Model: Sonar Reasoning
Use: Detailed analytical reports
```

4. **Market Research**:

```text theme={"dark"}
Query: "Emerging trends in renewable energy"
Model: Sonar Pro
Use: Comprehensive market analysis
```

The Perplexity node provides powerful web search capabilities with AI-driven analysis and citation tracking, suitable for various research and analysis needs.
