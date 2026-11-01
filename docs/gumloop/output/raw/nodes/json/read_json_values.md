> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# JSON Reader

This document outlines the functionality and usage of the JSON Reader node, which enables extracting specific values from JSON data.

## Node Inputs

* **JSON String**: A string containing valid JSON data from which values will be extracted
* **Keys**: Name of the key(s) that you want to read from the JSON structure (supports dot notation)

## Node Output

* **Key Values**: Each key defined in the input is exposed as a separate output value

## Node Functionality

The JSON Reader node extracts specified pieces of information from a JSON structure. It takes a JSON string and a list of keys as input, then outputs the values associated with those keys.

The node supports dot notation for accessing nested properties within the JSON structure, allowing you to extract deeply nested values without using multiple JSON Reader nodes.

## When to Use

Use the JSON Reader node when you need to:

* Extract specific fields from API responses
* Process JSON data from files or other sources
* Convert JSON values into individual outputs for further processing
* Filter specific keys from larger JSON structures
* Access nested JSON properties using dot notation

## Working with Nested JSON using Dot Notation

The JSON Reader node supports dot notation to easily access nested properties within JSON objects. This eliminates the need for chaining multiple JSON Reader nodes.

### Dot Notation Syntax

Use periods (`.`) to separate nested property names:

* `parent.child` accesses a child property
* `parent.child.grandchild` accesses a deeply nested property
* `array.0.property` accesses the first item in an array and its property

### Example: Using Dot Notation

Given this JSON structure:

```json theme={"dark"}
{
  "success": true,
  "data": {
    "title": "Product Update",
    "content": "New features released",
    "metadata": {
      "author": "John Doe",
      "date": "2024-01-30"
    },
    "tags": ["product", "update", "features"]
  }
}
```

You can extract nested values using these keys:

* `data.title` → "Product Update"
* `data.metadata.author` → "John Doe"
* `data.metadata.date` → "2024-01-30"
* `data.tags.0` → "product" (first tag in the array)

## Example Use Cases

### 1. API Response Processing with Nested Properties

```json theme={"dark"}
{
  "status": "success",
  "results": {
    "user": {
      "id": "12345",
      "profile": {
        "username": "johndoe",
        "email": "john@example.com"
      }
    }
  }
}
```

Keys to extract:

* `status`
* `results.user.id`
* `results.user.profile.username`
* `results.user.profile.email`

### 2. Configuration Data with Nested Settings

```json theme={"dark"}
{
  "settings": {
    "appearance": {
      "theme": "dark",
      "fontSize": "medium"
    },
    "preferences": {
      "language": "en",
      "notifications": true
    }
  },
  "version": "2.0.0"
}
```

Keys to extract:

* `version`
* `settings.appearance.theme`
* `settings.preferences.language`
* `settings.preferences.notifications`

### 3. Working with Arrays and Nested Objects

```json theme={"dark"}
{
  "orders": [
    {
      "id": "ORD-001",
      "customer": {
        "name": "Alice Smith",
        "email": "alice@example.com"
      },
      "items": [
        {
          "product": "Laptop",
          "price": 999.99
        },
        {
          "product": "Mouse",
          "price": 24.99
        }
      ]
    }
  ]
}
```

Keys to extract:

* `orders.0.id` → "ORD-001"
* `orders.0.customer.name` → "Alice Smith"
* `orders.0.items.0.product` → "Laptop"
* `orders.0.items.1.price` → 24.99

## Working with Arrays

For processing entire arrays or more complex array operations:

1. Extract the array itself using its path in the JSON
2. Use List Operations nodes to manipulate the array data
3. For iterating over all array items, use Loop Mode on subsequent nodes

## Important Considerations

1. **Key Sensitivity**: Keys are case-sensitive
2. **Dot Notation Format**: Ensure there are no spaces in the dot notation (use `parent.child`, not `parent . child`)
3. **Invalid JSON**: Ensure your JSON string is valid before processing
4. **Array Indexing**: Array indices start at 0 (e.g., `items.0` for the first item)

## Related Nodes

* [Write JSON Value](https://docs.gumloop.com/nodes/json/write_json_value): For modifying JSON data
* [Call API](https://docs.gumloop.com/nodes/advanced/call_api): For fetching JSON from APIs
* [If/Else](https://docs.gumloop.com/nodes/flow_basics/if_else): For conditional JSON processing
* [Error Shield](https://docs.gumloop.com/nodes/flow_basics/error_shield): For handling JSON parsing errors
* [Custom Node](https://docs.gumloop.com/nodes/custom_node_details): For creating a customized JSON parser with more advanced functionality
