> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Discord Message Sender

This document outlines the functionality and characteristics of the Discord Message Sender node, which enables automated message sending to Discord channels.

## Node Inputs

### Required Fields

* **Server**: Select Discord server
* **Channel**: Choose specific channel within the server
* **Message**: Content to send

### Optional Fields

* **Use Channel/Thread ID**: Toggle to send to specific thread
* **Attachments**: Files to include with message. Multiple files can be added as comma separated values

## Node Output

* Message send confirmation

## Node Functionality

The Discord Message Sender node sends messages to specified Discord channels or threads.

**Key features include**:

* Text message support
* File attachment capability
* Thread targeting
* Loop Mode for batch sending
* Channel or thread sending
* Secure authentication with Gumloop

## When To Use

The Discord Message Sender node is essential when you need to automate Discord communications. Common use cases include:

* **Announcements**: Send automated updates to announcement channels
* **Notifications**: Post automated alerts or notifications
* **Report Sharing**: Share automated reports with attachments
* **Updates**: Send regular status updates to teams

**Some specific examples**:

* Posting daily status updates to team channels
* Sending automated alerts for system events
* Sharing generated reports with relevant teams
* Posting scheduled announcements

## Important Considerations:

1. Requires Authentication with Discord - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Must have appropriate channel permissions
3. Thread ID required for thread responses \[you can pass this dynamically using the 'Discord Message Reader' node]

In summary, the Discord Message Sender node provides reliable message sending capabilities for Discord, supporting both simple text messages and file attachments in channels or threads.
