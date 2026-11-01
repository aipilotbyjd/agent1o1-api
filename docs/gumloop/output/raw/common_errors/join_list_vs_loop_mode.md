> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Join List Items vs Loop Mode

# Join List Items vs Loop Mode: Choosing the Right Approach for List Processing

When working with lists in Gumloop, you'll frequently encounter two powerful options for processing: the **Join List Items** node and **Loop Mode**. Understanding when to use each approach is crucial for creating efficient and effective workflows. This guide explains both methods in detail and provides clear guidance on choosing the right one for your specific automation needs.

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart TB
      A["List Data"] --> B{"How should items<br>be processed?"}
      B -->|"Together as a whole"| C["Join List Items"]
      B -->|"Individually, one at a time"| D["Loop Mode"]
      C --> E["Single Text<br>Output"]
      D --> F["List of<br>Results"]
  ```
</div>

## Understanding the Core Difference

The fundamental difference between these two approaches lies in how they handle list items:

### Join List Items Node

* **Combines** all list items into a single text value
* Preserves the relationship and context between items
* Outputs a **single text string**
* Allows controlling how items are combined with separators

### Loop Mode

* **Processes** each list item individually, one at a time
* Treats each item as a separate, independent entity
* Creates a new **list of results** (one per input item)
* Items never get combined or "see" each other

## Join List Items: Detailed Overview

The Join List Items node is a specialized node designed to convert a list into a single text string by combining all items with a specified separator.

<div align="center">
  <img src="https://mintcdn.com/agenthub/OIDhR9iY2uRNFi5X/images/join_list_image.png?fit=max&auto=format&n=OIDhR9iY2uRNFi5X&q=85&s=607c3af2f3243b36f8e5633b4fc57764" alt="Alt text" width="800" data-path="images/join_list_image.png" />
</div>

### When to Use Join List Items

The Join List Items node is ideal when:

1. **Context Matters**: Items need to be processed together to understand their relationship
   * *Example*: Analyzing sentiment across multiple customer comments requires seeing all comments together

2. **Formatting is Important**: Items need specific formatting when combined
   * *Example*: Creating an HTML list from array items with `<li>` tags

3. **Single Input Required**: The next node expects a single text input
   * *Example*: Sending all project updates in one email or Slack message

4. **Pattern Analysis**: Looking for patterns across multiple items
   * *Example*: Identifying common themes in a list of support tickets

### Example Configuration

Here's how to configure Join List Items for various outputs:

**Simple List:**

* Separator: Comma (`", "`)
* Input: `["Apple", "Banana", "Cherry"]`
* Output: `"Apple, Banana, Cherry"`

**Numbered List:**

* Custom formatting with prefix/suffix:
* Separator: `Newline`
* Prefix: `"1. "`
* Input: `["Start task", "Complete work", "Review results"]`
* Output:
  `"Start task
  /n Complete work
  /n Review results"`

## Loop Mode: Detailed Overview

Loop Mode is a processing option available on many Gumloop nodes that changes how they handle list inputs. Instead of requiring a text input, Loop Mode lets the node process each item in a list separately.

<div align="center">
  <img src="https://mintcdn.com/agenthub/0MIzwL1cHHBNpu7Y/images/batch_mode.png?fit=max&auto=format&n=0MIzwL1cHHBNpu7Y&q=85&s=947fda4d89028b50106ed90070ead73a" alt="Alt text" width="600" data-path="images/batch_mode.png" />
</div>

### How Loop Mode Works

1. The node takes a list input
2. It processes the first item in the list
3. Then processes the second item
4. Continues until all items are processed
5. Outputs a new list with all results

### Loop Mode Performance Considerations

Loop Mode processes multiple items simultaneously based on your plan:

* Pro Plan: 15 concurrent items

This parallel processing significantly speeds up operations on large lists.

### When to Use Loop Mode

Loop Mode is the best choice when:

1. **Independent Processing**: Each item needs to be handled separately
   * *Example*: Sending personalized emails to different customers

2. **Transformation Tasks**: Converting each item independently
   * *Example*: Summarizing each document in a collection

3. **Batch Operations**: Performing the same action on many items
   * *Example*: Categorizing each product description

