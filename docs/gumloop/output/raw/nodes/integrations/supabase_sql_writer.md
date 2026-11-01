> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Supabase SQL Writer

This document outlines the functionality and characteristics of the Supabase SQL Writer node, which enables executing SQL queries on Supabase databases.

## Node Inputs

### Required Fields

* **Project**: Select your Supabase project
* **SQL Query**: Your SQL command

### Optional Field

* **Number of Records**: Limit the number of returned records (default: 10, max: 10,000)

## Node Output

* **Query Output**: Results from your SQL query as a list

## Node Functionality

The Supabase SQL Writer node executes custom SQL queries on your Supabase database.

**Key features include**:

* Full SQL query support
* Customizable record limits
* Direct database access
* Secure authentication with Gumloop

## Example

Think of SQL queries like giving specific instructions to your database:

* Simple SELECT query:

```sql theme={"dark"}
SELECT name, email 
FROM users 
WHERE signup_date > '2024-01-01'
```

## Important Considerations:

1. Requires Authentication with Supabase - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. SQL queries must be properly formatted

In summary, the Supabase SQL Writer node provides powerful database manipulation capabilities through direct SQL queries, perfect for complex data operations and custom database management tasks.
