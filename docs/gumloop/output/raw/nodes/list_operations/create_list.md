> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Create List

This document explains the Create List node, which turns individual items into a list format.

## Node Inputs

### Optional Fields

* **Input1**: First item
* **Input2**: Second item
* Add more inputs using "Add Input" button

## Node Output

* **List**: List of all inputs in the same order they're fed in.

## Node Functionality

The Create List node:

* Builds arrays from items
* Accepts multiple inputs
* Preserves input order
* Handles any text data
* Supports loop mode

## Examples

1. **Name Collection**:

```text theme={"dark"}
Input1: "John Smith"
Input2: "Mary Johnson"
Result: ["John Smith", "Mary Johnson"]
```

2. **Data Organization**:

```text theme={"dark"}
Input1: "Task 1"
Input2: "Task 2"
Input3: "Task 3"
Result: ["Task 1", "Task 2", "Task 3"]
```

3. **Multi-Value Setup**:

```text theme={"dark"}
Input1: "user@email.com"
Input2: "admin@email.com"
Result: ["user@email.com", "admin@email.com"]
```

## Additional Information

[Video Tutorial](https://www.youtube.com/watch?v=UbvqAV0AbNs)

In summary, the Create List node transforms individual items into a structured array format, perfect for data organization and batch processing setup.