4. **Multiple Results Needed**: You need a separate result for each input
   * *Example*: Creating a sentiment score for each customer review

### Loop Mode with Multiple Nodes

For complex processing chains, multiple nodes can use Loop Mode together:

```text theme={"dark"}
Google Sheets Reader (URLs) → Website Scraper (Loop Mode) → Summarizer (Loop Mode) → Categorizer (Loop Mode)
```

In this workflow:

1. Each URL is scraped individually
2. Each website content is summarized separately
3. Each summary is categorized independently
4. The final output is three lists:
   * List of website content
   * List of summaries
   * List of categories

## Real-World Business Examples

### Example 1: Customer Feedback Analysis

**Goal**: Analyze customer feedback to identify common themes and sentiment.

#### Approach 1: Join List Items (Recommended)

```text theme={"dark"}
Google Sheets Reader (feedback) → Join List Items (newline separator) → Ask AI (analyze feedback)
```

**Why it works**: The AI sees all feedback at once, enabling it to identify patterns, recurring issues, and overall sentiment. This provides a holistic understanding that would be impossible when analyzing individual comments in isolation.

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart LR
      A["Google Sheets<br>(Customer Feedback)"] --> B["Join List Items<br>(newline separator)"]
      B --> C["Ask AI<br>'Analyze customer feedback'"]
      C --> D["Comprehensive analysis<br>of all feedback"]
  ```
</div>

**Sample Ask AI prompt**:

```text theme={"dark"}
Analyze the following customer feedback and identify:
1. Common themes or issues
2. Overall sentiment
3. Priority areas for improvement
4. Positive aspects worth highlighting

Feedback:
{input}
```

#### Approach 2: Loop Mode (Not Ideal)

```text theme={"dark"}
Google Sheets Reader (feedback) → Ask AI (Loop Mode) → Summarizer
```

**Why it doesn't work as well**: Each feedback item is processed independently, so the AI cannot recognize patterns across multiple comments. This results in siloed insights that miss the bigger picture and trends.

### Example 2: Content Distribution

**Goal**: Create social media posts for multiple platforms from content pieces.

#### Approach 1: Loop Mode (Recommended)

```text theme={"dark"}
Google Sheets Reader (content) → Ask AI (Loop Mode) → Twitter Poster (Loop Mode)
```

**Why it works**: Each content piece needs its own unique social post, and posts don't need context from other content items. Loop Mode processes each piece independently, creating tailored messaging for each.

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart LR
      A["Airtable Reader<br>(Content Calendar)"] --> B["Ask AI<br>(Loop Mode)<br>'Create social post'"]
      B --> C["Twitter Poster<br>(Loop Mode)"]
      C --> D["Multiple tweets<br>(one per content piece)"]
  ```
</div>

**Sample Ask AI prompt** (for each content piece):

```text theme={"dark"}
Create an engaging Twitter post (280 chars max) for the following content:
{input}

Include relevant hashtags and a call to action.
```

#### Approach 2: Join List Items (Not Ideal)

```text theme={"dark"}
Google Sheets Reader (content) → Join List Items → Ask AI → Twitter Poster
```

**Why it doesn't work as well**: This would combine all content pieces into one text block, resulting in a single social post about multiple unrelated topics. This creates confusing messaging that doesn't effectively promote any single content piece.

### Example 3: Document Processing

**Goal**: Extract contact information from multiple PDF documents.

#### Approach 1: Loop Mode (Recommended)

```text theme={"dark"}
File Reader (PDFs) → Extract Data (Loop Mode) → Airtable Writer
```

