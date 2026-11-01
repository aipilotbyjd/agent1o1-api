> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Subflows

Subflows allow you to use any workflow as a reusable node within other workflows. This modular approach is one of Gumloop's most powerful features, enabling you to build complex automations from smaller, well-tested components.

<div className="rounded-2xl overflow-hidden border border-pink-200 dark:border-pink-800">
  <iframe src="https://player.vimeo.com/video/1123440653?h=61bb3f5b20" style={{ width: '100%', aspectRatio: '16/9' }} frameBorder="0" allow="autoplay; fullscreen; picture-in-picture" title="Basics of Subflows" />
</div>

## Why Use Subflows?

<CardGroup cols={3}>
  <Card title="Cleanliness" icon="broom">
    Break complex logic into manageable pieces. Like functions in programming, subflows make large automations easier to
    maintain and debug.
  </Card>

  <Card title="Looping" icon="repeat">
    Perfect a single task once, then loop it across thousands of items. Build and test on one instance, then scale
    effortlessly.
  </Card>

  <Card title="Nested Processing" icon="layer-group">
    Handle multi-level data structures with ease. Process emails containing orders, each with multiple items, using
    nested subflows.
  </Card>
</CardGroup>

### Example: Web Scraping at Scale

You want to scrape contact information from thousands of company websites. With subflows, you build one workflow that perfectly handles a single website, then loop it across your entire list. This approach makes testing simple and scaling automatic.

### Example: Nested Data Processing

Process an email inbox containing orders, where each order has multiple items. Using three nested subflows, you can:

1. **First workflow**: Process the entire inbox and extract orders
2. **Second workflow** (looped): Process each individual order
3. **Third workflow** (looped): Process each item within an order

This creates a triple-nested loop structure, allowing operations at each level of your data hierarchy.

<Tip>
  **Build Workflows Backwards Using Subflows**

  When working with lists or batch processing, start by building a subflow that handles a single item perfectly:

  1. Create a subflow with input nodes and default test values
  2. Perfect the logic for one item at a time
  3. Once working, use it as a node in your main workflow
  4. Connect your data source and enable Loop Mode

  This approach provides parallel processing, faster execution, easier testing, better error handling, and prevents type structure issues.
</Tip>

## Working with Subflows

### Adding Subflows to Your Canvas

In the workflow builder, locate the Subflow Library in the node menu. All of your existing workflows appear here and can be dragged onto the canvas like any standard node.

<div align="center">
  <img src="https://mintcdn.com/agenthub/w1F7hfGEH4EChCiL/images/subflow_library.png?fit=max&auto=format&n=w1F7hfGEH4EChCiL&q=85&s=73b48cac7744f2e5e73e4d4a95a45d25" alt="Subflow Library" width="600" data-path="images/subflow_library.png" />
</div>

### Configuring Inputs and Outputs

Subflows need defined inputs and outputs to be useful in other workflows. Here's how to set them up:

#### Step 1: Initial Subflow Without Inputs

Consider this example workflow that scrapes a website, extracts the company name, summarizes it, and categorizes the business:

<div align="center">
  <img src="https://mintcdn.com/agenthub/KMa3S5LrPuj3opZR/images/example_subflow.png?fit=max&auto=format&n=KMa3S5LrPuj3opZR&q=85&s=beaa9d6445e248c4673cf5da21f4b106" alt="Example Subflow" width="900" data-path="images/example_subflow.png" />
</div>

When you first add this workflow as a subflow node, it has no connection points:

<div align="center">
  <img src="https://mintcdn.com/agenthub/Ikk0mS7ZAjXA5-_q/images/inputless_subflow_node.png?fit=max&auto=format&n=Ikk0mS7ZAjXA5-_q&q=85&s=775ec7bd0f8cc918b7aeb2ea3cf17ee7" alt="Subflow Node Without Inputs" width="400" data-path="images/inputless_subflow_node.png" />
</div>

#### Step 2: Adding Input Nodes

Return to the subflow and add an Input node for each parameter you want to accept. For this example, we need one input for the website URL:

<div align="center">
  <img src="https://mintcdn.com/agenthub/Ikk0mS7ZAjXA5-_q/images/input_only_subflow.png?fit=max&auto=format&n=Ikk0mS7ZAjXA5-_q&q=85&s=2348de99feefae014cc79242c917a086" alt="Subflow With Input Node" width="600" data-path="images/input_only_subflow.png" />
</div>

<Info>
  You can name your input nodes for clarity, though this is optional. The name will appear as the connection label in
  the parent workflow.
</Info>

After saving, the subflow node now displays the URL input:

<div align="center">
  <img src="https://mintcdn.com/agenthub/Ikk0mS7ZAjXA5-_q/images/input_only_subflow_node.png?fit=max&auto=format&n=Ikk0mS7ZAjXA5-_q&q=85&s=17ffc9f10f942d64654f3f880b0d499a" alt="Subflow Node With Input" width="400" data-path="images/input_only_subflow_node.png" />
</div>

#### Step 3: Adding Output Nodes

Add Output nodes for each value you want to return. In this example, we want to output the summary, company name, and category separately:

<div align="center">
  <img src="https://mintcdn.com/agenthub/KMa3S5LrPuj3opZR/images/finished_subflow.png?fit=max&auto=format&n=KMa3S5LrPuj3opZR&q=85&s=d98687f4fb47eefd69e52b75be73ebbe" alt="Completed Subflow" width="600" data-path="images/finished_subflow.png" />
</div>

The completed subflow node now has both inputs and outputs:

<div align="center">
  <img src="https://mintcdn.com/agenthub/KMa3S5LrPuj3opZR/images/finished_subflow_node.png?fit=max&auto=format&n=KMa3S5LrPuj3opZR&q=85&s=262993f59da86b06705306df82e34451" alt="Completed Subflow Node" width="400" data-path="images/finished_subflow_node.png" />
</div>
