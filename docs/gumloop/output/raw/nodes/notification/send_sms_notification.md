> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Send SMS Notification

This document explains the Send SMS Notification node, which sends text messages to phone numbers.

## Node Inputs

### Required Fields

* **Phone Number**: Recipient's number (international format)
  Example: +441632960675
* **Message**: Text content to send

### Optional Fields

* **Delay Send**: Schedule message for later

  * **Send Time**: When to send scheduled message

  > Format: 2024-04-24T12:00:00

## Node Output

* Confirmation status of the SMS

## Node Functionality

The Send SMS node:

* Sends text messages
* Handles international numbers
* Supports scheduling
* Enables batch sending
* Uses Gumloop's phone number

## When to Use

Use this node when you need to:

1. **Send Alerts**:
   * System notifications
   * Emergency updates
   * Status changes

2. **Customer Communication**:
   * Order updates
   * Appointment reminders
   * Delivery notifications

3. **Team Coordination**:
   * Task assignments
   * Meeting reminders
   * Schedule changes

## Example Use Cases

1. **Delivery Updates**:

```text theme={"dark"}
Number: +441632960675
Message: "Your order #123 will arrive in 30 minutes"
```

2. **Appointment Reminders**:

```text theme={"dark"}
Number: +441632960675
Message: "Reminder: Your appointment is tomorrow at 2 PM"
Delay Send: 24 hours before
```

3. **System Alerts**:

```text theme={"dark"}
Number: +441632960675
Message: "Alert: Server CPU usage at 90%"
```

## Scheduling Features

1. **Immediate Send**:

```text theme={"dark"}
Delay Send: No
Result: Sends right away
```

2. **Future Send**:

```text theme={"dark"}
Delay Send: Yes
Send Time: 2024-04-24T12:00:00
Must be: 15+ minutes ahead
Maximum: 35 days in future
```

## Important Considerations

1. Use international format
2. SMS messages must be scheduled at least 15 minutes in advance and at a maximum of 35 days into the future

In summary, the Send SMS Notification node helps automate text message communications in your Gumloop workflows.
