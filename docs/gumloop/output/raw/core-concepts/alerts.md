> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Alerts

Set up email notifications for workflow failures directly from your workbook's side panel. Get notified when your automations encounter issues, helping you maintain reliable workflows.

> **Note**: Alerts are available on the Pro plan and above.

## Setting Up Alerts

1. Open your workbook's side panel and click the bell icon
2. Click "Add Alerts"
3. Enter your email address where you want to receive notifications
4. Optionally enable "Alert only on trigger-based failures"

<div align="center">
  <img src="https://mintcdn.com/agenthub/0MIzwL1cHHBNpu7Y/images/alerts.png?fit=max&auto=format&n=0MIzwL1cHHBNpu7Y&q=85&s=cb5032022181212096640d7c20b4659d" alt="Alt text" width="800" data-path="images/alerts.png" />
</div>

## Configuration Options

* **Email**: Address where alerts will be sent
* **Alert only on trigger-based failures**: When enabled, you'll only receive notifications when automations fail during externally triggered runs (webhooks, Gmail, Slack, etc.). Manual 'Run' button failures won't send alerts.

<div align="center">
  <img src="https://mintcdn.com/agenthub/dn7emlKONFx9smnZ/images/create_email_alert.png?fit=max&auto=format&n=dn7emlKONFx9smnZ&q=85&s=f975a30b0baf2136f55a4931d46d7bd6" alt="Alt text" width="400" data-path="images/create_email_alert.png" />
</div>

## How It Works

When a workflow failure occurs, you'll receive an email notification containing:

* Which workflow failed
* Run link with the error
* Details about the error

This helps you quickly identify and address issues in your automated workflows.

## Important Considerations

1. Make sure the email address is correct and accessible
2. Consider enabling "Alert only on trigger-based failures" for production workflows
3. Multiple email addresses can be set up for the same workbook
4. Requires Pro plan or above to access alerts feature

Alerts help you maintain reliable automations by keeping you informed of any issues that require attention.

***

## Credit Usage Notifications

Looking for credit usage alerts? Credit notification preferences (out-of-credits alerts and percentage-based usage thresholds) are managed separately on the [Subscription page](https://www.gumloop.com/settings/organization/subscription). See [Credit Notification Preferences](/core-concepts/credits#credit-notification-preferences) for details.
