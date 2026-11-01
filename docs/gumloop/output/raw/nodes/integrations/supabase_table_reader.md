> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Supabase Table Reader

This document outlines the functionality and characteristics of the Supabase Table Reader node, which enables automated data retrieval from Supabase tables.

## Node Inputs

### Required Fields

* **Project**: Select your Supabase project
* **Table**: Choose the table to read from

### Optional Field

* **Number of Records**: Limit the number of records to retrieve (default: 10)

## Node Output

Each column in your table becomes an output containing the corresponding data.

## Node Functionality

The Supabase Table Reader node retrieves data from specified Supabase tables.

**Key features include**:

* Direct table access
* Customizable record limits
* Dynamic column outputs
* Secure authentication with Gumloop

## When To Use

The Supabase Table Reader node is essential when you need to access data stored in Supabase. Common use cases include:

* **Data Retrieval**: Access stored records for analysis, reporting, or integration with other services
  * Pulling sales data for monthly performance reports
  * Retrieving customer records for CRM integration
  * Accessing inventory levels for stock management

* **User Information**: Work with user-related data stored in your database
  * Fetching user preferences for personalization
  * Reading user activity logs for behavior analysis

* **Content Management**: Access content and settings stored in your database
  * Reading blog posts or articles for publishing
  * Retrieving product catalogs for e-commerce operations

* **Status Checking**: Monitor and track various system states
  * Checking order statuses for fulfillment
  * Monitoring subscription states for billing
  * Tracking project milestones for automation

## Example

Think of Supabase as your project's database, like a collection of spreadsheets:

1. Choose where to read from:
   * Project: "My App" (like picking which spreadsheet file)
   * Table: "Users" (like selecting which sheet to read)

2. Control how much to read:
   * Number of Records: 10 (will read first 10 rows)

The node will then output data from each column in your table, like getting specific columns from a spreadsheet.

## Important Considerations:

1. Requires Authentication with Supabase - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Tables must exist in your project
3. Output format matches table structure
4. Default limit is 10 records

In summary, the Supabase Table Reader node provides straightforward access to your Supabase database tables, making it easy to retrieve and use stored data in your workflows.
