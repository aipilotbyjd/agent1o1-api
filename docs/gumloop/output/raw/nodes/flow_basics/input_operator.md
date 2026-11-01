> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Input

This document outlines the Input node, which lets you add customizable input values to your workflows.

## Node Inputs

### Required Fields

* **Input Name**: Name for your input (used when calling workflows through webhooks or agents)

### Optional Fields

* **Default Value**: A preset value used if no input is provided

## Node Output

* **Value**: The final input value (either from user input, webhook, or default value)

## Node Functionality

The Input node creates entry points for data in your workflows. It can:

* Fetch values from other workflows [Subflows](https://docs.gumloop.com/core-concepts/subflows)
* Accept values from webhooks
* Use preset default values
* **Multiple inputs** can now be added within a single input node - no need for separate input nodes for each field

## Important Considerations

1. Always set clear Input Names for webhook usage
2. Use Default Values for optional inputs

In summary, the Input node is your workflow's front door for data entry, whether from users, webhooks, or default settings.
