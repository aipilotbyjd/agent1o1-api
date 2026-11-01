> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Launch Phantom

This document outlines the functionality and usage of the `Launch Phantom` node, which allows users to execute PhantomBuster automations directly within Gumloop workflows. The node is designed to streamline the integration of data from PhantomBuster by automating tasks and waiting for completion before proceeding.

## Node Inputs

The `Launch Phantom` node requires the following inputs:

* **Phantom Name**: The name of the specific [PhantomBuster automation](https://phantombuster.com) you wish to run.

## Node Output

The `Launch Phantom` node outputs a single text result containing the data fetched or processed by the Phantom. This output can be used for further steps within the workflow.

## Node Functionality

The `Launch Phantom` node is designed to run PhantomBuster automations, also known as "Phantoms," directly within Gumloop. Phantoms are pre-configured automation scripts that can perform a variety of tasks on social media platforms, websites, and more. This node can be used to automate the retrieval of data from platforms like LinkedIn, Twitter, Instagram, etc., and bring that data into your workflow.

**Key Features**:

* **Batch Processing**: The node can execute multiple Phantoms in batch mode, processing each one sequentially.
  * You can expose the 'Phantom Name' dynamically for this under 'configure options'
* **Real-Time Execution**: The node waits for the Phantom to complete its execution before continuing with the workflow, ensuring that only fully processed data is passed forward.

## When To Use

The `Launch Phantom` node is useful in scenarios where automated data collection, web scraping, or social media monitoring is needed. Common use cases include:

* **Lead Generation**: Use Phantoms to gather leads from Facebook, Twitter, or other platforms.
* **Social Media Insights**: Automate the retrieval of engagement metrics, follower counts, or profile details.
* **Web Scraping**: Pull structured data from websites to integrate into your analytics or CRM systems.

## Important Considerations

1. **Authentication**: Requires a PhantomBuster API Key – set up the API Key in the [Connectors page](https://www.gumloop.com/personal/connectors).
2. **PhantomBuster Account**: Ensure that your PhantomBuster account has the necessary credits to run the Phantom.
3. **Execution Time**: Depending on the Phantom and the amount of data to process, execution times can vary. Be aware of PhantomBuster's rate limits and usage policies.

In summary, the `Launch Phantom` node provides a seamless way to incorporate PhantomBuster automations within Gumloop workflows, enabling automated data retrieval and web scraping in a reliable, streamlined manner. With simple configuration and secure API-based authentication, it allows for powerful integrations with minimal setup.
