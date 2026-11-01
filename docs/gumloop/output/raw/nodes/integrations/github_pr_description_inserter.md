> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# GitHub PR Description Inserter

## Node Inputs

* **pr url**: Enter the full URL of the pull request you wish to update. It should be in the format `https://github.com/user_name/repository_name/pull/PR_number`.
* **description text**: Enter the new description content for the pull request. This is the text that you want to appear in the pull request's description field.

## Node Output

* **updated pr url**: The link to the pull request that was updated.

## Node Functionality

This node is designed to update the description of a pull request on GitHub. It requires the URL of the pull request and the new description text. Once provided with these inputs, it will modify the pull request’s description to include the new text.

### When To Use

Use this node when you need to automatically update the description of a pull request, perhaps as part of a larger automated process that involves other steps such as code reviews or deploying changes. This could be helpful if you have a standardized procedure for PR descriptions or need to append additional information without manually editing each PR.
