> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Combine Text

This document explains the Combine Text node, which merges multiple text values using a customizable template format.

## Node Inputs

### Required Fields

* **Format**: A template that defines how text values should be combined

  Example: "Hello `{Name}`, your order total is `{Total}`!"

### Dynamic Values

* Any connected node's outputs can be used in your format
* Select values by clicking on the format field and choosing from available outputs
* Reference values using input badges

## Node Output

* **Combined Text**: The final merged result with all placeholders replaced by their values

## Node Functionality

The Combine Text node:

* Merges text values from connected nodes
* Uses simple template-based formatting with placeholders
* Allows direct selection of values from connected nodes
* Preserves spaces and punctuation
* Works with both single values and lists (Loop Mode)

## How To Use

1. **Connect Source Nodes**: Link previous nodes that contain the values you want to combine
2. **Create Your Template**: Click inside the format field and:
   * Type your text template
   * Add placeholders by selecting from available connected values
   * Format as needed with spaces, punctuation, and line breaks
3. **Run Your Workflow**: The node will replace all placeholders with their corresponding values

## Common Use Cases

### 1. Personalized Messages

```text theme={"dark"}
Format: "Dear {Customer Name}, thank you for your purchase of {Product}. Your order #{Order Number} will be shipped to {Shipping Address}."

Result: "Dear Alex Smith, thank you for your purchase of Premium Plan. Your order #12345 will be shipped to 123 Main St, New York."
```

### 2. Document Headers

```text theme={"dark"}
Format: "REPORT: {Report Type}\nDate: {Current Date}\nPrepared by: {Author Name}"

Result: "REPORT: Q1 Financial Summary
Date: March 31, 2025
Prepared by: Finance Team"
```

### 3. Data Formatting

```text theme={"dark"}
Format: "{Company Name} ({Industry})\nFounded: {Year}\nEmployees: {Employee Count}\nRevenue: {Annual Revenue}"

Result: "Acme Corporation (Technology)
Founded: 2010
Employees: 250
Revenue: $25M"
```

### 4. URL Construction

```text theme={"dark"}
Format: "https://api.example.com/v1/{Endpoint}?key={API Key}&query={Search Term}"

Result: "https://api.example.com/v1/search?key=abcd1234&query=automation%20tools"
```

### 5. SQL Query Building

```text theme={"dark"}
Format: "SELECT * FROM {Table Name} WHERE {Column} = '{Value}' LIMIT {Limit};"

Result: "SELECT * FROM customers WHERE region = 'Northeast' LIMIT 100;"
```

## Format Tips

### 1. Basic Text Combination

Combine text with proper spacing:

```text theme={"dark"}
Format: "{Title} {First Name} {Last Name}"

Result: "Mr. John Smith"
```

### 2. Custom Separators

Add separators between values:

```text theme={"dark"}
Format: "{Company Name} - {Industry} - {Location}"

Result: "Acme Corporation - Technology - New York"
```

### 3. Repeated Values

Use the same value multiple times:

```text theme={"dark"}
Format: "{Customer Name} purchased {Product}. Please deliver to {Customer Name}."

Result: "John Smith purchased Premium Plan. Please deliver to John Smith."
```

## Loop Mode

When the Combine Text node runs in Loop Mode:

* It processes each item in input lists independently
* Input lists must be the same length
* Results in a list of combined strings

### Loop Mode Example

```text theme={"dark"}
Connected values:
- Names: ["John", "Sarah", "Miguel"]
- Scores: ["85", "92", "78"]

Format: "{Names} scored {Scores}%"

Result: ["John scored 85%", "Sarah scored 92%", "Miguel scored 78%"]
```

In summary, the Combine Text node is a versatile tool for merging values from connected nodes into formatted text, supporting everything from simple concatenation to complex template-based formatting.
