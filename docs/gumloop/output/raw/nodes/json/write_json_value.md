> ## Documentation Index
> Fetch the complete documentation index at: https://docs.gumloop.com/llms.txt
> Use this file to discover all available pages before exploring further.

# JSON Writer

## Node Inputs

The node accepts the following inputs:

* **Keys**: A string representing the key in the JSON object where the value will be added or updated. For example, if you want to change the `name` property in a JSON, you would provide "name" as the key.

> Each key that is defined is exposed as input to the node. Values passed into these inputs become the values in your JSON.

## Node Output

The node produces the following output:

* **JSON String**: After the node finishes processing the inputs, it outputs a stringified JSON with the updates. This is the new version of the original JSON string, now containing the changes made by adding or updating the value for the specified key.

## Example Usage:

If you set up keys "name" and "age":

* Input values "John" and "25"
* Output: `{"name": "John", "age": "25"}`

## Loop Mode

* When enabled, accepts lists of values
* Creates multiple JSON objects, one for each set of inputs

## When To Use

You can use the `JSON Writer` node whenever you need to manipulate JSON data within an automated process, such as updating configuration settings, modifying response data from an API, or just to ensure certain data fields contain up-to-date information before passing the JSON on to another process or system. This node simplifies tasks that involve the dynamic updating of JSON content without requiring you to write custom code or manually edit JSON strings.
