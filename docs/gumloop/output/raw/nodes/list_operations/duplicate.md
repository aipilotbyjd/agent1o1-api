> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Duplicate

This document explains the Duplicate node, which creates a list by repeating a single value.

## Node Inputs

### Required Fields

* **Input**: Value to duplicate
* **List Size to Match**: Match another list's size

### Optional Fields

* **Specify List Size**: Enable manual size control
  * **List Size**: Number of duplicates to create

> Note: If you've a list with an unknown size (eg. using the 'Extract List' option on the Extract Data node), you can directly pass that list onto the 'List size to Match' input to create a duplicate list matching that size.

## Node Output

* **Duplicated List**: List of repeated values

## Node Functionality

The Duplicate node:

* Repeats single value
* Creates uniform lists
* Matches list sizes
* Preserves data type
* Supports loop mode

## Common Use Cases

1. **Default Values**:

```text theme={"dark"}
Input: "Pending"
Size: 5
Result: ["Pending", "Pending", "Pending", "Pending", "Pending"]
```

2. **Match Reference**:

```text theme={"dark"}
Input: "Unknown"
Reference: [1, 2, 3]
Result: ["Unknown", "Unknown", "Unknown"]
```

3. **Template Creation**:

```text theme={"dark"}
Input: "{{placeholder}}"
Size: 3
Result: ["{{placeholder}}", "{{placeholder}}", "{{placeholder}}"]
```

## Additional Information

[Video Tutorial](https://www.youtube.com/watch?v=FHZ3Ypwjw0c)

In summary, the Duplicate node helps create lists of repeated values, perfect for initialization and placeholder data in your Gumloop workflows.
