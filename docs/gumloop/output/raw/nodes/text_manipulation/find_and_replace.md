> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Find And Replace

This document explains the Find And Replace node, which allows you to search for and replace specific words, phrases, or patterns within text content.

## Node Inputs

### Required Fields

* **Input**: The text content that will be processed for replacements

* **Replacements**: A list of find/replace pairs to apply, with each pair containing:
  * **Find word**: The text to search for
  * **Replace with**: The text to use as replacement

## Node Output

* **Output Text**: The resulting text after all replacements have been applied

## Node Functionality

The Find And Replace node searches for specific words, phrases, or patterns in text and replaces each occurrence with new text. It processes multiple replacements in a single operation, making it efficient for batch text editing tasks.

Key capabilities include:

* Multiple replacements in one operation
* Support for exact match replacements
* Basic regular expression pattern matching (limited support)

## When to Use

The Find And Replace node is particularly valuable in scenarios requiring text transformation and standardization. Common use cases include:

* **Content Updating**: Replace outdated terminology with current terms
* **Data Cleaning**: Remove or replace unwanted characters and formatting
* **Text Standardization**: Ensure consistent terminology across documents
* **Template Customization**: Replace placeholder text with personalized content
* **Format Conversion**: Transform data from one format to another

**Some specific examples**:

* Replacing product names across marketing materials
* Standardizing date formats in exported data
* Removing sensitive information from documents
* Converting between US and UK English spelling
* Replacing placeholders like `{{name}}` with actual customer names

## Using Regular Expressions

The Find And Replace node supports basic regular expressions (regex) for pattern matching. When using regex:

* Regular expression patterns **must** be wrapped in forward slashes (`/pattern/`) with any flags at the end
* Without the proper slashes, the node will treat the pattern as literal text

### Limitations

* **Capture groups are not supported**: While regex patterns can match text, you cannot use capture groups (`$1`, `$2`, etc.) in the replacement text
* For complex regex replacements with capture groups, use the [Run Code node](https://docs.gumloop.com/nodes/advanced/run_code) or the [custom node](https://www.gumloop.com/custom-nodes/builder) feature with Python instead

### Working Regex Examples

1. **Case-Insensitive Matching**

```text theme={"dark"}
Input text: "I like eating apples and APPLE pie"
Find word: /apple/i
Replace with: "orange"
Output: "I like eating oranges and orange pie"
Pattern explanation: The 'i' flag makes the pattern match regardless of case
```

2. **Clean Up Extra Spaces**

```text theme={"dark"}
Input text: "Product    Name:     iPhone    14"
Find word: /\s{2,}/g
Replace with: " "
Output: "Product Name: iPhone 14"
Pattern explanation: \s matches any whitespace, {2,} means 2 or more occurrences
```

3. **Remove HTML Tags**

```text theme={"dark"}
Input text: "<p>Hello <strong>world</strong>! This is <em>important</em>.</p>"
Find word: /<[^>]*>/g
Replace with: ""
Output: "Hello world! This is important."
Pattern explanation: < matches opening bracket, [^>]* matches any character except >, > matches closing bracket
```

4. **Redact Phone Numbers**

```text theme={"dark"}
Input text: "Call me at (555) 123-4567 or (999) 888-7777"
Find word: /\(\d{3}\) \d{3}-\d{4}/g
Replace with: "[REDACTED]"
Output: "Call me at [REDACTED] or [REDACTED]"
Pattern explanation: \( matches literal parenthesis, \d{3} matches exactly 3 digits
```

5. **Remove Line Breaks**

```text theme={"dark"}
Input text: "First line\nSecond line\r\nThird line"
Find word: /\r?\n/g
Replace with: " "
Output: "First line Second line Third line"
Note: Removes all line breaks (both \n and \r\n) and replaces with spaces
Pattern explanation: \r? matches optional carriage return, \n matches newline
```

### Common Regex Flags

* `i`: Case-insensitive matching
* `g`: Global matching (replace all occurrences)
* `s`: Dotall mode (makes `.` match newlines)
* `m`: Multiline mode (makes `^` and `$` match line boundaries)

## Loop Mode Pattern

```text theme={"dark"}
Input: List of customer emails with placeholders
Process: Replace standard placeholders with personalized information
Output: List of personalized emails ready to send
```

## Alternative for Complex Replacements

For more advanced regex operations, especially those requiring capture groups, use the **Run Code node** with Python:

```python theme={"dark"}
import re

# Example: Convert date format using capture groups
text = "Date: 12-31-2023"
result = re.sub(r'(\d{2})-(\d{2})-(\d{4})', r'\3-\1-\2', text)
# Output: "Date: 2023-12-31"

return result
```

## Important Considerations

1. Replacements are processed in the order they are defined
2. Multiple replacements can interfere with each other if not planned carefully
3. Case-sensitive by default (use regex with 'i' flag for case-insensitive)
4. Regex patterns require proper slashes (`/pattern/`) to be interpreted correctly
5. **Capture groups are not supported** - use the [Run Code node](https://docs.gumloop.com/nodes/advanced/run_code) or the [custom node](https://www.gumloop.com/custom-nodes/builder) feature with Python instead
6. For multiline text operations, use the 's' flag to make '.' match newlines

## Troubleshooting Common Issues

| Issue                       | Solution                                                   |
| --------------------------- | ---------------------------------------------------------- |
| Regex not working           | Ensure pattern is wrapped in `/pattern/` with proper flags |
| Capture groups not working  | Use Run Code node with Python regex instead                |
| Multiline text not matching | Add 's' flag: `/pattern/s`                                 |
| Case sensitivity issues     | Add 'i' flag: `/pattern/i`                                 |
| Only first match replaced   | Add 'g' flag: `/pattern/g`                                 |

In summary, the Find And Replace node is a powerful text manipulation tool for basic to moderate text processing needs. For advanced regex operations requiring capture groups, consider using the Run Code node with Python for full regex functionality.
