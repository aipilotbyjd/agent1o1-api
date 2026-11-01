> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Current Datetime

## Node Inputs

* **Format**: *Optional*. Specifies the output format of the datetime. Choices include:

  * `Year-Month-Day`
  * `Day-Month-Year`
  * `Month-Day-Year`
  * `Hour:Minute:Second`
  * `Year-Month-Day Hour:Minute:Second` (default)
  * `Day-Month-Year Hour:Minute:Second`
  * `Month-Day-Year Hour:Minute:Second`
  * `Custom` - allows defining a custom format

* **Timezone**: *Optional*. The timezone in which the current datetime will be outputted. If not provided, it defaults to `UTC`. All available timezones are selectable.

### Custom Format

When the `format` is set to `Custom`, you can define your own pattern for the date and time using the `custom_format` field. This pattern uses placeholders to represent different parts of the date and time. Here are some common placeholders you can use:

| Placeholder | Description                                                          |
| ----------- | -------------------------------------------------------------------- |
| `%d`        | Day of the month, for example 01 for the first day of the month.     |
| `%m`        | Month of the year, for example 11 for November.                      |
| `%Y`        | The full year, for example 2022.                                     |
| `%H`        | Hour of the day in 24-hour format, for example 13 for 1 PM.          |
| `%M`        | Minute of the hour, for example 30 for half past the hour.           |
| `%S`        | Second of the minute, for example 45 for 45 seconds past the minute. |
| `%b`        | Abbreviated month name, for example 'Nov' for November.              |
| `%A`        | Full weekday name, for example 'Monday'.                             |
| `%I`        | Hour in 12-hour format, for example 01 for 1 o'clock.                |
| `%Z`        | Timezone name, for example 'UTC'.                                    |

For example, if you want the date and time to be displayed as `Day/Month/Year Hour:Minute:Second`, you would use the pattern `'%d/%m/%Y %H:%M:%S'`.

For a full and complete list of formattinig options, see the table in [this documentation](https://docs.python.org/3/library/datetime.html#strftime-and-strptime-behavior)

## Node Output

* **Current Datetime**: Returns the current date and time in the specified format.

## Node Functionality

The Current Datetime node provides the current date and time as output. The format of this output can be customized by selecting one of the given options or by specifying a custom pattern. It is also possible to choose the timezone in which the current datetime will be represented.

This is particularly useful when you want to timestamp data, log events with the time of occurrence, or when you need to display the current time to users in different parts of the world in a consistent format.

## When To Use

Use the Current Datetime node when you need to:

* Capture the exact time an event occurs within your automated process.
* Insert the current date and time into files, databases, or other outputs.
* Display the current time to users in reports or dashboards.
* Record timestamps in logs for debugging or auditing purposes.

It's an essential tool for any process where time tracking is necessary or where actions are time-dependent.
