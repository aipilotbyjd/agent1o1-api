> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Combine Lists

This document explains the Combine Lists node, which merges multiple lists into one.

## Node Inputs

### Required Fields

* **Input1**: First list of items
* **Input2**: Second list of items
* Add more inputs by clicking "Add Input"

## Node Output

* **Combined List**: All items in one list in the same sequence they're fed in.

## Node Functionality

The Combine Lists node:

* Merges multiple lists
* Preserves item order
* Maintains data types
* Handles empty lists
* Supports batch processing

## Common Use Cases

1. **Data Consolidation**:

```text theme={"dark"}
Input1: ["John", "Mary"]
Input2: ["Alex", "Sarah"]
Result: ["John", "Mary", "Alex", "Sarah"]
```

2. **List Merging**:

```text theme={"dark"}
Input1: [1, 2, 3]
Input2: [4, 5, 6]
Result: [1, 2, 3, 4, 5, 6]
```

3. **Category Combining**:

```text theme={"dark"}
Input1: ["Red", "Blue"]
Input2: ["Green", "Yellow"]
Result: ["Red", "Blue", "Green", "Yellow"]
```

## Additional Information

[Video Tutorial](https://www.youtube.com/watch?v=Joi5XmctmrQ)

In summary, the Combine Lists node helps merge multiple lists while maintaining their order, perfect for data consolidation tasks.