**Why it works**: Each document contains different information and needs to be processed separately. Loop Mode handles each PDF individually, extracting unique contact details from each one.

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart LR
      A["File Reader<br>(Multiple PDFs)"] --> B["Extract Data<br>(Loop Mode)<br>Contact Information"]
      B --> C["Airtable Writer<br>(contacts table)"]
  ```
</div>

**Extract Data configuration**:

* Field 1: Name (text)
* Field 2: Email (text)
* Field 3: Phone (text)
* Field 4: Company (text)

### Example 4: Weekly Team Report

**Goal**: Compile a weekly report of all team activities and send as one email.

#### Approach 1: Join List Items (Recommended)

```text theme={"dark"}
Airtable Reader (activities) → Join List Items (HTML formatting) → Combine Text (email template) → Gmail Sender
```

**Why it works**: All team activities need to be combined into a single cohesive report. Join List Items allows formatting the activities as a properly structured HTML section within the larger email.

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart LR
      A["Airtable Reader<br>(Team Activities)"] --> B["Join List Items<br>(HTML formatting)"]
      C["Current Date"] --> D["Combine Text<br>(Email Template)"]
      B --> D
      D --> E["Gmail Sender"]
  ```
</div>

**Join List Items configuration**:

* Separator: `"</li><li>"`
* Prefix: `"<h2>Team Activities</h2><ul><li>"`
* Suffix: `"</li></ul>"`

## Advanced Strategies: Using Both Methods Together

In complex workflows, combining both methods can create powerful solutions:

### Example: Processing Reviews by Product Category

**Goal**: Analyze customer reviews grouped by product category.

**Approach**: Use both Loop Mode and Join List Items at different stages.

```text theme={"dark"}
1. Google Sheets Reader (all reviews)
2. Group by product category (creates nested lists)
3. Subflow (Loop Mode) {
   a. Input: Reviews for one category
   b. Join List Items (all reviews for this category)
   c. Ask AI (analyze sentiment and themes)
   d. Output: Analysis for this category
}
4. Final output: List of analyses by category
```

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart TB
      A["Google Sheets Reader<br>(All Reviews)"] --> B["Group by<br>Product Category"]
      B --> C["Subflow<br>(Loop Mode)"]
      
      subgraph "Inside Subflow"
      D["Input<br>(Reviews for one category)"] --> E["Join List Items"]
      E --> F["Ask AI<br>(Analyze sentiment)"]
      F --> G["Output<br>(Category analysis)"]
      end
      
      C --> H["List of Analyses<br>by Category"]
  ```
</div>

This hybrid approach:

1. Uses Loop Mode to process each product category separately
2. Uses Join List Items to analyze all reviews within a category together
3. Produces an analysis that maintains both the category context and the relationships between reviews

## Decision Framework: Choosing the Right Approach

Ask yourself these questions to determine which approach to use:

1. **Do the items need to be processed together?**
   * Yes → Join List Items
   * No → Loop Mode

2. **Is context across items important?**
   * Yes → Join List Items
   * No → Loop Mode

3. **Do you need separate results for each item?**
   * Yes → Loop Mode
   * No → Join List Items

4. **Are you looking for patterns across all items?**
   * Yes → Join List Items
   * No → Loop Mode

5. **Is there a size limit on the receiving system?**
   * Yes, small limit → Loop Mode (for chunking)
   * No or large limit → Either approach works

## Quick Reference Table

| Task                                     | Recommended Approach | Explanation                                          |
| ---------------------------------------- | -------------------- | ---------------------------------------------------- |
| Analyzing feedback for trends            | Join List Items      | Needs to see all items together to identify patterns |
| Sending personalized emails              | Loop Mode            | Each recipient gets unique content                   |
| Creating a report with all projects      | Join List Items      | All projects in one coherently formatted document    |
| Processing multiple documents            | Loop Mode            | Each document contains different information         |
| Sentiment analysis of reviews            | Join List Items      | Context between reviews matters                      |
| Posting to multiple social platforms     | Loop Mode            | Each platform needs different formatting             |
| Finding common themes in support tickets | Join List Items      | Requires all tickets to identify similarities        |
| Generating single-item summaries         | Loop Mode            | Each item summarized independently                   |

## Conclusion

Both Join List Items and Loop Mode are powerful tools in Gumloop with distinct advantages for different scenarios. By understanding when to use each approach, you can build more efficient, effective workflows that process your data exactly as needed.

Remember these key principles:

* Use **Join List Items** when all items need to be processed together as a cohesive whole
* Use **Loop Mode** when each item needs to be processed independently
* Consider combining both approaches for complex workflows with nested data structures

For additional support or to share your use cases, [reach out to us](https://portal.usepylon.com/gumloop/forms/help).
