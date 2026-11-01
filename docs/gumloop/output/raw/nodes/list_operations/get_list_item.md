> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Get List Item

This document explains the Get List Item node, which retrieves a specific item from a list by its position.

## Node Inputs

### Required Fields

* **List**: List of items to retrieve a specific item from
* **Index**: Position of desired item (starts at 0)

## Node Output

* **Item**: Selected element from the list

## Example Use Cases

1. **API Response Processing**:

```text theme={"dark"}
List: [response1, response2, response3]
Index: 0
Result: First API response only
```

2. **Data Extraction**:

```text theme={"dark"}
List: ["Name", "Email", "Phone"]
Index: 1
Result: "Email" field only
```

3. **Result Selection**:

```text theme={"dark"}
List: [bestMatch, secondBest, thirdBest]
Index: 0
Result: Best matching result
```

## Index Examples

```text theme={"dark"}
Index: 0 → First item
Index: 1 → Second item
Index: -1 → Last item
Index: -2 → Second-to-last item
```

## Important Considerations

1. Index starts at 0
2. Negative indices allowed
3. Invalid index causes error
4. Works in loop mode
5. Maintains data type

In summary, the Get List Item node helps select specific elements from lists, essential for data processing and workflow control in Gumloop.
