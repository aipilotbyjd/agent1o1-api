> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Discord Message Reader

This document outlines the functionality and characteristics of the Discord Message Reader node, which enables automated message retrieval from Discord channels.

## Node Inputs

### Required Fields

* **Server**: Select Discord server to read from
* **Channel**: Choose specific channel within the server

### Optional Fields

* **Message Count**: Number of messages to retrieve (default: 10)
* **Use Dates**: Toggle to filter messages by date range
  * **Start Date**: Beginning of date range
  * **End Date**: End of date range
* **Message Information**: Select what to retrieve:
  * Messages
  * Thread IDs
  * Attachment Names
* **Ignore Bot Messages**: Option to exclude bot messages

## Node Output

* **Messages**: List of message content and related information based on selected Message Information options

## Node Functionality

The Discord Message Reader node retrieves messages from specified Discord channels.

**Key features include**:

* Flexible message filtering
* Date range support
* Bot message filtering
* Attachment handling
* Thread tracking
* Secure authentication with Gumloop

## When To Use

The Discord Message Reader node is essential when you need to monitor or extract information from Discord channels. Common use cases include:

* **Community Management**: Track discussions and announcements
* **Support Monitoring**: Follow support channel messages
* **Event Tracking**: Collect event-related messages
* **Resource Gathering**: Extract shared files and attachments

**Some specific examples**:

* Logging announcement channel updates
* Monitoring support requests in help channels
* Collecting shared resources from community channels
* Tracking event discussions and coordination

## Important Considerations:

1. Requires Authentication with Discord - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Must have appropriate server and channel permissions

In summary, the Discord Message Reader node provides comprehensive access to Discord channel content, supporting various filtering options and information types for effective message monitoring and extraction.
