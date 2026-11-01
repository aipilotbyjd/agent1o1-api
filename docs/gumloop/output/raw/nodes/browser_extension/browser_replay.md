> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Browser Replay

The Browser Replay node allows you to replay actions recorded from the Gumloop Chrome Extension to scrape content within a workflow. It provides a powerful way to automate web interactions and extract data from websites.

You can replay the same set of actions on different URLs. This can be helpful for workflows on a website where the content changes, but the layout stays the same, for example taking actions on a given LinkedIn Profile.

This node requires the [Gumloop Chrome Extension](https://chromewebstore.google.com/detail/gumloop/hpkeijgchoedhhdfjdjlaimanpmbnhjm) to function properly.

## Node Inputs

This node accepts the following inputs for customization:

* **Select Replay**: The name of the Replay that you want to use.
* **URL**: The starting URL for your Replay.
* **Action**: Define the action you want to take after your Replay has been completed. Options include:
  * Scrape
  * Scrape source
  * Screenshot
  * Screenshot - full page
  * Get all URLs
* **Use Advanced Browsing?**: *(Optional)* Enable this to use advanced browsing techniques with residential proxies. This can be helpful for websites that are prone to blocking automated services.
* **Use My Browser Cookies?**: *(Optional)* Enable this to use cookies from your browser during the workflow execution. This can be helpful for websites in which you need to be "logged in" to use the replay properly. When this option is checked, a modal will pop up telling you to enter in the starting URL for your website. When you enter the URL, a new tab will be opened on your browser to capture the cookies, in order to use them when replaying the action.

## Node Output

The node generates the following output:

* **Action Output**: The result of the action performed at the end of the Replay.

## Node Functionality

The Browser Replay node executes a series of pre-recorded actions on a web page and then performs a final action to extract data or capture the page state.

### Key Features

1. **Replay Actions**: Executes a series of pre-recorded actions such as clicks, inputs, scrolls, and navigations.
2. **Flexible Final Action**: Allows various final actions like scraping text, capturing screenshots, or collecting URLs.
3. **Advanced Browsing**: Option to use residential proxies to avoid common blocks and restrictions.
4. **Cookie Support**: Ability to use browser cookies for maintaining session states.

### When To Use

Use the Browser Replay node when you want to:

* Automate complex web interactions that require multiple steps.
* Extract data from websites that require specific navigation or interaction before the desired content is available.
* Capture screenshots or page sources after a series of interactions.
* Perform web scraping tasks that involve dynamic content or require user authentication.

This node is particularly useful for automating repetitive web tasks, gathering data from complex web applications, or interacting with websites that have dynamic content or require specific user actions.

## More information

* [Video Tutorial](https://www.loom.com/share/6f0b461e81d64d45b483487f5b45e797)
