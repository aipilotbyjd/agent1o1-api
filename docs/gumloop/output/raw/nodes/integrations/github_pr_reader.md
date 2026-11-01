> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Read GitHub Pull Request

## Node Inputs

The following input is necessary for the node to function properly:

* **pr url**: This input expects text containing the full URL of the pull request you are interested in. An example placeholder URL is provided: `https://github.com/user_name/repository_name/pull/PR_number`. You must replace `user name`, `repository name`, and `PR number` with actual values to specify which pull request to fetch details from.

## Node Output

The node produces the following outputs after successful execution:

* **pr metadata**: A piece of text that contains metadata of the specified GitHub pull request. Metadata includes information like the title, description, creation date, and more.
* **edited file diffs**: A list of text snippets showing the changes made to each file in the pull request. This output is technical and contains updates in code or text made in the pull request.
* **edited file names**: A list of text containing the names of each file that was edited as part of the pull request. This tells you which files were involved in the changes.

## Node Functionality

The "Read GitHub Pull Request" node is designed to retrieve detailed information about a pull request on GitHub. Once the user provides the URL of a pull request, the node interacts with GitHub's services to fetch a variety of information, including the changes made and a summary of the pull request's contents. All the gathered data is formatted and provided as outputs for further use in other processes or analyses.

### When To Use

This node is quite useful when you need to programmatically review and analyze the details of pull requests on GitHub. Scenarios for its use include:

* Automating the extraction of pull request details for reporting purposes.
* Monitoring pull request activity by reading and potentially alerting on recent changes.
* Integrating with other tools or services that require information from GitHub pull requests, such as CI/CD pipelines or code review workflows.

This functionality is helpful for project managers, developers, and DevOps engineers looking to streamline their manual review processes or integrate PR information into other systems.
