> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Firestore Writer

This document outlines the functionality and characteristics of the Firestore Writer node, which enables automated document creation and updates in Google Firestore.

## Node Inputs

### Required Fields

* **Project ID**: Your Google Firestore project identifier
* **Collection ID**: Target collection for document storage
* **JSON Data**: Data to write in JSON format

### Optional Fields

* **Database ID**: Specific database (default: "(default)")
* **Document ID**: Custom identifier for the document

## Node Output

* **Status**: Indicates whether the write operation was successful or not. Outputs “True” if successful and “False” if not.

## Node Functionality

The Firestore Writer node creates or updates documents in Firestore collections.

**Key features include**:

* JSON data support
* Automatic ID generation
* Document updating
* Loop Mode support for batch write operations
* Secure authentication with Gumloop

## When To Use

The Firestore Writer node is valuable for database operations. Common use cases include:

* **User Data**: Store user profiles and preferences
* **Application State**: Save application configurations
* **Event Logging**: Record system events
* **Data Collection**: Store form submissions

**Some specific examples**:

* Saving user registration data
* Storing application settings
* Recording transaction history
* Maintaining customer records

## Example

To store user information:

1. Configuration:

```json theme={"dark"}
{
    "project_id": "example-49dfa",
    "collection_id": "users",
    "json_data": {
        "name": "John",
        "age": 30,
        "email": "john@example.com",
        "settings": {
            "notifications": true,
            "theme": "dark"
        }
    }
}
```

2. Document will be created with either:
   * Generated ID (if Document ID is empty)
   * Specified ID (if Document ID is provided)

## Important Considerations:

1. Requires Authentication with Google Cloud - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. JSON must be properly formatted
3. Document IDs must be valid strings

In summary, the Firestore Writer node provides reliable document creation and updating in Google Firestore, supporting various data structures and automatic collection management.
