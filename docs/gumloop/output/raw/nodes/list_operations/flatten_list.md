> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Flatten List of Lists

This document explains the Flatten List of Lists node, which converts nested lists into a single flat list.

## Node Inputs

### Required Fields

* **Input List of list**: Nested list structure

  Example: `[[1, 2], [3, 4], [5]]`

## Node Output

* **List**: Single flattened list

  Result: `[1, 2, 3, 4, 5]`

## Node Functionality

The Flatten List of Lists node:

* Combines nested lists
* Preserves item order
* Handles varying depths
* Maintains data types
* Supports batch processing

## Example Use Cases

1. **Process Results**:

```text theme={"dark"}
Input: [[page1_data], [page2_data]]
Output: [all_data_combined]
Use: Process all data together
```

2. **Merge Categories**:

```text theme={"dark"}
Input: [[fruits], [vegetables]]
Output: [all_items]
Use: Create single inventory
```

3. **Combine Responses**:

```text theme={"dark"}
Input: [[response1, response2], [response3]]
Output: [response1, response2, response3]
Use: Process all responses
```

## Important Considerations

1. Maintains original order
2. Works with any length lists
3. Handles empty lists

In summary, the Flatten List of Lists node helps simplify complex nested arrays into single-level lists, making data easier to process in your Gumloop workflows.
