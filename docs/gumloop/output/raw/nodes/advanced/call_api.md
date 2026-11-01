> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# Call API

## Node Inputs

* **method**\
  The HTTP method to be used in the API request. It can be either `GET` (used to retrieve data) or `POST` (used to send or modify data).

* **url**\
  The destination URL of the API call, beginning with `https://`. This URL represents the specific address on the internet where the API is accessed.

* **headers** *(optional)*\
  Additional information sent along with the request. Headers often include authentication tokens, specifying the type of data being sent, and more. Each header consists of a key and a value, like `Authorization: Bearer 1223456789`.

* **body** *(optional)*\
  Data that is sent with the request. This is typically used with `POST` requests where the body contains the information that needs to be processed by the API. The body can be provided in two ways: either as a structured list of key/value pairs with an additional type for each value (e.g., string, integer, boolean, json) or as a properly formatted JSON string.

## Node Output

* **api response**\
  The raw output response from the API after the node executes the request. This is typically in the form of a string containing any data returned by the API, which could be text, numbers, or structured JSON data.

# Node Functionality

This node allows you to interact with external web services or APIs. It can send requests to retrieve data (using `GET` method) or modify data (using `POST` method) at a specified URL. Along with the main request, you can send additional headers for things like authorization or content type declaration. You can also send a body with data associated with your request; for instance, information you want to store or process using the API.

## When To Use

Use the `Call API` node when you need to connect and communicate with an external service or system that offers a web API. This could be for various purposes such as retrieving user data, updating records, triggering remote processes, or simply requesting real-time data from another service. Whether you're integrating with third-party services or connecting to your own backend systems, this node can be configured to fulfill the needed API call requirements.
