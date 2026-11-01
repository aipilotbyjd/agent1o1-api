> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Supabase Table Writer

This document outlines the functionality and characteristics of the Supabase Table Writer node, which enables automated data writing to Supabase tables.

## Node Inputs

### Required Fields

* **Project**: Select your Supabase project
* **Table**: Choose the table to write to

### Optional Fields

* **Upsert Mode**: Toggle to update existing rows instead of creating duplicates
* **Ignore Duplicates**: Skip duplicate rows when Upsert Mode is enabled

### Dynamic Fields

Connect your node outputs to any column in your Supabase table.

## Node Output

Success/failure status of the write operation.

## When To Use

The Supabase Table Writer node is essential when you need to store data in Supabase. Common use cases include:

* **User Management**: Store and update user profiles
  * Saving user preferences from form submissions
  * Updating profile information from external sources

* **Data Collection**: Capture and store information
  * Recording form submissions from your website
  * Storing analytics data from various sources

* **Content Management**: Maintain dynamic content
  * Creating new blog posts or articles
  * Updating product information in catalogs

* **Transaction Recording**: Store business transactions
  * Recording payment transactions from invoices
  * Logging customer interactions

## Example

Think of writing to Supabase like adding rows to a spreadsheet:

1. Choose where to write:
   * Project: "My App" (like picking which spreadsheet file)
   * Table: "Users" (like selecting which sheet)

2. Configure how to handle duplicates:
   * Upsert Mode: Yes (update if record exists)
   * Ignore Duplicates: No (always update existing records)

3. Connect your data:
   * Each column in your table appears as an input
   * Connect relevant node outputs to these inputs

## Important Considerations:

* Requires Authentication with Supabase - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)

In summary, the Supabase Table Writer node provides reliable data writing capabilities to your Supabase database, with flexible options for handling duplicates and array data.
