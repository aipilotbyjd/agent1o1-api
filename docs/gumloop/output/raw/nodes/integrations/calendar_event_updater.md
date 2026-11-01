> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Google Calendar Event Updater

This document outlines the functionality and characteristics of the Google Calendar Event Updater node, which enables automated updating of calendar events in Google Calendar.

## Node Inputs

### Required Fields

* **Calendar**: Select target Google Calendar
* **Event ID**: The unique identifier of the event to update
  * Obtain this from the Google Calendar Event Reader node
  * Alternatively, get it from the Google Calendar URL by decoding the base64 ID in the event URL

### Optional Fields (Based on Selected Update Options)

The node allows you to update specific aspects of an event by selecting which fields to modify:

* **Event Title**: New title for the event
* **Event Description**: New description text (supports HTML formatting)
* **Event Location**: New physical or virtual location
* **Event Start Time (UTC)**: New start date/time in UTC format
* **Event End Time (UTC)**: New end date/time in UTC format
* **Add Attendees**: Email addresses to add to the event (comma-separated)
* **Remove Attendees**: Email addresses to remove from the event (comma-separated)

> **Important**: Selected fields will completely overwrite existing values. To append or modify existing content, you must include the original content in your update (see examples below).

## Node Output

* **Status**: Message indicating success or failure of the update operation

## Node Functionality

The Google Calendar Event Updater node modifies existing events in Google Calendar using their unique Event ID.

**Key features include**:

* Selective field updates (only update what you need)
* Attendee management (add/remove)
* Time adjustments
* Location and description modifications
* Secure authentication with Gumloop

## When To Use

The Google Calendar Event Updater node is particularly valuable for calendar automation workflows. Common use cases include:

* **Meeting Standardization**: Update meetings to use standard formats or locations
* **Attendee Management**: Add team members to relevant meetings based on criteria
* **Location Updates**: Change meeting rooms or add virtual meeting links
* **Schedule Adjustments**: Modify event times based on availability
* **Description Enrichment**: Add supplementary information to event descriptions

## Common Use Cases

### 1. [Basic Event Update](https://www.gumloop.com/pipeline?workbook_id=uwt6HoSd9qpZAvhLMWANiM)

This simple example updates a single event by first finding it, then modifying it:

```text theme={"dark"}
Google Calendar Event Reader → Google Calendar Event Updater
```

[Workflow Link](https://www.gumloop.com/pipeline?workbook_id=uwt6HoSd9qpZAvhLMWANiM)

**Setup:**

* **Event Reader:**
  * Search by Title: "Meeting"
  * Number of Events: 1
  * Select Event IDs, Event Description outputs
* **Event Updater:**
  * Fields: Event Description, Add Attendees
  * Event Description: Same as input + " (Updated with additional information)"
  * Add Attendees: "[team@company.com](mailto:team@company.com)"

**Purpose:** Find a specific meeting and add team members while updating the description

### 2. Conditional Event Updates

This example uses conditional logic to update events differently based on criteria:

```text theme={"dark"}
Google Calendar Event Reader [Trigger] → If-Else → Google Calendar Event Updater
```

<div align="center">
  ```mermaid theme={"dark"}
  %%{init: {'theme':'neutral', 'themeVariables': { 'primaryColor': '#f5f5f5', 'primaryBorderColor': '#ddd'}}}%%
  flowchart LR
      A["Calendar Event Reader<br>[Trigger]"] --> B{"Contains 'Client'<br>in title?"}
      B -->|Yes| C["Calendar Event Updater<br>(Add client preparation)"]
      B -->|No| D["Calendar Event Updater<br>(Add standard notes)"]
  ```
</div>

**Setup:**

* **Event Reader Trigger:**
  * Minutes Before Event: 60
  * Select Event IDs, Event Titles outputs
* **If-Else Node:**
  * Condition: Event Title contains "Client"
* **Client Event Updater:**
  * Update Description: Add client meeting preparation template
* **Standard Event Updater:**
  * Update Description: Add standard meeting notes template

**Purpose:** Automatically prepare different meeting types with appropriate information an hour before they start

## Practical Examples

### Example 1: Appending to Existing Content

To preserve existing content while adding new information, use the original content in your update:

```text theme={"dark"}
// Reader Node configuration:
Select outputs: Event IDs, Event Description

// Updater Node configuration:
Event Description: {{Event Description}} + " [UPDATED: Additional information about the meeting]"
```

This approach preserves the original description and appends new text to it.

### Example 2: Adding Zoom Links to Meetings

Find all meetings without location information and add standard Zoom details:

```text theme={"dark"}
// Reader Node configuration:
Search by Title: "Meeting"
Select outputs: Event IDs, Event Location

// Filter Node configuration:
Condition: Event Location is empty

// Updater Node configuration:
Event Location: "Zoom: https://zoom.us/j/123456789"
Event Description: {{Event Description}} + "\n\nZoom meeting link: https://zoom.us/j/123456789"
```

### Example 3: Rescheduling Events

Reschedule events to start and end 30 minutes later:

```text theme={"dark"}
// Use Ask AI node to modify timestamps
Input: {{Event Start Time}} and {{Event End Time}}
Prompt: "Add 30 minutes to these UTC timestamps while preserving format"

// Updater Node configuration:
Event Start Time: {{Modified Start Time from AI}}
Event End Time: {{Modified End Time from AI}}
```

## Working with Google Calendar Event Reader

The Calendar Event Updater works seamlessly with the Calendar Event Reader, forming a powerful combination for event management:

1. **Finding Events to Update:**
   * Use Calendar Event Reader to search for events by title, date range, etc.
   * Always select "Event IDs" in the Event Reader outputs
   * Consider using Number of Events = 1 for precise single-event updates

2. **Passing Data Between Nodes:**
   * Connect the Event IDs output from Reader to Event ID input on Updater
   * Also connect any fields you want to preserve or modify (descriptions, locations, etc.)

3. **Batch Updates with Loop Mode:**
   * To update multiple events, use the Calendar Event Reader with Number of Events > 1
   * Enable Loop Mode on the Calendar Event Updater
   * This processes each event individually with the same update pattern

## Important Considerations

1. **Complete Replacement:** Fields you select to update will completely replace existing content. To preserve and add to content, you must include the original content in your update.

   > In order to update existing content and add information to it, you can pass that data field from the event reader node

2. **Authentication:** Requires authentication with Google - Set up in the [Connectors page](https://www.gumloop.com/personal/connectors).

3. **Time Format:** When updating start/end times, use ISO format (e.g., "2025-05-01T14:00:00Z").

4. **Selective Updates:** Only select the fields you actually want to change. Unselected fields remain untouched.

5. **Guest Permissions:** Some calendars may have restrictions on who can modify events or add attendees.

6. **Event ID Requirement:** The Event ID is mandatory and must be valid. Always test with known events first.

## Troubleshooting Common Issues

| Issue                      | Possible Cause               | Solution                                                    |
| -------------------------- | ---------------------------- | ----------------------------------------------------------- |
| "Event not found"          | Invalid Event ID             | Ensure Event ID is correctly passed from Event Reader       |
| "Insufficient permissions" | Calendar permission settings | Check that your Google account has edit rights to the event |
| Invalid time format        | Incorrect datetime format    | Ensure times use UTC format (YYYY-MM-DDTHH:MM:SSZ)          |

In summary, the Google Calendar Event Updater node offers powerful capabilities for programmatically modifying calendar events based on specific criteria. When paired with the Google Calendar Event Reader, it enables sophisticated calendar automation workflows that can save significant time and standardize your calendar management processes.
