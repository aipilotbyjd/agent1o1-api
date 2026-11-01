> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Calendar Event Reader

This document outlines the functionality and characteristics of the Google Calendar Event Reader node, which enables automated reading of calendar events from Google Calendar.

## Node Inputs

### Required Fields

* **Calendar**: Select target Google Calendar
* **Date Range Settings**: Choose between relative or exact dates

### More Options

#### Credentials to use

* Select which Google Calendar credentials to use
* Options include Personal Default, Team Default, or specific credentials
* Using the correct credential ensures access to the appropriate calendar

#### Search by Title

* **Filter events by searching for text in event titles**
* Exact or partial match text search
* Case-insensitive (e.g., "Meeting" will match "MEETING" or "meeting")
* Leave empty to retrieve all events without title filtering
* Can be configured dynamically as an input
* **Important**: When using Search by Title, ensure your date range covers when the events exist
* For maximum reliability, consider using exact dates with a broad range (e.g., entire year) when searching by title

#### Number of Events

* **Maximum number of events to retrieve**
* Limits the total number of events returned from the specified date range
* Leave empty for no limit (will return all events in the date range)
* Useful for managing large calendars or limiting processing
* **Note**: Output type changes based on this setting:
  * When set to 1: Outputs as single text values
  * When set to more than 1: Outputs as lists

### Date Range Options

#### Relative Dates

* When "Use Exact Dates" is unchecked, you can specify a relative time range
* Examples: "next 7 days", "last 30 days", "next month"
* This is dynamic and adjusts based on when the workflow runs

#### Exact Dates

* Check "Use Exact Dates" to specify fixed start and end dates
* Use "Start Date (UTC)" and "End Date (UTC)" fields
* Dates must be in UTC format (e.g., "2024-01-01T00:00:00Z")
* This is static and always looks at the same date range

> You can pass this dynamically using the `Datetime` node.

### Event Information Options

Select which event data to retrieve:

* Event Names
* Event IDs
  > Unique identifiers for each event that can be used with the Calendar Event Updater node
* Event Start Times
* Event End Times
* Event Durations
* Event Locations
* Event Descriptions
* Attendee Emails
* Attendee Statuses
* User Statuses
* Organizer Emails

### Configure Inputs

Make these parameters dynamic by enabling them in "Configure Inputs":

* **Search by Title**: String to filter events based on title text
* **Number of Events**: Maximum number of events to retrieve
* **Calendar**: The specific calendar to read from
* **Minutes Before Event**: (Only in trigger mode) Time before events to trigger the workflow

## Node Output

The output format changes based on the Number of Events setting:

### When Number of Events > 1 (or empty)

* All outputs are provided as lists (`string[]`), maintaining consistent event order
* Selected event information fields appear as individual outputs
* Each output contains data for all events in the specified time range

### When Number of Events = 1

* All outputs are provided as single text values (not lists)
* Selected event information fields appear as individual outputs
* Each output contains data for the single retrieved event

### Available Outputs

* All selected Event Information options will appear as outputs
* **Event IDs**: Unique identifiers that can be used with Calendar Event Updater

## Node Functionality

The Google Calendar Event Reader node retrieves event information within a specified time window with filtering options.

**Key features include**:

* Flexible event information selection
* UTC time standardization
* Customizable date ranges (relative or exact)
* Title text filtering
* Event quantity limitation
* Secure authentication with Gumloop

## When To Use

The Google Calendar Event Reader node is particularly valuable for calendar data analysis and automation. Common use cases include:

* **Attendance Tracking**: Monitor meeting participation patterns
* **Schedule Analysis**: Review time allocation across different activities
* **Event Reporting**: Generate summaries of past or upcoming events
* **Resource Planning**: Analyze room or resource usage patterns
* **Event Updates**: Use with Calendar Event Updater to modify existing events

## Common Use Cases

### 1. Meeting Summary Automation

```text theme={"dark"}
Google Calendar Event Reader → Ask AI → Slack Message Sender
Setup:
- Date Range: "next 7 days"
- Information: Event Names, Start Times, Descriptions
- Search by Title: "Team" (to filter for team meetings only)
Purpose: Send automated weekly team meeting summaries to your team
```

### 2. Attendance Monitoring

```text theme={"dark"}
Google Calendar Event Reader → Extract Data → Google Sheets Writer
Setup:
- Date Range: "last 30 days"
- Information: Event Names, Attendee Emails, Attendee Statuses, User Statuses
- Number of Events: 100 (to limit processing to the 100 most recent meetings)
Purpose: Track meeting attendance patterns for team analytics
```

### 3. Event Update Workflow

```text theme={"dark"}
Google Calendar Event Reader → Calendar Event Updater
Setup:
- Information: Event Names, Event IDs, Event Locations
- Search by Title: "Interview"
Purpose: Update all interview events to include a standard Zoom link
```

## Trigger Mode

### Configuration

This node can trigger workflows before calendar events:

* Triggers the workflow `X` minutes before every event on your calendar
* Default time is 15 minutes, you can adjust this under the `Minutes Before Event` input
* Can be filtered by title to trigger only for specific types of events

<div align="center">
  <img src="https://mintcdn.com/agenthub/BbHU4APLtf15VcpU/images/event_trigger.png?fit=max&auto=format&n=BbHU4APLtf15VcpU&q=85&s=32a821accf88a097265dd4e4e445d537" alt="Alt text" width="500" data-path="images/event_trigger.png" />
</div>

### Example Trigger Workflow

```text theme={"dark"}
Google Calendar Event Reader [Trigger] → Extract Data → Send Email Notification
Setup:
- Minutes Before Event: 30
- Information: Event Names, Locations, Descriptions
Purpose: Send automated preparation reminders before important client meetings
```

## Best Practices

* Use relative dates for recurring workflows (like daily/weekly checks)
* Use exact dates when you need events from a specific time period
* Use Search by Title to narrow down to specific event types
* Set Number of Events when working with busy calendars to prevent performance issues
* Save Event IDs when you need to update events later in the workflow
* If no dates are specified, the node defaults to last 1 month

## Output Fields Explained

### Standard Fields

* **Event Names**: Titles of the calendar events
* **Event IDs**: Unique identifiers for each event (for use with Calendar Event Updater)
* **Event Start/End Times**: When events begin and end in UTC format
* **Event Durations**: Length of events in hours:minutes format
* **Event Locations**: Physical or virtual location information
* **Organizer Emails**: Email addresses of event creators

### Additional Fields

* **Event Descriptions**: Full text content of the event description field
* **Attendee Emails**: List of all invited participant email addresses.
  > For each event the email addresses are separated by a comma
* **Attendee Statuses**: Response status for each attendee (Yes/No/Maybe).
  > For each event the stauses are separated by a comma
* **User Statuses**: The authenticated user's response status for each event

## Important Considerations:

1. Requires Authentication with Google - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors)
2. The date output is in UTC format
3. At least one Event Information field must be selected
4. Search by Title is case-insensitive and matches partial strings
5. For recurring events, each instance is returned as a separate event
6. The number of events retrieved may be less than specified if fewer events match your criteria
7. Setting Number of Events to 1 changes output types from lists to single text values
8. When using Search by Title, ensure your date range is broad enough to include the events you're searching for

In summary, the Google Calendar Event Reader node provides comprehensive access to calendar data with powerful filtering options, enabling detailed analysis and automation of calendar-based workflows.
