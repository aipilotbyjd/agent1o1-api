> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Split Text

This document explains the Split Text node, which divides text into a list based on specified characters.

## Node Inputs

### Required Fields

* **Text**: Content to split

  Example: "apple,banana,orange"

### Optional Fields

* **Character(s) to Split on**: Delimiter

  Default: comma `,`
* **Split on Newline**: Use line breaks instead

## Node Output

* **List of Text**: Array of split segments

## Node Functionality

The Split Text node:

* Divides text into parts
* Uses custom delimiters
* Handles line breaks
* Preserves empty segments
* Supports batch processing

## Common Use Cases

1. **CSV Data**:

```text theme={"dark"}
Input: "John,32,New York"
Split on: ","
Output: ["John", "32", "New York"]
```

2. **Line Processing**:

```text theme={"dark"}
Input: "First line
Second line
Third line"
Split on Newline: true
Output: ["First line", "Second line", "Third line"]
```

3. **Tag Separation**:

```text theme={"dark"}
Input: "#happy #coding #automation"
Split on: " "
Output: ["#happy", "#coding", "#automation"]
```

## Additional Information

[Video Tutorial](https://www.youtube.com/watch?v=yIocul7RIGA)

In summary, the Split Text node helps break down text into manageable pieces using delimiters or line breaks, perfect for data processing tasks.
