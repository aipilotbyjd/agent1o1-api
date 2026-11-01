> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# List Trimmer

This document explains the List Trimmer node, which helps reduce or extract portions of lists.

## Node Inputs

### Required Fields

* **List**: List of items to trim

### Optional Fields

* **Specify Section**: Enable to extract specific range
  * **Start Index**: Enter the starting index of the section you want to keep (inclusive). The first item of the list is index 0.
  * **End Index**: Enter the ending index of the section you want to keep (exclusive).
* **# of Items to Keep**: When not specifying section

## Node Output

* **Trimmed List**: Resulting shortened list

## Node Functionality

The List Trimmer node:

* Shortens lists
* Extracts sections
* Uses zero-based indexing
* Preserves item order
* Handles varying sizes

## When to Use

Use this node when you need to:

1. **Limit Results**:
   * Keep top N items
   * Reduce API responses
   * Control output size

2. **Extract Sections**:
   * Get specific ranges
   * Split data chunks
   * Sample large lists

3. **Data Processing**:
   * Remove unwanted items
   * Focus on key sections
   * Prepare for batch ops

## Common Use Cases

1. **Keep First Items**:

```text theme={"dark"}
Input: [A, B, C, D, E]
Items to Keep: 3
Result: [A, B, C]
```

2. **Extract Section**:

```text theme={"dark"}
Input: [A, B, C, D, E]
Start Index: 1
End Index: 4
Result: [B, C, D]
```

3. **Limit Results**:

```text theme={"dark"}
Input: [result1...result100]
Items to Keep: 10
Result: First 10 results
```

## Important Considerations

1. Indices start at 0
2. End index is exclusive
3. Maintains item order
4. Works in loop mode

In summary, the List Trimmer node helps manage list size and extract specific portions of data in your Gumloop workflows.
