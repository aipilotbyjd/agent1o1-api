> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Enrich Contact Information

## Overview

The **Enrich Contact Information** node is a powerful tool for gathering detailed contact information about individuals or companies. By using various data sources, this node can help you find specific details, such as email addresses, phone numbers, and LinkedIn profiles, to support outreach, recruitment, and verification efforts.

***

## Node Inputs

1. **Inputs**
   * **Description**: Select the data you have available to help locate the contact information you need. You can provide one or more of the following:
     * **Domain Name**: The website address of the company (e.g., "google.com") where you're looking to find contacts.
     * **First Name**: The first name of the individual you're trying to find (e.g., "Bill").
     * **Last Name**: The last name of the individual (e.g., "Gates").
     * **Job Title**: The role of the person you're searching for (e.g., "Founder").
     * **Company**: The company name where the individual works (e.g., "Microsoft").
     * **LinkedIn URL**: The LinkedIn profile URL of the person you're trying to enrich.

2. **Contact Information**
   * **Description**: Specify the pieces of information you'd like to retrieve about the contact. Available options include:
     * **First Name**: The first name of the contact.
     * **Last Name**: The last name of the contact.
     * **Job Title**: The contact's job title.
     * **Email Address**: The contact's email address.
     * **Phone Number**: The contact's phone number.
     * **LinkedIn URL**: The LinkedIn profile URL of the contact.

3. **Multiple Contact Settings**

   * **Enrich Multiple Contacts?**: Toggle to enable returning multiple contacts that match your search criteria.

   * **Maximum Number of Contacts**: Specify the maximum number of contacts to return (default: 5).

   > **Note**: Not all requested information is guaranteed to be found. If specific contact details are unavailable, empty strings will be outputted.

***

## Node Outputs

The output can include any of the following information, depending on what was requested and available:

* **First Name**: The contact's first name.
* **Last Name**: The contact's last name.
* **Job Title**: The contact's job title within the company.
* **Email Address**: The contact's professional email address.
* **Phone Number**: The contact's phone number.
* **LinkedIn URL**: The LinkedIn profile URL for the contact.

When "Enrich Multiple Contacts" is enabled, each output becomes a list containing multiple values.

***

## Node Functionality

The **Enrich Contact Information** node connects with multiple data providers to enhance your contact data:

* Apollo
* Proxycurl
* Hunter.io
* ZoomInfo (requires API key)

***

## When to Use

Use the **Enrich Contact Information** node in situations where you need to expand on basic information about a contact or company. Ideal scenarios include:

1. **Lead Generation**: Find comprehensive contact details for individuals at a target company.
2. **Sales and Outreach**: Collect accurate contact information before reaching out to prospective clients.
3. **Recruitment Efforts**: Verify job titles and find contact information for potential candidates.
4. **Information Verification**: Confirm existing contact data or fill in missing details.
5. **LinkedIn Search**: Locate the LinkedIn profile of a person based on partial information.
6. **Team Research**: When enabled with multiple contacts, research entire teams or departments.

This node streamlines the process of gathering and verifying contact information, helping you maintain accurate and up-to-date data for outreach and networking efforts.

***

## Additional Details

* **Batch Processing**: Supports loop mode, allowing for the processing of multiple contacts in one run.
* **Credit Cost**:
  * **60 credits** if using providers without API keys.
  * **1 credit** if using providers with API keys.
* **Output Format**: All outputs are provided in list of text `string[]` format if "Enrich Multiple Contacts?" is enabled.
* **Multiple Contact Support**: Return up to 5 contacts (or your specified maximum) matching your search criteria.

> **Important**: Data availability depends on the data sources, and not all requested information is guaranteed to be found.

***

The **Enrich Contact Information** node simplifies the process of gathering detailed contact data, making it easy to reach out to potential leads, contacts, or candidates with confidence in the accuracy of the information obtained.
