> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Calendar Event Creator

This document outlines the functionality and characteristics of the Google Calendar Event Creator node, which enables automated event creation and invitation management in Google Calendar.

## Node Inputs

### Required Fields

* **Calendar**: Select target Google Calendar
* **Event Name**: Title of the calendar event
* **Date and Time**: Event start time (ISO format: YYYY-MM-DDTHH:MM:SS)
  * You can connect this directly with the `Datetime` node.
* **Duration**: Length of event in minutes

### Optional Fields

* **Event Description**: Details about the event
* **Invitee Emails**: Email addresses of participants (comma-separated)
* **Location**: Physical address or virtual meeting link

### Show More Options

Under the "Show More Options" section, you can configure additional settings:

#### Event Type

Select the type of calendar entry:

* **Event**: Standard calendar event (default)
* **Working Location**: Indicates where you'll be working (office, home, etc.)
* **Out of Office**: Shows you're unavailable during this time
* **Task**: To-do item with a deadline

#### Working Location Types

When "Working Location" is selected as the Event Type, you can specify:

* **Home Office**: Indicates you're working from home
* **Office**: Indicates you're working from the primary office location
* **Custom Location**: Allows you to specify a different working location

## Node Functionality

The Google Calendar Event Creator node automates event creation and invitation management.

**Key features include**:

* Multiple event type options
* Loop Mode for creating multiple events
* Secure authentication with Gumloop

## When To Use

The Google Calendar Event Creator node is particularly valuable for automated scheduling needs. Common use cases include:

* **Meeting Scheduling**: Create recurring team meetings or one-on-ones
* **Event Management**: Schedule workshops or training sessions
* **Project Planning**: Set up project milestone reviews
* **Interview Coordination**: Schedule candidate interviews
* **Working Location Management**: Track remote vs. in-office work schedules
* **Time Off Tracking**: Automate PTO and out-of-office scheduling

## Example Workflow

```text theme={"dark"}
Google Sheets Reader → Google Calendar Event Creator → Send Email Notification
Setup:
- Read event details from spreadsheet rows
- Create events in batch using Loop Mode
- Send confirmation emails to organizers
```

## Implementation Example

To schedule a team meeting:

* Event Name: "Weekly Team Sync"
* Date and Time: "2024-01-15T15:30:00"
* Duration: "60"
* Invitee Emails: "[team@company.com](mailto:team@company.com)"
* Location: "[https://meet.google.com/](https://meet.google.com/)"
* Event Type: "Event"

## Important Considerations

1. Requires Authentication with Google - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. Date and Time must be in ISO format (YYYY-MM-DDTHH:MM:SS)
3. All invitee emails must be valid
4. Duration is specified in minutes
5. Virtual meeting links must be complete URLs
6. Different event types have different visibility and behavior in Google Calendar

In summary, the Google Calendar Event Creator node simplifies event scheduling by automating calendar event creation and invitation management, perfect for coordinating meetings and managing schedules at scale. The flexible event types allow for different calendar entry formats based on your specific needs.
