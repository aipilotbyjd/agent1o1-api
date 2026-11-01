> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# GitHub PR Commenter

## Node Inputs

* **PR URL**

  * **Type**: text
  * **Description**: The full URL of the pull request you wish to comment on.
  * **Placeholder**: `https://github.com/user_name/repository_name/pull/PR_number`

* **Comment Text**
  * **Type**: text
  * **Description**: The content of the comment you wish to post.
  * **Placeholder**: `Your comment here...`

## Node Output

* **Updated PR URL**
  * **Type**: text
  * **Description**: A link to the pull request that was updated with the new comment.

# Node Functionality

The GitHub PR Commenter node takes two pieces of information: the URL of a pull request (PR URL) on GitHub and the text you wish to comment with (Comment Text). With these, it posts the comment to the specified pull request, facilitating communication and collaboration on GitHub without the need to manually use the website or interface.

## When To Use

This node can be particularly useful in various scenarios, including but not limited to:

* **Automated Code Review Processes**: When you have an automated system that runs tests or analyses on code and you want to report the results within a pull request.
* **Continuous Integration/Continuous Deployment (CI/CD) Workflows**: During a CI/CD pipeline run, you can use this node to automatically post status updates, warnings, or success messages on the relevant pull request.
* **Project Management Automation**: If your project management tools need to reflect certain actions or updates in a GitHub pull request, this node can be triggered to post standardized messages.
* **Collaboration on Code**: When multiple team members are working on a project and quick automated updates or guidelines need to be posted on pull requests, this node can save time and ensure consistency in communication.
