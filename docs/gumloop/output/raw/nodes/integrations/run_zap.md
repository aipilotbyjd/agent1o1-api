> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Run Zap

This document outlines the functionality and characteristics of the Run Zap node, which enables Zapier automation triggering from Gumloop workflows.

## Node Inputs

### Required Field

* **Zapier Webhook URL**: Your Zapier webhook trigger URL

### Optional Fields

* **Method**: HTTP method (GET/POST/PATCH)
* **Headers**: Custom HTTP headers
* **Body**: Data to send to Zapier

## Node Output

* **Zapier Response JSON**: Response data from Zapier

## Node Functionality

The Run Zap node triggers Zapier automations via webhooks.

**Key features include**:

* Multiple HTTP methods
* Custom header support
* Data payload sending
* Response handling
* Loop Mode support for batch operations
* Secure webhook communication

## When To Use

The Run Zap node is valuable for cross-platform automation. Common use cases include:

* **Data Transfer**: Send data to other platforms via Zapier
* **Workflow Triggers**: Start Zapier automations
* **Integration Bridge**: Connect Gumloop with other tools
* **Event Notifications**: Trigger notifications through Zapier

## Example

To trigger a Zapier email automation:

1. Basic Setup:
   * Webhook URL: "[https://hooks.zapier.com/hooks/catch/123456/abcdef/](https://hooks.zapier.com/hooks/catch/123456/abcdef/)"
   * Method: "POST"

2. Headers Configuration:
   * Add header:
     * Key: "Authorization"
     * Value: "Bearer 123456789"

3. Body Parameters:
   * Add first parameter:
     * Key: "email"
     * Value: "[user@example.com](mailto:user@example.com)"
     * Value Type: string

   * Add second parameter:
     * Key: "send\_welcome"
     * Value: true
     * Value Type: boolean

   * Add third parameter:
     * Key: "user\_id"
     * Value: 12345
     * Value Type: integer

## Important Considerations:

1. Webhook URL must be from Zapier
2. Headers must be key-value pairs
3. Test webhooks before production use

In summary, the Run Zap node provides reliable Zapier integration, enabling cross-platform automation through webhook triggers and data transmission.
