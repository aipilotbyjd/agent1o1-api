> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Join List Items

This document explains the Join List Items node, which combines list items into a single text string.

## Node Inputs

### Required Fields

* **List**: List of items to combine

### Optional Fields

* **Join Characters**: Separator between items. Deafult is comma `,`
* **Join by Newline**: Put each item on new line

## Node Output

* **Joined Text**: Combined string result

## Node Functionality

The Join List Items node:

* Merges list elements
* Adds custom separators
* Supports line breaks
* Preserves item order
* Handles empty items

Here's a visual representation:

<div align="center">
  <img src="https://mintcdn.com/agenthub/OIDhR9iY2uRNFi5X/images/join_list_image.png?fit=max&auto=format&n=OIDhR9iY2uRNFi5X&q=85&s=607c3af2f3243b36f8e5633b4fc57764" alt="Alt text" width="800" data-path="images/join_list_image.png" />
</div>

## Common Use Cases

1. **CSV Creation**:

```text theme={"dark"}
List: ["name", "email", "phone"]
Join Characters: ","
Result: "name,email,phone"
```

2. **Multi-line Text**:

```text theme={"dark"}
List: ["Line 1", "Line 2", "Line 3"]
Join by Newline: true
Result:
Line 1
Line 2
Line 3
```

3. **Custom Formatting**:

```text theme={"dark"}
List: ["apple", "banana", "orange"]
Join Characters: " | "
Result: "apple | banana | orange"
```

## Important Considerations

1. Choose appropriate separator
2. Consider readability needs
3. Preserves item order

## Additional Information

[Video Tutorial](https://www.youtube.com/watch?v=wHgpSEYugMk)

In summary, the Join List Items node helps convert lists into formatted text strings for various output needs in your Gumloop workflows.
